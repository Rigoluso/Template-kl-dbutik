<?php if (! defined('ABSPATH')) { exit; } ?>
<!doctype html>
<html <?php language_attributes(); ?> data-profile="<?php echo esc_attr(get_theme_mod('shop_profile', 'light')); ?>">
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip-link" href="#main-content">Hoppa till innehållet</a>
<?php $campaign = get_theme_mod('shop_campaign', ''); if ($campaign) : ?>
<div class="campaign"><?php echo esc_html($campaign); ?></div>
<?php endif; ?>
<header class="site-header">
    <div class="container header-inner">
        <div class="brand-wrap"><?php if (has_custom_logo()) { the_custom_logo(); } else { ?><a class="brand" href="<?php echo esc_url(home_url('/')); ?>"><?php bloginfo('name'); ?></a><?php } ?></div>
        <nav class="site-nav" id="site-navigation" aria-label="Huvudmeny" data-open="true"><?php wp_nav_menu(array('theme_location' => 'primary', 'container' => false, 'depth' => 1, 'fallback_cb' => 'kladbutik_fallback_menu')); ?></nav>
        <div class="header-actions">
            <button class="icon-button theme-toggle" id="theme-toggle" type="button" aria-pressed="false" hidden>Mörkt</button>
            <?php if (function_exists('wc_get_page_permalink')) : ?>
                <a class="account-link" href="<?php echo esc_url(wc_get_page_permalink('myaccount')); ?>">Mitt konto</a>
                <a href="<?php echo esc_url(wc_get_cart_url()); ?>">Varukorg<?php if (WC()->cart && WC()->cart->get_cart_contents_count()) { echo ' (' . absint(WC()->cart->get_cart_contents_count()) . ')'; } ?></a>
            <?php endif; ?>
            <button class="icon-button mobile-menu" id="menu-toggle" type="button" aria-controls="site-navigation" aria-expanded="true">Meny</button>
        </div>
    </div>
</header>
