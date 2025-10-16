<?php
/**
 * Lightweight shim for loading plugin defaults in tests without bootstrapping
 * the full WordPress runtime.
 */

if (!defined('DISCORD_BOT_JLG_PLUGIN_PATH')) {
    define('DISCORD_BOT_JLG_PLUGIN_PATH', dirname(__DIR__, 3) . '/');
}

if (!defined('DISCORD_BOT_JLG_PLUGIN_URL')) {
    define('DISCORD_BOT_JLG_PLUGIN_URL', 'https://example.com/wp-content/plugins/discord-bot-jlg/');
}

if (!defined('DISCORD_BOT_JLG_VERSION')) {
    define('DISCORD_BOT_JLG_VERSION', 'test');
}

if (!defined('DISCORD_BOT_JLG_OPTION_NAME')) {
    define('DISCORD_BOT_JLG_OPTION_NAME', 'discord_server_stats_options');
}

if (!defined('DISCORD_BOT_JLG_CACHE_KEY')) {
    define('DISCORD_BOT_JLG_CACHE_KEY', 'discord_server_stats_cache');
}

if (!defined('DISCORD_BOT_JLG_DEFAULT_CACHE_DURATION')) {
    define('DISCORD_BOT_JLG_DEFAULT_CACHE_DURATION', 300);
}

if (!defined('DISCORD_BOT_JLG_CRON_HOOK')) {
    define('DISCORD_BOT_JLG_CRON_HOOK', 'discord_bot_jlg_refresh_cache');
}

if (!defined('DISCORD_BOT_JLG_ANALYTICS_RETENTION_DEFAULT')) {
    define('DISCORD_BOT_JLG_ANALYTICS_RETENTION_DEFAULT', 90);
}

if (!function_exists('discord_bot_jlg_get_default_options')) {
    function discord_bot_jlg_get_default_options() {
        return array(
            'server_id'                     => '',
            'bot_token'                     => '',
            'bot_token_rotated_at'          => 0,
            'server_profiles'               => array(),
            'demo_mode'                     => false,
            'show_online'                   => true,
            'show_total'                    => true,
            'custom_css'                    => '',
            'widget_title'                  => 'Discord Server',
            'cache_duration'                => DISCORD_BOT_JLG_DEFAULT_CACHE_DURATION,
            'analytics_retention_days'      => DISCORD_BOT_JLG_ANALYTICS_RETENTION_DEFAULT,
            'analytics_alert_webhook_secret'=> '',
        );
    }
}
