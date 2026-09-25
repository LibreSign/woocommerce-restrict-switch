<?php

namespace LibreSign\WooRestrictSwitch\Tests\Integration;

use LibreSign\WooRestrictSwitch\Tests\Support\PlanFactory;
use WP_Query;
use WP_UnitTestCase;

final class WoocommerceRestrictSwitchTest extends WP_UnitTestCase {

	private $plans;

	private $basic;

	private $professional;

	private $enterprise;

	private $group;

	private $customer;

	public function set_up() {
		parent::set_up();

		update_option( 'woocommerce_subscriptions_allow_switching', 'grouped' );

		$this->plans        = new PlanFactory();
		$this->enterprise   = $this->plans->plan( true );
		$this->basic        = $this->plans->plan( true, array( $this->enterprise ) );
		$this->professional = $this->plans->plan( true, array( $this->enterprise ) );
		$this->group        = $this->plans->group( array( $this->basic, $this->professional, $this->enterprise ) );
		$this->customer     = self::factory()->user->create( array( 'role' => 'customer' ) );
	}

	public function tear_down() {
		unset( $_POST['restrict_herself_upsells_switch'] );
		set_current_screen( 'front' );

		parent::tear_down();
	}

	private function subscribe_to( $plan ) {
		$this->plans->subscribe( $this->customer, $this->plans->variation_of( $plan ) );
		wp_set_current_user( $this->customer );
	}

	private function plans_offered_in_the_group() {
		return wc_get_product( $this->group->get_id() )->get_children();
	}

	private function ids( ...$plans ) {
		return array_map( static fn ( $plan ) => $plan->get_id(), $plans );
	}

	/**
	 * @dataProvider provide_switching_options
	 */
	public function test_reads_the_switching_option_of_subscriptions( $option, $expected ) {
		update_option( 'woocommerce_subscriptions_allow_switching', $option );

		$this->assertSame( $expected, wrd_allow_switching() );
	}

	public static function provide_switching_options() {
		yield 'switching disabled'                => array( 'no', 'no' );
		yield 'between variations'                => array( 'variable', 'variable' );
		yield 'between grouped products'          => array( 'grouped', 'grouped' );
		yield 'between variations and groups'     => array( 'variable_grouped', 'variable_grouped' );
		yield 'a value subscriptions never writes' => array( 'yes', 'no' );
	}

	public function test_reads_a_missing_switching_option_as_disabled() {
		delete_option( 'woocommerce_subscriptions_allow_switching' );

		$this->assertSame( 'no', wrd_allow_switching() );
	}

	public function test_offers_a_subscriber_their_own_plan_and_its_upsells() {
		$this->subscribe_to( $this->basic );

		$this->assertSame( $this->ids( $this->basic, $this->enterprise ), array_values( $this->plans_offered_in_the_group() ) );
	}

	public function test_offers_only_the_plan_itself_when_it_has_no_upsells() {
		$this->subscribe_to( $this->enterprise );

		$this->assertSame( $this->ids( $this->enterprise ), array_values( $this->plans_offered_in_the_group() ) );
	}

	public function test_reads_the_plan_of_a_subscription_to_the_product_itself() {
		$this->plans->subscribe( $this->customer, $this->basic );
		wp_set_current_user( $this->customer );

		$this->assertSame( $this->ids( $this->basic, $this->enterprise ), array_values( $this->plans_offered_in_the_group() ) );
	}

	public function test_offers_every_plan_to_a_visitor() {
		$this->assertSame( $this->ids( $this->basic, $this->professional, $this->enterprise ), $this->plans_offered_in_the_group() );
	}

	public function test_offers_every_plan_when_switching_is_disabled() {
		update_option( 'woocommerce_subscriptions_allow_switching', 'no' );
		$this->subscribe_to( $this->basic );

		$this->assertSame( $this->ids( $this->basic, $this->professional, $this->enterprise ), $this->plans_offered_in_the_group() );
	}

	public function test_offers_every_plan_in_the_admin() {
		$this->subscribe_to( $this->basic );
		set_current_screen( 'edit-product' );

		$this->assertSame( $this->ids( $this->basic, $this->professional, $this->enterprise ), $this->plans_offered_in_the_group() );
	}

	public function test_leaves_the_variations_of_a_plan_alone() {
		$variation = $this->plans->variation_of( $this->professional );
		$this->subscribe_to( $this->basic );

		$this->assertSame( array( $variation->get_id() ), wc_get_product( $this->professional->get_id() )->get_children() );
	}

