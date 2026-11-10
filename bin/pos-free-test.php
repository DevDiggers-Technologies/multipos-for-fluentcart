<?php
/**
 * Runtime check of the free POS order flow and its boundaries against FluentCart.
 *
 * Run on a site with FluentCart and MultiPOS for FluentCart (free) active:
 *   wp eval-file wp-content/plugins/multipos-for-fluentcart/bin/pos-free-test.php
 *
 * It sets up the outlet if there is none, sells one simple product, and checks that the
 * free order endpoint refuses what is MultiPOS Pro: split payments, custom items, variable
 * products and a changed price. Every order it creates is deleted and FluentCart stock put
 * back. Exits non-zero when a check fails.
 *
 * @package MultiPOS for FluentCart
 */

use DDFCMultiPOS\API\Includes\Orders\DDFCPOS_API_Create_Order;
use DDFCMultiPOS\API\Includes\Products\DDFCPOS_API_Get_Products;
use DDFCMultiPOS\Helper\FluentCart\DDFCPOS_Product;
use DDFCMultiPOS\Helper\Outlet\DDFCPOS_Outlet_Helper;
use FluentCart\App\Models\Order;

defined( 'ABSPATH' ) || exit( 1 );

global $wpdb, $ddfcpos_configuration;

if ( ! class_exists( 'DDFCPOS_Free_Init' ) || class_exists( 'DDFCPOS_Init' ) ) {
	echo "Run with the free plugin active and MultiPOS Pro inactive.\n";
	exit( 1 );
}

$failures = 0;
$check    = function ( $label, $condition ) use ( &$failures ) {
	echo ( $condition ? 'PASS ' : 'FAIL ' ) . $label . "\n";
	$failures += $condition ? 0 : 1;
};

$admin = get_users( [ 'role' => 'administrator', 'number' => 1 ] );
wp_set_current_user( $admin[0]->ID );

$saved_outlet = get_option( DDFCPOS_Outlet_Helper::DDFCPOS_OUTLET_OPTION, null );

if ( ! ( new DDFCPOS_Outlet_Helper() )->ddfcpos_get_outlet_option() ) {
	( new DDFCPOS_Outlet_Helper() )->ddfcpos_save_outlet( [ 'name' => 'Test Outlet', 'mode' => 'grocery', 'country' => 'US', 'status' => 'enabled' ] );
}

$outlet_id = DDFCPOS_Outlet_Helper::DDFCPOS_OUTLET_ID;
$simple    = $wpdb->get_row( "SELECT v.id, v.post_id FROM {$wpdb->prefix}fct_product_variations v INNER JOIN {$wpdb->prefix}fct_product_details d ON d.post_id = v.post_id INNER JOIN {$wpdb->posts} p ON p.ID = v.post_id WHERE p.post_status = 'publish' AND d.variation_type = 'simple' AND v.payment_type = 'onetime' AND v.item_price > 0 AND ( SELECT COUNT(*) FROM {$wpdb->prefix}fct_product_variations c WHERE c.post_id = v.post_id ) = 1 ORDER BY v.id DESC LIMIT 1" );
$variable  = $wpdb->get_var( "SELECT v.post_id FROM {$wpdb->prefix}fct_product_variations v GROUP BY v.post_id HAVING COUNT(*) > 1 LIMIT 1" );

if ( ! $simple ) {
	echo "No simple one-time product with a price.\n";
	exit( 1 );
}

$product = DDFCPOS_Product::get( $simple->post_id );
$price   = (float) $product->get_price();
$stock   = $wpdb->get_row( $wpdb->prepare( "SELECT total_stock, available, on_hold, committed, stock_status FROM {$wpdb->prefix}fct_product_variations WHERE id = %d", $simple->id ), ARRAY_A );
$created = [];

$create = function ( $products, $extra = [] ) use ( $outlet_id, $admin ) {
	global $ddfcpos_configuration;

	$order_data = array_merge(
		[
			'customer_id'     => 0,
			'products'        => $products,
			'payment_methods' => [ [ 'slug' => 'cash', 'name' => 'Cash', 'amount' => '100000' ] ],
			'table'           => '',
		],
		$extra
	);

	return ( new DDFCPOS_API_Create_Order( $ddfcpos_configuration ) )->ddfcpos_get_data(
		[
			'outlet_id'  => $outlet_id,
			'cashier_id' => $admin[0]->ID,
			'order_data' => wp_json_encode( $order_data ),
		]
	);
};

$line = function ( $quantity, $unit ) use ( $product ) {
	return [
		'product_id' => $product->get_id(),
		'name'       => $product->get_name(),
		'quantity'   => $quantity,
		'unit_price' => $unit,
	];
};

$order_id = function ( $response ) {
	return is_array( $response ) && ! empty( $response['id'] ) ? (int) $response['id'] : 0;
};

