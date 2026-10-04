<?php
/**
 * Plugin Name: Klädbutik – administrationsgrund och lanseringskontroll
 * Description: Persistent shop settings, product information and server-side purchase guard.
 * Version: 0.1.0
 * License: GPL-2.0-or-later
 */
if (! defined('ABSPATH')) { exit; }
require_once __DIR__ . '/shop-launch-policy.php';

function shop_template_mode(): string {
    return getenv('SHOP_MODE') ?: 'demo';
}

function shop_template_launch_errors(): array {
    $settings = get_option('shop_template_settings', array());
    $settings = is_array($settings) ? $settings : array();
    $legal_pages_published = true;
    foreach (array('kopvillkor', 'integritet', 'frakt-retur', 'angerratt', 'kontakt') as $slug) {
        $page = get_page_by_path($slug);
        if (! $page || $page->post_status !== 'publish') { $legal_pages_published = false; }
    }
    $state = array_merge($settings, array(
        'mode' => shop_template_mode(),
        'legal_pages_published' => $legal_pages_published,
        // Extensions must implement and verify these behaviors. Admin checkboxes
        // cannot turn an absent payment/legal integration into a working one.
        'payment_ready' => (bool) apply_filters('shop_template_payment_ready', false),
        'withdrawal_ready' => (bool) apply_filters('shop_template_withdrawal_ready', false),
        'price_history_ready' => (bool) apply_filters('shop_template_price_history_ready', false),
        'mail_retry_ready' => (bool) apply_filters('shop_template_mail_retry_ready', false),
    ));
    foreach (array('legal_reviewed', 'products_reviewed', 'shipping_reviewed', 'email_reviewed', 'launch_requested') as $key) {
        $state[$key] = ! empty($settings[$key]);
    }
    return shop_template_policy_errors($state);
}

function shop_template_checkout_enabled(): bool {
    return shop_template_launch_errors() === array();
}

function shop_template_purchase_error(): WP_Error {
    return new WP_Error('shop_not_launched', 'Köp är avstängda. Butiken är ännu inte färdig för betalning.', array('status' => 503));
}

function shop_template_rest_purchase_guard($result, $server, $request) {
    if (shop_template_checkout_enabled()) { return $result; }
    $route = strtolower($request->get_route());
    if (preg_match('#^/wc/store/(?:v[0-9]+/)?checkout(?:/|$)#', $route) ||
        (preg_match('#^/wc/v[0-9]+/orders(?:/|$)#', $route) && ! in_array($request->get_method(), array('GET', 'HEAD', 'OPTIONS'), true))) {
        return shop_template_purchase_error();
    }
    return $result;
}
add_filter('rest_pre_dispatch', 'shop_template_rest_purchase_guard', 5, 3);

function shop_template_classic_checkout_guard($data, $errors) {
    if (! shop_template_checkout_enabled()) { $errors->add('shop_not_launched', shop_template_purchase_error()->get_error_message()); }
}
add_action('woocommerce_after_checkout_validation', 'shop_template_classic_checkout_guard', 5, 2);

// Runs before WooCommerce's form handler at wp_loaded priority 20. Rejecting
// before order creation also protects a crafted direct POST without JavaScript.
function shop_template_direct_purchase_guard() {
    if (shop_template_checkout_enabled()) { return; }
    if (isset($_POST['woocommerce_checkout_place_order']) || isset($_POST['woocommerce_pay'])) {
        wp_die(esc_html(shop_template_purchase_error()->get_error_message()), 'Köp är avstängda', array('response' => 503));
    }
}
add_action('wp_loaded', 'shop_template_direct_purchase_guard', 5);

function shop_template_ajax_checkout_guard() {
    if (! shop_template_checkout_enabled()) {
        wp_send_json(array('result' => 'failure', 'messages' => '<ul class="woocommerce-error" role="alert"><li>' . esc_html(shop_template_purchase_error()->get_error_message()) . '</li></ul>', 'refresh' => false, 'reload' => false), 503);
    }
}
add_action('wc_ajax_checkout', 'shop_template_ajax_checkout_guard', 0);

function shop_template_order_pay_guard() {
    if (! shop_template_checkout_enabled() && function_exists('is_wc_endpoint_url') && is_wc_endpoint_url('order-pay')) {
        wp_die(esc_html(shop_template_purchase_error()->get_error_message()), 'Köp är avstängda', array('response' => 503));
    }
}
add_action('template_redirect', 'shop_template_order_pay_guard', 0);

function shop_template_block_gateways($gateways) {
    return shop_template_checkout_enabled() ? $gateways : array();
}
add_filter('woocommerce_available_payment_gateways', 'shop_template_block_gateways', 1);
add_filter('woocommerce_order_button_text', function () { return 'Beställ och betala'; });
add_filter('woocommerce_order_button_html', function ($html) {
    return shop_template_checkout_enabled() ? $html : '<p class="woocommerce-info" role="status">Köp är avstängda tills lanseringskontrollen är slutförd.</p>';
});

