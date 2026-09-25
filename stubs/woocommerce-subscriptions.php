<?php

class WC_Subscription extends WC_Order {}

class WC_Product_Subscription_Variation extends WC_Product_Variation {}

/**
 * @param int $user_id
 * @return WC_Subscription[]
 */
function wcs_get_users_subscriptions( $user_id = 0 ) {}
