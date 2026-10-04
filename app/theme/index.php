<?php if (! defined('ABSPATH')) { exit; } get_header(); ?>
<main id="main-content" class="container content-main page-content">
    <?php if (have_posts()) : while (have_posts()) : the_post(); ?><article><h1><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h1><?php the_content(); ?></article><?php endwhile; the_posts_navigation(); else : ?><h1>Inget att visa ännu</h1><p>Inga publicerade sidor matchar din sökning.</p><a class="button" href="<?php echo esc_url(home_url('/')); ?>">Till startsidan</a><?php endif; ?>
</main>
<?php get_footer(); ?>
