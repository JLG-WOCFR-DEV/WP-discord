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

        $received_atts = is_array($atts) ? $atts : array();

        $atts = shortcode_atts(
            array(
                'layout'               => 'horizontal',
                'show_online'          => !empty($options['show_online']),
                'show_total'           => !empty($options['show_total']),
                'show_idle'            => false,
                'show_dnd'             => false,
                'show_offline'         => false,
                'show_voice'           => false,
                'show_boosts'          => false,
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
                'icon_online'          => '🟢',
                'icon_total'           => '👥',
                'icon_idle'            => '🌙',
                'icon_dnd'             => '⛔️',
                'icon_offline'         => '⚪️',
                'icon_voice'           => '🎧',
                'icon_boosts'          => '🚀',
                'label_online'         => __('En ligne', 'discord-bot-jlg'),
                'label_total'          => __('Membres', 'discord-bot-jlg'),
                'label_idle'           => __('Inactifs', 'discord-bot-jlg'),
                'label_dnd'            => __('Ne pas déranger', 'discord-bot-jlg'),
                'label_offline'        => __('Hors ligne', 'discord-bot-jlg'),
                'label_voice'          => __('En vocal', 'discord-bot-jlg'),
                'label_boosts'         => __('Boosts', 'discord-bot-jlg'),
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
            ),
            $atts,
            'discord_stats'
        );

        $show_online        = filter_var($atts['show_online'], FILTER_VALIDATE_BOOLEAN);
        $show_total         = filter_var($atts['show_total'], FILTER_VALIDATE_BOOLEAN);
        $show_idle          = filter_var($atts['show_idle'], FILTER_VALIDATE_BOOLEAN);
        $show_dnd           = filter_var($atts['show_dnd'], FILTER_VALIDATE_BOOLEAN);
        $show_offline       = filter_var($atts['show_offline'], FILTER_VALIDATE_BOOLEAN);
        $show_voice         = filter_var($atts['show_voice'], FILTER_VALIDATE_BOOLEAN);
        $show_boosts        = filter_var($atts['show_boosts'], FILTER_VALIDATE_BOOLEAN);
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

        if ($force_demo) {
            $stats = $this->api->get_demo_stats();
        } else {
            $stats = $this->api->get_stats();
        }

        if (!is_array($stats)) {
            return sprintf(
                '<div class="discord-stats-error">%s</div>',
                esc_html__('Impossible de récupérer les stats Discord', 'discord-bot-jlg')
            );
        }

        $status_counts = array(
            'online'  => 0,
            'idle'    => 0,
            'dnd'     => 0,
            'offline' => 0,
            'unknown' => 0,
        );

        if (isset($stats['status_counts']) && is_array($stats['status_counts'])) {
            foreach ($stats['status_counts'] as $key => $value) {
                if (array_key_exists($key, $status_counts)) {
                    $status_counts[$key] = max(0, (int) $value);
                }
            }
        }

        $idle_count    = max(0, isset($status_counts['idle']) ? (int) $status_counts['idle'] : 0);
        $dnd_count     = max(0, isset($status_counts['dnd']) ? (int) $status_counts['dnd'] : 0);
        $offline_count = max(0, isset($stats['offline_count']) ? (int) $stats['offline_count'] : (isset($status_counts['offline']) ? (int) $status_counts['offline'] : 0));
        $offline_is_approximate = !empty($stats['offline_is_approximate']);

        $voice_stats = array(
            'participants' => 0,
            'channels'     => 0,
        );

        if (isset($stats['voice_stats']) && is_array($stats['voice_stats'])) {
            $voice_stats['participants'] = max(0, isset($stats['voice_stats']['participants']) ? (int) $stats['voice_stats']['participants'] : 0);
            $voice_stats['channels']     = max(0, isset($stats['voice_stats']['channels']) ? (int) $stats['voice_stats']['channels'] : 0);
        }

        $voice_participants     = max(0, isset($stats['voice_participants']) ? (int) $stats['voice_participants'] : $voice_stats['participants']);
        $voice_channels_active  = max(0, isset($stats['voice_channels_active']) ? (int) $stats['voice_channels_active'] : $voice_stats['channels']);
        $boost_count            = max(0, isset($stats['boost_count']) ? (int) $stats['boost_count'] : 0);
        $voice_extra_singular   = __('%d salon actif', 'discord-bot-jlg');
        $voice_extra_plural     = __('%d salons actifs', 'discord-bot-jlg');
        $voice_extra_text       = '';

        if ($voice_channels_active > 0) {
            $voice_extra_text = sprintf(
                _n('%d salon actif', '%d salons actifs', $voice_channels_active, 'discord-bot-jlg'),
                $voice_channels_active
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

        $attributes = array(
            sprintf('id="%s"', esc_attr($unique_id)),
            sprintf('class="%s"', esc_attr(implode(' ', $container_classes))),
            sprintf('data-demo="%s"', esc_attr($is_forced_demo ? 'true' : 'false')),
            sprintf('data-fallback-demo="%s"', esc_attr($is_fallback_demo ? 'true' : 'false')),
            sprintf('data-stale="%s"', esc_attr($is_stale ? 'true' : 'false')),
            sprintf('data-hide-labels="%s"', esc_attr($hide_labels ? 'true' : 'false')),
        );

        if ($is_stale && $last_updated > 0) {
            $attributes[] = sprintf('data-last-updated="%s"', esc_attr($last_updated));
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
                $date_format = trim(get_option('date_format'));
                $time_format = trim(get_option('time_format'));
                $combined_format = trim($date_format . ' ' . $time_format);

                if ('' === $combined_format) {
                    $combined_format = 'F j, Y H:i';
                }

                if (function_exists('wp_date')) {
                    $formatted_timestamp = wp_date($combined_format, $last_updated);
                } else {
                    $formatted_timestamp = date_i18n($combined_format, $last_updated);
                }

                $template = __('Données mises en cache du %s', 'discord-bot-jlg');

                if (false !== strpos($template, '%s')) {
                    $stale_notice_text = sprintf($template, $formatted_timestamp);
                } else {
                    $stale_notice_text = trim($template . ' ' . $formatted_timestamp);
                }
            }
        }

        $discord_svg = '<svg class="discord-logo-svg" aria-hidden="true" focusable="false" viewBox="0 0 127.14 96.36" xmlns="http://www.w3.org/2000/svg"><path d="M107.7,8.07A105.15,105.15,0,0,0,81.47,0a72.06,72.06,0,0,0-3.36,6.83A97.68,97.68,0,0,0,49,6.83,72.37,72.37,0,0,0,45.64,0,105.89,105.89,0,0,0,19.39,8.09C2.79,32.65-1.71,56.6.54,80.21h0A105.73,105.73,0,0,0,32.71,96.36,77.7,77.7,0,0,0,39.6,85.25a68.42,68.42,0,0,1-10.85-5.18c.91-.66,1.8-1.34,2.66-2a75.57,75.57,0,0,0,64.32,0c.87.71,1.76,1.39,2.66,2a68.68,68.68,0,0,1-10.87,5.19,77,77,0,0,0,6.89,11.1A105.25,105.25,0,0,0,126.6,80.22h0C129.24,52.84,122.09,29.11,107.7,8.07ZM42.45,65.69C36.18,65.69,31,60,31,53s5-12.74,11.43-12.74S54,46,53.89,53,48.84,65.69,42.45,65.69Zm42.24,0C78.41,65.69,73.25,60,73.25,53s5-12.74,11.44-12.74S96.23,46,96.12,53,91.08,65.69,84.69,65.69Z"/></svg>';

        ob_start();
        ?>
        <div <?php echo implode(' ', $attributes); ?>>

            <?php if (!empty($stats['is_demo'])): ?>
            <div class="discord-demo-badge"><?php echo esc_html__('Mode Démo', 'discord-bot-jlg'); ?></div>
            <?php endif; ?>

            <?php if ($is_stale && '' !== $stale_notice_text): ?>
            <div class="discord-stale-notice"><?php echo esc_html($stale_notice_text); ?></div>
            <?php endif; ?>

            <?php if ($show_title): ?>
            <div class="discord-stats-title"><?php echo esc_html($atts['title']); ?></div>
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
                            <span class="discord-server-name__text"><?php echo esc_html($server_name); ?></span>
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
                    <div class="discord-stat discord-online"
                        data-value="<?php echo esc_attr((int) $stats['online']); ?>"
                        data-label-online="<?php echo esc_attr($atts['label_online']); ?>"
                        data-hide-labels="<?php echo esc_attr($hide_labels ? 'true' : 'false'); ?>">
                        <?php if (!$hide_icons): ?>
                        <span class="discord-icon"><?php echo esc_html($atts['icon_online']); ?></span>
                        <?php endif; ?>
                        <span class="discord-number" role="status" aria-live="polite"><?php echo esc_html(number_format_i18n((int) $stats['online'])); ?></span>
                        <span class="<?php echo esc_attr(implode(' ', $online_label_classes)); ?>">
                            <span class="discord-label-text"><?php echo esc_html($atts['label_online']); ?></span>
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
                        <span class="discord-number" role="status" aria-live="polite"><?php echo $has_total ? esc_html(number_format_i18n((int) $stats['total'])) : '&mdash;'; ?></span>
                        <span class="discord-approx-indicator" aria-hidden="true"<?php echo $total_is_approximate ? '' : ' hidden'; ?>>≈</span>
                        <span class="<?php echo esc_attr(implode(' ', $label_classes)); ?>">
                            <span class="discord-label-text"><?php echo esc_html($has_total ? $atts['label_total'] : $label_unavailable); ?></span>
                            <span class="discord-label-extra screen-reader-text"><?php echo $total_is_approximate ? esc_html__('approx.', 'discord-bot-jlg') : ''; ?></span>
                        </span>
                    </div>
                    <?php endif; ?>

                    <?php if ($show_idle) : ?>
                    <?php
                    $idle_label_classes = array('discord-label');
                    if ($hide_labels) {
                        $idle_label_classes[] = 'screen-reader-text';
                    }
                    ?>
                    <div class="discord-stat discord-idle"
                        data-value="<?php echo esc_attr($idle_count); ?>"
                        data-label-idle="<?php echo esc_attr($atts['label_idle']); ?>"
                        data-hide-labels="<?php echo esc_attr($hide_labels ? 'true' : 'false'); ?>">
                        <?php if (!$hide_icons): ?>
                        <span class="discord-icon"><?php echo esc_html($atts['icon_idle']); ?></span>
                        <?php endif; ?>
                        <span class="discord-number" role="status" aria-live="polite"><?php echo esc_html(number_format_i18n($idle_count)); ?></span>
                        <span class="<?php echo esc_attr(implode(' ', $idle_label_classes)); ?>">
                            <span class="discord-label-text"><?php echo esc_html($atts['label_idle']); ?></span>
                        </span>
                    </div>
                    <?php endif; ?>

                    <?php if ($show_dnd) : ?>
                    <?php
                    $dnd_label_classes = array('discord-label');
                    if ($hide_labels) {
                        $dnd_label_classes[] = 'screen-reader-text';
                    }
                    ?>
                    <div class="discord-stat discord-dnd"
                        data-value="<?php echo esc_attr($dnd_count); ?>"
                        data-label-dnd="<?php echo esc_attr($atts['label_dnd']); ?>"
                        data-hide-labels="<?php echo esc_attr($hide_labels ? 'true' : 'false'); ?>">
                        <?php if (!$hide_icons): ?>
                        <span class="discord-icon"><?php echo esc_html($atts['icon_dnd']); ?></span>
                        <?php endif; ?>
                        <span class="discord-number" role="status" aria-live="polite"><?php echo esc_html(number_format_i18n($dnd_count)); ?></span>
                        <span class="<?php echo esc_attr(implode(' ', $dnd_label_classes)); ?>">
                            <span class="discord-label-text"><?php echo esc_html($atts['label_dnd']); ?></span>
                        </span>
                    </div>
                    <?php endif; ?>

                    <?php if ($show_offline) : ?>
                    <?php
                    $offline_label_classes = array('discord-label');
                    if ($hide_labels) {
                        $offline_label_classes[] = 'screen-reader-text';
                    }
                    ?>
                    <div class="discord-stat discord-offline<?php echo $offline_is_approximate ? ' discord-offline-approximate' : ''; ?>"
                        data-value="<?php echo esc_attr($offline_count); ?>"
                        data-label-offline="<?php echo esc_attr($atts['label_offline']); ?>"
                        data-label-approx="<?php echo esc_attr__('approx.', 'discord-bot-jlg'); ?>"
                        data-approximate="<?php echo esc_attr($offline_is_approximate ? 'true' : 'false'); ?>"
                        data-hide-labels="<?php echo esc_attr($hide_labels ? 'true' : 'false'); ?>">
                        <?php if (!$hide_icons): ?>
                        <span class="discord-icon"><?php echo esc_html($atts['icon_offline']); ?></span>
                        <?php endif; ?>
                        <span class="discord-number" role="status" aria-live="polite"><?php echo esc_html(number_format_i18n($offline_count)); ?></span>
                        <span class="discord-approx-indicator" aria-hidden="true"<?php echo $offline_is_approximate ? '' : ' hidden'; ?>>≈</span>
                        <span class="<?php echo esc_attr(implode(' ', $offline_label_classes)); ?>">
                            <span class="discord-label-text"><?php echo esc_html($atts['label_offline']); ?></span>
                            <span class="discord-label-extra screen-reader-text"><?php echo $offline_is_approximate ? esc_html__('approx.', 'discord-bot-jlg') : ''; ?></span>
                        </span>
                    </div>
                    <?php endif; ?>

                    <?php if ($show_voice) : ?>
                    <?php
                    $voice_label_classes = array('discord-label');
                    if ($hide_labels) {
                        $voice_label_classes[] = 'screen-reader-text';
                    }
                    ?>
                    <div class="discord-stat discord-voice"
                        data-value="<?php echo esc_attr($voice_participants); ?>"
                        data-label-voice="<?php echo esc_attr($atts['label_voice']); ?>"
                        data-voice-channels="<?php echo esc_attr($voice_channels_active); ?>"
                        data-label-voice-extra-singular="<?php echo esc_attr($voice_extra_singular); ?>"
                        data-label-voice-extra-plural="<?php echo esc_attr($voice_extra_plural); ?>"
                        data-hide-labels="<?php echo esc_attr($hide_labels ? 'true' : 'false'); ?>">
                        <?php if (!$hide_icons): ?>
                        <span class="discord-icon"><?php echo esc_html($atts['icon_voice']); ?></span>
                        <?php endif; ?>
                        <span class="discord-number" role="status" aria-live="polite"><?php echo esc_html(number_format_i18n($voice_participants)); ?></span>
                        <span class="<?php echo esc_attr(implode(' ', $voice_label_classes)); ?>">
                            <span class="discord-label-text"><?php echo esc_html($atts['label_voice']); ?></span>
                            <span class="discord-label-extra screen-reader-text" data-role="discord-voice-extra"><?php echo esc_html($voice_extra_text); ?></span>
                        </span>
                    </div>
                    <?php endif; ?>

                    <?php if ($show_boosts) : ?>
                    <?php
                    $boost_label_classes = array('discord-label');
                    if ($hide_labels) {
                        $boost_label_classes[] = 'screen-reader-text';
                    }
                    ?>
                    <div class="discord-stat discord-boosts"
                        data-value="<?php echo esc_attr($boost_count); ?>"
                        data-label-boosts="<?php echo esc_attr($atts['label_boosts']); ?>"
                        data-hide-labels="<?php echo esc_attr($hide_labels ? 'true' : 'false'); ?>">
                        <?php if (!$hide_icons): ?>
                        <span class="discord-icon"><?php echo esc_html($atts['icon_boosts']); ?></span>
                        <?php endif; ?>
                        <span class="discord-number" role="status" aria-live="polite"><?php echo esc_html(number_format_i18n($boost_count)); ?></span>
                        <span class="<?php echo esc_attr(implode(' ', $boost_label_classes)); ?>">
                            <span class="discord-label-text"><?php echo esc_html($atts['label_boosts']); ?></span>
                        </span>
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

        wp_register_script(
            'discord-bot-jlg-frontend',
            DISCORD_BOT_JLG_PLUGIN_URL . 'assets/js/discord-bot-jlg.js',
            array('wp-polyfill', 'wp-api-fetch'),
            DISCORD_BOT_JLG_VERSION,
            true
        );

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
