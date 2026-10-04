<?php if (! defined('ABSPATH')) { exit; } get_header(); ?>
<main id="main-content" class="container content-main">
    <?php while (have_posts()) : the_post(); ?><article class="page-content"><h1><?php the_title(); ?></h1><?php the_content(); wp_link_pages(); ?></article><?php endwhile; ?>
</main>
<?php get_footer(); ?>
