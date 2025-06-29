<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}


function import_json_to_elementor($json_data) {
    // Validate JSON
    if (!isset($json_data['data']['content']) || !isset($json_data['data']['styles'])) {
        return new WP_Error('invalid_json', 'Invalid JSON structure');
    }

    $content = $json_data['data']['content'];
    $styles = $json_data['data']['styles'];

    // Enqueue custom fonts
    if (!empty($styles['fontFaceRules'])) {
        add_action('wp_enqueue_scripts', function () use ($styles) {
            $font_css = implode("\n", $styles['fontFaceRules']);
            wp_add_inline_style('elementor-frontend', $font_css);
        });
    }

    // Initialize Elementor data
    $elementor_data = [];
    foreach ($content as $section) {
        $elementor_data[] = map_section_to_elementor($section, $styles);
    }

    $version = defined('ELEMENTOR_VERSION') ? ELEMENTOR_VERSION : 'unknown';

    // Create new page
    $page_id = wp_insert_post([
        'post_title' => $json_data['data']['name'] ?? 'Imported Page',
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

function map_section_to_elementor($section, $styles) {
    $type = $section['type'] ?? '';
    switch ($type) {
        case 'hero':
            return map_hero_to_elementor($section, $styles);
        case 'steps':
            return map_steps_to_elementor($section, $styles);
        case 'collection':
            return map_collection_to_elementor($section, $styles);
        case 'testimonial':
            return map_testimonial_to_elementor($section, $styles);
        case 'faq':
            return map_faq_to_elementor($section, $styles);
        case 'cta':
            return map_cta_to_elementor($section, $styles);
        case 'trustedBy':
            return map_trustedby_to_elementor($section, $styles);
        default:
            return map_unknown_to_elementor($section, $styles);
    }
}

// --- Reusable widget/component builders ---
function build_eyebrow_widget($eyebrow, $css_vars, $prefix = '') {
    if (empty($eyebrow['text'])) {
        return null;
    }
    $color = $css_vars["{$prefix}eyebrow-color"] ?? '#7c3aed';
    $bg = $css_vars["{$prefix}eyebrow-pill-background-color"] ?? '#7c3aed';
    $opacity = $css_vars["{$prefix}eyebrow-pill-opacity"] ?? '0.1';
    $font_family = $css_vars["{$prefix}eyebrow-font-family"] ?? 'Inter, sans-serif';
    $font_size = $css_vars["{$prefix}eyebrow-font-size"] ?? 13;
    $font_weight = $css_vars["{$prefix}eyebrow-font-weight"] ?? '600';
    $text_transform = $css_vars["{$prefix}eyebrow-text-transform"] ?? 'uppercase';
    $letter_spacing = $css_vars["{$prefix}eyebrow-letter-spacing"] ?? '0.05em';
    return [
        'id' => uniqid(),
        'elType' => 'widget',
        'widgetType' => 'text-editor',
        'settings' => [
            'editor' => '<p>' . esc_html($eyebrow['text']) . '</p>',
            'text_color' => $color,
            'typography_typography' => 'custom',
            'typography_font_family' => $font_family,
            'typography_font_size' => ['size' => $font_size, 'unit' => 'px'],
            'typography_font_weight' => $font_weight,
            'typography_text_transform' => $text_transform,
            'typography_letter_spacing' => ['size' => $letter_spacing, 'unit' => 'em'],
            '_background_background' => 'classic',
            '_background_color' => sprintf('rgba(%s, %s)', $bg, $opacity),
            '_border_radius' => ['size' => 100, 'unit' => 'px'],
            '_padding' => ['top' => 5, 'bottom' => 5, 'left' => 15, 'right' => 15, 'unit' => 'px'],
            '_margin' => ['top' => 0, 'bottom' => 20, 'left' => 0, 'right' => 0, 'unit' => 'px'],
        ],
    ];
}

function build_title_widget($title, $css_vars, $prefix = '', $header_size = 'h2') {
    if (empty($title)) {
        return null;
    }
    $color = $css_vars["{$prefix}title-color"] ?? '#0f172a';
    $font_family = $css_vars["{$prefix}title-font-family"] ?? 'Inter, sans-serif';
    $font_size = $css_vars["{$prefix}title-font-size"] ?? 36;
    $font_weight = $css_vars["{$prefix}title-font-weight"] ?? '900';
    $letter_spacing = $css_vars["{$prefix}title-letter-spacing"] ?? '-0.02em';
    $line_height = $css_vars["{$prefix}title-line-height"] ?? '1.1';
    return [
        'id' => uniqid(),
        'elType' => 'widget',
        'widgetType' => 'heading',
        'settings' => [
            'title' => wp_kses_post($title),
            'header_size' => $header_size,
            'title_color' => $color,
            'typography_typography' => 'custom',
            'typography_font_family' => $font_family,
            'typography_font_size' => ['size' => $font_size, 'unit' => 'px'],
            'typography_font_weight' => $font_weight,
            'typography_letter_spacing' => ['size' => $letter_spacing, 'unit' => 'em'],
            'typography_line_height' => ['size' => $line_height, 'unit' => 'em'],
        ],
    ];
}

function build_subtitle_widget($subtitle, $css_vars, $prefix = '') {
    if (empty($subtitle)) {
        return null;
    }
    $color = $css_vars["{$prefix}subtitle-color"] ?? '#475569';
    $font_family = $css_vars["{$prefix}subtitle-font-family"] ?? 'Inter, sans-serif';
    $font_size = $css_vars["{$prefix}subtitle-font-size"] ?? 18;
    $font_weight = $css_vars["{$prefix}subtitle-font-weight"] ?? '400';
    $line_height = $css_vars["{$prefix}subtitle-line-height"] ?? '1.6';
    return [
        'id' => uniqid(),
        'elType' => 'widget',
        'widgetType' => 'text-editor',
        'settings' => [
            'editor' => wp_kses_post($subtitle),
            'text_color' => $color,
            'typography_typography' => 'custom',
            'typography_font_family' => $font_family,
            'typography_font_size' => ['size' => $font_size, 'unit' => 'px'],
            'typography_font_weight' => $font_weight,
            'typography_line_height' => ['size' => $line_height, 'unit' => 'em'],
            '_margin' => ['top' => 20, 'bottom' => 20, 'left' => 0, 'right' => 0, 'unit' => 'px'],
        ],
    ];
}

function build_cta_widgets($ctas, $css_vars, $prefix = '') {
    if (empty($ctas) || !is_array($ctas)) {
        return [];
    }
    $widgets = [];
    foreach ($ctas as $cta) {
        $is_primary = ($cta['variant'] ?? '') === 'primary';
        $is_secondary = ($cta['variant'] ?? '') === 'secondary';
        $is_link = ($cta['variant'] ?? '') === 'link';
        $widgets[] = [
            'id' => uniqid(),
            'elType' => 'widget',
            'widgetType' => 'button',
            'settings' => [
                'text' => esc_html($cta['text'] ?? ''),
                'link' => [
                    'url' => esc_url($cta['link']['href'] ?? ''),
                    'is_external' => false
                ],
                'button_background_color' => $is_primary
                    ? ($css_vars["{$prefix}button-background-color"] ?? '#3b82f6')
                    : ($is_secondary
                        ? ($css_vars["{$prefix}button-secondary-background-color"] ?? '#e2e8f0')
                        : 'transparent'),
                'button_text_color' => $is_primary
                    ? ($css_vars["{$prefix}button-color"] ?? '#ffffff')
                    : ($is_secondary
                        ? ($css_vars["{$prefix}button-secondary-color"] ?? '#475569')
                        : '#475569'),
                'typography_typography' => 'custom',
                'typography_font-family' => $css_vars["{$prefix}button-font-family"] ?? 'Inter, sans-serif',
                'typography_font_size' => ['size' => 16, 'unit' => 'px'],
                'typography_font_weight' => $css_vars["{$prefix}button-weight"] ?? '600',
                'border_radius' => ['size' => 10, 'unit' => 'px'],
                'padding' => ['top' => 15, 'bottom' => 15, 'left' => 25, 'right' => 25, 'unit' => 'px'],
                'icon' => ($cta['icon'] ?? '') === 'arrow' ? 'fas fa-arrow-right' : '',
            ],
        ];
    }
    return $widgets;
}

function build_smallprint_widget($smallprint, $css_vars, $prefix = '') {
    if (empty($smallprint)) {
        return null;
    }
    $color = $css_vars["{$prefix}smallprint-color"] ?? '#64748b';
    $font_family = $css_vars["{$prefix}smallprint-font-family"] ?? 'Inter, sans-serif';
    $font_size = $css_vars["{$prefix}smallprint-font-size"] ?? 12;
    $font_weight = $css_vars["{$prefix}smallprint-font-weight"] ?? '600';
    $text_transform = $css_vars["{$prefix}smallprint-text-transform"] ?? 'uppercase';
    $letter_spacing = $css_vars["{$prefix}smallprint-letter-spacing"] ?? '0.05em';
    return [
        'id' => uniqid(),
        'elType' => 'widget',
        'widgetType' => 'text-editor',
        'settings' => [
            'editor' => '<p>' . esc_html($smallprint) . '</p>',
            'text_color' => $color,
            'typography_typography' => 'custom',
            'typography_font_family' => $font_family,
            'typography_font_size' => ['size' => $font_size, 'unit' => 'px'],
            'typography_font_weight' => $font_weight,
            'typography_text_transform' => $text_transform,
            'typography_letter_spacing' => ['size' => $letter_spacing, 'unit' => 'em'],
            '_margin' => ['top' => 20, 'bottom' => 0, 'left' => 0, 'right' => 0, 'unit' => 'px'],
        ],
    ];
}
function map_steps_to_elementor($section, $styles) {
    $content = $section['content'] ?? [];
    $css_vars = $styles['css'];
    $widgets = [];
    $eyebrow = build_eyebrow_widget($content['eyebrow'] ?? [], $css_vars, '--lr-steps-');
    if ($eyebrow) {
        $widgets[] = $eyebrow;
    }
    $title = build_title_widget($content['title'] ?? '', $css_vars, '--lr-steps-', 'h2');
    if ($title) {
        $widgets[] = $title;
    }
    if (!empty($content['ctas'])) {
        $cta_widgets = build_cta_widgets($content['ctas'], $css_vars, '--lr-steps-');
        if ($cta_widgets) {
            $widgets[] = [
                'id' => uniqid(),
                'elType' => 'container',
                'settings' => [
                    'flex_direction' => 'row',
                    'flex_gap' => ['size' => 10, 'unit' => 'px'],
                    'flex_wrap' => 'wrap',
                    'justify_content' => 'center',
                ],
                'elements' => $cta_widgets,
            ];
        }
    }
    // Items (steps)
    if (!empty($content['items']) && is_array($content['items'])) {
        foreach ($content['items'] as $item) {
            $widgets[] = [
                'id' => uniqid(),
                'elType' => 'widget',
                'widgetType' => 'text-editor',
                'settings' => [
                    'editor' => wp_kses_post($item['description'] ?? ''),
                    'text_color' => $css_vars['--lr-steps-item-color'] ?? '#991b1b',
                    'typography_typography' => 'custom',
                    'typography_font_family' => $css_vars['--lr-steps-item-font-family'] ?? 'Inter, sans-serif',
                    'typography_font_size' => ['size' => 16, 'unit' => 'px'],
                    'typography_font_weight' => $css_vars['--lr-steps-item-font-weight'] ?? '600',
                    'typography_line_height' => ['size' => 1.5, 'unit' => 'em'],
                ],
            ];
            // Item CTAs
            if (!empty($item['ctas'])) {
                $item_cta_widgets = build_cta_widgets($item['ctas'], $css_vars, '--lr-steps-');
                if ($item_cta_widgets) {
                    $widgets[] = [
                        'id' => uniqid(),
                        'elType' => 'container',
                        'settings' => [
                            'flex_direction' => 'row',
                            'flex_gap' => ['size' => 10, 'unit' => 'px'],
                            'flex_wrap' => 'wrap',
                            'justify_content' => 'center',
                        ],
                        'elements' => $item_cta_widgets,
                    ];
                }
            }
        }
    }
    return [
        'id' => uniqid(),
        'elType' => 'section',
        'settings' => [],
        'elements' => $widgets,
    ];
}
function map_collection_to_elementor($section, $styles) {
    $content = $section['content'] ?? [];
    $css_vars = $styles['css'];
    $widgets = [];
    $prefix = ($section['layout'] ?? '') === 'alternate' ? '--lr-alternate-' : '--lr-boxes-';
    $eyebrow = build_eyebrow_widget($content['eyebrow'] ?? [], $css_vars, $prefix);
    if ($eyebrow) {
        $widgets[] = $eyebrow;
    }
    $title = build_title_widget($content['title'] ?? '', $css_vars, $prefix, 'h2');
    if ($title) {
        $widgets[] = $title;
    }
    $subtitle = build_subtitle_widget($content['subtitle'] ?? '', $css_vars, $prefix);
    if ($subtitle) {
        $widgets[] = $subtitle;
    }
    if (!empty($content['ctas'])) {
        $cta_widgets = build_cta_widgets($content['ctas'], $css_vars, $prefix);
        if ($cta_widgets) {
            $widgets[] = [
                'id' => uniqid(),
                'elType' => 'container',
                'settings' => [
                    'flex_direction' => 'row',
                    'flex_gap' => ['size' => 10, 'unit' => 'px'],
                    'flex_wrap' => 'wrap',
                    'justify_content' => 'center',
                ],
                'elements' => $cta_widgets,
            ];
        }
    }
    // Items (features/benefits)
    if (!empty($content['items']) && is_array($content['items'])) {
        foreach ($content['items'] as $item) {
            $item_eyebrow = build_eyebrow_widget($item['eyebrow'] ?? [], $css_vars, $prefix . 'item-');
            if ($item_eyebrow) {
                $widgets[] = $item_eyebrow;
            }
            $item_title = build_title_widget($item['title'] ?? '', $css_vars, $prefix . 'item-', 'h3');
            if ($item_title) {
                $widgets[] = $item_title;
            }
            $widgets[] = [
                'id' => uniqid(),
                'elType' => 'widget',
                'widgetType' => 'text-editor',
                'settings' => [
                    'editor' => wp_kses_post($item['description'] ?? ''),
                    'text_color' => $css_vars[$prefix . 'item-text-color'] ?? '#4c1d95',
                    'typography_typography' => 'custom',
                    'typography_font_family' => $css_vars[$prefix . 'item-text-font-family'] ?? 'Inter, sans-serif',
                    'typography_font_size' => ['size' => 16, 'unit' => 'px'],
                    'typography_font_weight' => $css_vars[$prefix . 'item-text-font-weight'] ?? '400',
                    'typography_line_height' => ['size' => 1.5, 'unit' => 'em'],
                ],
            ];
            if (!empty($item['ctas'])) {
                $item_cta_widgets = build_cta_widgets($item['ctas'], $css_vars, $prefix);
                if ($item_cta_widgets) {
                    $widgets[] = [
                        'id' => uniqid(),
                        'elType' => 'container',
                        'settings' => [
                            'flex_direction' => 'row',
                            'flex_gap' => ['size' => 10, 'unit' => 'px'],
                            'flex_wrap' => 'wrap',
                            'justify_content' => 'center',
                        ],
                        'elements' => $item_cta_widgets,
                    ];
                }
            }
        }
    }
    return [
        'id' => uniqid(),
        'elType' => 'section',
        'settings' => [],
        'elements' => $widgets,
    ];
}
function map_testimonial_to_elementor($section, $styles) {
    $content = $section['content'] ?? [];
    $css_vars = $styles['css'];
    $widgets = [];
    if (!empty($content['items']) && is_array($content['items'])) {
        foreach ($content['items'] as $item) {
            $author = $item['author'] ?? [];
            $author_html = '';
            if (!empty($author['name'])) {
                $author_html .= '<strong>' . esc_html($author['name']) . '</strong>';
                if (!empty($author['position'])) {
                    $author_html .= ', ' . esc_html($author['position']);
                }
                if (!empty($author['organization'])) {
                    $author_html .= ' @ ' . esc_html($author['organization']);
                }
            }
            $widgets[] = [
                'id' => uniqid(),
                'elType' => 'widget',
                'widgetType' => 'text-editor',
                'settings' => [
                    'editor' => wp_kses_post(($item['quote'] ?? '') . '<br>' . $author_html),
                    'text_color' => $css_vars['--lr-testimonial-color'] ?? '#92400e',
                    'typography_typography' => 'custom',
                    'typography_font_family' => $css_vars['--lr-testimonial-primary-font-family'] ?? 'Inter, sans-serif',
                    'typography_font_size' => ['size' => 21, 'unit' => 'px'],
                    'typography_font_weight' => $css_vars['--lr-testimonial-primary-font-weight'] ?? '400',
                    'typography_line_height' => ['size' => 1.5, 'unit' => 'em'],
                ],
            ];
        }
    }
    return [
        'id' => uniqid(),
        'elType' => 'section',
        'settings' => [],
        'elements' => $widgets,
    ];
}
function map_faq_to_elementor($section, $styles) {
    $content = $section['content'] ?? [];
    $css_vars = $styles['css'];
    $widgets = [];
    $eyebrow = build_eyebrow_widget($content['eyebrow'] ?? [], $css_vars, '--lr-faq-section-');
    if ($eyebrow) {
        $widgets[] = $eyebrow;
    }
    $title = build_title_widget($content['title'] ?? '', $css_vars, '--lr-faq-', 'h2');
    if ($title) {
        $widgets[] = $title;
    }
    $subtitle = build_subtitle_widget($content['subtitle'] ?? '', $css_vars, '--lr-faq-');
    if ($subtitle) {
        $widgets[] = $subtitle;
    }
    if (!empty($content['ctas'])) {
        $cta_widgets = build_cta_widgets($content['ctas'], $css_vars, '--lr-faq-');
        if ($cta_widgets) {
            $widgets[] = [
                'id' => uniqid(),
                'elType' => 'container',
                'settings' => [
                    'flex_direction' => 'row',
                    'flex_gap' => ['size' => 10, 'unit' => 'px'],
                    'flex_wrap' => 'wrap',
                    'justify_content' => 'center',
                ],
                'elements' => $cta_widgets,
            ];
        }
    }
    if (!empty($content['items']) && is_array($content['items'])) {
        foreach ($content['items'] as $item) {
            $widgets[] = [
                'id' => uniqid(),
                'elType' => 'widget',
                'widgetType' => 'text-editor',
                'settings' => [
                    'editor' => '<strong>' . wp_kses_post($item['title'] ?? '') . '</strong><br>' . wp_kses_post($item['description'] ?? ''),
                    'text_color' => $css_vars['--lr-faq-item-text-color'] ?? '#047857',
                    'typography_typography' => 'custom',
                    'typography_font_family' => $css_vars['--lr-faq-item-text-font-family'] ?? 'Inter, sans-serif',
                    'typography_font_size' => ['size' => 16, 'unit' => 'px'],
                    'typography_font_weight' => $css_vars['--lr-faq-item-text-font-weight'] ?? '400',
                    'typography_line_height' => ['size' => 1.5, 'unit' => 'em'],
                ],
            ];
        }
    }
    return [
        'id' => uniqid(),
        'elType' => 'section',
        'settings' => [],
        'elements' => $widgets,
    ];
}
function map_cta_to_elementor($section, $styles) {
    $content = $section['content'] ?? [];
    $css_vars = $styles['css'];
    $widgets = [];
    $eyebrow = build_eyebrow_widget($content['eyebrow'] ?? [], $css_vars, '--lr-cta-');
    if ($eyebrow) {
        $widgets[] = $eyebrow;
    }
    $title = build_title_widget($content['title'] ?? '', $css_vars, '--lr-cta-', 'h2');
    if ($title) {
        $widgets[] = $title;
    }
    $subtitle = build_subtitle_widget($content['subtitle'] ?? '', $css_vars, '--lr-cta-');
    if ($subtitle) {
        $widgets[] = $subtitle;
    }
    if (!empty($content['ctas'])) {
        $cta_widgets = build_cta_widgets($content['ctas'], $css_vars, '--lr-cta-');
        if ($cta_widgets) {
            $widgets[] = [
                'id' => uniqid(),
                'elType' => 'container',
                'settings' => [
                    'flex_direction' => 'row',
                    'flex_gap' => ['size' => 10, 'unit' => 'px'],
                    'flex_wrap' => 'wrap',
                    'justify_content' => 'center',
                ],
                'elements' => $cta_widgets,
            ];
        }
    }
    $smallprint = build_smallprint_widget($content['smallprint'] ?? '', $css_vars, '--lr-cta-');
    if ($smallprint) {
        $widgets[] = $smallprint;
    }
    return [
        'id' => uniqid(),
        'elType' => 'section',
        'settings' => [],
        'elements' => $widgets,
    ];
}
function map_trustedby_to_elementor($section, $styles) {
    $content = $section['content'] ?? [];
    $css_vars = $styles['css'];
    $widgets = [];
    // Trusted by sections often just show logos or a simple list
    if (!empty($content['items']) && is_array($content['items'])) {
        foreach ($content['items'] as $item) {
            // If item is a logo/image, render as image widget, else as text
            if (!empty($item['image'])) {
                $widgets[] = [
                    'id' => uniqid(),
                    'elType' => 'widget',
                    'widgetType' => 'image',
                    'settings' => [
                        'image' => [
                            'url' => esc_url($item['image']),
                            'id' => attach_image_to_media_library($item['image']),
                        ],
                        'image_border_radius' => ['size' => 10, 'unit' => 'px'],
                    ],
                ];
            } elseif (!empty($item['name'])) {
                $widgets[] = [
                    'id' => uniqid(),
                    'elType' => 'widget',
                    'widgetType' => 'text-editor',
                    'settings' => [
                        'editor' => esc_html($item['name']),
                    ],
                ];
            }
        }
    } else {
        // fallback
        $widgets[] = [
            'id' => uniqid(),
            'elType' => 'widget',
            'widgetType' => 'text-editor',
            'settings' => [
                'editor' => '<p>Trusted by section</p>',
            ],
        ];
    }
    return [
        'id' => uniqid(),
        'elType' => 'section',
        'settings' => [],
        'elements' => $widgets,
    ];
}
function map_unknown_to_elementor($section, $styles) {
    // Fallback for unknown section types
    return [
        'id' => uniqid(),
        'elType' => 'section',
        'settings' => [],
        'elements' => [
            [
                'id' => uniqid(),
                'elType' => 'widget',
                'widgetType' => 'text-editor',
                'settings' => [
                    'editor' => '<p>Unknown section type: ' . esc_html($section['type'] ?? 'unknown') . '</p>'
                ]
            ]
        ],
    ];
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
    $eyebrow = build_eyebrow_widget($content['eyebrow'] ?? [], $css_vars, '--lr-hero-');
    if ($eyebrow) {
        $widgets[] = $eyebrow;
    }
    $title = build_title_widget($content['title'] ?? '', $css_vars, '--lr-hero-', 'h1');
    if ($title) {
        $widgets[] = $title;
    }
    $subtitle = build_subtitle_widget($content['subtitle'] ?? '', $css_vars, '--lr-hero-');
    if ($subtitle) {
        $widgets[] = $subtitle;
    }
    if (!empty($content['ctas'])) {
        $cta_widgets = build_cta_widgets($content['ctas'], $css_vars, '--lr-hero-');
        if ($cta_widgets) {
            $widgets[] = [
                'id' => uniqid(),
                'elType' => 'container',
                'settings' => [
                    'flex_direction' => 'row',
                    'flex_gap' => ['size' => 10, 'unit' => 'px'],
                    'flex_wrap' => 'wrap',
                    'justify_content' => 'center',
                ],
                'elements' => $cta_widgets,
            ];
        }
    }
    $smallprint = build_smallprint_widget($content['smallprint'] ?? '', $css_vars, '--lr-hero-');
    if ($smallprint) {
        $widgets[] = $smallprint;
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

