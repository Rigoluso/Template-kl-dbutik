<?php
/** Run in the installed WordPress environment: wp eval-file .../launch-guard.php */
if (! defined('ABSPATH') || ! class_exists('WooCommerce')) {
    throw new RuntimeException('Run this test with WP CLI against installed WordPress and WooCommerce.');
}

function shop_test_assert($condition, $message) {
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

// Missing enforcement would accept a direct API purchase while the demo banner
// still made the shop look safe. Cart and catalog requests must keep working.
foreach (array('/wc/store/v1/checkout', '/wc/store/v1/checkout/1', '/wc/v3/orders', '/wc/v3/orders/1', '/wc/v3/orders/batch') as $route) {
    $request = new WP_REST_Request('POST', $route);
    $result = apply_filters('rest_pre_dispatch', null, rest_get_server(), $request);
    shop_test_assert(is_wp_error($result) && $result->get_error_code() === 'shop_not_launched', 'Unsafe purchase route accepted: ' . $route);
    shop_test_assert($result->get_error_data()['status'] === 503, 'Launch block must explain unavailability.');
}
foreach (array('/wc/store/v1/cart/add-item', '/wc/store/v1/cart/apply-coupon', '/wc/store/v1/products') as $route) {
    $request = new WP_REST_Request('POST', $route);
    shop_test_assert(apply_filters('rest_pre_dispatch', null, rest_get_server(), $request) === null, 'Browsing/cart route wrongly blocked: ' . $route);
}

$errors = new WP_Error();
do_action('woocommerce_after_checkout_validation', array(), $errors);
shop_test_assert($errors->get_error_code() === 'shop_not_launched', 'Classic checkout accepted an order before launch.');

$old_settings = get_option('shop_template_settings', null);
try {
    update_option('shop_template_settings', array('company_name' => 'Test AB', 'organisation_number' => '556000-0000', 'address' => 'Testgatan 1', 'contact_email' => 'test@example.test', 'legal_reviewed' => 1, 'products_reviewed' => 1, 'shipping_reviewed' => 1, 'email_reviewed' => 1, 'launch_requested' => 1));
    shop_test_assert(! shop_template_checkout_enabled(), 'Checklist enabled payment without a verified payment integration.');
    shop_test_assert(count(shop_template_launch_errors()) > 0, 'Incomplete payment integration missing from launch checklist.');
} finally {
    if ($old_settings === null) {
        delete_option('shop_template_settings');
    } else {
        update_option('shop_template_settings', $old_settings);
    }
}

shop_test_assert(taxonomy_exists('product_designer') && taxonomy_exists('product_collection'), 'Product administration taxonomies missing.');
shop_test_assert(get_option('shop_template_bootstrapped') === '0.1.0', 'Successful bootstrap marker missing.');
shop_test_assert(get_option('woocommerce_currency') === 'SEK', 'New store currency must be SEK.');
shop_test_assert(get_option('woocommerce_default_country') === 'SE', 'New store market must be Sweden.');
WP_CLI::success('Launch guard blocks purchases while leaving browsing and cart routes available.');
