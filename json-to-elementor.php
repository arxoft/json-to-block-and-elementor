<?php
/*
Plugin Name: JSON to Elementor and Block Importer
Description: Imports a JSON file to create an Elementor page with matching layout, content, and styles.
Version: 1.1
Author: Arxoft
Text Domain: json-to-block-and-elementor
*/

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

register_activation_hook(__FILE__, 'json_to_block_and_elementor_activate');
function json_to_block_and_elementor_activate() {

    // Check if Elementor is active
    if (!did_action('elementor/loaded')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            __('JSON to Elementor Importer requires Elementor to be installed and activated.', 'json-to-block-and-elementor'),
            __('Plugin Activation Error', 'json-to-block-and-elementor'),
            array('back_link' => true)
        );
    }

    // Initialize plugin options
    $options = array(
        'version' => '1.1',
        'last_import' => 0,
    );
    add_option('json_to_block_and_elementor_options', $options);

    // Log activation
    error_log('JSON to Elementor Importer activated on ' . date('Y-m-d H:i:s'));
}

register_deactivation_hook(__FILE__, 'json_to_block_and_elementor_deactivate');
function json_to_block_and_elementor_deactivate() {

    // Clear Elementor cache
    if (class_exists('\Elementor\Plugin')) {
        \Elementor\Plugin::$instance->files_manager->clear_cache();
    }

    // Delete transients
    delete_transient('json_to_block_and_elementor_import_cache');

    // Log deactivation
    error_log('JSON to Elementor Importer deactivated on ' . date('Y-m-d H:i:s'));
}

// Register admin menu
add_action('admin_menu', 'json_to_block_and_elementor_menu');
function json_to_block_and_elementor_menu() {
    add_menu_page(
        __('JSON to Block / Elementor', 'json-to-block-and-elementor'),
        __('JSON to Block / Elementor', 'json-to-block-and-elementor'),
        'manage_options',
        'json-to-block-and-elementor',
        'json_to_block_and_elementor_admin_page',
        'dashicons-upload',
        80
    );
}

// Admin page for JSON upload
function json_to_block_and_elementor_admin_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.', 'json-to-block-and-elementor'));
    }

    // Check if Elementor is active
    if (!did_action('elementor/loaded')) {
        echo '<div class="error"><p>' . esc_html__('Elementor is not active. Please install and activate Elementor.', 'json-to-block-and-elementor') . '</p></div>';
        return;
    }

    // Handle file upload
    if (isset($_FILES['json_file']) && $_FILES['json_file']['error'] === UPLOAD_ERR_OK) {
        $json_file = $_FILES['json_file']['tmp_name'];
        $json_data = json_decode(file_get_contents($json_file), true);

        if (json_last_error() === JSON_ERROR_NONE && isset($json_data['data'])) {
            $result = json_to_block_and_elementor_create_page($json_data['data']);
            echo '<div class="updated"><p>' . wp_kses_post($result) . '</p></div>';
        } else {
            echo '<div class="error"><p>' . esc_html__('Invalid JSON file.', 'json-to-block-and-elementor') . '</p></div>';
        }
    }

    ?>
    <div class="wrap">
        <h1><?php esc_html_e('JSON to Elementor Importer', 'json-to-block-and-elementor'); ?></h1>
        <form method="post" enctype="multipart/form-data">
            <p><label for="json_file"><?php esc_html_e('Upload JSON File:', 'json-to-block-and-elementor'); ?></label></p>
            <p><input type="file" name="json_file" id="json_file" accept=".json" required></p>
            <p><?php submit_button(__('Import JSON', 'json-to-block-and-elementor')); ?></p>
        </form>
    </div>
    <?php
}

