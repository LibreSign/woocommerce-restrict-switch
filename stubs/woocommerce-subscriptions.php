<?php

class WC_Subscription extends WC_Order {}

class WC_Product_Subscription extends WC_Product_Simple {}

class WC_Product_Variable_Subscription extends WC_Product_Variable {}

class WC_Product_Subscription_Variation extends WC_Product_Variation {}

class WC_Subscriptions_Plugin {
	/**
	 * @return self
	 */
	public static function instance() {}

	/**
	 * @return void
	 */
	public function activate_plugin() {}
}

/**
 * @param int $user_id
 * @return WC_Subscription[]
 */
function wcs_get_users_subscriptions( $user_id = 0 ) {}

/**
 * @param array<string, mixed> $args
 * @return WC_Subscription|WP_Error
 */
function wcs_create_subscription( $args = array() ) {}