	public function test_hides_the_plans_a_subscriber_cannot_switch_to_from_related_products() {
		$this->subscribe_to( $this->basic );
		$unrelated = self::factory()->post->create( array( 'post_type' => 'product' ) );

		$related = apply_filters( 'woocommerce_related_products', $this->ids( $this->basic, $this->professional, $this->enterprise ) + array( 3 => $unrelated ), $this->basic->get_id(), array() );

		$this->assertSame( array( $this->basic->get_id(), $this->enterprise->get_id(), $unrelated ), array_values( $related ) );
	}

	public function test_shows_every_related_product_to_a_visitor() {
		$related = apply_filters( 'woocommerce_related_products', $this->ids( $this->basic, $this->professional ), $this->basic->get_id(), array() );

		$this->assertSame( $this->ids( $this->basic, $this->professional ), $related );
	}

	public function test_hides_the_plans_a_subscriber_cannot_switch_to_from_the_shop() {
		$this->subscribe_to( $this->basic );

		$this->go_to( get_post_type_archive_link( 'product' ) );

		$this->assertSame( $this->ids( $this->professional ), array_values( $GLOBALS['wp_query']->get( 'post__not_in' ) ) );
	}

	public function test_shows_the_whole_shop_to_a_visitor() {
		$this->go_to( get_post_type_archive_link( 'product' ) );

		$this->assertSame( array(), $GLOBALS['wp_query']->get( 'post__not_in' ) );
	}

	public function test_shows_the_whole_shop_when_switching_is_disabled() {
		update_option( 'woocommerce_subscriptions_allow_switching', 'no' );
		$this->subscribe_to( $this->basic );

		$this->go_to( get_post_type_archive_link( 'product' ) );

		$this->assertSame( array(), $GLOBALS['wp_query']->get( 'post__not_in' ) );
	}

	public function test_leaves_secondary_queries_alone() {
		$this->subscribe_to( $this->basic );

		$query = new WP_Query( array( 'post_type' => 'product' ) );

		$this->assertSame( array(), $query->get( 'post__not_in' ) );
	}

	public function test_ignores_a_subscription_to_a_deleted_plan() {
		$this->subscribe_to( $this->basic );
		$this->basic->delete( true );

		$this->go_to( get_post_type_archive_link( 'product' ) );

		$this->assertSame( array(), $GLOBALS['wp_query']->get( 'post__not_in' ) );
		$this->assertSame( array(), array_values( $this->plans_offered_in_the_group() ) );
	}

	public function test_finds_every_plan_grouped_with_a_product() {
		$other_group = $this->plans->group( array( $this->basic, $this->plans->plan( false ) ) );

		$this->assertSame(
			array_merge( $this->ids( $this->basic, $this->professional, $this->enterprise ), $other_group->get_children() ),
			wrd_get_grouped_products_containing_product( $this->basic->get_id() )
		);
	}

	public function test_finds_nothing_for_a_product_outside_any_group() {
		$this->assertSame( array(), wrd_get_grouped_products_containing_product( $this->plans->plan( true )->get_id() ) );
	}

	public function test_saves_the_restriction_when_the_box_is_checked() {
		$plan                                     = $this->plans->plan( false );
		$_POST['restrict_herself_upsells_switch'] = 'yes';

		do_action( 'woocommerce_process_product_meta', $plan->get_id() );

		$this->assertSame( 'yes', get_post_meta( $plan->get_id(), 'restrict_herself_upsells_switch', true ) );
	}

	public function test_clears_the_restriction_when_the_box_is_unchecked() {
		do_action( 'woocommerce_process_product_meta', $this->professional->get_id() );

		$this->assertSame( 'no', get_post_meta( $this->professional->get_id(), 'restrict_herself_upsells_switch', true ) );
	}

	public function test_shows_the_restriction_box_among_the_linked_products() {
		$this->assertMatchesRegularExpression( '/<input[^>]+name="restrict_herself_upsells_switch"[^>]+checked=.checked./', $this->render_linked_products( $this->basic->get_id() ) );
	}

	public function test_hides_the_restriction_box_when_switching_is_disabled() {
		update_option( 'woocommerce_subscriptions_allow_switching', 'no' );

		$this->assertSame( '', $this->render_linked_products( $this->basic->get_id() ) );
	}

	private function render_linked_products( $product_id ) {
		require_once WC()->plugin_path() . '/includes/admin/wc-meta-box-functions.php';
		$this->go_to( get_permalink( $product_id ) );
		the_post();

		ob_start();
		do_action( 'woocommerce_product_options_related' );

		return ob_get_clean();
	}
}
