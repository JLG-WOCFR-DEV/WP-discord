<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Implémente le shortcode `[discord_stats]` et gère les assets associés côté public.
 */
class Discord_Bot_JLG_Shortcode {

    private $option_name;
    private $api;

    private static $assets_registered   = false;
    private static $inline_css_added    = false;
    private static $footer_hook_added   = false;

    /**
     * Conserve la clé d'option et le service API utilisés lors du rendu du shortcode.
     *
     * @param string              $option_name Nom de l'option stockant les réglages d'affichage.
     * @param Discord_Bot_JLG_API $api         Service fournissant les statistiques Discord.
     *
     * @return void
     */
    public function __construct($option_name, Discord_Bot_JLG_API $api) {
        $this->option_name = $option_name;
        $this->api         = $api;
    }

    /**
     * Génère l'affichage du shortcode avec les options fusionnées entre réglages et attributs.
     *
     * @param array|string $atts Attributs reçus du shortcode WordPress.
     *
     * @return string HTML du composant de statistiques prêt à être inséré dans la page.
     */
    public function render_shortcode($atts) {
        $options = $this->api->get_plugin_options();

        $normalized_atts = is_array($atts) ? $atts : array();
        $received_atts   = $normalized_atts;

        $sensitive_attribute_keys = array(
            'bot_token',
            'bot_token_override',
            '__bot_token_override',
            'botToken',
            'botTokenOverride',
        );

        foreach ($sensitive_attribute_keys as $sensitive_key) {
            if (array_key_exists($sensitive_key, $normalized_atts)) {
                unset($normalized_atts[$sensitive_key]);
                unset($received_atts[$sensitive_key]);
            }
        }

        $default_theme = 'discord';
        if (
            isset($options['default_theme'])
            && discord_bot_jlg_is_allowed_theme($options['default_theme'])
        ) {
            $default_theme = $options['default_theme'];
        }

        $default_colors = array(
            'stat_bg_color'      => isset($options['stat_bg_color']) ? discord_bot_jlg_sanitize_color($options['stat_bg_color']) : '',
            'stat_text_color'    => isset($options['stat_text_color']) ? discord_bot_jlg_sanitize_color($options['stat_text_color']) : '',
            'accent_color'       => isset($options['accent_color']) ? discord_bot_jlg_sanitize_color($options['accent_color']) : '',
            'accent_color_alt'   => isset($options['accent_color_alt']) ? discord_bot_jlg_sanitize_color($options['accent_color_alt']) : '',
            'accent_text_color'  => isset($options['accent_text_color']) ? discord_bot_jlg_sanitize_color($options['accent_text_color']) : '',
        );

        $default_text_sources = array(
            'icon_online'          => array('option' => 'default_icon_online', 'fallback' => '🟢'),
            'icon_total'           => array('option' => 'default_icon_total', 'fallback' => '👥'),
            'icon_presence'        => array('option' => 'default_icon_presence', 'fallback' => '📊'),
            'icon_approximate'     => array('option' => 'default_icon_approximate', 'fallback' => '📈'),
            'icon_premium'         => array('option' => 'default_icon_premium', 'fallback' => '💎'),
            'label_online'         => array('option' => 'default_label_online', 'fallback' => __('En ligne', 'discord-bot-jlg')),
            'label_total'          => array('option' => 'default_label_total', 'fallback' => __('Membres', 'discord-bot-jlg')),
            'label_presence'       => array('option' => 'default_label_presence', 'fallback' => __('Présence par statut', 'discord-bot-jlg')),
            'label_presence_online'=> array('option' => 'default_label_presence_online', 'fallback' => __('En ligne', 'discord-bot-jlg')),
            'label_presence_idle'  => array('option' => 'default_label_presence_idle', 'fallback' => __('Inactif', 'discord-bot-jlg')),
            'label_presence_dnd'   => array('option' => 'default_label_presence_dnd', 'fallback' => __('Ne pas déranger', 'discord-bot-jlg')),
            'label_presence_offline'=> array('option' => 'default_label_presence_offline', 'fallback' => __('Hors ligne', 'discord-bot-jlg')),
            'label_presence_streaming'=> array('option' => 'default_label_presence_streaming', 'fallback' => __('En direct', 'discord-bot-jlg')),
            'label_presence_other' => array('option' => 'default_label_presence_other', 'fallback' => __('Autres', 'discord-bot-jlg')),
            'label_approximate'    => array('option' => 'default_label_approximate', 'fallback' => __('Membres (approx.)', 'discord-bot-jlg')),
            'label_premium'        => array('option' => 'default_label_premium', 'fallback' => __('Boosts serveur', 'discord-bot-jlg')),
            'label_premium_singular' => array('option' => 'default_label_premium_singular', 'fallback' => __('Boost serveur', 'discord-bot-jlg')),
            'label_premium_plural' => array('option' => 'default_label_premium_plural', 'fallback' => __('Boosts serveur', 'discord-bot-jlg')),
        );

        $default_texts = array();

        foreach ($default_text_sources as $attribute_key => $config) {
            $option_key = $config['option'];
            $raw_value  = isset($options[$option_key]) ? sanitize_text_field($options[$option_key]) : '';

            if ('' === $raw_value) {
                $raw_value = $config['fallback'];
            }

            $default_texts[$attribute_key] = $raw_value;
        }

        $default_invite_url = isset($options['invite_url']) ? esc_url_raw($options['invite_url']) : '';
        $default_invite_label = isset($options['invite_label'])
            ? sanitize_text_field($options['invite_label'])
            : '';

        if ('' === $default_invite_label) {
            $default_invite_label = __('Rejoindre le serveur', 'discord-bot-jlg');
        }

        $default_cta_label = __('Rejoindre la communauté', 'discord-bot-jlg');
        $default_cta_tooltip = __('Découvrir le serveur Discord', 'discord-bot-jlg');

        $min_refresh_option = defined('Discord_Bot_JLG_API::MIN_PUBLIC_REFRESH_INTERVAL')
            ? Discord_Bot_JLG_API::MIN_PUBLIC_REFRESH_INTERVAL
            : 10;
        $max_refresh_option = 3600;

        $default_refresh_interval = isset($options['default_refresh_interval'])
            ? absint($options['default_refresh_interval'])
            : 60;

        if ($default_refresh_interval <= 0) {
            $default_refresh_interval = 60;
        }

        $default_refresh_interval = max(
            $min_refresh_option,
            min($max_refresh_option, $default_refresh_interval)
        );

        $atts = shortcode_atts(
            array(
                'layout'               => 'horizontal',
                'show_online'          => !empty($options['show_online']),
                'show_total'           => !empty($options['show_total']),
                'show_presence_breakdown' => !empty($options['show_presence_breakdown']),
                'show_approximate_member_count' => !empty($options['show_approximate_member_count']),
                'show_premium_subscriptions' => !empty($options['show_premium_subscriptions']),
                'show_title'           => false,
                'title'                => isset($options['widget_title']) ? $options['widget_title'] : '',
                'theme'                => $default_theme,
                'animated'             => true,
                'refresh'              => !empty($options['default_refresh_enabled']),
                'refresh_interval'     => (string) $default_refresh_interval,
                'compact'              => false,
                'align'                => 'left',
                'width'                => '',
                'class'                => '',
                'className'            => '',
                'icon_online'          => $default_texts['icon_online'],
                'icon_total'           => $default_texts['icon_total'],
                'icon_presence'        => $default_texts['icon_presence'],
                'icon_approximate'     => $default_texts['icon_approximate'],
                'icon_premium'         => $default_texts['icon_premium'],
                'label_online'         => $default_texts['label_online'],
                'label_total'          => $default_texts['label_total'],
                'label_presence'       => $default_texts['label_presence'],
                'label_presence_online'=> $default_texts['label_presence_online'],
                'label_presence_idle'  => $default_texts['label_presence_idle'],
                'label_presence_dnd'   => $default_texts['label_presence_dnd'],
                'label_presence_offline'=> $default_texts['label_presence_offline'],
                'label_presence_streaming'=> $default_texts['label_presence_streaming'],
                'label_presence_other' => $default_texts['label_presence_other'],
                'label_approximate'    => $default_texts['label_approximate'],
                'label_premium'        => $default_texts['label_premium'],
                'label_premium_singular' => $default_texts['label_premium_singular'],
                'label_premium_plural' => $default_texts['label_premium_plural'],
                'hide_labels'          => false,
                'hide_icons'           => false,
                'border_radius'        => '8',
                'gap'                  => '20',
                'padding'              => '15',
                'stat_bg_color'        => $default_colors['stat_bg_color'],
                'stat_text_color'      => $default_colors['stat_text_color'],
                'accent_color'         => $default_colors['accent_color'],
                'accent_color_alt'     => $default_colors['accent_color_alt'],
                'accent_text_color'    => $default_colors['accent_text_color'],
                'demo'                 => false,
                'show_discord_icon'    => false,
                'discord_icon_position'=> 'left',
                'show_server_name'     => !empty($options['show_server_name']),
                'show_server_avatar'   => !empty($options['show_server_avatar']),
                'avatar_size'          => '128',
                'invite_url'           => $default_invite_url,
                'invite_label'         => $default_invite_label,
                'cta_enabled'          => false,
                'cta_label'            => $default_cta_label,
                'cta_url'              => '',
                'cta_style'            => 'solid',
                'cta_new_tab'          => true,
                'cta_tooltip'          => '',
                'profile'              => '',
                'server_id'            => '',
                'show_sparkline'       => false,
                'sparkline_metric'     => 'online',
                'sparkline_days'       => '7',
            ),
            $normalized_atts,
            'discord_stats'
        );

        $show_online        = filter_var($atts['show_online'], FILTER_VALIDATE_BOOLEAN);
        $show_total         = filter_var($atts['show_total'], FILTER_VALIDATE_BOOLEAN);
        $show_presence_breakdown = filter_var($atts['show_presence_breakdown'], FILTER_VALIDATE_BOOLEAN);
        $show_approximate_members = filter_var($atts['show_approximate_member_count'], FILTER_VALIDATE_BOOLEAN);
        $show_premium_subscriptions = filter_var($atts['show_premium_subscriptions'], FILTER_VALIDATE_BOOLEAN);
        $show_title         = filter_var($atts['show_title'], FILTER_VALIDATE_BOOLEAN);
        $animated           = filter_var($atts['animated'], FILTER_VALIDATE_BOOLEAN);
        $refresh            = filter_var($atts['refresh'], FILTER_VALIDATE_BOOLEAN);
        $compact            = filter_var($atts['compact'], FILTER_VALIDATE_BOOLEAN);
        $hide_labels        = filter_var($atts['hide_labels'], FILTER_VALIDATE_BOOLEAN);
        $hide_icons         = filter_var($atts['hide_icons'], FILTER_VALIDATE_BOOLEAN);
        $force_demo         = filter_var($atts['demo'], FILTER_VALIDATE_BOOLEAN);
        $show_discord_icon  = filter_var($atts['show_discord_icon'], FILTER_VALIDATE_BOOLEAN);
        $show_server_name   = filter_var($atts['show_server_name'], FILTER_VALIDATE_BOOLEAN);
        $show_server_avatar = filter_var($atts['show_server_avatar'], FILTER_VALIDATE_BOOLEAN);
        $avatar_size        = $this->sanitize_avatar_size($atts['avatar_size']);
        $invite_url         = esc_url_raw(is_string($atts['invite_url']) ? trim($atts['invite_url']) : '');
        $invite_label       = isset($atts['invite_label']) ? sanitize_text_field($atts['invite_label']) : '';
        $cta_enabled        = filter_var($atts['cta_enabled'], FILTER_VALIDATE_BOOLEAN);
        $cta_label          = isset($atts['cta_label']) ? sanitize_text_field($atts['cta_label']) : '';
        $cta_url            = esc_url_raw(is_string($atts['cta_url']) ? trim($atts['cta_url']) : '');
        $cta_style          = isset($atts['cta_style']) ? sanitize_key($atts['cta_style']) : 'solid';
        $cta_new_tab        = filter_var($atts['cta_new_tab'], FILTER_VALIDATE_BOOLEAN);
        $cta_tooltip_raw    = array_key_exists('cta_tooltip', $received_atts) ? $received_atts['cta_tooltip'] : null;
        $cta_tooltip        = isset($atts['cta_tooltip']) ? sanitize_text_field($atts['cta_tooltip']) : '';
        $show_sparkline     = filter_var($atts['show_sparkline'], FILTER_VALIDATE_BOOLEAN);
        $sparkline_metric   = isset($atts['sparkline_metric']) ? sanitize_key($atts['sparkline_metric']) : 'online';
        $allowed_sparkline_metrics = array('online', 'presence', 'premium');

        if (!in_array($sparkline_metric, $allowed_sparkline_metrics, true)) {
            $sparkline_metric = 'online';
        }

        $sparkline_days = isset($atts['sparkline_days']) ? (int) $atts['sparkline_days'] : 7;

        if ($sparkline_days < 3) {
            $sparkline_days = 3;
        } elseif ($sparkline_days > 30) {
            $sparkline_days = 30;
        }

        $allowed_cta_styles = array('solid', 'outline');
        if (!in_array($cta_style, $allowed_cta_styles, true)) {
            $cta_style = 'solid';
        }

        if ('' === $invite_label) {
            $invite_label = __('Rejoindre le serveur', 'discord-bot-jlg');
        }

        if ('' === $cta_label) {
            $cta_label = $default_cta_label;
        }

        if (null === $cta_tooltip_raw && '' === $cta_tooltip) {
            $cta_tooltip = $default_cta_tooltip;
        }

        if (!$cta_enabled || '' === $cta_url) {
            $cta_enabled = false;
        }

        $profile_key        = $this->sanitize_profile_key($atts['profile']);
        $override_server_id = $this->sanitize_server_id_attribute($atts['server_id']);

        $sparkline_metric_label_map = array(
            'online'   => isset($atts['label_online']) ? $atts['label_online'] : __('En ligne', 'discord-bot-jlg'),
            'presence' => isset($atts['label_presence']) ? $atts['label_presence'] : __('Présence', 'discord-bot-jlg'),
            'premium'  => isset($atts['label_premium']) ? $atts['label_premium'] : __('Boosts serveur', 'discord-bot-jlg'),
        );
        $sparkline_label = isset($sparkline_metric_label_map[$sparkline_metric])
            ? $sparkline_metric_label_map[$sparkline_metric]
            : __('Tendance', 'discord-bot-jlg');

        if ($force_demo) {
            $stats = $this->api->get_demo_stats();
        } else {
            $stats = $this->api->get_stats(
                array_filter(
                    array(
                        'profile_key' => $profile_key,
                        'server_id'   => $override_server_id,
                    ),
                    'strlen'
                )
            );
        }

        if (!is_array($stats)) {
            return sprintf(
                '<div class="discord-stats-error" role="alert" aria-live="assertive">%s</div>',
                esc_html__('Impossible de récupérer les stats Discord', 'discord-bot-jlg')
            );
        }

        if (function_exists('wp_unique_id')) {
            $unique_id = wp_unique_id('discord-stats-');
        } else {
            $unique_id = str_replace('.', '-', uniqid('discord-stats-', true));
        }

        $is_demo          = !empty($stats['is_demo']);
        $is_fallback_demo = !empty($stats['fallback_demo']);
        $is_forced_demo   = $is_demo && !$is_fallback_demo;

        $has_total            = !empty($stats['has_total']) && isset($stats['total']) && null !== $stats['total'];
        $total_is_approximate = !empty($stats['total_is_approximate']);
        $is_stale             = !empty($stats['stale']);
        $last_updated         = isset($stats['last_updated']) ? (int) $stats['last_updated'] : 0;
        $server_name          = isset($stats['server_name']) ? trim((string) $stats['server_name']) : '';
        $server_avatar_base   = isset($stats['server_avatar_base_url']) ? esc_url_raw($stats['server_avatar_base_url']) : '';
        $server_avatar_raw    = isset($stats['server_avatar_url']) ? esc_url_raw($stats['server_avatar_url']) : '';
        $server_avatar_url    = '';

        $presence_counts = array();
        if (!empty($stats['presence_count_by_status']) && is_array($stats['presence_count_by_status'])) {
            foreach ($stats['presence_count_by_status'] as $status_key => $count_value) {
                if (!is_scalar($count_value)) {
                    continue;
                }

                $status_slug = sanitize_key($status_key);

                if ('' === $status_slug) {
                    continue;
                }

                $presence_counts[$status_slug] = max(0, (int) $count_value);
            }
        }

        $approximate_presence = null;
        if (isset($stats['approximate_presence_count']) && null !== $stats['approximate_presence_count']) {
            $approximate_presence = (int) $stats['approximate_presence_count'];
        } elseif (!empty($presence_counts)) {
            $approximate_presence = array_sum($presence_counts);
        }

        if (null !== $approximate_presence && $approximate_presence < 0) {
            $approximate_presence = 0;
        }

        $approximate_member_count = null;
        if (isset($stats['approximate_member_count']) && null !== $stats['approximate_member_count']) {
            $approximate_member_count = (int) $stats['approximate_member_count'];
        }

        $premium_subscription_count = isset($stats['premium_subscription_count'])
            ? max(0, (int) $stats['premium_subscription_count'])
            : 0;

        if ($show_server_avatar) {
            $server_avatar_url = $this->prepare_avatar_url($server_avatar_base, $server_avatar_raw, $avatar_size);
        }

        $container_classes = array('discord-stats-container');

        $layout_class = strtolower(sanitize_html_class($atts['layout'], 'horizontal'));
        if (!empty($layout_class)) {
            $container_classes[] = 'discord-layout-' . $layout_class;
        }

        $theme_class = strtolower(sanitize_html_class($atts['theme'], 'discord'));
        if (!empty($theme_class)) {
            $container_classes[] = 'discord-theme-' . $theme_class;
        }

        $align_class = strtolower(sanitize_html_class($atts['align'], 'left'));
        if (!empty($align_class)) {
            $container_classes[] = 'discord-align-' . $align_class;
        }

        if ($compact) {
            $container_classes[] = 'discord-compact';
        }

        if ($animated) {
            $container_classes[] = 'discord-animated';
        }

        if ($is_demo) {
            $container_classes[] = 'discord-demo-mode';
        }

        if ('' !== $invite_url) {
            $container_classes[] = 'discord-has-invite';
        }

        if ($cta_enabled) {
            $container_classes[] = 'discord-has-cta';
            $container_classes[] = 'discord-cta-style-' . $cta_style;
        }

        if ($show_presence_breakdown && (null !== $approximate_presence || !empty($presence_counts))) {
            $container_classes[] = 'discord-has-presence-breakdown';
        }

        if ($show_approximate_members && null !== $approximate_member_count) {
            $container_classes[] = 'discord-has-approximate-total';
        }

        if ($show_premium_subscriptions) {
            $container_classes[] = 'discord-has-premium';
        }

        $cta_button_attributes = array();

        if ($cta_enabled) {
            $cta_button_attributes[] = sprintf(
                'class="%s"',
                esc_attr('discord-cta-button discord-cta-button--' . $cta_style)
            );
            $cta_button_attributes[] = sprintf('href="%s"', esc_url($cta_url));

            if ($cta_new_tab) {
                $cta_button_attributes[] = 'target="_blank"';
                $cta_button_attributes[] = 'rel="noopener noreferrer"';
            }

            if ('' !== $cta_tooltip) {
                $cta_button_attributes[] = sprintf('title="%s"', esc_attr($cta_tooltip));
                $cta_button_attributes[] = sprintf('aria-label="%s"', esc_attr($cta_tooltip));
            }
        }

        $logo_position_class = '';
        if ($show_discord_icon) {
            $container_classes[] = 'discord-with-logo';

            $logo_position_class = strtolower(sanitize_html_class($atts['discord_icon_position'], 'left'));
            if (!empty($logo_position_class)) {
                $container_classes[] = 'discord-logo-' . $logo_position_class;
            }
        }

        if (!$has_total) {
            $container_classes[] = 'discord-total-missing';
        }

        $custom_class_sources = array();

        $class_name_attribute = '';
        if (array_key_exists('className', $received_atts)) {
            $class_name_attribute = is_string($atts['className']) ? trim($atts['className']) : '';
        } elseif (!empty($atts['className'])) {
            $class_name_attribute = is_string($atts['className']) ? trim($atts['className']) : '';
        }

        if ('' !== $class_name_attribute) {
            $custom_class_sources[] = $class_name_attribute;
        }

        $legacy_class_attribute = '';
        if (array_key_exists('class', $received_atts)) {
            $legacy_class_attribute = is_string($atts['class']) ? trim($atts['class']) : '';
        } elseif (!empty($atts['class'])) {
            $legacy_class_attribute = is_string($atts['class']) ? trim($atts['class']) : '';
        }

        if ('' === $class_name_attribute && '' !== $legacy_class_attribute) {
            $custom_class_sources[] = $legacy_class_attribute;
        }

        if (!empty($custom_class_sources)) {
            $collected_custom_classes = array();

            foreach ($custom_class_sources as $custom_class_value) {
                $custom_classes = preg_split('/\s+/', $custom_class_value, -1, PREG_SPLIT_NO_EMPTY);

                if (empty($custom_classes)) {
                    continue;
                }

                $custom_classes = array_filter(array_map('sanitize_html_class', $custom_classes));

                if (empty($custom_classes)) {
                    continue;
                }

                $collected_custom_classes = array_merge($collected_custom_classes, $custom_classes);
            }

            if (!empty($collected_custom_classes)) {
                $container_classes = array_merge($container_classes, array_unique($collected_custom_classes));
            }
        }

        if ($show_server_avatar) {
            $container_classes[] = 'discord-avatar-enabled';

            if ('' !== $server_avatar_url) {
                $container_classes[] = 'discord-has-server-avatar';
            }
        }

        $style_declarations = array(
            '--discord-gap: ' . intval($atts['gap']) . 'px',
            '--discord-padding: ' . intval($atts['padding']) . 'px',
            '--discord-radius: ' . intval($atts['border_radius']) . 'px',
        );

        $stat_bg_color     = discord_bot_jlg_sanitize_color($atts['stat_bg_color']);
        $stat_text_color   = discord_bot_jlg_sanitize_color($atts['stat_text_color']);
        $accent_color      = discord_bot_jlg_sanitize_color($atts['accent_color']);
        $accent_color_alt  = discord_bot_jlg_sanitize_color($atts['accent_color_alt']);
        $accent_text_color = discord_bot_jlg_sanitize_color($atts['accent_text_color']);

        if ('' !== $accent_color && '' === $accent_color_alt) {
            $accent_color_alt = $accent_color;
        }

        if ('' !== $stat_bg_color) {
            $style_declarations[] = '--discord-surface-background: ' . $stat_bg_color;
        }

        if ('' !== $stat_text_color) {
            $style_declarations[] = '--discord-surface-text: ' . $stat_text_color;
        }

        if ('' !== $accent_color) {
            $style_declarations[] = '--discord-accent: ' . $accent_color;
            $style_declarations[] = '--discord-logo-color: ' . $accent_color;
        }

        if ('' !== $accent_color_alt) {
            $style_declarations[] = '--discord-accent-secondary: ' . $accent_color_alt;
        }

        if ('' !== $accent_text_color) {
            $style_declarations[] = '--discord-accent-contrast: ' . $accent_text_color;
        }

        if (!empty($atts['width'])) {
            $validated_width = $this->validate_width_value($atts['width']);

            if ('' !== $validated_width) {
                $width_keyword_values = array('auto', 'fit-content', 'max-content', 'min-content');
                $lower_width         = strtolower($validated_width);

                if (!in_array($lower_width, $width_keyword_values, true)) {
                    $style_declarations[] = 'width: 100%';
                } else {
                    $style_declarations[] = 'width: ' . $validated_width;
                }

                $style_declarations[] = 'max-width: ' . $validated_width;
            }
        }

        $title_text = is_string($atts['title']) ? trim($atts['title']) : '';
        $title_id   = $unique_id . '-title';
        $server_name_id = $unique_id . '-server-name';

        $region_label_ids            = array();
        $region_synthetic_label_id   = $unique_id . '-region-label';
        $render_synthetic_label      = false;
        $region_label_text           = '';

        if ($show_title && '' !== $title_text) {
            $region_label_ids[] = $title_id;
        }

        if ($show_server_name && '' !== $server_name) {
            $region_label_ids[] = $server_name_id;
        }

        $region_label_base = __('Statistiques Discord', 'discord-bot-jlg');
        /* translators: %s: Discord server name. */
        $region_label_pattern = __('Statistiques Discord – %s', 'discord-bot-jlg');

        if (empty($region_label_ids)) {
            if ('' !== $server_name) {
                $region_label_text = sprintf($region_label_pattern, $server_name);
            } else {
                $region_label_text = $region_label_base;
            }

            if ('' === $region_label_text) {
                $region_label_text = __('Statistiques Discord', 'discord-bot-jlg');
            }

            $region_label_ids[]       = $region_synthetic_label_id;
            $render_synthetic_label   = true;
        }

        if (!empty($region_label_ids)) {
            $region_label_ids = array_values(array_unique(array_filter($region_label_ids, 'strlen')));
        }

        if ('' !== $region_label_text) {
            $region_label_text = trim($region_label_text);
        }

        $date_format_option = trim(get_option('date_format'));
        $time_format_option = trim(get_option('time_format'));
        $combined_datetime_format = trim($date_format_option . ' ' . $time_format_option);

        if ('' === $combined_datetime_format) {
            $combined_datetime_format = 'F j, Y H:i';
        }

        $format_status_timestamp = static function ($timestamp) use ($combined_datetime_format) {
            $timestamp = (int) $timestamp;

            if ($timestamp <= 0) {
                return '';
            }

            if (function_exists('discord_bot_jlg_format_datetime')) {
                return discord_bot_jlg_format_datetime($combined_datetime_format, $timestamp);
            }

            if (function_exists('wp_date')) {
                return wp_date($combined_datetime_format, $timestamp);
            }

            return date_i18n($combined_datetime_format, $timestamp);
        };

        $refresh_interval = 0;
        $min_refresh_interval = $min_refresh_option;

        if ($refresh && (!$is_demo || $is_fallback_demo)) {
            $refresh_interval = max($min_refresh_interval, intval($atts['refresh_interval']));
        }

        if ($refresh_interval > 0) {
            $attributes[] = sprintf('data-refresh="%s"', esc_attr($refresh_interval));
        }

        $this->enqueue_assets($options, ($refresh_interval > 0));

        $stale_notice_text = '';

        if ($is_stale) {
            $stale_notice_text = __('Données mises en cache', 'discord-bot-jlg');

            if ($last_updated > 0) {
                $formatted_timestamp = $format_status_timestamp($last_updated);

                if ('' !== $formatted_timestamp) {
                    $template = __('Données mises en cache du %s', 'discord-bot-jlg');

                    if (false !== strpos($template, '%s')) {
                        $stale_notice_text = sprintf($template, $formatted_timestamp);
                    } else {
                        $stale_notice_text = trim($template . ' ' . $formatted_timestamp);
                    }
                }
            }
        }

        $cache_duration = isset($options['cache_duration']) ? (int) $options['cache_duration'] : 0;
        if ($cache_duration < $min_refresh_option || $cache_duration > 3600) {
            $cache_duration = 300;
        }

        $now_timestamp = (int) current_time('timestamp', true);
        $next_refresh_estimate = 0;

        if ($refresh_interval > 0) {
            $next_refresh_estimate = $now_timestamp + $refresh_interval;
        } elseif ($cache_duration > 0) {
            $reference_timestamp = ($last_updated > 0) ? $last_updated : $now_timestamp;
            $next_refresh_estimate = $reference_timestamp + $cache_duration;
        }

        $fallback_details = array();
        if ($is_fallback_demo) {
            $fallback_details = $this->api->get_last_fallback_details();
        }

        $fallback_reason = '';
        $fallback_timestamp = 0;
        $fallback_next_retry = 0;

        if (is_array($fallback_details) && !empty($fallback_details)) {
            $fallback_reason     = isset($fallback_details['reason']) ? trim((string) $fallback_details['reason']) : '';
            $fallback_timestamp  = isset($fallback_details['timestamp']) ? (int) $fallback_details['timestamp'] : 0;
            $fallback_next_retry = isset($fallback_details['next_retry']) ? (int) $fallback_details['next_retry'] : 0;

            if ($fallback_timestamp > 0 && $last_updated <= 0) {
                $last_updated = $fallback_timestamp;
            }
        }

        $status_variant = 'live';
        if ($is_fallback_demo) {
            $status_variant = 'fallback';
        } elseif ($is_demo) {
            $status_variant = 'demo';
        } elseif ($is_stale) {
            $status_variant = 'cache';
        }

        $status_labels = array(
            'live'     => __('Live', 'discord-bot-jlg'),
            'cache'    => __('Cache', 'discord-bot-jlg'),
            'fallback' => __('Secours', 'discord-bot-jlg'),
            'demo'     => __('Démo', 'discord-bot-jlg'),
            'unknown'  => __('Statut', 'discord-bot-jlg'),
        );

        $status_descriptions = array(
            'live'     => __('Données à jour en provenance de Discord.', 'discord-bot-jlg'),
            'cache'    => __('Données servies depuis le cache local.', 'discord-bot-jlg'),
            'fallback' => __('Statistiques de secours utilisées le temps de la reprise.', 'discord-bot-jlg'),
            'demo'     => __('Jeu de données de démonstration.', 'discord-bot-jlg'),
            'unknown'  => '',
        );

        $status_label_initial = isset($status_labels[$status_variant]) ? $status_labels[$status_variant] : $status_labels['unknown'];
        $status_description_initial = isset($status_descriptions[$status_variant]) ? $status_descriptions[$status_variant] : $status_descriptions['unknown'];

        $status_mode_initial = $status_label_initial;
        if ('' !== $status_description_initial) {
            /* translators: 1: human readable status, 2: textual description. */
            $status_mode_initial = sprintf(__('%1$s – %2$s', 'discord-bot-jlg'), $status_label_initial, $status_description_initial);
        }

        $formatted_last_updated = $format_status_timestamp($last_updated);
        $formatted_next_refresh = $format_status_timestamp($next_refresh_estimate);
        $formatted_next_retry   = $format_status_timestamp($fallback_next_retry);

        $no_data_label = __('Non disponible', 'discord-bot-jlg');

        if ('' === $formatted_last_updated) {
            $formatted_last_updated = $no_data_label;
        }

        if ('' === $formatted_next_refresh) {
            $formatted_next_refresh = $no_data_label;
        }

        if ('' === $formatted_next_retry) {
            $formatted_next_retry = $no_data_label;
        }

        $can_force_refresh = current_user_can('manage_options');
        $logs_url = $can_force_refresh ? admin_url('admin.php?page=discord-bot-jlg') : '';

        $status_meta = array(
            'variant'          => $status_variant,
            'isDemo'           => $is_demo,
            'isFallbackDemo'   => $is_fallback_demo,
            'isStale'          => $is_stale,
            'lastUpdated'      => ($last_updated > 0) ? $last_updated : null,
            'refreshInterval'  => ($refresh_interval > 0) ? $refresh_interval : null,
            'cacheDuration'    => ($cache_duration > 0) ? $cache_duration : null,
            'nextRefresh'      => ($next_refresh_estimate > 0) ? $next_refresh_estimate : null,
            'generatedAt'      => $now_timestamp,
            'retryAfter'       => null,
            'nextRetry'        => ($fallback_next_retry > 0) ? $fallback_next_retry : null,
            'profileKey'       => $profile_key,
            'serverId'         => ('' !== $override_server_id) ? $override_server_id : (isset($stats['server_id']) ? (string) $stats['server_id'] : ''),
            'forceDemo'        => $force_demo,
            'canForceRefresh'  => $can_force_refresh,
            'logsUrl'          => $logs_url,
            'fallbackDetails'  => array(),
            'history'          => array(),
        );

        if (!empty($fallback_reason) || $fallback_timestamp > 0 || $fallback_next_retry > 0) {
            $status_meta['fallbackDetails'] = array(
                'timestamp' => $fallback_timestamp,
                'reason'    => $fallback_reason,
                'nextRetry' => $fallback_next_retry,
            );
        }

        $history_args = array(
            'limit'       => 5,
            'profile_key' => ('' !== $profile_key) ? $profile_key : 'default',
        );

        if ('' !== $override_server_id) {
            $history_args['server_id'] = $override_server_id;
        } elseif (isset($stats['server_id'])) {
            $history_args['server_id'] = $this->sanitize_server_id_attribute($stats['server_id']);
        }

        $status_history = $this->api->get_status_history($history_args);
        if (is_array($status_history)) {
            $status_meta['history'] = $status_history;
        }

        $status_meta_json = wp_json_encode($status_meta);
        if (false === $status_meta_json) {
            $status_meta_json = '{}';
        }

        $attributes = array(
            sprintf('id="%s"', esc_attr($unique_id)),
            sprintf('class="%s"', esc_attr(implode(' ', $container_classes))),
            'role="region"',
            sprintf('data-demo="%s"', esc_attr($is_forced_demo ? 'true' : 'false')),
            sprintf('data-fallback-demo="%s"', esc_attr($is_fallback_demo ? 'true' : 'false')),
            sprintf('data-stale="%s"', esc_attr($is_stale ? 'true' : 'false')),
            sprintf('data-hide-labels="%s"', esc_attr($hide_labels ? 'true' : 'false')),
            'aria-live="polite"',
            'aria-busy="false"',
            sprintf('data-region-label-base="%s"', esc_attr($region_label_base)),
            sprintf('data-region-label-pattern="%s"', esc_attr($region_label_pattern)),
            sprintf('data-region-title-id="%s"', esc_attr($title_id)),
            sprintf('data-region-server-id="%s"', esc_attr($server_name_id)),
            sprintf('data-region-synthetic-id="%s"', esc_attr($region_synthetic_label_id)),
        );

        if (!empty($region_label_ids)) {
            $labelledby_value = implode(' ', $region_label_ids);
            $attributes[]     = sprintf('aria-labelledby="%s"', esc_attr($labelledby_value));
            $attributes[]     = sprintf('data-region-label-ids="%s"', esc_attr($labelledby_value));
            $attributes[]     = 'data-region-labelling="labelledby"';
        }

        if ($render_synthetic_label) {
            $attributes[] = sprintf('aria-label="%s"', esc_attr($region_label_text));
            $attributes[] = sprintf('data-region-label="%s"', esc_attr($region_label_text));
        }

        if ('' !== $server_name) {
            $attributes[] = sprintf('data-region-server-name="%s"', esc_attr($server_name));
        }

        if ($is_stale && $last_updated > 0) {
            $attributes[] = sprintf('data-last-updated="%s"', esc_attr($last_updated));
        }

        if ('' !== $status_meta_json) {
            $attributes[] = sprintf('data-status-meta="%s"', esc_attr($status_meta_json));
        }

        $attributes[] = sprintf('data-status-variant="%s"', esc_attr($status_variant));
        $attributes[] = sprintf('data-can-force-refresh="%s"', esc_attr($can_force_refresh ? 'true' : 'false'));

        if ($cache_duration > 0) {
            $attributes[] = sprintf('data-cache-duration="%d"', (int) $cache_duration);
        }

        if (!empty($style_declarations)) {
            $attributes[] = sprintf('style="%s"', esc_attr(implode('; ', $style_declarations)));
        }

        if ($show_server_name) {
            $attributes[] = 'data-show-server-name="true"';

            if ('' !== $server_name) {
                $attributes[] = sprintf('data-server-name="%s"', esc_attr($server_name));
            }
        }

        if ($show_server_avatar) {
            $attributes[] = 'data-show-server-avatar="true"';
            $attributes[] = sprintf('data-avatar-size="%s"', esc_attr($avatar_size));

            if ('' !== $server_avatar_url) {
                $attributes[] = sprintf('data-server-avatar-url="%s"', esc_url($server_avatar_url));
            }

            if ('' !== $server_avatar_base) {
                $attributes[] = sprintf('data-server-avatar-base-url="%s"', esc_url($server_avatar_base));
            }
        }

        if ('' !== $profile_key) {
            $attributes[] = sprintf('data-profile-key="%s"', esc_attr($profile_key));
        }

        if ('' !== $override_server_id) {
            $attributes[] = sprintf('data-server-id-override="%s"', esc_attr($override_server_id));
        }

        if ($show_sparkline) {
            $attributes[] = 'data-show-sparkline="true"';
            $attributes[] = sprintf('data-sparkline-metric="%s"', esc_attr($sparkline_metric));
            $attributes[] = sprintf('data-sparkline-days="%d"', (int) $sparkline_days);
        }

        $status_panel_id        = $unique_id . '-status-panel';
        $status_panel_title_id  = $status_panel_id . '-title';
        $status_history_id      = $unique_id . '-status-history';

        $discord_svg = '<svg class="discord-logo-svg" aria-hidden="true" focusable="false" viewBox="0 0 127.14 96.36" xmlns="http://www.w3.org/2000/svg"><path d="M107.7,8.07A105.15,105.15,0,0,0,81.47,0a72.06,72.06,0,0,0-3.36,6.83A97.68,97.68,0,0,0,49,6.83,72.37,72.37,0,0,0,45.64,0,105.89,105.89,0,0,0,19.39,8.09C2.79,32.65-1.71,56.6.54,80.21h0A105.73,105.73,0,0,0,32.71,96.36,77.7,77.7,0,0,0,39.6,85.25a68.42,68.42,0,0,1-10.85-5.18c.91-.66,1.8-1.34,2.66-2a75.57,75.57,0,0,0,64.32,0c.87.71,1.76,1.39,2.66,2a68.68,68.68,0,0,1-10.87,5.19,77,77,0,0,0,6.89,11.1A105.25,105.25,0,0,0,126.6,80.22h0C129.24,52.84,122.09,29.11,107.7,8.07ZM42.45,65.69C36.18,65.69,31,60,31,53s5-12.74,11.43-12.74S54,46,53.89,53,48.84,65.69,42.45,65.69Zm42.24,0C78.41,65.69,73.25,60,73.25,53s5-12.74,11.44-12.74S96.23,46,96.12,53,91.08,65.69,84.69,65.69Z"/></svg>';

        ob_start();
        ?>
        <div <?php echo implode(' ', $attributes); ?>>

            <?php if ($render_synthetic_label): ?>
            <span class="screen-reader-text discord-region-label"
                data-region-synthetic-label="true"
                id="<?php echo esc_attr($region_synthetic_label_id); ?>"><?php echo esc_html($region_label_text); ?></span>
            <?php endif; ?>

            <div class="discord-status-surface">
                <button type="button"
                    class="discord-status-badge"
                    data-status-badge="true"
                    data-status-toggle="true"
                    aria-expanded="false"
                    aria-haspopup="dialog"
                    aria-controls="<?php echo esc_attr($status_panel_id); ?>"
                    aria-label="<?php echo esc_attr__('Afficher le statut détaillé des données Discord', 'discord-bot-jlg'); ?>">
                    <span class="discord-status-badge__indicator" aria-hidden="true">
                        <span class="discord-status-badge__dot"></span>
                        <svg class="discord-status-badge__progress" viewBox="0 0 36 36" role="presentation" focusable="false">
                            <circle class="discord-status-badge__progress-indicator" cx="18" cy="18" r="16"></circle>
                        </svg>
                    </span>
                    <span class="discord-status-badge__label" data-status-label><?php echo esc_html($status_label_initial); ?></span>
                    <span class="discord-status-badge__countdown" data-status-countdown></span>
                    <span class="discord-status-badge__chevron" aria-hidden="true"></span>
                </button>
                <div class="discord-status-panel"
                    data-status-panel
                    id="<?php echo esc_attr($status_panel_id); ?>"
                    role="dialog"
                    aria-modal="false"
                    aria-labelledby="<?php echo esc_attr($status_panel_title_id); ?>"
                    aria-describedby="<?php echo esc_attr($status_history_id); ?>"
                    hidden>
                    <div class="discord-status-panel__header">
                        <div class="discord-status-panel__title" id="<?php echo esc_attr($status_panel_title_id); ?>" data-status-mode><?php echo esc_html($status_mode_initial); ?></div>
                        <button type="button" class="discord-status-panel__close" data-status-close aria-label="<?php echo esc_attr__('Fermer le panneau de statut', 'discord-bot-jlg'); ?>">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="discord-status-panel__content">
                        <dl class="discord-status-meta">
                            <div class="discord-status-meta__item">
                                <dt><?php esc_html_e('Dernière synchronisation', 'discord-bot-jlg'); ?></dt>
                                <dd data-status-last-sync><?php echo esc_html($formatted_last_updated); ?></dd>
                            </div>
                            <div class="discord-status-meta__item">
                                <dt><?php esc_html_e('Prochaine actualisation', 'discord-bot-jlg'); ?></dt>
                                <dd data-status-next-sync><?php echo esc_html($formatted_next_refresh); ?></dd>
                            </div>
                            <div class="discord-status-meta__item">
                                <dt><?php esc_html_e('Prochaine tentative', 'discord-bot-jlg'); ?></dt>
                                <dd data-status-next-retry><?php echo esc_html($formatted_next_retry); ?></dd>
                            </div>
                        </dl>
                        <?php if ('' !== $fallback_reason) : ?>
                        <div class="discord-status-panel__note"><?php echo esc_html($fallback_reason); ?></div>
                        <?php endif; ?>
                        <div class="discord-status-panel__actions">
                            <button type="button"
                                class="discord-status-panel__action<?php echo $can_force_refresh ? '' : ' is-disabled'; ?>"
                                data-status-force-refresh<?php echo $can_force_refresh ? '' : ' disabled="disabled"'; ?>>
                                <?php esc_html_e('Forcer une synchronisation', 'discord-bot-jlg'); ?>
                            </button>
                            <a class="discord-status-panel__link" data-status-log-link href="<?php echo esc_url('' !== $logs_url ? $logs_url : '#'); ?>"<?php echo '' === $logs_url ? ' hidden' : ''; ?> target="_blank" rel="noreferrer noopener">
                                <?php esc_html_e('Voir le journal', 'discord-bot-jlg'); ?>
                            </a>
                        </div>
                        <div class="discord-status-history">
                            <button type="button" class="discord-status-history__toggle" data-status-history-toggle disabled="disabled"><?php esc_html_e('Voir le journal', 'discord-bot-jlg'); ?></button>
                            <p class="discord-status-history__empty" data-status-history-empty><?php esc_html_e('Aucun incident récent.', 'discord-bot-jlg'); ?></p>
                            <ul class="discord-status-history__list" data-status-history id="<?php echo esc_attr($status_history_id); ?>" hidden></ul>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (!empty($stats['is_demo'])): ?>
            <div class="discord-demo-badge"><?php echo esc_html__('Mode Démo', 'discord-bot-jlg'); ?></div>
            <?php endif; ?>

            <?php if ($is_stale && '' !== $stale_notice_text): ?>
            <div class="discord-stale-notice"><?php echo esc_html($stale_notice_text); ?></div>
            <?php endif; ?>

            <?php if ($show_title): ?>
            <div class="discord-stats-title" id="<?php echo esc_attr($title_id); ?>"><?php echo esc_html($atts['title']); ?></div>
            <?php endif; ?>

            <div class="discord-stats-main">
                <?php if ($show_discord_icon && $logo_position_class === 'left'): ?>
                <div class="discord-logo-container">
                    <?php echo $discord_svg; ?>
                </div>
                <?php endif; ?>

                <?php if ($show_discord_icon && $logo_position_class === 'top'): ?>
                <div class="discord-logo-container discord-logo-top">
                    <?php echo $discord_svg; ?>
                </div>
                <?php endif; ?>

                <div class="discord-stats-wrapper">
                    <?php if (($show_server_avatar && '' !== $server_avatar_url) || ($show_server_name && '' !== $server_name)) : ?>
                    <div class="discord-server-header" data-role="discord-server-header">
                        <?php if ($show_server_avatar && '' !== $server_avatar_url) :
                            $avatar_alt = ('' !== $server_name)
                                ? sprintf(__('Avatar du serveur Discord %s', 'discord-bot-jlg'), $server_name)
                                : __('Avatar du serveur Discord', 'discord-bot-jlg');
                        ?>
                        <div class="discord-server-avatar" data-role="discord-server-avatar">
                            <img class="discord-server-avatar__image"
                                src="<?php echo esc_url($server_avatar_url); ?>"
                                alt="<?php echo esc_attr($avatar_alt); ?>"
                                loading="lazy"
                                decoding="async"
                                width="<?php echo esc_attr($avatar_size); ?>"
                                height="<?php echo esc_attr($avatar_size); ?>"
                            />
                        </div>
                        <?php endif; ?>
                        <?php if ($show_server_name && '' !== $server_name) : ?>
                        <div class="discord-server-name" data-role="discord-server-name">
                            <span class="discord-server-name__text" id="<?php echo esc_attr($server_name_id); ?>"><?php echo esc_html($server_name); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    <?php if ($show_online) : ?>
                    <?php
                    $online_label_classes = array('discord-label');
                    if ($hide_labels) {
                        $online_label_classes[] = 'screen-reader-text';
                    }
                    ?>
                    <?php $online_label_id = $unique_id . '-label-online'; ?>
                    <div class="discord-stat discord-online"
                        data-value="<?php echo esc_attr((int) $stats['online']); ?>"
                        data-label-online="<?php echo esc_attr($atts['label_online']); ?>"
                        data-hide-labels="<?php echo esc_attr($hide_labels ? 'true' : 'false'); ?>"
                        data-label-id="<?php echo esc_attr($online_label_id); ?>">
                        <?php if (!$hide_icons): ?>
                        <span class="discord-icon"><?php echo esc_html($atts['icon_online']); ?></span>
                        <?php endif; ?>
                        <span class="discord-number" role="status" aria-live="polite" aria-labelledby="<?php echo esc_attr($online_label_id); ?>">
                            <span class="discord-number-value"><?php echo esc_html(number_format_i18n((int) $stats['online'])); ?></span>
                        </span>
                        <span class="<?php echo esc_attr(implode(' ', $online_label_classes)); ?>">
                            <span class="discord-label-text" id="<?php echo esc_attr($online_label_id); ?>"><?php echo esc_html($atts['label_online']); ?></span>
                        </span>
                    </div>
                    <?php endif; ?>

                    <?php if ($show_total) : ?>
                    <?php
                    $total_classes = array('discord-stat', 'discord-total');
                    if ($has_total) {
                        if ($total_is_approximate) {
                            $total_classes[] = 'discord-total-approximate';
                        }
                    } else {
                        $total_classes[] = 'discord-total-unavailable';
                    }

                    $label_unavailable = __('Total indisponible', 'discord-bot-jlg');
                    $label_classes    = array('discord-label');
                    if ($hide_labels) {
                        $label_classes[] = 'screen-reader-text';
                    }
                    ?>
                    <?php
                    $total_label_id        = $unique_id . '-label-total';
                    $total_label_extra_id  = $unique_id . '-label-total-extra';
                    $total_aria_labelledby = trim($total_label_id . ' ' . $total_label_extra_id);
                    ?>
                    <div class="<?php echo esc_attr(implode(' ', $total_classes)); ?>"
                        <?php if ($has_total): ?>
                        data-value="<?php echo esc_attr((int) $stats['total']); ?>"
                        <?php endif; ?>
                        data-label-total="<?php echo esc_attr($atts['label_total']); ?>"
                        data-label-unavailable="<?php echo esc_attr($label_unavailable); ?>"
                        data-label-approx="<?php echo esc_attr__('approx.', 'discord-bot-jlg'); ?>"
                        data-placeholder="<?php echo esc_attr('—'); ?>">
                        <?php if (!$hide_icons): ?>
                        <span class="discord-icon"><?php echo esc_html($atts['icon_total']); ?></span>
                        <?php endif; ?>
                        <span class="discord-number" role="status" aria-live="polite" aria-labelledby="<?php echo esc_attr($total_aria_labelledby); ?>">
                            <span class="discord-number-value"><?php echo $has_total ? esc_html(number_format_i18n((int) $stats['total'])) : '&mdash;'; ?></span>
                        </span>
                        <span class="discord-approx-indicator" aria-hidden="true"<?php echo $total_is_approximate ? '' : ' hidden'; ?>>≈</span>
                        <span class="<?php echo esc_attr(implode(' ', $label_classes)); ?>">
                            <span class="discord-label-text" id="<?php echo esc_attr($total_label_id); ?>"><?php echo esc_html($has_total ? $atts['label_total'] : $label_unavailable); ?></span>
                            <span class="discord-label-extra screen-reader-text" id="<?php echo esc_attr($total_label_extra_id); ?>"><?php echo $total_is_approximate ? esc_html__('approx.', 'discord-bot-jlg') : ''; ?></span>
                        </span>
                    </div>
                    <?php endif; ?>

                    <?php if ($show_presence_breakdown && (null !== $approximate_presence || !empty($presence_counts))) : ?>
                    <?php
                    $presence_label_classes = array('discord-label');
                    if ($hide_labels) {
                        $presence_label_classes[] = 'screen-reader-text';
                    }

                    $presence_label_map = array(
                        'online'    => isset($atts['label_presence_online']) ? $atts['label_presence_online'] : '',
                        'idle'      => isset($atts['label_presence_idle']) ? $atts['label_presence_idle'] : '',
                        'dnd'       => isset($atts['label_presence_dnd']) ? $atts['label_presence_dnd'] : '',
                        'offline'   => isset($atts['label_presence_offline']) ? $atts['label_presence_offline'] : '',
                        'streaming' => isset($atts['label_presence_streaming']) ? $atts['label_presence_streaming'] : '',
                        'other'     => isset($atts['label_presence_other']) ? $atts['label_presence_other'] : '',
                    );

                    $presence_counts_ordered = array();
                    if (!empty($presence_counts)) {
                        $preferred_presence_order = array('online', 'idle', 'dnd', 'offline', 'streaming', 'other');
                        foreach ($preferred_presence_order as $preferred_status) {
                            if (array_key_exists($preferred_status, $presence_counts)) {
                                $presence_counts_ordered[$preferred_status] = $presence_counts[$preferred_status];
                            }
                        }

                        foreach ($presence_counts as $presence_status => $presence_value) {
                            if (array_key_exists($presence_status, $presence_counts_ordered)) {
                                continue;
                            }

                            $presence_counts_ordered[$presence_status] = $presence_value;
                        }
                    }

                    $presence_display_value = (null !== $approximate_presence)
                        ? $approximate_presence
                        : array_sum($presence_counts_ordered);

                    if ($presence_display_value < 0) {
                        $presence_display_value = 0;
                    }
                    ?>
                    <?php $presence_label_id = $unique_id . '-label-presence'; ?>
                    <div class="discord-stat discord-presence-breakdown"
                        data-role="discord-presence-breakdown"
                        data-label-presence="<?php echo esc_attr($atts['label_presence']); ?>"
                        data-label-online="<?php echo esc_attr($presence_label_map['online']); ?>"
                        data-label-idle="<?php echo esc_attr($presence_label_map['idle']); ?>"
                        data-label-dnd="<?php echo esc_attr($presence_label_map['dnd']); ?>"
                        data-label-offline="<?php echo esc_attr($presence_label_map['offline']); ?>"
                        data-label-streaming="<?php echo esc_attr($presence_label_map['streaming']); ?>"
                        data-label-other="<?php echo esc_attr($presence_label_map['other']); ?>"
                        data-hide-labels="<?php echo esc_attr($hide_labels ? 'true' : 'false'); ?>"
                        <?php if (null !== $presence_display_value) : ?>
                        data-value="<?php echo esc_attr($presence_display_value); ?>"
                        <?php endif; ?>>
                        <?php if (!$hide_icons): ?>
                        <span class="discord-icon"><?php echo esc_html($atts['icon_presence']); ?></span>
                        <?php endif; ?>
                        <div class="discord-presence-content">
                            <div class="discord-presence-summary">
                                <span class="discord-number" role="status" aria-live="polite" aria-labelledby="<?php echo esc_attr($presence_label_id); ?>">
                                    <span class="discord-number-value"><?php echo esc_html(number_format_i18n($presence_display_value)); ?></span>
                                </span>
                                <span class="<?php echo esc_attr(implode(' ', $presence_label_classes)); ?>">
                                    <span class="discord-label-text" id="<?php echo esc_attr($presence_label_id); ?>"><?php echo esc_html($atts['label_presence']); ?></span>
                                </span>
                            </div>
                            <?php if (!empty($presence_counts_ordered)) : ?>
                            <ul class="discord-presence-list">
                                <?php foreach ($presence_counts_ordered as $presence_status => $presence_value) :
                                    $status_label = isset($presence_label_map[$presence_status])
                                        ? $presence_label_map[$presence_status]
                                        : ucfirst($presence_status);
                                ?>
                                <li class="discord-presence-item discord-presence-<?php echo esc_attr($presence_status); ?>"
                                    data-status="<?php echo esc_attr($presence_status); ?>"
                                    data-label="<?php echo esc_attr($status_label); ?>">
                                    <span class="discord-presence-dot" aria-hidden="true"></span>
                                    <span class="discord-presence-item-label"><?php echo esc_html($status_label); ?></span>
                                    <span class="discord-presence-item-value"><?php echo esc_html(number_format_i18n(max(0, (int) $presence_value))); ?></span>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($show_approximate_members && null !== $approximate_member_count) : ?>
                    <?php
                    $approx_label_classes = array('discord-label');
                    if ($hide_labels) {
                        $approx_label_classes[] = 'screen-reader-text';
                    }
                    ?>
                    <?php $approximate_label_id = $unique_id . '-label-approximate'; ?>
                    <div class="discord-stat discord-approximate-members"
                        data-role="discord-approximate-members"
                        data-label-approximate="<?php echo esc_attr($atts['label_approximate']); ?>"
                        data-placeholder="<?php echo esc_attr('—'); ?>"
                        data-value="<?php echo esc_attr($approximate_member_count); ?>">
                        <?php if (!$hide_icons): ?>
                        <span class="discord-icon"><?php echo esc_html($atts['icon_approximate']); ?></span>
                        <?php endif; ?>
                        <span class="discord-number" role="status" aria-live="polite" aria-labelledby="<?php echo esc_attr($approximate_label_id); ?>">
                            <span class="discord-number-value"><?php echo esc_html(number_format_i18n($approximate_member_count)); ?></span>
                        </span>
                        <span class="discord-approx-indicator" aria-hidden="true">≈</span>
                        <span class="<?php echo esc_attr(implode(' ', $approx_label_classes)); ?>">
                            <span class="discord-label-text" id="<?php echo esc_attr($approximate_label_id); ?>"><?php echo esc_html($atts['label_approximate']); ?></span>
                        </span>
                    </div>
                    <?php endif; ?>

                    <?php if ($show_premium_subscriptions) : ?>
                    <?php
                    $premium_label_classes = array('discord-label');
                    if ($hide_labels) {
                        $premium_label_classes[] = 'screen-reader-text';
                    }
                    $premium_label_text = $premium_subscription_count === 1
                        ? $atts['label_premium_singular']
                        : $atts['label_premium_plural'];

                    if ('' === $premium_label_text) {
                        $premium_label_text = $atts['label_premium'];
                    }
                    ?>
                    <?php $premium_label_id = $unique_id . '-label-premium'; ?>
                    <div class="discord-stat discord-premium-subscriptions"
                        data-role="discord-premium-subscriptions"
                        data-label-premium="<?php echo esc_attr($atts['label_premium']); ?>"
                        data-label-premium-singular="<?php echo esc_attr($atts['label_premium_singular']); ?>"
                        data-label-premium-plural="<?php echo esc_attr($atts['label_premium_plural']); ?>"
                        data-placeholder="<?php echo esc_attr('0'); ?>"
                        data-value="<?php echo esc_attr($premium_subscription_count); ?>">
                        <?php if (!$hide_icons): ?>
                        <span class="discord-icon"><?php echo esc_html($atts['icon_premium']); ?></span>
                        <?php endif; ?>
                        <span class="discord-number" role="status" aria-live="polite" aria-labelledby="<?php echo esc_attr($premium_label_id); ?>">
                            <span class="discord-number-value"><?php echo esc_html(number_format_i18n($premium_subscription_count)); ?></span>
                        </span>
                        <span class="<?php echo esc_attr(implode(' ', $premium_label_classes)); ?>">
                            <span class="discord-label-text" id="<?php echo esc_attr($premium_label_id); ?>"><?php echo esc_html($premium_label_text); ?></span>
                        </span>
                    </div>
                    <?php endif; ?>

                    <?php if ($show_sparkline) : ?>
                    <div class="discord-analytics-embed"
                        data-role="discord-analytics-embed"
                        data-metric="<?php echo esc_attr($sparkline_metric); ?>"
                        data-days="<?php echo esc_attr($sparkline_days); ?>">
                        <div class="discord-analytics-embed__header">
                            <span class="discord-analytics-embed__title"><?php echo esc_html($sparkline_label); ?></span>
                            <span class="discord-analytics-embed__range"><?php printf(esc_html__('Derniers %d jours', 'discord-bot-jlg'), $sparkline_days); ?></span>
                        </div>
                        <div class="discord-analytics-embed__sparkline" data-role="discord-sparkline"></div>
                        <div class="discord-analytics-embed__note" data-role="discord-sparkline-note"></div>
                    </div>
                    <?php endif; ?>
                </div>

                <?php if ($show_discord_icon && $logo_position_class === 'right'): ?>
                <div class="discord-logo-container">
                    <?php echo $discord_svg; ?>
                </div>
                <?php endif; ?>
                <?php if ($cta_enabled): ?>
                <div class="discord-cta" data-role="discord-cta">
                    <a <?php echo implode(' ', $cta_button_attributes); ?>>
                        <span class="discord-cta-button__label"><?php echo esc_html($cta_label); ?></span>
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ('' !== $invite_url) :
            $invite_button_classes = array('discord-invite-button', 'wp-element-button');

            if ($compact) {
                $invite_button_classes[] = 'discord-invite-button--compact';
            }

            $invite_rel_value = implode(' ', array('noopener', 'noreferrer', 'nofollow'));
            $invite_link_attributes = array(
                sprintf('class="%s"', esc_attr(implode(' ', $invite_button_classes))),
                sprintf('href="%s"', esc_url($invite_url)),
                'target="_blank"',
                sprintf('rel="%s"', esc_attr($invite_rel_value)),
            );
        ?>
        <div class="discord-invite">
            <a <?php echo implode(' ', $invite_link_attributes); ?>>
                <span class="discord-invite-button__label"><?php echo esc_html($invite_label); ?></span>
            </a>
        </div>
        <?php endif; ?>

        <?php
        return ob_get_clean();
    }

