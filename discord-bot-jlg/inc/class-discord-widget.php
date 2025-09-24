<?php

if (!defined('ABSPATH')) {
    exit;
}

class Discord_Bot_JLG_Widget {

    public function register_widget() {
        register_widget('Discord_Stats_Widget');
    }
}

class Discord_Stats_Widget extends WP_Widget {

    public function __construct() {
        parent::__construct(
            'discord_stats_widget',
            esc_html__('Discord Bot - JLG', 'discord-bot-jlg'),
            array('description' => esc_html__('Affiche les statistiques de votre serveur Discord', 'discord-bot-jlg'))
        );
    }

    public function widget($args, $instance) {
        echo $args['before_widget'];

        $instance = wp_parse_args($instance, $this->get_default_instance());

        $title = !empty($instance['title']) ? $instance['title'] : '';

        if (!empty($title)) {
            $filtered_title = apply_filters('widget_title', $title, $instance, $this->id_base);
            echo $args['before_title'] . esc_html($filtered_title) . $args['after_title'];
        }

        $allowed_layouts   = array('horizontal', 'vertical');
        $allowed_positions = array('left', 'right', 'top');
        $allowed_themes    = array('discord', 'dark', 'light');

        $layout = sanitize_key($instance['layout']);
        if (!in_array($layout, $allowed_layouts, true)) {
            $layout = 'horizontal';
        }

        $icon_position = sanitize_key($instance['discord_icon_position']);
        if (!in_array($icon_position, $allowed_positions, true)) {
            $icon_position = 'left';
        }

        $theme = sanitize_key($instance['theme']);
        if (!in_array($theme, $allowed_themes, true)) {
            $theme = 'discord';
        }

        $min_refresh = defined('Discord_Bot_JLG_API::MIN_PUBLIC_REFRESH_INTERVAL')
            ? Discord_Bot_JLG_API::MIN_PUBLIC_REFRESH_INTERVAL
            : 10;

        $refresh_interval = isset($instance['refresh_interval']) ? (int) $instance['refresh_interval'] : $min_refresh;
        if ($refresh_interval < $min_refresh) {
            $refresh_interval = $min_refresh;
        }

        $card_title = !empty($instance['card_title']) ? $instance['card_title'] : $title;

        $shortcode_atts = array(
            'layout'               => $layout,
            'show_online'          => !empty($instance['show_online']) ? 'true' : 'false',
            'show_total'           => !empty($instance['show_total']) ? 'true' : 'false',
            'compact'              => !empty($instance['compact']) ? 'true' : 'false',
            'hide_labels'          => !empty($instance['hide_labels']) ? 'true' : 'false',
            'hide_icons'           => !empty($instance['hide_icons']) ? 'true' : 'false',
            'show_discord_icon'    => !empty($instance['show_discord_icon']) ? 'true' : 'false',
            'discord_icon_position'=> $icon_position,
            'theme'                => $theme,
            'refresh'              => !empty($instance['refresh']) ? 'true' : 'false',
            'show_title'           => !empty($instance['show_card_title']) ? 'true' : 'false',
        );

        if (!empty($instance['refresh'])) {
            $shortcode_atts['refresh_interval'] = (string) $refresh_interval;
        }

        if (!empty($instance['show_card_title'])) {
            $shortcode_atts['title'] = $card_title;
        }

        $attr_parts = array();
        foreach ($shortcode_atts as $key => $value) {
            if ('' === $value) {
                continue;
            }

            $attr_parts[] = sprintf('%s="%s"', esc_attr($key), esc_attr($value));
        }

        $shortcode = '[discord_stats';
        if (!empty($attr_parts)) {
            $shortcode .= ' ' . implode(' ', $attr_parts);
        }
        $shortcode .= ']';

        echo do_shortcode($shortcode);

        echo $args['after_widget'];
    }

    public function update($new_instance, $old_instance) {
        $instance = $this->get_default_instance();

        $instance['title'] = isset($new_instance['title']) ? sanitize_text_field($new_instance['title']) : '';

        $layout = isset($new_instance['layout']) ? sanitize_key($new_instance['layout']) : 'horizontal';
        $instance['layout'] = in_array($layout, array('horizontal', 'vertical'), true) ? $layout : 'horizontal';

        $instance['show_online'] = !empty($new_instance['show_online']) ? 1 : 0;
        $instance['show_total']  = !empty($new_instance['show_total']) ? 1 : 0;
        $instance['compact']     = !empty($new_instance['compact']) ? 1 : 0;
        $instance['hide_labels'] = !empty($new_instance['hide_labels']) ? 1 : 0;
        $instance['hide_icons']  = !empty($new_instance['hide_icons']) ? 1 : 0;

        $instance['show_discord_icon'] = !empty($new_instance['show_discord_icon']) ? 1 : 0;
        $icon_position = isset($new_instance['discord_icon_position']) ? sanitize_key($new_instance['discord_icon_position']) : 'left';
        $instance['discord_icon_position'] = in_array($icon_position, array('left', 'right', 'top'), true) ? $icon_position : 'left';

        $theme = isset($new_instance['theme']) ? sanitize_key($new_instance['theme']) : 'discord';
        $instance['theme'] = in_array($theme, array('discord', 'dark', 'light'), true) ? $theme : 'discord';

        $instance['refresh'] = !empty($new_instance['refresh']) ? 1 : 0;
        $min_refresh = defined('Discord_Bot_JLG_API::MIN_PUBLIC_REFRESH_INTERVAL')
            ? Discord_Bot_JLG_API::MIN_PUBLIC_REFRESH_INTERVAL
            : 10;
        $interval = isset($new_instance['refresh_interval']) ? absint($new_instance['refresh_interval']) : $min_refresh;
        if ($interval < $min_refresh) {
            $interval = $min_refresh;
        }
        $instance['refresh_interval'] = $interval;

        $instance['show_card_title'] = !empty($new_instance['show_card_title']) ? 1 : 0;
        $instance['card_title']      = isset($new_instance['card_title']) ? sanitize_text_field($new_instance['card_title']) : '';

        return $instance;
    }

