<?php
if (! defined('ABSPATH')) { exit; }

function kladbutik_setup() {
    load_theme_textdomain('kladbutik', get_template_directory() . '/languages');
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('custom-logo', array('height' => 60, 'width' => 240, 'flex-width' => true, 'flex-height' => true));
    add_theme_support('html5', array('search-form', 'gallery', 'caption', 'style', 'script'));
    add_theme_support('woocommerce');
    add_theme_support('wc-product-gallery-zoom');
    add_theme_support('wc-product-gallery-lightbox');
    add_theme_support('wc-product-gallery-slider');
    add_theme_support('responsive-embeds');
    add_theme_support('align-wide');
    register_nav_menus(array('primary' => __('Huvudmeny', 'kladbutik'), 'footer' => __('Sidfot', 'kladbutik')));
}
add_action('after_setup_theme', 'kladbutik_setup');

function kladbutik_assets() {
    $version = wp_get_theme()->get('Version');
    wp_enqueue_style('kladbutik', get_stylesheet_uri(), array(), $version);
    wp_enqueue_script('kladbutik', get_template_directory_uri() . '/theme.js', array(), $version, true);
    $accent = sanitize_hex_color(get_theme_mod('shop_accent', '#365d41')) ?: '#365d41';
    $dark_accent = sanitize_hex_color(get_theme_mod('shop_dark_accent', '#c4d7b4')) ?: '#c4d7b4';
    $font = get_theme_mod('shop_font', 'sans') === 'serif' ? 'Georgia,"Times New Roman",serif' : 'system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif';
    wp_add_inline_style('kladbutik', ':root{--accent:' . $accent . ';--font:' . $font . ';}:root[data-profile="dark"]{--accent:' . $dark_accent . ';}');
}
add_action('wp_enqueue_scripts', 'kladbutik_assets');

function kladbutik_choice($value, $setting) {
    $control = $setting->manager->get_control($setting->id);
    return isset($control->choices[$value]) ? $value : $setting->default;
}

function kladbutik_customize($customizer) {
    $customizer->add_section('shop_design', array('title' => __('Butikens design', 'kladbutik'), 'priority' => 30));
    $fields = array(
        'shop_eyebrow' => array('Kollektioner för din vardag', 'Liten rubrik', 'text'),
        'shop_heading' => array('Kläder. Din stil.', 'Huvudrubrik', 'text'),
        'shop_description' => array('Upptäck plagg och designers i vår butik.', 'Ingress', 'textarea'),
        'shop_campaign' => array('', 'Kampanjbanner (tomt = dold)', 'text'),
        'shop_collection_heading' => array('Upptäck kollektionerna', 'Rubrik för kollektioner', 'text'),
        'shop_products_heading' => array('Senaste plaggen', 'Rubrik för produkter', 'text'),
        'shop_footer_text' => array('', 'Text i sidfot', 'textarea'),
    );
    foreach ($fields as $id => $field) {
        $customizer->add_setting($id, array('default' => $field[0], 'sanitize_callback' => 'sanitize_textarea_field'));
        $customizer->add_control($id, array('label' => $field[1], 'section' => 'shop_design', 'type' => $field[2]));
    }
    foreach (array('shop_accent' => array('#365d41', 'Accentfärg ljus profil'), 'shop_dark_accent' => array('#c4d7b4', 'Accentfärg mörk profil')) as $id => $field) {
        $customizer->add_setting($id, array('default' => $field[0], 'sanitize_callback' => 'sanitize_hex_color'));
        $customizer->add_control(new WP_Customize_Color_Control($customizer, $id, array('label' => $field[1], 'section' => 'shop_design')));
    }
    $customizer->add_setting('shop_profile', array('default' => 'light', 'sanitize_callback' => 'kladbutik_choice'));
    $customizer->add_control('shop_profile', array('label' => 'Visuell profil', 'section' => 'shop_design', 'type' => 'select', 'choices' => array('light' => 'Ljus', 'dark' => 'Mörk', 'auto' => 'Följ besökarens system')));
    $customizer->add_setting('shop_font', array('default' => 'sans', 'sanitize_callback' => 'kladbutik_choice'));
    $customizer->add_control('shop_font', array('label' => 'Typsnitt', 'section' => 'shop_design', 'type' => 'select', 'choices' => array('sans' => 'Systemtypsnitt utan seriffer', 'serif' => 'Georgia med seriffer')));
    $customizer->add_setting('shop_hero_image', array('default' => 0, 'sanitize_callback' => 'absint'));
    $customizer->add_control(new WP_Customize_Media_Control($customizer, 'shop_hero_image', array('label' => 'Bild på startsidan', 'section' => 'shop_design', 'mime_type' => 'image')));
}
add_action('customize_register', 'kladbutik_customize');

function kladbutik_fallback_menu() {
    if (function_exists('wc_get_page_permalink')) {
        echo '<ul><li><a href="' . esc_url(wc_get_page_permalink('shop')) . '">Alla plagg</a></li></ul>';
    }
}

function kladbutik_catalog_filter_query($query) {
    if (is_admin() || ! $query->is_main_query() || ! function_exists('is_shop') || ! (is_shop() || is_product_taxonomy())) { return; }
    $tax_query = $query->get('tax_query') ?: array();
    foreach (array('designer' => 'product_designer', 'collection' => 'product_collection', 'category' => 'product_cat', 'size' => 'pa_size', 'color' => 'pa_color') as $parameter => $taxonomy) {
        if (isset($_GET[$parameter]) && is_string($_GET[$parameter]) && taxonomy_exists($taxonomy)) {
            $slug = sanitize_title(wp_unslash($_GET[$parameter]));
            if ($slug !== '') { $tax_query[] = array('taxonomy' => $taxonomy, 'field' => 'slug', 'terms' => $slug); }
        }
    }
    $query->set('tax_query', $tax_query);
}
add_action('pre_get_posts', 'kladbutik_catalog_filter_query', 30);

function kladbutik_filter_select($parameter, $taxonomy, $label) {
    if (! taxonomy_exists($taxonomy)) { return; }
    $terms = get_terms(array('taxonomy' => $taxonomy, 'hide_empty' => true));
    if (is_wp_error($terms) || ! $terms) { return; }
    $chosen = isset($_GET[$parameter]) && is_string($_GET[$parameter]) ? sanitize_title(wp_unslash($_GET[$parameter])) : '';
    echo '<div><label for="filter-' . esc_attr($parameter) . '">' . esc_html($label) . '</label><select id="filter-' . esc_attr($parameter) . '" name="' . esc_attr($parameter) . '"><option value="">Alla</option>';
    foreach ($terms as $term) { echo '<option value="' . esc_attr($term->slug) . '"' . selected($chosen, $term->slug, false) . '>' . esc_html($term->name) . '</option>'; }
    echo '</select></div>';
}

// Private sessions and checkout pages must never invite search indexing.
function kladbutik_private_robots($robots) {
    if (function_exists('is_cart') && (is_cart() || is_checkout() || is_account_page() || is_wc_endpoint_url('order-received') || is_wc_endpoint_url('order-pay'))) {
        $robots['noindex'] = true; $robots['nofollow'] = true;
    }
    return $robots;
}
add_filter('wp_robots', 'kladbutik_private_robots');
