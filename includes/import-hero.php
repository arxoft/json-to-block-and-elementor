<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

function import_hero_to_elementor($json_data) {

    // Validate JSON
    if (!isset($json_data['data']['content']) || !isset($json_data['data']['styles'])) {
        return new WP_Error('invalid_json', 'Invalid JSON structure');
    }

    $content = $json_data['data']['content'];
    $styles = $json_data['data']['styles'];
    
    // Filter Hero sections
    $hero_sections = array_filter($content, function ($section) {
        return $section['type'] === 'hero';
    });

    if (empty($hero_sections)) {
        return new WP_Error('no_hero', 'No Hero sections found in JSON');
    }

    // Initialize Elementor data
    $elementor_data = [];

    // Process each Hero section
    foreach ($hero_sections as $hero) {
        $elementor_data[] = map_hero_to_elementor($hero, $styles);
    }

    // Enqueue custom fonts
    if (!empty($styles['fontFaceRules'])) {
        add_action('wp_enqueue_scripts', function () use ($styles) {
            $font_css = implode("\n", $styles['fontFaceRules']);
            wp_add_inline_style('elementor-frontend', $font_css);
        });
    }

    $version = defined('ELEMENTOR_VERSION') ? ELEMENTOR_VERSION : 'unknown';

    // Create new page
    $page_id = wp_insert_post([
        'post_title' => $json_data['data']['name'] ?? 'Imported Hero Page',
        'post_type' => 'page',
        'post_status' => 'publish',
        'meta_input' => [
            '_wp_page_template' => 'elementor_header_footer',
            '_elementor_edit_mode' => 'builder',
            '_elementor_version' => $version,
        ],
    ]);

    if (is_wp_error($page_id)) {
        return $page_id;
    }

    // Save Elementor data
    update_post_meta($page_id, '_elementor_data', wp_slash(wp_json_encode($elementor_data)));

    return $page_id;
}

function map_hero_to_elementor($hero, $styles) {
    $content = $hero['content'];
    $layout = $hero['layout'];
    $css_vars = $styles['css'];

    // Define section settings
    $section_settings = [
        'background_background' => 'classic',
        'background_color' => $css_vars['--lr-hero-background-a-color'] ?? '#f8fafc',
        'background_image' => [
            'url' => $css_vars['--lr-hero-background-b-image'] ?? '',
            'id' => attach_image_to_media_library($css_vars['--lr-hero-background-b-image'] ?? ''),
        ],
        'background_position' => $css_vars['--lr-hero-background-b-position'] ?? 'center center',
        'background_repeat' => $css_vars['--lr-hero-background-b-repeat'] ?? 'no-repeat',
        'background_size' => $css_vars['--lr-hero-background-b-size'] ?? 'cover',
        'background_opacity' => $css_vars['--lr-hero-background-b-opacity'] ?? '0.5',
        'layout' => 'flex', // Use Flexbox Container
        'gap' => ['size' => 20, 'unit' => 'px'],
        'padding' => ['top' => 80, 'bottom' => 80, 'left' => 20, 'right' => 20, 'unit' => 'px'],
    ];

    // Define container(s) based on layout
    $elements = [];
    if ($layout === 'horizontal') {
        // Two containers: text and image
        $text_container = [
            'id' => uniqid(),
            'elType' => 'container',
            'settings' => [
                'flex_direction' => 'column',
                'flex_gap' => ['size' => 20, 'unit' => 'px'],
                'content_width' => 'boxed',
                'width' => ['size' => 50, 'unit' => '%'],
            ],
            'elements' => get_hero_content_widgets($content, $css_vars),
        ];
        $image_container = [
            'id' => uniqid(),
            'elType' => 'container',
            'settings' => [
                'content_width' => 'boxed',
                'width' => ['size' => 50, 'unit' => '%'],
            ],
            'elements' => [
                [
                    'id' => uniqid(),
                    'elType' => 'widget',
                    'widgetType' => 'image',
                    'settings' => [
                        'image' => [
                            'url' => $css_vars['--lr-hero-background-b-image'] ?? '',
                            'id' => attach_image_to_media_library($css_vars['--lr-hero-background-b-image'] ?? ''),
                        ],
                        'image_border_radius' => ['size' => 10, 'unit' => 'px'],
                    ],
                ],
            ],
        ];
        $elements = [$text_container, $image_container];
        $section_settings['flex_direction'] = 'row';
        $section_settings['content_width'] = 'full';
    } else {
        // Single container for vertical layout
        $elements = [
            [
                'id' => uniqid(),
                'elType' => 'container',
                'settings' => [
                    'flex_direction' => 'column',
                    'flex_gap' => ['size' => 20, 'unit' => 'px'],
                    'content_width' => 'boxed',
                    'width' => ['size' => 80, 'unit' => '%'],
                    'align_items' => 'center',
                    'text_align' => 'center',
                ],
                'elements' => get_hero_content_widgets($content, $css_vars),
            ],
        ];
        $section_settings['content_width'] = 'boxed';
    }

    return [
        'id' => uniqid(),
        'elType' => 'section',
        'settings' => $section_settings,
        'elements' => $elements,
    ];
}

