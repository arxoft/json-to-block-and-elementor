<?php
/*
Plugin Name: Elementor JSON Importer
Description: Imports JSON layouts to Elementor pages
Version: 1.0
Author: Landing Rabbit
*/

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Include import functionality
require_once plugin_dir_path(__FILE__) . 'includes/import-hero.php';

// Activation hook
register_activation_hook(__FILE__, function () {
    error_log('Elementor JSON Importer activated');
    // Flush rewrite rules if needed
    flush_rewrite_rules();
});

// Deactivation hook
register_deactivation_hook(__FILE__, function () {
    error_log('Elementor JSON Importer deactivated');
    flush_rewrite_rules();
});

// Add admin menu for JSON import
add_action('admin_menu', function () {
    error_log('Registering JSON to Elementor menu'); // Debug
    add_management_page(
        'JSON to Elementor',
        'JSON to Elementor',
        'manage_options',
        'json-to-elementor',
        'render_import_page'
    );
});

// Render admin page
function render_import_page() {
    if (!class_exists('\Elementor\Plugin')) {
        echo '<div class="error"><p>Elementor is not active. Please activate the Elementor plugin.</p></div>';
        return;
    }
    ?>
    <div class="wrap">
        <h1>Import JSON to Elementor</h1>
        <form method="post" enctype="multipart/form-data">
            <input type="file" name="json_file" accept=".json">
            <?php wp_nonce_field('json_import_nonce', 'json_import_nonce'); ?>
            <input type="submit" class="button button-primary" name="import_json" value="Import">
        </form>
    </div>
    <?php
    if (isset($_POST['import_json']) && check_admin_referer('json_import_nonce', 'json_import_nonce')) {
        if (isset($_FILES['json_file']) && $_FILES['json_file']['error'] === UPLOAD_ERR_OK) {
            $json_data = json_decode(file_get_contents($_FILES['json_file']['tmp_name']), true);
            if ($json_data && isset($json_data['data']['content'])) {
                $result = import_hero_to_elementor($json_data);
                if (is_wp_error($result)) {
                    echo '<div class="error"><p>' . esc_html($result->get_error_message()) . '</p></div>';
                } else {
                    echo '<div class="updated"><p>Page created! <a href="' . get_edit_post_link($result) . '">Edit Page</a></p></div>';
                }
            } else {
                echo '<div class="error"><p>Invalid JSON structure</p></div>';
            }
        } else {
            echo '<div class="error"><p>File upload failed</p></div>';
        }
    }
}

// Ensure menu is visible in Docker environment
add_action('admin_init', function () {
    // Force flush permalinks to ensure menu registration
    flush_rewrite_rules();
});