    private function sanitize_avatar_size($size) {
        $allowed_sizes = array(16, 32, 64, 128, 256, 512, 1024, 2048, 4096);
        $size = (int) $size;

        if ($size <= 0) {
            $size = 128;
        }

        if (in_array($size, $allowed_sizes, true)) {
            return $size;
        }

        foreach ($allowed_sizes as $allowed) {
            if ($size <= $allowed) {
                return $allowed;
            }
        }

        return $allowed_sizes[count($allowed_sizes) - 1];
    }

    private function prepare_avatar_url($base_url, $fallback_url, $size) {
        $base = '';

        if ('' !== $base_url) {
            $base = $base_url;
        } elseif ('' !== $fallback_url) {
            $base = $fallback_url;
        }

        if ('' === $base) {
            return '';
        }

        $base = remove_query_arg('size', $base);

        return add_query_arg('size', $this->sanitize_avatar_size($size), $base);
    }

    private function sanitize_profile_key($value) {
        if (!is_string($value)) {
            return '';
        }

        $value = trim($value);

        if ('' === $value) {
            return '';
        }

        return sanitize_key($value);
    }

    private function sanitize_server_id_attribute($value) {
        if (!is_string($value) && !is_numeric($value)) {
            return '';
        }

        $value = preg_replace('/[^0-9]/', '', (string) $value);

        return (string) $value;
    }

