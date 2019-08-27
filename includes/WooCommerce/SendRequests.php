<?php
namespace Appsero\Helper\WooCommerce;

use Appsero\Helper\Traits\Hooker;

/**
 * SendRequests Class
 * Send request to appsero sever
 */
class SendRequests {

    use Hooker;
    use UseCases\SendRequestsHelper;

    public function __construct() {
        // Add or Update order with license
        $this->action( 'woocommerce_order_status_changed', 'order_status_changed', 20, 4 );

        $this->action( 'before_delete_post', 'delete_order', 8, 1 );
    }

    /**
     * Order status chnage
     */
    public function order_status_changed( $order_id, $status_from, $status_to, $order ) {
        require_once __DIR__ . '/Orders.php';

        $connected = get_option( 'appsero_connected_products', [] );

        foreach( $order->get_items( 'line_item' ) as $wooItem ) {
            $ordersObject = new Orders();
            $ordersObject->product_id = $wooItem->get_product_id();

            // Check the product is connected with appsero
            if ( in_array( $ordersObject->product_id, $connected ) ) {
                $orderData = $ordersObject->get_order_data( $order, $wooItem );

                $orderData['licenses'] = $this->get_order_licenses( $order, $ordersObject->product_id, $wooItem );

                $route = 'public/' . $ordersObject->product_id . '/update-order';

                appsero_helper_remote_post( $route, $orderData );
            }
        }
    }

    /**
     * Get licenses of active add-on
     */
    private function get_order_licenses( $order, $product_id, $wooItem ) {
        require_once __DIR__ . '/Licenses.php';

        $licensesObject = new Licenses();

        return $this->get_order_item_licenses( $order, $product_id, $licensesObject, $wooItem );
    }

    /**
     * Delete order
     */
    public function delete_order( $order_id ) {
        // We check if the global post type isn't order and just return
        global $post_type;
        if ( $post_type != 'shop_order' ) return;

        $order     = wc_get_order( $order_id );
        $connected = get_option( 'appsero_connected_products', [] );

        foreach ( $order->get_items( 'line_item' ) as $wooItem ) {
            $product_id = $wooItem->get_product_id();

            // Check the product is connected with appsero
            if ( in_array( $product_id, $connected ) ) {
                $route = 'public/' . $product_id . '/delete-order/' . $order_id;

                appsero_helper_remote_post( $route, [] );
            }
        }
    }

}