function shop_template_checkout_content($content) {
    if (! is_admin() && is_main_query() && in_the_loop() && function_exists('is_checkout') && is_checkout() && ! is_wc_endpoint_url('order-received') && ! shop_template_checkout_enabled()) {
        return '<div class="woocommerce"><div class="woocommerce-info" role="status"><strong>Köp är avstängda.</strong> ' . (shop_template_mode() === 'demo' ? 'Det här är en demobutik. Du kan prova produktval och varukorg utan riktiga betalningar.' : 'Butiken är ännu inte färdig för betalning.') . '</div><p><a class="button" href="' . esc_url(wc_get_cart_url()) . '">Tillbaka till varukorgen</a></p></div>';
    }
    return $content;
}
add_filter('the_content', 'shop_template_checkout_content', 9);

function shop_template_demo_banner() {
    if (shop_template_mode() === 'demo') {
        echo '<div class="shop-demo-banner" role="status"><strong>Demobutik.</strong> Exempelprodukter är inga verkliga varor. Du kan prova katalog och varukorg. Inga köp eller betalningar tas emot.</div>';
    } elseif (! shop_template_checkout_enabled()) {
        echo '<div class="shop-demo-banner" role="status">Butiken förbereds. Köp är tills vidare avstängda.</div>';
    }
}
add_action('wp_body_open', 'shop_template_demo_banner');

function shop_template_taxonomies() {
    $capabilities = array('manage_terms' => 'manage_product_terms', 'edit_terms' => 'edit_product_terms', 'delete_terms' => 'delete_product_terms', 'assign_terms' => 'assign_product_terms');
    foreach (array('product_designer' => array('Designers', 'Designer', 'designer'), 'product_collection' => array('Kollektioner', 'Kollektion', 'kollektion')) as $taxonomy => $names) {
        register_taxonomy($taxonomy, array('product'), array('label' => $names[0], 'labels' => array('name' => $names[0], 'singular_name' => $names[1]), 'public' => true, 'hierarchical' => true, 'show_ui' => true, 'show_in_rest' => true, 'show_admin_column' => true, 'capabilities' => $capabilities, 'rewrite' => array('slug' => $names[2], 'with_front' => false)));
    }
}
add_action('init', 'shop_template_taxonomies', 20);

function shop_template_product_fields(): array {
    return array(
        '_shop_fibre' => array('Fibersammansättning', 'Ange tillämplig textilinformation för den verkliga produkten.'),
        '_shop_care' => array('Skötselråd', 'Ange verifierade råd för tvätt och skötsel.'),
        '_shop_size_guide' => array('Storleksguide', 'Ange mått och enheter för detta plagg.'),
        '_shop_delivery' => array('Leveransinformation', 'Ange verkliga leveranstider och villkor.'),
        '_shop_product_identifier' => array('Produktidentifiering (GPSR)', 'Till exempel modell eller annan identifierare utöver SKU.'),
        '_shop_manufacturer' => array('Tillverkare (GPSR)', 'Namn samt tillämplig postadress och elektronisk kontaktadress.'),
        '_shop_eu_responsible' => array('Ansvarig aktör inom EU (GPSR)', 'Ange tillämplig ansvarig aktör och kontaktuppgifter när det krävs.'),
        '_shop_safety' => array('Varningar och säkerhetsinformation', 'Ange nödvändig information på svenska när den är tillämplig.'),
    );
}

function shop_template_product_admin_fields() {
    echo '<div class="options_group">';
    wp_nonce_field('shop_template_product_fields', '_shop_product_nonce');
    foreach (shop_template_product_fields() as $id => $field) {
        woocommerce_wp_textarea_input(array('id' => $id, 'label' => $field[0], 'description' => $field[1], 'desc_tip' => true));
    }
    echo '</div>';
}
add_action('woocommerce_product_options_general_product_data', 'shop_template_product_admin_fields');

function shop_template_save_product_information($product) {
    if (! current_user_can('edit_post', $product->get_id()) || ! isset($_POST['_shop_product_nonce']) || ! is_string($_POST['_shop_product_nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_shop_product_nonce'])), 'shop_template_product_fields')) { return; }
    foreach (shop_template_product_fields() as $id => $field) {
        if (isset($_POST[$id]) && is_string($_POST[$id])) { $product->update_meta_data($id, sanitize_textarea_field(wp_unslash($_POST[$id]))); }
    }
}
add_action('woocommerce_admin_process_product_object', 'shop_template_save_product_information');

function shop_template_information_tab($tabs) {
    global $product;
    if (! $product instanceof WC_Product) { return $tabs; }
    foreach (shop_template_product_fields() as $id => $field) {
        if ($product->get_meta($id)) { $tabs['shop_information'] = array('title' => 'Material och information', 'priority' => 15, 'callback' => 'shop_template_render_product_information'); break; }
    }
    return $tabs;
}
add_filter('woocommerce_product_tabs', 'shop_template_information_tab');

function shop_template_render_product_information() {
    global $product;
    if (! $product instanceof WC_Product) { return; }
    echo '<section class="product-information"><h2>Material och information</h2><dl>';
    foreach (shop_template_product_fields() as $id => $field) {
        $value = $product->get_meta($id);
        if ($value) { echo '<dt>' . esc_html($field[0]) . '</dt><dd>' . esc_html($value) . '</dd>'; }
    }
    echo '</dl></section>';
}