try {
	// Catalogue: simple products only.
	$products_api = new DDFCPOS_API_Get_Products( $ddfcpos_configuration );
	$pos_product  = $products_api->ddfcpos_prepare_product_data( $product->get_id(), $outlet_id, [] );
	$check( 'catalogue: a simple product is offered', is_array( $pos_product ) && 'simple' === $pos_product['type'] );

	if ( $variable ) {
		$check( 'catalogue: a variable product is not offered', false === $products_api->ddfcpos_prepare_product_data( (int) $variable, $outlet_id, [] ) );
	}

	// A normal sale.
	$order     = $create( [ $line( 2, $price ) ] );
	$created[] = $order_id( $order );
	$model     = $created[0] ? Order::query()->find( $created[0] ) : null;
	$check( 'sale: order created and paid', $model && 'paid' === $model->payment_status );
	$check( 'sale: outlet and cashier saved on the order', $model && '1' === (string) $model->getMeta( '_ddfcpos_outlet_id' ) );

	$ledger = $created[0] ? $wpdb->get_row( $wpdb->prepare( "SELECT amount_in, amount_out FROM {$wpdb->prefix}ddfcpos_transactions WHERE order_id = %d", $created[0] ) ) : null;
	$check( 'ledger: one row, cash in less change is the order total', $ledger && abs( ( $ledger->amount_in - $ledger->amount_out ) * 100 - (int) $model->total_amount ) < 1 );

	// The till price can not be changed in the free plugin: the server sells at the catalogue price.
	$cheap     = $create( [ $line( 1, 0.01 ) ] );
	$created[] = $order_id( $cheap );
	$cheap_row = end( $created ) ? $wpdb->get_row( $wpdb->prepare( "SELECT unit_price FROM {$wpdb->prefix}fct_order_items WHERE order_id = %d", end( $created ) ) ) : null;
	$check( 'boundary: a lowered unit price is replaced by the catalogue price', $cheap_row && (int) $cheap_row->unit_price === (int) $product->get_price_cents() );

	// Split payments, custom items and variable products are MultiPOS Pro.
	$split = $create( [ $line( 1, $price ) ], [ 'payment_methods' => [ [ 'slug' => 'cash', 'name' => 'Cash', 'amount' => '1' ], [ 'slug' => 'cash', 'name' => 'Cash', 'amount' => '100000' ] ] ] );
	$check( 'boundary: two payment methods are refused', is_wp_error( $split ) );

	$custom = $create( [ [ 'product_id' => 'custom_1', 'name' => 'Anything', 'quantity' => 1, 'unit_price' => 5, 'is_custom' => true ] ] );
	$check( 'boundary: a custom item is refused', is_wp_error( $custom ) );

	if ( $variable ) {
		$variation = $create( [ [ 'product_id' => (int) $variable, 'name' => 'Variable', 'quantity' => 1, 'unit_price' => 5 ] ] );
		$check( 'boundary: a variable product is refused', is_wp_error( $variation ) );
	}

	$refund_tender = $create( [ $line( 1, $price ) ], [ 'payment_methods' => [ [ 'slug' => 'refund', 'name' => 'Refund', 'amount' => '100000' ] ] ] );
	$check( 'boundary: a ledger word is not a payment method', is_wp_error( $refund_tender ) );

	// The same sale key sent twice makes one order.
	$key       = 'test' . wp_rand();
	$first     = $create( [ $line( 1, $price ) ], [ 'sale_key' => $key ] );
	$again     = $create( [ $line( 1, $price ) ], [ 'sale_key' => $key ] );
	$created[] = $order_id( $first );
	$check( 'retry: a resent sale returns the first order', $order_id( $first ) && $order_id( $first ) === $order_id( $again ) );

	// The free routes are registered for a POS user, and the Pro-only ones are not. Routes are
	// added on init for a logged-in user, so register them again now that a user is set.
	$server = rest_get_server();
	( new \DDFCMultiPOS\API\DDFCPOS_API_Register_Routes( $ddfcpos_configuration ) )->ddfcpos_register_api_routes();
	$routes = $server->get_routes();
	$check( 'routes: /ddfcpos/v1/create-order is registered', isset( $routes['/ddfcpos/v1/create-order'] ) );
	$check( 'routes: /ddfcpos/v1/get-transactions is registered', isset( $routes['/ddfcpos/v1/get-transactions'] ) );
	foreach ( [ 'create-refund', 'create-transaction', 'close-register', 'register-summary', 'verify-manager-pin', 'manage-quick-keys', 'get-outlet-stock', 'get-customer-insights', 'manage-kitchen-orders' ] as $route ) {
		$check( "routes: /ddfcpos/v1/{$route} is not registered", ! isset( $routes[ "/ddfcpos/v1/{$route}" ] ) );
	}
} finally {
	foreach ( array_filter( $created ) as $id ) {
		$wpdb->delete( "{$wpdb->prefix}ddfcpos_transactions", [ 'order_id' => $id ] );
		$model = Order::query()->find( $id );

		if ( $model ) {
			$model->delete();
		}
	}

	$wpdb->update( "{$wpdb->prefix}fct_product_variations", $stock, [ 'id' => $simple->id ] );

	if ( null === $saved_outlet ) {
		delete_option( DDFCPOS_Outlet_Helper::DDFCPOS_OUTLET_OPTION );
	} else {
		update_option( DDFCPOS_Outlet_Helper::DDFCPOS_OUTLET_OPTION, $saved_outlet );
	}
}

echo $failures ? "\n{$failures} check(s) failed.\n" : "\nAll checks passed.\n";
exit( $failures ? 1 : 0 );