function get_hero_content_widgets($content, $css_vars) {
    $widgets = [];

    // Eyebrow
    if (!empty($content['eyebrow']['text'])) {
        $widgets[] = [
            'id' => uniqid(),
            'elType' => 'widget',
            'widgetType' => 'text-editor',
            'settings' => [
                'editor' => '<p>' . esc_html($content['eyebrow']['text']) . '</p>',
                'text_color' => $css_vars['--lr-hero-eyebrow-color'] ?? '#7c3aed',
                'typography_typography' => 'custom',
                'typography_font_family' => $css_vars['--lr-hero-eyebrow-font-family'] ?? 'Inter, sans-serif',
                'typography_font_size' => ['size' => 13, 'unit' => 'px'],
                'typography_font_weight' => $css_vars['--lr-hero-eyebrow-font-weight'] ?? '600',
                'typography_text_transform' => $css_vars['--lr-hero-eyebrow-text-transform'] ?? 'uppercase',
                'typography_letter_spacing' => ['size' => 0.05, 'unit' => 'em'],
                '_background_background' => 'classic',
                '_background_color' => sprintf('rgba(%s, %s)', $css_vars['--lr-hero-eyebrow-pill-background-color'] ?? '#7c3aed', $css_vars['--lr-hero-eyebrow-pill-opacity'] ?? '0.1'),
                '_border_radius' => ['size' => 100, 'unit' => 'px'],
                '_padding' => ['top' => 5, 'bottom' => 5, 'left' => 15, 'right' => 15, 'unit' => 'px'],
                '_margin' => ['top' => 0, 'bottom' => 20, 'left' => 0, 'right' => 0, 'unit' => 'px'],
            ],
        ];
    }

    // Title
    if (!empty($content['title'])) {
        $widgets[] = [
            'id' => uniqid(),
            'elType' => 'widget',
            'widgetType' => 'heading',
            'settings' => [
                'title' => wp_kses_post($content['title']), // Allows <em>, <strong>
                'header_size' => 'h1',
                'title_color' => $css_vars['--lr-hero-title-color'] ?? '#0f172a',
                'typography_typography' => 'custom',
                'typography_font_family' => $css_vars['--lr-hero-title-font-family'] ?? 'Inter, sans-serif',
                'typography_font_size' => ['size' => 56, 'unit' => 'px'],
                'typography_font_weight' => $css_vars['--lr-hero-title-font-weight'] ?? '900',
                'typography_letter_spacing' => ['size' => -0.02, 'unit' => 'em'],
                'typography_line_height' => ['size' => 1.1, 'unit' => 'em'],
            ],
        ];
    }

    // Subtitle
    if (!empty($content['subtitle'])) {
        $widgets[] = [
            'id' => uniqid(),
            'elType' => 'widget',
            'widgetType' => 'text-editor',
            'settings' => [
                'editor' => wp_kses_post($content['subtitle']), // Allows <p>, <a>, <strong>, <em>
                'text_color' => $css_vars['--lr-hero-subtitle-color'] ?? '#475569',
                'typography_typography' => 'custom',
                'typography_font_family' => $css_vars['--lr-hero-subtitle-font-family'] ?? 'Inter, sans-serif',
                'typography_font_size' => ['size' => 18, 'unit' => 'px'],
                'typography_font_weight' => $css_vars['--lr-hero-subtitle-font-weight'] ?? '400',
                'typography_line_height' => ['size' => 1.6, 'unit' => 'em'],
                '_margin' => ['top' => 20, 'bottom' => 20, 'left' => 0, 'right' => 0, 'unit' => 'px'],
            ],
        ];
    }

    // CTAs
    if (!empty($content['ctas'])) {
        $cta_container = [
            'id' => uniqid(),
            'elType' => 'container',
            'settings' => [
                'flex_direction' => 'row',
                'flex_gap' => ['size' => 10, 'unit' => 'px'],
                'flex_wrap' => 'wrap',
                'justify_content' => 'center',
            ],
            'elements' => [],
        ];
        foreach ($content['ctas'] as $cta) {
            $is_primary = $cta['variant'] === 'primary';
            $cta_container['elements'][] = [
                'id' => uniqid(),
                'elType' => 'widget',
                'widgetType' => 'button',
                'settings' => [
                    'text' => esc_html($cta['text']),
                    'link' => ['url' => esc_url($cta['link']['href']), 'is_external' => false],
                    'button_background_color' => $is_primary
                        ? ($css_vars['--lr-hero-button-background-color'] ?? '#3b82f6')
                        : ($css_vars['--lr-hero-button-secondary-background-color'] ?? '#e2e8f0'),
                    'button_text_color' => $is_primary
                        ? ($css_vars['--lr-hero-button-color'] ?? '#ffffff')
                        : ($css_vars['--lr-hero-button-secondary-color'] ?? '#475569'),
                    'typography_typography' => 'custom',
                    'typography_font-family' => $css_vars['--lr-hero-button-font-family'] ?? 'Inter, sans-serif',
                    'typography_font_size' => ['size' => 16, 'unit' => 'px'],
                    'typography_font_weight' => $css_vars['--lr-hero-button-weight'] ?? '600',
                    'border_radius' => ['size' => 10, 'unit' => 'px'],
                    'padding' => ['top' => 15, 'bottom' => 15, 'left' => 25, 'right' => 25, 'unit' => 'px'],
                    'icon' => $cta['icon'] === 'arrow' ? 'fas fa-arrow-right' : '',
                ],
            ];
        }
        $widgets[] = $cta_container;
    }

    // Smallprint
    if (!empty($content['smallprint'])) {
        $widgets[] = [
            'id' => uniqid(),
            'elType' => 'widget',
            'widgetType' => 'text-editor',
            'settings' => [
                'editor' => '<p>' . esc_html($content['smallprint']) . '</p>',
                'text_color' => $css_vars['--lr-hero-smallprint-color'] ?? '#64748b',
                'typography_typography' => 'custom',
                'typography_font_family' => $css_vars['--lr-hero-smallprint-font-family'] ?? 'Inter, sans-serif',
                'typography_font_size' => ['size' => 12, 'unit' => 'px'],
                'typography_font_weight' => $css_vars['--lr-hero-smallprint-font-weight'] ?? '600',
                'typography_text-transform' => $css_vars['--lr-hero-smallprint-text-transform'] ?? 'uppercase',
                'typography_letter_spacing' => ['size' => 0.05, 'unit' => 'em'],
                '_margin' => ['top' => 20, 'bottom' => 0, 'left' => 0, 'right' => 0, 'unit' => 'px'],
            ],
        ];
    }

    return $widgets;
}

function attach_image_to_media_library($url) {
    if (empty($url)) {
        return 0;
    }

    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $attachment_id = media_sideload_image($url, 0, null, 'id');
    return is_wp_error($attachment_id) ? 0 : $attachment_id;
}

