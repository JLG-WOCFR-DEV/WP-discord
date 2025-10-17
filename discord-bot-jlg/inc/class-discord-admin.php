<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Gère l'intégration du plugin dans l'administration WordPress (menus, pages, formulaires et assets).
 */
class Discord_Bot_JLG_Admin {

    const SECRET_ROTATION_MAX_AGE_DAYS = 90;
    const ALERT_WEBHOOK_SECRET_MAX_LENGTH = 128;

    private $option_name;
    private $api;
    private $demo_page_hook_suffix;
    private $forced_setup_step;
    private $event_logger;
    private $api_key_repository;
    private $last_generated_api_key;
    private $last_generated_api_key_label;

    /**
     * Initialise l'instance avec la clé d'option et le client API utilisé pour les vérifications.
     *
     * @param string              $option_name Nom de l'option stockant la configuration du plugin.
     * @param Discord_Bot_JLG_API $api         Service d'accès aux statistiques Discord.
     *
     * @return void
     */
    public function __construct($option_name, Discord_Bot_JLG_API $api, $event_logger = null) {
        $this->option_name = $option_name;
        $this->api         = $api;
        $this->demo_page_hook_suffix = '';
        $this->forced_setup_step    = '';
        $this->event_logger = ($event_logger instanceof Discord_Bot_JLG_Event_Logger)
            ? $event_logger
            : $this->api->get_event_logger();
        $this->api_key_repository = $this->api->get_api_key_repository();
        $this->last_generated_api_key = '';
        $this->last_generated_api_key_label = '';

        add_action('admin_post_discord_bot_jlg_export_log', array($this, 'handle_monitoring_export'));
        add_action('admin_notices', array($this, 'maybe_display_secret_rotation_notice'));
    }

    /**
     * Enregistre le menu principal et les sous-menus du plugin dans l'administration WordPress.
     *
     * @return void
     */
    public function add_admin_menu() {
        $discord_icon = 'data:image/svg+xml;base64,PHN2ZyB2aWV3Qm94PSIwIDAgMjQgMjQiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PHBhdGggZmlsbD0iI2E0YWFiOCIgZD0iTTIwLjMxNyA0LjM3YTE5LjggMTkuOCAwIDAwLTQuODg1LTEuNTE1LjA3NC4wNzQgMCAwMC0uMDc5LjAzN2MtLjIxLjM3NS0uNDQ0Ljg2NC0uNjA4IDEuMjVhMTguMjcgMTguMjcgMCAwMC01LjQ4NyAwYy0uMTY1LS4zOTctLjQwNC0uODg1LS42MTgtMS4yNWEuMDc3LjA3NyAwIDAwLS4wNzktLjAzN0ExOS43NCAxOS43NCAwIDAwMy42NzcgNC4zN2EuMDcuMDcgMCAwMC0uMDMyLjAyN0MuNTMzIDkuMDQ2LS4zMiAxMy41OC4wOTkgMTguMDU3YS4wOC4wOCAwIDAwLjAzMS4wNTdBMTkuOSAxOS45IDAgMDA2LjA3MyAyMWEuMDc4LjA3OCAwIDAwLjA4NC0uMDI4IDEzLjQgMTMuNCAwIDAwMS4xNTUtMi4xLjA3Ni4wNzYgMCAwMC0uMDQxLS4xMDYgMTMuMSAxMy4xIDAgMDEtMS44NzItLjg5Mi4wNzcuMDc3IDAgMDEtLjAwOC0uMTI4IDE0IDE0IDAgMDAuMzctLjI5Mi4wNzQuMDc0IDAgMDEuMDc3LS4wMWMzLjkyNyAxLjc5MyA4LjE4IDEuNzkzIDEyLjA2IDAgYS4wNzQuMDc0IDAgMDEuMDc4LjAwOS4xMTkuMDk5LjI0Ni4xOTguMzczLjI5MmEuMDc3LjA3NyAwIDAxLS4wMDYuMTI3IDEyLjMgMTIuMyAwIDAxLTEuODczLjg5Mi4wNzcuMDc3IDAgMDAtLjA0MS4xMDdjMy43NDQgMS40MDMgMS4xNTUgMi4xLS4wODQuMDI4YS4wNzguMDc4IDAgMDAxOS45MDItMS45MDMuMDc2LjA3NiAwIDAwLjAzLS4wNTdjLjUzNy00LjU4LS45MDQtOC41NTMtMy44MjMtMTIuMDU3YS4wNi4wNiAwIDAwLS4wMzEtLjAyOHpNOC4wMiAxNS4yNzhjLTEuMTgzIDAtMi4xNTctMS4wODUtMi4xNTctMi40MiAwLTEuMzMzLjk1Ni0yLjQxOSAyLjE1Ny0yLjQxOSAxLjIxIDAgMi4xNzYgMS4wOTYgMi4xNTcgMi40MiAwIDEuMzM0LS45NTYgMi40MTktMi4xNTcgMi40MTl6bTcuOTc1IDBjLTEuMTgzIDAtMi4xNTctMS4wODUtMi4xNTctMi40MiAwLTEuMzMzLjk1NS0yLjQxOSAyLjE1Ny0yLjQxOXMyLjE1NyAxLjA5NiAyLjE1NyAyLjQyYzAgMS4zMzQtLjk1NiAyLjQxOS0yLjE1NyAyLjQxOXoiLz48L3N2Zz4=';

        $manage_settings_cap = Discord_Bot_JLG_Capabilities::get_capability('manage_settings');

        add_menu_page(
            __('Discord Bot - JLG', 'discord-bot-jlg'),
            __('Discord Bot', 'discord-bot-jlg'),
            $manage_settings_cap,
            'discord-bot-jlg',
            array($this, 'options_page'),
            $discord_icon,
            30
        );

        add_submenu_page(
            'discord-bot-jlg',
            __('Configuration', 'discord-bot-jlg'),
            __('Configuration', 'discord-bot-jlg'),
            $manage_settings_cap,
            'discord-bot-jlg',
            array($this, 'options_page')
        );

        $this->demo_page_hook_suffix = add_submenu_page(
            'discord-bot-jlg',
            __('Guide & Démo', 'discord-bot-jlg'),
            __('Guide & Démo', 'discord-bot-jlg'),
            $manage_settings_cap,
            'discord-bot-demo',
            array($this, 'demo_page')
        );
    }

    /**
     * Enregistre les sections, champs et options nécessaires pour la configuration du plugin.
     *
     * @return void
     */
    public function settings_init() {
        register_setting(
            'discord_stats_settings',
            $this->option_name,
            array(
                'sanitize_callback' => array($this, 'sanitize_options'),
            )
        );

        add_settings_section(
            'discord_stats_api_section',
            __('Configuration Discord API', 'discord-bot-jlg'),
            array($this, 'api_section_callback'),
            'discord_stats_settings'
        );

        add_settings_field(
            'server_id',
            __('ID du Serveur Discord', 'discord-bot-jlg'),
            array($this, 'server_id_render'),
            'discord_stats_settings',
            'discord_stats_api_section'
        );

        add_settings_field(
            'bot_token',
            __('Token du Bot Discord', 'discord-bot-jlg'),
            array($this, 'bot_token_render'),
            'discord_stats_settings',
            'discord_stats_api_section'
        );

        add_settings_section(
            'discord_stats_profiles_section',
            __('Profils de serveur', 'discord-bot-jlg'),
            array($this, 'profiles_section_callback'),
            'discord_stats_settings'
        );

        add_settings_field(
            'server_profiles',
            __('Profils enregistrés', 'discord-bot-jlg'),
            array($this, 'server_profiles_render'),
            'discord_stats_settings',
            'discord_stats_profiles_section'
        );

        add_settings_section(
            'discord_stats_display_section',
            esc_html__('Options d\'affichage', 'discord-bot-jlg'),
            array($this, 'display_section_callback'),
            'discord_stats_settings'
        );

        add_settings_field(
            'demo_mode',
            __('Mode démonstration', 'discord-bot-jlg'),
            array($this, 'demo_mode_render'),
            'discord_stats_settings',
            'discord_stats_display_section'
        );

        add_settings_field(
            'show_online',
            __('Afficher les membres en ligne', 'discord-bot-jlg'),
            array($this, 'show_online_render'),
            'discord_stats_settings',
            'discord_stats_display_section'
        );

        add_settings_field(
            'show_total',
            __('Afficher le total des membres', 'discord-bot-jlg'),
            array($this, 'show_total_render'),
            'discord_stats_settings',
            'discord_stats_display_section'
        );

        add_settings_field(
            'show_presence_breakdown',
            __('Afficher la répartition des présences', 'discord-bot-jlg'),
            array($this, 'show_presence_breakdown_render'),
            'discord_stats_settings',
            'discord_stats_display_section'
        );

        add_settings_field(
            'show_approximate_member_count',
            __('Afficher le total approximatif', 'discord-bot-jlg'),
            array($this, 'show_approximate_member_count_render'),
            'discord_stats_settings',
            'discord_stats_display_section'
        );

        add_settings_field(
            'show_premium_subscriptions',
            __('Afficher les boosts Nitro', 'discord-bot-jlg'),
            array($this, 'show_premium_subscriptions_render'),
            'discord_stats_settings',
            'discord_stats_display_section'
        );

        add_settings_field(
            'show_server_name',
            __('Afficher le nom du serveur', 'discord-bot-jlg'),
            array($this, 'show_server_name_render'),
            'discord_stats_settings',
            'discord_stats_display_section'
        );

        add_settings_field(
            'show_server_avatar',
            __('Afficher l\'avatar du serveur', 'discord-bot-jlg'),
            array($this, 'show_server_avatar_render'),
            'discord_stats_settings',
            'discord_stats_display_section'
        );

        add_settings_field(
            'default_stat_icons',
            __('Icônes par défaut', 'discord-bot-jlg'),
            array($this, 'default_stat_icons_render'),
            'discord_stats_settings',
            'discord_stats_display_section'
        );

        add_settings_field(
            'default_stat_labels',
            __('Libellés par défaut', 'discord-bot-jlg'),
            array($this, 'default_stat_labels_render'),
            'discord_stats_settings',
            'discord_stats_display_section'
        );

        add_settings_field(
            'default_theme',
            __('Thème par défaut', 'discord-bot-jlg'),
            array($this, 'default_theme_render'),
            'discord_stats_settings',
            'discord_stats_display_section'
        );

        add_settings_section(
            'discord_stats_cta_section',
            esc_html__('Engagement & appels à l\'action', 'discord-bot-jlg'),
            array($this, 'cta_section_callback'),
            'discord_stats_settings'
        );

        add_settings_field(
            'widget_title',
            __('Titre du widget', 'discord-bot-jlg'),
            array($this, 'widget_title_render'),
            'discord_stats_settings',
            'discord_stats_cta_section'
        );

        add_settings_field(
            'invite_url',
            __('URL d\'invitation Discord', 'discord-bot-jlg'),
            array($this, 'invite_url_render'),
            'discord_stats_settings',
            'discord_stats_cta_section'
        );

        add_settings_field(
            'invite_label',
            __('Libellé du bouton d\'invitation', 'discord-bot-jlg'),
            array($this, 'invite_label_render'),
            'discord_stats_settings',
            'discord_stats_cta_section'
        );

        add_settings_section(
            'discord_stats_automation_section',
            esc_html__('Automatisation & performance', 'discord-bot-jlg'),
            array($this, 'automation_section_callback'),
            'discord_stats_settings'
        );

        add_settings_field(
            'default_refresh_enabled',
            __('Rafraîchissement auto par défaut', 'discord-bot-jlg'),
            array($this, 'default_refresh_enabled_render'),
            'discord_stats_settings',
            'discord_stats_automation_section'
        );

        add_settings_field(
            'default_refresh_interval',
            __('Intervalle d\'auto-rafraîchissement (secondes)', 'discord-bot-jlg'),
            array($this, 'default_refresh_interval_render'),
            'discord_stats_settings',
            'discord_stats_automation_section'
        );

        add_settings_field(
            'cache_duration',
            __('Durée du cache (secondes)', 'discord-bot-jlg'),
            array($this, 'cache_duration_render'),
            'discord_stats_settings',
            'discord_stats_automation_section'
        );

        add_settings_field(
            'analytics_retention_days',
            __('Rétention des analytics (jours)', 'discord-bot-jlg'),
            array($this, 'analytics_retention_render'),
            'discord_stats_settings',
            'discord_stats_automation_section'
        );

        add_settings_section(
            'discord_stats_alerts_section',
            esc_html__('Centre d’alertes analytics', 'discord-bot-jlg'),
            array($this, 'alerts_section_callback'),
            'discord_stats_settings'
        );

        add_settings_field(
            'analytics_alerts_enabled',
            __('Activer les alertes', 'discord-bot-jlg'),
            array($this, 'analytics_alerts_enabled_render'),
            'discord_stats_settings',
            'discord_stats_alerts_section'
        );

        add_settings_field(
            'analytics_alert_drop_percent',
            __('Seuil de baisse (%)', 'discord-bot-jlg'),
            array($this, 'analytics_alert_drop_render'),
            'discord_stats_settings',
            'discord_stats_alerts_section'
        );

        add_settings_field(
            'analytics_alert_recipients',
            __('Destinataires e-mail', 'discord-bot-jlg'),
            array($this, 'analytics_alert_recipients_render'),
            'discord_stats_settings',
            'discord_stats_alerts_section'
        );

        add_settings_field(
            'analytics_alert_webhook',
            __('Webhook Discord/Slack', 'discord-bot-jlg'),
            array($this, 'analytics_alert_webhook_render'),
            'discord_stats_settings',
            'discord_stats_alerts_section'
        );

        add_settings_field(
            'analytics_alert_webhook_secret',
            __('Secret du webhook entrant', 'discord-bot-jlg'),
            array($this, 'analytics_alert_webhook_secret_render'),
            'discord_stats_settings',
            'discord_stats_alerts_section'
        );

        add_settings_field(
            'analytics_alert_cooldown',
            __('Délai minimum entre alertes (minutes)', 'discord-bot-jlg'),
            array($this, 'analytics_alert_cooldown_render'),
            'discord_stats_settings',
            'discord_stats_alerts_section'
        );

        add_settings_section(
            'discord_stats_custom_css_section',
            esc_html__('Personnalisation avancée', 'discord-bot-jlg'),
            array($this, 'custom_css_section_callback'),
            'discord_stats_settings'
        );

        add_settings_field(
            'custom_css',
            __('CSS personnalisé', 'discord-bot-jlg'),
            array($this, 'custom_css_render'),
            'discord_stats_settings',
            'discord_stats_custom_css_section'
        );
    }

    /**
     * Valide et nettoie les options soumises depuis le formulaire d'administration.
     *
     * @param mixed $input Valeurs brutes envoyées par WordPress lors de l'enregistrement des options.
     *
     * @return array Options validées et normalisées prêtes à être stockées.
     */
    public function sanitize_options($input) {
        if (!is_array($input)) {
            $input = array();
        }

        $current_options = get_option($this->option_name);
        if (!is_array($current_options)) {
            $current_options = array();
        }

        $current_theme = 'discord';

        if (
            isset($current_options['default_theme'])
            && discord_bot_jlg_is_allowed_theme($current_options['default_theme'])
        ) {
            $current_theme = $current_options['default_theme'];
        }

        $min_refresh_interval = defined('Discord_Bot_JLG_API::MIN_PUBLIC_REFRESH_INTERVAL')
            ? Discord_Bot_JLG_API::MIN_PUBLIC_REFRESH_INTERVAL
            : 10;
        $max_refresh_interval = 3600;

        $min_cache_duration = max(
            60,
            defined('Discord_Bot_JLG_API::MIN_PUBLIC_REFRESH_INTERVAL')
                ? (int) Discord_Bot_JLG_API::MIN_PUBLIC_REFRESH_INTERVAL
                : 60
        );

        $current_refresh_interval = isset($current_options['default_refresh_interval'])
            ? absint($current_options['default_refresh_interval'])
            : 60;

        if ($current_refresh_interval <= 0) {
            $current_refresh_interval = 60;
        }

        $current_refresh_interval = max(
            $min_refresh_interval,
            min($max_refresh_interval, $current_refresh_interval)
        );

        $existing_colors = array(
            'stat_bg_color'      => isset($current_options['stat_bg_color']) ? discord_bot_jlg_sanitize_color($current_options['stat_bg_color']) : '',
            'stat_text_color'    => isset($current_options['stat_text_color']) ? discord_bot_jlg_sanitize_color($current_options['stat_text_color']) : '',
            'accent_color'       => isset($current_options['accent_color']) ? discord_bot_jlg_sanitize_color($current_options['accent_color']) : '',
            'accent_color_alt'   => isset($current_options['accent_color_alt']) ? discord_bot_jlg_sanitize_color($current_options['accent_color_alt']) : '',
            'accent_text_color'  => isset($current_options['accent_text_color']) ? discord_bot_jlg_sanitize_color($current_options['accent_text_color']) : '',
        );

        $default_retention = defined('DISCORD_BOT_JLG_ANALYTICS_RETENTION_DEFAULT')
            ? (int) DISCORD_BOT_JLG_ANALYTICS_RETENTION_DEFAULT
            : Discord_Bot_JLG_Analytics::DEFAULT_RETENTION_DAYS;

        $current_retention = isset($current_options['analytics_retention_days'])
            ? max(0, (int) $current_options['analytics_retention_days'])
            : $default_retention;

        $default_alert_drop = class_exists('Discord_Bot_JLG_Alerts')
            ? (int) Discord_Bot_JLG_Alerts::DEFAULT_DROP_PERCENT
            : 35;
        $default_alert_cooldown = class_exists('Discord_Bot_JLG_Alerts')
            ? (int) Discord_Bot_JLG_Alerts::DEFAULT_COOLDOWN_MINUTES
            : 60;

        $sanitized = array(
            'server_id'      => '',
            'bot_token'      => isset($current_options['bot_token']) ? $current_options['bot_token'] : '',
            'bot_token_rotated_at' => isset($current_options['bot_token_rotated_at'])
                ? (int) $current_options['bot_token_rotated_at']
                : 0,
            'bot_token_expires_at' => isset($current_options['bot_token_expires_at'])
                ? (int) $current_options['bot_token_expires_at']
                : 0,
            'bot_token_status' => isset($current_options['bot_token_status'])
                ? sanitize_key($current_options['bot_token_status'])
                : 'missing',
            'server_profiles' => isset($current_options['server_profiles']) && is_array($current_options['server_profiles'])
                ? $current_options['server_profiles']
                : array(),
            // Display toggles default to off so the boolean normalization loop can
            // enable them based on the submitted form values.
            'demo_mode'      => 0,
            'show_online'    => 0,
            'show_total'     => 0,
            'show_presence_breakdown'       => 0,
            'show_approximate_member_count' => 0,
            'show_premium_subscriptions'    => 0,
            'show_server_name'   => 0,
            'show_server_avatar' => 0,
            'default_refresh_enabled' => 0,
            'default_theme'   => $current_theme,
            'widget_title'   => '',
            'invite_url'     => isset($current_options['invite_url'])
                ? esc_url_raw($current_options['invite_url'])
                : '',
            'invite_label'   => isset($current_options['invite_label'])
                ? sanitize_text_field($current_options['invite_label'])
                : '',
            'cache_duration' => isset($current_options['cache_duration'])
                ? max(
                    $min_cache_duration,
                    min(3600, (int) $current_options['cache_duration'])
                )
                : 300,
            'custom_css'     => '',
            'default_refresh_interval' => $current_refresh_interval,
            'analytics_retention_days' => $current_retention,
            'analytics_alerts_enabled' => isset($current_options['analytics_alerts_enabled'])
                ? (int) $current_options['analytics_alerts_enabled']
                : 0,
            'analytics_alert_drop_percent' => isset($current_options['analytics_alert_drop_percent'])
                ? max(1, (int) $current_options['analytics_alert_drop_percent'])
                : $default_alert_drop,
            'analytics_alert_recipients' => isset($current_options['analytics_alert_recipients'])
                ? $this->sanitize_alert_recipients_value($current_options['analytics_alert_recipients'])
                : '',
            'analytics_alert_webhook' => isset($current_options['analytics_alert_webhook'])
                ? $this->sanitize_alert_webhook($current_options['analytics_alert_webhook'])
                : '',
            'analytics_alert_webhook_secret' => isset($current_options['analytics_alert_webhook_secret'])
                ? $this->sanitize_alert_webhook_secret($current_options['analytics_alert_webhook_secret'])
                : '',
            'analytics_alert_cooldown' => isset($current_options['analytics_alert_cooldown'])
                ? max(5, (int) $current_options['analytics_alert_cooldown'])
                : $default_alert_cooldown,
            'stat_bg_color'      => $existing_colors['stat_bg_color'],
            'stat_text_color'    => $existing_colors['stat_text_color'],
            'accent_color'       => $existing_colors['accent_color'],
            'accent_color_alt'   => $existing_colors['accent_color_alt'],
            'accent_text_color'  => $existing_colors['accent_text_color'],
            'default_icon_online'      => isset($current_options['default_icon_online'])
                ? sanitize_text_field($current_options['default_icon_online'])
                : '',
            'default_icon_total'       => isset($current_options['default_icon_total'])
                ? sanitize_text_field($current_options['default_icon_total'])
                : '',
            'default_icon_presence'    => isset($current_options['default_icon_presence'])
                ? sanitize_text_field($current_options['default_icon_presence'])
                : '',
            'default_icon_approximate' => isset($current_options['default_icon_approximate'])
                ? sanitize_text_field($current_options['default_icon_approximate'])
                : '',
            'default_icon_premium'     => isset($current_options['default_icon_premium'])
                ? sanitize_text_field($current_options['default_icon_premium'])
                : '',
            'default_label_online'            => isset($current_options['default_label_online'])
                ? sanitize_text_field($current_options['default_label_online'])
                : '',
            'default_label_total'             => isset($current_options['default_label_total'])
                ? sanitize_text_field($current_options['default_label_total'])
                : '',
            'default_label_presence'          => isset($current_options['default_label_presence'])
                ? sanitize_text_field($current_options['default_label_presence'])
                : '',
            'default_label_presence_online'   => isset($current_options['default_label_presence_online'])
                ? sanitize_text_field($current_options['default_label_presence_online'])
                : '',
            'default_label_presence_idle'     => isset($current_options['default_label_presence_idle'])
                ? sanitize_text_field($current_options['default_label_presence_idle'])
                : '',
            'default_label_presence_dnd'      => isset($current_options['default_label_presence_dnd'])
                ? sanitize_text_field($current_options['default_label_presence_dnd'])
                : '',
            'default_label_presence_offline'  => isset($current_options['default_label_presence_offline'])
                ? sanitize_text_field($current_options['default_label_presence_offline'])
                : '',
            'default_label_presence_streaming'=> isset($current_options['default_label_presence_streaming'])
                ? sanitize_text_field($current_options['default_label_presence_streaming'])
                : '',
            'default_label_presence_other'    => isset($current_options['default_label_presence_other'])
                ? sanitize_text_field($current_options['default_label_presence_other'])
                : '',
            'default_label_approximate'       => isset($current_options['default_label_approximate'])
                ? sanitize_text_field($current_options['default_label_approximate'])
                : '',
            'default_label_premium'           => isset($current_options['default_label_premium'])
                ? sanitize_text_field($current_options['default_label_premium'])
                : '',
            'default_label_premium_singular'  => isset($current_options['default_label_premium_singular'])
                ? sanitize_text_field($current_options['default_label_premium_singular'])
                : '',
            'default_label_premium_plural'    => isset($current_options['default_label_premium_plural'])
                ? sanitize_text_field($current_options['default_label_premium_plural'])
                : '',
        );

        if (isset($input['server_id'])) {
            $server_id = sanitize_text_field($input['server_id']);

            if ('' === $server_id) {
                $sanitized['server_id'] = '';
            } elseif (preg_match('/^\d+$/', $server_id)) {
                $sanitized['server_id'] = $server_id;
            }
        }

        $constant_overridden      = (defined('DISCORD_BOT_JLG_TOKEN') && '' !== DISCORD_BOT_JLG_TOKEN);
        $current_timestamp        = current_time('timestamp');
        $skip_token_migration     = false;
        $preserve_existing_secret = false;

        if (!$constant_overridden) {
            $delete_requested = !empty($input['bot_token_delete']);
            $skip_bot_token_migration = false;

            if ($delete_requested) {
                $sanitized['bot_token'] = '';
                $sanitized['bot_token_rotated_at'] = 0;
            } elseif (array_key_exists('bot_token', $input)) {
                $raw_token = trim((string) $input['bot_token']);

                if ('' === $raw_token) {
                    $preserve_existing_secret = true;
                    $sanitized['bot_token'] = isset($current_options['bot_token'])
                        ? $current_options['bot_token']
                        : '';
                    $sanitized['bot_token_rotated_at'] = isset($current_options['bot_token_rotated_at'])
                        ? (int) $current_options['bot_token_rotated_at']
                        : 0;
                    $sanitized['bot_token_expires_at'] = isset($current_options['bot_token_expires_at'])
                        ? (int) $current_options['bot_token_expires_at']
                        : 0;
                    $sanitized['bot_token_status'] = isset($current_options['bot_token_status'])
                        ? sanitize_key($current_options['bot_token_status'])
                        : 'missing';
                    $skip_token_migration = true;
                } elseif ('' !== $raw_token) {
                    $token_to_store = sanitize_text_field($raw_token);
                    $encrypted      = discord_bot_jlg_encrypt_secret($token_to_store);

                    if (is_wp_error($encrypted)) {
                        add_settings_error(
                            'discord_stats_settings',
                            'discord_bot_jlg_token_encrypt_error',
                            $encrypted->get_error_message(),
                            'error'
                        );
                    } else {
                        $sanitized['bot_token'] = $encrypted;
                        $sanitized['bot_token_rotated_at'] = $current_timestamp;
                    }
                } else {
                    $skip_bot_token_migration = true;
                    $sanitized['bot_token'] = isset($current_options['bot_token'])
                        ? $current_options['bot_token']
                        : '';
                    $sanitized['bot_token_rotated_at'] = isset($current_options['bot_token_rotated_at'])
                        ? (int) $current_options['bot_token_rotated_at']
                        : 0;
                    $sanitized['bot_token_expires_at'] = isset($current_options['bot_token_expires_at'])
                        ? (int) $current_options['bot_token_expires_at']
                        : 0;
                    $sanitized['bot_token_status'] = isset($current_options['bot_token_status'])
                        ? sanitize_key($current_options['bot_token_status'])
                        : 'missing';
                }
            }

            if (
                !$skip_bot_token_migration
                && '' !== $sanitized['bot_token']
                && !discord_bot_jlg_is_encrypted_secret($sanitized['bot_token'])
                && !$skip_token_migration
            ) {
                $migrated = discord_bot_jlg_encrypt_secret($sanitized['bot_token']);

                if (is_wp_error($migrated)) {
                    add_settings_error(
                        'discord_stats_settings',
                        'discord_bot_jlg_token_migration_error',
                        $migrated->get_error_message(),
                        'error'
                    );
                } else {
                    $sanitized['bot_token'] = $migrated;
                    $sanitized['bot_token_rotated_at'] = $current_timestamp;
                }
            }
        }

        if ('' === $sanitized['bot_token']) {
            $sanitized['bot_token_rotated_at'] = 0;
        }

        if ($preserve_existing_secret) {
            $sanitized['bot_token_expires_at'] = isset($current_options['bot_token_expires_at'])
                ? (int) $current_options['bot_token_expires_at']
                : 0;
            $sanitized['bot_token_status'] = isset($current_options['bot_token_status'])
                ? sanitize_key($current_options['bot_token_status'])
                : 'missing';
        } else {
            $main_secret_metadata = $this->finalize_secret_metadata(
                'default',
                $sanitized['bot_token'],
                $sanitized['bot_token_rotated_at'],
                $current_timestamp,
                __('configuration principale', 'discord-bot-jlg')
            );
            $sanitized['bot_token_expires_at'] = $main_secret_metadata['expires_at'];
            $sanitized['bot_token_status'] = $main_secret_metadata['status'];
        }

        $boolean_fields = array(
            'demo_mode',
            'show_online',
            'show_total',
            'show_presence_breakdown',
            'show_approximate_member_count',
            'show_premium_subscriptions',
            'show_server_name',
            'show_server_avatar',
            'default_refresh_enabled',
            'analytics_alerts_enabled',
        );

        foreach ($boolean_fields as $boolean_field) {
            $sanitized[$boolean_field] = !empty($input[$boolean_field]) ? 1 : 0;
        }

        if (isset($input['default_theme'])) {
            $raw_theme = is_string($input['default_theme'])
                ? trim($input['default_theme'])
                : '';

            if ('' === $raw_theme) {
                $sanitized['default_theme'] = $current_theme;
            } elseif (discord_bot_jlg_is_allowed_theme($raw_theme)) {
                $sanitized['default_theme'] = $raw_theme;
            } else {
                $sanitized['default_theme'] = 'discord';
            }
        }

        if (isset($input['widget_title'])) {
            $sanitized['widget_title'] = sanitize_text_field($input['widget_title']);
        }

        if (array_key_exists('invite_url', $input)) {
            $raw_invite_url = is_string($input['invite_url'])
                ? trim($input['invite_url'])
                : '';

            if ('' === $raw_invite_url) {
                $sanitized['invite_url'] = '';
            } else {
                $invite_url = esc_url_raw($raw_invite_url);
                $http_validator_available = function_exists('wp_http_validate_url');
                $is_valid_invite_url = (
                    '' !== $invite_url
                    && preg_match('#^https?://#i', $invite_url)
                    && (!$http_validator_available || wp_http_validate_url($invite_url))
                );

                if ($is_valid_invite_url) {
                    $sanitized['invite_url'] = $invite_url;
                } else {
                    add_settings_error(
                        'discord_stats_settings',
                        'discord_bot_jlg_invite_url_invalid',
                        esc_html__('L\'URL d\'invitation Discord semble invalide. Veuillez saisir une URL complète commençant par http ou https.', 'discord-bot-jlg'),
                        'error'
                    );
                }
            }
        }

        if (isset($input['invite_label'])) {
            $sanitized['invite_label'] = sanitize_text_field($input['invite_label']);
        }

        if (array_key_exists('cache_duration', $input)) {
            $raw_cache_duration = is_string($input['cache_duration'])
                ? trim($input['cache_duration'])
                : $input['cache_duration'];

            if ('' === $raw_cache_duration) {
                $fallback_duration          = isset($current_options['cache_duration'])
                    ? (int) $current_options['cache_duration']
                    : 300;
                $sanitized['cache_duration'] = max(
                    $min_cache_duration,
                    min(3600, $fallback_duration)
                );
            } else {
                $cache_duration              = absint($raw_cache_duration);
                $sanitized['cache_duration'] = max(
                    $min_cache_duration,
                    min(3600, $cache_duration)
                );
            }
        }

        if (array_key_exists('analytics_retention_days', $input)) {
            $raw_retention = is_string($input['analytics_retention_days'])
                ? trim($input['analytics_retention_days'])
                : $input['analytics_retention_days'];

            if ('' === $raw_retention) {
                $sanitized['analytics_retention_days'] = $current_retention;
            } else {
                $retention = absint($raw_retention);

                if ($retention > 365) {
                    $retention = 365;
                }

                $sanitized['analytics_retention_days'] = $retention;
            }
        }

        if (array_key_exists('analytics_alert_drop_percent', $input)) {
            $raw_drop = is_string($input['analytics_alert_drop_percent'])
                ? trim($input['analytics_alert_drop_percent'])
                : $input['analytics_alert_drop_percent'];

            if ('' === $raw_drop) {
                $sanitized['analytics_alert_drop_percent'] = max(5, (int) $sanitized['analytics_alert_drop_percent']);
            } else {
                $drop = (float) $raw_drop;
                if ($drop < 0) {
                    $drop = $default_alert_drop;
                }

                $drop = (int) round($drop);
                if ($drop < 5) {
                    $drop = 5;
                } elseif ($drop > 95) {
                    $drop = 95;
                }

                $sanitized['analytics_alert_drop_percent'] = $drop;
            }
        }

        if (array_key_exists('analytics_alert_recipients', $input)) {
            $invalid_recipients = array();
            $recipients_value   = $this->sanitize_alert_recipients_value($input['analytics_alert_recipients'], $invalid_recipients);
            $sanitized['analytics_alert_recipients'] = $recipients_value;

            if (!empty($invalid_recipients)) {
                add_settings_error(
                    'discord_stats_settings',
                    'discord_bot_jlg_alert_invalid_emails',
                    sprintf(
                        /* translators: %s: list of invalid emails. */
                        esc_html__('Les adresses suivantes sont invalides et ont été ignorées : %s', 'discord-bot-jlg'),
                        esc_html(implode(', ', $invalid_recipients))
                    ),
                    'warning'
                );
            }
        }

        if (array_key_exists('analytics_alert_webhook', $input)) {
            $is_valid_webhook = true;
            $webhook_value    = $this->sanitize_alert_webhook($input['analytics_alert_webhook'], $is_valid_webhook);
            $sanitized['analytics_alert_webhook'] = $webhook_value;

            if (false === $is_valid_webhook && '' !== trim((string) $input['analytics_alert_webhook'])) {
                add_settings_error(
                    'discord_stats_settings',
                    'discord_bot_jlg_alert_webhook_invalid',
                    esc_html__('L’URL de webhook fournie est invalide (HTTPS requis).', 'discord-bot-jlg'),
                    'warning'
                );
            }
        }

        if (array_key_exists('analytics_alert_webhook_secret', $input)) {
            $sanitized['analytics_alert_webhook_secret'] = $this->sanitize_alert_webhook_secret(
                $input['analytics_alert_webhook_secret']
            );
        }

        if (array_key_exists('analytics_alert_cooldown', $input)) {
            $raw_cooldown = is_string($input['analytics_alert_cooldown'])
                ? trim($input['analytics_alert_cooldown'])
                : $input['analytics_alert_cooldown'];

            if ('' === $raw_cooldown) {
                $sanitized['analytics_alert_cooldown'] = max(5, (int) $sanitized['analytics_alert_cooldown']);
            } else {
                $cooldown = absint($raw_cooldown);

                if ($cooldown <= 0) {
                    $cooldown = $default_alert_cooldown;
                }

                if ($cooldown < 5) {
                    $cooldown = 5;
                } elseif ($cooldown > 1440) {
                    $cooldown = 1440;
                }

                $sanitized['analytics_alert_cooldown'] = $cooldown;
            }
        }

        if (isset($input['custom_css'])) {
            $sanitized['custom_css'] = discord_bot_jlg_sanitize_custom_css($input['custom_css']);
        }

        $color_fields = array('stat_bg_color', 'stat_text_color', 'accent_color', 'accent_color_alt', 'accent_text_color');

        foreach ($color_fields as $color_field) {
            if (!array_key_exists($color_field, $input)) {
                continue;
            }

            $raw_color = is_string($input[$color_field]) ? trim($input[$color_field]) : '';

            if ('' === $raw_color) {
                $sanitized[$color_field] = '';
                continue;
            }

            $sanitized_color = discord_bot_jlg_sanitize_color($raw_color);

            $sanitized[$color_field] = $sanitized_color;
        }

        $contrast_pairs = array(
            array(
                'foreground' => 'stat_text_color',
                'background' => 'stat_bg_color',
                'message'    => esc_html__(
                    'The contrast between card text and background is below 4.5:1 (%s).',
                    'discord-bot-jlg'
                ),
            ),
            array(
                'foreground' => 'accent_text_color',
                'background' => 'accent_color',
                'message'    => esc_html__(
                    'The contrast between accent text and primary accent color is below 4.5:1 (%s).',
                    'discord-bot-jlg'
                ),
            ),
            array(
                'foreground' => 'accent_text_color',
                'background' => 'accent_color_alt',
                'message'    => esc_html__(
                    'The contrast between accent text and alternate accent color is below 4.5:1 (%s).',
                    'discord-bot-jlg'
                ),
            ),
        );

        foreach ($contrast_pairs as $pair) {
            $fg_color = isset($sanitized[$pair['foreground']]) ? $sanitized[$pair['foreground']] : '';
            $bg_color = isset($sanitized[$pair['background']]) ? $sanitized[$pair['background']] : '';

            if ('' === $fg_color || '' === $bg_color) {
                continue;
            }

            $ratio = discord_bot_jlg_calculate_contrast_ratio($fg_color, $bg_color);

            if (null === $ratio) {
                continue;
            }

            if ($ratio < 4.5) {
                $formatted_ratio = function_exists('number_format_i18n')
                    ? number_format_i18n($ratio, 2)
                    : number_format($ratio, 2);

                add_settings_error(
                    'discord_stats_settings',
                    'discord_bot_jlg_contrast_' . $pair['foreground'] . '_' . $pair['background'],
                    sprintf($pair['message'], $formatted_ratio),
                    'warning'
                );
            }
        }

        $text_fields = array(
            'default_icon_online',
            'default_icon_total',
            'default_icon_presence',
            'default_icon_approximate',
            'default_icon_premium',
            'default_label_online',
            'default_label_total',
            'default_label_presence',
            'default_label_presence_online',
            'default_label_presence_idle',
            'default_label_presence_dnd',
            'default_label_presence_offline',
            'default_label_presence_streaming',
            'default_label_presence_other',
            'default_label_approximate',
            'default_label_premium',
            'default_label_premium_singular',
            'default_label_premium_plural',
        );

        foreach ($text_fields as $text_field) {
            if (!array_key_exists($text_field, $input)) {
                continue;
            }

            $raw_value = is_string($input[$text_field]) ? trim($input[$text_field]) : '';

            if ('' === $raw_value) {
                $sanitized[$text_field] = '';
                continue;
            }

            $sanitized[$text_field] = sanitize_text_field($raw_value);
        }

        $existing_profiles = isset($current_options['server_profiles']) && is_array($current_options['server_profiles'])
            ? $current_options['server_profiles']
            : array();

        $submitted_profiles = isset($input['server_profiles']) ? $input['server_profiles'] : array();
        $new_profile_input  = isset($input['new_profile']) ? $input['new_profile'] : array();

        $sanitized['server_profiles'] = $this->sanitize_server_profiles(
            $submitted_profiles,
            $new_profile_input,
            $existing_profiles
        );

        if (array_key_exists('default_refresh_interval', $input)) {
            $raw_refresh_interval = is_string($input['default_refresh_interval'])
                ? trim($input['default_refresh_interval'])
                : $input['default_refresh_interval'];

            if ('' === $raw_refresh_interval) {
                $sanitized['default_refresh_interval'] = $current_refresh_interval;
            } else {
                $interval = absint($raw_refresh_interval);

                if ($interval > 0) {
                    $sanitized['default_refresh_interval'] = max(
                        $min_refresh_interval,
                        min($max_refresh_interval, $interval)
                    );
                }
            }
        }

        return $sanitized;
    }

