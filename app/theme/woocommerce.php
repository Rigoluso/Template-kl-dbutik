<?php if (! defined('ABSPATH')) { exit; } get_header(); ?>
<main id="main-content" class="container content-main">
    <?php $catalog = is_shop() || is_product_taxonomy(); if ($catalog) : ?>
    <div class="shop-layout">
        <aside class="shop-filters" aria-labelledby="filter-title">
            <h2 id="filter-title">Hitta dina plagg</h2>
            <form action="<?php echo esc_url(wc_get_page_permalink('shop')); ?>" method="get">
                <div class="filter-search"><label for="catalog-search">Sök produkter</label><input type="search" id="catalog-search" name="s" value="<?php echo esc_attr(get_search_query()); ?>"><input type="hidden" name="post_type" value="product"></div>
                <?php kladbutik_filter_select('category', 'product_cat', 'Kategori'); kladbutik_filter_select('designer', 'product_designer', 'Designer'); kladbutik_filter_select('collection', 'product_collection', 'Kollektion'); kladbutik_filter_select('size', 'pa_size', 'Storlek'); kladbutik_filter_select('color', 'pa_color', 'Färg'); ?>
                <div class="filter-price"><div class="price-range"><div><label for="min-price">Minsta pris (kr)</label><input type="number" min="0" step="1" id="min-price" name="min_price" value="<?php echo isset($_GET['min_price']) && is_scalar($_GET['min_price']) ? esc_attr(absint($_GET['min_price'])) : ''; ?>"></div><div><label for="max-price">Högsta pris (kr)</label><input type="number" min="0" step="1" id="max-price" name="max_price" value="<?php echo isset($_GET['max_price']) && is_scalar($_GET['max_price']) ? esc_attr(absint($_GET['max_price'])) : ''; ?>"></div></div></div>
                <button class="button" type="submit">Visa plagg</button><a class="clear-filters" href="<?php echo esc_url(wc_get_page_permalink('shop')); ?>">Rensa filter</a>
            </form>
        </aside>
        <div class="shop-catalog"><?php woocommerce_content(); ?></div>
    </div>
    <?php else : woocommerce_content(); endif; ?>
</main>
<?php get_footer(); ?>
