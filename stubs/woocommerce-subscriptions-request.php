<?php

namespace Automattic\WooCommerce_Subscriptions\Internal\Utilities;

class Request {
	/**
	 * @var array<string, mixed>|null
	 */
	private static ?array $post_vars = null;

	/**
	 * @var array<string, mixed>|null
	 */
	private static ?array $url_vars = null;
}