    private function sanitize_server_profiles($submitted_profiles, $new_profile_input, $existing_profiles) {
        $result = array();
        $current_timestamp = current_time('timestamp');

        if (!is_array($submitted_profiles)) {
            $submitted_profiles = array();
        }

        if (!is_array($existing_profiles)) {
            $existing_profiles = array();
        }

        foreach ($submitted_profiles as $raw_key => $profile_input) {
            if (!is_array($profile_input)) {
                continue;
            }

            $profile_key = '';

            if (isset($profile_input['key'])) {
                $profile_key = discord_bot_jlg_sanitize_profile_key($profile_input['key']);
            }

            if ('' === $profile_key) {
                $profile_key = discord_bot_jlg_sanitize_profile_key($raw_key);
            }

            if ('' === $profile_key) {
                continue;
            }

            if (!empty($profile_input['delete'])) {
                continue;
            }

            $label = isset($profile_input['label']) ? sanitize_text_field($profile_input['label']) : '';
            $server_id = isset($profile_input['server_id'])
                ? $this->sanitize_profile_server_id($profile_input['server_id'])
                : '';

            $existing_token = '';
            if (isset($existing_profiles[$profile_key]) && is_array($existing_profiles[$profile_key])) {
                $existing_token = isset($existing_profiles[$profile_key]['bot_token'])
                    ? (string) $existing_profiles[$profile_key]['bot_token']
                    : '';
                $existing_rotation = isset($existing_profiles[$profile_key]['bot_token_rotated_at'])
                    ? (int) $existing_profiles[$profile_key]['bot_token_rotated_at']
                    : 0;
            } else {
                $existing_rotation = 0;
            }

            $token_to_store    = $existing_token;
            $rotation_to_store = $existing_rotation;

            if (!empty($profile_input['bot_token_delete'])) {
                $token_to_store = '';
                $rotation_to_store = 0;
            } elseif (array_key_exists('bot_token', $profile_input)) {
                $raw_token = is_string($profile_input['bot_token'])
                    ? trim($profile_input['bot_token'])
                    : '';

                if ('' !== $raw_token) {
                    $token_candidate = sanitize_text_field($raw_token);
                    $encrypted       = discord_bot_jlg_encrypt_secret($token_candidate);

                    if (is_wp_error($encrypted)) {
                        add_settings_error(
                            'discord_stats_settings',
                            'discord_bot_jlg_profile_token_encrypt_' . $profile_key,
                            $encrypted->get_error_message(),
                            'error'
                        );
                    } else {
                        $token_to_store = $encrypted;
                        $rotation_to_store = $current_timestamp;
                    }
                }
            }

            if (
                '' !== $token_to_store
                && !discord_bot_jlg_is_encrypted_secret($token_to_store)
            ) {
                $migrated = discord_bot_jlg_encrypt_secret($token_to_store);

                if (is_wp_error($migrated)) {
                    add_settings_error(
                        'discord_stats_settings',
                        'discord_bot_jlg_profile_token_migration_' . $profile_key,
                        $migrated->get_error_message(),
                        'error'
                    );
                } else {
                    $token_to_store = $migrated;

                    if ($rotation_to_store <= 0) {
                        $rotation_to_store = $current_timestamp;
                    }
                }
            }

            $metadata = $this->finalize_secret_metadata(
                'profile_' . $profile_key,
                $token_to_store,
                $rotation_to_store,
                $current_timestamp,
                ('' !== $label) ? $label : $profile_key
            );

            $result[$profile_key] = array(
                'key'       => $profile_key,
                'label'     => $label,
                'server_id' => $server_id,
                'bot_token' => $token_to_store,
                'bot_token_rotated_at' => $rotation_to_store,
                'bot_token_expires_at' => $metadata['expires_at'],
                'bot_token_status'     => $metadata['status'],
            );
        }

        if (!is_array($new_profile_input)) {
            $new_profile_input = array();
        }

        $has_new_profile_data = false;
        foreach (array('key', 'label', 'server_id', 'bot_token') as $field) {
            if (!empty($new_profile_input[$field])) {
                $has_new_profile_data = true;
                break;
            }
        }

        if ($has_new_profile_data) {
            $profile_key = isset($new_profile_input['key']) ? discord_bot_jlg_sanitize_profile_key($new_profile_input['key']) : '';
            $label       = isset($new_profile_input['label']) ? sanitize_text_field($new_profile_input['label']) : '';

            if ('' === $profile_key && '' !== $label) {
                $profile_key = discord_bot_jlg_sanitize_profile_key(sanitize_title($label));
            }

            if ('' === $profile_key) {
                $profile_key = discord_bot_jlg_sanitize_profile_key('profil_' . uniqid());
            }

            if ('' === $profile_key || isset($result[$profile_key])) {
                add_settings_error(
                    'discord_stats_settings',
                    'discord_bot_jlg_profile_duplicate_' . $profile_key,
                    esc_html__('Impossible d’enregistrer le nouveau profil : la clé est manquante ou déjà utilisée.', 'discord-bot-jlg'),
                    'error'
                );
            } else {
                $server_id = isset($new_profile_input['server_id'])
                    ? $this->sanitize_profile_server_id($new_profile_input['server_id'])
                    : '';

                $token_to_store    = '';
                $rotation_to_store = 0;
                if (!empty($new_profile_input['bot_token'])) {
                    $token_candidate = sanitize_text_field($new_profile_input['bot_token']);
                    $encrypted       = discord_bot_jlg_encrypt_secret($token_candidate);

                    if (is_wp_error($encrypted)) {
                        add_settings_error(
                            'discord_stats_settings',
                            'discord_bot_jlg_profile_token_encrypt_new',
                            $encrypted->get_error_message(),
                            'error'
                        );
                    } else {
                        $token_to_store    = $encrypted;
                        $rotation_to_store = $current_timestamp;
                    }
                }

                $metadata = $this->finalize_secret_metadata(
                    'profile_' . $profile_key,
                    $token_to_store,
                    $rotation_to_store,
                    $current_timestamp,
                    ('' !== $label) ? $label : $profile_key
                );

                $result[$profile_key] = array(
                    'key'       => $profile_key,
                    'label'     => $label,
                    'server_id' => $server_id,
                    'bot_token' => $token_to_store,
                    'bot_token_rotated_at' => $rotation_to_store,
                    'bot_token_expires_at' => $metadata['expires_at'],
                    'bot_token_status'     => $metadata['status'],
                );
            }
        }

        return $result;
    }

    private function finalize_secret_metadata($context_key, $token, $rotated_at, $current_timestamp, $label) {
        $metadata = array(
            'expires_at' => 0,
            'status'     => 'missing',
        );

        $token = (string) $token;
        $rotated_at = (int) $rotated_at;
        $current_timestamp = (int) $current_timestamp;
        $label = (string) $label;
        $context_key = sanitize_key($context_key);

        if ('' === $token) {
            return $metadata;
        }

        if ('' === $label) {
            $label = $context_key;
        }

        $max_age_days = (int) apply_filters(
            'discord_bot_jlg_secret_rotation_max_age_days',
            self::SECRET_ROTATION_MAX_AGE_DAYS,
            $context_key,
            $label
        );

        if ($max_age_days <= 0) {
            $max_age_days = self::SECRET_ROTATION_MAX_AGE_DAYS;
        }

        if ($rotated_at <= 0) {
            $metadata['status'] = 'unknown';

            $this->add_secret_rotation_notice(
                $context_key . '_rotation_unknown',
                sprintf(
                    __('Le jeton Discord pour « %s » doit être enregistré de nouveau afin de suivre sa rotation automatique.', 'discord-bot-jlg'),
                    $label
                ),
                'warning'
            );

            return $metadata;
        }

        $expires_at = $rotated_at + ($max_age_days * DAY_IN_SECONDS);
        $metadata['expires_at'] = $expires_at;
        $metadata['status'] = ($current_timestamp >= $expires_at) ? 'expired' : 'active';

        if ('expired' === $metadata['status']) {
            $this->add_secret_rotation_notice(
                $context_key . '_expired',
                sprintf(
                    /* translators: 1: profile label, 2: rotation window in days. */
                    __('Le jeton Discord pour « %s » a expiré (rotation maximale %d jours). Veuillez le régénérer.', 'discord-bot-jlg'),
                    $label,
                    $max_age_days
                ),
                'error'
            );
        }

        return $metadata;
    }

    private function add_secret_rotation_notice($slug_suffix, $message, $type = 'error') {
        $slug_suffix = sanitize_key($slug_suffix);
        $message = (string) $message;
        $type = (string) $type;

        if ('' === $slug_suffix || '' === $message) {
            return;
        }

        add_settings_error(
            'discord_stats_settings',
            'discord_bot_jlg_secret_' . $slug_suffix,
            esc_html($message),
            $type
        );
    }

    private function should_flag_secret_for_rotation($token, array $metadata, $rotated_at, $now, $threshold_seconds) {
        $token = (string) $token;

        if ('' === $token) {
            return false;
        }

        $status = isset($metadata['status']) ? sanitize_key($metadata['status']) : '';

        if ('expired' === $status || 'unknown' === $status) {
            return true;
        }

        $expires_at = isset($metadata['expires_at']) ? (int) $metadata['expires_at'] : 0;

        if ($expires_at > 0 && $expires_at <= $now) {
            return true;
        }

        $rotated_at = (int) $rotated_at;

        if ($rotated_at <= 0) {
            return true;
        }

        return (($now - $rotated_at) >= $threshold_seconds);
    }

    public function maybe_display_secret_rotation_notice() {
        if (!Discord_Bot_JLG_Capabilities::current_user_can('manage_settings')) {
            return;
        }

        if (!function_exists('get_current_screen')) {
            return;
        }

        $screen = get_current_screen();

        if (!$screen || empty($screen->id) || false === strpos((string) $screen->id, 'discord-bot')) {
            return;
        }

        $options = get_option($this->option_name);

        if (!is_array($options)) {
            $options = array();
        }

        $tokens = $this->get_tokens_requiring_rotation($options);

        if (empty($tokens)) {
            return;
        }

        $threshold_days = $this->get_secret_rotation_threshold_days();
        $date_format    = get_option('date_format');
        $time_format    = get_option('time_format');

        echo '<div class="notice notice-warning">';
        echo '<p>';
        printf(
            /* translators: %d: rotation threshold in days. */
            esc_html__('Rotation des tokens recommandée tous les %d jours.', 'discord-bot-jlg'),
            (int) $threshold_days
        );
        echo '<br />';
        esc_html_e('Les tokens listés ci-dessous dépassent ce délai. Réenregistrez un secret pour réinitialiser l’horodatage.', 'discord-bot-jlg');
        echo '</p>';
        echo '<ul>';

        foreach ($tokens as $token) {
            $label      = isset($token['label']) ? (string) $token['label'] : '';
            $rotated_at = isset($token['rotated_at']) ? (int) $token['rotated_at'] : 0;
            $expires_at = isset($token['expires_at']) ? (int) $token['expires_at'] : 0;
            $status     = isset($token['status']) ? sanitize_key($token['status']) : '';

            echo '<li>';

            if ($rotated_at > 0) {
                $formatted = date_i18n($date_format . ' ' . $time_format, $rotated_at);
                printf(
                    /* translators: 1: token label, 2: formatted datetime. */
                    esc_html__('%1$s — dernière rotation le %2$s.', 'discord-bot-jlg'),
                    esc_html($label),
                    esc_html($formatted)
                );
            } else {
                printf(
                    /* translators: %s: token label. */
                    esc_html__('%s — aucune date de rotation enregistrée.', 'discord-bot-jlg'),
                    esc_html($label)
                );
            }

            if ('expired' === $status) {
                if ($expires_at > 0) {
                    $expiry_formatted = date_i18n($date_format . ' ' . $time_format, $expires_at);
                    printf(
                        /* translators: %s: formatted datetime. */
                        esc_html__(' (expiré le %s)', 'discord-bot-jlg'),
                        esc_html($expiry_formatted)
                    );
                } else {
                    echo esc_html__(' (expiré)', 'discord-bot-jlg');
                }
            } elseif ('unknown' === $status) {
                echo esc_html__(' (rotation inconnue)', 'discord-bot-jlg');
            } elseif ($expires_at > 0) {
                $expiry_formatted = date_i18n($date_format . ' ' . $time_format, $expires_at);
                printf(
                    /* translators: %s: formatted datetime. */
                    esc_html__(' (expiration prévue le %s)', 'discord-bot-jlg'),
                    esc_html($expiry_formatted)
                );
            }

            echo '</li>';
        }

        echo '</ul>';
        echo '</div>';
    }

    private function get_tokens_requiring_rotation(array $options) {
        $tokens            = array();
        $now               = current_time('timestamp');
        $threshold_seconds = $this->get_secret_rotation_threshold_days() * DAY_IN_SECONDS;
        $constant_overridden = (defined('DISCORD_BOT_JLG_TOKEN') && '' !== DISCORD_BOT_JLG_TOKEN);

        if (!$constant_overridden) {
            $main_token = isset($options['bot_token']) ? (string) $options['bot_token'] : '';
            $main_metadata = array(
                'status'     => isset($options['bot_token_status']) ? $options['bot_token_status'] : '',
                'expires_at' => isset($options['bot_token_expires_at']) ? (int) $options['bot_token_expires_at'] : 0,
            );
            $main_rotated_at = isset($options['bot_token_rotated_at'])
                ? (int) $options['bot_token_rotated_at']
                : 0;

            if ($this->should_flag_secret_for_rotation($main_token, $main_metadata, $main_rotated_at, $now, $threshold_seconds)) {
                $tokens[] = array(
                    'label'      => __('Token principal', 'discord-bot-jlg'),
                    'rotated_at' => $main_rotated_at,
                    'expires_at' => isset($main_metadata['expires_at']) ? (int) $main_metadata['expires_at'] : 0,
                    'status'     => sanitize_key(isset($main_metadata['status']) ? $main_metadata['status'] : ''),
                );
            }
        }

        if (isset($options['server_profiles']) && is_array($options['server_profiles'])) {
            foreach ($options['server_profiles'] as $profile_key => $profile) {
                if (!is_array($profile)) {
                    continue;
                }

                $token = isset($profile['bot_token']) ? (string) $profile['bot_token'] : '';

                if ('' === $token) {
                    continue;
                }

                $profile_metadata = array(
                    'status'     => isset($profile['bot_token_status']) ? $profile['bot_token_status'] : '',
                    'expires_at' => isset($profile['bot_token_expires_at']) ? (int) $profile['bot_token_expires_at'] : 0,
                );

                $rotated_at = isset($profile['bot_token_rotated_at'])
                    ? (int) $profile['bot_token_rotated_at']
                    : 0;

                if (!$this->should_flag_secret_for_rotation($token, $profile_metadata, $rotated_at, $now, $threshold_seconds)) {
                    continue;
                }

                $label = '';

                if (isset($profile['label']) && '' !== trim($profile['label'])) {
                    $label = sanitize_text_field($profile['label']);
                }

                if ('' === $label) {
                    $sanitized_key = discord_bot_jlg_sanitize_profile_key($profile_key);

                    if ('' === $sanitized_key) {
                        $label = __('Profil sans nom', 'discord-bot-jlg');
                    } else {
                        $label = sprintf(
                            /* translators: %s: profile key. */
                            __('Profil %s', 'discord-bot-jlg'),
                            $sanitized_key
                        );
                    }
                }

                $tokens[] = array(
                    'label'      => $label,
                    'rotated_at' => $rotated_at,
                    'expires_at' => isset($profile_metadata['expires_at']) ? (int) $profile_metadata['expires_at'] : 0,
                    'status'     => sanitize_key(isset($profile_metadata['status']) ? $profile_metadata['status'] : ''),
                );
            }
        }

        return $tokens;
    }

    private function get_secret_rotation_threshold_days() {
        $days = (int) apply_filters('discord_bot_jlg_secret_rotation_max_age_days', self::SECRET_ROTATION_MAX_AGE_DAYS);

        if ($days <= 0) {
            return self::SECRET_ROTATION_MAX_AGE_DAYS;
        }

        return $days;
    }

