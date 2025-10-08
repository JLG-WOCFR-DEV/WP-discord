<?php
/**
 * Cron helpers for Discord Bot JLG.
 *
 * @package DiscordBotJLG
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('discord_bot_jlg_get_cron_interval')) {
    /**
     * Returns the interval used for the automatic cache refresh schedule.
     *
     * @return int Interval in seconds.
     */
    function discord_bot_jlg_get_cron_interval() {
        $default_interval = (int) DISCORD_BOT_JLG_DEFAULT_CACHE_DURATION;

        if ($default_interval <= 0) {
            $default_interval = 300;
        }

        $options = get_option(DISCORD_BOT_JLG_OPTION_NAME, array());

        if (!is_array($options)) {
            $options = array();
        }

        $interval = isset($options['cache_duration'])
            ? (int) $options['cache_duration']
            : $default_interval;

        if ($interval < 60) {
            $interval = 60;
        } elseif ($interval > 3600) {
            $interval = 3600;
        }

        $interval = (int) apply_filters('discord_bot_jlg_cron_interval', $interval);

        if ($interval < 60) {
            $interval = 60;
        } elseif ($interval > 3600) {
            $interval = 3600;
        }

        return $interval;
    }
}

if (!function_exists('discord_bot_jlg_register_cron_schedule')) {
    /**
     * Declares the custom cron schedule used for refreshing the cache.
     *
     * @param array $schedules Registered cron schedules.
     *
     * @return array
     */
    function discord_bot_jlg_register_cron_schedule($schedules) {
        if (!is_array($schedules)) {
            $schedules = array();
        }

        $schedules['discord_bot_jlg_refresh'] = array(
            'interval' => discord_bot_jlg_get_cron_interval(),
            'display'  => __('Discord Bot JLG cache refresh', 'discord-bot-jlg'),
        );

        return $schedules;
    }
}

