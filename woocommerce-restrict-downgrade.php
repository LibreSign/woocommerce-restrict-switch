<?php
/**
 * WooCommerce Restrict Downgrade
 *
 * @package   woocommerce-restrict-downgrade
 * @author    LibreCode <contact@librecode.coop>
 * @license   GPL-2.0+
 * @link      http://github.com/libresign/woocommerce-restrict-downgrade
 * @copyright 2024 LibreCode
 *
 * @wordpress-plugin
 * Plugin Name:       WooCommerce Restrict Downgrade
 * Plugin URI:        https://github.com/LibreSign/woocommerce-restrict-downgrade
 * Description:       Restrict downgrade products when using the plugin WooCommerce Subscriptions with the option to allow upgrade or downgrade.
 * Version:           0.0.1
 * Author:            LibreCode
 * Author URI:        https://github.com/LibreSign
 * Text Domain:       woocommerce-restrict-downgrade
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * GitHub Plugin URI: https://github.com/LibreSign/woocommerce-restrict-downgrade
 */

add_filter('woocommerce_product_get_children', 'wrd_product_get_children', 10, 2);

function wrd_product_get_children($children, $product) {
    if (wrd_allow_switching() === 'no' || !is_user_logged_in() || is_admin() || ! $product instanceof WC_Product_Grouped) {
        return $children;
    }
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
            if (!in_array($current_product->get_id(), $children)) {
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
    $intersect = array_intersect($children, $switch_to);
    return $intersect;
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