    private function sanitize_alert_recipients_value($raw_value, &$invalid = null) {
        if (!is_array($invalid)) {
            $invalid = array();
        }

        if (!is_string($raw_value)) {
            return '';
        }

        $candidates = preg_split('/[\s,;]+/', $raw_value);
        if (!is_array($candidates)) {
            return '';
        }

        $valid = array();
        $invalid_local = array();

        foreach ($candidates as $candidate) {
            $candidate = trim($candidate);

            if ('' === $candidate) {
                continue;
            }

            if (is_email($candidate)) {
                $sanitized = sanitize_email($candidate);
                if ('' !== $sanitized) {
                    $valid[] = $sanitized;
                }
            } else {
                $invalid_local[] = sanitize_text_field($candidate);
            }
        }

        $invalid = $invalid_local;

        if (empty($valid)) {
            return '';
        }

        $valid = array_values(array_unique($valid));

        return implode("\n", $valid);
    }

    private function sanitize_alert_webhook($value, &$is_valid = null) {
        $is_valid = true;

        if (!is_string($value)) {
            return '';
        }

        $value = trim($value);
        if ('' === $value) {
            return '';
        }

        $sanitized = esc_url_raw($value);
        if ('' === $sanitized) {
            $is_valid = false;
            return '';
        }

        if (function_exists('wp_http_validate_url')) {
            $validated = wp_http_validate_url($sanitized);
            if (false === $validated) {
                $is_valid = false;
                return '';
            }
            $sanitized = $validated;
        }

        if (0 !== strpos($sanitized, 'https://')) {
            $is_valid = false;
            return '';
        }

        return $sanitized;
    }

    /**
     * Sanitize the secret used for analytics alert webhooks.
     *
     * The value must consist of printable ASCII characters so that secrets
     * encoded in base64 (and similar formats) are preserved. To avoid storing
     * unexpectedly large payloads, the value is capped at
     * self::ALERT_WEBHOOK_SECRET_MAX_LENGTH characters.
     *
     * @param mixed $value Raw secret value.
     *
     * @return string Sanitized secret or an empty string if invalid.
     */
    private function sanitize_alert_webhook_secret($value) {
        if (is_array($value)) {
            return '';
        }

        $value = trim((string) $value);

        if ('' === $value) {
            return '';
        }

        if (!preg_match('/^[\x20-\x7E]+$/', $value)) {
            return '';
        }

        if (strlen($value) > self::ALERT_WEBHOOK_SECRET_MAX_LENGTH) {
            $value = substr($value, 0, self::ALERT_WEBHOOK_SECRET_MAX_LENGTH);
        }

        return $value;
    }

    private function sanitize_profile_server_id($value) {
        if (!is_string($value) && !is_numeric($value)) {
            return '';
        }

        $value = preg_replace('/[^0-9]/', '', (string) $value);

        return (string) $value;
    }

    /**
     * Présente la section de gestion des profils de serveur.
     */
    public function profiles_section_callback() {
        ?>
        <p>
            <?php esc_html_e('Enregistrez plusieurs connexions Discord et réutilisez-les facilement dans vos blocs, shortcodes et widgets.', 'discord-bot-jlg'); ?>
        </p>
        <?php
    }

    public function server_profiles_render() {
        $options = get_option($this->option_name);
        $profiles = array();

        if (is_array($options) && isset($options['server_profiles']) && is_array($options['server_profiles'])) {
            $profiles = $options['server_profiles'];
        }

        if (!is_array($profiles)) {
            $profiles = array();
        }

        ?>
        <table class="widefat striped discord-profiles-table">
            <thead>
                <tr>
                    <th scope="col"><?php esc_html_e('Profil', 'discord-bot-jlg'); ?></th>
                    <th scope="col"><?php esc_html_e('ID du serveur', 'discord-bot-jlg'); ?></th>
                    <th scope="col"><?php esc_html_e('Token du bot', 'discord-bot-jlg'); ?></th>
                    <th scope="col"><?php esc_html_e('Vérification', 'discord-bot-jlg'); ?></th>
                    <th scope="col"><?php esc_html_e('Actions', 'discord-bot-jlg'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($profiles)) : ?>
                    <?php foreach ($profiles as $key => $profile) :
                        if (!is_array($profile)) {
                            continue;
                        }

                        $profile_key   = isset($profile['key'])
                            ? discord_bot_jlg_sanitize_profile_key($profile['key'])
                            : discord_bot_jlg_sanitize_profile_key($key);
                        if ('' === $profile_key) {
                            continue;
                        }

                        $profile_label = isset($profile['label']) ? sanitize_text_field($profile['label']) : '';
                        $server_id     = isset($profile['server_id']) ? preg_replace('/[^0-9]/', '', (string) $profile['server_id']) : '';
                        $has_token     = !empty($profile['bot_token']);
                        $rotation_timestamp = isset($profile['bot_token_rotated_at'])
                            ? (int) $profile['bot_token_rotated_at']
                            : 0;
                        ?>
                        <tr>
                            <td>
                                <input type="hidden" name="<?php echo esc_attr($this->option_name); ?>[server_profiles][<?php echo esc_attr($profile_key); ?>][key]" value="<?php echo esc_attr($profile_key); ?>" />
                                <label>
                                    <span class="screen-reader-text"><?php esc_html_e('Nom du profil', 'discord-bot-jlg'); ?></span>
                                    <input type="text" class="regular-text" name="<?php echo esc_attr($this->option_name); ?>[server_profiles][<?php echo esc_attr($profile_key); ?>][label]" value="<?php echo esc_attr($profile_label); ?>" placeholder="<?php esc_attr_e('Nom du profil', 'discord-bot-jlg'); ?>" />
                                </label>
                                <p class="description">
                                    <?php esc_html_e('Utilisez un nom parlant (ex. “Serveur Communauté”).', 'discord-bot-jlg'); ?>
                                </p>
                            </td>
                            <td>
                                <label>
                                    <span class="screen-reader-text"><?php esc_html_e('ID du serveur', 'discord-bot-jlg'); ?></span>
                                    <input type="text" class="regular-text" name="<?php echo esc_attr($this->option_name); ?>[server_profiles][<?php echo esc_attr($profile_key); ?>][server_id]" value="<?php echo esc_attr($server_id); ?>" placeholder="1234567890" />
                                </label>
                                <p class="description"><?php esc_html_e('Saisissez l’identifiant numérique de votre serveur Discord.', 'discord-bot-jlg'); ?></p>
                            </td>
                            <td>
                                <input type="password" class="regular-text" name="<?php echo esc_attr($this->option_name); ?>[server_profiles][<?php echo esc_attr($profile_key); ?>][bot_token]" value="" autocomplete="new-password" placeholder="<?php esc_attr_e('Nouveau token (optionnel)', 'discord-bot-jlg'); ?>" />
                                <p class="description">
                                    <?php esc_html_e('Laisser vide pour conserver le token existant.', 'discord-bot-jlg'); ?>
                                </p>
                                <?php if ($has_token) : ?>
                                <p class="description" style="margin-top: -8px;">
                                    <?php esc_html_e('Un token est actuellement enregistré.', 'discord-bot-jlg'); ?>
                                </p>
                                <p class="description" style="margin-top: -12px;">
                                    <?php
                                    if ($rotation_timestamp > 0) {
                                        $date_format = get_option('date_format');
                                        $time_format = get_option('time_format');
                                        $formatted   = date_i18n($date_format . ' ' . $time_format, $rotation_timestamp);

                                        printf(
                                            /* translators: %s: formatted date and time. */
                                            esc_html__('Dernière rotation : %s.', 'discord-bot-jlg'),
                                            esc_html($formatted)
                                        );
                                    } else {
                                        esc_html_e('Date de rotation inconnue — réenregistrez un token pour l’horodater.', 'discord-bot-jlg');
                                    }
                                    ?>
                                </p>
                                <?php endif; ?>
                                <label>
                                    <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[server_profiles][<?php echo esc_attr($profile_key); ?>][bot_token_delete]" value="1" />
                                    <?php esc_html_e('Supprimer le token enregistré', 'discord-bot-jlg'); ?>
                                </label>
                            </td>
                            <td>
                                <button type="submit"
                                        class="button button-secondary"
                                        form="discord-profile-test-form"
                                        name="test_connection_profile"
                                        value="<?php echo esc_attr($profile_key); ?>"
                                        aria-label="<?php
                                            printf(
                                                /* translators: %s: profile label. */
                                                esc_attr__('Tester la connexion pour le profil « %s »', 'discord-bot-jlg'),
                                                esc_attr($profile_label ? $profile_label : $profile_key)
                                            );
                                        ?>">
                                    <?php esc_html_e('Tester', 'discord-bot-jlg'); ?>
                                </button>
                                <p class="description"><?php esc_html_e('Utilise le serveur et le token enregistrés pour ce profil.', 'discord-bot-jlg'); ?></p>
                            </td>
                            <td>
                                <label>
                                    <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[server_profiles][<?php echo esc_attr($profile_key); ?>][delete]" value="1" />
                                    <?php esc_html_e('Supprimer ce profil', 'discord-bot-jlg'); ?>
                                </label>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="5">
                            <em><?php esc_html_e('Aucun profil enregistré pour le moment.', 'discord-bot-jlg'); ?></em>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th scope="row"><?php esc_html_e('Nouveau profil', 'discord-bot-jlg'); ?></th>
                    <td>
                        <input type="text" class="regular-text" name="<?php echo esc_attr($this->option_name); ?>[new_profile][server_id]" placeholder="1234567890" />
                        <p class="description"><?php esc_html_e('Identifiant du serveur Discord.', 'discord-bot-jlg'); ?></p>
                    </td>
                    <td>
                        <input type="password" class="regular-text" name="<?php echo esc_attr($this->option_name); ?>[new_profile][bot_token]" placeholder="<?php esc_attr_e('Token du bot', 'discord-bot-jlg'); ?>" autocomplete="new-password" />
                        <p class="description"><?php esc_html_e('Le token sera chiffré automatiquement avant sauvegarde.', 'discord-bot-jlg'); ?></p>
                    </td>
                    <td>
                        <input type="text" class="regular-text" name="<?php echo esc_attr($this->option_name); ?>[new_profile][label]" placeholder="<?php esc_attr_e('Nom du profil', 'discord-bot-jlg'); ?>" />
                        <input type="text" class="regular-text" name="<?php echo esc_attr($this->option_name); ?>[new_profile][key]" placeholder="<?php esc_attr_e('Clé unique (optionnel)', 'discord-bot-jlg'); ?>" />
                        <p class="description"><?php esc_html_e('La clé est utilisée dans les shortcodes (ex. profil communautaire).', 'discord-bot-jlg'); ?></p>
                    </td>
                    <td class="discord-profiles-table__placeholder">
                        <p class="description"><?php esc_html_e('Sauvegardez avant de pouvoir tester ce profil.', 'discord-bot-jlg'); ?></p>
                    </td>
                </tr>
            </tfoot>
        </table>
        <?php
    }

    /**
     * Affiche la section d'aide dédiée à la configuration de l'API Discord.
     *
     * @return void
     */
    public function api_section_callback() {
        ?>
        <div class="discord-setup-guide">
            <div class="notice notice-info discord-setup-notice">
                <p><?php esc_html_e('📚 Suivez les étapes ci-dessous pour connecter votre bot et valider les droits Discord.', 'discord-bot-jlg'); ?></p>
            </div>
            <details class="discord-setup-details">
                <summary><?php esc_html_e('Afficher le guide détaillé', 'discord-bot-jlg'); ?></summary>
                <?php $this->render_api_steps(); ?>
            </details>
            <details class="discord-setup-details">
                <summary><?php esc_html_e('Voir des exemples de shortcode', 'discord-bot-jlg'); ?></summary>
                <?php $this->render_api_previews(); ?>
            </details>
        </div>
        <?php
    }

    /**
     * Affiche les étapes de configuration de l'API Discord.
     */
    private function render_api_steps() {
        ?>
        <h4><?php esc_html_e('Étape 1 : Créer un Bot Discord', 'discord-bot-jlg'); ?></h4>
        <ol>
            <li>
                <?php
                printf(
                    wp_kses_post(
                        /* translators: %1$s: URL to the Discord Developer Portal. */
                        __(
                            'Rendez-vous sur <a href="%1$s" target="_blank" rel="noopener noreferrer">Discord Developer Portal</a>',
                            'discord-bot-jlg'
                        )
                    ),
                    esc_url('https://discord.com/developers/applications')
                );
                ?>
            </li>
            <li><?php echo wp_kses_post(__('Cliquez sur <strong>"New Application"</strong> en haut à droite', 'discord-bot-jlg')); ?></li>
            <li><?php esc_html_e('Donnez un nom à votre application (ex: "Stats Bot")', 'discord-bot-jlg'); ?></li>
            <li><?php echo wp_kses_post(__('Dans le menu de gauche, cliquez sur <strong>"Bot"</strong>', 'discord-bot-jlg')); ?></li>
            <li><?php echo wp_kses_post(__('Cliquez sur <strong>"Add Bot"</strong>', 'discord-bot-jlg')); ?></li>
            <li><?php echo wp_kses_post(__('Sous "Token", cliquez sur <strong>"Copy"</strong> pour copier le token du bot', 'discord-bot-jlg')); ?></li>
            <li><?php echo wp_kses_post(__('⚠️ <strong>Important :</strong> Gardez ce token secret et ne le partagez jamais !', 'discord-bot-jlg')); ?></li>
        </ol>

        <h4><?php esc_html_e('Étape 2 : Inviter le Bot sur votre serveur', 'discord-bot-jlg'); ?></h4>
        <ol>
            <li><?php echo wp_kses_post(__('Dans le menu de gauche, allez dans <strong>"OAuth2"</strong> &gt; <strong>"URL Generator"</strong>', 'discord-bot-jlg')); ?></li>
            <li><?php echo wp_kses_post(__('Dans "Scopes", cochez <strong>"bot"</strong>', 'discord-bot-jlg')); ?></li>
            <li>
                <?php echo wp_kses_post(__('Dans "Bot Permissions", sélectionnez :', 'discord-bot-jlg')); ?>
                <ul>
                    <li><?php esc_html_e('✅ View Channels', 'discord-bot-jlg'); ?></li>
                    <li><?php esc_html_e('✅ Read Messages', 'discord-bot-jlg'); ?></li>
                    <li><?php esc_html_e('✅ Send Messages (optionnel)', 'discord-bot-jlg'); ?></li>
                </ul>
            </li>
            <li><?php esc_html_e('Copiez l\'URL générée en bas de la page', 'discord-bot-jlg'); ?></li>
            <li><?php esc_html_e('Ouvrez cette URL dans votre navigateur', 'discord-bot-jlg'); ?></li>
            <li><?php echo wp_kses_post(__('Sélectionnez votre serveur et cliquez sur <strong>"Autoriser"</strong>', 'discord-bot-jlg')); ?></li>
        </ol>

        <h4><?php esc_html_e('Étape 3 : Obtenir l\'ID de votre serveur', 'discord-bot-jlg'); ?></h4>
        <ol>
            <li><?php esc_html_e('Ouvrez Discord (application ou web)', 'discord-bot-jlg'); ?></li>
            <li><?php echo wp_kses_post(__('Allez dans <strong>Paramètres utilisateur</strong> (engrenage en bas)', 'discord-bot-jlg')); ?></li>
            <li><?php echo wp_kses_post(__('Dans <strong>"Avancés"</strong>, activez <strong>"Mode développeur"</strong>', 'discord-bot-jlg')); ?></li>
            <li><?php esc_html_e('Retournez sur votre serveur', 'discord-bot-jlg'); ?></li>
            <li><?php echo wp_kses_post(__('Faites un <strong>clic droit sur le nom du serveur</strong>', 'discord-bot-jlg')); ?></li>
            <li><?php echo wp_kses_post(__('Cliquez sur <strong>"Copier l\'ID"</strong>', 'discord-bot-jlg')); ?></li>
        </ol>

        <h4><?php esc_html_e('Étape 4 : Activer le Widget (optionnel mais recommandé)', 'discord-bot-jlg'); ?></h4>
        <ol>
            <li><?php echo wp_kses_post(__('Dans Discord, allez dans <strong>Paramètres du serveur</strong>', 'discord-bot-jlg')); ?></li>
            <li><?php echo wp_kses_post(__('Dans <strong>"Widget"</strong>, activez <strong>"Activer le widget du serveur"</strong>', 'discord-bot-jlg')); ?></li>
            <li><?php esc_html_e('Cela permet une méthode de fallback si le bot a des problèmes', 'discord-bot-jlg'); ?></li>
        </ol>
        <?php
    }

