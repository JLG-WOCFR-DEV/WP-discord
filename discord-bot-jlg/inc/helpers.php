<?php
/**
 * Shared helper functions for Discord Bot JLG plugin.
 */

if (!defined('DISCORD_BOT_JLG_SECRET_PREFIX_LEGACY')) {
    define('DISCORD_BOT_JLG_SECRET_PREFIX_LEGACY', 'dbjlg_enc_v1:');
}

if (!defined('DISCORD_BOT_JLG_SECRET_PREFIX')) {
    define('DISCORD_BOT_JLG_SECRET_PREFIX', 'dbjlg_enc_v2:');
}

if (!function_exists('absint')) {
    /**
     * Lightweight polyfill for WordPress absint().
     *
     * @param mixed $maybeint Value to sanitize.
     *
     * @return int Absolute integer value or 0 when conversion fails.
     */
    function absint($maybeint) {
        return abs((int) $maybeint);
    }
}

if (!function_exists('sanitize_hex_color')) {
    /**
     * Lightweight polyfill for WordPress sanitize_hex_color().
     *
     * @param string $color Possible hex color value.
     *
     * @return string|null Sanitized hex color or null when invalid.
     */
    function sanitize_hex_color($color) {
        if (!is_string($color)) {
            return null;
        }

        $color = trim($color);

        if ('' === $color) {
            return '';
        }

        if ('#' !== substr($color, 0, 1)) {
            $color = '#' . $color;
        }

        if (preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $color, $matches)) {
            return '#' . strtolower($matches[1]);
        }

        return null;
    }
}

