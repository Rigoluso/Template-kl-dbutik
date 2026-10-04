<?php
/** Real interruption after a child SKU is written, before remaining WC data. */
if (! defined('ABSPATH') || ! class_exists('WooCommerce') || get_option('shop_template_bootstrapped')) { throw new RuntimeException('Fresh isolated database is required.'); }
$interrupt = function ($meta_id, $object_id, $meta_key, $meta_value) {
    if ($meta_key === '_sku' && $meta_value === 'DEMO-TSHIRT-S' && get_post_type($object_id) === 'product_variation') {
        throw new RuntimeException('Simulated interruption after variation SKU.');
    }
};
add_action('added_post_meta', $interrupt, 10, 4);
$interrupted = false;
try {
    require dirname(__DIR__, 2) . '/bootstrap.php';
} catch (RuntimeException $error) {
    if ($error->getMessage() !== 'Simulated interruption after variation SKU.') { throw $error; }
    $interrupted = true;
} finally { remove_action('added_post_meta', $interrupt, 10); }
if (! $interrupted) { throw new RuntimeException('Failure injection did not reach the real variation SKU write.'); }
$parent = wc_get_product_id_by_sku('DEMO-TSHIRT');
if (! $parent || wc_get_product($parent)->get_meta('_shop_template_demo') !== '0.1.0') { throw new RuntimeException('Durable installer-owned parent unexpectedly lost.'); }
$partial_children = get_posts(array('post_type' => 'product_variation', 'post_status' => 'any', 'post_parent' => $parent, 'fields' => 'ids'));
if ($partial_children) { throw new RuntimeException('Interrupted variation write left a partially saved child instead of rolling back.'); }
if (get_option('shop_template_bootstrapped')) { throw new RuntimeException('Interrupted variant installer marked initialization complete.'); }
WP_CLI::success('Interrupted new variation SKU/price/stock write rolls back, preserving the durable owned parent.');