    /**
     * Affiche les prévisualisations rapides du shortcode dans la section API.
     */
    private function render_api_previews() {
        ?>
        <div class="discord-preview-wrapper">
            <p class="discord-preview-notice"><?php echo wp_kses_post(__('<strong>💡 Conseil :</strong> Après avoir rempli les champs ci-dessous, utilisez le bouton « Tester la connexion » pour vérifier que tout fonctionne !', 'discord-bot-jlg')); ?></p>
            <div class="discord-preview-list">
                <?php
                $this->render_preview_block(
                    __('Avec logo Discord officiel :', 'discord-bot-jlg'),
                    '[discord_stats demo="true" show_discord_icon="true" discord_icon_position="left"]',
                    array(
                        'container_class' => 'discord-preview-card',
                    )
                );

                $this->render_preview_block(
                    __('Logo Discord centré en haut :', 'discord-bot-jlg'),
                    '[discord_stats demo="true" show_discord_icon="true" discord_icon_position="top" align="center" theme="dark"]',
                    array(
                        'container_class'     => 'discord-preview-card',
                        'inner_wrapper_class' => 'discord-preview-card__inner is-narrow',
                    )
                );
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Affiche un rappel concernant la personnalisation de l'affichage des statistiques.
     *
     * @return void
     */
    public function display_section_callback() {
        printf('<p>%s</p>', esc_html__('Personnalisez l\'affichage des statistiques Discord.', 'discord-bot-jlg'));
    }

    public function cta_section_callback() {
        printf(
            '<p>%s</p>',
            esc_html__(
                'Contrôlez les appels à l’action associés au bloc et au widget.',
                'discord-bot-jlg'
            )
        );
    }

    public function automation_section_callback() {
        printf(
            '<p>%s</p>',
            esc_html__(
                'Définissez les cadences de rafraîchissement et la rétention des données.',
                'discord-bot-jlg'
            )
        );
    }

    public function custom_css_section_callback() {
        printf(
            '<p>%s</p>',
            esc_html__(
                'Injectez un complément de styles pour harmoniser le rendu avec votre thème.',
                'discord-bot-jlg'
            )
        );
    }

    public function analytics_section_callback() {
        printf(
            '<p>%s</p>',
            esc_html__(
                'Conservez un historique des snapshots pour alimenter les graphiques et tendances.',
                'discord-bot-jlg'
            )
        );
    }

    /**
     * Rend le champ permettant de saisir l'identifiant du serveur Discord.
     *
     * @return void
     */
    public function server_id_render() {
        $options = get_option($this->option_name);
        ?>
        <input type="text" name="<?php echo esc_attr($this->option_name); ?>[server_id]"
               value="<?php echo esc_attr(isset($options['server_id']) ? $options['server_id'] : ''); ?>"
               class="regular-text"
               autocomplete="off" />
        <p class="description"><?php esc_html_e('L\'ID de votre serveur Discord', 'discord-bot-jlg'); ?></p>
        <?php
    }

    /**
     * Rend le champ de saisie du token du bot Discord.
     *
     * @return void
     */
    public function bot_token_render() {
        $options             = get_option($this->option_name);
        $constant_overridden = (defined('DISCORD_BOT_JLG_TOKEN') && '' !== DISCORD_BOT_JLG_TOKEN);
        $stored_token        = isset($options['bot_token']) ? $options['bot_token'] : '';
        $rotation_timestamp  = isset($options['bot_token_rotated_at'])
            ? (int) $options['bot_token_rotated_at']
            : 0;
        $is_encrypted_token  = discord_bot_jlg_is_encrypted_secret($stored_token);
        $decryption_error    = null;

        if ($is_encrypted_token) {
            $decrypted_token = discord_bot_jlg_decrypt_secret($stored_token);

            if (is_wp_error($decrypted_token)) {
                $decryption_error = $decrypted_token;

                $this->api->flush_options_cache();

                add_settings_error(
                    'discord_stats_settings',
                    'discord_bot_jlg_token_decrypt_error',
                    $decryption_error->get_error_message(),
                    'error'
                );
            }
        }

        $has_saved_token = (
            !$constant_overridden
            && !empty($stored_token)
            && (null === $decryption_error)
        );
        $input_id            = sprintf('%s_bot_token', $this->option_name);
        $delete_input_name   = sprintf('%s[bot_token_delete]', $this->option_name);
        $delete_input_id     = sprintf('%s_bot_token_delete', $this->option_name);

        $input_attributes = array(
            'type'          => 'password',
            'name'          => sprintf('%s[bot_token]', $this->option_name),
            'class'         => 'regular-text',
            'value'         => '',
            'autocomplete'  => 'new-password',
            'id'            => $input_id,
            'aria-describedby' => sprintf('%s_description', $input_id),
        );

        if ($constant_overridden) {
            $input_attributes['readonly'] = 'readonly';
            $input_attributes['placeholder'] = __('Défini via une constante', 'discord-bot-jlg');
        } else {
            $input_attributes['placeholder'] = __('Collez votre token Discord', 'discord-bot-jlg');
        }

        $attribute_parts = array();
        foreach ($input_attributes as $attribute => $value) {
            if ('' === $value && 'value' !== $attribute) {
                continue;
            }

            $attribute_parts[] = sprintf('%s="%s"', esc_attr($attribute), esc_attr($value));
        }
        ?>
        <input <?php echo implode(' ', $attribute_parts); ?> />
        <p class="description" id="<?php echo esc_attr($input_id); ?>_description">
            <?php
            if ($constant_overridden) {
                echo wp_kses_post(__('Le token est actuellement défini via la constante <code>DISCORD_BOT_JLG_TOKEN</code> et remplace cette valeur.', 'discord-bot-jlg'));
            } else {
                echo esc_html__('Saisissez un nouveau token pour mettre à jour la valeur enregistrée. Laissez ce champ vide pour conserver le token actuel.', 'discord-bot-jlg');
            }
            ?>
        </p>
        <p class="description">
            <strong><?php esc_html_e('Statut :', 'discord-bot-jlg'); ?></strong>
            <?php
            if ($constant_overridden) {
                esc_html_e('Défini via une constante.', 'discord-bot-jlg');
            } elseif ($has_saved_token && $is_encrypted_token) {
                esc_html_e('Un token est enregistré (secret chiffré).', 'discord-bot-jlg');
            } elseif ($has_saved_token) {
                esc_html_e('Un token est enregistré.', 'discord-bot-jlg');
            } elseif (null !== $decryption_error) {
                esc_html_e('Erreur lors du déchiffrement du token enregistré.', 'discord-bot-jlg');
            } else {
                esc_html_e('Aucun token enregistré.', 'discord-bot-jlg');
            }
            ?>
        </p>
        <?php if (!$constant_overridden && $has_saved_token) : ?>
            <p class="description">
                <?php
                if ($rotation_timestamp > 0) {
                    $date_format = get_option('date_format');
                    $time_format = get_option('time_format');
                    $formatted   = date_i18n($date_format . ' ' . $time_format, $rotation_timestamp);

                    printf(
                        /* translators: %s: formatted date and time. */
                        esc_html__('Dernière rotation enregistrée le %s.', 'discord-bot-jlg'),
                        esc_html($formatted)
                    );
                } else {
                    esc_html_e('La date de rotation n’est pas connue. Réenregistrez un token pour consigner un nouvel horodatage.', 'discord-bot-jlg');
                }
                ?>
            </p>
        <?php endif; ?>
        <?php if (!$constant_overridden && $has_saved_token) : ?>
            <p>
                <label for="<?php echo esc_attr($delete_input_id); ?>">
                    <input type="checkbox" name="<?php echo esc_attr($delete_input_name); ?>" id="<?php echo esc_attr($delete_input_id); ?>" value="1" />
                    <?php esc_html_e('Supprimer le token enregistré lors de l\'enregistrement', 'discord-bot-jlg'); ?>
                </label>
            </p>
        <?php endif; ?>
        <?php
    }

    /**
     * Rend la case à cocher activant le mode démonstration.
     *
     * @return void
     */
    public function demo_mode_render() {
        $options   = get_option($this->option_name);
        $demo_mode = isset($options['demo_mode']) ? (int) $options['demo_mode'] : 0;
        ?>
        <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[demo_mode]"
               value="1" <?php checked($demo_mode, 1); ?> />
        <label><?php esc_html_e('Activer le mode démonstration (affiche des données fictives pour tester l\'apparence)', 'discord-bot-jlg'); ?></label>
        <p class="description"><?php esc_html_e('🎨 Parfait pour tester les styles et dispositions sans configuration Discord', 'discord-bot-jlg'); ?></p>
        <?php
    }

    /**
     * Rend la case à cocher contrôlant l'affichage du nombre d'utilisateurs en ligne.
     *
     * @return void
     */
    public function show_online_render() {
        $options = get_option($this->option_name);
        $value   = isset($options['show_online']) ? (int) $options['show_online'] : 0;
        ?>
        <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[show_online]"
               value="1" <?php checked($value, 1); ?> />
        <label><?php esc_html_e('Afficher le nombre d\'utilisateurs en ligne', 'discord-bot-jlg'); ?></label>
        <?php
    }

    /**
     * Rend la case à cocher contrôlant l'affichage du nombre total de membres.
     *
     * @return void
     */
    public function show_total_render() {
        $options = get_option($this->option_name);
        $value   = isset($options['show_total']) ? (int) $options['show_total'] : 0;
        ?>
        <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[show_total]"
               value="1" <?php checked($value, 1); ?> />
        <label><?php esc_html_e('Afficher le nombre total de membres', 'discord-bot-jlg'); ?></label>
        <?php
    }

    public function show_presence_breakdown_render() {
        $options = get_option($this->option_name);
        $value   = isset($options['show_presence_breakdown']) ? (int) $options['show_presence_breakdown'] : 0;
        ?>
        <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[show_presence_breakdown]"
               value="1" <?php checked($value, 1); ?> />
        <label><?php esc_html_e('Afficher la répartition des statuts (en ligne, inactif, DnD, etc.)', 'discord-bot-jlg'); ?></label>
        <p class="description"><?php esc_html_e('Active une carte dédiée lorsque les données du widget ou de l’API bot sont disponibles.', 'discord-bot-jlg'); ?></p>
        <?php
    }

    public function show_approximate_member_count_render() {
        $options = get_option($this->option_name);
        $value   = isset($options['show_approximate_member_count']) ? (int) $options['show_approximate_member_count'] : 0;
        ?>
        <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[show_approximate_member_count]"
               value="1" <?php checked($value, 1); ?> />
        <label><?php esc_html_e('Afficher le total approximatif fourni par l’API', 'discord-bot-jlg'); ?></label>
        <p class="description"><?php esc_html_e('Affiche une seconde carte dédiée au compteur approximate_member_count lorsque le total exact est indisponible.', 'discord-bot-jlg'); ?></p>
        <?php
    }

    public function show_premium_subscriptions_render() {
        $options = get_option($this->option_name);
        $value   = isset($options['show_premium_subscriptions']) ? (int) $options['show_premium_subscriptions'] : 0;
        ?>
        <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[show_premium_subscriptions]"
               value="1" <?php checked($value, 1); ?> />
        <label><?php esc_html_e('Afficher le nombre de boosts Nitro (premium_subscription_count)', 'discord-bot-jlg'); ?></label>
        <?php
    }

    /**
     * Rend la case à cocher pour afficher le nom du serveur.
     */
    public function show_server_name_render() {
        $options = get_option($this->option_name);
        $value   = isset($options['show_server_name']) ? (int) $options['show_server_name'] : 0;
        ?>
        <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[show_server_name]"
               value="1" <?php checked($value, 1); ?> />
        <label><?php esc_html_e('Afficher le nom du serveur lorsque disponible', 'discord-bot-jlg'); ?></label>
        <p class="description"><?php esc_html_e('Permet d\'afficher automatiquement l\'entête du serveur dans le shortcode et le bloc.', 'discord-bot-jlg'); ?></p>
        <?php
    }

    /**
     * Rend la case à cocher pour afficher l'avatar du serveur.
     */
    public function show_server_avatar_render() {
        $options = get_option($this->option_name);
        $value   = isset($options['show_server_avatar']) ? (int) $options['show_server_avatar'] : 0;
        ?>
        <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[show_server_avatar]"
               value="1" <?php checked($value, 1); ?> />
        <label><?php esc_html_e('Afficher l\'avatar du serveur (si disponible)', 'discord-bot-jlg'); ?></label>
        <p class="description"><?php esc_html_e('L\'avatar est récupéré via l\'API Discord. Il sera redimensionné selon la taille choisie dans le bloc ou le shortcode.', 'discord-bot-jlg'); ?></p>
        <?php
    }

    public function default_stat_icons_render() {
        $options = get_option($this->option_name);
        if (!is_array($options)) {
            $options = array();
        }

        $defaults = array(
            'default_icon_online'      => array('label' => __('Membres en ligne', 'discord-bot-jlg'), 'placeholder' => '🟢'),
            'default_icon_total'       => array('label' => __('Total des membres', 'discord-bot-jlg'), 'placeholder' => '👥'),
            'default_icon_presence'    => array('label' => __('Répartition des présences', 'discord-bot-jlg'), 'placeholder' => '📊'),
            'default_icon_approximate' => array('label' => __('Total approximatif', 'discord-bot-jlg'), 'placeholder' => '📈'),
            'default_icon_premium'     => array('label' => __('Boosts Nitro', 'discord-bot-jlg'), 'placeholder' => '💎'),
        );

        ?>
        <fieldset>
            <legend class="screen-reader-text"><?php esc_html_e('Icônes par défaut', 'discord-bot-jlg'); ?></legend>
            <p class="description"><?php esc_html_e('Définissez des icônes ou émojis proposés par défaut dans le shortcode, le bloc et le widget.', 'discord-bot-jlg'); ?></p>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px; max-width: 720px;">
                <?php foreach ($defaults as $option_key => $metadata) :
                    $current_value = isset($options[$option_key]) ? $options[$option_key] : '';
                    ?>
                    <label style="display: flex; flex-direction: column; gap: 4px;">
                        <span><?php echo esc_html($metadata['label']); ?></span>
                        <input type="text"
                               name="<?php echo esc_attr($this->option_name); ?>[<?php echo esc_attr($option_key); ?>]"
                               value="<?php echo esc_attr($current_value); ?>"
                               class="regular-text"
                               style="max-width: 120px;"
                               placeholder="<?php echo esc_attr($metadata['placeholder']); ?>" />
                    </label>
                <?php endforeach; ?>
            </div>
        </fieldset>
        <?php
    }

    public function default_stat_labels_render() {
        $options = get_option($this->option_name);
        if (!is_array($options)) {
            $options = array();
        }

        $main_labels = array(
            'default_label_online'      => array('label' => __('Membres en ligne', 'discord-bot-jlg'), 'placeholder' => __('En ligne', 'discord-bot-jlg')),
            'default_label_total'       => array('label' => __('Total des membres', 'discord-bot-jlg'), 'placeholder' => __('Membres', 'discord-bot-jlg')),
            'default_label_presence'    => array('label' => __('Titre de la répartition', 'discord-bot-jlg'), 'placeholder' => __('Présence par statut', 'discord-bot-jlg')),
            'default_label_approximate' => array('label' => __('Total approximatif', 'discord-bot-jlg'), 'placeholder' => __('Membres (approx.)', 'discord-bot-jlg')),
            'default_label_premium'     => array('label' => __('Boosts (libellé global)', 'discord-bot-jlg'), 'placeholder' => __('Boosts serveur', 'discord-bot-jlg')),
            'default_label_premium_singular' => array('label' => __('Boost (singulier)', 'discord-bot-jlg'), 'placeholder' => __('Boost serveur', 'discord-bot-jlg')),
            'default_label_premium_plural'   => array('label' => __('Boosts (pluriel)', 'discord-bot-jlg'), 'placeholder' => __('Boosts serveur', 'discord-bot-jlg')),
        );

        $presence_labels = array(
            'default_label_presence_online'    => array('label' => __('Présence : en ligne', 'discord-bot-jlg'), 'placeholder' => __('En ligne', 'discord-bot-jlg')),
            'default_label_presence_idle'      => array('label' => __('Présence : inactif', 'discord-bot-jlg'), 'placeholder' => __('Inactif', 'discord-bot-jlg')),
            'default_label_presence_dnd'       => array('label' => __('Présence : ne pas déranger', 'discord-bot-jlg'), 'placeholder' => __('Ne pas déranger', 'discord-bot-jlg')),
            'default_label_presence_offline'   => array('label' => __('Présence : hors ligne', 'discord-bot-jlg'), 'placeholder' => __('Hors ligne', 'discord-bot-jlg')),
            'default_label_presence_streaming' => array('label' => __('Présence : en direct', 'discord-bot-jlg'), 'placeholder' => __('En direct', 'discord-bot-jlg')),
            'default_label_presence_other'     => array('label' => __('Présence : autres', 'discord-bot-jlg'), 'placeholder' => __('Autres', 'discord-bot-jlg')),
        );

        ?>
        <fieldset>
            <legend class="screen-reader-text"><?php esc_html_e('Libellés par défaut', 'discord-bot-jlg'); ?></legend>
            <p class="description"><?php esc_html_e('Ces textes sont injectés automatiquement dans le bloc, le shortcode et le widget. Laissez vide pour conserver les libellés natifs.', 'discord-bot-jlg'); ?></p>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 12px; max-width: 900px;">
                <?php foreach ($main_labels as $option_key => $metadata) :
                    $current_value = isset($options[$option_key]) ? $options[$option_key] : '';
                    ?>
                    <label style="display: flex; flex-direction: column; gap: 4px;">
                        <span><?php echo esc_html($metadata['label']); ?></span>
                        <input type="text"
                               name="<?php echo esc_attr($this->option_name); ?>[<?php echo esc_attr($option_key); ?>]"
                               value="<?php echo esc_attr($current_value); ?>"
                               class="regular-text"
                               placeholder="<?php echo esc_attr($metadata['placeholder']); ?>" />
                    </label>
                <?php endforeach; ?>
            </div>

            <h4 style="margin-top: 18px;"><?php esc_html_e('Détails de présence', 'discord-bot-jlg'); ?></h4>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 12px; max-width: 900px;">
                <?php foreach ($presence_labels as $option_key => $metadata) :
                    $current_value = isset($options[$option_key]) ? $options[$option_key] : '';
                    ?>
                    <label style="display: flex; flex-direction: column; gap: 4px;">
                        <span><?php echo esc_html($metadata['label']); ?></span>
                        <input type="text"
                               name="<?php echo esc_attr($this->option_name); ?>[<?php echo esc_attr($option_key); ?>]"
                               value="<?php echo esc_attr($current_value); ?>"
                               class="regular-text"
                               placeholder="<?php echo esc_attr($metadata['placeholder']); ?>" />
                    </label>
                <?php endforeach; ?>
            </div>
        </fieldset>
        <?php
    }

    /**
     * Rend le sélecteur de thème par défaut.
     */
    public function default_theme_render() {
        $options = get_option($this->option_name);
        $current = isset($options['default_theme']) && discord_bot_jlg_is_allowed_theme($options['default_theme'])
            ? $options['default_theme']
            : 'discord';
        $choices = $this->get_theme_choices();
        ?>
        <select name="<?php echo esc_attr($this->option_name); ?>[default_theme]">
            <?php foreach ($choices as $value => $label) : ?>
                <option value="<?php echo esc_attr($value); ?>" <?php selected($current, $value); ?>>
                    <?php echo esc_html($label); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description"><?php esc_html_e('Ce thème sera appliqué par défaut au shortcode, au widget et au bloc Gutenberg.', 'discord-bot-jlg'); ?></p>
        <?php
    }

    /**
     * Rend la case à cocher contrôlant l'auto-rafraîchissement par défaut.
     */
    public function default_refresh_enabled_render() {
        $options = get_option($this->option_name);
        $value   = isset($options['default_refresh_enabled']) ? (int) $options['default_refresh_enabled'] : 0;
        ?>
        <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[default_refresh_enabled]"
               value="1" <?php checked($value, 1); ?> />
        <label><?php esc_html_e('Activer l\'auto-rafraîchissement pour les nouveaux blocs/shortcodes', 'discord-bot-jlg'); ?></label>
        <?php
    }

    /**
     * Rend le champ numérique dédié à l'intervalle d'auto-rafraîchissement par défaut.
     */
    public function default_refresh_interval_render() {
        $options = get_option($this->option_name);
        $value   = isset($options['default_refresh_interval'])
            ? (int) $options['default_refresh_interval']
            : 60;
        $min_refresh = Discord_Bot_JLG_API::MIN_PUBLIC_REFRESH_INTERVAL;
        ?>
        <input type="number" name="<?php echo esc_attr($this->option_name); ?>[default_refresh_interval]"
               value="<?php echo esc_attr($value); ?>"
               min="<?php echo esc_attr($min_refresh); ?>" max="3600" class="small-text" />
        <p class="description">
            <?php
            printf(
                /* translators: 1: minimum refresh interval in seconds, 2: maximum refresh interval in seconds. */
                esc_html__('Entre %1$s et %2$s secondes. Utilisé lorsque l\'auto-rafraîchissement est activé par défaut.', 'discord-bot-jlg'),
                esc_html($min_refresh),
                esc_html(number_format_i18n(3600))
            );
            ?>
        </p>
        <?php
    }

    /**
     * Retourne la liste des thèmes disponibles avec leur libellé traduit.
     *
     * @return array<string, string>
     */
    private function get_theme_choices() {
        $labels = array(
            'discord'   => __('Discord', 'discord-bot-jlg'),
            'dark'      => __('Sombre', 'discord-bot-jlg'),
            'light'     => __('Clair', 'discord-bot-jlg'),
            'minimal'   => __('Minimal', 'discord-bot-jlg'),
            'radix'     => __('Radix Structure', 'discord-bot-jlg'),
            'headless'  => __('Headless Essence', 'discord-bot-jlg'),
            'shadcn'    => __('Shadcn Minimal', 'discord-bot-jlg'),
            'bootstrap' => __('Bootstrap Fluent', 'discord-bot-jlg'),
            'semantic'  => __('Semantic Harmony', 'discord-bot-jlg'),
            'anime'     => __('Anime Pulse', 'discord-bot-jlg'),
        );

        $choices = array();
        foreach (discord_bot_jlg_get_available_themes() as $theme) {
            if (isset($labels[$theme])) {
                $choices[$theme] = $labels[$theme];
            } else {
                $choices[$theme] = ucfirst($theme);
            }
        }

        if (empty($choices)) {
            $choices = $labels;
        }

        return $choices;
    }

    /**
     * Rend le champ texte permettant de définir le titre du widget.
     *
     * @return void
     */
    public function widget_title_render() {
        $options = get_option($this->option_name);
        ?>
        <input type="text" name="<?php echo esc_attr($this->option_name); ?>[widget_title]"
               value="<?php echo esc_attr(isset($options['widget_title']) ? $options['widget_title'] : ''); ?>"
               class="regular-text" />
        <?php
    }

    /**
     * Rend le champ de saisie de l'URL d'invitation Discord.
     */
    public function invite_url_render() {
        $options = get_option($this->option_name);
        $value   = isset($options['invite_url']) ? esc_url($options['invite_url']) : '';
        ?>
        <input type="url" name="<?php echo esc_attr($this->option_name); ?>[invite_url]"
               value="<?php echo esc_attr($value); ?>"
               class="regular-text"
               placeholder="https://discord.gg/xxxx" />
        <p class="description"><?php esc_html_e('Lien d\'invitation utilisé pour le bouton d\'appel à l\'action.', 'discord-bot-jlg'); ?></p>
        <?php
    }

    /**
     * Rend le champ texte pour personnaliser le libellé du bouton d'invitation.
     */
    public function invite_label_render() {
        $options = get_option($this->option_name);
        $value   = isset($options['invite_label']) ? $options['invite_label'] : '';
        ?>
        <input type="text" name="<?php echo esc_attr($this->option_name); ?>[invite_label]"
               value="<?php echo esc_attr($value); ?>"
               class="regular-text"
               placeholder="<?php echo esc_attr__('Rejoindre le serveur', 'discord-bot-jlg'); ?>" />
        <p class="description"><?php esc_html_e('Texte du bouton permettant aux visiteurs de rejoindre votre serveur.', 'discord-bot-jlg'); ?></p>
        <?php
    }

    /**
     * Rend le champ numérique dédié au réglage de la durée du cache.
     *
     * @return void
     */
    public function cache_duration_render() {
        $options = get_option($this->option_name);
        $min_cache_duration = max(
            60,
            defined('Discord_Bot_JLG_API::MIN_PUBLIC_REFRESH_INTERVAL')
                ? (int) Discord_Bot_JLG_API::MIN_PUBLIC_REFRESH_INTERVAL
                : 60
        );
        ?>
        <input type="number" name="<?php echo esc_attr($this->option_name); ?>[cache_duration]"
               value="<?php echo esc_attr(isset($options['cache_duration']) ? $options['cache_duration'] : ''); ?>"
               min="<?php echo esc_attr($min_cache_duration); ?>" max="3600" class="small-text" />
        <p class="description">
            <?php
            printf(
                /* translators: 1: minimum cache duration in seconds, 2: maximum cache duration in seconds. */
                esc_html__('Minimum %1$s secondes, maximum %2$s secondes (1 heure)', 'discord-bot-jlg'),
                esc_html(number_format_i18n($min_cache_duration)),
                esc_html(number_format_i18n(3600))
            );
            ?>
        </p>
        <?php
    }

    /**
     * Rend la zone de texte pour ajouter du CSS personnalisé.
     *
     * @return void
     */
    public function custom_css_render() {
        $options = get_option($this->option_name);
        ?>
        <textarea name="<?php echo esc_attr($this->option_name); ?>[custom_css]" rows="5" cols="50"><?php echo esc_textarea(isset($options['custom_css']) ? $options['custom_css'] : ''); ?></textarea>
        <p class="description"><?php esc_html_e('CSS personnalisé pour styliser l\'affichage', 'discord-bot-jlg'); ?></p>
        <?php
    }

    public function analytics_retention_render() {
        $options = get_option($this->option_name);
        $default_retention = defined('DISCORD_BOT_JLG_ANALYTICS_RETENTION_DEFAULT')
            ? (int) DISCORD_BOT_JLG_ANALYTICS_RETENTION_DEFAULT
            : Discord_Bot_JLG_Analytics::DEFAULT_RETENTION_DAYS;
        $value = isset($options['analytics_retention_days'])
            ? max(0, (int) $options['analytics_retention_days'])
            : $default_retention;
        ?>
        <input type="number"
               name="<?php echo esc_attr($this->option_name); ?>[analytics_retention_days]"
               value="<?php echo esc_attr($value); ?>"
               min="0"
               max="365"
               class="small-text" />
        <p class="description"><?php esc_html_e('Nombre de jours conservés (0 désactive la purge automatique).', 'discord-bot-jlg'); ?></p>
        <?php
    }

    public function alerts_section_callback() {
        ?>
        <p>
            <?php esc_html_e('Configurez les notifications “pro” déclenchées lorsque la présence chute anormalement. Les alertes se basent sur la moyenne des 7 derniers jours.', 'discord-bot-jlg'); ?>
        </p>
        <?php
    }

    public function analytics_alerts_enabled_render() {
        $options = get_option($this->option_name);
        $enabled = isset($options['analytics_alerts_enabled']) ? (int) $options['analytics_alerts_enabled'] : 0;
        ?>
        <label>
            <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[analytics_alerts_enabled]" value="1" <?php checked($enabled, 1); ?> />
            <?php esc_html_e('Activer les alertes en cas de baisse marquée de l’activité.', 'discord-bot-jlg'); ?>
        </label>
        <p class="description"><?php esc_html_e('Analyse le dernier snapshot vs. la moyenne des 7 derniers jours (hors point courant).', 'discord-bot-jlg'); ?></p>
        <?php
    }

    public function analytics_alert_drop_render() {
        $options = get_option($this->option_name);
        $value = isset($options['analytics_alert_drop_percent'])
            ? max(5, (int) $options['analytics_alert_drop_percent'])
            : 35;
        ?>
        <input type="number"
               name="<?php echo esc_attr($this->option_name); ?>[analytics_alert_drop_percent]"
               value="<?php echo esc_attr($value); ?>"
               min="5"
               max="95"
               class="small-text" />
        <p class="description"><?php esc_html_e('Pourcentage de baisse minimum avant d’envoyer une alerte (comparé à la moyenne précédente).', 'discord-bot-jlg'); ?></p>
        <?php
    }

    public function analytics_alert_recipients_render() {
        $options = get_option($this->option_name);
        $value = isset($options['analytics_alert_recipients']) ? $options['analytics_alert_recipients'] : '';
        ?>
        <textarea name="<?php echo esc_attr($this->option_name); ?>[analytics_alert_recipients]" rows="3" cols="50" placeholder="ops@example.com&#10;marketing@example.com"><?php echo esc_textarea($value); ?></textarea>
        <p class="description"><?php esc_html_e('Saisissez une adresse par ligne (ou séparées par des virgules).', 'discord-bot-jlg'); ?></p>
        <?php
    }

    public function analytics_alert_webhook_render() {
        $options = get_option($this->option_name);
        $value = isset($options['analytics_alert_webhook']) ? esc_url($options['analytics_alert_webhook']) : '';
        ?>
        <input type="url"
               name="<?php echo esc_attr($this->option_name); ?>[analytics_alert_webhook]"
               value="<?php echo esc_attr($value); ?>"
               class="regular-text"
               placeholder="https://hooks.slack.com/..." />
        <p class="description"><?php esc_html_e('Optionnel. Notification envoyée en JSON (HTTPS) pour Slack, Discord, etc.', 'discord-bot-jlg'); ?></p>
        <?php
    }

    public function analytics_alert_webhook_secret_render() {
        $options = get_option($this->option_name);
        $value = isset($options['analytics_alert_webhook_secret'])
            ? sanitize_text_field($options['analytics_alert_webhook_secret'])
            : '';
        ?>
        <input type="text"
               name="<?php echo esc_attr($this->option_name); ?>[analytics_alert_webhook_secret]"
               value="<?php echo esc_attr($value); ?>"
               class="regular-text"
               maxlength="128" />
        <p class="description"><?php esc_html_e('Clé secrète utilisée pour valider la signature HMAC des webhooks entrants.', 'discord-bot-jlg'); ?></p>
        <?php
    }

    public function analytics_alert_cooldown_render() {
        $options = get_option($this->option_name);
        $value = isset($options['analytics_alert_cooldown']) ? max(5, (int) $options['analytics_alert_cooldown']) : 60;
        ?>
        <input type="number"
               name="<?php echo esc_attr($this->option_name); ?>[analytics_alert_cooldown]"
               value="<?php echo esc_attr($value); ?>"
               min="5"
               max="1440"
               class="small-text" />
        <p class="description"><?php esc_html_e('Temps minimal entre deux alertes pour le même profil (en minutes).', 'discord-bot-jlg'); ?></p>
        <?php
    }

    /**
     * Affiche la page principale de configuration du plugin.
     *
     * @return void
     */
    public function options_page() {
        $this->handle_api_key_actions();

        $tabs        = $this->get_admin_tabs();
        $current_tab = $this->get_current_admin_tab($tabs);

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('🎮 Discord Bot - JLG - Configuration', 'discord-bot-jlg'); ?></h1>
            <?php settings_errors('discord_stats_settings'); ?>
            <?php $this->handle_test_connection_request(); ?>

            <?php $this->render_admin_tabs_navigation($tabs, $current_tab); ?>

            <div class="discord-bot-settings-layout">
                <?php
                $this->render_options_main_content($current_tab, $tabs[$current_tab]);
                $this->render_options_sidebar($current_tab, $tabs[$current_tab]);
                ?>
            </div>

            <?php $this->render_admin_footer_note(); ?>
        </div>
        <?php
    }

    private function handle_api_key_actions() {
        if (empty($_POST['discord_bot_jlg_api_keys_action'])) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
            return;
        }

        $requested_tab = isset($_REQUEST['tab']) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- lecture seule.
            ? sanitize_key(wp_unslash($_REQUEST['tab']))
            : '';

        if ('api_keys' !== $requested_tab) {
            return;
        }

        if (!Discord_Bot_JLG_Capabilities::current_user_can('manage_profiles')) {
            add_settings_error(
                'discord_stats_settings',
                'discord_bot_jlg_api_key_permissions',
                esc_html__('Accès refusé : seuls les administrateurs peuvent gérer les clés API.', 'discord-bot-jlg'),
                'error'
            );

            return;
        }

        if (!check_admin_referer('discord_bot_jlg_api_keys_action', 'discord_bot_jlg_api_keys_nonce')) {
            add_settings_error(
                'discord_stats_settings',
                'discord_bot_jlg_api_key_nonce',
                esc_html__('Échec de la vérification de sécurité pour l’action sur les clés API.', 'discord-bot-jlg'),
                'error'
            );

            return;
        }

        $action = sanitize_key(wp_unslash($_POST['discord_bot_jlg_api_keys_action']));

        switch ($action) {
            case 'generate':
                $this->process_api_key_generation();
                break;
            case 'revoke':
                $this->process_api_key_revocation();
                break;
        }
    }

    /**
     * Traite la demande de test de connexion depuis la page d'options.
     */
    private function handle_test_connection_request() {
        $has_test_flag = isset($_POST['test_connection']) || isset($_POST['test_connection_profile']);

        if (!$has_test_flag) {
            return;
        }

        $nonce_fields = array(
            'discord_test_connection_nonce_default',
            'discord_test_connection_nonce_profile',
            'discord_test_connection_nonce_sidebar',
        );

        $nonce_verified = false;

        foreach ($nonce_fields as $nonce_field) {
            if (!isset($_POST[$nonce_field])) {
                continue;
            }

            if (!check_admin_referer('discord_test_connection', $nonce_field, false)) {
                return;
            }

            $nonce_verified = true;
            break;
        }

        if (!$nonce_verified) {
            return;
        }

        if (!Discord_Bot_JLG_Capabilities::current_user_can('manage_profiles')) {
            add_settings_error(
                'discord_stats_settings',
                'discord_bot_jlg_access_denied',
                esc_html__('Accès refusé : vous n\'avez pas les droits suffisants pour tester la connexion Discord.', 'discord-bot-jlg'),
                'error'
            );

            return;
        }

        $profile_key = '';
        if (isset($_POST['test_connection_profile'])) {
            $profile_key = discord_bot_jlg_sanitize_profile_key(wp_unslash($_POST['test_connection_profile']));
        }

        if (isset($_POST['current_setup_step'])) {
            $requested_step = sanitize_key(wp_unslash($_POST['current_setup_step']));
            if ('' !== $requested_step) {
                $this->forced_setup_step = $requested_step;
            }
        }

        if ('' !== $profile_key && 'default' !== $profile_key) {
            $this->forced_setup_step = 'profiles';
        }

        $this->test_discord_connection($profile_key);
    }

    private function process_api_key_generation() {
        if (!($this->api_key_repository instanceof Discord_Bot_JLG_API_Key_Repository)) {
            add_settings_error(
                'discord_stats_settings',
                'discord_bot_jlg_api_key_repository_missing',
                esc_html__('Impossible de générer une clé API : stockage indisponible.', 'discord-bot-jlg'),
                'error'
            );

            return;
        }

        $label = isset($_POST['api_key_label'])
            ? sanitize_text_field(wp_unslash($_POST['api_key_label']))
            : '';
        $profile_keys = isset($_POST['api_key_profiles'])
            ? array_map('sanitize_text_field', (array) wp_unslash($_POST['api_key_profiles']))
            : array();
        $scopes = isset($_POST['api_key_scopes'])
            ? array_map('sanitize_key', (array) wp_unslash($_POST['api_key_scopes']))
            : array();
        $expiration_days = isset($_POST['api_key_expiration_days'])
            ? max(0, (int) $_POST['api_key_expiration_days'])
            : 0;

        $expires_at = '';
        if ($expiration_days > 0) {
            $expires_timestamp = current_time('timestamp', true) + ($expiration_days * DAY_IN_SECONDS);
            $expires_at = gmdate('Y-m-d H:i:s', $expires_timestamp);
        }

        $created_by = function_exists('get_current_user_id') ? (int) get_current_user_id() : 0;

        $result = $this->api_key_repository->create_key(
            array(
                'label'        => $label,
                'profile_keys' => $profile_keys,
                'scopes'       => $scopes,
                'created_by'   => $created_by,
                'expires_at'   => $expires_at,
            )
        );

        if (!is_array($result) || empty($result['key'])) {
            add_settings_error(
                'discord_stats_settings',
                'discord_bot_jlg_api_key_creation_failed',
                esc_html__('Impossible de générer la clé API. Veuillez réessayer.', 'discord-bot-jlg'),
                'error'
            );

            return;
        }

        $this->last_generated_api_key = $result['key'];
        $this->last_generated_api_key_label = isset($result['label']) ? $result['label'] : '';

        add_settings_error(
            'discord_stats_settings',
            'discord_bot_jlg_api_key_created',
            esc_html__('Nouvelle clé API générée. Copiez-la immédiatement depuis le panneau ci-dessous.', 'discord-bot-jlg'),
            'updated'
        );

        if ($this->event_logger instanceof Discord_Bot_JLG_Event_Logger) {
            $profiles = isset($result['profiles']) && is_array($result['profiles']) ? $result['profiles'] : array();
            $scopes_logged = isset($result['scopes']) && is_array($result['scopes']) ? $result['scopes'] : array();

            $this->event_logger->log(
                'rest_api_key',
                array(
                    'channel' => 'admin',
                    'action'  => 'generated',
                    'key_id'  => isset($result['id']) ? (int) $result['id'] : 0,
                    'profiles'=> $profiles,
                    'scopes'  => $scopes_logged,
                )
            );
        }
    }