if (!function_exists('discord_bot_jlg_get_available_themes')) {
    /**
     * Returns the list of allowed themes for the public components.
     *
     * @return string[]
     */
    function discord_bot_jlg_get_available_themes() {
        $themes = array(
            'discord',
            'dark',
            'light',
            'minimal',
            'radix',
            'headless',
            'shadcn',
            'bootstrap',
            'semantic',
            'anime',
        );

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

if (!function_exists('discord_bot_jlg_sanitize_color')) {
    /**
     * Sanitizes a color value ensuring it is a safe HEX, RGB or RGBA string.
     *
     * @param mixed $color Color value to sanitize.
     *
     * @return string Sanitized color or empty string when invalid.
     */
    function discord_bot_jlg_sanitize_color($color) {
        if (!is_string($color)) {
            return '';
        }

        $color = trim($color);

        if ('' === $color) {
            return '';
        }

        $hex = sanitize_hex_color($color);
        if (is_string($hex)) {
            return $hex;
        }

        if (preg_match('/^#([0-9a-fA-F]{4}|[0-9a-fA-F]{8})$/', $color, $matches)) {
            return '#' . strtolower($matches[1]);
        }

        if (preg_match('/^rgba?\(\s*([0-9]{1,3})\s*,\s*([0-9]{1,3})\s*,\s*([0-9]{1,3})(?:\s*,\s*([0-9]*\.?[0-9]+))?\s*\)$/i', $color, $matches)) {
            $red   = min(255, (int) $matches[1]);
            $green = min(255, (int) $matches[2]);
            $blue  = min(255, (int) $matches[3]);

            $has_alpha = (isset($matches[4]) && '' !== $matches[4]);

            if ($has_alpha) {
                $alpha = (float) $matches[4];
                if ($alpha < 0) {
                    $alpha = 0;
                } elseif ($alpha > 1) {
                    $alpha = 1;
                }

                $alpha_string = rtrim(rtrim(sprintf('%.3f', $alpha), '0'), '.');

                if ('' === $alpha_string) {
                    $alpha_string = '0';
                }

                return sprintf('rgba(%d, %d, %d, %s)', $red, $green, $blue, $alpha_string);
            }

            return sprintf('rgb(%d, %d, %d)', $red, $green, $blue);
        }

        return '';
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
        if (!is_string($value) || '' === $value) {
            return false;
        }

        $prefixes = array(DISCORD_BOT_JLG_SECRET_PREFIX);

        if (defined('DISCORD_BOT_JLG_SECRET_PREFIX_LEGACY')) {
            $prefixes[] = DISCORD_BOT_JLG_SECRET_PREFIX_LEGACY;
        }

        $prefixes = array_values(array_unique(array_filter($prefixes, 'strlen')));

        foreach ($prefixes as $prefix) {
            if (0 === strpos($value, $prefix)) {
                return true;
            }
        }

        return false;
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

        try {
            $iv = random_bytes(16);
        } catch (Exception $exception) {
            if (function_exists('openssl_random_pseudo_bytes')) {
                $iv = openssl_random_pseudo_bytes(16);
            } else {
                $iv = false;
            }
        }

        if (false === $iv || 16 !== strlen($iv)) {
            return new WP_Error(
                'discord_bot_jlg_encrypt_secret_iv_generation_failed',
                __('La génération de l’IV aléatoire a échoué pour le chiffrement du token Discord.', 'discord-bot-jlg')
            );
        }

        $key_material = hash('sha256', AUTH_KEY, true);

        $ciphertext = openssl_encrypt($secret, 'aes-256-cbc', $key_material, OPENSSL_RAW_DATA, $iv);

        if (false === $ciphertext) {
            return new WP_Error(
                'discord_bot_jlg_encrypt_secret_failed',
                __('Le chiffrement du token Discord a échoué.', 'discord-bot-jlg')
            );
        }

        $mac = hash_hmac('sha256', $iv . $ciphertext, AUTH_SALT, true);

        return DISCORD_BOT_JLG_SECRET_PREFIX . base64_encode($iv . $ciphertext . $mac);
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

        $prefixes = array(
            'current' => DISCORD_BOT_JLG_SECRET_PREFIX,
        );

        if (defined('DISCORD_BOT_JLG_SECRET_PREFIX_LEGACY')) {
            $prefixes['legacy'] = DISCORD_BOT_JLG_SECRET_PREFIX_LEGACY;
        }

        $matched_prefix_key = null;
        $matched_prefix     = null;

        foreach ($prefixes as $key => $prefix) {
            if (0 === strpos($secret, $prefix)) {
                $matched_prefix_key = $key;
                $matched_prefix     = $prefix;
                break;
            }
        }

        if (null === $matched_prefix) {
            return new WP_Error(
                'discord_bot_jlg_decrypt_secret_unrecognized_prefix',
                __('Le format du token enregistré n’est pas reconnu comme un secret chiffré.', 'discord-bot-jlg')
            );
        }

        $payload = substr($secret, strlen($matched_prefix));
        $decoded = base64_decode($payload, true);

        if (false === $decoded) {
            return new WP_Error(
                'discord_bot_jlg_decrypt_secret_invalid_payload',
                __('Le token chiffré est corrompu ou incomplet.', 'discord-bot-jlg')
            );
        }

        $key_material = hash('sha256', AUTH_KEY, true);

        if ('legacy' === $matched_prefix_key) {
            if (strlen($decoded) <= 32) {
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

            $iv_material = hash('sha256', AUTH_SALT . AUTH_KEY, true);
            $iv          = substr($iv_material, 0, 16);

            $plaintext = openssl_decrypt($ciphertext, 'aes-256-cbc', $key_material, OPENSSL_RAW_DATA, $iv);

            if (false === $plaintext) {
                return new WP_Error(
                    'discord_bot_jlg_decrypt_secret_failed',
                    __('Le déchiffrement du token Discord a échoué.', 'discord-bot-jlg')
                );
            }

            if (function_exists('discord_bot_jlg_encrypt_secret')) {
                $migrated = discord_bot_jlg_encrypt_secret($plaintext);

                if (!is_wp_error($migrated) && function_exists('do_action')) {
                    do_action('discord_bot_jlg_secret_migrated', $migrated, $secret);
                }
            }

            return $plaintext;
        }

        $decoded_length = strlen($decoded);
        $iv_length      = 16;
        $mac_length     = 32;

        if ($decoded_length >= ($iv_length + $mac_length + 1)) {
            $iv                 = substr($decoded, 0, $iv_length);
            $ciphertext_and_mac = substr($decoded, $iv_length);

            if (strlen($ciphertext_and_mac) > $mac_length) {
                $ciphertext = substr($ciphertext_and_mac, 0, -$mac_length);
                $mac        = substr($ciphertext_and_mac, -$mac_length);
                $expected   = hash_hmac('sha256', $iv . $ciphertext, AUTH_SALT, true);

                if (hash_equals($expected, $mac)) {
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
        }

        if ($decoded_length <= $mac_length) {
            return new WP_Error(
                'discord_bot_jlg_decrypt_secret_invalid_payload',
                __('Le token chiffré est corrompu ou incomplet.', 'discord-bot-jlg')
            );
        }

        $ciphertext = substr($decoded, 0, -$mac_length);
        $mac        = substr($decoded, -$mac_length);
        $expected   = hash_hmac('sha256', $ciphertext, AUTH_SALT, true);

        if (!hash_equals($expected, $mac)) {
            return new WP_Error(
                'discord_bot_jlg_decrypt_secret_mac_mismatch',
                __('Le token chiffré n’a pas pu être vérifié.', 'discord-bot-jlg')
            );
        }

        $iv_material = hash('sha256', AUTH_SALT . AUTH_KEY, true);
        $iv          = substr($iv_material, 0, $iv_length);

        $plaintext = openssl_decrypt($ciphertext, 'aes-256-cbc', $key_material, OPENSSL_RAW_DATA, $iv);

        if (false === $plaintext) {
            return new WP_Error(
                'discord_bot_jlg_decrypt_secret_failed',
                __('Le déchiffrement du token Discord a échoué.', 'discord-bot-jlg')
            );
        }

        if (function_exists('discord_bot_jlg_encrypt_secret')) {
            $migrated = discord_bot_jlg_encrypt_secret($plaintext);

            if (!is_wp_error($migrated) && function_exists('do_action')) {
                do_action('discord_bot_jlg_secret_migrated', $migrated, $secret);
            }
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

if (!function_exists('discord_bot_jlg_is_psr_logger')) {
    /**
     * Vérifie si un objet implémente l'interface PSR-3 Logger.
     *
     * @param mixed $candidate Objet à tester.
     *
     * @return bool
     */
    function discord_bot_jlg_is_psr_logger($candidate) {
        if (!is_object($candidate)) {
            return false;
        }

        if (!interface_exists('Psr\\Log\\LoggerInterface')) {
            return false;
        }

        return ($candidate instanceof Psr\Log\LoggerInterface);
    }
}

if (!function_exists('discord_bot_jlg_logger_debug')) {
    /**
     * Tente de consigner un message via un logger PSR-3.
     *
     * @param mixed  $logger  Instance potentielle de logger PSR-3.
     * @param string $message Message à journaliser.
     * @param array  $context Contexte optionnel.
     *
     * @return bool True si le logger a été utilisé, false sinon.
     */
    function discord_bot_jlg_logger_debug($logger, $message, array $context = array()) {
        if (!discord_bot_jlg_is_psr_logger($logger)) {
            return false;
        }

        $trimmed_message = trim((string) $message);

        if ('' === $trimmed_message) {
            return false;
        }

        $logger->debug($trimmed_message, $context);

        return true;
    }
}
