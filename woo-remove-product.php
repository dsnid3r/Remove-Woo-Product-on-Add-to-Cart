<?php
/*
Plugin Name: Remove Woo Product on Add to Cart
Plugin URI:  https://thoughtwaveai.com
Description: Automatically removes a specific product from the WooCommerce cart when another product is added.
Version:     1.0
Author:      Dustin Snider
Author URI:  https://thoughtwaveai.com
License:     GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
WC requires at least: 3.0.0
WC tested up to: 5.5.0
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Adds a custom field to the product options Advanced tab.
 */
function rwap_add_custom_advanced_fields() {
    global $post;
    
    // Get all products excluding the current one
    $args = array(
        'post_type' => 'product',
        'posts_per_page' => -1,
        'post_status' => 'publish',
        'fields' => 'ids',
        'exclude' => array( $post->ID ), // Exclude the current product
    );
    $product_ids = get_posts($args);

    echo '<div class="options_group">';
    
    // Create a dropdown
    woocommerce_wp_select(
        array(
            'id'          => '_remove_product_ids',
            'label'       => __('Remove Product(s) from Cart:', 'woocommerce'),
            'options'     => array('' => __('Select products...', 'woocommerce')) + rwap_get_product_options($product_ids),
            'description' => __('Select products that should be removed from the cart when this product is added.', 'woocommerce'),
            'desc_tip'    => true,
            'class'       => 'select short',
            'custom_attributes' => array(
                'multiple' => 'multiple',
                'style' => 'width: 50%;',
            ),
        )
    );
    
    echo '</div>';
}
add_action('woocommerce_product_options_advanced', 'rwap_add_custom_advanced_fields');

/**
 * Helper function to format product options for the dropdown.
 */
function rwap_get_product_options($product_ids) {
    $options = array();
    foreach ($product_ids as $id) {
        $product = wc_get_product($id);
        if ($product) {
            // Use '{ID}: {Name}' format for options
            $options[$id] = "{$id}: {$product->get_name()}";
        }
    }
    return $options;
}

/**
 * Save the custom field value when submitted.
 *
 * @param int $post_id
 */
function rwap_save_custom_field($post_id) {
    $product_ids = isset($_POST['_remove_product_ids']) ? (array) $_POST['_remove_product_ids'] : [];
    if ( ! empty( $product_ids ) ) {
        // Ensure array values are integers
        $product_ids = array_map( 'intval', $product_ids );
        update_post_meta($post_id, '_remove_product_ids', implode(',', $product_ids));
    } else {
        delete_post_meta($post_id, '_remove_product_ids');
    }
}
add_action('woocommerce_process_product_meta', 'rwap_save_custom_field');

/**
 * Removes selected products from the cart when a product is added.
 */
function rwap_remove_products_from_cart($cart_item_data, $product_id) {
    if (is_admin() && !defined('DOING_AJAX')) return $cart_item_data;
    
    $cart = WC()->cart->get_cart();
    $remove_products = get_post_meta($product_id, '_remove_product_ids', true);
    
    if (!empty($remove_products)) {
        $remove_products = explode(',', $remove_products);
        foreach ($cart as $cart_item_key => $cart_item) {
            if (in_array($cart_item['product_id'], $remove_products)) {
                WC()->cart->remove_cart_item($cart_item_key);
            }
        }
    }
    
    return $cart_item_data;
}
add_filter('woocommerce_add_to_cart', 'rwap_remove_products_from_cart', 10, 2);