    private function process_api_key_revocation() {
        if (!($this->api_key_repository instanceof Discord_Bot_JLG_API_Key_Repository)) {
            add_settings_error(
                'discord_stats_settings',
                'discord_bot_jlg_api_key_repository_missing',
                esc_html__('Impossible de révoquer la clé API : stockage indisponible.', 'discord-bot-jlg'),
                'error'
            );

            return;
        }

        $key_id = isset($_POST['api_key_id']) ? (int) $_POST['api_key_id'] : 0;
        if ($key_id <= 0) {
            add_settings_error(
                'discord_stats_settings',
                'discord_bot_jlg_api_key_invalid',
                esc_html__('Clé API introuvable ou identifiant invalide.', 'discord-bot-jlg'),
                'error'
            );

            return;
        }

        $record = $this->api_key_repository->get_key_by_id($key_id);
        $label = '';
        if (is_array($record) && !empty($record['label'])) {
            $label = sanitize_text_field($record['label']);
        }

        $this->api_key_repository->revoke_key($key_id);

        $message = ($label)
            ? sprintf(
                /* translators: %s: API key label. */
                esc_html__('La clé API « %s » a été révoquée.', 'discord-bot-jlg'),
                $label
            )
            : esc_html__('La clé API a été révoquée.', 'discord-bot-jlg');

        add_settings_error(
            'discord_stats_settings',
            'discord_bot_jlg_api_key_revoked',
            $message,
            'updated'
        );

        if ($this->event_logger instanceof Discord_Bot_JLG_Event_Logger) {
            $this->event_logger->log(
                'rest_api_key',
                array(
                    'channel' => 'admin',
                    'action'  => 'revoked',
                    'key_id'  => $key_id,
                    'label'   => $label,
                )
            );
        }
    }

    /**
     * Retourne la configuration des onglets disponibles.
     *
     * @return array
     */
    private function get_admin_tabs() {
        $tabs = array(
            'connection'   => array(
                'label'          => __('Connexion', 'discord-bot-jlg'),
                'icon'           => '🔌',
                'sections'       => array(
                    array(
                        'id'           => 'discord_stats_api_section',
                        'submit_label' => esc_html__('Enregistrer la configuration API', 'discord-bot-jlg'),
                    ),
                    array(
                        'id'           => 'discord_stats_profiles_section',
                        'submit_label' => esc_html__('Mettre à jour les profils', 'discord-bot-jlg'),
                    ),
                ),
                'sidebar_panels' => array('connection_test', 'quick_links'),
            ),
            'appearance'   => array(
                'label'          => __('Apparence', 'discord-bot-jlg'),
                'icon'           => '🎨',
                'sections'       => array(
                    array(
                        'id'           => 'discord_stats_display_section',
                        'submit_label' => esc_html__('Mettre à jour l\'affichage', 'discord-bot-jlg'),
                    ),
                    array(
                        'id'           => 'discord_stats_cta_section',
                        'submit_label' => esc_html__('Mettre à jour l\'engagement', 'discord-bot-jlg'),
                    ),
                    array(
                        'id'           => 'discord_stats_custom_css_section',
                        'submit_label' => esc_html__('Enregistrer le CSS personnalisé', 'discord-bot-jlg'),
                    ),
                ),
                'sidebar_panels' => array('appearance_shortcuts', 'quick_links'),
            ),
            'automation'   => array(
                'label'          => __('Automatisation', 'discord-bot-jlg'),
                'icon'           => '⚙️',
                'sections'       => array(
                    array(
                        'id'           => 'discord_stats_automation_section',
                        'submit_label' => esc_html__('Enregistrer les automatismes', 'discord-bot-jlg'),
                    ),
                ),
                'sidebar_panels' => array('automation_tips', 'quick_links'),
            ),
            'api_keys'    => array(
                'label'          => __('Clés API', 'discord-bot-jlg'),
                'icon'           => '🔑',
                'render_callback'=> array($this, 'render_api_keys_admin_tab'),
                'sidebar_panels' => array('api_keys_help', 'quick_links'),
            ),
            'monitoring'   => array(
                'label'          => __('Surveillance', 'discord-bot-jlg'),
                'icon'           => '📊',
                'render_callback'=> array($this, 'render_monitoring_dashboard'),
                'sidebar_panels' => array('monitoring_help'),
            ),
        );

        /**
         * Filtre la configuration des onglets de l'administration.
         *
         * @param array $tabs Onglets disponibles.
         */
        return apply_filters('discord_bot_jlg_admin_tabs', $tabs);
    }

    /**
     * Détermine l'onglet en cours à partir de la requête.
     *
     * @param array $tabs Onglets disponibles.
     *
     * @return string
     */
    private function get_current_admin_tab(array $tabs) {
        $default_tab = key($tabs);

        if (!isset($_GET['tab'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Lecture seule.
            return (string) $default_tab;
        }

        $requested = sanitize_key(wp_unslash($_GET['tab'])); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Lecture seule.

        if (!isset($tabs[$requested])) {
            return (string) $default_tab;
        }

        return (string) $requested;
    }

    /**
     * Rend la navigation des onglets.
     *
     * @param array  $tabs        Liste des onglets.
     * @param string $current_tab Onglet actif.
     */
    private function render_admin_tabs_navigation(array $tabs, $current_tab) {
        if (empty($tabs)) {
            return;
        }

        $base_url = admin_url('admin.php?page=discord-bot-jlg');

        printf(
            "<nav class=\"nav-tab-wrapper discord-bot-nav-tab-wrapper\" aria-label=\"%s\">",
            esc_attr__('Navigation de Discord Bot', 'discord-bot-jlg')
        );

        foreach ($tabs as $tab_key => $tab) {
            $tab_key   = sanitize_key($tab_key);
            $label     = isset($tab['label']) ? $tab['label'] : '';
            $icon      = isset($tab['icon']) ? $tab['icon'] : '';
            $is_active = ($tab_key === $current_tab);
            $classes   = 'nav-tab' . ($is_active ? ' nav-tab-active' : '');
            $url       = add_query_arg('tab', $tab_key, $base_url);

            printf(
                "<a class=\"%1\$s\" href=\"%2\$s\" role=\"tab\" aria-selected=\"%3\$s\">",
                esc_attr($classes),
                esc_url($url),
                $is_active ? 'true' : 'false'
            );

            if ('' !== $icon) {
                printf(
                    "<span class=\"discord-bot-tab-icon\" aria-hidden=\"true\">%s</span>",
                    esc_html($icon)
                );
            }

            printf("<span class=\"discord-bot-tab-label\">%s</span>", esc_html($label));
            echo '</a>';
        }

        echo '</nav>';
    }

    private function render_api_keys_admin_tab() {
        ?>
        <div class="discord-bot-settings-main" aria-live="polite">
            <?php
            if (!($this->api_key_repository instanceof Discord_Bot_JLG_API_Key_Repository)) {
                echo '<p>' . esc_html__('Le stockage des clés API est indisponible sur ce site. Vérifiez les droits de la base de données.', 'discord-bot-jlg') . '</p>';
                echo '</div>';
                return;
            }

            if ('' !== $this->last_generated_api_key) {
                $label = ('' !== $this->last_generated_api_key_label)
                    ? sprintf(
                        /* translators: %s: API key label. */
                        esc_html__('Clé « %s »', 'discord-bot-jlg'),
                        esc_html($this->last_generated_api_key_label)
                    )
                    : esc_html__('Clé API générée', 'discord-bot-jlg');

                printf(
                    '<div class="notice notice-success"><p><strong>%1$s</strong> : <code class="discord-api-key-secret">%2$s</code></p><p class="description">%3$s</p></div>',
                    $label,
                    esc_html($this->last_generated_api_key),
                    esc_html__('Conservez cette clé en lieu sûr : elle ne sera plus affichée.', 'discord-bot-jlg')
                );
            }

            $available_profiles = array(
                'default' => __('Profil principal', 'discord-bot-jlg'),
            );

            $configured_profiles = $this->api->get_server_profiles(false);
            if (is_array($configured_profiles)) {
                foreach ($configured_profiles as $profile) {
                    if (!is_array($profile)) {
                        continue;
                    }

                    $profile_key = isset($profile['key']) ? discord_bot_jlg_sanitize_profile_key($profile['key']) : '';
                    if ('' === $profile_key || isset($available_profiles[$profile_key])) {
                        continue;
                    }

                    $label = isset($profile['label']) ? sanitize_text_field($profile['label']) : $profile_key;
                    $available_profiles[$profile_key] = $label;
                }
            }

            $allowed_scopes = $this->api_key_repository->get_allowed_scopes();
            $scope_labels = array(
                'stats'     => __('Lecture des statistiques instantanées', 'discord-bot-jlg'),
                'analytics' => __('Lecture des analyses agrégées', 'discord-bot-jlg'),
                'export'    => __('Export des données analytics', 'discord-bot-jlg'),
            );

            $form_action = add_query_arg(
                array(
                    'page' => 'discord-bot-jlg',
                    'tab'  => 'api_keys',
                ),
                admin_url('admin.php')
            );
            ?>
            <div class="components-card discord-admin-card">
                <div class="components-card__body">
                    <h2 class="discord-admin-card__title"><?php esc_html_e('🔐 Générer une nouvelle clé API', 'discord-bot-jlg'); ?></h2>
                    <p class="description"><?php esc_html_e('Chaque clé est limitée aux profils et aux scopes sélectionnés. Utilisez-les pour les intégrations externes (tableaux de bord, exports automatisés, etc.).', 'discord-bot-jlg'); ?></p>
                    <form method="post" action="<?php echo esc_url($form_action); ?>" class="discord-api-key-form">
                        <?php wp_nonce_field('discord_bot_jlg_api_keys_action', 'discord_bot_jlg_api_keys_nonce'); ?>
                        <input type="hidden" name="discord_bot_jlg_api_keys_action" value="generate" />
                        <p>
                            <label for="discord-api-key-label"><strong><?php esc_html_e('Nom interne', 'discord-bot-jlg'); ?></strong></label><br />
                            <input type="text" id="discord-api-key-label" name="api_key_label" class="regular-text" placeholder="<?php esc_attr_e('Tableau de bord marketing', 'discord-bot-jlg'); ?>" />
                        </p>
                        <fieldset class="discord-api-key-fieldset">
                            <legend><strong><?php esc_html_e('Profils autorisés', 'discord-bot-jlg'); ?></strong></legend>
                            <label>
                                <input type="checkbox" name="api_key_profiles[]" value="__all__" />
                                <?php esc_html_e('Tous les profils actuels et futurs', 'discord-bot-jlg'); ?>
                            </label>
                            <?php foreach ($available_profiles as $profile_key => $label) : ?>
                                <label>
                                    <input type="checkbox" name="api_key_profiles[]" value="<?php echo esc_attr($profile_key); ?>" />
                                    <?php echo esc_html($label); ?>
                                </label>
                            <?php endforeach; ?>
                        </fieldset>
                        <fieldset class="discord-api-key-fieldset">
                            <legend><strong><?php esc_html_e('Permissions REST', 'discord-bot-jlg'); ?></strong></legend>
                            <?php foreach ($allowed_scopes as $scope) :
                                $label = isset($scope_labels[$scope]) ? $scope_labels[$scope] : $scope;
                                ?>
                                <label>
                                    <input type="checkbox" name="api_key_scopes[]" value="<?php echo esc_attr($scope); ?>" />
                                    <?php echo esc_html($label); ?>
                                </label>
                            <?php endforeach; ?>
                            <p class="description"><?php esc_html_e('Sélectionnez au moins une permission. Sans sélection, la lecture des statistiques instantanées sera autorisée par défaut.', 'discord-bot-jlg'); ?></p>
                        </fieldset>
                        <p>
                            <label for="discord-api-key-expiration"><strong><?php esc_html_e('Expiration (en jours)', 'discord-bot-jlg'); ?></strong></label><br />
                            <input type="number" id="discord-api-key-expiration" name="api_key_expiration_days" min="0" max="365" step="1" value="0" class="small-text" />
                            <span class="description"><?php esc_html_e('Laissez 0 pour une clé sans date de fin.', 'discord-bot-jlg'); ?></span>
                        </p>
                        <?php submit_button(esc_html__('Générer la clé API', 'discord-bot-jlg'), 'primary'); ?>
                    </form>
                </div>
            </div>

            <?php
            $keys = $this->api_key_repository->list_keys(array('include_revoked' => true));
            ?>
            <div class="components-card discord-admin-card">
                <div class="components-card__body">
                    <h2 class="discord-admin-card__title"><?php esc_html_e('🗝️ Clés existantes', 'discord-bot-jlg'); ?></h2>
                    <?php if (empty($keys)) : ?>
                        <p><?php esc_html_e('Aucune clé API enregistrée pour le moment.', 'discord-bot-jlg'); ?></p>
                    <?php else : ?>
                        <table class="widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Étiquette', 'discord-bot-jlg'); ?></th>
                                    <th><?php esc_html_e('Profils', 'discord-bot-jlg'); ?></th>
                                    <th><?php esc_html_e('Permissions', 'discord-bot-jlg'); ?></th>
                                    <th><?php esc_html_e('Usage', 'discord-bot-jlg'); ?></th>
                                    <th><?php esc_html_e('Expiration', 'discord-bot-jlg'); ?></th>
                                    <th><?php esc_html_e('Dernier accès', 'discord-bot-jlg'); ?></th>
                                    <th><?php esc_html_e('Statut', 'discord-bot-jlg'); ?></th>
                                    <th class="column-actions">&nbsp;</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($keys as $key) :
                                    $profiles = isset($key['profiles']) && is_array($key['profiles']) ? $key['profiles'] : array();
                                    $profile_labels = array();
                                    if (in_array('__all__', $profiles, true) || in_array('all', $profiles, true) || in_array('*', $profiles, true)) {
                                        $profile_labels[] = esc_html__('Tous les profils', 'discord-bot-jlg');
                                    } else {
                                        foreach ($profiles as $profile_key) {
                                            $profile_labels[] = isset($available_profiles[$profile_key])
                                                ? esc_html($available_profiles[$profile_key])
                                                : esc_html($profile_key);
                                        }
                                    }

                                    $scopes = isset($key['scopes']) && is_array($key['scopes']) ? $key['scopes'] : array();
                                    $scope_labels_rendered = array();
                                    foreach ($scopes as $scope) {
                                        $scope_labels_rendered[] = isset($scope_labels[$scope]) ? esc_html($scope_labels[$scope]) : esc_html($scope);
                                    }

                                    $usage_count = isset($key['usage_count']) ? (int) $key['usage_count'] : 0;
                                    $fingerprint = isset($key['fingerprint']) ? substr(sanitize_text_field($key['fingerprint']), 0, 12) : '';
                                    $expires_at = isset($key['expires_at']) ? $key['expires_at'] : '';
                                    $last_used_at = isset($key['last_used_at']) ? $key['last_used_at'] : '';
                                    $revoked_at = isset($key['revoked_at']) ? $key['revoked_at'] : '';

                                    $expires_timestamp = ('' !== $expires_at) ? strtotime($expires_at . ' +0000') : 0;
                                    $is_expired = ($expires_timestamp > 0 && $expires_timestamp <= current_time('timestamp', true));
                                    $is_revoked = ('' !== $revoked_at);
                                    $status_label = $is_revoked
                                        ? esc_html__('Révoquée', 'discord-bot-jlg')
                                        : ($is_expired ? esc_html__('Expirée', 'discord-bot-jlg') : esc_html__('Active', 'discord-bot-jlg'));
                                    $status_class = $is_revoked ? 'status-revoked' : ($is_expired ? 'status-expired' : 'status-active');
                                    ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo esc_html(isset($key['label']) ? $key['label'] : ''); ?></strong><br />
                                            <?php if ('' !== $fingerprint) : ?>
                                                <code><?php echo esc_html($fingerprint); ?></code>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo !empty($profile_labels) ? esc_html(implode(', ', $profile_labels)) : '—'; ?></td>
                                        <td><?php echo !empty($scope_labels_rendered) ? esc_html(implode(', ', $scope_labels_rendered)) : '—'; ?></td>
                                        <td><?php echo esc_html(number_format_i18n($usage_count)); ?></td>
                                        <td><?php echo esc_html($this->format_api_key_datetime($expires_at)); ?></td>
                                        <td><?php echo esc_html($this->format_api_key_datetime($last_used_at)); ?></td>
                                        <td><span class="discord-api-key-status <?php echo esc_attr($status_class); ?>"><?php echo $status_label; ?></span></td>
                                        <td>
                                            <?php if (!$is_revoked) : ?>
                                                <form method="post" action="<?php echo esc_url($form_action); ?>">
                                                    <?php wp_nonce_field('discord_bot_jlg_api_keys_action', 'discord_bot_jlg_api_keys_nonce'); ?>
                                                    <input type="hidden" name="discord_bot_jlg_api_keys_action" value="revoke" />
                                                    <input type="hidden" name="api_key_id" value="<?php echo esc_attr((int) $key['id']); ?>" />
                                                    <?php submit_button(esc_html__('Révoquer', 'discord-bot-jlg'), 'delete', 'submit', false); ?>
                                                </form>
                                            <?php else : ?>
                                                <span class="dashicons dashicons-lock" aria-hidden="true"></span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <?php
            $events = ($this->event_logger instanceof Discord_Bot_JLG_Event_Logger)
                ? $this->event_logger->get_events(
                    array(
                        'type'  => 'rest_api_key',
                        'limit' => 10,
                    )
                )
                : array();
            ?>
            <div class="components-card discord-admin-card">
                <div class="components-card__body">
                    <h2 class="discord-admin-card__title"><?php esc_html_e('📝 Derniers événements', 'discord-bot-jlg'); ?></h2>
                    <?php if (empty($events)) : ?>
                        <p><?php esc_html_e('Aucun accès API récent enregistré.', 'discord-bot-jlg'); ?></p>
                    <?php else : ?>
                        <ul class="discord-admin-list">
                            <?php foreach ($events as $event) :
                                $timestamp = isset($event['timestamp']) ? (int) $event['timestamp'] : 0;
                                $context = isset($event['context']) && is_array($event['context']) ? $event['context'] : array();
                                $action = isset($context['action']) ? sanitize_text_field($context['action']) : '';
                                $outcome = isset($context['outcome']) ? sanitize_text_field($context['outcome']) : '';
                                $scope = isset($context['scope']) ? sanitize_text_field($context['scope']) : '';
                                $profile = isset($context['profile_key']) ? sanitize_text_field($context['profile_key']) : '';
                                $fingerprint = isset($context['fingerprint']) ? substr(sanitize_text_field($context['fingerprint']), 0, 12) : '';
                                $label = isset($context['label']) ? sanitize_text_field($context['label']) : '';
                                $key_id = isset($context['key_id']) ? (int) $context['key_id'] : 0;

                                $timestamp_label = ($timestamp > 0)
                                    ? discord_bot_jlg_format_datetime('Y-m-d H:i', $timestamp)
                                    : '';

                                $description_parts = array();
                                if ('' !== $action) {
                                    $description_parts[] = sprintf(esc_html__('Action : %s', 'discord-bot-jlg'), esc_html($action));
                                }
                                if ('' !== $outcome) {
                                    $description_parts[] = sprintf(esc_html__('Résultat : %s', 'discord-bot-jlg'), esc_html($outcome));
                                }
                                if ('' !== $scope) {
                                    $description_parts[] = sprintf(esc_html__('Scope : %s', 'discord-bot-jlg'), esc_html($scope));
                                }
                                if ('' !== $profile) {
                                    $description_parts[] = sprintf(esc_html__('Profil : %s', 'discord-bot-jlg'), esc_html($profile));
                                }
                                if ('' !== $fingerprint) {
                                    $description_parts[] = sprintf(esc_html__('Empreinte : %s', 'discord-bot-jlg'), esc_html($fingerprint));
                                }
                                if ($key_id > 0) {
                                    $description_parts[] = sprintf(esc_html__('ID : %d', 'discord-bot-jlg'), $key_id);
                                }
                                if ('' !== $label) {
                                    $description_parts[] = sprintf(esc_html__('Clé : %s', 'discord-bot-jlg'), esc_html($label));
                                }
                                ?>
                                <li>
                                    <strong><?php echo esc_html($timestamp_label); ?></strong>
                                    <?php if (!empty($description_parts)) : ?>
                                        — <?php echo esc_html(implode(' · ', $description_parts)); ?>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }

    private function format_api_key_datetime($datetime) {
        if (!is_string($datetime) || '' === $datetime) {
            return '—';
        }

        $timestamp = strtotime($datetime . ' +0000');
        if ($timestamp <= 0) {
            return '—';
        }

        if (function_exists('get_date_from_gmt')) {
            $format = get_option('date_format') . ' ' . get_option('time_format');
            $formatted = get_date_from_gmt($datetime, $format);
            if ('' !== $formatted) {
                return $formatted;
            }
        }

        return gmdate('Y-m-d H:i', $timestamp) . ' UTC';
    }

    /**
     * Affiche le tableau de bord de surveillance.
     */
    private function render_monitoring_dashboard() {
        $filters = $this->get_monitoring_filters();

        $snapshot = $this->api->get_admin_health_snapshot(
            array(
                'events_limit' => 12,
                'event_type'   => $filters['type'],
                'channel'      => $filters['channel'],
                'profile_key'  => $filters['profile'],
                'server_id'    => $filters['server_id'],
            )
        );

        $rate_limit   = isset($snapshot['rate_limit']) && is_array($snapshot['rate_limit']) ? $snapshot['rate_limit'] : array();
        $last_error   = isset($snapshot['last_error']) && is_array($snapshot['last_error']) ? $snapshot['last_error'] : null;
        $last_success = isset($snapshot['last_success']) && is_array($snapshot['last_success']) ? $snapshot['last_success'] : null;
        $timeline_entries = isset($snapshot['events']) && is_array($snapshot['events']) ? $snapshot['events'] : array();
        $fallback     = isset($snapshot['fallback']) && is_array($snapshot['fallback']) ? $snapshot['fallback'] : array();
        $retry_after  = isset($snapshot['retry_after']) ? (int) $snapshot['retry_after'] : 0;

        $now_gmt = current_time('timestamp', true);

        $event_type_options = array(
            ''                => __('Tous les types', 'discord-bot-jlg'),
            'discord_http'     => __('Appels API Discord', 'discord-bot-jlg'),
            'discord_connector'=> __('Tâches et connecteur', 'discord-bot-jlg'),
        );

        $channel_options = array(
            ''       => __('Tous les canaux', 'discord-bot-jlg'),
            'widget' => __('Widget', 'discord-bot-jlg'),
            'bot'    => __('Bot', 'discord-bot-jlg'),
            'queue'  => __('File', 'discord-bot-jlg'),
            'cron'   => __('Cron', 'discord-bot-jlg'),
            'rest'   => __('REST', 'discord-bot-jlg'),
        );

        $profile_options = array(
            ''         => __('Tous les profils', 'discord-bot-jlg'),
            'default'  => __('Profil principal', 'discord-bot-jlg'),
        );

        $configured_profiles = $this->api->get_server_profiles(false);
        if (is_array($configured_profiles)) {
            foreach ($configured_profiles as $profile) {
                if (!is_array($profile)) {
                    continue;
                }

                $profile_key = isset($profile['key']) ? discord_bot_jlg_sanitize_profile_key($profile['key']) : '';
                if ('' === $profile_key || isset($profile_options[$profile_key])) {
                    continue;
                }

                $label = isset($profile['label']) ? sanitize_text_field($profile['label']) : $profile_key;
                $profile_options[$profile_key] = $label;
            }
        }

        $monitoring_base_url = add_query_arg(
            array(
                'page' => 'discord-bot-jlg',
                'tab'  => 'monitoring',
            ),
            admin_url('admin.php')
        );

        $reset_filters_url = remove_query_arg(
            array('log_type', 'log_channel', 'log_profile', 'log_server'),
            $monitoring_base_url
        );

        $rate_limit_remaining = isset($rate_limit['remaining']) ? (int) $rate_limit['remaining'] : null;
        $rate_limit_limit     = isset($rate_limit['limit']) ? (int) $rate_limit['limit'] : null;
        $rate_limit_reset     = isset($rate_limit['reset_after']) ? (float) $rate_limit['reset_after'] : 0.0;
        $rate_limit_timestamp = isset($rate_limit['timestamp']) ? (int) $rate_limit['timestamp'] : 0;
        $rate_limit_retry     = isset($rate_limit['retry_after']) ? (int) $rate_limit['retry_after'] : null;

        $fallback_next_retry = isset($fallback['next_retry']) ? (int) $fallback['next_retry'] : 0;
        $fallback_reason     = isset($fallback['reason']) ? $fallback['reason'] : '';
        $fallback_timestamp  = isset($fallback['timestamp']) ? (int) $fallback['timestamp'] : 0;

        ?>
        <div class="discord-monitoring-grid">
            <div class="discord-monitoring-card">
                <h2><?php esc_html_e('📡 Limites API', 'discord-bot-jlg'); ?></h2>
                <?php if ($rate_limit_limit || $rate_limit_remaining || $rate_limit_retry) : ?>
                    <ul>
                        <?php if (null !== $rate_limit_remaining) : ?>
                            <li>
                                <strong><?php esc_html_e('Requêtes restantes', 'discord-bot-jlg'); ?> :</strong>
                                <?php echo esc_html(number_format_i18n($rate_limit_remaining)); ?>
                                <?php if ($rate_limit_limit) : ?>
                                    <span class="description">/ <?php echo esc_html(number_format_i18n($rate_limit_limit)); ?></span>
                                <?php endif; ?>
                            </li>
                        <?php endif; ?>
                        <?php if ($rate_limit_reset > 0) : ?>
                            <li>
                                <strong><?php esc_html_e('Réinitialisation estimée', 'discord-bot-jlg'); ?> :</strong>
                                <?php
                                $reset_seconds = (int) ceil($rate_limit_reset);
                                echo esc_html(human_time_diff($now_gmt, $now_gmt + $reset_seconds));
                                ?>
                            </li>
                        <?php endif; ?>
                        <?php if (null !== $rate_limit_retry && $rate_limit_retry > 0) : ?>
                            <li>
                                <strong><?php esc_html_e('Prochain essai conseillé', 'discord-bot-jlg'); ?> :</strong>
                                <?php echo esc_html(sprintf(_n('dans %s seconde', 'dans %s secondes', $rate_limit_retry, 'discord-bot-jlg'), number_format_i18n($rate_limit_retry))); ?>
                            </li>
                        <?php endif; ?>
                    </ul>
                    <?php if ($rate_limit_timestamp > 0) : ?>
                        <p class="description">
                            <?php
                            printf(
                                /* translators: %s: human readable time diff */
                                esc_html__('Données relevées %s.', 'discord-bot-jlg'),
                                esc_html(human_time_diff($rate_limit_timestamp, $now_gmt))
                            );
                            ?>
                        </p>
                    <?php endif; ?>
                <?php else : ?>
                    <p><?php esc_html_e('Aucun plafond récent communiqué par Discord.', 'discord-bot-jlg'); ?></p>
                <?php endif; ?>
            </div>

            <div class="discord-monitoring-card">
                <h2><?php esc_html_e('🛡️ Dernier incident', 'discord-bot-jlg'); ?></h2>
                <?php if ($last_error) : ?>
                    <p>
                        <strong><?php echo esc_html($last_error['label']); ?></strong><br />
                        <span class="description">
                            <?php
                            printf(
                                /* translators: %s: human readable time diff */
                                esc_html__('Il y a %s', 'discord-bot-jlg'),
                                esc_html(human_time_diff($last_error['timestamp'], $now_gmt))
                            );
                            ?>
                        </span>
                    </p>
                    <?php if (!empty($last_error['reason'])) : ?>
                        <p class="discord-monitoring-card__reason"><?php echo esc_html($last_error['reason']); ?></p>
                    <?php endif; ?>
                <?php else : ?>
                    <p><?php esc_html_e('Aucun incident récent détecté.', 'discord-bot-jlg'); ?></p>
                <?php endif; ?>

                <?php if ($last_success) : ?>
                    <p class="discord-monitoring-card__success">
                        <strong><?php esc_html_e('Dernier succès', 'discord-bot-jlg'); ?> :</strong>
                        <?php echo esc_html($last_success['label']); ?>
                        <span class="description">
                            <?php
                            printf(
                                esc_html__('Il y a %s', 'discord-bot-jlg'),
                                esc_html(human_time_diff($last_success['timestamp'], $now_gmt))
                            );
                            ?>
                        </span>
                    </p>
                <?php endif; ?>
            </div>

            <div class="discord-monitoring-card">
                <h2><?php esc_html_e('🔁 Mode secours', 'discord-bot-jlg'); ?></h2>
                <?php if ($fallback_reason || $fallback_timestamp) : ?>
                    <?php if ($fallback_reason) : ?>
                        <p class="discord-monitoring-card__reason"><?php echo esc_html($fallback_reason); ?></p>
                    <?php endif; ?>
                    <?php if ($fallback_timestamp > 0) : ?>
                        <p class="description">
                            <?php
                            printf(
                                esc_html__('Dernier fallback il y a %s.', 'discord-bot-jlg'),
                                esc_html(human_time_diff($fallback_timestamp, $now_gmt))
                            );
                            ?>
                        </p>
                    <?php endif; ?>
                    <?php if ($fallback_next_retry > 0) : ?>
                        <p>
                            <strong><?php esc_html_e('Prochain essai forcé', 'discord-bot-jlg'); ?> :</strong>
                            <?php echo esc_html(human_time_diff($now_gmt, $fallback_next_retry)); ?>
                        </p>
                    <?php endif; ?>
                <?php else : ?>
                    <p><?php esc_html_e('Aucun fallback enregistré récemment.', 'discord-bot-jlg'); ?></p>
                <?php endif; ?>

                <?php if ($retry_after > 0) : ?>
                    <p class="description">
                        <?php
                        printf(
                            esc_html__('Délai courant communiqué : %s s.', 'discord-bot-jlg'),
                            esc_html(number_format_i18n($retry_after))
                        );
                        ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <div class="discord-monitoring-history">
            <h2><?php esc_html_e('🧾 Journal récent', 'discord-bot-jlg'); ?></h2>

            <form method="get" class="discord-monitoring-history__filters" aria-label="<?php esc_attr_e('Filtres du journal de surveillance', 'discord-bot-jlg'); ?>">
                <input type="hidden" name="page" value="discord-bot-jlg" />
                <input type="hidden" name="tab" value="monitoring" />

                <label for="discord-monitoring-filter-type" class="discord-monitoring-history__filter">
                    <span class="discord-monitoring-history__filter-label"><?php esc_html_e('Type', 'discord-bot-jlg'); ?></span>
                    <select id="discord-monitoring-filter-type" name="log_type">
                        <?php foreach ($event_type_options as $value => $label) : ?>
                            <option value="<?php echo esc_attr($value); ?>"<?php selected($filters['type'], $value); ?>><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label for="discord-monitoring-filter-channel" class="discord-monitoring-history__filter">
                    <span class="discord-monitoring-history__filter-label"><?php esc_html_e('Canal', 'discord-bot-jlg'); ?></span>
                    <select id="discord-monitoring-filter-channel" name="log_channel">
                        <?php foreach ($channel_options as $value => $label) : ?>
                            <option value="<?php echo esc_attr($value); ?>"<?php selected($filters['channel'], $value); ?>><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label for="discord-monitoring-filter-profile" class="discord-monitoring-history__filter">
                    <span class="discord-monitoring-history__filter-label"><?php esc_html_e('Profil', 'discord-bot-jlg'); ?></span>
                    <select id="discord-monitoring-filter-profile" name="log_profile">
                        <?php foreach ($profile_options as $value => $label) : ?>
                            <option value="<?php echo esc_attr($value); ?>"<?php selected($filters['profile'], $value); ?>><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label for="discord-monitoring-filter-server" class="discord-monitoring-history__filter">
                    <span class="discord-monitoring-history__filter-label"><?php esc_html_e('Serveur', 'discord-bot-jlg'); ?></span>
                    <input type="text" id="discord-monitoring-filter-server" name="log_server" value="<?php echo esc_attr($filters['server_id']); ?>" placeholder="123456789" />
                </label>

                <div class="discord-monitoring-history__filter-actions">
                    <button type="submit" class="button button-secondary"><?php esc_html_e('Filtrer', 'discord-bot-jlg'); ?></button>
                    <a class="button button-link" href="<?php echo esc_url($reset_filters_url); ?>"><?php esc_html_e('Réinitialiser', 'discord-bot-jlg'); ?></a>
                </div>
            </form>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="discord-monitoring-history__export">
                <?php wp_nonce_field('discord_bot_jlg_export_monitoring', '_discord_monitoring_nonce'); ?>
                <input type="hidden" name="action" value="discord_bot_jlg_export_log" />
                <input type="hidden" name="log_type" value="<?php echo esc_attr($filters['type']); ?>" />
                <input type="hidden" name="log_channel" value="<?php echo esc_attr($filters['channel']); ?>" />
                <input type="hidden" name="log_profile" value="<?php echo esc_attr($filters['profile']); ?>" />
                <input type="hidden" name="log_server" value="<?php echo esc_attr($filters['server_id']); ?>" />
                <button type="submit" class="button button-primary">
                    <?php esc_html_e('Exporter en CSV', 'discord-bot-jlg'); ?>
                </button>
            </form>

            <?php if (empty($timeline_entries)) : ?>
                <p><?php esc_html_e('Aucun événement à afficher pour le moment.', 'discord-bot-jlg'); ?></p>
            <?php else : ?>
                <ol class="discord-monitoring-history__list">
                    <?php foreach ($timeline_entries as $entry) :
                        $entry_timestamp = isset($entry['timestamp']) ? (int) $entry['timestamp'] : 0;
                        $entry_label     = isset($entry['label']) ? $entry['label'] : '';
                        $entry_reason    = isset($entry['reason']) ? $entry['reason'] : '';
                        $entry_outcome   = isset($entry['outcome']) ? sanitize_html_class($entry['outcome']) : '';
                        ?>
                        <li class="discord-monitoring-history__item<?php echo '' !== $entry_outcome ? ' discord-monitoring-history__item--' . esc_attr($entry_outcome) : ''; ?>">
                            <div class="discord-monitoring-history__meta">
                                <?php if ($entry_timestamp > 0) : ?>
                                    <span class="discord-monitoring-history__time" aria-hidden="true">
                                        <?php echo esc_html(human_time_diff($entry_timestamp, $now_gmt)); ?>
                                    </span>
                                    <span class="screen-reader-text">
                                        <?php
                                        printf(
                                            esc_html__('Événement survenu il y a %s', 'discord-bot-jlg'),
                                            esc_html(human_time_diff($entry_timestamp, $now_gmt))
                                        );
                                        ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="discord-monitoring-history__content">
                                <span class="discord-monitoring-history__label"><?php echo esc_html($entry_label); ?></span>
                                <?php if ('' !== trim($entry_reason)) : ?>
                                    <span class="discord-monitoring-history__reason"><?php echo esc_html($entry_reason); ?></span>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
        </div>
        <?php
    }

    public function handle_monitoring_export() {
        if (!Discord_Bot_JLG_Capabilities::current_user_can('export_analytics')) {
            wp_die(__('Vous n’avez pas les droits suffisants pour exporter ce journal.', 'discord-bot-jlg'));
        }

        check_admin_referer('discord_bot_jlg_export_monitoring', '_discord_monitoring_nonce');

        $filters = $this->get_monitoring_filters('post');

        $entries = $this->api->get_monitoring_timeline(
            array(
                'limit'       => 100,
                'event_type'  => $filters['type'],
                'channel'     => $filters['channel'],
                'profile_key' => $filters['profile'],
                'server_id'   => $filters['server_id'],
            )
        );

        $filename = 'discord-monitoring-log-' . gmdate('Ymd-His') . '.csv';

        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);

        $output = fopen('php://output', 'w');
        if (false === $output) {
            wp_die(__('Impossible de générer le fichier CSV.', 'discord-bot-jlg'));
        }

        fputcsv($output, array(
            __('Horodatage', 'discord-bot-jlg'),
            __('Type', 'discord-bot-jlg'),
            __('Canal', 'discord-bot-jlg'),
            __('Profil', 'discord-bot-jlg'),
            __('Résumé', 'discord-bot-jlg'),
            __('Détails', 'discord-bot-jlg'),
        ));

        $channel_labels = array(
            'widget' => __('Widget', 'discord-bot-jlg'),
            'bot'    => __('Bot', 'discord-bot-jlg'),
            'queue'  => __('File', 'discord-bot-jlg'),
            'cron'   => __('Cron', 'discord-bot-jlg'),
            'rest'   => __('REST', 'discord-bot-jlg'),
        );

        $type_labels = array(
            'discord_http'      => __('API Discord', 'discord-bot-jlg'),
            'discord_connector' => __('Connecteur', 'discord-bot-jlg'),
        );

        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $timestamp = isset($entry['timestamp']) ? (int) $entry['timestamp'] : 0;
            $type      = isset($entry['type']) ? sanitize_key($entry['type']) : '';
            $channel   = isset($entry['channel']) ? sanitize_key($entry['channel']) : '';
            $profile   = isset($entry['profile_key']) ? discord_bot_jlg_sanitize_profile_key($entry['profile_key']) : '';
            $label     = isset($entry['label']) ? $entry['label'] : '';
            $reason    = isset($entry['reason']) ? $entry['reason'] : '';

            $formatted_date = ($timestamp > 0)
                ? get_date_from_gmt(gmdate('Y-m-d H:i:s', $timestamp), get_option('date_format') . ' ' . get_option('time_format'))
                : '';

            $type_label = isset($type_labels[$type]) ? $type_labels[$type] : $type;
            $channel_label = isset($channel_labels[$channel]) ? $channel_labels[$channel] : $channel;

            if ('' === $profile) {
                $profile_label = __('Profil principal', 'discord-bot-jlg');
            } elseif ('default' === $profile) {
                $profile_label = __('Profil principal', 'discord-bot-jlg');
            } else {
                $profile_label = $profile;
            }

            fputcsv(
                $output,
                array(
                    $formatted_date,
                    $type_label,
                    $channel_label,
                    $profile_label,
                    wp_strip_all_tags($label),
                    wp_strip_all_tags($reason),
                )
            );
        }

        fclose($output);
        exit;
    }

