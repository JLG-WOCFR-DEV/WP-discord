<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Discord_Stats_Widget extends WP_Widget {

    public function __construct() {
        parent::__construct(
            'discord_stats_widget',
            'Discord Bot - JLG',
            array('description' => 'Affiche les statistiques de votre serveur Discord')
        );
    }

    public function widget($args, $instance) {
        echo $args['before_widget'];

        $options = get_option('discord_server_stats_options');
        $title = !empty($options['widget_title']) ? $options['widget_title'] : 'Discord Server';

        if (!empty($title)) {
            echo $args['before_title'] . apply_filters('widget_title', $title) . $args['after_title'];
        }

        echo do_shortcode('[discord_stats]');

        echo $args['after_widget'];
    }

    public function form($instance) {
        ?>
        <p>
            Configurez les options dans le menu principal <a href="<?php echo esc_url(admin_url('admin.php?page=discord-bot-jlg')); ?>">Discord Bot</a>
        </p>
        <?php
    }
}
