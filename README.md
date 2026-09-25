# WooCommerce restrict switch

Restrict switch to products that isn't upsell of a produdct

## Configuring

### WooCommerce Subscription

* Go to WooCommerce subscription settings
* At "`Allow Switching`", check "`Between Subscription Variations`" or "`Between Grouped Subscriptions`"
* Click at "`Save changes`"

### Product

* Edit the product
* Go to "`Linked products`"
* Define the upsells products. Is the products that you want allow to switch to.
* Check the box "restrict siwtch"

## Development

Every check is a Composer script:

```bash
composer lint  # php -l on every file
composer cs    # PHPCS
composer stan  # PHPStan
composer test  # PHPUnit
composer ci    # all of the above, in this order
composer coverage  # PHPUnit with a coverage report for octocov
```

### Layout and coverage

`tests/Unit/StructureTest.php` checks that every file of the plugin has a test
named after it: `src/SwitchRestriction.php` has `tests/Unit/SwitchRestrictionTest.php`
and the main file has `tests/Integration/WoocommerceRestrictSwitchTest.php`.
It also fails on a test whose file no longer exists.

`composer coverage` writes `tests/.coverage/clover.xml`. In CI,
[octocov](https://github.com/k1LoW/octocov) fails the run when line coverage
is below 95% or below the last report of `main` (`.octocov.yml`).

### Tests

`composer install` brings in WordPress, the WordPress test suite, WooCommerce and
WooCommerce Subscriptions, so the tests only need a MySQL/MariaDB database they
are allowed to wipe on every run.

| Variable | Default |
|---|---|
| `WP_TESTS_DB_NAME` | `wordpress_test` |
| `WP_TESTS_DB_USER` | `root` |
| `WP_TESTS_DB_PASSWORD` | `root` |
| `WP_TESTS_DB_HOST` | `mariadb` |
| `WP_TESTS_TABLE_PREFIX` | `wptests_` |
| `WP_CORE_DIR` | `vendor/wordpress` |

On the local SaaS stack:

```bash
docker exec wordpress-docker-mariadb-1 \
  mariadb -uroot -proot -e 'CREATE DATABASE IF NOT EXISTS wordpress_test;'

docker exec -w /var/www/html/wp-content/plugins/woocommerce-restrict-switch \
  wordpress-docker-wordpress-1 composer test
```