    /**
     * Affiche le contenu principal en fonction de l'onglet sélectionné.
     *
     * @param string $current_tab Onglet en cours.
     * @param array  $tab_config  Configuration de l'onglet.
     */
    private function render_options_main_content($current_tab, array $tab_config) {
        if ('connection' === $current_tab) {
            $this->render_connection_setup_wizard($tab_config);
            return;
        }

        ?>
        <div class="discord-bot-settings-main" aria-live="polite">
            <?php
            if (isset($tab_config['render_callback']) && is_callable($tab_config['render_callback'])) {
                call_user_func($tab_config['render_callback']);
                return;
            }

            if (empty($tab_config['sections']) || !is_array($tab_config['sections'])) {
                echo '<p>' . esc_html__('Aucun réglage disponible pour cet onglet pour le moment.', 'discord-bot-jlg') . '</p>';
                return;
            }

            foreach ($tab_config['sections'] as $section) {
                if (is_string($section)) {
                    $section = array(
                        'id' => $section,
                    );
                }

                if (empty($section['id'])) {
                    continue;
                }

                $submit_label = isset($section['submit_label'])
                    ? (string) $section['submit_label']
                    : esc_html__('Enregistrer les modifications', 'discord-bot-jlg');

                $this->render_settings_section_form($section['id'], $submit_label);
            }
            ?>
        </div>
        <?php
    }

