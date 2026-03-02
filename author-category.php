<?php
/**
 * Plugin Name: Author Category
 * Plugin URI: https://github.com/projectestac/wordpress-author-category
 * Description: A simple plugin to limit authors to post in just a few categories.
 * Version: 1.0
 * License: GPL3
 */

include_once 'classes/author_category.class.php';

// Initialize the plugin in the admin area.
add_action('init', function() {
    if (is_admin()) {
        new author_category();
    }
});

/**
 * Enqueue scripts for the block editor (Gutenberg).
 *
 * This function checks if the current user is restricted to a specific category.
 * If so, it enqueues a JavaScript file to disable the category selection panel.
 */
add_action('enqueue_block_editor_assets', function () {
    $current_user = wp_get_current_user();
    $cat_id = get_user_meta($current_user->ID, '_author_cat', true);

    if (!empty($cat_id)) {
        $plugin_version = '1.0';
        wp_enqueue_script(
            'author-cat-block-script',
            plugins_url('javascript/disable_categories_in_editor.js', __FILE__),
            ['wp-blocks', 'wp-dom-ready', 'wp-edit-post'],
            $plugin_version,
            true // Load in the footer.
        );
    }
});
