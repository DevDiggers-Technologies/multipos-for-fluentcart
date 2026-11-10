<?php
/**
 * Common functions class
 *
 * @package MultiPOS for FluentCart
 * @version 1.0.0
 */

namespace DDFCMultiPOS\Includes\Common;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDFCPOS_Common_Functions' ) ) {
	/**
	 * Common functions
	 */
	class DDFCPOS_Common_Functions {
		/**
		 * Configuration Variable
		 *
		 * @var array
		 */
		protected $ddfcpos_configuration;

		/**
		 * Construct
		 */
		public function __construct( $ddfcpos_configuration ) {
			$this->ddfcpos_configuration = $ddfcpos_configuration;
		}

		/**
		 * Add SVG icons
		 *
		 * @param array $default_svg_icons
		 * @param array $args
		 * @return array
		 */
		public function ddfcpos_add_svg_icons( $default_svg_icons, $args ) {
			$size         = ! empty( $args['size'] ) ? $args['size'] : '24';
			$size_attr    = 'width="' . $size . '" height="' . $size . '"';
			$stroke_color = ! empty( $args['stroke_color'] ) ? $args['stroke_color'] : 'currentColor';
			$stroke_width = isset( $args['stroke_width'] ) ? $args['stroke_width'] : '2';
			$fill         = ! empty( $args['fill'] ) ? $args['fill'] : 'none';

			$stroke_svg = '<svg xmlns="http://www.w3.org/2000/svg" ' . $size_attr . ' viewBox="0 0 24 24" fill="' . $fill . '" stroke="' . $stroke_color . '" stroke-width="' . $stroke_width . '" stroke-linecap="round" stroke-linejoin="round">';

			$svg_icons = [
				'permissions' => $stroke_svg . '<path d="M12 3l7 3v5c0 4.5-3 8.3-7 10-4-1.7-7-5.5-7-10V6l7-3z"></path><path d="M9.5 12l1.8 1.8L15 10"></path></svg>',
				'register'    => $stroke_svg . '<rect x="3" y="11" width="18" height="9" rx="2"></rect><path d="M7 11V5h10v6"></path><path d="M10 8h4"></path><path d="M7 15.5h2"></path><path d="M15 15.5h2"></path></svg>',
				'payments' => '<svg xmlns="http://www.w3.org/2000/svg" ' . $size_attr . ' viewBox="0 0 24 24" fill="' . $fill . '" stroke="' . $stroke_color . '" stroke-width="' . $stroke_width . '" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect><line x1="1" y1="10" x2="23" y2="10"></line></svg>',
				'login'    => '<svg xmlns="http://www.w3.org/2000/svg" ' . $size_attr . ' viewBox="0 0 24 24" fill="' . $fill . '" stroke="' . $stroke_color . '" stroke-width="' . $stroke_width . '" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path><polyline points="10 17 15 12 10 7"></polyline><line x1="15" y1="12" x2="3" y2="12"></line></svg>',
				'printer'  => '<svg xmlns="http://www.w3.org/2000/svg" ' . $size_attr . ' viewBox="0 0 24 24" fill="' . $fill . '" stroke="' . $stroke_color . '" stroke-width="' . $stroke_width . '" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>',
				'pwa'      => '<svg xmlns="http://www.w3.org/2000/svg" ' . $size_attr . ' viewBox="0 0 24 24" fill="' . $fill . '" stroke="' . $stroke_color . '" stroke-width="' . $stroke_width . '" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"></rect><line x1="12" y1="18" x2="12.01" y2="18"></line></svg>',
				'layout'            => '<svg xmlns="http://www.w3.org/2000/svg" ' . $size_attr . ' viewBox="0 0 24 24" fill="' . $fill . '" stroke="' . $stroke_color . '" stroke-width="' . $stroke_width . '" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="3" rx="2"></rect><path d="M3 9h18"></path><path d="M9 21V9"></path></svg>',
				'tables'   => '<svg xmlns="http://www.w3.org/2000/svg" ' . $size_attr . ' viewBox="0 0 24 24" fill="' . $fill . '" stroke="' . $stroke_color . '" stroke-width="' . $stroke_width . '" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>',
			];

			return array_merge( $default_svg_icons, $svg_icons );
		}

		/**
		 * Hold back FluentCart emails for POS sales: all of them when "order emails" is off, and
		 * always the shipping ones (the customer took the goods at the till).
		 *
		 * @param bool  $send Whether to send.
		 * @param array $args event, mail_name, order.
		 * @return bool
		 */
		public function ddfcpos_filter_fluentcart_emails( $send, $args ) {
			$order = isset( $args['order'] ) ? $args['order'] : null;

			if ( ! $send || ! $order || empty( $order->id ) ) {
				return $send;
			}

			$config = is_array( $order->config ) ? $order->config : [];

			if ( empty( $config['source'] ) || 'multipos' !== $config['source'] ) {
				return $send;
			}

			if ( ! empty( $args['event'] ) && false !== strpos( $args['event'], 'shipping_status' ) ) {
				return false;
			}

			return ! empty( $this->ddfcpos_configuration['order_mails_enabled'] );
		}
	}
}
