<?php
/** Idempotent first-install configuration. Run only with `wp eval-file`. */
if (! defined('ABSPATH') || ! defined('WP_CLI') || ! WP_CLI) {
    throw new RuntimeException('This installer must be run by WP CLI.');
}
if (! class_exists('WooCommerce')) {
    WP_CLI::error('WooCommerce must be installed and activated before bootstrap.');
}
if (get_option('shop_template_bootstrapped') === '0.1.0') {
    WP_CLI::success('Existing shop preserved; first-install configuration already complete.');
    return;
}

$mode = getenv('SHOP_MODE') ?: 'demo';
if (! in_array($mode, array('demo', 'production'), true)) {
    WP_CLI::error('SHOP_MODE must be demo or production.');
}

function shop_bootstrap_page($title, $slug, $content = '', $status = 'publish') {
    $existing = get_page_by_path($slug);
    if ($existing) { return $existing->ID; }
    $id = wp_insert_post(array('post_type' => 'page', 'post_status' => $status, 'post_title' => $title, 'post_name' => $slug, 'post_content' => $content), true);
    if (is_wp_error($id)) { WP_CLI::error($id->get_error_message()); }
    return $id;
}

// Applied once. Further design/product/settings edits live in the persistent DB.
update_option('WPLANG', 'sv_SE');
update_option('timezone_string', 'Europe/Stockholm');
update_option('blogname', 'Din klädbutik');
update_option('blogdescription', '');
update_option('permalink_structure', '/%postname%/');
update_option('woocommerce_currency', 'SEK');
update_option('woocommerce_default_country', 'SE');
update_option('woocommerce_allowed_countries', 'specific');
update_option('woocommerce_specific_allowed_countries', array('SE'));
update_option('woocommerce_ship_to_countries', 'specific');
update_option('woocommerce_specific_ship_to_countries', array('SE'));
update_option('woocommerce_default_customer_address', 'base');
update_option('woocommerce_enable_guest_checkout', 'yes');
update_option('woocommerce_enable_signup_and_login_from_checkout', 'no');
update_option('woocommerce_enable_signup_from_checkout', 'no');
update_option('woocommerce_manage_stock', 'yes');
update_option('woocommerce_hold_stock_minutes', '30');
update_option('woocommerce_hide_out_of_stock_items', 'no');
update_option('woocommerce_stock_format', 'no_amount');
update_option('woocommerce_calc_taxes', 'yes');
update_option('woocommerce_prices_include_tax', 'yes');
update_option('woocommerce_tax_display_shop', 'incl');
update_option('woocommerce_tax_display_cart', 'incl');
// No invented tax rates or shipping methods. The launch checklist requires setup.
update_option('woocommerce_enable_reviews', 'no');
update_option('woocommerce_coming_soon', 'no');
update_option('woocommerce_store_pages_only', 'no');
update_option('default_comment_status', 'closed');
update_option('users_can_register', 0);

$home = shop_bootstrap_page('Startsida', 'start');
$shop = shop_bootstrap_page('Alla plagg', 'butik');
$cart = shop_bootstrap_page('Varukorg', 'varukorg', '[woocommerce_cart]');
$checkout = shop_bootstrap_page('Kassa', 'kassa', '[woocommerce_checkout]');
$account = shop_bootstrap_page('Mitt konto', 'mitt-konto', '[woocommerce_my_account]');
update_option('show_on_front', 'page');
update_option('page_on_front', $home);
update_option('woocommerce_shop_page_id', $shop);
update_option('woocommerce_cart_page_id', $cart);
update_option('woocommerce_checkout_page_id', $checkout);
update_option('woocommerce_myaccount_page_id', $account);

$incomplete = '<!-- wp:paragraph --><p><strong>Ej färdig lanseringstext.</strong> Denna sida är ett utkast. Fyll i företagets uppgifter, aktuella villkor och tillämplig information och låt dem granskas före publicering. Texten är ingen garanti om juridisk efterlevnad.</p><!-- /wp:paragraph -->';
$legal_ids = array();
foreach (array('kopvillkor' => 'Köpvillkor', 'integritet' => 'Integritet och cookies', 'frakt-retur' => 'Frakt, retur och reklamation', 'angerratt' => 'Ångerrätt och ångerfunktion', 'kontakt' => 'Kontakt', 'faq' => 'Vanliga frågor') as $slug => $title) {
    $legal_ids[$slug] = shop_bootstrap_page($title, $slug, $incomplete, 'draft');
}
update_option('shop_template_legal_page_ids', $legal_ids);
update_option('woocommerce_terms_page_id', $legal_ids['kopvillkor']);
update_option('wp_page_for_privacy_policy', $legal_ids['integritet']);

