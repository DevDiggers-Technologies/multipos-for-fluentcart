<?php
/**
 * Coupon Helper class
 *
 * FluentCart coupons as the POS understands them. FluentCart keeps fixed amounts,
 * the minimum spend and the discount cap in cents, the percentage as a plain number,
 * and restricts by variation IDs and product category IDs.
 *
 * @package MultiPOS for FluentCart
 */

namespace DDFCMultiPOS\Helper\Coupon;

use DDFCMultiPOS\Helper\FluentCart\DDFCPOS_FluentCart_Helper;
use FluentCart\App\Models\AppliedCoupon;
use FluentCart\App\Models\Coupon;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDFCPOS_Coupon_Helper' ) ) {
	/**
	 * Coupon Helper class
	 */
	class DDFCPOS_Coupon_Helper {
		/**
		 * Coupon conditions as an array.
		 *
		 * @param Coupon $coupon Coupon.
		 * @return array
		 */
		public static function ddfcpos_get_conditions( $coupon ) {
			return is_array( $coupon->conditions ) ? $coupon->conditions : (array) json_decode( (string) $coupon->conditions, true );
		}

		/**
		 * Fixed coupon amount in cents.
		 *
		 * @param Coupon $coupon Coupon.
		 * @return int
		 */
		public static function ddfcpos_fixed_amount_cents( $coupon ) {
			return (int) round( floatval( $coupon->amount ) );
		}

		/**
		 * Whether a product line may take the coupon.
		 *
		 * @param array $conditions   Coupon conditions.
		 * @param int   $post_id      Product ID.
		 * @param int   $variation_id Variation ID.
		 * @return bool
		 */
		public static function ddfcpos_line_is_eligible( $conditions, $post_id, $variation_id ) {
			$included = array_map( 'intval', (array) ( isset( $conditions['included_products'] ) ? $conditions['included_products'] : [] ) );
			$excluded = array_map( 'intval', (array) ( isset( $conditions['excluded_products'] ) ? $conditions['excluded_products'] : [] ) );

			if ( $excluded && in_array( (int) $variation_id, $excluded, true ) ) {
				return false;
			}

			if ( $included && ! in_array( (int) $variation_id, $included, true ) ) {
				return false;
			}

			$included_cats = array_map( 'intval', (array) ( isset( $conditions['included_categories'] ) ? $conditions['included_categories'] : [] ) );
			$excluded_cats = array_map( 'intval', (array) ( isset( $conditions['excluded_categories'] ) ? $conditions['excluded_categories'] : [] ) );

			if ( $included_cats || $excluded_cats ) {
				$terms = get_the_terms( $post_id, DDFCPOS_FluentCart_Helper::CATEGORY_TAXONOMY );
				$terms = ! empty( $terms ) && ! is_wp_error( $terms ) ? array_map( 'intval', wp_list_pluck( $terms, 'term_id' ) ) : [];

				if ( $included_cats && ! array_intersect( $included_cats, $terms ) ) {
					return false;
				}

				if ( $excluded_cats && array_intersect( $excluded_cats, $terms ) ) {
					return false;
				}
			}

			return true;
		}

		/**
		 * Why a coupon can not be used right now, or '' when it can.
		 *
		 * @param Coupon   $coupon      Coupon.
		 * @param int      $customer_id FluentCart customer ID.
		 * @param string   $email       Customer email.
		 * @param int|null $spend_cents Cart amount in cents to check the spend limits against, null to skip.
		 * @return string
		 */
		public static function ddfcpos_get_coupon_error( $coupon, $customer_id = 0, $email = '', $spend_cents = null ) {
			if ( 'active' !== $coupon->status ) {
				return esc_html__( 'This coupon is not active.', 'multipos-for-fluentcart' );
			}

			$conditions = self::ddfcpos_get_conditions( $coupon );

			if ( null !== $spend_cents ) {
				$min_spend = isset( $conditions['min_purchase_amount'] ) ? (int) $conditions['min_purchase_amount'] : 0;
				$max_spend = isset( $conditions['max_purchase_amount'] ) ? floatval( $conditions['max_purchase_amount'] ) : 0;

				if ( $min_spend && $spend_cents < $min_spend ) {
					return esc_html__( 'The cart total is below the minimum required to use this coupon.', 'multipos-for-fluentcart' );
				}

				if ( $max_spend && DDFCPOS_FluentCart_Helper::from_cents( $spend_cents ) > $max_spend ) {
					return esc_html__( 'The cart total exceeds the maximum allowed for this coupon.', 'multipos-for-fluentcart' );
				}
			}

			$now = time();

			if ( ! empty( $coupon->start_date ) && '0000-00-00 00:00:00' !== $coupon->start_date && strtotime( $coupon->start_date . ' UTC' ) > $now ) {
				return esc_html__( 'This coupon can not be used yet.', 'multipos-for-fluentcart' );
			}

			if ( ! empty( $coupon->end_date ) && '0000-00-00 00:00:00' !== $coupon->end_date && strtotime( $coupon->end_date . ' UTC' ) < $now ) {
				return esc_html__( 'Sorry, Coupon Expired. Try another one!', 'multipos-for-fluentcart' );
			}

			$max_uses   = isset( $conditions['max_uses'] ) ? (int) $conditions['max_uses'] : 0;

			if ( $max_uses && (int) $coupon->use_count >= $max_uses ) {
				return esc_html__( 'Sorry, Coupon usage limit exceeded', 'multipos-for-fluentcart' );
			}

			$max_per_customer = isset( $conditions['max_per_customer'] ) ? (int) $conditions['max_per_customer'] : 0;

			if ( $max_per_customer && $customer_id ) {
				$used = AppliedCoupon::query()
					->where( 'coupon_id', $coupon->id )
					->whereHas(
						'order',
						function ( $query ) use ( $customer_id ) {
							$query->where( 'customer_id', $customer_id );
						}
					)
					->count();

				if ( $used >= $max_per_customer ) {
					return esc_html__( 'Sorry, Coupon already used at limit by this customer', 'multipos-for-fluentcart' );
				}
			}

			$emails = isset( $conditions['email_restrictions'] ) ? trim( (string) $conditions['email_restrictions'] ) : '';

			if ( $emails ) {
				$allowed = false;

				foreach ( array_filter( array_map( 'trim', explode( ',', $emails ) ) ) as $pattern ) {
					if ( preg_match( '/^' . str_replace( '\*', '.*', preg_quote( $pattern, '/' ) ) . '$/i', (string) $email ) ) {
						$allowed = true;
						break;
					}
				}

				if ( ! $allowed ) {
					return esc_html__( 'This coupon is not valid for this customer.', 'multipos-for-fluentcart' );
				}
			}

			return '';
		}

		/**
		 * Coupon in the shape the POS cart works with: FluentCart's type and condition names,
		 * amounts in decimals. The POS spreads a fixed coupon over the cart, as FluentCart does.
		 * Product restrictions use MultiPOS product IDs.
		 *
		 * @param Coupon $coupon Coupon.
		 * @return array
		 */
		public static function ddfcpos_prepare_pos_coupon( $coupon ) {
			$conditions = self::ddfcpos_get_conditions( $coupon );
			$to_ids     = function ( $variation_ids ) {
				$ids = [];

				foreach ( array_map( 'intval', (array) $variation_ids ) as $variation_id ) {
					$product = \DDFCMultiPOS\Helper\FluentCart\DDFCPOS_Product::from_variation_id( $variation_id );

					if ( $product ) {
						$ids[] = $product->get_id();
					}
				}

				return $ids;
			};

			$is_percent = 'percentage' === $coupon->type;

			return [
				'id'           => (int) $coupon->id,
				'value'        => $is_percent ? round( floatval( $coupon->amount ), 2 ) : DDFCPOS_FluentCart_Helper::from_cents( $coupon->amount ),
				'code'         => $coupon->code,
				'type'         => $is_percent ? 'percentage' : 'fixed',
				'date_expires' => ! empty( $coupon->end_date ) && '0000-00-00 00:00:00' !== $coupon->end_date ? $coupon->end_date : null,
				'restrictions' => [
					'min_purchase_amount' => DDFCPOS_FluentCart_Helper::from_cents( isset( $conditions['min_purchase_amount'] ) ? $conditions['min_purchase_amount'] : 0 ),
					// FluentCart keeps this one as the decimal the admin typed, not cents.
					'max_purchase_amount' => floatval( isset( $conditions['max_purchase_amount'] ) ? $conditions['max_purchase_amount'] : 0 ),
					'max_discount_amount' => DDFCPOS_FluentCart_Helper::from_cents( isset( $conditions['max_discount_amount'] ) ? $conditions['max_discount_amount'] : 0 ),
					'included_products'   => $to_ids( isset( $conditions['included_products'] ) ? $conditions['included_products'] : [] ),
					'excluded_products'   => $to_ids( isset( $conditions['excluded_products'] ) ? $conditions['excluded_products'] : [] ),
					'included_categories' => array_map( 'intval', (array) ( isset( $conditions['included_categories'] ) ? $conditions['included_categories'] : [] ) ),
					'excluded_categories' => array_map( 'intval', (array) ( isset( $conditions['excluded_categories'] ) ? $conditions['excluded_categories'] : [] ) ),
					'email_restrictions'  => array_values( array_filter( array_map( 'trim', explode( ',', isset( $conditions['email_restrictions'] ) ? (string) $conditions['email_restrictions'] : '' ) ) ) ),
					'stackable'           => ! isset( $coupon->stackable ) || 'no' !== $coupon->stackable,
				],
			];
		}
	}
}
