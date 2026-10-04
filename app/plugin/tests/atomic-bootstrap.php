<?php
/** Real WP interruption at the boundary between product row and ownership meta. */
if (! defined('ABSPATH') || ! class_exists('WooCommerce') || get_option('shop_template_bootstrapped')) { throw new RuntimeException('Fresh isolated database is required.'); }
$interrupt = function ($post_id, $post, $update) {
    if (! $update && $post->post_type === 'product' && $post->post_title === 'Demo: T-shirt') {
        throw new RuntimeException('Simulated interruption before demo ownership metadata.');
    }
};
add_action('wp_insert_post', $interrupt, 10, 3);
$interrupted = false;
try {
    require dirname(__DIR__, 2) . '/bootstrap.php';
} catch (RuntimeException $error) {
    if ($error->getMessage() !== 'Simulated interruption before demo ownership metadata.') { throw $error; }
    $interrupted = true;
} finally { remove_action('wp_insert_post', $interrupt, 10); }
if (! $interrupted) { throw new RuntimeException('Failure injection did not reach the real product write.'); }
$orphans = get_posts(array('post_type' => 'product', 'post_status' => 'any', 'title' => 'Demo: T-shirt', 'fields' => 'ids'));
if ($orphans) { throw new RuntimeException('Interrupted parent write left an unowned visible product instead of rolling back.'); }
if (get_option('shop_template_bootstrapped')) { throw new RuntimeException('Interrupted installer marked initialization complete.'); }
WP_CLI::success('Interrupted initial demo product row rolls back before ownership metadata, without successful marker.');
