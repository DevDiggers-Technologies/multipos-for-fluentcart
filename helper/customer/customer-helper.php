<?php
/**
 * Customer Helper class
 *
 * POS customers are FluentCart customers (wp_fct_customers). They may or may not have a
 * WordPress account. Their address and phone live in FluentCart's customer addresses.
 *
 * @package MultiPOS for FluentCart
 */

namespace DDFCMultiPOS\Helper\Customer;

use FluentCart\App\Models\Customer;
use FluentCart\App\Models\CustomerAddresses;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDFCPOS_Customer_Helper' ) ) {
	/**
	 * Customer Helper class
	 */
	class DDFCPOS_Customer_Helper {
		/**
		 * Option holding the walk-in (default guest) customer ID.
		 */
		const DEFAULT_CUSTOMER_OPTION = '_ddfcpos_default_customer';

		/**
		 * The walk-in customer every sale without a named customer is booked to. Created on first use.
		 *
		 * @return Customer|null
		 */
		public static function ddfcpos_get_walkin_customer() {
			$customer_id = absint( get_option( self::DEFAULT_CUSTOMER_OPTION ) );
			$customer    = $customer_id ? Customer::query()->find( $customer_id ) : null;

			if ( $customer ) {
				return $customer;
			}

			$host  = wp_parse_url( home_url(), PHP_URL_HOST );
			$email = sanitize_email( 'walk-in@' . ( $host && false !== strpos( $host, '.' ) ? $host : 'example.com' ) );

			$customer = Customer::query()->where( 'email', $email )->first();

			if ( ! $customer ) {
				$customer = Customer::query()->create(
					[
						'email'      => $email,
						'first_name' => esc_html__( 'Walk-in', 'devdiggers-multipos-for-fluentcart' ),
						'last_name'  => esc_html__( 'Customer', 'devdiggers-multipos-for-fluentcart' ),
						'status'     => 'active',
					]
				);
			}

			if ( $customer ) {
				update_option( self::DEFAULT_CUSTOMER_OPTION, (string) $customer->id );
			}

			return $customer;
		}

		/**
		 * Customer an order is booked to. Falls back to the walk-in customer.
		 *
		 * @param int $customer_id FluentCart customer ID.
		 * @return Customer|null
		 */
		public static function ddfcpos_get_order_customer( $customer_id ) {
			$customer = absint( $customer_id ) ? Customer::query()->find( absint( $customer_id ) ) : null;

			return $customer ? $customer : self::ddfcpos_get_walkin_customer();
		}

		/**
		 * Primary address of a type, or the latest one.
		 *
		 * @param int    $customer_id Customer ID.
		 * @param string $type        billing or shipping.
		 * @return CustomerAddresses|null
		 */
		public static function ddfcpos_get_address( $customer_id, $type = 'billing' ) {
			return CustomerAddresses::query()
				->where( 'customer_id', $customer_id )
				->where( 'type', $type )
				->orderBy( 'is_primary', 'DESC' )
				->orderBy( 'id', 'DESC' )
				->first();
		}

		/**
		 * Save the POS form fields to the customer and its billing and shipping addresses.
		 *
		 * @param Customer $customer Customer.
		 * @param array    $data     first_name, last_name, email, phone, address_1, address_2, city, state, postcode, country.
		 * @return void
		 */
		public static function ddfcpos_save_details( $customer, $data ) {
			$customer->first_name = sanitize_text_field( isset( $data['first_name'] ) ? $data['first_name'] : '' );
			$customer->last_name  = sanitize_text_field( isset( $data['last_name'] ) ? $data['last_name'] : '' );
			$customer->email      = sanitize_email( $data['email'] );
			$customer->country    = sanitize_text_field( isset( $data['country'] ) ? $data['country'] : '' );
			$customer->state      = sanitize_text_field( isset( $data['state'] ) ? $data['state'] : '' );
			$customer->city       = sanitize_text_field( isset( $data['city'] ) ? $data['city'] : '' );
			$customer->postcode   = sanitize_text_field( isset( $data['postcode'] ) ? $data['postcode'] : '' );
			$customer->save();

			$phone = sanitize_text_field( isset( $data['phone'] ) ? $data['phone'] : '' );
			$customer->updateMeta( 'ddfcpos_phone', $phone );

			$fields = [
				'name'      => trim( $customer->first_name . ' ' . $customer->last_name ),
				'email'     => $customer->email,
				'phone'     => $phone,
				'address_1' => sanitize_text_field( isset( $data['address_1'] ) ? $data['address_1'] : '' ),
				'address_2' => sanitize_text_field( isset( $data['address_2'] ) ? $data['address_2'] : '' ),
				'city'      => $customer->city,
				'state'     => $customer->state,
				'postcode'  => $customer->postcode,
				'country'   => $customer->country,
			];

			// Nothing but a name and email typed: no address to keep.
			if ( ! array_filter( array_intersect_key( $fields, array_flip( [ 'phone', 'address_1', 'address_2', 'city', 'state', 'postcode', 'country' ] ) ) ) ) {
				return;
			}

			foreach ( [ 'billing', 'shipping' ] as $type ) {
				$address = self::ddfcpos_get_address( $customer->id, $type );

				if ( $address ) {
					$address->fill( $fields );
					$address->save();
				} else {
					CustomerAddresses::query()->create(
						array_merge(
							$fields,
							[
								'customer_id' => $customer->id,
								'type'        => $type,
								'is_primary'  => 1,
								'status'      => 'active',
							]
						)
					);
				}
			}
		}

		/**
		 * Customer in the shape the POS app works with.
		 *
		 * @param Customer|int $customer  Customer or ID.
		 * @param int          $default_id Default (walk-in) customer ID.
		 * @return array|null
		 */
		public static function ddfcpos_prepare_pos_customer( $customer, $default_id = 0 ) {
			if ( ! $customer instanceof Customer ) {
				$customer = $customer ? Customer::query()->find( absint( $customer ) ) : null;
			}

			if ( ! $customer ) {
				return null;
			}

			$default_id = $default_id ? $default_id : absint( get_option( self::DEFAULT_CUSTOMER_OPTION ) );
			$billing    = self::ddfcpos_get_address( $customer->id, 'billing' );
			$shipping   = self::ddfcpos_get_address( $customer->id, 'shipping' );
			$user       = $customer->user_id ? get_userdata( $customer->user_id ) : false;
			$phone      = $billing && $billing->phone ? (string) $billing->phone : (string) $customer->getMeta( 'ddfcpos_phone', '' );
			$address    = function ( $row ) use ( $customer, $phone ) {
				return [
					'first_name' => $customer->first_name,
					'last_name'  => $customer->last_name,
					'company'    => '',
					'address_1'  => $row ? (string) $row->address_1 : '',
					'address_2'  => $row ? (string) $row->address_2 : '',
					'city'       => $row ? (string) $row->city : (string) $customer->city,
					'state'      => $row ? (string) $row->state : (string) $customer->state,
					'postcode'   => $row ? (string) $row->postcode : (string) $customer->postcode,
					'country'    => $row ? (string) $row->country : (string) $customer->country,
					'email'      => $customer->email,
					'phone'      => $phone,
				];
			};

			$display_name = trim( $customer->first_name . ' ' . $customer->last_name );

			return apply_filters(
				'ddfcpos/customer/data',
				[
					'id'           => (int) $customer->id,
					'user_id'      => (int) $customer->user_id,
					'email'        => $customer->email,
					'first_name'   => $customer->first_name,
					'last_name'    => $customer->last_name,
					'display_name' => $display_name ? $display_name : ( $user ? $user->display_name : $customer->email ),
					'username'     => $user ? $user->user_login : '',
					'avatar_url'   => get_avatar_url( $customer->user_id ? $customer->user_id : $customer->email ),
					'coupons'      => [],
					'phone'        => $phone,
					'default'      => (int) $customer->id === (int) $default_id ? 1 : 0,
					'billing'      => $address( $billing ),
					'shipping'     => $address( $shipping ? $shipping : $billing ),
				],
				$customer
			);
		}
	}
}
