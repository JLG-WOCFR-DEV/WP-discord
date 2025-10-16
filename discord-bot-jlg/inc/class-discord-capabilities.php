<?php
if (!defined('ABSPATH')) {
    exit;
}

class Discord_Bot_JLG_Capabilities {
    const MANAGE_SETTINGS = 'manage_discord_bot';
    const MANAGE_PROFILES = 'manage_discord_profiles';
    const VIEW_ANALYTICS = 'view_discord_analytics';
    const EXPORT_ANALYTICS = 'export_discord_analytics';
    const MANAGE_ALERTS = 'manage_discord_alerts';

    public static function get_capability_map() {
        $map = array(
            'manage_settings' => self::MANAGE_SETTINGS,
            'manage_profiles' => self::MANAGE_PROFILES,
            'view_analytics'  => self::VIEW_ANALYTICS,
            'export_analytics'=> self::EXPORT_ANALYTICS,
            'manage_alerts'   => self::MANAGE_ALERTS,
        );

        if (function_exists('apply_filters')) {
            $filtered = apply_filters('discord_bot_jlg_capability_map', $map);
            if (is_array($filtered)) {
                $map = $filtered;
            }
        }

        $normalized = array();

        foreach ($map as $action => $capability) {
            if (!is_string($action) || '' === $action) {
                continue;
            }

            if (!is_string($capability) || '' === $capability) {
                continue;
            }

            $normalized[$action] = $capability;
        }

        return $normalized;
    }

    public static function get_capability($action) {
        $map = self::get_capability_map();

        if (isset($map[$action])) {
            return $map[$action];
        }

        if (is_string($action)) {
            if (0 === strpos($action, 'view_profile_stats:')) {
                return self::VIEW_ANALYTICS;
            }

            if (
                0 === strpos($action, 'view_profile_analytics:')
                || 0 === strpos($action, 'view_profile_events:')
            ) {
                return self::VIEW_ANALYTICS;
            }

            if (0 === strpos($action, 'export_profile_analytics:')) {
                return self::EXPORT_ANALYTICS;
            }
        }

        return 'manage_options';
    }

    public static function current_user_can($action, $user_id = null) {
        $capability = self::get_capability($action);

        if (null === $user_id) {
            if (function_exists('current_user_can') && current_user_can($capability)) {
                return true;
            }

            if (
                'manage_options' !== $capability
                && function_exists('current_user_can')
                && current_user_can('manage_options')
            ) {
                return true;
            }

            return false;
        }

        if (function_exists('user_can') && user_can($user_id, $capability)) {
            return true;
        }

        if (
            'manage_options' !== $capability
            && function_exists('user_can')
            && user_can($user_id, 'manage_options')
        ) {
            return true;
        }

        return false;
    }

    public static function ensure_roles_have_capabilities() {
        if (!function_exists('get_role')) {
            return;
        }

        $roles = array('administrator');
        if (function_exists('apply_filters')) {
            $filtered_roles = apply_filters('discord_bot_jlg_capability_roles', $roles);
            if (is_array($filtered_roles) && !empty($filtered_roles)) {
                $roles = $filtered_roles;
            }
        }

        $capabilities = array_values(self::get_capability_map());
        $capabilities = array_values(array_unique(array_filter($capabilities, 'is_string')));

        if (empty($capabilities)) {
            return;
        }

        foreach ($roles as $role_name) {
            if (!is_string($role_name) || '' === $role_name) {
                continue;
            }

            $role = get_role($role_name);
            if (!$role || !is_object($role) || !method_exists($role, 'add_cap')) {
                continue;
            }

            foreach ($capabilities as $capability) {
                if (!$role->has_cap($capability)) {
                    $role->add_cap($capability);
                }
            }
        }
    }
}
