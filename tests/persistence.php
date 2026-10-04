<?php
// Real DB and upload checks before/after forced container recreation.
if (!defined('WP_CLI') || !WP_CLI || getenv('SHOP_MODE') !== 'demo') throw new RuntimeException('Run only in the isolated demo integration environment.');
$phase = $args[0] ?? '';
$sku = 'VERIFY-PERSISTENCE';
$id = wc_get_product_id_by_sku($sku);
$uploads = wp_upload_dir();
$path = $uploads['basedir'] . '/verification-persistence.txt';
if ($phase === 'create') {
    $product = $id ? wc_get_product($id) : new WC_Product_Simple();
    $product->set_sku($sku);
    $product->set_name('Persistensprov');
    $product->set_regular_price('123.45');
    $product->set_status('draft');
    $product->save();
    if (!is_dir($uploads['basedir']) && !wp_mkdir_p($uploads['basedir'])) throw new RuntimeException('Cannot create uploads directory.');
    if (file_put_contents($path, "saved-upload-data\n") === false) throw new RuntimeException('Upload volume is not writable.');
    update_option('shop_verification_saved_setting', 'preserve-this-value');
    WP_CLI::success('Saved actual product, setting and uploaded data for persistence verification.');
} elseif ($phase === 'verify') {
    $product = $id ? wc_get_product($id) : null;
    if (!$product || $product->get_name() !== 'Persistensprov' || $product->get_regular_price() !== '123.45') throw new RuntimeException('Product data was not preserved.');
    if (get_option('shop_verification_saved_setting') !== 'preserve-this-value') throw new RuntimeException('Setting was not preserved.');
    if (!is_readable($path) || file_get_contents($path) !== "saved-upload-data\n") throw new RuntimeException('Uploaded file was not preserved.');
    $products = wc_get_products(['limit' => -1, 'status' => 'publish']);
    $demo = array_filter($products, static fn($item) => in_array($item->get_sku(), ['DEMO-TSHIRT', 'DEMO-SWEATSHIRT'], true));
    if (count($demo) !== 2) throw new RuntimeException('Demo data was duplicated/lost.');
    WP_CLI::success('Product, setting, upload and demo uniqueness survived container recreation.');
} else {
    throw new RuntimeException('Use create or verify.');
}
