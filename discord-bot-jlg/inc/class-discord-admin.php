<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Gère l'intégration du plugin dans l'administration WordPress (menus, pages, formulaires et assets).
 */
class Discord_Bot_JLG_Admin {

    private $option_name;
    private $api;
    private $demo_page_hook_suffix;

    /**
     * Initialise l'instance avec la clé d'option et le client API utilisé pour les vérifications.
     *
     * @param string              $option_name Nom de l'option stockant la configuration du plugin.
     * @param Discord_Bot_JLG_API $api         Service d'accès aux statistiques Discord.
     *
     * @return void
     */
    public function __construct($option_name, Discord_Bot_JLG_API $api) {
        $this->option_name = $option_name;
        $this->api         = $api;
        $this->demo_page_hook_suffix = '';
    }

    /**
     * Enregistre le menu principal et les sous-menus du plugin dans l'administration WordPress.
     *
     * @return void
     */
    public function add_admin_menu() {
        $discord_icon = 'data:image/svg+xml;base64,PHN2ZyB2aWV3Qm94PSIwIDAgMjQgMjQiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PHBhdGggZmlsbD0iI2E0YWFiOCIgZD0iTTIwLjMxNyA0LjM3YTE5LjggMTkuOCAwIDAwLTQuODg1LTEuNTE1LjA3NC4wNzQgMCAwMC0uMDc5LjAzN2MtLjIxLjM3NS0uNDQ0Ljg2NC0uNjA4IDEuMjVhMTguMjcgMTguMjcgMCAwMC01LjQ4NyAwYy0uMTY1LS4zOTctLjQwNC0uODg1LS42MTgtMS4yNWEuMDc3LjA3NyAwIDAwLS4wNzktLjAzN0ExOS43NCAxOS43NCAwIDAwMy42NzcgNC4zN2EuMDcuMDcgMCAwMC0uMDMyLjAyN0MuNTMzIDkuMDQ2LS4zMiAxMy41OC4wOTkgMTguMDU3YS4wOC4wOCAwIDAwLjAzMS4wNTdBMTkuOSAxOS45IDAgMDA2LjA3MyAyMWEuMDc4LjA3OCAwIDAwLjA4NC0uMDI4IDEzLjQgMTMuNCAwIDAwMS4xNTUtMi4xLjA3Ni4wNzYgMCAwMC0uMDQxLS4xMDYgMTMuMSAxMy4xIDAgMDEtMS44NzItLjg5Mi4wNzcuMDc3IDAgMDEtLjAwOC0uMTI4IDE0IDE0IDAgMDAuMzctLjI5Mi4wNzQuMDc0IDAgMDEuMDc3LS4wMWMzLjkyNyAxLjc5MyA4LjE4IDEuNzkzIDEyLjA2IDAgYS4wNzQuMDc0IDAgMDEuMDc4LjAwOS4xMTkuMDk5LjI0Ni4xOTguMzczLjI5MmEuMDc3LjA3NyAwIDAxLS4wMDYuMTI3IDEyLjMgMTIuMyAwIDAxLTEuODczLjg5Mi4wNzcuMDc3IDAgMDAtLjA0MS4xMDdjMy43NDQgMS40MDMgMS4xNTUgMi4xLS4wODQuMDI4YS4wNzguMDc4IDAgMDAxOS45MDItMS45MDMuMDc2LjA3NiAwIDAwLjAzLS4wNTdjLjUzNy00LjU4LS45MDQtOC41NTMtMy44MjMtMTIuMDU3YS4wNi4wNiAwIDAwLS4wMzEtLjAyOHpNOC4wMiAxNS4yNzhjLTEuMTgzIDAtMi4xNTctMS4wODUtMi4xNTctMi40MiAwLTEuMzMzLjk1Ni0yLjQxOSAyLjE1Ny0yLjQxOSAxLjIxIDAgMi4xNzYgMS4wOTYgMi4xNTcgMi40MiAwIDEuMzM0LS45NTYgMi40MTktMi4xNTcgMi40MTl6bTcuOTc1IDBjLTEuMTgzIDAtMi4xNTctMS4wODUtMi4xNTctMi40MiAwLTEuMzMzLjk1NS0yLjQxOSAyLjE1Ny0yLjQxOXMyLjE1NyAxLjA5NiAyLjE1NyAyLjQyYzAgMS4zMzQtLjk1NiAyLjQxOS0yLjE1NyAyLjQxOXoiLz48L3N2Zz4=';

        add_menu_page(
            __('Discord Bot - JLG', 'discord-bot-jlg'),
            __('Discord Bot', 'discord-bot-jlg'),
            'manage_options',
            'discord-bot-jlg',
            array($this, 'options_page'),
            $discord_icon,
            30
        );

        add_submenu_page(
            'discord-bot-jlg',
            __('Configuration', 'discord-bot-jlg'),
            __('Configuration', 'discord-bot-jlg'),
            'manage_options',
            'discord-bot-jlg',
            array($this, 'options_page')
        );

        $this->demo_page_hook_suffix = add_submenu_page(
            'discord-bot-jlg',
            __('Guide & Démo', 'discord-bot-jlg'),
            __('Guide & Démo', 'discord-bot-jlg'),
            'manage_options',
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
            'default_theme',
            __('Thème par défaut', 'discord-bot-jlg'),
            array($this, 'default_theme_render'),
            'discord_stats_settings',
            'discord_stats_display_section'
        );

        add_settings_field(
            'default_refresh_enabled',
            __('Rafraîchissement auto par défaut', 'discord-bot-jlg'),
            array($this, 'default_refresh_enabled_render'),
            'discord_stats_settings',
            'discord_stats_display_section'
        );

        add_settings_field(
            'default_refresh_interval',
            __('Intervalle d\'auto-rafraîchissement (secondes)', 'discord-bot-jlg'),
            array($this, 'default_refresh_interval_render'),
            'discord_stats_settings',
            'discord_stats_display_section'
        );

        add_settings_field(
            'widget_title',
            __('Titre du widget', 'discord-bot-jlg'),
            array($this, 'widget_title_render'),
            'discord_stats_settings',
            'discord_stats_display_section'
        );

        add_settings_field(
            'cache_duration',
            __('Durée du cache (secondes)', 'discord-bot-jlg'),
            array($this, 'cache_duration_render'),
            'discord_stats_settings',
            'discord_stats_display_section'
        );

        add_settings_field(
            'custom_css',
            __('CSS personnalisé', 'discord-bot-jlg'),
            array($this, 'custom_css_render'),
            'discord_stats_settings',
            'discord_stats_display_section'
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

        $sanitized = array(
            'server_id'      => '',
            'bot_token'      => isset($current_options['bot_token']) ? $current_options['bot_token'] : '',
            'demo_mode'      => 0,
            'show_online'    => 0,
            'show_total'     => 0,
            'show_server_name'   => 0,
            'show_server_avatar' => 0,
            'default_refresh_enabled' => 0,
            'default_theme'   => $current_theme,
            'widget_title'   => '',
            'cache_duration' => isset($current_options['cache_duration'])
                ? (int) $current_options['cache_duration']
                : 300,
            'custom_css'     => '',
            'default_refresh_interval' => $current_refresh_interval,
        );

        if (isset($input['server_id'])) {
            $server_id = sanitize_text_field($input['server_id']);

            if ('' === $server_id) {
                $sanitized['server_id'] = '';
            } elseif (preg_match('/^\d+$/', $server_id)) {
                $sanitized['server_id'] = $server_id;
            }
        }

        $constant_overridden = (defined('DISCORD_BOT_JLG_TOKEN') && '' !== DISCORD_BOT_JLG_TOKEN);

        if (!$constant_overridden) {
            $delete_requested = !empty($input['bot_token_delete']);

            if ($delete_requested) {
                $sanitized['bot_token'] = '';
            } elseif (array_key_exists('bot_token', $input)) {
                $raw_token = trim((string) $input['bot_token']);

                if ('' !== $raw_token) {
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
                    }
                }
            }

            if (
                '' !== $sanitized['bot_token']
                && !discord_bot_jlg_is_encrypted_secret($sanitized['bot_token'])
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
                }
            }
        }

