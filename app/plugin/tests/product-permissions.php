<?php
/** Run with WP CLI against an isolated installed WordPress/WooCommerce DB. */
if (! defined('ABSPATH') || ! class_exists('WooCommerce')) { throw new RuntimeException('WordPress and WooCommerce are required.'); }
function shop_permissions_assert($condition, $message) {
    if (! $condition) { throw new RuntimeException($message); }
}
$admins = get_users(array('role' => 'administrator', 'number' => 1));
shop_permissions_assert(! empty($admins), 'Administrator fixture unavailable.');
$original_user = get_current_user_id();
$original_post = $_POST;
$product = new WC_Product_Simple();
$product->set_name('Isolated product permission test');
$product->set_status('draft');
$product->save();
$customer = wp_insert_user(array('user_login' => 'shop-test-' . wp_generate_password(8, false), 'user_pass' => wp_generate_password(40, true), 'role' => 'customer'));
shop_permissions_assert(! is_wp_error($customer), 'Customer fixture creation failed.');
try {
    wp_set_current_user($admins[0]->ID);
    $_POST = array('_shop_fibre' => '100 % bomull');
    do_action('woocommerce_admin_process_product_object', $product);
    shop_permissions_assert($product->get_meta('_shop_fibre') === '', 'Product field saved without a nonce.');
    $_POST['_shop_product_nonce'] = 'invalid';
    do_action('woocommerce_admin_process_product_object', $product);
    shop_permissions_assert($product->get_meta('_shop_fibre') === '', 'Product field saved with invalid nonce.');
    $_POST['_shop_product_nonce'] = wp_create_nonce('shop_template_product_fields');
    do_action('woocommerce_admin_process_product_object', $product);
    $product->save();
    shop_permissions_assert(wc_get_product($product->get_id())->get_meta('_shop_fibre') === '100 % bomull', 'Authorized textile information did not persist.');
    wp_set_current_user($customer);
    $_POST = array('_shop_fibre' => 'Customer overwrite', '_shop_product_nonce' => wp_create_nonce('shop_template_product_fields'));
    do_action('woocommerce_admin_process_product_object', $product);
    $product->save();
    shop_permissions_assert(wc_get_product($product->get_id())->get_meta('_shop_fibre') === '100 % bomull', 'Customer changed protected product information.');
    shop_permissions_assert(! current_user_can('manage_woocommerce'), 'Customer can manage store settings.');
    wp_set_current_user($admins[0]->ID);
    $saved = shop_template_sanitize_settings(array('company_name' => '<b>Example AB</b>', 'address' => "Testgatan 1\nSverige", 'contact_email' => 'test@example.test', 'legal_reviewed' => '1', 'unexpected' => 'ignored'));
    shop_permissions_assert($saved['company_name'] === 'Example AB' && $saved['legal_reviewed'] === 1 && ! isset($saved['unexpected']), 'Settings sanitization failed.');
    set_error_handler(function ($severity, $message) { throw new RuntimeException($message); });
    try {
        $malformed = shop_template_sanitize_settings(array('company_name' => array('untrusted'), 'launch_requested' => array('1')));
        shop_permissions_assert($malformed['company_name'] === '' && $malformed['launch_requested'] === 0, 'Malformed setting enabled launch.');
    } finally { restore_error_handler(); }
} finally {
    wp_set_current_user($original_user);
    $_POST = $original_post;
    $product->delete(true);
    require_once ABSPATH . 'wp-admin/includes/user.php';
    wp_delete_user($customer);
}
WP_CLI::success('Product fields require a valid nonce and product capability; settings sanitize untrusted input.');
