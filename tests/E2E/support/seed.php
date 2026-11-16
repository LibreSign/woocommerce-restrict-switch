<?php

wc_transaction_query( 'start' );

$plans = array();

foreach ( array( 'Basic', 'Professional', 'Enterprise' ) as $plan_name ) {
	$plan = new WC_Product_Subscription();
	$plan->set_name( $plan_name );
	$plan->set_regular_price( '10' );
	$plan->set_virtual( true );
	$plan->update_meta_data( '_subscription_price', '10' );
	$plan->update_meta_data( '_subscription_period', 'month' );
	$plan->update_meta_data( '_subscription_period_interval', '1' );
	$plan->update_meta_data( 'restrict_herself_upsells_switch', 'yes' );
	$plan->save();

	$plans[ $plan_name ] = $plan;
}

foreach ( array( 'Basic', 'Professional' ) as $plan_name ) {
	$plans[ $plan_name ]->set_upsell_ids( array( $plans['Enterprise']->get_id() ) );
	$plans[ $plan_name ]->save();
}

$upgrade_group = new WC_Product_Grouped();
$upgrade_group->set_name( 'Upgrade subscription' );
$upgrade_group->set_slug( 'upgrade-subscription' );
$upgrade_group->set_children( array_map( static fn ( $plan ) => $plan->get_id(), array_values( $plans ) ) );
$upgrade_group->save();

$customer_id = wp_insert_user(
	array(
		'user_login' => 'customer',
		'user_pass'  => 'password',
		'user_email' => 'customer@example.org',
		'role'       => 'customer',
	)
);

$first_order = wc_create_order( array( 'customer_id' => $customer_id ) );
$first_order->add_product( $plans['Basic'] );
$first_order->calculate_totals();
$first_order->update_status( 'completed' );

$subscription = wcs_create_subscription(
	array(
		'order_id'         => $first_order->get_id(),
		'customer_id'      => $customer_id,
		'billing_period'   => 'month',
		'billing_interval' => 1,
	)
);
$subscription->add_product( $plans['Basic'] );
$subscription->calculate_totals();
$subscription->update_status( 'active' );

wc_transaction_query( 'commit' );
