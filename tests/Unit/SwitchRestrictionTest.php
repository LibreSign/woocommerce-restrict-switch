<?php

namespace LibreSign\WooRestrictSwitch\Tests\Unit;

use LibreSign\WooRestrictSwitch\SwitchRestriction;
use PHPUnit\Framework\TestCase;

final class SwitchRestrictionTest extends TestCase {

	private const BASIC        = 10;
	private const PROFESSIONAL = 20;
	private const ENTERPRISE   = 30;
	private const OUTSIDE      = 40;

	private const GROUP = array( self::BASIC, self::PROFESSIONAL, self::ENTERPRISE );

	/**
	 * @dataProvider provide_groups
	 */
	public function test_offers_the_restricted_plans_and_their_upsells( $children, $restricted_plans, $expected ) {
		$this->assertSame( $expected, SwitchRestriction::offered_in_group( $children, $restricted_plans ) );
	}

	public static function provide_groups() {
		yield 'a plan with upsells offers itself and them' => array(
			self::GROUP,
			array( self::BASIC => array( self::ENTERPRISE ) ),
			array( 0 => self::BASIC, 2 => self::ENTERPRISE ),
		);
		yield 'a plan without upsells offers only itself' => array(
			self::GROUP,
			array( self::ENTERPRISE => array() ),
			array( 2 => self::ENTERPRISE ),
		);
		yield 'two restricted plans add up' => array(
			self::GROUP,
			array(
				self::BASIC        => array(),
				self::PROFESSIONAL => array(),
			),
			array( self::BASIC, self::PROFESSIONAL ),
		);
		yield 'an upsell outside the group is not added' => array(
			self::GROUP,
			array( self::BASIC => array( self::OUTSIDE ) ),
			array( self::BASIC ),
		);
		yield 'no restricted plan keeps the whole group' => array(
			self::GROUP,
			array(),
			self::GROUP,
		);
		yield 'a restricted plan outside the group keeps the whole group' => array(
			self::GROUP,
			array( self::OUTSIDE => array( self::BASIC ) ),
			self::GROUP,
		);
	}

	/**
	 * @dataProvider provide_hidden_plans
	 */
	public function test_hides_what_is_grouped_with_a_restricted_plan_except_its_upsells( $restricted_plans, $grouped_with, $expected ) {
		$this->assertSame( $expected, array_values( SwitchRestriction::hidden( $restricted_plans, $grouped_with ) ) );
	}

	public static function provide_hidden_plans() {
		yield 'the plans that are not upsells are hidden' => array(
			array( self::BASIC => array( self::ENTERPRISE ) ),
			array( self::BASIC => self::GROUP ),
			array( self::PROFESSIONAL ),
		);
		yield 'a plan without upsells hides the rest of the group' => array(
			array( self::ENTERPRISE => array() ),
			array( self::ENTERPRISE => self::GROUP ),
			array( self::BASIC, self::PROFESSIONAL ),
		);
		yield 'a plan outside any group hides nothing' => array(
			array( self::OUTSIDE => array() ),
			array( self::OUTSIDE => array() ),
			array(),
		);
		yield 'no restricted plan hides nothing' => array(
			array(),
			array(),
			array(),
		);
		yield 'each restricted plan hides from its own groups' => array(
			array(
				self::BASIC   => array( self::ENTERPRISE ),
				self::OUTSIDE => array(),
			),
			array(
				self::BASIC   => self::GROUP,
				self::OUTSIDE => array( self::OUTSIDE, self::BASIC ),
			),
			array( self::PROFESSIONAL, self::BASIC ),
		);
	}
}