function shop_template_sanitize_settings($input): array {
    $input = is_array($input) ? $input : array();
    $result = array();
    foreach (array('company_name', 'organisation_number', 'address') as $key) {
        $value = isset($input[$key]) && is_string($input[$key]) ? $input[$key] : '';
        $result[$key] = $key === 'address' ? sanitize_textarea_field($value) : sanitize_text_field($value);
    }
    $result['contact_email'] = isset($input['contact_email']) && is_string($input['contact_email']) ? sanitize_email($input['contact_email']) : '';
    foreach (array('legal_reviewed', 'products_reviewed', 'shipping_reviewed', 'email_reviewed', 'launch_requested') as $key) { $result[$key] = isset($input[$key]) && is_scalar($input[$key]) && (string) $input[$key] === '1' ? 1 : 0; }
    return $result;
}

function shop_template_admin_settings() {
    register_setting('shop_template', 'shop_template_settings', array('type' => 'array', 'sanitize_callback' => 'shop_template_sanitize_settings', 'default' => array(), 'show_in_rest' => false));
}
add_action('admin_init', 'shop_template_admin_settings');
add_filter('option_page_capability_shop_template', function () { return 'manage_woocommerce'; });
add_action('admin_menu', function () { add_submenu_page('woocommerce', 'Lanseringskontroll', 'Lanseringskontroll', 'manage_woocommerce', 'shop-template', 'shop_template_admin_page'); });

function shop_template_admin_page() {
    if (! current_user_can('manage_woocommerce')) { wp_die('Du saknar behörighet.', '', array('response' => 403)); }
    $settings = get_option('shop_template_settings', array());
    $settings = is_array($settings) ? $settings : array();
    $errors = shop_template_launch_errors();
    echo '<div class="wrap"><h1>Lanseringskontroll</h1><p><strong>' . (shop_template_checkout_enabled() ? 'Konfigurationskontrollen är slutförd.' : 'Köp är avstängda på servern.') . '</strong> Miljö: ' . esc_html(shop_template_mode()) . '.</p>';
    echo '<p>Version 0.1.0 är en fungerande katalog- och administrationsgrund. Stripe Checkout, digital ångerfunktion, verifierad prishistorik och återförsök för ordermejl återstår. Markeringarna nedan dokumenterar ägarens kontroll; de ersätter inte fungerande integrationer och är ingen garanti om juridisk efterlevnad.</p>';
    if ($errors) { echo '<div class="notice notice-warning inline"><h2>Återstående krav</h2><ul>'; foreach ($errors as $message) { echo '<li>' . esc_html($message) . '</li>'; } echo '</ul></div>'; }
    echo '<form method="post" action="options.php">'; settings_fields('shop_template');
    echo '<table class="form-table" role="presentation">';
    foreach (array('company_name' => 'Företagsnamn', 'organisation_number' => 'Organisationsnummer', 'address' => 'Fullständig företagsadress', 'contact_email' => 'Kontaktadress för e-post') as $key => $label) {
        echo '<tr><th scope="row"><label for="shop-' . esc_attr($key) . '">' . esc_html($label) . '</label></th><td>';
        if ($key === 'address') { echo '<textarea class="large-text" rows="3" id="shop-' . esc_attr($key) . '" name="shop_template_settings[' . esc_attr($key) . ']">' . esc_textarea($settings[$key] ?? '') . '</textarea>'; }
        else { echo '<input class="regular-text" type="' . ($key === 'contact_email' ? 'email' : 'text') . '" id="shop-' . esc_attr($key) . '" name="shop_template_settings[' . esc_attr($key) . ']" value="' . esc_attr($settings[$key] ?? '') . '">'; }
        echo '</td></tr>';
    }
    foreach (array('legal_reviewed' => 'Företagets juridiska information har granskats.', 'products_reviewed' => 'Alla verkliga produkter har kontrollerad textil- och GPSR-information.', 'shipping_reviewed' => 'Moms och svensk frakt har konfigurerats och testats.', 'email_reviewed' => 'E-postleverans har konfigurerats och testats.', 'launch_requested' => 'Jag vill lansera när samtliga tekniska krav också är uppfyllda.') as $key => $label) {
        echo '<tr><th scope="row">Kontroll</th><td><label><input type="checkbox" name="shop_template_settings[' . esc_attr($key) . ']" value="1"' . checked(! empty($settings[$key]), true, false) . '> ' . esc_html($label) . '</label></td></tr>';
    }
    echo '</table>'; submit_button('Spara företagets uppgifter och kontroll'); echo '</form>';
    echo '<h2>Administration utan kod</h2><p>Produkter, varianter, SKU, lager, bilder och rabatter finns i WooCommerce. Designers och kollektioner finns under Produkter. Startsida, färger, typsnitt, logotyp och texter finns under Utseende → Anpassa. Sidor och menyer redigeras med WordPress ordinarie verktyg.</p></div>';
}
