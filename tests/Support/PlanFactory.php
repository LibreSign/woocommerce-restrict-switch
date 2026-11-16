<?php

namespace LibreSign\WooRestrictSwitch\Tests\Support;

use WC_Product;
use WC_Product_Grouped;
use WC_Product_Subscription_Variation;
use WC_Product_Variable_Subscription;

final class PlanFactory {

	public function plan( $restricted, $upsells = array() ) {
		$plan = new WC_Product_Variable_Subscription();
		$plan->set_name( 'LibreSign plan' );
		$plan->set_upsell_ids( array_map( static fn ( WC_Product $upsell ) => $upsell->get_id(), $upsells ) );
		$plan->save();

		update_post_meta( $plan->get_id(), 'restrict_herself_upsells_switch', $restricted ? 'yes' : 'no' );

		return $plan;
	}

	public function variation_of( WC_Product $plan ) {
		$variation = new WC_Product_Subscription_Variation();
		$variation->set_parent_id( $plan->get_id() );
		$variation->set_regular_price( '10' );
		$variation->save();

		return $variation;
	}

	public function group( $plans ) {
		$group = new WC_Product_Grouped();
		$group->set_name( 'Upgrade subscription' );
		$group->set_children( array_map( static fn ( WC_Product $plan ) => $plan->get_id(), $plans ) );
		$group->save();

		return $group;
	}

	public function subscribe( $user_id, WC_Product $product, $status = 'active' ) {
		$subscription = wcs_create_subscription(
			array(
				'customer_id'      => $user_id,
				'billing_period'   => 'month',
				'billing_interval' => 1,
			)
		);
		$subscription->add_product( $product );
		$subscription->set_status( $status );
		$subscription->save();

		return $subscription;
	}
}