    private function validate_width_value($raw_width) {
        if (is_array($raw_width)) {
            return '';
        }

        $width = trim((string) $raw_width);

        if ('' === $width) {
            return '';
        }

        $width = preg_replace('/\s+/', ' ', $width);

        if (null === $width) {
            return '';
        }

        $length_pattern = '/^(?:\d+(?:\.\d+)?)(?:px|em|rem|%|vh|vw|vmin|vmax|ch|ex|cm|mm|in|pt|pc)$/i';
        if (preg_match($length_pattern, $width)) {
            return $width;
        }

        $keywords = array('auto', 'fit-content', 'max-content', 'min-content');
        $lower_width = strtolower($width);
        if (in_array($lower_width, $keywords, true)) {
            return $lower_width;
        }

        $calc_pattern = '/^calc\(\s*[0-9+\-*\/\.%\sA-Za-z()]+\)$/';
        if (preg_match($calc_pattern, $width)) {
            return $width;
        }

        $numeric_pattern = '-?\d+(?:\.\d+)?(?:px|em|rem|%|vh|vw|vmin|vmax|ch|ex|cm|mm|in|pt|pc)?';
        $variable_pattern = 'var\(\s*--[A-Za-z0-9_-]+\s*\)';
        $function_value_pattern = '(?:' . $numeric_pattern . '|' . $variable_pattern . ')';

        $min_max_pattern = '/^(?:min|max)\(\s*' . $function_value_pattern . '(?:\s*,\s*' . $function_value_pattern . ')+\s*\)$/i';
        if (preg_match($min_max_pattern, $width)) {
            return $width;
        }

        $clamp_pattern = '/^clamp\(\s*' . $function_value_pattern . '\s*,\s*' . $function_value_pattern . '\s*,\s*' . $function_value_pattern . '\s*\)$/i';
        if (preg_match($clamp_pattern, $width)) {
            return $width;
        }

        return '';
    }