    public function form($instance) {
        $instance = wp_parse_args($instance, $this->get_default_instance());

        $min_refresh = defined('Discord_Bot_JLG_API::MIN_PUBLIC_REFRESH_INTERVAL')
            ? Discord_Bot_JLG_API::MIN_PUBLIC_REFRESH_INTERVAL
            : 10;

        ?>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('title')); ?>"><?php esc_html_e('Titre du widget', 'discord-bot-jlg'); ?></label>
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('title')); ?>"
                   name="<?php echo esc_attr($this->get_field_name('title')); ?>" type="text"
                   value="<?php echo esc_attr($instance['title']); ?>" />
        </p>

        <p>
            <label for="<?php echo esc_attr($this->get_field_id('layout')); ?>"><?php esc_html_e('Disposition', 'discord-bot-jlg'); ?></label>
            <select class="widefat" id="<?php echo esc_attr($this->get_field_id('layout')); ?>"
                    name="<?php echo esc_attr($this->get_field_name('layout')); ?>">
                <option value="horizontal" <?php selected($instance['layout'], 'horizontal'); ?>><?php esc_html_e('Horizontale', 'discord-bot-jlg'); ?></option>
                <option value="vertical" <?php selected($instance['layout'], 'vertical'); ?>><?php esc_html_e('Verticale', 'discord-bot-jlg'); ?></option>
            </select>
        </p>

        <p>
            <input type="checkbox" id="<?php echo esc_attr($this->get_field_id('show_online')); ?>"
                   name="<?php echo esc_attr($this->get_field_name('show_online')); ?>" value="1" <?php checked($instance['show_online'], 1); ?> />
            <label for="<?php echo esc_attr($this->get_field_id('show_online')); ?>"><?php esc_html_e('Afficher les membres en ligne', 'discord-bot-jlg'); ?></label>
        </p>

        <p>
            <input type="checkbox" id="<?php echo esc_attr($this->get_field_id('show_total')); ?>"
                   name="<?php echo esc_attr($this->get_field_name('show_total')); ?>" value="1" <?php checked($instance['show_total'], 1); ?> />
            <label for="<?php echo esc_attr($this->get_field_id('show_total')); ?>"><?php esc_html_e('Afficher le total des membres', 'discord-bot-jlg'); ?></label>
        </p>

        <p>
            <input type="checkbox" id="<?php echo esc_attr($this->get_field_id('compact')); ?>"
                   name="<?php echo esc_attr($this->get_field_name('compact')); ?>" value="1" <?php checked($instance['compact'], 1); ?> />
            <label for="<?php echo esc_attr($this->get_field_id('compact')); ?>"><?php esc_html_e('Activer le mode compact', 'discord-bot-jlg'); ?></label>
        </p>

        <p>
            <input type="checkbox" id="<?php echo esc_attr($this->get_field_id('hide_labels')); ?>"
                   name="<?php echo esc_attr($this->get_field_name('hide_labels')); ?>" value="1" <?php checked($instance['hide_labels'], 1); ?> />
            <label for="<?php echo esc_attr($this->get_field_id('hide_labels')); ?>"><?php esc_html_e('Masquer les libellés', 'discord-bot-jlg'); ?></label>
        </p>

        <p>
            <input type="checkbox" id="<?php echo esc_attr($this->get_field_id('hide_icons')); ?>"
                   name="<?php echo esc_attr($this->get_field_name('hide_icons')); ?>" value="1" <?php checked($instance['hide_icons'], 1); ?> />
            <label for="<?php echo esc_attr($this->get_field_id('hide_icons')); ?>"><?php esc_html_e('Masquer les icônes', 'discord-bot-jlg'); ?></label>
        </p>

        <p>
            <input type="checkbox" id="<?php echo esc_attr($this->get_field_id('show_discord_icon')); ?>"
                   name="<?php echo esc_attr($this->get_field_name('show_discord_icon')); ?>" value="1" <?php checked($instance['show_discord_icon'], 1); ?> />
            <label for="<?php echo esc_attr($this->get_field_id('show_discord_icon')); ?>"><?php esc_html_e('Afficher le logo Discord', 'discord-bot-jlg'); ?></label>
        </p>

        <p>
            <label for="<?php echo esc_attr($this->get_field_id('discord_icon_position')); ?>"><?php esc_html_e('Position du logo', 'discord-bot-jlg'); ?></label>
            <select class="widefat" id="<?php echo esc_attr($this->get_field_id('discord_icon_position')); ?>"
                    name="<?php echo esc_attr($this->get_field_name('discord_icon_position')); ?>">
                <option value="left" <?php selected($instance['discord_icon_position'], 'left'); ?>><?php esc_html_e('À gauche', 'discord-bot-jlg'); ?></option>
                <option value="right" <?php selected($instance['discord_icon_position'], 'right'); ?>><?php esc_html_e('À droite', 'discord-bot-jlg'); ?></option>
                <option value="top" <?php selected($instance['discord_icon_position'], 'top'); ?>><?php esc_html_e('Au-dessus', 'discord-bot-jlg'); ?></option>
            </select>
        </p>

        <p>
            <label for="<?php echo esc_attr($this->get_field_id('theme')); ?>"><?php esc_html_e('Thème visuel', 'discord-bot-jlg'); ?></label>
            <select class="widefat" id="<?php echo esc_attr($this->get_field_id('theme')); ?>"
                    name="<?php echo esc_attr($this->get_field_name('theme')); ?>">
                <option value="discord" <?php selected($instance['theme'], 'discord'); ?>><?php esc_html_e('Discord', 'discord-bot-jlg'); ?></option>
                <option value="dark" <?php selected($instance['theme'], 'dark'); ?>><?php esc_html_e('Sombre', 'discord-bot-jlg'); ?></option>
                <option value="light" <?php selected($instance['theme'], 'light'); ?>><?php esc_html_e('Clair', 'discord-bot-jlg'); ?></option>
            </select>
        </p>

        <p>
            <input type="checkbox" id="<?php echo esc_attr($this->get_field_id('refresh')); ?>"
                   name="<?php echo esc_attr($this->get_field_name('refresh')); ?>" value="1" <?php checked($instance['refresh'], 1); ?> />
            <label for="<?php echo esc_attr($this->get_field_id('refresh')); ?>"><?php esc_html_e('Actualisation automatique', 'discord-bot-jlg'); ?></label>
        </p>

        <p>
            <label for="<?php echo esc_attr($this->get_field_id('refresh_interval')); ?>"><?php esc_html_e('Intervalle d\'actualisation (secondes)', 'discord-bot-jlg'); ?></label>
            <input class="small-text" type="number" min="<?php echo esc_attr($min_refresh); ?>" step="1"
                   id="<?php echo esc_attr($this->get_field_id('refresh_interval')); ?>"
                   name="<?php echo esc_attr($this->get_field_name('refresh_interval')); ?>"
                   value="<?php echo esc_attr($instance['refresh_interval']); ?>" />
        </p>

        <p>
            <input type="checkbox" id="<?php echo esc_attr($this->get_field_id('show_card_title')); ?>"
                   name="<?php echo esc_attr($this->get_field_name('show_card_title')); ?>" value="1" <?php checked($instance['show_card_title'], 1); ?> />
            <label for="<?php echo esc_attr($this->get_field_id('show_card_title')); ?>"><?php esc_html_e('Afficher un titre dans la carte', 'discord-bot-jlg'); ?></label>
        </p>

        <p>
            <label for="<?php echo esc_attr($this->get_field_id('card_title')); ?>"><?php esc_html_e('Titre de la carte', 'discord-bot-jlg'); ?></label>
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('card_title')); ?>"
                   name="<?php echo esc_attr($this->get_field_name('card_title')); ?>" type="text"
                   value="<?php echo esc_attr($instance['card_title']); ?>" />
        </p>
        <?php
    }

    private function get_default_instance() {
        $options = get_option(DISCORD_BOT_JLG_OPTION_NAME);
        if (!is_array($options)) {
            $options = array();
        }

        $default_title = isset($options['widget_title']) ? $options['widget_title'] : __('Discord Server', 'discord-bot-jlg');

        $min_refresh = defined('Discord_Bot_JLG_API::MIN_PUBLIC_REFRESH_INTERVAL')
            ? Discord_Bot_JLG_API::MIN_PUBLIC_REFRESH_INTERVAL
            : 10;

        $cache_duration = isset($options['cache_duration']) ? (int) $options['cache_duration'] : 60;
        if ($cache_duration < $min_refresh) {
            $cache_duration = $min_refresh;
        }

        return array(
            'title'                => $default_title,
            'layout'               => 'horizontal',
            'show_online'          => !empty($options['show_online']) ? 1 : 0,
            'show_total'           => !empty($options['show_total']) ? 1 : 0,
            'compact'              => 0,
            'hide_labels'          => 0,
            'hide_icons'           => 0,
            'show_discord_icon'    => 0,
            'discord_icon_position'=> 'left',
            'theme'                => 'discord',
            'refresh'              => 0,
            'refresh_interval'     => $cache_duration,
            'show_card_title'      => 0,
            'card_title'           => '',
        );
    }
}
