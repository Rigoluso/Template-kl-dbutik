<?php
/** Characterize the theme's GET filters using the actual Woo product query. */
if (! defined('ABSPATH') || ! class_exists('WooCommerce')) { throw new RuntimeException('WordPress and WooCommerce are required.'); }
function shop_catalog_assert($condition, $message) {
    if (! $condition) { throw new RuntimeException($message); }
}
$sweatshirt = wc_get_product_id_by_sku('DEMO-SWEATSHIRT');
shop_catalog_assert($sweatshirt > 0, 'Requires first-install demo products.');
$old_query = $GLOBALS['wp_query'];
$old_main_query = $GLOBALS['wp_the_query'];
$old_get = $_GET;
function shop_catalog_ids(array $parameters): array {
    $_GET = $parameters;
    $query = new WP_Query();
    $GLOBALS['wp_query'] = $query;
    $GLOBALS['wp_the_query'] = $query;
    $query->query(array('post_type' => 'product', 's' => 'sweatshirt', 'posts_per_page' => 10, 'fields' => 'ids'));
    return array_map('intval', $query->posts);
}
try {
    shop_catalog_assert(shop_catalog_ids(array()) === array((int) $sweatshirt), 'Catalog search did not return the matching product.');
    shop_catalog_assert(shop_catalog_ids(array('designer' => 'nonexistent-designer')) === array(), 'Designer filter ignored during product search.');
    shop_catalog_assert(shop_catalog_ids(array('size' => 'nonexistent-size')) === array(), 'Size filter ignored during product search.');
    shop_catalog_assert(shop_catalog_ids(array('color' => 'nonexistent-color')) === array(), 'Color filter ignored during product search.');
    shop_catalog_assert(shop_catalog_ids(array('min_price' => '1000')) === array(), 'Minimum price filter ignored during product search.');
    shop_catalog_assert(shop_catalog_ids(array('size' => 'm', 'color' => 'naturvit', 'max_price' => '700')) === array((int) $sweatshirt), 'Matching size/color/price filters hid the correct product.');
} finally {
    $_GET = $old_get;
    $GLOBALS['wp_query'] = $old_query;
    $GLOBALS['wp_the_query'] = $old_main_query;
}
WP_CLI::success('Product search and designer/size/color/price filters work together.');