    private function enqueue_assets($options, $needs_script = false) {
        $this->register_assets();

        if (!self::$inline_css_added && is_array($options) && !empty($options['custom_css'])) {
            $custom_css = discord_bot_jlg_sanitize_custom_css($options['custom_css']);

            if (
                '' !== $custom_css
                && false === strpos($custom_css, '</')
                && false === stripos($custom_css, '<script')
                && false === stripos($custom_css, '<style')
            ) {
                wp_add_inline_style('discord-bot-jlg-inline', $custom_css);
                self::$inline_css_added = true;
            }
        }

        wp_enqueue_style('discord-bot-jlg');
        wp_enqueue_style('discord-bot-jlg-inline');

        if ($needs_script) {
            wp_enqueue_script('discord-bot-jlg-frontend');
        }

        if (!self::$footer_hook_added) {
            add_action('wp_footer', array($this, 'print_late_styles'), 1);
            add_action('admin_print_footer_scripts', array($this, 'print_late_styles'), 1);
            self::$footer_hook_added = true;
        }
    }

    private function register_assets() {
        if (self::$assets_registered) {
            return;
        }

        wp_register_style(
            'discord-bot-jlg',
            DISCORD_BOT_JLG_PLUGIN_URL . 'assets/css/discord-bot-jlg.css',
            array(),
            DISCORD_BOT_JLG_VERSION
        );

        wp_register_style(
            'discord-bot-jlg-inline',
            DISCORD_BOT_JLG_PLUGIN_URL . 'assets/css/discord-bot-jlg-inline.css',
            array('discord-bot-jlg'),
            DISCORD_BOT_JLG_VERSION
        );

        $script_dependencies = array();

        wp_register_script(
            'discord-bot-jlg-frontend',
            DISCORD_BOT_JLG_PLUGIN_URL . 'assets/js/discord-bot-jlg.js',
            $script_dependencies,
            DISCORD_BOT_JLG_VERSION,
            true
        );

        if (function_exists('wp_get_script_polyfill')) {
            if (function_exists('wp_scripts')) {
                wp_scripts();
            }

            if (isset($GLOBALS['wp_scripts'])) {
                $polyfill_loader = wp_get_script_polyfill(
                    $GLOBALS['wp_scripts'],
                    array(
                        'fetch'   => 'wp-polyfill-fetch',
                        'Promise' => 'wp-polyfill-promise',
                    )
                );

                if (!empty($polyfill_loader) && function_exists('wp_add_inline_script')) {
                    wp_add_inline_script('discord-bot-jlg-frontend', $polyfill_loader, 'before');
                }
            }
        }

        $locale = str_replace('_', '-', get_locale());

        $requires_nonce = is_user_logged_in();

        wp_localize_script(
            'discord-bot-jlg-frontend',
            'discordBotJlg',
            array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'action'  => 'refresh_discord_stats',
                'nonce'   => $requires_nonce ? wp_create_nonce('refresh_discord_stats') : '',
                'requiresNonce' => $requires_nonce,
                'restUrl' => rest_url('discord-bot-jlg/v1/stats'),
                'restNonce' => $requires_nonce ? wp_create_nonce('wp_rest') : '',
                'analyticsRestUrl' => rest_url('discord-bot-jlg/v1/analytics'),
                'analyticsRestNonce' => $requires_nonce ? wp_create_nonce('wp_rest') : '',
                'locale'  => $locale,
                'minRefreshInterval' => defined('Discord_Bot_JLG_API::MIN_PUBLIC_REFRESH_INTERVAL')
                    ? Discord_Bot_JLG_API::MIN_PUBLIC_REFRESH_INTERVAL
                    : 10,
                'demoBadgeLabel'       => __('Mode Démo', 'discord-bot-jlg'),
                'genericError'         => __('Une erreur est survenue lors de la récupération des statistiques.', 'discord-bot-jlg'),
                'nonceExpiredFallback' => __('Votre session a expiré, veuillez recharger la page.', 'discord-bot-jlg'),
                'consoleErrorPrefix'   => __('Erreur lors de la mise à jour des statistiques Discord :', 'discord-bot-jlg'),
                'staleNotice'          => __('Données mises en cache du %s', 'discord-bot-jlg'),
                'rateLimited'          => __('Actualisation trop fréquente, veuillez patienter avant de réessayer.', 'discord-bot-jlg'),
                'serverAvatarAltTemplate' => __('Avatar du serveur Discord %s', 'discord-bot-jlg'),
                'serverAvatarAltFallback' => __('Avatar du serveur Discord', 'discord-bot-jlg'),
                'sparklineNoData'      => __('Tendance indisponible pour le moment.', 'discord-bot-jlg'),
                'sparklineError'       => __('Impossible de charger la tendance.', 'discord-bot-jlg'),
                'statusLabelLive'      => __('Live', 'discord-bot-jlg'),
                'statusLabelCache'     => __('Cache', 'discord-bot-jlg'),
                'statusLabelFallback'  => __('Secours', 'discord-bot-jlg'),
                'statusLabelDemo'      => __('Démo', 'discord-bot-jlg'),
                'statusLabelUnknown'   => __('Statut', 'discord-bot-jlg'),
                'statusDescriptionLive'     => __('Données à jour en provenance de Discord.', 'discord-bot-jlg'),
                'statusDescriptionCache'    => __('Données servies depuis le cache local.', 'discord-bot-jlg'),
                'statusDescriptionFallback' => __('Statistiques de secours utilisées le temps de la reprise.', 'discord-bot-jlg'),
                'statusDescriptionDemo'     => __('Jeu de données de démonstration.', 'discord-bot-jlg'),
                'statusDescriptionUnknown'  => __('Statut en attente.', 'discord-bot-jlg'),
                'statusCountdownPaused' => __('En pause', 'discord-bot-jlg'),
                'statusCountdownReady'  => __('Actualisation imminente', 'discord-bot-jlg'),
                'statusHistoryEmpty'    => __('Aucun incident récent.', 'discord-bot-jlg'),
                'statusHistoryShow'     => __('Voir le journal', 'discord-bot-jlg'),
                'statusHistoryHide'     => __('Masquer le journal', 'discord-bot-jlg'),
                'statusBadgeAriaLabel'  => __('Statut des données Discord', 'discord-bot-jlg'),
                'statusNoData'          => __('Non disponible', 'discord-bot-jlg'),
            )
        );

        self::$assets_registered = true;
    }

    /**
     * Force l'impression tardive des feuilles de style si elles sont encore en file d'attente.
     *
     * @return void
     */
    public function print_late_styles() {
        if (wp_style_is('discord-bot-jlg-inline', 'enqueued')) {
            wp_print_styles('discord-bot-jlg-inline');
        } elseif (wp_style_is('discord-bot-jlg', 'enqueued')) {
            wp_print_styles('discord-bot-jlg');
        }
    }
}
