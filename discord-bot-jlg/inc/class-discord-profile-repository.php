<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Gère la résolution des profils de serveur et les signatures de cache associées.
 */
class Discord_Bot_JLG_Profile_Repository {

    /**
     * Résout le contexte de connexion à partir des arguments fournis.
     *
     * @param array  $args           Arguments de récupération.
     * @param array  $options        Options du plugin.
     * @param string $base_cache_key Clef de cache par défaut.
     *
     * @return array
     */
    public function resolve_context($args, $options, $base_cache_key) {
        $args    = is_array($args) ? $args : array();
        $options = is_array($options) ? $options : array();

        $effective_options = $options;
        $signature_parts   = array();

        $token_override_keys = array(
            'bot_token',
            'bot_token_override',
            '__bot_token_override',
            'botToken',
            'botTokenOverride',
        );

        foreach ($token_override_keys as $token_key) {
            if (array_key_exists($token_key, $args)) {
                unset($args[$token_key]);
            }
        }

        $profile_key        = isset($args['profile_key']) ? sanitize_key($args['profile_key']) : '';
        $server_id_override = isset($args['server_id']) ? self::sanitize_server_id($args['server_id']) : '';

        if ('' !== $profile_key) {
            $profile = $this->find_profile($profile_key, $options);

            if (null === $profile) {
                $signature = 'profile-missing:' . $profile_key;

                return array(
                    'options'   => $effective_options,
                    'cache_key' => $this->build_cache_key($base_cache_key, $signature),
                    'signature' => $signature,
                    'error'     => new WP_Error(
                        'discord_bot_jlg_profile_not_found',
                        sprintf(
                            /* translators: %s: server profile key. */
                            __('Le profil de serveur « %s » est introuvable.', 'discord-bot-jlg'),
                            $profile_key
                        )
                    ),
                );
            }

            if (!empty($profile['server_id'])) {
                $effective_options['server_id'] = self::sanitize_server_id($profile['server_id']);
            }

            if (isset($profile['bot_token']) && '' !== $profile['bot_token']) {
                $effective_options['bot_token'] = $profile['bot_token'];
                $effective_options['__bot_token_override'] = true;
            }

            $signature_parts[] = 'profile:' . $profile_key;
        }

        if ('' !== $server_id_override) {
            $effective_options['server_id'] = $server_id_override;
            $signature_parts[] = 'server:' . $server_id_override;
        }

        if (!isset($effective_options['server_id'])) {
            $effective_options['server_id'] = '';
        } else {
            $effective_options['server_id'] = self::sanitize_server_id($effective_options['server_id']);
        }

        $signature = 'default';

        if (!empty($signature_parts)) {
            $signature = implode('|', $signature_parts);
        }

        $active_profile_key = ('' !== $profile_key) ? $profile_key : 'default';
        $effective_options['__active_profile_key'] = $active_profile_key;
        $effective_options['__request_signature'] = ('' !== $signature) ? $signature : 'default';

        return array(
            'options'   => $effective_options,
            'cache_key' => $this->build_cache_key($base_cache_key, $signature),
            'signature' => $signature,
        );
    }

    /**
     * Recherche un profil en fonction de sa clef.
     *
     * @param string $profile_key Clef du profil.
     * @param array  $options     Options globales du plugin.
     *
     * @return array|null
     */
    public function find_profile($profile_key, $options) {
        if (!is_array($options) || !isset($options['server_profiles']) || !is_array($options['server_profiles'])) {
            return null;
        }

        foreach ($options['server_profiles'] as $stored_key => $profile) {
            if (!is_array($profile)) {
                continue;
            }

            $candidate_key = '';

            if (isset($profile['key'])) {
                $candidate_key = sanitize_key($profile['key']);
            }

            if ('' === $candidate_key) {
                $candidate_key = sanitize_key($stored_key);
            }

            if ('' === $candidate_key || $candidate_key !== $profile_key) {
                continue;
            }

            return array(
                'key'       => $candidate_key,
                'label'     => isset($profile['label']) ? sanitize_text_field($profile['label']) : '',
                'server_id' => isset($profile['server_id']) ? self::sanitize_server_id($profile['server_id']) : '',
                'bot_token' => isset($profile['bot_token']) ? (string) $profile['bot_token'] : '',
            );
        }

        return null;
    }

    /**
     * Génère la clef de cache dérivée d'une signature.
     *
     * @param string $base_cache_key Clef par défaut.
     * @param string $signature      Signature de la requête.
     *
     * @return string
     */
    public function build_cache_key($base_cache_key, $signature) {
        $base_cache_key = (string) $base_cache_key;
        $signature      = (string) $signature;

        if ('' === $signature || 'default' === $signature) {
            return $base_cache_key;
        }

        return $base_cache_key . '_' . md5($signature);
    }

    /**
     * Normalise un identifiant de serveur Discord.
     *
     * @param mixed $value Valeur à normaliser.
     *
     * @return string
     */
    public static function sanitize_server_id($value) {
        if (!is_string($value) && !is_numeric($value)) {
            return '';
        }

        $value = preg_replace('/[^0-9]/', '', (string) $value);

        return (string) $value;
    }
}
