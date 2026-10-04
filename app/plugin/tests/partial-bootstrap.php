<?php
/** Fresh isolated demo DB, before any successful bootstrap. */
if (! defined('ABSPATH') || ! class_exists('WooCommerce') || get_option('shop_template_bootstrapped')) { throw new RuntimeException('Fresh isolated database is required.'); }
function shop_partial_assert($condition, $message) { if (! $condition) { throw new RuntimeException($message); } }
$partial = new WC_Product_Variable();
$partial->set_name('Demo: T-shirt');
$partial->set_sku('DEMO-TSHIRT');
$partial->set_status('publish');
$partial->update_meta_data('_shop_template_demo', '0.1.0');
$partial->save();
$existing_child = new WC_Product_Variation();
$existing_child->set_parent_id($partial->get_id());
$existing_child->set_sku('DEMO-TSHIRT-S');
$existing_child->set_attributes(array('pa_size' => 's', 'pa_color' => 'naturvit'));
$existing_child->set_regular_price('311');
$existing_child->set_manage_stock(true);
$existing_child->set_stock_quantity(4);
$existing_child->save();

// The installer is resumed after only the parent and first child survived.
require dirname(__DIR__, 2) . '/bootstrap.php';
shop_partial_assert(wc_get_product_id_by_sku('DEMO-TSHIRT-M') > 0 && wc_get_product_id_by_sku('DEMO-TSHIRT-L') > 0, 'Interrupted demo bootstrap marked success without repairing missing variants.');
$resumed = wc_get_product($partial->get_id());
shop_partial_assert($resumed->get_image_id() > 0 && is_file(get_attached_file($resumed->get_image_id())), 'Interrupted demo bootstrap did not restore its missing image.');
shop_partial_assert(count($resumed->get_attributes()) === 2, 'Interrupted demo bootstrap left variant attributes unusable.');
$preserved = wc_get_product($existing_child->get_id());
shop_partial_assert($preserved->get_regular_price() === '311' && $preserved->get_stock_quantity() === 4, 'Resuming bootstrap overwrote an existing child.');
shop_partial_assert(get_option('shop_template_bootstrapped') === '0.1.0', 'Recovered bootstrap did not mark completion.');
WP_CLI::success('Interrupted demo bootstrap repairs only missing variants and media and preserves existing child data.');
