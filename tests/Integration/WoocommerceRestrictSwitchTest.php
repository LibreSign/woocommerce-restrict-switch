<?php

namespace LibreSign\WooRestrictSwitch\Tests\Integration;

use Automattic\WooCommerce_Subscriptions\Internal\Utilities\Request;
use LibreSign\WooRestrictSwitch\Tests\Support\PlanFactory;
use ReflectionProperty;
use WC_Form_Handler;
use WC_Product_Simple;
use WP_Query;
use WP_REST_Request;
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
		unset( $_POST['restrict_herself_upsells_switch'], $_REQUEST['add-to-cart'] );
		$this->set_request_variables( null );
		wc_clear_notices();
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

		$this->assertSame( $expected, restrict_switch_allow_switching() );
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

		$this->assertSame( 'no', restrict_switch_allow_switching() );
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

	public function test_offers_every_plan_to_a_customer_without_subscriptions() {
		wp_set_current_user( $this->customer );

		$this->assertSame( $this->ids( $this->basic, $this->professional, $this->enterprise ), $this->plans_offered_in_the_group() );
	}

	public function test_offers_every_plan_to_a_subscriber_of_an_unrestricted_plan() {
		$this->subscribe_to( $this->plans->plan( false ) );

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
		$every_plan = $this->ids( $this->basic, $this->professional, $this->enterprise );
		$this->basic->delete( true );

		$this->go_to( get_post_type_archive_link( 'product' ) );

		$this->assertSame( array(), $GLOBALS['wp_query']->get( 'post__not_in' ) );
		$this->assertSame( $every_plan, $this->plans_offered_in_the_group() );
	}

	public function test_finds_every_plan_grouped_with_a_product() {
		$other_group = $this->plans->group( array( $this->basic, $this->plans->plan( false ) ) );

		$this->assertSame(
			array_merge( $this->ids( $this->basic, $this->professional, $this->enterprise ), $other_group->get_children() ),
			restrict_switch_get_grouped_products_containing_product( $this->basic->get_id() )
		);
	}

	public function test_does_not_instantiate_objects_stored_among_the_grouped_products() {
		$plan  = $this->plans->plan( true );
		$group = self::factory()->post->create( array( 'post_type' => 'product' ) );
		update_post_meta( $group, '_children', array( $plan->get_id(), new \ArrayObject() ) );

		$found = restrict_switch_get_grouped_products_containing_product( $plan->get_id() );

		$this->assertSame( $plan->get_id(), $found[0] );
		$this->assertInstanceOf( \__PHP_Incomplete_Class::class, $found[1] );
	}

	public function test_finds_nothing_for_a_product_outside_any_group() {
		$this->assertSame( array(), restrict_switch_get_grouped_products_containing_product( $this->plans->plan( true )->get_id() ) );
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

	public function test_refuses_a_switch_to_a_plan_outside_the_upsells_through_add_to_cart() {
		$this->request_a_switch_from( $this->basic );
		$_REQUEST['add-to-cart'] = $this->plans->variation_of( $this->professional )->get_id();

		WC_Form_Handler::add_to_cart_action();

		$this->assertTrue( WC()->cart->is_empty() );
		$this->assertSame( 1, wc_notice_count( 'error' ) );
	}

	public function test_accepts_a_switch_to_an_upsell_through_add_to_cart() {
		$this->request_a_switch_from( $this->basic );
		$_REQUEST['add-to-cart'] = $this->plans->variation_of( $this->enterprise )->get_id();

		WC_Form_Handler::add_to_cart_action();

		$this->assertSame( array( $this->enterprise->get_id() ), $this->switches_in_the_cart() );
	}

	public function test_refuses_a_switch_to_a_plan_outside_the_upsells_through_the_store_api() {
		$this->request_a_switch_from( $this->basic );

		$response = $this->add_to_cart_through_the_store_api( $this->plans->variation_of( $this->professional ) );

		$this->assertSame( 'woocommerce_rest_add_to_cart_error', $response->get_data()['code'] );
		$this->assertTrue( WC()->cart->is_empty() );
	}

	public function test_accepts_a_switch_to_an_upsell_through_the_store_api() {
		$this->request_a_switch_from( $this->basic );

		$response = $this->add_to_cart_through_the_store_api( $this->plans->variation_of( $this->enterprise ) );

		$this->assertSame( 201, $response->get_status() );
		$this->assertSame( array( $this->enterprise->get_id() ), $this->switches_in_the_cart() );
	}

	public function test_accepts_any_switch_from_an_unrestricted_plan() {
		$unrestricted = $this->plans->plan( false );
		$this->plans->group( array( $unrestricted, $this->professional ) );
		$this->request_a_switch_from( $unrestricted );
		$_REQUEST['add-to-cart'] = $this->plans->variation_of( $this->professional )->get_id();

		WC_Form_Handler::add_to_cart_action();

		$this->assertSame( array( $this->professional->get_id() ), $this->switches_in_the_cart() );
	}

	public function test_adds_no_notice_of_its_own_to_a_switch_subscriptions_already_refused() {
		$not_a_subscription = new WC_Product_Simple();
		$not_a_subscription->set_regular_price( '10' );
		$not_a_subscription->save();
		$this->request_a_switch_from( $this->basic );
		$_REQUEST['add-to-cart'] = $not_a_subscription->get_id();

		WC_Form_Handler::add_to_cart_action();

		$this->assertTrue( WC()->cart->is_empty() );
		$this->assertSame( 1, wc_notice_count( 'error' ) );
	}

	private function request_a_switch_from( $plan ) {
		$subscription = $this->plans->subscribe( $this->customer, $this->plans->variation_of( $plan ) );
		wp_set_current_user( $this->customer );
		wc_load_cart();
		WC()->cart->empty_cart();
		add_filter( 'woocommerce_add_to_cart_redirect', '__return_false', PHP_INT_MAX );

		$this->set_request_variables(
			array(
				'switch-subscription' => $subscription->get_id(),
				'item'                => array_key_first( $subscription->get_items() ),
				'_wcsnonce'           => wp_create_nonce( 'wcs_switch_request' ),
			)
		);
	}

	private function set_request_variables( $url_variables ) {
		( new ReflectionProperty( Request::class, 'post_vars' ) )->setValue( null, null === $url_variables ? null : array() );
		( new ReflectionProperty( Request::class, 'url_vars' ) )->setValue( null, $url_variables );
	}

	private function add_to_cart_through_the_store_api( $variation ) {
		$request = new WP_REST_Request( 'POST', '/wc/store/v1/cart/add-item' );
		$request->set_header( 'Nonce', wp_create_nonce( 'wc_store_api' ) );
		$request->set_body_params(
			array(
				'id'       => $variation->get_id(),
				'quantity' => 1,
			)
		);

		return rest_get_server()->dispatch( $request );
	}

	private function switches_in_the_cart() {
		$switches = array();
		foreach ( WC()->cart->get_cart() as $cart_item ) {
			if ( isset( $cart_item['subscription_switch'] ) ) {
				$switches[] = $cart_item['product_id'];
			}
		}

		return $switches;
	}
}
