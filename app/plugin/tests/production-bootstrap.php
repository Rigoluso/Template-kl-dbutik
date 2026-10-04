<?php
/** Run only against a fresh isolated production-mode installation. */
if (! defined('ABSPATH') || ! class_exists('WooCommerce') || getenv('SHOP_MODE') !== 'production') { throw new RuntimeException('Fresh isolated production mode is required.'); }
function shop_production_assert($condition, $message) { if (! $condition) { throw new RuntimeException($message); } }
shop_production_assert(wc_get_products(array('limit' => -1, 'return' => 'ids')) === array(), 'Production bootstrap inserted demo products.');
shop_production_assert(! shop_template_checkout_enabled(), 'Empty production store accepted payment.');
shop_production_assert(get_option('woocommerce_specific_allowed_countries') === array('SE'), 'Unconfigured market enabled.');
shop_production_assert(get_option('woocommerce_specific_ship_to_countries') === array('SE'), 'Unconfigured shipping market enabled.');
foreach (array('pa_size' => 'M', 'pa_color' => 'Svart') as $taxonomy => $term_name) {
    // This is the operation the admin's global attribute term form performs.
    $term = wp_insert_term($term_name, $taxonomy);
    shop_production_assert(! is_wp_error($term), 'Production admin cannot add terms to the canonical size/color filter: ' . $taxonomy);
    try {
        shop_production_assert(wc_attribute_taxonomy_id_by_name($taxonomy) > 0, 'Canonical attribute unavailable for product variation editor: ' . $taxonomy);
    } finally { wp_delete_term($term['term_id'], $taxonomy); }
}
WP_CLI::success('Production starts empty and closed, Sweden only, with usable global size/color attributes.');
