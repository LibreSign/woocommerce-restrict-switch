<?php
/**
 * WooCommerce Restrict switch
 *
 * @package   woocommerce-restrict-switch
 * @author    LibreCode <contact@librecode.coop>
 * @license   GPL-2.0+
 * @link      http://github.com/libresign/woocommerce-restrict-switch
 * @copyright 2024 LibreCode
 *
 * @wordpress-plugin
 * Plugin Name:       WooCommerce Restrict switch
 * Plugin URI:        https://github.com/LibreSign/woocommerce-restrict-switch
 * Description:       Restrict switch to products that isn't upsell of a produdct.
 * Version:           0.0.1
 * Author:            LibreCode
 * Author URI:        https://github.com/LibreSign
 * Text Domain:       woocommerce-restrict-switch
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * GitHub Plugin URI: https://github.com/LibreSign/woocommerce-restrict-switch
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Filter related products
add_filter( 'woocommerce_related_products', 'exclude_specific_products_from_related', 10, 3 );

function exclude_specific_products_from_related( $related_products, $product_id, $args ) {
    $excluded_ids = wrd_disallowed_switch_to();

    $related_products = array_diff( $related_products, $excluded_ids );

    return $related_products;
}

// Filter children products
add_filter('woocommerce_product_get_children', 'wrd_product_get_children', 10, 2);

function wrd_product_get_children($children, $product) {
    if (wrd_allow_switching() === 'no' || !is_user_logged_in() || is_admin() || ! $product instanceof WC_Product_Grouped) {
        return $children;
    }
    $switch_to = wrd_allow_switch_to($children);
    $intersect = array_intersect($children, $switch_to);
    return $intersect;
}

function wrd_allow_switch_to(array $deny_list = []):array {
    $user_id = get_current_user_id();
    $subscriptions = wcs_get_users_subscriptions($user_id);
    $switch_to = [];
    foreach ($subscriptions as $subscription) {
        $items = $subscription->get_items();
        foreach ($items as $item) {
            $current_product = $item->get_product();
            if ($current_product instanceof WC_Product_Subscription_Variation) {
                $current_product = wc_get_product($current_product->get_parent_id());
            } else {
                $current_product = $item->get_product();
            }
            if (!in_array($current_product->get_id(), $deny_list)) {
                continue;
            }
            $restrict_herself_upsells_switch = get_post_meta($current_product->get_id(), 'restrict_herself_upsells_switch', true);
            if ($restrict_herself_upsells_switch !== 'yes') {
                continue;
            }
            $switch_to = array_merge($switch_to, $current_product->get_upsell_ids());
            $switch_to[] = $current_product->get_id();
        }
    }
    return $switch_to;
}

// Filter post list
add_action( 'pre_get_posts', 'wrd_hide_products_for_authenticated_users' );

function wrd_hide_products_for_authenticated_users( $q ) {
    if ( !is_user_logged_in() || is_admin() || !$q->is_main_query() || wrd_allow_switching() === 'no' ) {
        return;
    }
    $restricted_switch = wrd_disallowed_switch_to();
    if (!$restricted_switch) {
        return;
    }
    $not_in = array_merge(
        $q->get('post__not_in'),
        $restricted_switch,
    );
    $q->set( 'post__not_in', $not_in );
}

function wrd_disallowed_switch_to(): array {
    $user_id = get_current_user_id();
    $subscriptions = wcs_get_users_subscriptions($user_id);
    $restricted_switch = [];
    foreach ($subscriptions as $subscription) {
        $items = $subscription->get_items();
        foreach ($items as $item) {
            $current_product = $item->get_product();
            if ($current_product instanceof WC_Product_Subscription_Variation) {
                $current_product = wc_get_product($current_product->get_parent_id());
            } else {
                $current_product = $item->get_product();
            }
            $restrict_herself_upsells_switch = get_post_meta($current_product->get_id(), 'restrict_herself_upsells_switch', true);
            if ($restrict_herself_upsells_switch !== 'yes') {
                continue;
            }
            $grouped_products = wrd_get_grouped_products_containing_product($current_product->get_id());
            $upsell_ids = $current_product->get_upsell_ids();
            $restricted_switch = array_merge(
                $restricted_switch,
                array_diff($grouped_products, $upsell_ids, [$current_product->get_id()]),
            );
        }
    }
    return $restricted_switch;
}

function wrd_get_grouped_products_containing_product( $product_id ) {
    global $wpdb;

    $product_id = (int) $product_id;
    $query = $wpdb->prepare(<<<SQL
        SELECT post_id, meta_value
          FROM $wpdb->postmeta
         WHERE meta_key = '_children'
           AND meta_value LIKE %s
        SQL,
        '%' . $wpdb->esc_like( 'i:' . $product_id . ';' ) . '%'
    );

    $results = $wpdb->get_results( $query );
    $return = [];
    foreach ($results as $row) {
        $return = array_merge($return, unserialize($row->meta_value));
    }
    return $return;
}

add_action('woocommerce_product_options_related', 'wrd_add_restrict_herself_upsells_switch');

function wrd_add_restrict_herself_upsells_switch() {
    if (wrd_allow_switching() === 'no') {
        return;
    }
    woocommerce_wp_checkbox(array(
        'id'            => 'restrict_herself_upsells_switch',
        'label'         => __('Restrict switch', 'woocommerce'),
        'description'   => __('Check this box to switch only between herself and upcells.', 'woocommerce')
    ));
}

add_action('woocommerce_process_product_meta', 'wrd_save_restrict_herself_upsells_switch');

function wrd_save_restrict_herself_upsells_switch($post_id) {
    $restrict_herself_upsells_switch = isset($_POST['restrict_herself_upsells_switch']) ? 'yes' : 'no';
    update_post_meta($post_id, 'restrict_herself_upsells_switch', $restrict_herself_upsells_switch);
}

function wrd_allow_switching(): string {
    $allow_switching = get_option( 'woocommerce_subscriptions_allow_switching', 'no' );
    if ( ! in_array( $allow_switching, array( 'no', 'variable', 'grouped', 'variable_grouped' ) ) ) {
        return 'no';
    }
    return $allow_switching;
}