        $sanitized['demo_mode']              = !empty($input['demo_mode']) ? 1 : 0;
        $sanitized['show_online']            = !empty($input['show_online']) ? 1 : 0;
        $sanitized['show_total']             = !empty($input['show_total']) ? 1 : 0;
        $sanitized['show_server_name']       = !empty($input['show_server_name']) ? 1 : 0;
        $sanitized['show_server_avatar']     = !empty($input['show_server_avatar']) ? 1 : 0;
        $sanitized['default_refresh_enabled'] = !empty($input['default_refresh_enabled']) ? 1 : 0;

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

        if (array_key_exists('cache_duration', $input)) {
            $raw_cache_duration = is_string($input['cache_duration'])
                ? trim($input['cache_duration'])
                : $input['cache_duration'];

            if ('' === $raw_cache_duration) {
                $fallback_duration          = isset($current_options['cache_duration'])
                    ? (int) $current_options['cache_duration']
                    : 300;
                $sanitized['cache_duration'] = max(
                    Discord_Bot_JLG_API::MIN_PUBLIC_REFRESH_INTERVAL,
                    min(3600, $fallback_duration)
                );
            } else {
                $cache_duration              = absint($raw_cache_duration);
                $sanitized['cache_duration'] = max(
                    Discord_Bot_JLG_API::MIN_PUBLIC_REFRESH_INTERVAL,
                    min(3600, $cache_duration)
                );
            }
        }

