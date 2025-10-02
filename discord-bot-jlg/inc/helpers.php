<?php
/**
 * Shared helper functions for Discord Bot JLG plugin.
 */

if (!defined('DISCORD_BOT_JLG_SECRET_PREFIX')) {
    define('DISCORD_BOT_JLG_SECRET_PREFIX', 'dbjlg_enc_v1:');
}

if (!function_exists('discord_bot_jlg_get_available_themes')) {
    /**
     * Returns the list of allowed themes for the public components.
     *
     * @return string[]
     */
    function discord_bot_jlg_get_available_themes() {
        $themes = array('discord', 'dark', 'light', 'minimal');

        if (function_exists('apply_filters')) {
            $filtered = apply_filters('discord_bot_jlg_available_themes', $themes);

            if (is_array($filtered)) {
                $themes = $filtered;
            }
        }

        $themes = array_map('strval', $themes);
        $themes = array_filter($themes, function ($theme) {
            return '' !== $theme;
        });

        return array_values(array_unique($themes));
    }
}

if (!function_exists('discord_bot_jlg_is_allowed_theme')) {
    /**
     * Checks whether the provided theme identifier is part of the allowed list.
     *
     * @param string $theme Theme identifier to validate.
     *
     * @return bool
     */
    function discord_bot_jlg_is_allowed_theme($theme) {
        if (!is_string($theme) || '' === $theme) {
            return false;
        }

        return in_array($theme, discord_bot_jlg_get_available_themes(), true);
    }
}

