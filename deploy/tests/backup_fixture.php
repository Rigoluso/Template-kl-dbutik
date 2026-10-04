<?php
// Run only through CLI in the isolated demo project used by backup-recovery.sh.
if (!defined('WP_CLI') || !WP_CLI || getenv('SHOP_MODE') !== 'demo') {
    throw new RuntimeException('Backup fixture requires the isolated demo CLI.');
}
$phase = $args[0] ?? '';
$id = wc_get_product_id_by_sku('VERIFY-BACKUP-RESTORE');
$uploads = wp_upload_dir();
$file = $uploads['basedir'] . '/backup-recovery-proof.txt';
$private = '/var/shop-private/backup-recovery-proof.txt';
if ($phase === 'create') {
    $product = $id ? wc_get_product($id) : new WC_Product_Simple();
    $product->set_sku('VERIFY-BACKUP-RESTORE');
    $product->set_name('Backup verification product');
    $product->set_status('draft');
    $product->set_regular_price('321.00');
    $product->set_tax_status('none');
    $product->set_manage_stock(true);
    $product->set_stock_quantity(9);
    $product->save();
    file_put_contents($file, "original-upload-for-backup\n");
    file_put_contents($private, "original-private-for-backup\n");
    update_option('shop_backup_verification_setting', 'original-setting');
    $order = wc_create_order(['status' => 'pending', 'created_via' => 'backup-verification']);
    $order->set_billing_email('backup-verification@example.test');
    $order->add_product($product, 1);
    $order->calculate_totals();
    $order->save();
    update_option('shop_backup_verification_order_id', $order->get_id());
    WP_CLI::success('Created real product, pending order, setting, upload and private file.');
} elseif ($phase === 'mutate') {
    $product = wc_get_product($id);
    $product->set_name('Changed after backup');
    $product->set_regular_price('999.00');
    $product->set_stock_quantity(0);
    $product->save();
    update_option('shop_backup_verification_setting', 'changed-after-backup');
    file_put_contents($file, "changed-upload\n");
    file_put_contents($private, "changed-private\n");
    $order = wc_get_order(get_option('shop_backup_verification_order_id'));
    $order->delete(true);
    WP_CLI::success('Changed the saved data and deleted the saved pending order.');
} elseif ($phase === 'verify') {
    $product = wc_get_product($id);
    if (!$product || $product->get_name() !== 'Backup verification product' || $product->get_regular_price() !== '321.00' || $product->get_stock_quantity() !== 9) {
        throw new RuntimeException('Product was not restored.');
    }
    if (get_option('shop_backup_verification_setting') !== 'original-setting' || file_get_contents($file) !== "original-upload-for-backup\n" || file_get_contents($private) !== "original-private-for-backup\n") {
        throw new RuntimeException('Setting or persistent file was not restored.');
    }
    $order = wc_get_order(get_option('shop_backup_verification_order_id'));
    if (!$order || $order->get_status() !== 'pending' || $order->get_created_via() !== 'backup-verification' || $order->get_total() !== '321.00' || $order->get_billing_email() !== 'backup-verification@example.test') {
        throw new RuntimeException('Original pending order was not restored.');
    }
    WP_CLI::success('Restored real product, pending order, setting, upload and private file.');
} else {
    throw new RuntimeException('Use create, mutate or verify.');
}
