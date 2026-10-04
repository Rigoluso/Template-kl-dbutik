<?php
/** Run in isolated demo installation. Repeated bootstrap must preserve edits. */
if (! defined('ABSPATH') || ! class_exists('WooCommerce')) { throw new RuntimeException('WordPress and WooCommerce are required.'); }
function shop_preservation_assert($condition, $message) { if (! $condition) { throw new RuntimeException($message); } }
$variation = wc_get_product(wc_get_product_id_by_sku('DEMO-TSHIRT-M'));
shop_preservation_assert($variation instanceof WC_Product_Variation, 'Demo variation fixture unavailable.');
$old_name = get_option('blogname');
$old_price = $variation->get_regular_price();
$old_stock = $variation->get_stock_quantity();
$old_color = get_theme_mod('shop_accent', null);
$attachments = get_posts(array('post_type' => 'attachment', 'posts_per_page' => -1, 'fields' => 'ids'));
try {
    update_option('blogname', 'Ägarens ändrade butiksnamn');
    set_theme_mod('shop_accent', '#234567');
    $variation->set_regular_price('311');
    $variation->set_stock_quantity(4);
    $variation->save();
    require dirname(__DIR__, 2) . '/bootstrap.php';
    shop_preservation_assert(get_option('blogname') === 'Ägarens ändrade butiksnamn', 'Repeated bootstrap reset shop name.');
    shop_preservation_assert(get_theme_mod('shop_accent') === '#234567', 'Repeated bootstrap reset design.');
    $current = wc_get_product($variation->get_id());
    shop_preservation_assert($current->get_regular_price() === '311' && $current->get_stock_quantity() === 4, 'Repeated bootstrap reset variant price or stock.');
    shop_preservation_assert(get_posts(array('post_type' => 'attachment', 'posts_per_page' => -1, 'fields' => 'ids')) === $attachments, 'Repeated bootstrap reimported demo media.');
} finally {
    update_option('blogname', $old_name);
    if ($old_color === null) { remove_theme_mod('shop_accent'); } else { set_theme_mod('shop_accent', $old_color); }
    $variation->set_regular_price($old_price);
    $variation->set_stock_quantity($old_stock);
    $variation->save();
}
WP_CLI::success('Repeated bootstrap preserves owner name, theme, variant price, stock and media.');