        if (isset($input['custom_css'])) {
            $sanitized['custom_css'] = discord_bot_jlg_sanitize_custom_css($input['custom_css']);
        }

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

    /**
     * Affiche la section d'aide dédiée à la configuration de l'API Discord.
     *
     * @return void
     */
    public function api_section_callback() {
        ?>
        <div style="background: #f0f4ff; padding: 20px; border-radius: 8px; margin: 20px 0;">
            <h3 style="margin-top: 0;"><?php esc_html_e('📚 Guide étape par étape', 'discord-bot-jlg'); ?></h3>
            <?php
            $this->render_api_steps();
            $this->render_api_previews();
            ?>
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
                            'Rendez-vous sur <a href="%1$s" target="_blank" rel="noopener noreferrer" style="color: #5865F2;">Discord Developer Portal</a>',
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
        <div style="background: #fff3cd; padding: 10px; border-radius: 4px; margin-top: 15px;">
            <?php echo wp_kses_post(__('<strong>💡 Conseil :</strong> Après avoir rempli les champs ci-dessous, utilisez le bouton "Tester la connexion" pour vérifier que tout fonctionne !', 'discord-bot-jlg')); ?>
            <?php
            $this->render_preview_block(
                __('Avec logo Discord officiel :', 'discord-bot-jlg'),
                '[discord_stats demo="true" show_discord_icon="true" discord_icon_position="left"]',
                array(
                    'container_style' => 'margin: 20px 0;',
                )
            );

            $this->render_preview_block(
                __('Logo Discord centré en haut :', 'discord-bot-jlg'),
                '[discord_stats demo="true" show_discord_icon="true" discord_icon_position="top" align="center" theme="dark"]',
                array(
                    'container_style' => 'margin: 20px 0;',
                )
            );
            ?>
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
               class="regular-text" />
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
            'discord' => __('Discord', 'discord-bot-jlg'),
            'dark'    => __('Sombre', 'discord-bot-jlg'),
            'light'   => __('Clair', 'discord-bot-jlg'),
            'minimal' => __('Minimal', 'discord-bot-jlg'),
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
     * Rend le champ numérique dédié au réglage de la durée du cache.
     *
     * @return void
     */
    public function cache_duration_render() {
        $options = get_option($this->option_name);
        ?>
        <input type="number" name="<?php echo esc_attr($this->option_name); ?>[cache_duration]"
               value="<?php echo esc_attr(isset($options['cache_duration']) ? $options['cache_duration'] : ''); ?>"
               min="<?php echo esc_attr(Discord_Bot_JLG_API::MIN_PUBLIC_REFRESH_INTERVAL); ?>" max="3600" class="small-text" />
        <p class="description">
            <?php
            printf(
                /* translators: 1: minimum cache duration in seconds, 2: maximum cache duration in seconds. */
                esc_html__('Minimum %1$s secondes, maximum %2$s secondes (1 heure)', 'discord-bot-jlg'),
                esc_html(Discord_Bot_JLG_API::MIN_PUBLIC_REFRESH_INTERVAL),
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

    /**
     * Affiche la page principale de configuration du plugin.
     *
     * @return void
     */
    public function options_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('🎮 Discord Bot - JLG - Configuration', 'discord-bot-jlg'); ?></h1>
            <?php settings_errors('discord_stats_settings'); ?>
            <?php $this->handle_test_connection_request(); ?>

            <div class="discord-bot-settings-layout">
                <?php
                $this->render_options_form();
                $this->render_options_sidebar();
                ?>
            </div>

            <?php $this->render_admin_footer_note(); ?>
        </div>
        <?php
    }

    /**
     * Traite la demande de test de connexion depuis la page d'options.
     */
    private function handle_test_connection_request() {
        if (!isset($_POST['test_connection']) || !check_admin_referer('discord_test_connection')) {
            return;
        }

        if (!current_user_can('manage_options')) {
            add_settings_error(
                'discord_stats_settings',
                'discord_bot_jlg_access_denied',
                esc_html__('Accès refusé : vous n\'avez pas les droits suffisants pour tester la connexion Discord.', 'discord-bot-jlg'),
                'error'
            );

            return;
        }

        $this->test_discord_connection();
    }

    /**
     * Affiche le formulaire principal des réglages.
     */
    private function render_options_form() {
        ?>
        <div class="discord-bot-settings-main">
            <form action="options.php" method="post">
                <?php
                settings_fields('discord_stats_settings');
                do_settings_sections('discord_stats_settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Affiche la colonne latérale avec les actions rapides.
     */
    private function render_options_sidebar() {
        ?>
        <div class="discord-bot-settings-sidebar">
            <?php
            $this->render_connection_test_panel();
            $this->render_quick_links_panel();
            ?>
        </div>
        <?php
    }

    /**
     * Affiche le panneau de test de connexion.
     */
    private function render_connection_test_panel() {
        ?>
        <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
            <h3 style="margin-top: 0;"><?php esc_html_e('🔧 Test de connexion', 'discord-bot-jlg'); ?></h3>
            <p><?php esc_html_e('Vérifiez que votre configuration fonctionne :', 'discord-bot-jlg'); ?></p>
            <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=discord-bot-jlg')); ?>">
                <input type="hidden" name="test_connection" value="1" />
                <?php wp_nonce_field('discord_test_connection'); ?>
                <p>
                    <button type="submit" class="button button-secondary" style="width: 100%;"><?php esc_html_e('Tester la connexion', 'discord-bot-jlg'); ?></button>
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * Affiche la liste des liens rapides utiles.
     */
    private function render_quick_links_panel() {
        ?>
        <div style="background: #e8f5e9; padding: 20px; border-radius: 8px;">
            <h3 style="margin-top: 0;"><?php esc_html_e('🚀 Liens rapides', 'discord-bot-jlg'); ?></h3>
            <ul style="list-style: none; padding: 0;">
                <li style="margin-bottom: 10px;">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=discord-bot-demo')); ?>" class="button button-primary" style="width: 100%;">
                        <?php esc_html_e('📖 Guide & Démo', 'discord-bot-jlg'); ?>
                    </a>
                </li>
                <li style="margin-bottom: 10px;">
                    <a href="https://discord.com/developers/applications" target="_blank" rel="noopener noreferrer" class="button" style="width: 100%;">
                        <?php esc_html_e('🔗 Discord Developer Portal', 'discord-bot-jlg'); ?>
                    </a>
                </li>
                <li>
                    <a href="<?php echo esc_url(admin_url('widgets.php')); ?>" class="button" style="width: 100%;">
                        <?php esc_html_e('📐 Gérer les Widgets', 'discord-bot-jlg'); ?>
                    </a>
                </li>
            </ul>
        </div>
        <?php
    }

    /**
     * Affiche le pied de page de la page d'options.
     */
    private function render_admin_footer_note() {
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
        <div style="background: #fff3cd; padding: 10px 20px; border-radius: 8px; margin: 20px 0;">
            <p><?php echo wp_kses_post(__('<strong>💡 Astuce :</strong> Tous les exemples ci-dessous utilisent le mode démo. Vous pouvez les copier-coller directement !', 'discord-bot-jlg')); ?></p>
        </div>
        <?php
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

            <h4><?php esc_html_e('Tous les paramètres disponibles :', 'discord-bot-jlg'); ?></h4>
            <div style="background: white; padding: 15px; border-radius: 4px;">
                <h5><?php esc_html_e('🎨 Apparence & Layout :', 'discord-bot-jlg'); ?></h5>
                <ul style="columns: 2; column-gap: 30px;">
                    <li><?php echo wp_kses_post(__('<strong>layout</strong> : horizontal, vertical, compact', 'discord-bot-jlg')); ?></li>
                    <li><?php echo wp_kses_post(__('<strong>theme</strong> : discord, dark, light, minimal', 'discord-bot-jlg')); ?></li>
                    <li><?php echo wp_kses_post(__('<strong>align</strong> : left, center, right', 'discord-bot-jlg')); ?></li>
                    <li><?php echo wp_kses_post(__('<strong>width</strong> : largeur CSS (ex: "300px", "100%")', 'discord-bot-jlg')); ?></li>
                    <li><?php echo wp_kses_post(__('<strong>compact</strong> : true/false (version réduite)', 'discord-bot-jlg')); ?></li>
                    <li><?php echo wp_kses_post(__('<strong>animated</strong> : true/false (animations hover)', 'discord-bot-jlg')); ?></li>
                    <li><?php echo wp_kses_post(__('<strong>class</strong> : classes CSS additionnelles', 'discord-bot-jlg')); ?></li>
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

        wp_enqueue_style(
            'discord-bot-jlg-admin',
            DISCORD_BOT_JLG_PLUGIN_URL . 'assets/css/discord-bot-jlg-admin.css',
            array(),
            DISCORD_BOT_JLG_VERSION
        );
    }

    /**
     * Teste la connexion à l'API Discord et affiche un message selon le résultat obtenu.
     *
     * @return void
     */
    public function test_discord_connection() {
        $options = get_option($this->option_name);
        if (!is_array($options)) {
            $options = array();
        }

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

        if (!empty($options['demo_mode'])) {
            printf(
                '<div class="notice notice-info"><p>%s</p></div>',
                esc_html__('🎨 Mode démonstration activé - Les données affichées sont fictives', 'discord-bot-jlg')
            );
            return;
        }

        $stats = $this->api->get_stats(
            array(
                'bypass_cache' => true,
            )
        );
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

            printf(
                '<div class="notice notice-success"><p>%s</p></div>',
                sprintf(
                    /* translators: 1: server name, 2: online members count, 3: total members count. */
                    esc_html__('✅ Connexion réussie ! Serveur : %1$s - %2$s en ligne / %3$s membres', 'discord-bot-jlg'),
                    esc_html($server_name),
                    esc_html(number_format_i18n($online_count)),
                    $total_display
                )
            );
        } elseif (is_array($stats) && !empty($stats['is_demo'])) {
            printf(
                '<div class="notice notice-warning"><p>%s%s</p></div>',
                esc_html__('⚠️ Pas de configuration Discord détectée. Mode démo actif.', 'discord-bot-jlg'),
                $diagnostic_suffix
            );
        } else {
            printf(
                '<div class="notice notice-error"><p>%s%s</p></div>',
                esc_html__('❌ Échec de la connexion. Vérifiez vos identifiants.', 'discord-bot-jlg'),
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
        $container_style     = isset($options['container_style']) ? $options['container_style'] : '';
        $inner_wrapper_style = isset($options['inner_wrapper_style']) ? $options['inner_wrapper_style'] : '';
        ?>
        <div<?php if ($container_style) { echo ' style="' . esc_attr($container_style) . '"'; } ?>>
            <h4><?php echo esc_html($title); ?></h4>
            <?php
            if ($inner_wrapper_style) {
                echo '<div style="' . esc_attr($inner_wrapper_style) . '">';
            }
            echo $this->get_admin_shortcode_preview($shortcode);
            if ($inner_wrapper_style) {
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
