<?php
// Real WooCommerce CSV parser test; this does not implement the requested ZIP UI.
if (!defined('WP_CLI') || !WP_CLI || getenv('SHOP_MODE') !== 'demo') throw new RuntimeException('Use only the isolated demo CSV test.');
$csv = $args[0] ?? '';
if (!is_file($csv)) throw new RuntimeException('Provide the example CSV path.');
$skus = ['EXAMPLE-IMPORT-TEE', 'EXAMPLE-IMPORT-TEE-S', 'EXAMPLE-IMPORT-TEE-M'];
foreach ($skus as $sku) {
    if (wc_get_product_id_by_sku($sku)) throw new RuntimeException('CSV fixture SKU already exists; refusing to overwrite it.');
}
require_once WC_ABSPATH . 'includes/import/abstract-wc-product-importer.php';
require_once WC_ABSPATH . 'includes/import/class-wc-product-csv-importer.php';
$mapping = ['Type'=>'type','SKU'=>'sku','Name'=>'name','Published'=>'published',
    'Is featured?'=>'featured','Visibility in catalog'=>'catalog_visibility',
    'Short description'=>'short_description','Description'=>'description',
    'Tax status'=>'tax_status','Tax class'=>'tax_class','In stock?'=>'stock_status',
    'Stock'=>'stock_quantity','Regular price'=>'regular_price','Categories'=>'category_ids',
    'Images'=>'images','Parent'=>'parent_id'];
foreach ([1,2] as $n) {
    foreach (['name'=>'name','value(s)'=>'value','visible'=>'visible','global'=>'taxonomy'] as $label=>$field) {
        $mapping["Attribute $n $label"] = "attributes:$field$n";
    }
}
try {
    $importer = new WC_Product_CSV_Importer($csv, ['mapping'=>$mapping,'parse'=>true,'update_existing'=>false]);
    $result = $importer->import();
    if ($result['failed'] || count($result['imported']) !== 1 || count($result['imported_variations']) !== 2) {
        $messages = array_map(static fn($error) => $error->get_error_message(), $result['failed']);
        throw new RuntimeException('Example CSV import failed: ' . wp_json_encode(['counts'=>array_map('count',$result),'errors'=>$messages]));
    }
    $parent = wc_get_product(wc_get_product_id_by_sku($skus[0]));
    if (!$parent instanceof WC_Product_Variable || $parent->get_status() !== 'draft') throw new RuntimeException('Example parent must be variable and draft.');
    $attributes = $parent->get_attributes();
    if (!isset($attributes['pa_size'], $attributes['pa_color'])) throw new RuntimeException('CSV created duplicate/missing global attributes.');
    foreach ([1=>5,2=>3] as $index=>$stock) {
        $child = wc_get_product(wc_get_product_id_by_sku($skus[$index]));
        if (!$child instanceof WC_Product_Variation || $child->get_parent_id() !== $parent->get_id() || $child->get_regular_price() !== '249' || $child->get_stock_quantity() !== $stock) throw new RuntimeException('CSV variant price, stock or parent relationship is incorrect.');
    }
    $child = wc_get_product(wc_get_product_id_by_sku($skus[1]));
    $child->set_regular_price('399');
    $child->set_stock_quantity(9);
    $child->save();
    $again = (new WC_Product_CSV_Importer($csv, ['mapping'=>$mapping,'parse'=>true,'update_existing'=>false]))->import();
    $preserved = wc_get_product($child->get_id());
    if (count($again['skipped']) !== 3 || $again['updated'] || $preserved->get_regular_price() !== '399' || $preserved->get_stock_quantity() !== 9) throw new RuntimeException('Repeated CSV import overwrote existing data without opt-in.');
    WP_CLI::success('Real example CSV imports a draft parent, two priced/stocked variants and existing global attributes; duplicate SKUs preserve owner data.');
} finally {
    foreach (array_reverse($skus) as $sku) {
        $id = wc_get_product_id_by_sku($sku);
        if ($id) wc_get_product($id)->delete(true);
    }
}
