<?php if (! defined('ABSPATH')) { exit; } ?>
<footer class="site-footer">
    <div class="container">
        <div class="footer-grid">
            <div><h2><?php bloginfo('name'); ?></h2><?php $text = get_theme_mod('shop_footer_text', ''); if ($text) { echo '<p>' . nl2br(esc_html($text)) . '</p>'; } ?></div>
            <div><h2>Butiken</h2><?php if (has_nav_menu('footer')) { wp_nav_menu(array('theme_location' => 'footer', 'container' => false, 'depth' => 1)); } elseif (function_exists('wc_get_page_permalink')) { ?><ul><li><a href="<?php echo esc_url(wc_get_page_permalink('shop')); ?>">Alla plagg</a></li><li><a href="<?php echo esc_url(wc_get_page_permalink('myaccount')); ?>">Mitt konto</a></li></ul><?php } ?></div>
            <?php
            $legal_links = array();
            foreach (array('kontakt' => 'Kontakt', 'frakt-retur' => 'Frakt och retur', 'kopvillkor' => 'Köpvillkor', 'integritet' => 'Integritet och cookies') as $slug => $label) {
                $page = get_page_by_path($slug);
                if ($page && $page->post_status === 'publish') { $legal_links[] = '<li><a href="' . esc_url(get_permalink($page)) . '">' . esc_html($label) . '</a></li>'; }
            }
            if ($legal_links) { echo '<div><h2>Information</h2><ul>' . implode('', $legal_links) . '</ul></div>'; }
            ?>
        </div>
        <div class="footer-bottom">&copy; <?php echo esc_html(wp_date('Y')); ?> <?php bloginfo('name'); ?></div>
    </div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
