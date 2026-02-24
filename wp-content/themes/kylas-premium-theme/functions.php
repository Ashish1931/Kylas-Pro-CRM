<?php
// Disable Gutenberg CSS
add_action('wp_print_styles', function() {
    wp_dequeue_style('wp-block-library');
    wp_dequeue_style('wp-block-library-theme');
    wp_dequeue_style('wc-block-style');
}, 100);

// Add support for title tag
add_theme_support('title-tag');

// Enqueue styles
add_action('wp_enqueue_scripts', function() {
    wp_enqueue_style('kylas-main', get_stylesheet_uri());
});