// Create Elementor page from JSON
function json_to_block_and_elementor_create_page($data) {
    // Create new page
    $page = array(
        'post_title'   => sanitize_text_field($data['name'] ?? 'Imported Page'),
        'post_content' => '',
        'post_status'  => 'publish',
        'post_type'    => 'page',
        'meta_input'   => array(
            '_elementor_edit_mode' => 'builder',
            '_elementor_template_type' => 'wp-page',
        ),
    );
    $page_id = wp_insert_post($page);

    if (is_wp_error($page_id)) {
        return esc_html__('Error creating page: ', 'json-to-block-and-elementor') . $page_id->get_error_message();
    }

    // Initialize Elementor data
    $elements = array();
    $custom_css = '';

    // Parse styles
    $styles = $data['styles'] ?? array();
    if (!empty($styles['fontFaceRules'])) {
        $custom_css .= implode("\n", $styles['fontFaceRules']) . "\n";
    }
    if (!empty($styles['css'])) {
        foreach ($styles['css'] as $key => $value) {
            $custom_css .= "$key: $value;\n";
        }
    }

    // Process content sections
    foreach ($data['content'] as $section) {
        $section_type = $section['type'];
        $section_content = $section['content'];
        $section_layout = $section['layout'] ?? 'default';

        $section_element = array(
            'id' => wp_generate_uuid4(),
            'elType' => 'section',
            'settings' => array(),
            'elements' => array(
                array(
                    'id' => wp_generate_uuid4(),
                    'elType' => 'column',
                    'settings' => array('_column_size' => 100),
                    'elements' => array(),
                ),
            ),
        );

        $column = &$section_element['elements'][0]['elements'];

        // Map sections to Elementor widgets
        switch ($section_type) {
            case 'hero':
                // Eyebrow
                if (!empty($section_content['eyebrow']['text'])) {
                    $column[] = array(
                        'id' => wp_generate_uuid4(),
                        'elType' => 'widget',
                        'widgetType' => 'heading',
                        'settings' => array(
                            'title' => wp_kses_post($section_content['eyebrow']['text']),
                            'header_size' => 'h6',
                            'typography_typography' => 'custom',
                            'typography_font_family' => $styles['css']['--lr-hero-eyebrow-font-family'] ?? 'Inter, sans-serif',
                            'typography_font_size' => array('size' => str_replace('px', '', $styles['css']['--lr-hero-eyebrow-font-size'] ?? '13'), 'unit' => 'px'),
                            'typography_font_weight' => $styles['css']['--lr-hero-eyebrow-font-weight'] ?? '600',
                            'typography_text_transform' => $styles['css']['--lr-hero-eyebrow-text-transform'] ?? 'uppercase',
                            'typography_letter_spacing' => array('size' => str_replace('em', '', $styles['css']['--lr-hero-eyebrow-letter-spacing'] ?? '0.05'), 'unit' => 'em'),
                        ),
                    );
                }

                // Title
                if (!empty($section_content['title'])) {
                    $column[] = array(
                        'id' => wp_generate_uuid4(),
                        'elType' => 'widget',
                        'widgetType' => 'heading',
                        'settings' => array(
                            'title' => wp_kses_post($section_content['title']),
                            'header_size' => 'h1',
                            'typography_typography' => 'custom',
                            'typography_font_family' => $styles['css']['--lr-hero-title-font-family'] ?? 'Inter, sans-serif',
                            'typography_font_size' => array('size' => str_replace('px', '', $styles['css']['--lr-hero-title-font-size'] ?? '56'), 'unit' => 'px'),
                            'typography_font_weight' => $styles['css']['--lr-hero-title-font-weight'] ?? '900',
                            'typography_text_transform' => $styles['css']['--lr-hero-title-text-transform'] ?? 'none',
                            'typography_letter_spacing' => array('size' => str_replace('em', '', $styles['css']['--lr-hero-title-letter-spacing'] ?? '-0.02'), 'unit' => 'em'),
                        ),
                    );
                }

                // Subtitle
                if (!empty($section_content['subtitle'])) {
                    $column[] = array(
                        'id' => wp_generate_uuid4(),
                        'elType' => 'widget',
                        'widgetType' => 'text-editor',
                        'settings' => array(
                            'editor' => wp_kses_post($section_content['subtitle']),
                            'typography_typography' => 'custom',
                            'typography_font_family' => $styles['css']['--lr-hero-subtitle-font-family'] ?? 'Inter, sans-serif',
                            'typography_font_size' => array('size' => str_replace('px', '', $styles['css']['--lr-hero-subtitle-font-size'] ?? '18'), 'unit' => 'px'),
                            'typography_font_weight' => $styles['css']['--lr-hero-subtitle-font-weight'] ?? '400',
                        ),
                    );
                }

                // CTAs
                if (!empty($section_content['ctas'])) {
                    foreach ($section_content['ctas'] as $cta) {
                        $column[] = array(
                            'id' => wp_generate_uuid4(),
                            'elType' => 'widget',
                            'widgetType' => 'button',
                            'settings' => array(
                                'text' => wp_kses_post($cta['text']),
                                'link' => array('url' => esc_url($cta['link']['href']), 'is_external' => true),
                                'button_type' => $cta['variant'] === 'primary' ? 'default' : 'outline',
                                'typography_typography' => 'custom',
                                'typography_font_family' => $styles['css']['--lr-hero-button-font-family'] ?? 'Inter, sans-serif',
                                'typography_font_size' => array('size' => str_replace('px', '', $styles['css']['--lr-hero-button-font-size'] ?? '16'), 'unit' => 'px'),
                                'typography_font_weight' => $styles['css']['--lr-hero-button-weight'] ?? '600',
                            ),
                        );
                    }
                }

                // Smallprint
                if (!empty($section_content['smallprint'])) {
                    $column[] = array(
                        'id' => wp_generate_uuid4(),
                        'elType' => 'widget',
                        'widgetType' => 'text-editor',
                        'settings' => array(
                            'editor' => wp_kses_post($section_content['smallprint']),
                            'typography_typography' => 'custom',
                            'typography_font_family' => $styles['css']['--lr-hero-smallprint-font-family'] ?? 'Inter, sans-serif',
                            'typography_font_size' => array('size' => str_replace('px', '', $styles['css']['--lr-hero-smallprint-font-size'] ?? '12'), 'unit' => 'px'),
                            'typography_font_weight' => $styles['css']['--lr-hero-smallprint-font-weight'] ?? '600',
                            'typography_text_transform' => $styles['css']['--lr-hero-smallprint-text-transform'] ?? 'uppercase',
                        ),
                    );
                }

                // Apply section styles
                $section_element['settings']['background_background'] = 'classic';
                $section_element['settings']['background_color'] = $styles['css']['--lr-hero-background-a-color'] ?? '#f8fafc';
                if (!empty($styles['css']['--lr-hero-background-b-image'])) {
                    $section_element['settings']['background_background'] = 'classic';
                    $section_element['settings']['background_image'] = array('url' => $styles['css']['--lr-hero-background-b-image']);
                    $section_element['settings']['background_position'] = $styles['css']['--lr-hero-background-b-position'] ?? 'center';
                    $section_element['settings']['background_size'] = $styles['css']['--lr-hero-background-b-size'] ?? 'cover';
                    $section_element['settings']['background_repeat'] = $styles['css']['--lr-hero-background-b-repeat'] ?? 'no-repeat';
                }
                break;

            // more section types ...

            default:
                // Fallback: Text Editor for unknown sections
                $column[] = array(
                    'id' => wp_generate_uuid4(),
                    'elType' => 'widget',
                    'widgetType' => 'text-editor',
                    'settings' => array(
                        'editor' => wp_kses_post(json_encode($section_content)),
                    ),
                );
                break;
        }

        $elements[] = $section_element;
    }

    // Save Elementor data
    update_post_meta($page_id, '_elementor_data', wp_json_encode($elements));
    update_post_meta($page_id, '_elementor_css', array('css' => $custom_css));

    // Enqueue custom fonts
    add_action('wp_enqueue_scripts', function () use ($styles) {
        if (!empty($styles['fontFaceRules'])) {
            wp_enqueue_style('json-to-block-and-elementor-fonts', '', array(), null);
            wp_add_inline_style('json-to-block-and-elementor-fonts', implode("\n", $styles['fontFaceRules']));
        }
    });

    // Trigger Elementor to regenerate CSS
    if (class_exists('\Elementor\Plugin')) {
        \Elementor\Plugin::$instance->files_manager->clear_cache();
    }

    // Update import timestamp
    $options = get_option('json_to_block_and_elementor_options', array());
    $options['last_import'] = time();
    update_option('json_to_block_and_elementor_options', $options);

    return sprintf(
        esc_html__('Page created successfully! <a href="%s">View Page</a>', 'json-to-block-and-elementor'),
        esc_url(get_permalink($page_id))
    );
}

// Enqueue admin scripts for file upload
add_action('admin_enqueue_scripts', function ($hook) {
    if ($hook !== 'toplevel_page_json-to-block-and-elementor') {
        return;
    }
    wp_enqueue_script('json-to-block-and-elementor-admin', plugin_dir_url(__FILE__) . 'admin.js', array('jquery'), '1.1', true);
});

// Create admin.js if it doesn't exist
add_action('admin_init', function () {
    $admin_js_path = plugin_dir_path(__FILE__) . 'admin.js';
    if (!file_exists($admin_js_path)) {
        file_put_contents($admin_js_path, '// JSON to Elementor admin scripts');
    }
});
