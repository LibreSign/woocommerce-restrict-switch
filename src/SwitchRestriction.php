<?php

namespace LibreSign\WooRestrictSwitch;

final class SwitchRestriction {

	/**
	 * @param int[]              $children
	 * @param array<int, int[]>  $restricted_plans
	 * @return int[]
	 */
	public static function offered_in_group( array $children, array $restricted_plans ): array {
		$allowed = array();

		foreach ( $restricted_plans as $plan => $upsells ) {
			if ( ! in_array( $plan, $children ) ) {
				continue;
			}
			$allowed   = array_merge( $allowed, $upsells );
			$allowed[] = $plan;
		}

		if ( ! $allowed ) {
			return $children;
		}

		return array_intersect( $children, $allowed );
	}

	/**
	 * @param array<int, int[]> $restricted_plans
	 * @param array<int, int[]> $grouped_with
	 * @return int[]
	 */
	public static function hidden( array $restricted_plans, array $grouped_with ): array {
		$hidden = array();

		foreach ( $restricted_plans as $plan => $upsells ) {
			$hidden = array_merge( $hidden, array_diff( $grouped_with[ $plan ] ?? array(), $upsells, array( $plan ) ) );
		}

		return $hidden;
	}
}