if (!function_exists('discord_bot_jlg_validate_bool')) {
    /**
     * Normalizes a value to a boolean, falling back when wp_validate_boolean() is unavailable.
     *
     * @param mixed $value Value to validate.
     *
     * @return bool
     */
    function discord_bot_jlg_validate_bool($value) {
        if (is_array($value)) {
            $found = false;

            $iterator = new \RecursiveIteratorIterator(new \RecursiveArrayIterator($value));
            foreach ($iterator as $item) {
                if (is_scalar($item)) {
                    $value = $item;
                    $found = true;
                    break;
                }
            }

            if (false === $found) {
                $value = null;
            }
        }

        if (!is_scalar($value)) {
            return false;
        }

        if (function_exists('wp_validate_boolean')) {
            return wp_validate_boolean($value);
        }

        if (is_string($value)) {
            $value = trim($value);
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}

if (!function_exists('discord_bot_jlg_has_wp_date')) {
    /**
     * Determine if wp_date() should be used.
     *
     * @return bool
     */
    function discord_bot_jlg_has_wp_date() {
        if (!empty($GLOBALS['discord_bot_jlg_disable_wp_date'])) {
            return false;
        }

        return function_exists('wp_date');
    }
}

if (!function_exists('discord_bot_jlg_format_datetime')) {
    /**
     * Formats a timestamp using wp_date() when available, falling back to date_i18n().
     *
     * @param string   $format    Format string compatible with PHP date().
     * @param int|null $timestamp Unix timestamp to format.
     *
     * @return string
     */
    function discord_bot_jlg_format_datetime($format, $timestamp = null) {
        if (discord_bot_jlg_has_wp_date()) {
            return wp_date($format, $timestamp);
        }

        return date_i18n($format, $timestamp);
    }
}

if (!function_exists('discord_bot_jlg_is_encrypted_secret')) {
    /**
     * Détermine si une valeur correspond à un secret chiffré reconnu.
     *
     * @param mixed $value Valeur à évaluer.
     *
     * @return bool
     */
    function discord_bot_jlg_is_encrypted_secret($value) {
        return (
            is_string($value)
            && '' !== $value
            && 0 === strpos($value, DISCORD_BOT_JLG_SECRET_PREFIX)
        );
    }
}

if (!function_exists('discord_bot_jlg_encrypt_secret')) {
    /**
     * Chiffre un secret à l'aide d'OpenSSL en utilisant AUTH_KEY et AUTH_SALT.
     *
     * @param string $secret Secret en clair à chiffrer.
     *
     * @return string|WP_Error Secret chiffré (préfixé) ou erreur.
     */
    function discord_bot_jlg_encrypt_secret($secret) {
        if (!is_string($secret) || '' === $secret) {
            return '';
        }

        if (!defined('AUTH_KEY') || !defined('AUTH_SALT')) {
            return new WP_Error(
                'discord_bot_jlg_encrypt_secret_missing_keys',
                __('Les constantes AUTH_KEY et AUTH_SALT sont requises pour chiffrer le token Discord.', 'discord-bot-jlg')
            );
        }

        $has_openssl_encrypt = function_exists('openssl_encrypt');
        if (function_exists('apply_filters')) {
            $has_openssl_encrypt = (bool) apply_filters(
                'discord_bot_jlg_has_openssl_encrypt',
                $has_openssl_encrypt,
                $secret
            );
        }

        if (!$has_openssl_encrypt) {
            return new WP_Error(
                'discord_bot_jlg_encrypt_secret_missing_openssl',
                __('La bibliothèque OpenSSL est requise pour chiffrer le token Discord.', 'discord-bot-jlg')
            );
        }

        $key_material = hash('sha256', AUTH_KEY, true);
        $iv_material  = hash('sha256', AUTH_SALT . AUTH_KEY, true);
        $iv           = substr($iv_material, 0, 16);

        $ciphertext = openssl_encrypt($secret, 'aes-256-cbc', $key_material, OPENSSL_RAW_DATA, $iv);

        if (false === $ciphertext) {
            return new WP_Error(
                'discord_bot_jlg_encrypt_secret_failed',
                __('Le chiffrement du token Discord a échoué.', 'discord-bot-jlg')
            );
        }

        $mac = hash_hmac('sha256', $ciphertext, AUTH_SALT, true);

        return DISCORD_BOT_JLG_SECRET_PREFIX . base64_encode($ciphertext . $mac);
    }
}

if (!function_exists('discord_bot_jlg_decrypt_secret')) {
    /**
     * Déchiffre un secret généré par discord_bot_jlg_encrypt_secret().
     *
     * @param string $secret Secret chiffré à déchiffrer.
     *
     * @return string|WP_Error Secret en clair ou erreur.
     */
    function discord_bot_jlg_decrypt_secret($secret) {
        if (!is_string($secret) || '' === $secret) {
            return new WP_Error(
                'discord_bot_jlg_decrypt_secret_invalid_value',
                __('Le token chiffré fourni est invalide.', 'discord-bot-jlg')
            );
        }

        if (!discord_bot_jlg_is_encrypted_secret($secret)) {
            return new WP_Error(
                'discord_bot_jlg_decrypt_secret_unrecognized_prefix',
                __('Le format du token enregistré n’est pas reconnu comme un secret chiffré.', 'discord-bot-jlg')
            );
        }

        if (!defined('AUTH_KEY') || !defined('AUTH_SALT')) {
            return new WP_Error(
                'discord_bot_jlg_decrypt_secret_missing_keys',
                __('Les constantes AUTH_KEY et AUTH_SALT sont requises pour déchiffrer le token Discord.', 'discord-bot-jlg')
            );
        }

        $has_openssl_decrypt = function_exists('openssl_decrypt');
        if (function_exists('apply_filters')) {
            $has_openssl_decrypt = (bool) apply_filters(
                'discord_bot_jlg_has_openssl_decrypt',
                $has_openssl_decrypt,
                $secret
            );
        }

        if (!$has_openssl_decrypt) {
            return new WP_Error(
                'discord_bot_jlg_decrypt_secret_missing_openssl',
                __('La bibliothèque OpenSSL est requise pour déchiffrer le token Discord.', 'discord-bot-jlg')
            );
        }

        $payload = substr($secret, strlen(DISCORD_BOT_JLG_SECRET_PREFIX));
        $decoded = base64_decode($payload, true);

        if (false === $decoded || strlen($decoded) <= 32) {
            return new WP_Error(
                'discord_bot_jlg_decrypt_secret_invalid_payload',
                __('Le token chiffré est corrompu ou incomplet.', 'discord-bot-jlg')
            );
        }

        $ciphertext = substr($decoded, 0, -32);
        $mac        = substr($decoded, -32);
        $expected   = hash_hmac('sha256', $ciphertext, AUTH_SALT, true);

        if (!hash_equals($expected, $mac)) {
            return new WP_Error(
                'discord_bot_jlg_decrypt_secret_mac_mismatch',
                __('Le token chiffré n’a pas pu être vérifié.', 'discord-bot-jlg')
            );
        }

        $key_material = hash('sha256', AUTH_KEY, true);
        $iv_material  = hash('sha256', AUTH_SALT . AUTH_KEY, true);
        $iv           = substr($iv_material, 0, 16);

        $plaintext = openssl_decrypt($ciphertext, 'aes-256-cbc', $key_material, OPENSSL_RAW_DATA, $iv);

        if (false === $plaintext) {
            return new WP_Error(
                'discord_bot_jlg_decrypt_secret_failed',
                __('Le déchiffrement du token Discord a échoué.', 'discord-bot-jlg')
            );
        }

        return $plaintext;
    }
}

if (!function_exists('discord_bot_jlg_sanitize_custom_css')) {
    /**
     * Sanitizes custom CSS by removing any HTML/JS tags while preserving CSS syntax.
     *
     * @param string $css Raw CSS provided by the user.
     *
     * @return string Sanitized CSS string safe to store.
     */
    function discord_bot_jlg_sanitize_custom_css($css) {
        if (!is_string($css)) {
            if (is_scalar($css)) {
                $css = (string) $css;
            } else {
                return '';
            }
        }

        if ('' === $css) {
            return '';
        }

        $css = str_replace("\0", '', $css);

        $css = preg_replace('#<\s*(script|style)[^>]*>.*?<\s*/\s*(?:script|style)\s*>#is', '', $css);
        $css = preg_replace('#<!--.*?-->#s', '', $css);
        $css = preg_replace('#<\?(?:php|=)?[\s\S]*?\?>#i', '', $css);

        $css = strip_tags($css);

        $css = preg_replace('#</[^>]*>#i', '', $css);

        $css = str_ireplace(array('<script', '</script', '<style', '</style'), '', $css);

        return trim($css);
    }
}
