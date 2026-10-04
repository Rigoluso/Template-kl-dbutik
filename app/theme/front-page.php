<?php if (! defined('ABSPATH')) { exit; } get_header(); ?>
<main id="main-content">
    <section class="container hero" aria-labelledby="hero-title">
        <div>
            <p class="eyebrow"><?php echo esc_html(get_theme_mod('shop_eyebrow', 'Kollektioner för din vardag')); ?></p>
            <h1 id="hero-title"><?php echo esc_html(get_theme_mod('shop_heading', 'Kläder. Din stil.')); ?></h1>
            <p><?php echo esc_html(get_theme_mod('shop_description', 'Upptäck plagg och designers i vår butik.')); ?></p>
            <?php if (function_exists('wc_get_page_permalink')) : ?><a class="button" href="<?php echo esc_url(wc_get_page_permalink('shop')); ?>">Utforska alla plagg</a><?php endif; ?>
        </div>
        <?php $hero_image = absint(get_theme_mod('shop_hero_image', 0)); ?>
        <div class="hero-media<?php echo $hero_image ? ' has-image' : ''; ?>">
            <?php if ($hero_image) { echo wp_get_attachment_image($hero_image, 'large', false, array('fetchpriority' => 'high', 'loading' => 'eager')); } else { ?><div class="hero-placeholder" aria-hidden="true">DIN<br>STIL<small><?php bloginfo('name'); ?></small></div><?php } ?>
        </div>
    </section>
    <?php $collections = taxonomy_exists('product_collection') ? get_terms(array('taxonomy' => 'product_collection', 'hide_empty' => true, 'number' => 3)) : array(); if (! is_wp_error($collections) && $collections) : ?>
    <section class="section container" aria-labelledby="collections-title">
        <div class="section-heading"><h2 id="collections-title"><?php echo esc_html(get_theme_mod('shop_collection_heading', 'Upptäck kollektionerna')); ?></h2></div>
        <div class="collection-grid"><?php foreach ($collections as $collection) : ?><a class="collection-card" href="<?php echo esc_url(get_term_link($collection)); ?>"><h3><?php echo esc_html($collection->name); ?></h3><?php if ($collection->description) { echo '<p>' . esc_html(wp_trim_words($collection->description, 18)) . '</p>'; } ?><span>Utforska &rarr;</span></a><?php endforeach; ?></div>
    </section>
    <?php endif; ?>
    <?php if (class_exists('WooCommerce')) : ?>
    <section class="section container" aria-labelledby="products-title">
        <div class="section-heading"><h2 id="products-title"><?php echo esc_html(get_theme_mod('shop_products_heading', 'Senaste plaggen')); ?></h2><a href="<?php echo esc_url(wc_get_page_permalink('shop')); ?>">Visa alla plagg</a></div>
        <?php echo do_shortcode('[products limit="8" columns="4" orderby="date" order="DESC"]'); ?>
    </section>
    <?php endif; ?>
    <?php while (have_posts()) : the_post(); if (trim(get_the_content())) : ?><section class="section container page-content"><?php the_content(); ?></section><?php endif; endwhile; ?>
</main>
<?php get_footer(); ?>