if (! wp_get_theme('kladbutik')->exists()) { WP_CLI::error('Theme kladbutik is missing from wp-content/themes.'); }
switch_theme('kladbutik');
if (! has_nav_menu('primary')) {
    $menu = wp_get_nav_menu_object('Huvudmeny');
    $menu_id = $menu ? $menu->term_id : wp_create_nav_menu('Huvudmeny');
    if (is_wp_error($menu_id)) { WP_CLI::error($menu_id->get_error_message()); }
    if (! wp_get_nav_menu_items($menu_id)) {
        wp_update_nav_menu_item($menu_id, 0, array('menu-item-title' => 'Alla plagg', 'menu-item-object' => 'page', 'menu-item-object-id' => $shop, 'menu-item-type' => 'post_type', 'menu-item-status' => 'publish'));
    }
    set_theme_mod('nav_menu_locations', array('primary' => $menu_id));
}

function shop_bootstrap_attribute($slug, $name, $terms) {
    $attribute_id = wc_attribute_taxonomy_id_by_name($slug);
    if (! $attribute_id) {
        $attribute_id = wc_create_attribute(array('name' => $name, 'slug' => $slug, 'type' => 'select', 'order_by' => 'menu_order', 'has_archives' => true));
        if (is_wp_error($attribute_id)) { WP_CLI::error($attribute_id->get_error_message()); }
        delete_transient('wc_attribute_taxonomies');
        WC_Cache_Helper::invalidate_cache_group('woocommerce-attributes');
    }
    $taxonomy = wc_attribute_taxonomy_name($slug);
    if (! taxonomy_exists($taxonomy)) { register_taxonomy($taxonomy, 'product', array('hierarchical' => false, 'public' => true, 'label' => $name)); }
    $term_ids = array();
    foreach ($terms as $term_name) {
        $term = term_exists($term_name, $taxonomy);
        if (! $term) { $term = wp_insert_term($term_name, $taxonomy); }
        if (is_wp_error($term)) { WP_CLI::error($term->get_error_message()); }
        $term_ids[] = (int) (is_array($term) ? $term['term_id'] : $term);
    }
    return array((int) $attribute_id, $taxonomy, $term_ids);
}

function shop_bootstrap_demo_image($product, $filename, $alt) {
    if ($product->get_image_id()) { return; }
    $source = get_template_directory() . '/assets/demo/' . $filename;
    if (! is_readable($source)) { WP_CLI::error('Missing original demo illustration: ' . $filename); }
    require_once ABSPATH . 'wp-admin/includes/image.php';
    $upload = wp_upload_bits($filename, null, file_get_contents($source));
    if ($upload['error']) { WP_CLI::error($upload['error']); }
    $attachment = wp_insert_attachment(array('post_mime_type' => 'image/png', 'post_title' => $alt, 'post_status' => 'inherit'), $upload['file'], $product->get_id(), true);
    if (is_wp_error($attachment)) { WP_CLI::error($attachment->get_error_message()); }
    update_post_meta($attachment, '_wp_attachment_image_alt', $alt);
    wp_update_attachment_metadata($attachment, wp_generate_attachment_metadata($attachment, $upload['file']));
    $product->set_image_id($attachment);
    $product->save();
}

// Empty global attributes are useful in the real production admin too. Terms
// remain empty until the owner adds actual garment sizes and colors.
shop_bootstrap_attribute('size', 'Storlek', array());
shop_bootstrap_attribute('color', 'Färg', array());

