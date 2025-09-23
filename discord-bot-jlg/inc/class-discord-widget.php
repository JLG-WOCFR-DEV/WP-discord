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

        $options = get_option(DISCORD_BOT_JLG_OPTION_NAME);
        $title   = !empty($options['widget_title']) ? $options['widget_title'] : __('Discord Server', 'discord-bot-jlg');

        if (!empty($title)) {
            $filtered_title = apply_filters('widget_title', $title);
            echo $args['before_title'] . esc_html($filtered_title) . $args['after_title'];
        }

        echo do_shortcode('[discord_stats]');

        echo $args['after_widget'];
    }

    public function form($instance) {
        ?>
        <p>
            <?php echo esc_html__('Configurez les options dans le menu principal', 'discord-bot-jlg'); ?>
            <a href="<?php echo esc_url(admin_url('admin.php?page=discord-bot-jlg')); ?>">
                <?php echo esc_html__('Discord Bot', 'discord-bot-jlg'); ?>
            </a>
        </p>
        <?php
    }
}
