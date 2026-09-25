<?php

wc_transaction_query( 'start' );

$wrd_plans = array();

foreach ( array( 'Basic', 'Professional', 'Enterprise' ) as $wrd_name ) {
	$wrd_plan = new WC_Product_Subscription();
	$wrd_plan->set_name( $wrd_name );
	$wrd_plan->set_regular_price( '10' );
	$wrd_plan->set_virtual( true );
	$wrd_plan->update_meta_data( '_subscription_price', '10' );
	$wrd_plan->update_meta_data( '_subscription_period', 'month' );
	$wrd_plan->update_meta_data( '_subscription_period_interval', '1' );
	$wrd_plan->update_meta_data( 'restrict_herself_upsells_switch', 'yes' );
	$wrd_plan->save();

	$wrd_plans[ $wrd_name ] = $wrd_plan;
}

foreach ( array( 'Basic', 'Professional' ) as $wrd_name ) {
	$wrd_plans[ $wrd_name ]->set_upsell_ids( array( $wrd_plans['Enterprise']->get_id() ) );
	$wrd_plans[ $wrd_name ]->save();
}

$wrd_group = new WC_Product_Grouped();
$wrd_group->set_name( 'Upgrade subscription' );
$wrd_group->set_slug( 'upgrade-subscription' );
$wrd_group->set_children( array_map( static fn ( $plan ) => $plan->get_id(), array_values( $wrd_plans ) ) );
$wrd_group->save();

$wrd_customer = wp_insert_user(
	array(
		'user_login' => 'customer',
		'user_pass'  => 'password',
		'user_email' => 'customer@example.org',
		'role'       => 'customer',
	)
);

$wrd_order = wc_create_order( array( 'customer_id' => $wrd_customer ) );
$wrd_order->add_product( $wrd_plans['Basic'] );
$wrd_order->calculate_totals();
$wrd_order->update_status( 'completed' );

$wrd_subscription = wcs_create_subscription(
	array(
		'order_id'         => $wrd_order->get_id(),
		'customer_id'      => $wrd_customer,
		'billing_period'   => 'month',
		'billing_interval' => 1,
	)
);
$wrd_subscription->add_product( $wrd_plans['Basic'] );
$wrd_subscription->calculate_totals();
$wrd_subscription->update_status( 'active' );

wc_transaction_query( 'commit' );
