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
            __('Discord Bot - JLG', 'discord-bot-jlg'),
            array('description' => esc_html__('Affiche les statistiques de votre serveur Discord', 'discord-bot-jlg'))
        );
    }

    public function widget($args, $instance) {
        echo $args['before_widget'];

        $options = get_option(DISCORD_BOT_JLG_OPTION_NAME);
        $title   = !empty($options['widget_title']) ? $options['widget_title'] : esc_html__('Discord Server', 'discord-bot-jlg');

        if (!empty($title)) {
            echo $args['before_title'] . apply_filters('widget_title', $title) . $args['after_title'];
        }

        echo do_shortcode('[discord_stats]');

        echo $args['after_widget'];
    }

    public function form($instance) {
        ?>
        <p>
            <?php
            echo wp_kses_post(
                sprintf(
                    /* translators: %s: Admin menu label. */
                    __('Configurez les options dans le menu principal <a href="%s">Discord Bot</a>', 'discord-bot-jlg'),
                    esc_url(admin_url('admin.php?page=discord-bot-jlg'))
                )
            );
            ?>
        </p>
        <?php
    }
}