    /**
     * Affiche l'assistant de configuration pour la connexion et les profils.
     *
     * @param array $tab_config Configuration de l'onglet courant.
     */
    private function render_connection_setup_wizard(array $tab_config) {
        unset($tab_config);

        $steps        = $this->get_connection_setup_steps();
        $current_step = $this->get_current_setup_step($steps);
        $completion   = $this->evaluate_setup_completion();
        $states       = $this->build_setup_step_states($steps, $current_step, $completion);

        ?>
        <div class="discord-bot-settings-main" aria-live="polite">
            <div class="discord-setup-wizard" data-current-step="<?php echo esc_attr($current_step); ?>">
                <?php $this->render_setup_step_navigation($steps, $current_step, $states); ?>

                <div class="discord-setup-panel">
                    <?php $this->render_setup_step_notice($current_step, $states); ?>
                    <?php $this->render_connection_step_content($current_step, $steps, $states); ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Retourne la configuration des étapes de l'assistant.
     *
     * @return array
     */
    private function get_connection_setup_steps() {
        return array(
            'connection' => array(
                'label'       => __('Connexion', 'discord-bot-jlg'),
                'description' => __('Renseignez l’identifiant du serveur et un token valide pour récupérer les statistiques.', 'discord-bot-jlg'),
                'sections'    => array(
                    array(
                        'id'           => 'discord_stats_api_section',
                        'submit_label' => esc_html__('Enregistrer la connexion', 'discord-bot-jlg'),
                    ),
                ),
            ),
            'profiles'   => array(
                'label'       => __('Profils', 'discord-bot-jlg'),
                'description' => __('Ajoutez des serveurs complémentaires et vérifiez individuellement leurs accès.', 'discord-bot-jlg'),
                'sections'    => array(
                    array(
                        'id'           => 'discord_stats_profiles_section',
                        'submit_label' => esc_html__('Mettre à jour les profils', 'discord-bot-jlg'),
                    ),
                ),
            ),
            'display'    => array(
                'label'       => __('Affichage', 'discord-bot-jlg'),
                'description' => __('Choisissez les blocs de statistiques et l’apparence proposée par défaut.', 'discord-bot-jlg'),
                'sections'    => array(
                    array(
                        'id'           => 'discord_stats_display_section',
                        'submit_label' => esc_html__('Enregistrer les options d’affichage', 'discord-bot-jlg'),
                    ),
                    array(
                        'id'           => 'discord_stats_cta_section',
                        'submit_label' => esc_html__('Enregistrer l’engagement', 'discord-bot-jlg'),
                    ),
                ),
            ),
        );
    }

    /**
     * Détermine l'étape courante de l'assistant à partir de la requête.
     *
     * @param array $steps Étapes disponibles.
     *
     * @return string
     */
    private function get_current_setup_step(array $steps) {
        $default_step = key($steps);
        if (null === $default_step) {
            return '';
        }

        if ('' !== $this->forced_setup_step && isset($steps[$this->forced_setup_step])) {
            return $this->forced_setup_step;
        }

        $request_keys = array('setup-step', 'current_setup_step');

        foreach ($request_keys as $request_key) {
            if (!isset($_REQUEST[$request_key])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                continue;
            }

            $candidate = sanitize_key(wp_unslash($_REQUEST[$request_key])); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            if (isset($steps[$candidate])) {
                return $candidate;
            }
        }

        return $default_step;
    }

    /**
     * Évalue l'accomplissement de chaque étape en fonction des options enregistrées.
     *
     * @return array
     */
    private function evaluate_setup_completion() {
        $options = get_option($this->option_name);
        if (!is_array($options)) {
            $options = array();
        }

        $has_server_id = !empty($options['server_id']);
        $has_bot_token = !empty($options['bot_token']);

        $profiles = array();
        if (isset($options['server_profiles']) && is_array($options['server_profiles'])) {
            foreach ($options['server_profiles'] as $profile) {
                if (is_array($profile) && (!empty($profile['server_id']) || !empty($profile['bot_token']))) {
                    $profiles[] = $profile;
                }
            }
        }

        $display_keys = array(
            'show_online',
            'show_total',
            'show_presence_breakdown',
            'show_approximate_member_count',
            'show_premium_subscriptions',
            'show_server_name',
            'show_server_avatar',
            'invite_url',
            'invite_label',
            'widget_title',
            'custom_css',
        );

        $display_configured = false;
        foreach ($display_keys as $display_key) {
            if (!empty($options[$display_key])) {
                $display_configured = true;
                break;
            }
        }

        if (!$display_configured && isset($options['default_theme']) && 'discord' !== $options['default_theme']) {
            $display_configured = true;
        }

        return array(
            'connection' => ($has_server_id || $has_bot_token),
            'profiles'   => !empty($profiles),
            'display'    => $display_configured,
        );
    }

    /**
     * Construit l'état d'affichage pour chaque étape.
     *
     * @param array  $steps        Étapes disponibles.
     * @param string $current_step Étape active.
     * @param array  $completion   Tableau des étapes complétées.
     *
     * @return array
     */
    private function build_setup_step_states(array $steps, $current_step, array $completion) {
        $states       = array();
        $before_state = true;

        foreach ($steps as $key => $step) {
            $state = 'upcoming';

            if ($key === $current_step) {
                $state        = 'current';
                $before_state = false;
            } elseif (!empty($completion[$key])) {
                $state = 'complete';
            } elseif ($before_state) {
                $state = 'pending';
            }

            $states[$key] = array(
                'state'       => $state,
                'is_complete' => !empty($completion[$key]),
            );
        }

        return $states;
    }

    /**
     * Affiche la navigation des étapes.
     *
     * @param array  $steps        Étapes.
     * @param string $current_step Étape active.
     * @param array  $states       États calculés.
     */
    private function render_setup_step_navigation(array $steps, $current_step, array $states) {
        ?>
        <nav class="discord-setup-steps-nav" aria-label="<?php esc_attr_e('Assistant de configuration Discord', 'discord-bot-jlg'); ?>">
            <ol class="discord-setup-steps">
                <?php
                $index = 1;
                foreach ($steps as $key => $step) {
                    $state    = isset($states[$key]['state']) ? $states[$key]['state'] : 'upcoming';
                    $classes  = array('discord-setup-step', 'is-' . $state);
                    $is_active = ($key === $current_step);

                    if ($is_active) {
                        $classes[] = 'is-active';
                    }

                    $url = add_query_arg(
                        array(
                            'setup-step' => $key,
                        )
                    );
                    ?>
                    <li class="<?php echo esc_attr(implode(' ', $classes)); ?>">
                        <a class="discord-setup-step__link" href="<?php echo esc_url($url); ?>" aria-current="<?php echo $is_active ? 'step' : 'false'; ?>">
                            <span class="discord-setup-step__index" aria-hidden="true">
                                <?php if (!empty($states[$key]['is_complete'])) : ?>
                                    <span class="dashicons dashicons-yes-alt"></span>
                                <?php else : ?>
                                    <?php echo esc_html($index); ?>
                                <?php endif; ?>
                            </span>
                            <span class="discord-setup-step__label"><?php echo esc_html($step['label']); ?></span>
                        </a>
                    </li>
                    <?php
                    $index++;
                }
                ?>
            </ol>
        </nav>
        <?php
    }

    /**
     * Affiche le contenu principal de l'étape courante.
     *
     * @param string $current_step Étape active.
     * @param array  $steps        Étapes disponibles.
     * @param array  $states       États calculés.
     */
    private function render_connection_step_content($current_step, array $steps, array $states) {
        if (!isset($steps[$current_step])) {
            echo '<p>' . esc_html__('Étape inconnue.', 'discord-bot-jlg') . '</p>';
            return;
        }

        $step_config = $steps[$current_step];

        if (!empty($step_config['description'])) {
            echo '<p class="discord-setup-intro">' . esc_html($step_config['description']) . '</p>';
        }

        if (!empty($step_config['sections']) && is_array($step_config['sections'])) {
            foreach ($step_config['sections'] as $section) {
                if (is_string($section)) {
                    $section = array('id' => $section);
                }

                if (empty($section['id'])) {
                    continue;
                }

                $submit_label = isset($section['submit_label'])
                    ? (string) $section['submit_label']
                    : esc_html__('Enregistrer les modifications', 'discord-bot-jlg');

                $this->render_settings_section_form($section['id'], $submit_label, array('layout' => 'card'));
            }
        }

        if ('connection' === $current_step) {
            $this->render_default_connection_test_controls($current_step);
        }

        if ('profiles' === $current_step) {
            $this->render_profile_test_form($current_step);
        }
    }

    /**
     * Affiche une notice contextuelle pour l'étape en cours.
     *
     * @param string $current_step Étape active.
     * @param array  $states       États calculés.
     */
    private function render_setup_step_notice($current_step, array $states) {
        $options = get_option($this->option_name);
        if (!is_array($options)) {
            $options = array();
        }

        $notice_type = 'info';
        $message     = '';

        switch ($current_step) {
            case 'connection':
                if (empty($options['server_id'])) {
                    $notice_type = 'warning';
                    $message     = esc_html__('Commencez par copier l’identifiant numérique de votre serveur Discord.', 'discord-bot-jlg');
                } elseif (empty($options['bot_token'])) {
                    $notice_type = 'info';
                    $message     = esc_html__('Enregistrez un token bot ou créez un profil pour activer le test de connexion.', 'discord-bot-jlg');
                } else {
                    $message = esc_html__('Votre configuration principale est enregistrée. Lancez un test pour valider l’accès.', 'discord-bot-jlg');
                }
                break;
            case 'profiles':
                if (empty($states['profiles']['is_complete'])) {
                    $notice_type = 'info';
                    $message     = esc_html__('Ajoutez un profil par serveur et utilisez le bouton « Tester » pour vérifier chaque connexion.', 'discord-bot-jlg');
                } else {
                    $message = esc_html__('Testez vos profils enregistrés pour confirmer l’accès aux statistiques dédiées.', 'discord-bot-jlg');
                }
                break;
            case 'display':
                $notice_type = 'info';
                $message     = esc_html__('Sélectionnez les blocs visibles par défaut et, si besoin, ajustez les libellés proposés dans le bloc et le widget.', 'discord-bot-jlg');
                break;
            default:
                $message = '';
        }

        if ('' === $message) {
            return;
        }

        printf(
            '<div class="notice notice-%1$s discord-setup-notice"><p>%2$s</p></div>',
            esc_attr($notice_type),
            esc_html($message)
        );
    }

    /**
     * Rend le formulaire de test de connexion principal.
     *
     * @param string $current_step Étape active.
     */
    private function render_default_connection_test_controls($current_step) {
        $options       = get_option($this->option_name);
        $has_server_id = is_array($options) && !empty($options['server_id']);

        $button_attributes = array(
            'class' => 'button button-secondary button-block',
        );

        if (!$has_server_id) {
            $button_attributes['disabled'] = 'disabled';
        }

        ?>
        <div class="discord-setup-card">
            <h3><?php esc_html_e('Tester la connexion principale', 'discord-bot-jlg'); ?></h3>
            <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=discord-bot-jlg')); ?>" class="discord-setup-test-form">
                <input type="hidden" name="test_connection" value="1" />
                <input type="hidden" name="test_connection_profile" value="default" />
                <input type="hidden" name="current_setup_step" value="<?php echo esc_attr($current_step); ?>" />
                <?php wp_nonce_field('discord_test_connection', 'discord_test_connection_nonce_default'); ?>
                <?php submit_button(esc_html__('Tester la connexion', 'discord-bot-jlg'), 'secondary', 'discord_test_connection_default', false, $button_attributes); ?>
            </form>
            <?php if (!$has_server_id) : ?>
                <p class="description"><?php esc_html_e('Enregistrez l’identifiant du serveur avant de lancer un test.', 'discord-bot-jlg'); ?></p>
            <?php else : ?>
                <p class="description"><?php esc_html_e('Le test utilise la configuration principale et contourne le cache pour un diagnostic instantané.', 'discord-bot-jlg'); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Rend le formulaire partagé pour tester les profils enregistrés.
     *
     * @param string $current_step Étape active.
     */
    private function render_profile_test_form($current_step) {
        ?>
        <form id="discord-profile-test-form" class="discord-setup-test-form discord-profile-test-form" method="post" action="<?php echo esc_url(admin_url('admin.php?page=discord-bot-jlg')); ?>">
            <input type="hidden" name="test_connection" value="1" />
            <input type="hidden" name="current_setup_step" value="<?php echo esc_attr($current_step); ?>" />
            <?php wp_nonce_field('discord_test_connection', 'discord_test_connection_nonce_profile'); ?>
        </form>
        <?php
    }

    /**
     * Recherche un profil serveur enregistré dans les options.
     *
     * @param string $profile_key Clé recherchée.
     * @param array  $options     Options enregistrées.
     *
     * @return array|null
     */
    private function locate_server_profile($profile_key, array $options) {
        if ('' === $profile_key) {
            return null;
        }

        if (!isset($options['server_profiles']) || !is_array($options['server_profiles'])) {
            return null;
        }

        foreach ($options['server_profiles'] as $stored_key => $profile) {
            if (!is_array($profile)) {
                continue;
            }

            $candidate_key = '';

            if (isset($profile['key'])) {
                $candidate_key = discord_bot_jlg_sanitize_profile_key($profile['key']);
            }

            if ('' === $candidate_key) {
                $candidate_key = discord_bot_jlg_sanitize_profile_key($stored_key);
            }

            if ($candidate_key !== $profile_key) {
                continue;
            }

            return array(
                'key'       => $candidate_key,
                'label'     => isset($profile['label']) ? sanitize_text_field($profile['label']) : '',
                'server_id' => isset($profile['server_id']) ? preg_replace('/[^0-9]/', '', (string) $profile['server_id']) : '',
                'bot_token' => isset($profile['bot_token']) ? $profile['bot_token'] : '',
            );
        }

        return null;
    }

    /**
     * Affiche un formulaire autonome pour une section spécifique des réglages.
     *
     * @param string $section_id Identifiant de la section enregistrée via l'API des réglages.
     * @param string $submit_label Libellé du bouton de soumission.
     */
    private function render_settings_section_form($section_id, $submit_label, $args = array()) {
        $page = 'discord_stats_settings';

        global $wp_settings_sections, $wp_settings_fields;

        if (
            !isset($wp_settings_sections[$page][$section_id])
            || empty($wp_settings_fields[$page][$section_id])
        ) {
            return;
        }

        $defaults = array(
            'layout' => 'default',
        );

        $args = wp_parse_args($args, $defaults);
        $layout = in_array($args['layout'], array('default', 'card'), true) ? $args['layout'] : 'default';

        $form_classes = array('discord-bot-settings-form');
        if ('card' === $layout) {
            $form_classes[] = 'discord-bot-settings-form--card';
        }

        $section = $wp_settings_sections[$page][$section_id];
        ?>
        <form action="options.php" method="post" class="<?php echo esc_attr(implode(' ', $form_classes)); ?>">
            <?php settings_fields($page); ?>

            <?php if ('card' === $layout) : ?>
                <div class="components-card discord-admin-card">
                    <div class="components-card__body">
            <?php endif; ?>

            <h2><?php echo esc_html($section['title']); ?></h2>

            <?php
            if (isset($section['callback']) && is_callable($section['callback'])) {
                call_user_func($section['callback'], $section);
            }
            ?>

            <table class="form-table" role="presentation">
                <?php do_settings_fields($page, $section_id); ?>
            </table>

            <?php if ('card' === $layout) : ?>
                    </div>
                    <div class="components-card__body discord-admin-card__footer">
            <?php endif; ?>

            <?php submit_button($submit_label); ?>

            <?php if ('card' === $layout) : ?>
                    </div>
                </div>
            <?php endif; ?>
        </form>
        <?php
    }

    /**
     * Affiche la colonne latérale avec les actions rapides.
     *
     * @param string $current_tab Onglet actuel.
     * @param array  $tab_config  Configuration de l'onglet.
     */
    private function render_options_sidebar($current_tab, array $tab_config) {
        $panels = array();

        if (!empty($tab_config['sidebar_panels']) && is_array($tab_config['sidebar_panels'])) {
            $panels = array_map('sanitize_key', $tab_config['sidebar_panels']);
        }

        if (empty($panels)) {
            // Par défaut, afficher les liens rapides pour conserver les repères utilisateurs.
            $panels = array('quick_links');
            if ('connection' === $current_tab) {
                array_unshift($panels, 'connection_test');
            }
        }

        ?>
        <div class="discord-bot-settings-sidebar" aria-label="<?php esc_attr_e('Actions annexes', 'discord-bot-jlg'); ?>">
            <?php
            foreach ($panels as $panel_id) {
                switch ($panel_id) {
                    case 'connection_test':
                        $this->render_connection_test_panel();
                        break;
                    case 'appearance_shortcuts':
                        $this->render_appearance_shortcuts_panel();
                        break;
                    case 'automation_tips':
                        $this->render_automation_tips_panel();
                        break;
                    case 'api_keys_help':
                        $this->render_api_keys_help_panel();
                        break;
                    case 'monitoring_help':
                        $this->render_monitoring_help_panel();
                        break;
                    case 'quick_links':
                    default:
                        $this->render_quick_links_panel();
                        break;
                }
            }
            ?>
        </div>
        <?php
    }

    /**
     * Affiche le panneau de test de connexion.
     */
    private function render_connection_test_panel() {
        ?>
        <div class="components-card discord-admin-card">
            <div class="components-card__body">
                <h3 class="discord-admin-card__title"><?php esc_html_e('🔧 Test de connexion', 'discord-bot-jlg'); ?></h3>
                <p><?php esc_html_e('Vérifiez que votre configuration fonctionne :', 'discord-bot-jlg'); ?></p>
                <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=discord-bot-jlg')); ?>" class="discord-admin-card__form">
                    <input type="hidden" name="test_connection" value="1" />
                    <?php wp_nonce_field('discord_test_connection', 'discord_test_connection_nonce_sidebar'); ?>
                    <?php submit_button(esc_html__('Tester la connexion', 'discord-bot-jlg'), 'secondary', 'discord_test_connection_sidebar', false, array('class' => 'button button-secondary button-block')); ?>
                </form>
            </div>
        </div>
        <?php
    }

    /**
     * Affiche la liste des liens rapides utiles.
     */
    private function render_quick_links_panel() {
        ?>
        <div class="components-card discord-admin-card">
            <div class="components-card__body">
                <h3 class="discord-admin-card__title"><?php esc_html_e('🚀 Liens rapides', 'discord-bot-jlg'); ?></h3>
                <ul class="discord-quick-links">
                    <li>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=discord-bot-demo')); ?>" class="button button-primary button-block">
                            <?php esc_html_e('📖 Guide & Démo', 'discord-bot-jlg'); ?>
                        </a>
                    </li>
                    <li>
                        <a href="https://discord.com/developers/applications" target="_blank" rel="noopener noreferrer" class="button button-secondary button-block">
                            <?php esc_html_e('🔗 Discord Developer Portal', 'discord-bot-jlg'); ?>
                        </a>
                    </li>
                    <?php
                    $is_block_theme = function_exists('wp_is_block_theme') && wp_is_block_theme();

                    if (!$is_block_theme) :
                        ?>
                        <li>
                            <a href="<?php echo esc_url(admin_url('widgets.php')); ?>" class="button button-secondary button-block">
                                <?php esc_html_e('📐 Gérer les Widgets', 'discord-bot-jlg'); ?>
                            </a>
                        </li>
                    <?php else : ?>
                        <li class="discord-quick-links__notice">
                            <strong><?php esc_html_e('📐 Widgets classiques indisponibles', 'discord-bot-jlg'); ?></strong>
                            <span><?php esc_html_e('Votre thème utilise l’éditeur de site basé sur les blocs. Ajoutez le bloc “Discord Stats” depuis l’éditeur de site ou utilisez le shortcode.', 'discord-bot-jlg'); ?></span>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
        <?php
    }

    /**
     * Affiche des raccourcis vers la documentation des presets.
     */
    private function render_appearance_shortcuts_panel() {
        ?>
        <div class="components-card discord-admin-card">
            <div class="components-card__body">
                <h3 class="discord-admin-card__title"><?php esc_html_e('🎨 Presets express', 'discord-bot-jlg'); ?></h3>
                <p><?php esc_html_e('Appliquez une base visuelle en un clic depuis Gutenberg, puis affinez les couleurs ici.', 'discord-bot-jlg'); ?></p>
                <ul class="discord-admin-list">
                    <li><?php esc_html_e('Carte immersive : statistiques complètes, avatar et bouton principal.', 'discord-bot-jlg'); ?></li>
                    <li><?php esc_html_e('Bannière e-sport : accent sur les présences et l’appel à l’action.', 'discord-bot-jlg'); ?></li>
                    <li><?php esc_html_e('Mode compact minimal : idéal pour les sidebars.', 'discord-bot-jlg'); ?></li>
                </ul>
                <a class="button button-secondary" href="<?php echo esc_url(admin_url('admin.php?page=discord-bot-demo')); ?>">
                    <?php esc_html_e('Voir les aperçus', 'discord-bot-jlg'); ?>
                </a>
            </div>
        </div>
        <?php
    }

    /**
     * Fournit des conseils sur l'automatisation.
     */
    private function render_automation_tips_panel() {
        ?>
        <div class="components-card discord-admin-card">
            <div class="components-card__body">
                <h3 class="discord-admin-card__title"><?php esc_html_e('⚙️ Bonnes pratiques', 'discord-bot-jlg'); ?></h3>
                <ul class="discord-admin-list">
                    <li><?php esc_html_e('Gardez un intervalle de rafraîchissement supérieur à 60 s pour éviter les limites Discord.', 'discord-bot-jlg'); ?></li>
                    <li><?php esc_html_e('Activez la rétention analytics pour alimenter les graphiques et les KPI.', 'discord-bot-jlg'); ?></li>
                    <li><?php esc_html_e('Les caches courts améliorent la réactivité, mais surveillez les erreurs dans l’onglet Surveillance.', 'discord-bot-jlg'); ?></li>
                </ul>
            </div>
        </div>
        <?php
    }

    private function render_api_keys_help_panel() {
        ?>
        <div class="components-card discord-admin-card">
            <div class="components-card__body">
                <h3 class="discord-admin-card__title"><?php esc_html_e('🔑 Astuces clés API', 'discord-bot-jlg'); ?></h3>
                <ul class="discord-admin-list">
                    <li><?php esc_html_e('Générez une clé par usage (ex. exports marketing) pour suivre facilement les accès.', 'discord-bot-jlg'); ?></li>
                    <li><?php esc_html_e('Limitez chaque clé aux profils et permissions nécessaires pour réduire l’exposition.', 'discord-bot-jlg'); ?></li>
                    <li><?php esc_html_e('Révoquez immédiatement les clés non utilisées ou en cas de doute.', 'discord-bot-jlg'); ?></li>
                </ul>
                <p class="description"><?php esc_html_e('Les accès sont journalisés dans l’onglet ci-contre et consultables via l’API REST /events.', 'discord-bot-jlg'); ?></p>
            </div>
        </div>
        <?php
    }

    /**
     * Affiche une aide contextuelle pour le suivi en temps réel.
     */
    private function render_monitoring_help_panel() {
        ?>
        <div class="components-card discord-admin-card">
            <div class="components-card__body">
                <h3 class="discord-admin-card__title"><?php esc_html_e('🛰️ Astuce surveillance', 'discord-bot-jlg'); ?></h3>
                <p><?php esc_html_e('Le tableau de bord ci-contre agrège les derniers événements Discord et la prochaine fenêtre de réessai.', 'discord-bot-jlg'); ?></p>
                <a class="button button-secondary" href="<?php echo esc_url(rest_url('discord-bot-jlg/v1/events')); ?>" target="_blank" rel="noopener noreferrer">
                    <?php esc_html_e('Exporter le journal complet', 'discord-bot-jlg'); ?>
                </a>
            </div>
        </div>
        <?php
    }

    /**
     * Affiche le pied de page de la page d'options.
     */
    private function render_admin_footer_note() {
        ?>
        <div class="discord-admin-footer-note">
            <p>
                <?php
                $version_label = sprintf(
                    /* translators: %s: plugin version. */
                    __('Discord Bot - JLG v%s', 'discord-bot-jlg'),
                    DISCORD_BOT_JLG_VERSION
                );
                printf(
                    /* translators: %1$s: plugin version label. */
                    esc_html__('%1$s | Développé par Jérôme Le Gousse', 'discord-bot-jlg'),
                    esc_html($version_label)
                );
                ?>
            </p>
        </div>
        <?php
    }

    /**
     * Affiche la page de guide et de démonstration du plugin.
     *
     * @return void
     */
    public function demo_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('📖 Guide & Démonstration', 'discord-bot-jlg'); ?></h1>
            <?php $this->render_demo_intro_notice(); ?>

            <?php $this->render_demo_analytics_overview(); ?>

            <hr style="margin: 30px 0;">

            <?php $this->render_demo_previews(); ?>

            <hr style="margin: 30px 0;">

            <?php $this->render_demo_guide_section(); ?>

            <hr style="margin: 30px 0;">

            <?php
            $this->render_demo_troubleshooting();
            $this->render_demo_footer_note();
            ?>
        </div>
        <?php
    }

    /**
     * Affiche l'encart d'introduction de la page de démonstration.
     */
    private function render_demo_intro_notice() {
        ?>
        <div style="background: #fff3cd; padding: 10px 20px; border-radius: 8px; margin: 20px auto; width: 100%; max-width: 500px;">
            <p><?php echo wp_kses_post(__('<strong>💡 Astuce :</strong> Tous les exemples ci-dessous utilisent le mode démo. Vous pouvez les copier-coller directement !', 'discord-bot-jlg')); ?></p>
        </div>
        <?php
    }

    private function render_demo_analytics_overview() {
        $options    = $this->api->get_plugin_options();
        $retention  = $this->api->get_analytics_retention_days($options);
        $retention  = (int) $retention;
        $retention_text = ($retention > 0)
            ? sprintf(
                /* translators: %d: number of days retained. */
                esc_html__('Rétention actuelle : %d jours de snapshots.', 'discord-bot-jlg'),
                $retention
            )
            : esc_html__('Rétention désactivée : activez-la dans l’onglet Configuration pour alimenter ce graphique.', 'discord-bot-jlg');
        ?>
        <div class="discord-analytics-panel" id="discord-analytics-panel" data-retention="<?php echo esc_attr($retention); ?>">
            <div class="discord-analytics-panel__header">
                <h2>📈 <?php esc_html_e('Tendances des statistiques', 'discord-bot-jlg'); ?></h2>
                <p class="description"><?php echo esc_html($retention_text); ?></p>
            </div>
            <div class="discord-analytics-panel__filters" data-role="analytics-filters"></div>
            <div class="discord-analytics-panel__body">
                <div class="discord-analytics-panel__canvas">
                    <canvas id="discord-analytics-chart" height="240" role="img" aria-label="<?php esc_attr_e('Évolution des présences et boosts Discord', 'discord-bot-jlg'); ?>"></canvas>
                </div>
                <div class="discord-analytics-panel__summary" data-role="analytics-summary"></div>
            </div>
            <p class="discord-analytics-panel__notice" data-role="analytics-notice"></p>
        </div>
        <?php
    }

    private function get_monitoring_filters($source = 'get') {
        $filters = array(
            'type'      => '',
            'channel'   => '',
            'profile'   => '',
            'server_id' => '',
        );

        $input = ('post' === $source) ? $_POST : $_GET;

        $mapping = array(
            'log_type'    => 'type',
            'log_channel' => 'channel',
            'log_profile' => 'profile',
            'log_server'  => 'server_id',
        );

        foreach ($mapping as $key => $target) {
            if (!isset($input[$key])) {
                continue;
            }

            $raw = wp_unslash($input[$key]);

            switch ($target) {
                case 'type':
                case 'channel':
                case 'profile':
                    $filters[$target] = sanitize_key($raw);
                    break;
                case 'server_id':
                    $filters[$target] = preg_replace('/[^0-9]/', '', (string) $raw);
                    break;
            }
        }

        return $filters;
    }

    /**
     * Affiche les prévisualisations du shortcode en mode démo.
     */
    private function render_demo_previews() {
            $previews = array(
                array(
                    'title' => __('Standard horizontal :', 'discord-bot-jlg'),
                    'shortcode' => '[discord_stats demo="true"]',
                ),
            array(
                'title' => __('Vertical pour sidebar :', 'discord-bot-jlg'),
                'shortcode' => '[discord_stats demo="true" layout="vertical" theme="minimal"]',
                'inner_wrapper_style' => 'max-width: 300px;',
            ),
            array(
                'title' => __('Compact mode sombre :', 'discord-bot-jlg'),
                'shortcode' => '[discord_stats demo="true" compact="true" theme="dark"]',
            ),
            array(
                'title' => __('Avec titre personnalisé :', 'discord-bot-jlg'),
                'shortcode' => '[discord_stats demo="true" show_title="true" title="🎮 Notre Communauté Gaming" align="center"]',
            ),
            array(
                'title' => __('Icônes personnalisées :', 'discord-bot-jlg'),
                'shortcode' => '[discord_stats demo="true" icon_online="🔥" label_online="Actifs" icon_total="⚔️" label_total="Guerriers"]',
            ),
            array(
                'title' => __('Minimaliste (nombres uniquement) :', 'discord-bot-jlg'),
                'shortcode' => '[discord_stats demo="true" hide_labels="true" hide_icons="true" theme="minimal"]',
            ),
            array(
                'title' => __('Palette personnalisée :', 'discord-bot-jlg'),
                'shortcode' => '[discord_stats demo="true" stat_bg_color="#111827" stat_text_color="rgba(255,255,255,0.92)" accent_color="#38bdf8" accent_text_color="#0b1120" align="center"]',
                'inner_wrapper_style' => 'max-width: 360px;',
            ),
            array(
                'title' => __('Nom du serveur mis en avant :', 'discord-bot-jlg'),
                'shortcode' => '[discord_stats demo="true" show_server_name="true" show_discord_icon="true" align="center"]',
            ),
                array(
                    'title' => __('Nom + avatar du serveur :', 'discord-bot-jlg'),
                    'shortcode' => '[discord_stats demo="true" show_server_name="true" show_server_avatar="true" avatar_size="96" align="center" theme="discord"]',
                    'inner_wrapper_style' => 'max-width: 360px;',
                ),
            );
        ?>
        <div style="background: #e3f2fd; padding: 20px; border-radius: 8px;">
            <h2><?php esc_html_e('🎨 Prévisualisation en direct', 'discord-bot-jlg'); ?></h2>
            <p><?php esc_html_e('Testez différentes configurations visuelles :', 'discord-bot-jlg'); ?></p>
            <?php
            foreach ($previews as $preview) {
                $options = array('container_style' => 'margin: 20px 0;');
                if (!empty($preview['inner_wrapper_style'])) {
                    $options['inner_wrapper_style'] = $preview['inner_wrapper_style'];
                }
                $this->render_preview_block($preview['title'], $preview['shortcode'], $options);
            }
            ?>
        </div>
        <?php
    }

    /**
     * Affiche le guide détaillé d'utilisation et les exemples.
     */
    private function render_demo_guide_section() {
        ?>
        <div style="background: #e8f5e9; padding: 20px; border-radius: 8px;">
            <h2><?php esc_html_e('📖 Guide d\'utilisation', 'discord-bot-jlg'); ?></h2>

            <p><?php echo wp_kses_post(__('Les choix effectués dans l\'onglet <strong>Configuration</strong> (nom/avatar du serveur, thème, auto-rafraîchissement) remplissent automatiquement les attributs équivalents du shortcode, du bloc et du widget. Vous pouvez toujours les modifier manuellement pour un cas précis.', 'discord-bot-jlg')); ?></p>
            <ul>
                <li><?php echo wp_kses_post(__('« Afficher le nom du serveur » pré-renseigne <code>show_server_name="true"</code>.', 'discord-bot-jlg')); ?></li>
                <li><?php echo wp_kses_post(__('« Afficher l\'avatar » active <code>show_server_avatar="true"</code> et ajuste la taille depuis la barre latérale du bloc.', 'discord-bot-jlg')); ?></li>
                <li><?php echo wp_kses_post(__('Le thème choisi devient la valeur par défaut de l\'attribut <code>theme</code>.', 'discord-bot-jlg')); ?></li>
                <li><?php echo wp_kses_post(__('Les icônes et libellés saisis dans « Icônes/Libellés par défaut » sont proposés automatiquement partout (bloc, widget, shortcode).', 'discord-bot-jlg')); ?></li>
                <li><?php echo wp_kses_post(__('En cochant « Rafraîchissement auto », le shortcode/ bloc utilise <code>refresh="true"</code> et l\'intervalle numérique saisi pour <code>refresh_interval</code>.', 'discord-bot-jlg')); ?></li>
            </ul>

            <h3><?php esc_html_e('Option 1 : Shortcode (avec paramètres)', 'discord-bot-jlg'); ?></h3>
            <p><?php esc_html_e('Copiez ce code dans n\'importe quelle page ou article :', 'discord-bot-jlg'); ?></p>
            <code style="background: white; padding: 10px; display: inline-block; border-radius: 4px;"><?php echo esc_html__('[discord_stats]', 'discord-bot-jlg'); ?></code>

            <h4><?php esc_html_e('Exemples avec paramètres :', 'discord-bot-jlg'); ?></h4>
            <pre style="background: white; padding: 15px; border-radius: 4px; overflow-x: auto;"><?php echo esc_html__("// BASIQUES\n// Layout vertical pour sidebar\n[discord_stats layout=\"vertical\"]\n\n// Compact avec titre\n[discord_stats compact=\"true\" show_title=\"true\" title=\"Rejoignez-nous !\"]\n\n// Theme sombre centré\n[discord_stats theme=\"dark\" align=\"center\"]\n\n// AVEC LOGO DISCORD\n// Logo à gauche (classique)\n[discord_stats show_discord_icon=\"true\"]\n\n// Logo à droite avec thème sombre\n[discord_stats show_discord_icon=\"true\" discord_icon_position=\"right\" theme=\"dark\"]\n\n// Logo centré en haut (parfait pour widgets)\n[discord_stats show_discord_icon=\"true\" discord_icon_position=\"top\" align=\"center\"]\n\n// Nom du serveur + logo\n[discord_stats show_server_name=\"true\" show_discord_icon=\"true\" align=\"center\"]\n\n// Nom + avatar du serveur\n[discord_stats show_server_name=\"true\" show_server_avatar=\"true\" avatar_size=\"128\" align=\"center\"]\n\n// PERSONNALISATION AVANCÉE\n// Bannière complète pour header\n[discord_stats show_discord_icon=\"true\" show_title=\"true\" title=\"🎮 Rejoignez notre Discord !\" width=\"100%\" align=\"center\" theme=\"discord\"]\n\n// Sidebar élégante avec logo\n[discord_stats layout=\"vertical\" show_discord_icon=\"true\" discord_icon_position=\"top\" theme=\"minimal\" compact=\"true\"]\n\n// Gaming style avec icônes custom\n[discord_stats show_discord_icon=\"true\" icon_online=\"🎮\" label_online=\"Joueurs actifs\" icon_total=\"⚔️\" label_total=\"Guerriers\" theme=\"dark\"]\n\n// Minimaliste avec logo seul\n[discord_stats hide_labels=\"true\" hide_icons=\"true\" show_discord_icon=\"true\" discord_icon_position=\"top\" align=\"center\" theme=\"minimal\"]\n\n// Footer discret\n[discord_stats compact=\"true\" show_discord_icon=\"true\" discord_icon_position=\"left\" theme=\"light\"]\n\n// FONCTIONNALITÉS SPÉCIALES\n// Auto-refresh toutes les 30 secondes (minimum 10 secondes)\n[discord_stats refresh=\"true\" refresh_interval=\"30\" show_discord_icon=\"true\"]\n\n// Afficher seulement les membres en ligne avec logo\n[discord_stats show_online=\"true\" show_total=\"false\" show_discord_icon=\"true\"]\n\n// MODE DÉMO (pour tester l'apparence)\n[discord_stats demo=\"true\" show_discord_icon=\"true\" theme=\"dark\" layout=\"vertical\"]", 'discord-bot-jlg'); ?></pre>

            <p style="margin-top: 10px;"><em><?php echo esc_html__('ℹ️ L\'auto-refresh nécessite un intervalle d\'au moins 10 secondes (10 000 ms). Toute valeur inférieure est automatiquement ajustée pour éviter les erreurs 429.', 'discord-bot-jlg'); ?></em></p>
            <p style="margin-top: 10px;"><em><?php echo esc_html__('🔐 Les rafraîchissements publics n\'utilisent plus de nonce WordPress. Un jeton reste exigé uniquement pour les requêtes effectuées par des utilisateurs connectés (administration).', 'discord-bot-jlg'); ?></em></p>

            <h3><?php esc_html_e('Option 2 : Bloc Éditeur Gutenberg', 'discord-bot-jlg'); ?></h3>
            <p><?php echo wp_kses_post(__('Ajoutez le bloc <strong>« Discord Server Stats »</strong> depuis l\'inserteur Gutenberg pour configurer vos statistiques en mode visuel. Toutes les options du shortcode sont disponibles via la barre latérale (mise en page, couleurs, libellés, rafraîchissement automatique, etc.).', 'discord-bot-jlg')); ?></p>
            <p><?php echo wp_kses_post(__('Le bloc affiche immédiatement un aperçu rendu côté serveur. Lors de l\'enregistrement avec l\'éditeur classique, un shortcode équivalent est automatiquement inséré pour conserver la compatibilité.', 'discord-bot-jlg')); ?></p>
            <p><?php echo wp_kses_post(__('Le panneau <strong>« Couleurs »</strong> du bloc utilise les <code>ColorPalette</code> de Gutenberg pour renseigner automatiquement les attributs <code>stat_bg_color</code>, <code>stat_text_color</code>, <code>accent_color</code>, <code>accent_color_alt</code> et <code>accent_text_color</code> (valeurs hex ou RGBa).', 'discord-bot-jlg')); ?></p>
            <p><?php echo wp_kses_post(__('Grâce au panneau <strong>Dimensions</strong>, ajustez désormais les marges externes et l\'espacement interne directement depuis l\'éditeur. Le bloc applique les variables <code>var(--wp--style--spacing--margin)</code> et <code>var(--wp--style--spacing--padding)</code> pour respecter les contrôles du thème et les réglages globaux.', 'discord-bot-jlg')); ?></p>
            <p><?php echo wp_kses_post(__('Besoin d\'un lien direct vers la section&nbsp;? Ajoutez votre ancre HTML personnalisée dans l\'onglet <strong>Avancé</strong> (ex. <code>discord-stats</code>) puis créez un lien interne vers <code>#discord-stats</code>.', 'discord-bot-jlg')); ?></p>

            <h4><?php esc_html_e('Tous les paramètres disponibles :', 'discord-bot-jlg'); ?></h4>
            <div style="background: white; padding: 15px; border-radius: 4px;">
                <h5><?php esc_html_e('🎨 Apparence & Layout :', 'discord-bot-jlg'); ?></h5>
                <ul style="columns: 2; column-gap: 30px;">
                    <li><?php echo wp_kses_post(__('<strong>layout</strong> : horizontal, vertical, compact', 'discord-bot-jlg')); ?></li>
                    <li><?php echo wp_kses_post(__('<strong>theme</strong> : discord, dark, light, minimal, radix, headless, shadcn, bootstrap, semantic, anime', 'discord-bot-jlg')); ?></li>
                    <li><?php echo wp_kses_post(__('<strong>align</strong> : left, center, right', 'discord-bot-jlg')); ?></li>
                    <li><?php echo wp_kses_post(__('<strong>width</strong> : largeur CSS (ex: "300px", "100%")', 'discord-bot-jlg')); ?></li>
                    <li><?php echo wp_kses_post(__('<strong>compact</strong> : true/false (version réduite)', 'discord-bot-jlg')); ?></li>
                    <li><?php echo wp_kses_post(__('<strong>animated</strong> : true/false (animations hover)', 'discord-bot-jlg')); ?></li>
                    <li><?php echo wp_kses_post(__('<strong>class</strong> : classes CSS additionnelles', 'discord-bot-jlg')); ?></li>
                    <li><?php echo wp_kses_post(__('<strong>stat_bg_color</strong> : couleur hex/RGBa des cartes (var CSS <code>--discord-surface-background</code>)', 'discord-bot-jlg')); ?></li>
                    <li><?php echo wp_kses_post(__('<strong>stat_text_color</strong> : couleur hex/RGBa du texte des cartes (<code>--discord-surface-text</code>)', 'discord-bot-jlg')); ?></li>
                    <li><?php echo wp_kses_post(__('<strong>accent_color</strong> : couleur principale du bouton/logo (<code>--discord-accent</code>)', 'discord-bot-jlg')); ?></li>
                    <li><?php echo wp_kses_post(__('<strong>accent_color_alt</strong> : seconde couleur du dégradé du bouton (<code>--discord-accent-secondary</code>)', 'discord-bot-jlg')); ?></li>
                    <li><?php echo wp_kses_post(__('<strong>accent_text_color</strong> : couleur du texte du bouton (<code>--discord-accent-contrast</code>)', 'discord-bot-jlg')); ?></li>
                </ul>

                <h5><?php esc_html_e('🎯 Logo Discord :', 'discord-bot-jlg'); ?></h5>
                <ul>
                    <li><?php echo wp_kses_post(__('<strong>show_discord_icon</strong> : true/false (afficher le logo officiel)', 'discord-bot-jlg')); ?></li>
                    <li><?php echo wp_kses_post(__('<strong>discord_icon_position</strong> : left, right, top (position du logo)', 'discord-bot-jlg')); ?></li>
                </ul>

                <h5><?php esc_html_e('📊 Données affichées :', 'discord-bot-jlg'); ?></h5>
                <ul style="columns: 2; column-gap: 30px;">
                    <li><?php echo wp_kses_post(__('<strong>show_online</strong> : true/false', 'discord-bot-jlg')); ?></li>
                    <li><?php echo wp_kses_post(__('<strong>show_total</strong> : true/false', 'discord-bot-jlg')); ?></li>
                    <li><?php echo wp_kses_post(__('<strong>show_title</strong> : true/false', 'discord-bot-jlg')); ?></li>
                    <li><?php echo wp_kses_post(__('<strong>show_server_name</strong> : true/false (afficher le nom du serveur si disponible)', 'discord-bot-jlg')); ?></li>
                    <li><?php echo wp_kses_post(__('<strong>show_server_avatar</strong> : true/false (afficher l\'avatar du serveur lorsqu\'il est disponible)', 'discord-bot-jlg')); ?></li>
                    <li><?php echo wp_kses_post(__('<strong>avatar_size</strong> : pixels (puissance de deux entre 16 et 4096 pour ajuster la résolution de l\'avatar)', 'discord-bot-jlg')); ?></li>
                    <li><?php echo wp_kses_post(__('<strong>title</strong> : texte du titre', 'discord-bot-jlg')); ?></li>
                    <li><?php echo wp_kses_post(__('<strong>hide_labels</strong> : true/false', 'discord-bot-jlg')); ?></li>
                    <li><?php echo wp_kses_post(__('<strong>hide_icons</strong> : true/false', 'discord-bot-jlg')); ?></li>
                </ul>
                <p><?php echo wp_kses_post(__('💡 Astuce : combinez <code>show_server_name="true"</code> et <code>show_server_avatar="true"</code> avec vos propres classes CSS (ex. <code>.discord-server-name--muted</code>) pour créer un en-tête harmonisé à votre charte graphique.', 'discord-bot-jlg')); ?></p>

                <h5><?php esc_html_e('✏️ Personnalisation textes/icônes :', 'discord-bot-jlg'); ?></h5>
                <ul style="columns: 2; column-gap: 30px;">
                    <li><?php echo wp_kses_post(__('<strong>icon_online</strong> : emoji/texte (défaut: 🟢)', 'discord-bot-jlg')); ?></li>
                    <li><?php echo wp_kses_post(__('<strong>icon_total</strong> : emoji/texte (défaut: 👥)', 'discord-bot-jlg')); ?></li>
                    <li><?php echo wp_kses_post(__('<strong>label_online</strong> : texte personnalisé', 'discord-bot-jlg')); ?></li>
                    <li><?php echo wp_kses_post(__('<strong>label_total</strong> : texte personnalisé', 'discord-bot-jlg')); ?></li>
                </ul>

                <h5><?php esc_html_e('⚙️ Paramètres techniques :', 'discord-bot-jlg'); ?></h5>
                <ul style="columns: 2; column-gap: 30px;">
                    <li><?php echo wp_kses_post(__('<strong>refresh</strong> : true/false (auto-actualisation)', 'discord-bot-jlg')); ?></li>
                    <li><?php echo wp_kses_post(__('<strong>refresh_interval</strong> : secondes (minimum 10&nbsp;secondes / 10 000&nbsp;ms)', 'discord-bot-jlg')); ?></li>
                    <li><?php echo wp_kses_post(__('<strong>demo</strong> : true/false (mode démonstration)', 'discord-bot-jlg')); ?></li>
                    <li><?php echo wp_kses_post(__('<strong>border_radius</strong> : pixels (coins arrondis)', 'discord-bot-jlg')); ?></li>
                    <li><?php echo wp_kses_post(__('<strong>gap</strong> : pixels (espace entre éléments)', 'discord-bot-jlg')); ?></li>
                    <li><?php echo wp_kses_post(__('<strong>padding</strong> : pixels (espacement interne)', 'discord-bot-jlg')); ?></li>
                </ul>
            </div>

            <h3><?php esc_html_e('Option 2 : Widget', 'discord-bot-jlg'); ?></h3>
            <p><?php echo wp_kses_post(__('Allez dans <strong>Apparence &gt; Widgets</strong> et ajoutez le widget <strong>"Discord Bot - JLG"</strong> dans votre sidebar', 'discord-bot-jlg')); ?></p>

            <h3><?php esc_html_e('Option 3 : Code PHP', 'discord-bot-jlg'); ?></h3>
            <p><?php esc_html_e('Pour les développeurs, dans vos templates PHP :', 'discord-bot-jlg'); ?></p>
            <code style="background: white; padding: 10px; display: block; border-radius: 4px;">
                <?php echo esc_html__('<?php echo do_shortcode(\'[discord_stats show_discord_icon="true"]\'); ?>', 'discord-bot-jlg'); ?>
            </code>

            <h3><?php esc_html_e('💡 Configurations recommandées', 'discord-bot-jlg'); ?></h3>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 15px;">
                <div style="background: white; padding: 15px; border-radius: 4px;">
                    <?php echo wp_kses_post(__('<strong>Pour une sidebar :</strong><br>', 'discord-bot-jlg')); ?>
                    <code style="font-size: 12px;"><?php echo esc_html__('[discord_stats layout="vertical" show_discord_icon="true" discord_icon_position="top" compact="true"]', 'discord-bot-jlg'); ?></code>
                </div>
                <div style="background: white; padding: 15px; border-radius: 4px;">
                    <?php echo wp_kses_post(__('<strong>Pour un header :</strong><br>', 'discord-bot-jlg')); ?>
                    <code style="font-size: 12px;"><?php echo esc_html__('[discord_stats show_discord_icon="true" show_title="true" title="Join us!" align="center" width="100%"]', 'discord-bot-jlg'); ?></code>
                </div>
                <div style="background: white; padding: 15px; border-radius: 4px;">
                    <?php echo wp_kses_post(__('<strong>Pour un footer :</strong><br>', 'discord-bot-jlg')); ?>
                    <code style="font-size: 12px;"><?php echo esc_html__('[discord_stats theme="dark" show_discord_icon="true" compact="true"]', 'discord-bot-jlg'); ?></code>
                </div>
                <div style="background: white; padding: 15px; border-radius: 4px;">
                    <?php echo wp_kses_post(__('<strong>Style gaming :</strong><br>', 'discord-bot-jlg')); ?>
                    <code style="font-size: 12px;"><?php echo esc_html__('[discord_stats theme="dark" icon_online="🎮" label_online="Players" show_discord_icon="true"]', 'discord-bot-jlg'); ?></code>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Affiche la section de dépannage.
     */
    private function render_demo_troubleshooting() {
        ?>
        <div style="background: #fff8e1; padding: 20px; border-radius: 8px;">
            <h2><?php esc_html_e('❓ Dépannage', 'discord-bot-jlg'); ?></h2>
            <ul>
                <li><?php echo wp_kses_post(__('<strong>Erreur de connexion ?</strong> Vérifiez que le bot est bien sur votre serveur', 'discord-bot-jlg')); ?></li>
                <li><?php echo wp_kses_post(__('<strong>Stats à 0 ?</strong> Assurez-vous que le widget est activé dans les paramètres Discord', 'discord-bot-jlg')); ?></li>
                <li><?php echo wp_kses_post(__('<strong>Token invalide ?</strong> Régénérez le token dans le Developer Portal', 'discord-bot-jlg')); ?></li>
                <li><?php echo wp_kses_post(__('<strong>Cache ?</strong> Les stats sont mises à jour toutes les 5 minutes par défaut', 'discord-bot-jlg')); ?></li>
            </ul>
        </div>
        <?php
    }

    /**
     * Affiche le pied de page de la page de démo.
     */
    private function render_demo_footer_note() {
        ?>
        <div style="margin-top: 30px; padding: 15px; background: #f0f0f0; border-radius: 8px; text-align: center;">
            <p style="margin: 0;">
                <?php
                $version_label = sprintf(
                    /* translators: %s: plugin version. */
                    __('Discord Bot - JLG v%s', 'discord-bot-jlg'),
                    DISCORD_BOT_JLG_VERSION
                );
                printf(
                    /* translators: %1$s: plugin version label. */
                    esc_html__('%1$s | Développé par Jérôme Le Gousse |', 'discord-bot-jlg'),
                    esc_html($version_label)
                );
                ?>
               <a href="https://discord.com/developers/docs/intro" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Documentation Discord API', 'discord-bot-jlg'); ?></a> |
               <?php esc_html_e('Besoin d\'aide ?', 'discord-bot-jlg'); ?>
            </p>
        </div>
        <?php
    }

    /**
     * Enfile les feuilles de style nécessaires sur les écrans d'administration du plugin.
     *
     * @param string $hook_suffix Identifiant du hook fourni par WordPress pour la page courante.
     *
     * @return void
     */
    public function enqueue_admin_styles($hook_suffix) {
        $allowed_ids = array(
            'toplevel_page_discord-bot-jlg',
            'discord-bot-jlg_page_discord-bot-demo',
        );

        if (!empty($this->demo_page_hook_suffix) && !in_array($this->demo_page_hook_suffix, $allowed_ids, true)) {
            $allowed_ids[] = $this->demo_page_hook_suffix;
        }

        if (function_exists('get_current_screen')) {
            $current_screen = get_current_screen();

            if ($current_screen && !in_array($current_screen->id, $allowed_ids, true)) {
                return;
            }
        } elseif (!in_array($hook_suffix, $allowed_ids, true)) {
            return;
        }

        wp_enqueue_style('wp-components');

        wp_enqueue_style(
            'discord-bot-jlg-admin',
            DISCORD_BOT_JLG_PLUGIN_URL . 'assets/css/discord-bot-jlg-admin.css',
            array(),
            DISCORD_BOT_JLG_VERSION
        );

        if (empty($current_screen) || $current_screen->id !== 'discord-bot-jlg_page_discord-bot-demo') {
            return;
        }

        wp_register_script(
            'discord-bot-jlg-chartjs',
            'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js',
            array(),
            '4.4.1',
            true
        );

        wp_register_script(
            'discord-bot-jlg-admin-analytics',
            DISCORD_BOT_JLG_PLUGIN_URL . 'assets/js/discord-bot-jlg-admin-analytics.js',
            array('discord-bot-jlg-chartjs'),
            DISCORD_BOT_JLG_VERSION,
            true
        );

        $rest_url = rest_url('discord-bot-jlg/v1/analytics');
        $options   = $this->api->get_plugin_options();
        $retention = $this->api->get_analytics_retention_days($options);

        $profiles = $this->api->get_server_profiles(false);
        if (!isset($profiles['default'])) {
            $profiles = array_merge(
                array(
                    'default' => array(
                        'key'   => 'default',
                        'label' => esc_html__('Profil par défaut', 'discord-bot-jlg'),
                    ),
                ),
                $profiles
            );
        }

        $profile_entries = array();
        foreach ($profiles as $profile_key => $profile_data) {
            $label = isset($profile_data['label']) ? $profile_data['label'] : '';
            if ('' === $label) {
                $label = ('default' === $profile_key)
                    ? esc_html__('Profil par défaut', 'discord-bot-jlg')
                    : $profile_key;
            }

            $profile_entries[] = array(
                'key'       => $profile_key,
                'label'     => $label,
                'server_id' => isset($profile_data['server_id']) ? $profile_data['server_id'] : '',
            );
        }

        if (empty($profile_entries)) {
            $profile_entries[] = array(
                'key'   => 'default',
                'label' => esc_html__('Profil par défaut', 'discord-bot-jlg'),
            );
        }

        $requested_profiles = wp_list_pluck($profile_entries, 'key');

        $default_selection = apply_filters(
            'discord_bot_jlg_admin_default_profiles',
            array_slice($requested_profiles, 0, min(2, count($requested_profiles))),
            $profile_entries
        );

        if (empty($default_selection)) {
            $default_selection = array($profile_entries[0]['key']);
        }

        $comparison_presets = $this->build_admin_comparison_presets($profile_entries, $default_selection);
        $annotations = apply_filters('discord_bot_jlg_admin_analytics_annotations', array(), $profile_entries);

        $requested_days = (int) $retention;
        if ($requested_days <= 0) {
            $requested_days = 7;
        }
        $requested_days = min(30, max(7, $requested_days));

        wp_localize_script(
            'discord-bot-jlg-admin-analytics',
            'discordBotJlgAdminAnalytics',
            array(
                'restUrl'        => esc_url_raw($rest_url),
                'nonce'          => wp_create_nonce('wp_rest'),
                'canvasId'       => 'discord-analytics-chart',
                'containerId'    => 'discord-analytics-panel',
                'days'           => $requested_days,
                'defaultMetric'  => 'presence',
                'defaultProfiles'=> array_values($default_selection),
                'requestedProfiles' => array_values($requested_profiles),
                'profiles'       => $profile_entries,
                'comparisonPresets' => $comparison_presets,
                'annotations'    => $annotations,
                'retentionDays'  => (int) $retention,
                'labels'         => array(
                    'averageOnline'      => esc_html__('Moyenne en ligne', 'discord-bot-jlg'),
                    'averagePresence'    => esc_html__('Présence moyenne', 'discord-bot-jlg'),
                    'averageTotal'       => esc_html__('Moyenne totale', 'discord-bot-jlg'),
                    'peakPresence'       => esc_html__('Pic de présence', 'discord-bot-jlg'),
                    'boostTrend'         => esc_html__('Tendance des boosts', 'discord-bot-jlg'),
                    'noData'             => esc_html__('Pas encore de données collectées.', 'discord-bot-jlg'),
                    'rangeLabel'         => esc_html__('Fenêtre temporelle', 'discord-bot-jlg'),
                    'rangeAll'           => esc_html__('Toute la période', 'discord-bot-jlg'),
                    'rangeCustom'        => esc_html__('Personnalisé', 'discord-bot-jlg'),
                    'rangeStart'         => esc_html__('Début', 'discord-bot-jlg'),
                    'rangeEnd'           => esc_html__('Fin', 'discord-bot-jlg'),
                    'exportCsv'          => esc_html__('Exporter CSV', 'discord-bot-jlg'),
                    'exportPng'          => esc_html__('Exporter PNG', 'discord-bot-jlg'),
                    'annotationsToggle'  => esc_html__('Afficher les annotations', 'discord-bot-jlg'),
                    'annotations'        => esc_html__('Annotations', 'discord-bot-jlg'),
                    'annotationOnline'   => esc_html__('Pic en ligne', 'discord-bot-jlg'),
                    'annotationPresence' => esc_html__('Pic de présence', 'discord-bot-jlg'),
                    'annotationPremium'  => esc_html__('Pic de boosts', 'discord-bot-jlg'),
                    'profileFilterLabel' => esc_html__('Profils à comparer', 'discord-bot-jlg'),
                    'profileSelectAll'   => esc_html__('Tout sélectionner', 'discord-bot-jlg'),
                    'profileSelectNone'  => esc_html__('Tout désélectionner', 'discord-bot-jlg'),
                    'presetLabel'        => esc_html__('Presets de comparaison', 'discord-bot-jlg'),
                    'metricLabel'        => esc_html__('Métrique', 'discord-bot-jlg'),
                    'metricPresence'     => esc_html__('Présence', 'discord-bot-jlg'),
                    'metricOnline'       => esc_html__('En ligne', 'discord-bot-jlg'),
                    'metricTotal'        => esc_html__('Total', 'discord-bot-jlg'),
                    'metricPremium'      => esc_html__('Boosts', 'discord-bot-jlg'),
                    'exportTargetLabel'  => esc_html__('Exporter', 'discord-bot-jlg'),
                    'exportCombined'     => esc_html__('Combiné', 'discord-bot-jlg'),
                    'summaryTitle'       => esc_html__('Synthèse', 'discord-bot-jlg'),
                    'summaryPresence'    => esc_html__('Présence moyenne', 'discord-bot-jlg'),
                    'summaryOnline'      => esc_html__('Moy. en ligne', 'discord-bot-jlg'),
                    'summaryTotal'       => esc_html__('Moy. membres', 'discord-bot-jlg'),
                    'summaryPeak'        => esc_html__('Pic de présence', 'discord-bot-jlg'),
                    'summaryBoost'       => esc_html__('Boosts', 'discord-bot-jlg'),
                ),
            )
        );

        wp_enqueue_script('discord-bot-jlg-chartjs');
        wp_enqueue_script('discord-bot-jlg-admin-analytics');
    }

    private function build_admin_comparison_presets($profiles, $default_selection) {
        if (!is_array($profiles)) {
            $profiles = array();
        }

        if (!is_array($default_selection)) {
            $default_selection = array();
        }

        $presets = array();

        $all_keys = array();
        foreach ($profiles as $profile) {
            if (!isset($profile['key'])) {
                continue;
            }
            $all_keys[] = $profile['key'];
        }

        if (!empty($all_keys)) {
            $presets[] = array(
                'id'       => 'all-profiles',
                'label'    => esc_html__('Tous les profils', 'discord-bot-jlg'),
                'profiles' => array_values($all_keys),
            );
        }

        if (!empty($default_selection)) {
            $presets[] = array(
                'id'       => 'default-selection',
                'label'    => esc_html__('Sélection par défaut', 'discord-bot-jlg'),
                'profiles' => array_values($default_selection),
            );
        }

        $featured = array_slice($profiles, 0, 3);
        if (count($featured) >= 2) {
            $presets[] = array(
                'id'       => 'first-duo',
                'label'    => esc_html__('Comparer les deux premiers', 'discord-bot-jlg'),
                'profiles' => array($featured[0]['key'], $featured[1]['key']),
            );
        }

        foreach ($profiles as $profile) {
            if (!isset($profile['key'])) {
                continue;
            }

            $label = isset($profile['label']) && $profile['label']
                ? $profile['label']
                : $profile['key'];

            $presets[] = array(
                'id'       => 'focus-' . sanitize_title($profile['key']),
                'label'    => sprintf(
                    /* translators: %s: profile label. */
                    esc_html__('Focus sur %s', 'discord-bot-jlg'),
                    $label
                ),
                'profiles' => array($profile['key']),
            );
        }

        $unique = array();
        $seen   = array();

        foreach ($presets as $preset) {
            if (empty($preset['profiles'])) {
                continue;
            }

            $signature = implode('|', $preset['profiles']);
            if (isset($seen[$signature])) {
                continue;
            }

            $seen[$signature] = true;
            $unique[]         = $preset;
        }

        return apply_filters('discord_bot_jlg_admin_comparison_presets', $unique, $profiles, $default_selection);
    }

    /**
     * Teste la connexion à l'API Discord et affiche un message selon le résultat obtenu.
     *
     * @return void
     */
    public function test_discord_connection($profile_key = '') {
        $options = get_option($this->option_name);
        if (!is_array($options)) {
            $options = array();
        }

        $profile_key        = discord_bot_jlg_sanitize_profile_key($profile_key);
        $is_default_profile = ('' === $profile_key || 'default' === $profile_key);

        if ('default' === $profile_key) {
            $profile_key = '';
        }

        $profile_label = '';

        if (!$is_default_profile) {
            $profile = $this->locate_server_profile($profile_key, $options);

            if (null === $profile) {
                printf(
                    '<div class="notice notice-error"><p>%s</p></div>',
                    sprintf(
                        /* translators: %s: server profile key. */
                        esc_html__('Profil « %s » introuvable. Enregistrez-le puis réessayez.', 'discord-bot-jlg'),
                        esc_html($profile_key)
                    )
                );

                return;
            }

            if (!empty($profile['label'])) {
                $profile_label = $profile['label'];
            }
        }

        $profile_prefix = '';
        if (!$is_default_profile) {
            $profile_prefix = sprintf(
                /* translators: %s: server profile label. */
                esc_html__('Profil « %s » — ', 'discord-bot-jlg'),
                esc_html('' !== $profile_label ? $profile_label : $profile_key)
            );
        }

        if ($is_default_profile) {
            $fallback_details = $this->api->get_last_fallback_details();

            if (
                !empty($fallback_details)
                && (empty($options['demo_mode']))
            ) {
                $timestamp = isset($fallback_details['timestamp']) ? (int) $fallback_details['timestamp'] : 0;
                if ($timestamp <= 0) {
                    $timestamp = time();
                }

                $date_format = get_option('date_format');
                if (!is_string($date_format) || '' === trim($date_format)) {
                    $date_format = 'Y-m-d';
                }

                $time_format = get_option('time_format');
                if (!is_string($time_format) || '' === trim($time_format)) {
                    $time_format = 'H:i';
                }

                $formatted_time = discord_bot_jlg_format_datetime($date_format . ' ' . $time_format, $timestamp);
                $reason_text    = isset($fallback_details['reason']) ? trim((string) $fallback_details['reason']) : '';
                $message_parts  = array();

                $message_parts[] = sprintf(
                    /* translators: %s: formatted date and time. */
                    esc_html__('⚠️ Statistiques de secours utilisées depuis le %s.', 'discord-bot-jlg'),
                    esc_html($formatted_time)
                );

                if ('' !== $reason_text) {
                    $message_parts[] = sprintf(
                        /* translators: %s: reason for the fallback. */
                        esc_html__('Raison : %s.', 'discord-bot-jlg'),
                        esc_html($reason_text)
                    );
                }

                $next_retry = isset($fallback_details['next_retry']) ? (int) $fallback_details['next_retry'] : 0;

                if ($next_retry > 0) {
                    $seconds_until_retry = max(0, $next_retry - time());
                    $retry_time          = discord_bot_jlg_format_datetime($date_format . ' ' . $time_format, $next_retry);
                    $message_parts[]     = sprintf(
                        /* translators: 1: seconds before retry, 2: formatted date and time. */
                        esc_html__('Prochaine tentative dans %1$d secondes (vers %2$s).', 'discord-bot-jlg'),
                        $seconds_until_retry,
                        esc_html($retry_time)
                    );
                } else {
                    $message_parts[] = esc_html__('Prochaine tentative dès que possible.', 'discord-bot-jlg');
                }

                printf(
                    '<div class="notice notice-warning"><p>%s</p></div>',
                    implode(' ', $message_parts)
                );
            }
        }

        if (!empty($options['demo_mode'])) {
            $message = esc_html__('🎨 Mode démonstration activé - Les données affichées sont fictives', 'discord-bot-jlg');

            if ('' !== $profile_prefix) {
                $message = $profile_prefix . $message;
            }

            printf(
                '<div class="notice notice-info"><p>%s</p></div>',
                $message
            );
            return;
        }

        $args = array(
            'bypass_cache' => true,
        );

        if (!$is_default_profile) {
            $args['profile_key'] = $profile_key;
        }

        $stats = $this->api->get_stats($args);
        $diagnostic = $this->api->get_last_error_message();
        $diagnostic_suffix = '';

        if (!empty($diagnostic)) {
            $diagnostic_suffix = ' ' . esc_html($diagnostic);
        }

        if (is_array($stats) && empty($stats['is_demo'])) {
            $server_name = isset($stats['server_name']) ? $stats['server_name'] : '';
            $online_count = isset($stats['online']) ? (int) $stats['online'] : 0;
            $has_total    = !empty($stats['has_total']) && isset($stats['total']) && null !== $stats['total'];

            if ($has_total) {
                $total_display = esc_html(number_format_i18n((int) $stats['total']));
            } else {
                $total_display = esc_html__('Total indisponible', 'discord-bot-jlg');
            }

            $success_message = sprintf(
                /* translators: 1: server name, 2: online members count, 3: total members count. */
                esc_html__('✅ Connexion réussie ! Serveur : %1$s - %2$s en ligne / %3$s membres', 'discord-bot-jlg'),
                esc_html($server_name),
                esc_html(number_format_i18n($online_count)),
                $total_display
            );

            if ('' !== $profile_prefix) {
                $success_message = $profile_prefix . $success_message;
            }

            printf(
                '<div class="notice notice-success"><p>%s</p></div>',
                $success_message
            );
        } elseif (is_array($stats) && !empty($stats['is_demo'])) {
            $warning_message = esc_html__('⚠️ Pas de configuration Discord détectée. Mode démo actif.', 'discord-bot-jlg');

            if ('' !== $profile_prefix) {
                $warning_message = $profile_prefix . $warning_message;
            }

            printf(
                '<div class="notice notice-warning"><p>%s%s</p></div>',
                $warning_message,
                $diagnostic_suffix
            );
        } else {
            $error_message = esc_html__('❌ Échec de la connexion. Vérifiez vos identifiants.', 'discord-bot-jlg');

            if ('' !== $profile_prefix) {
                $error_message = $profile_prefix . $error_message;
            }

            printf(
                '<div class="notice notice-error"><p>%s%s</p></div>',
                $error_message,
                $diagnostic_suffix
            );
        }
    }


    /**
     * Affiche un bloc de prévisualisation pour un shortcode.
     *
     * @param string $title     Titre affiché au-dessus de la prévisualisation.
     * @param string $shortcode Shortcode à exécuter.
     * @param array  $options   Options d'affichage (style du conteneur, wrapper interne, etc.).
     */
    private function render_preview_block($title, $shortcode, array $options = array()) {
        $container_style      = isset($options['container_style']) ? $options['container_style'] : '';
        $container_class      = isset($options['container_class']) ? (string) $options['container_class'] : '';
        $inner_wrapper_style  = isset($options['inner_wrapper_style']) ? $options['inner_wrapper_style'] : '';
        $inner_wrapper_class  = isset($options['inner_wrapper_class']) ? (string) $options['inner_wrapper_class'] : '';

        $container_classes = array('discord-preview-block');
        if ('' !== $container_class) {
            $additional = preg_split('/\s+/', $container_class);
            if (is_array($additional)) {
                $container_classes = array_merge($container_classes, $additional);
            } else {
                $container_classes[] = $container_class;
            }
        }
        $container_classes = array_unique(array_filter(array_map('trim', $container_classes)));

        $container_attributes = '';

        if (!empty($container_classes)) {
            $container_attributes .= ' class="' . esc_attr(implode(' ', $container_classes)) . '"';
        }

        if ('' !== $container_style) {
            $container_attributes .= ' style="' . esc_attr($container_style) . '"';
        }

        $inner_classes = array('discord-preview-card__inner');
        if ('' !== $inner_wrapper_class) {
            $inner_additional = preg_split('/\s+/', $inner_wrapper_class);
            if (is_array($inner_additional)) {
                $inner_classes = array_merge($inner_classes, $inner_additional);
            } else {
                $inner_classes[] = $inner_wrapper_class;
            }
        }
        $inner_classes = array_unique(array_filter(array_map('trim', $inner_classes)));

        ?>
        <div<?php echo $container_attributes; ?>>
            <h4><?php echo esc_html($title); ?></h4>
            <?php
            if ('' !== $inner_wrapper_style || count($inner_classes) > 1) {
                $inner_attr = '';
                if (!empty($inner_classes)) {
                    $inner_attr .= ' class="' . esc_attr(implode(' ', $inner_classes)) . '"';
                }
                if ('' !== $inner_wrapper_style) {
                    $inner_attr .= ' style="' . esc_attr($inner_wrapper_style) . '"';
                }
                echo '<div' . $inner_attr . '>';
            }

            echo $this->get_admin_shortcode_preview($shortcode);

            if ('' !== $inner_wrapper_style || count($inner_classes) > 1) {
                echo '</div>';
            }
            ?>
        </div>
        <?php
    }

    private function get_admin_shortcode_preview($shortcode) {
        $output = do_shortcode($shortcode);

        if (!is_string($output)) {
            return '';
        }

        return wp_kses($output, $this->get_admin_preview_allowed_html());
    }

    private function get_admin_preview_allowed_html() {
        $allowed_tags = wp_kses_allowed_html('post');

        $div_attributes = isset($allowed_tags['div']) ? $allowed_tags['div'] : array();
        $div_attributes = array_merge(
            $div_attributes,
            array(
                'style'                 => true,
                'data-demo'             => true,
                'data-fallback-demo'    => true,
                'data-stale'            => true,
                'data-last-updated'     => true,
                'data-refresh'          => true,
                'data-show-server-name' => true,
                'data-server-name'      => true,
                'data-value'            => true,
                'data-label-total'      => true,
                'data-label-unavailable'=> true,
                'data-label-approx'     => true,
                'data-placeholder'      => true,
                'data-role'             => true,
            )
        );
        $allowed_tags['div'] = $div_attributes;

        $span_attributes = isset($allowed_tags['span']) ? $allowed_tags['span'] : array();
        $span_attributes = array_merge(
            $span_attributes,
            array(
                'style'      => true,
                'data-value' => true,
            )
        );
        $allowed_tags['span'] = $span_attributes;

        $allowed_tags['svg'] = array(
            'class'       => true,
            'viewbox'     => true,
            'xmlns'       => true,
            'role'        => true,
            'aria-hidden' => true,
            'focusable'   => true,
        );

        $allowed_tags['path'] = array(
            'd'    => true,
            'fill' => true,
        );

        return $allowed_tags;
    }
}
