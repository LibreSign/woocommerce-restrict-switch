#!/bin/sh
set -e

wp core install --url=http://localhost:8889 --title=E2E --admin_user=admin --admin_password=password --admin_email=admin@example.org --skip-email
wp rewrite structure '/%postname%/'
wp plugin activate woocommerce woocommerce-subscriptions woocommerce-restrict-switch

wp option update woocommerce_coming_soon no
wp option update woocommerce_subscriptions_allow_switching grouped

if [ -z "$(wp post list --post_type=product --name=upgrade-subscription --format=ids)" ]; then
	wp eval-file /seed.php
fi