// Never runs again after a successful bootstrap, even after restart/update.
if ($mode === 'demo') {
    list($size_id, $size_taxonomy, $size_terms) = shop_bootstrap_attribute('size', 'Storlek', array('S', 'M', 'L'));
    list($color_id, $color_taxonomy, $color_terms) = shop_bootstrap_attribute('color', 'Färg', array('Naturvit'));
    foreach (array(array('DEMO-TSHIRT', 'Demo: T-shirt', 'tshirt.png', '289'), array('DEMO-SWEATSHIRT', 'Demo: Sweatshirt', 'sweatshirt.png', '649')) as $demo) {
        $existing_id = wc_get_product_id_by_sku($demo[0]);
        $product = $existing_id ? wc_get_product($existing_id) : new WC_Product_Variable();
        // Never touch another owner's product merely because its SKU matches.
        if ($existing_id && (! $product instanceof WC_Product_Variable || $product->get_meta('_shop_template_demo') !== '0.1.0')) { continue; }
        if (! $existing_id) {
            $product->set_name($demo[1]);
            $product->set_sku($demo[0]);
            $product->set_status('publish');
            $product->set_catalog_visibility('visible');
            $product->set_description('Demoprodukt för att prova katalog, storleksval och varukorg. Bilden är en illustration. Detta är ingen verklig vara och kan inte köpas.');
            $product->set_short_description('Demoprodukt · köp är avstängda');
            $product->update_meta_data('_shop_template_demo', '0.1.0');
            $product->set_default_attributes(array('pa_color' => 'naturvit'));
        }
        $attributes = $product->get_attributes();
        foreach (array(array($size_id, $size_taxonomy, $size_terms), array($color_id, $color_taxonomy, $color_terms)) as $data) {
            if (isset($attributes[$data[1]])) { continue; }
            $attribute = new WC_Product_Attribute();
            $attribute->set_id($data[0]); $attribute->set_name($data[1]); $attribute->set_options($data[2]);
            $attribute->set_visible(true); $attribute->set_variation(true);
            $attributes[$data[1]] = $attribute;
        }
        $product->set_attributes($attributes);
        if (! $existing_id) {
            // The parent row and its ownership meta must become durable
            // together. MariaDB's InnoDB rolls back even if the process dies.
            global $wpdb;
            if ($wpdb->query('START TRANSACTION') === false) { throw new RuntimeException('Could not start the initial demo product transaction.'); }
            try {
                $product->save();
                if ($wpdb->query('COMMIT') === false) { throw new RuntimeException('Could not commit the initial demo product transaction.'); }
            } catch (Throwable $error) {
                $wpdb->query('ROLLBACK');
                throw $error;
            }
        } else {
            $product->save();
        }
        foreach (array('S', 'M', 'L') as $size) {
            $existing_child_id = wc_get_product_id_by_sku($demo[0] . '-' . $size);
            if ($existing_child_id) {
                $existing_child = wc_get_product($existing_child_id);
                if (! $existing_child instanceof WC_Product_Variation || $existing_child->get_parent_id() !== $product->get_id()) { WP_CLI::error('Demo variation SKU belongs to another product; installation stopped without changing it.'); }
                continue;
            }
            $variation = new WC_Product_Variation();
            $variation->set_parent_id($product->get_id());
            $variation->set_sku($demo[0] . '-' . $size);
            $variation->set_attributes(array('pa_size' => sanitize_title($size), 'pa_color' => 'naturvit'));
            $variation->set_regular_price($demo[3]);
            $variation->set_manage_stock(true);
            $variation->set_stock_quantity(10);
            $variation->set_stock_status('instock');
            // A new child's SKU, attributes, price and stock must be durable
            // together before a later retry treats that SKU as complete.
            global $wpdb;
            if ($wpdb->query('START TRANSACTION') === false) { throw new RuntimeException('Could not start the initial demo variation transaction.'); }
            try {
                $variation->save();
                if ($wpdb->query('COMMIT') === false) { throw new RuntimeException('Could not commit the initial demo variation transaction.'); }
            } catch (Throwable $error) {
                $wpdb->query('ROLLBACK');
                throw $error;
            }
        }
        WC_Product_Variable::sync($product->get_id());
        foreach (array('product_collection' => 'Demo-kollektion', 'product_designer' => 'Demo-designer') as $taxonomy => $term) {
            if (! wp_get_object_terms($product->get_id(), $taxonomy, array('fields' => 'ids'))) { wp_set_object_terms($product->get_id(), $term, $taxonomy); }
        }
        shop_bootstrap_demo_image($product, $demo[2], 'Illustration av en demoprodukt: ' . $demo[1]);
    }
}

flush_rewrite_rules(false);
update_option('shop_template_bootstrapped', '0.1.0');
WP_CLI::success('Shop initialized. Launch remains blocked until the missing integrations and launch requirements are implemented and configured.');
