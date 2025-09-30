<?php
/**
 * Shared helper functions for Discord Bot JLG plugin.
 */

if (!defined('DISCORD_BOT_JLG_SECRET_PREFIX_V1')) {
    define('DISCORD_BOT_JLG_SECRET_PREFIX_V1', 'dbjlg_enc_v1:');
}

if (!defined('DISCORD_BOT_JLG_SECRET_PREFIX_V2')) {
    define('DISCORD_BOT_JLG_SECRET_PREFIX_V2', 'dbjlg_enc_v2:');
}

if (!defined('DISCORD_BOT_JLG_SECRET_PREFIX')) {
    define('DISCORD_BOT_JLG_SECRET_PREFIX', DISCORD_BOT_JLG_SECRET_PREFIX_V2);
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

        $prefixes = array(
            DISCORD_BOT_JLG_SECRET_PREFIX,
            DISCORD_BOT_JLG_SECRET_PREFIX_V1,
            DISCORD_BOT_JLG_SECRET_PREFIX_V2,
        );

        $prefixes = array_unique(array_filter($prefixes, 'strlen'));

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

        if (!function_exists('openssl_encrypt') || !function_exists('openssl_random_pseudo_bytes')) {
            return new WP_Error(
                'discord_bot_jlg_encrypt_secret_missing_openssl',
                __('La bibliothèque OpenSSL est requise pour chiffrer le token Discord.', 'discord-bot-jlg')
            );
        }

        $iv = openssl_random_pseudo_bytes(16, $is_strong);

        if (false === $iv || 16 !== strlen($iv) || true !== $is_strong) {
            return new WP_Error(
                'discord_bot_jlg_encrypt_secret_iv_generation_failed',
                __('La génération de l’IV nécessaire au chiffrement du token Discord a échoué.', 'discord-bot-jlg')
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

        $mac_payload = $ciphertext . $iv;
        $mac         = hash_hmac('sha256', $mac_payload, AUTH_SALT, true);

        return DISCORD_BOT_JLG_SECRET_PREFIX . base64_encode($ciphertext . $iv . $mac);
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

        if (!defined('AUTH_KEY') || !defined('AUTH_SALT')) {
            return new WP_Error(
                'discord_bot_jlg_decrypt_secret_missing_keys',
                __('Les constantes AUTH_KEY et AUTH_SALT sont requises pour déchiffrer le token Discord.', 'discord-bot-jlg')
            );
        }

        if (!function_exists('openssl_decrypt')) {
            return new WP_Error(
                'discord_bot_jlg_decrypt_secret_missing_openssl',
                __('La bibliothèque OpenSSL est requise pour déchiffrer le token Discord.', 'discord-bot-jlg')
            );
        }

        $scheme  = null;
        $prefix  = null;

        if (0 === strpos($secret, DISCORD_BOT_JLG_SECRET_PREFIX_V2)) {
            $scheme = 'v2';
            $prefix = DISCORD_BOT_JLG_SECRET_PREFIX_V2;
        } elseif (0 === strpos($secret, DISCORD_BOT_JLG_SECRET_PREFIX_V1)) {
            $scheme = 'v1';
            $prefix = DISCORD_BOT_JLG_SECRET_PREFIX_V1;
        } elseif (0 === strpos($secret, DISCORD_BOT_JLG_SECRET_PREFIX)) {
            $scheme = 'v2';
            $prefix = DISCORD_BOT_JLG_SECRET_PREFIX;
        }

        if (null === $prefix) {
            return new WP_Error(
                'discord_bot_jlg_decrypt_secret_unrecognized_prefix',
                __('Le format du token enregistré n’est pas reconnu comme un secret chiffré.', 'discord-bot-jlg')
            );
        }

        $payload = substr($secret, strlen($prefix));
        $decoded = base64_decode($payload, true);

        if (false === $decoded || strlen($decoded) <= 32) {
            return new WP_Error(
                'discord_bot_jlg_decrypt_secret_invalid_payload',
                __('Le token chiffré est corrompu ou incomplet.', 'discord-bot-jlg')
            );
        }

        $key_material = hash('sha256', AUTH_KEY, true);

        if ('v1' === $scheme) {
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
        } else {
            if (strlen($decoded) <= 48) {
                return new WP_Error(
                    'discord_bot_jlg_decrypt_secret_invalid_payload',
                    __('Le token chiffré est corrompu ou incomplet.', 'discord-bot-jlg')
                );
            }

            $mac_length = 32;
            $iv_length  = 16;

            $mac        = substr($decoded, -$mac_length);
            $iv         = substr($decoded, -($mac_length + $iv_length), $iv_length);
            $ciphertext = substr($decoded, 0, -($mac_length + $iv_length));
            $expected   = hash_hmac('sha256', $ciphertext . $iv, AUTH_SALT, true);

            if (!hash_equals($expected, $mac)) {
                return new WP_Error(
                    'discord_bot_jlg_decrypt_secret_mac_mismatch',
                    __('Le token chiffré n’a pas pu être vérifié.', 'discord-bot-jlg')
                );
            }

            $plaintext = openssl_decrypt($ciphertext, 'aes-256-cbc', $key_material, OPENSSL_RAW_DATA, $iv);
        }

        if (false === $plaintext) {
            return new WP_Error(
                'discord_bot_jlg_decrypt_secret_failed',
                __('Le déchiffrement du token Discord a échoué.', 'discord-bot-jlg')
            );
        }

        return $plaintext;
    }
}
