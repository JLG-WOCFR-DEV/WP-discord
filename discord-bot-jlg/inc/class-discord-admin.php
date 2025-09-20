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
    }

    /**
     * Enregistre le menu principal et les sous-menus du plugin dans l'administration WordPress.
     *
     * @return void
     */
    public function add_admin_menu() {
        $discord_icon = 'data:image/svg+xml;base64,PHN2ZyB2aWV3Qm94PSIwIDAgMjQgMjQiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PHBhdGggZmlsbD0iI2E0YWFiOCIgZD0iTTIwLjMxNyA0LjM3YTE5LjggMTkuOCAwIDAwLTQuODg1LTEuNTE1LjA3NC4wNzQgMCAwMC0uMDc5LjAzN2MtLjIxLjM3NS0uNDQ0Ljg2NC0uNjA4IDEuMjVhMTguMjcgMTguMjcgMCAwMC01LjQ4NyAwYy0uMTY1LS4zOTctLjQwNC0uODg1LS42MTgtMS4yNWEuMDc3LjA3NyAwIDAwLS4wNzktLjAzN0ExOS43NCAxOS43NCAwIDAwMy42NzcgNC4zN2EuMDcuMDcgMCAwMC0uMDMyLjAyN0MuNTMzIDkuMDQ2LS4zMiAxMy41OC4wOTkgMTguMDU3YS4wOC4wOCAwIDAwLjAzMS4wNTdBMTkuOSAxOS45IDAgMDA2LjA3MyAyMWEuMDc4LjA3OCAwIDAwLjA4NC0uMDI4IDEzLjQgMTMuNCAwIDAwMS4xNTUtMi4xLjA3Ni4wNzYgMCAwMC0uMDQxLS4xMDYgMTMuMSAxMy4xIDAgMDEtMS44NzItLjg5Mi4wNzcuMDc3IDAgMDEtLjAwOC0uMTI4IDE0IDE0IDAgMDAuMzctLjI5Mi4wNzQuMDc0IDAgMDEuMDc3LS4wMWMzLjkyNyAxLjc5MyA4LjE4IDEuNzkzIDEyLjA2IDAgYS4wNzQuMDc0IDAgMDEuMDc4LjAwOS4xMTkuMDk5LjI0Ni4xOTguMzczLjI5MmEuMDc3LjA3NyAwIDAxLS4wMDYuMTI3IDEyLjMgMTIuMyAwIDAxLTEuODczLjg5Mi4wNzcuMDc3IDAgMDAtLjA0MS4xMDdjMy43NDQgMS40MDMgMS4xNTUgMi4xLS4wODQuMDI4YS4wNzguMDc4IDAgMDAxOS45MDItMS45MDMuMDc2LjA3NiAwIDAwLjAzLS4wNTdjLjUzNy00LjU4LS45MDQtOC41NTMtMy44MjMtMTIuMDU3YS4wNi4wNiAwIDAwLS4wMzEtLjAyOHpNOC4wMiAxNS4yNzhjLTEuMTgzIDAtMi4xNTctMS4wODUtMi4xNTctMi40MiAwLTEuMzMzLjk1Ni0yLjQxOSAyLjE1Ny0yLjQxOSAxLjIxIDAgMi4xNzYgMS4wOTYgMi4xNTcgMi40MiAwIDEuMzM0LS45NTYgMi40MTktMi4xNTcgMi40MTl6bTcuOTc1IDBjLTEuMTgzIDAtMi4xNTctMS4wODUtMi4xNTctMi40MiAwLTEuMzMzLjk1NS0yLjQxOSAyLjE1Ny0yLjQxOXMyLjE1NyAxLjA5NiAyLjE1NyAyLjQyYzAgMS4zMzQtLjk1NiAyLjQxOS0yLjE1NyAyLjQxOXoiLz48L3N2Zz4=';

        add_menu_page(
            'Discord Bot - JLG',
            'Discord Bot',
            'manage_options',
            'discord-bot-jlg',
            array($this, 'options_page'),
            $discord_icon,
            30
        );

        add_submenu_page(
            'discord-bot-jlg',
            'Configuration',
            'Configuration',
            'manage_options',
            'discord-bot-jlg',
            array($this, 'options_page')
        );

        add_submenu_page(
            'discord-bot-jlg',
            'Guide & Démo',
            'Guide & Démo',
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
            'Configuration Discord API',
            array($this, 'api_section_callback'),
            'discord_stats_settings'
        );

        add_settings_field(
            'server_id',
            'ID du Serveur Discord',
            array($this, 'server_id_render'),
            'discord_stats_settings',
            'discord_stats_api_section'
        );

        add_settings_field(
            'bot_token',
            'Token du Bot Discord',
            array($this, 'bot_token_render'),
            'discord_stats_settings',
            'discord_stats_api_section'
        );

        add_settings_section(
            'discord_stats_display_section',
            'Options d\'affichage',
            array($this, 'display_section_callback'),
            'discord_stats_settings'
        );

        add_settings_field(
            'demo_mode',
            'Mode démonstration',
            array($this, 'demo_mode_render'),
            'discord_stats_settings',
            'discord_stats_display_section'
        );

        add_settings_field(
            'show_online',
            'Afficher les membres en ligne',
            array($this, 'show_online_render'),
            'discord_stats_settings',
            'discord_stats_display_section'
        );

        add_settings_field(
            'show_total',
            'Afficher le total des membres',
            array($this, 'show_total_render'),
            'discord_stats_settings',
            'discord_stats_display_section'
        );

        add_settings_field(
            'widget_title',
            'Titre du widget',
            array($this, 'widget_title_render'),
            'discord_stats_settings',
            'discord_stats_display_section'
        );

        add_settings_field(
            'cache_duration',
            'Durée du cache (secondes)',
            array($this, 'cache_duration_render'),
            'discord_stats_settings',
            'discord_stats_display_section'
        );

        add_settings_field(
            'custom_css',
            'CSS personnalisé',
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

        $sanitized = array(
            'server_id'      => '',
            'bot_token'      => '',
            'demo_mode'      => 0,
            'show_online'    => 0,
            'show_total'     => 0,
            'widget_title'   => '',
            'cache_duration' => 300,
            'custom_css'     => '',
        );

        if (isset($input['server_id'])) {
            $server_id = sanitize_text_field($input['server_id']);

            if ('' === $server_id) {
                $sanitized['server_id'] = '';
            } elseif (preg_match('/^\d+$/', $server_id)) {
                $sanitized['server_id'] = $server_id;
            }
        }

        if (isset($input['bot_token'])) {
            $sanitized['bot_token'] = sanitize_text_field($input['bot_token']);
        }

        $sanitized['demo_mode']   = !empty($input['demo_mode']) ? 1 : 0;
        $sanitized['show_online'] = !empty($input['show_online']) ? 1 : 0;
        $sanitized['show_total']  = !empty($input['show_total']) ? 1 : 0;

        if (isset($input['widget_title'])) {
            $sanitized['widget_title'] = sanitize_text_field($input['widget_title']);
        }

        if (isset($input['cache_duration'])) {
            $cache_duration               = absint($input['cache_duration']);
            $sanitized['cache_duration'] = max(60, min(3600, $cache_duration));
        }

        if (isset($input['custom_css'])) {
            $sanitized['custom_css'] = sanitize_textarea_field($input['custom_css']);
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
            <h3 style="margin-top: 0;">📚 Guide étape par étape</h3>
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
        <h4>Étape 1 : Créer un Bot Discord</h4>
        <ol>
            <li>Rendez-vous sur <a href="https://discord.com/developers/applications" target="_blank" style="color: #5865F2;">Discord Developer Portal</a></li>
            <li>Cliquez sur <strong>"New Application"</strong> en haut à droite</li>
            <li>Donnez un nom à votre application (ex: "Stats Bot")</li>
            <li>Dans le menu de gauche, cliquez sur <strong>"Bot"</strong></li>
            <li>Cliquez sur <strong>"Add Bot"</strong></li>
            <li>Sous "Token", cliquez sur <strong>"Copy"</strong> pour copier le token du bot</li>
            <li>⚠️ <strong>Important :</strong> Gardez ce token secret et ne le partagez jamais !</li>
        </ol>

        <h4>Étape 2 : Inviter le Bot sur votre serveur</h4>
        <ol>
            <li>Dans le menu de gauche, allez dans <strong>"OAuth2"</strong> > <strong>"URL Generator"</strong></li>
            <li>Dans "Scopes", cochez <strong>"bot"</strong></li>
            <li>Dans "Bot Permissions", sélectionnez :</li>
                <ul>
                    <li>✅ View Channels</li>
                    <li>✅ Read Messages</li>
                    <li>✅ Send Messages (optionnel)</li>
                </ul>
            <li>Copiez l'URL générée en bas de la page</li>
            <li>Ouvrez cette URL dans votre navigateur</li>
            <li>Sélectionnez votre serveur et cliquez sur <strong>"Autoriser"</strong></li>
        </ol>

        <h4>Étape 3 : Obtenir l'ID de votre serveur</h4>
        <ol>
            <li>Ouvrez Discord (application ou web)</li>
            <li>Allez dans <strong>Paramètres utilisateur</strong> (engrenage en bas)</li>
            <li>Dans <strong>"Avancés"</strong>, activez <strong>"Mode développeur"</strong></li>
            <li>Retournez sur votre serveur</li>
            <li>Faites un <strong>clic droit sur le nom du serveur</strong></li>
            <li>Cliquez sur <strong>"Copier l'ID"</strong></li>
        </ol>

        <h4>Étape 4 : Activer le Widget (optionnel mais recommandé)</h4>
        <ol>
            <li>Dans Discord, allez dans <strong>Paramètres du serveur</strong></li>
            <li>Dans <strong>"Widget"</strong>, activez <strong>"Activer le widget du serveur"</strong></li>
            <li>Cela permet une méthode de fallback si le bot a des problèmes</li>
        </ol>
        <?php
    }

    /**
     * Affiche les prévisualisations rapides du shortcode dans la section API.
     */
    private function render_api_previews() {
        ?>
        <div style="background: #fff3cd; padding: 10px; border-radius: 4px; margin-top: 15px;">
            <strong>💡 Conseil :</strong> Après avoir rempli les champs ci-dessous, utilisez le bouton "Tester la connexion" pour vérifier que tout fonctionne !
            <?php
            $this->render_preview_block(
                'Avec logo Discord officiel :',
                '[discord_stats demo="true" show_discord_icon="true" discord_icon_position="left"]',
                array(
                    'container_style' => 'margin: 20px 0;',
                )
            );

            $this->render_preview_block(
                'Logo Discord centré en haut :',
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
        echo '<p>Personnalisez l\'affichage des statistiques Discord.</p>';
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
        <p class="description">L'ID de votre serveur Discord</p>
        <?php
    }

    /**
     * Rend le champ de saisie du token du bot Discord.
     *
     * @return void
     */
    public function bot_token_render() {
        $options = get_option($this->option_name);
        $constant_overridden = (defined('DISCORD_BOT_JLG_TOKEN') && '' !== DISCORD_BOT_JLG_TOKEN);
        ?>
        <input type="password" name="<?php echo esc_attr($this->option_name); ?>[bot_token]"
               value="<?php echo esc_attr(isset($options['bot_token']) ? $options['bot_token'] : ''); ?>"
               class="regular-text" <?php echo $constant_overridden ? 'readonly' : ''; ?> />
        <p class="description">
            <?php
            if ($constant_overridden) {
                echo 'Le token est actuellement défini via la constante <code>DISCORD_BOT_JLG_TOKEN</code> et remplace cette valeur.';
            } else {
                echo 'Le token de votre bot Discord (gardez-le secret !).';
            }
            ?>
        </p>
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
        <label>Activer le mode démonstration (affiche des données fictives pour tester l'apparence)</label>
        <p class="description">🎨 Parfait pour tester les styles et dispositions sans configuration Discord</p>
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
        <label>Afficher le nombre d'utilisateurs en ligne</label>
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
        <label>Afficher le nombre total de membres</label>
        <?php
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
               min="60" max="3600" class="small-text" />
        <p class="description">Minimum 60 secondes, maximum 3600 secondes (1 heure)</p>
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
        <p class="description">CSS personnalisé pour styliser l'affichage</p>
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
            <h1>🎮 Discord Bot - JLG - Configuration</h1>
            <?php $this->handle_test_connection_request(); ?>

            <div style="display: flex; gap: 20px; margin-top: 20px;">
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
        if (isset($_GET['test_connection']) && check_admin_referer('discord_test_connection')) {
            $this->test_discord_connection();
        }
    }

    /**
     * Affiche le formulaire principal des réglages.
     */
    private function render_options_form() {
        ?>
        <div style="flex: 1;">
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
        <div style="width: 300px;">
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
            <h3 style="margin-top: 0;">🔧 Test de connexion</h3>
            <p>Vérifiez que votre configuration fonctionne :</p>
            <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>">
                <input type="hidden" name="page" value="discord-bot-jlg" />
                <input type="hidden" name="test_connection" value="1" />
                <?php wp_nonce_field('discord_test_connection'); ?>
                <p>
                    <button type="submit" class="button button-secondary" style="width: 100%;">Tester la connexion</button>
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
            <h3 style="margin-top: 0;">🚀 Liens rapides</h3>
            <ul style="list-style: none; padding: 0;">
                <li style="margin-bottom: 10px;">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=discord-bot-demo')); ?>" class="button button-primary" style="width: 100%;">
                        📖 Guide & Démo
                    </a>
                </li>
                <li style="margin-bottom: 10px;">
                    <a href="https://discord.com/developers/applications" target="_blank" class="button" style="width: 100%;">
                        🔗 Discord Developer Portal
                    </a>
                </li>
                <li>
                    <a href="<?php echo esc_url(admin_url('widgets.php')); ?>" class="button" style="width: 100%;">
                        📐 Gérer les Widgets
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
                printf(
                    '%s | Développé par Jérôme Le Gousse',
                    esc_html(sprintf('Discord Bot - JLG v%s', DISCORD_BOT_JLG_VERSION))
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
            <h1>📖 Guide & Démonstration</h1>
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
            <p><strong>💡 Astuce :</strong> Tous les exemples ci-dessous utilisent le mode démo. Vous pouvez les copier-coller directement !</p>
        </div>
        <?php
    }

    /**
     * Affiche les prévisualisations du shortcode en mode démo.
     */
    private function render_demo_previews() {
        $previews = array(
            array(
                'title' => 'Standard horizontal :',
                'shortcode' => '[discord_stats demo="true"]',
            ),
            array(
                'title' => 'Vertical pour sidebar :',
                'shortcode' => '[discord_stats demo="true" layout="vertical" theme="minimal"]',
                'inner_wrapper_style' => 'max-width: 300px;',
            ),
            array(
                'title' => 'Compact mode sombre :',
                'shortcode' => '[discord_stats demo="true" compact="true" theme="dark"]',
            ),
            array(
                'title' => 'Avec titre personnalisé :',
                'shortcode' => '[discord_stats demo="true" show_title="true" title="🎮 Notre Communauté Gaming" align="center"]',
            ),
            array(
                'title' => 'Icônes personnalisées :',
                'shortcode' => '[discord_stats demo="true" icon_online="🔥" label_online="Actifs" icon_total="⚔️" label_total="Guerriers"]',
            ),
            array(
                'title' => 'Minimaliste (nombres uniquement) :',
                'shortcode' => '[discord_stats demo="true" hide_labels="true" hide_icons="true" theme="minimal"]',
            ),
        );
        ?>
        <div style="background: #e3f2fd; padding: 20px; border-radius: 8px;">
            <h2>🎨 Prévisualisation en direct</h2>
            <p>Testez différentes configurations visuelles :</p>
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
            <h2>📖 Guide d'utilisation</h2>

            <h3>Option 1 : Shortcode (avec paramètres)</h3>
            <p>Copiez ce code dans n'importe quelle page ou article :</p>
            <code style="background: white; padding: 10px; display: inline-block; border-radius: 4px;">[discord_stats]</code>

            <h4>Exemples avec paramètres :</h4>
            <pre style="background: white; padding: 15px; border-radius: 4px; overflow-x: auto;">// BASIQUES
// Layout vertical pour sidebar
[discord_stats layout="vertical"]

// Compact avec titre
[discord_stats compact="true" show_title="true" title="Rejoignez-nous !"]

// Theme sombre centré
[discord_stats theme="dark" align="center"]

// AVEC LOGO DISCORD
// Logo à gauche (classique)
[discord_stats show_discord_icon="true"]

// Logo à droite avec thème sombre
[discord_stats show_discord_icon="true" discord_icon_position="right" theme="dark"]

// Logo centré en haut (parfait pour widgets)
[discord_stats show_discord_icon="true" discord_icon_position="top" align="center"]

// PERSONNALISATION AVANCÉE
// Bannière complète pour header
[discord_stats show_discord_icon="true" show_title="true" title="🎮 Rejoignez notre Discord !" width="100%" align="center" theme="discord"]

// Sidebar élégante avec logo
[discord_stats layout="vertical" show_discord_icon="true" discord_icon_position="top" theme="minimal" compact="true"]

// Gaming style avec icônes custom
[discord_stats show_discord_icon="true" icon_online="🎮" label_online="Joueurs actifs" icon_total="⚔️" label_total="Guerriers" theme="dark"]

// Minimaliste avec logo seul
[discord_stats hide_labels="true" hide_icons="true" show_discord_icon="true" discord_icon_position="top" align="center" theme="minimal"]

// Footer discret
[discord_stats compact="true" show_discord_icon="true" discord_icon_position="left" theme="light"]

// FONCTIONNALITÉS SPÉCIALES
// Auto-refresh toutes les 30 secondes (minimum 10 secondes)
[discord_stats refresh="true" refresh_interval="30" show_discord_icon="true"]

// Afficher seulement les membres en ligne avec logo
[discord_stats show_online="true" show_total="false" show_discord_icon="true"]

// MODE DÉMO (pour tester l'apparence)
[discord_stats demo="true" show_discord_icon="true" theme="dark" layout="vertical"]</pre>

            <p style="margin-top: 10px;"><em>ℹ️ L'auto-refresh nécessite un intervalle d'au moins 10&nbsp;secondes (10 000&nbsp;ms). Toute valeur inférieure est automatiquement ajustée pour éviter les erreurs 429.</em></p>

            <h4>Tous les paramètres disponibles :</h4>
            <div style="background: white; padding: 15px; border-radius: 4px;">
                <h5>🎨 Apparence & Layout :</h5>
                <ul style="columns: 2; column-gap: 30px;">
                    <li><strong>layout</strong> : horizontal, vertical, compact</li>
                    <li><strong>theme</strong> : discord, dark, light, minimal</li>
                    <li><strong>align</strong> : left, center, right</li>
                    <li><strong>width</strong> : largeur CSS (ex: "300px", "100%")</li>
                    <li><strong>compact</strong> : true/false (version réduite)</li>
                    <li><strong>animated</strong> : true/false (animations hover)</li>
                    <li><strong>class</strong> : classes CSS additionnelles</li>
                </ul>

                <h5>🎯 Logo Discord :</h5>
                <ul>
                    <li><strong>show_discord_icon</strong> : true/false (afficher le logo officiel)</li>
                    <li><strong>discord_icon_position</strong> : left, right, top (position du logo)</li>
                </ul>

                <h5>📊 Données affichées :</h5>
                <ul style="columns: 2; column-gap: 30px;">
                    <li><strong>show_online</strong> : true/false</li>
                    <li><strong>show_total</strong> : true/false</li>
                    <li><strong>show_title</strong> : true/false</li>
                    <li><strong>title</strong> : texte du titre</li>
                    <li><strong>hide_labels</strong> : true/false</li>
                    <li><strong>hide_icons</strong> : true/false</li>
                </ul>

                <h5>✏️ Personnalisation textes/icônes :</h5>
                <ul style="columns: 2; column-gap: 30px;">
                    <li><strong>icon_online</strong> : emoji/texte (défaut: 🟢)</li>
                    <li><strong>icon_total</strong> : emoji/texte (défaut: 👥)</li>
                    <li><strong>label_online</strong> : texte personnalisé</li>
                    <li><strong>label_total</strong> : texte personnalisé</li>
                </ul>

                <h5>⚙️ Paramètres techniques :</h5>
                <ul style="columns: 2; column-gap: 30px;">
                    <li><strong>refresh</strong> : true/false (auto-actualisation)</li>
                    <li><strong>refresh_interval</strong> : secondes (minimum 10&nbsp;secondes / 10 000&nbsp;ms)</li>
                    <li><strong>demo</strong> : true/false (mode démonstration)</li>
                    <li><strong>border_radius</strong> : pixels (coins arrondis)</li>
                    <li><strong>gap</strong> : pixels (espace entre éléments)</li>
                    <li><strong>padding</strong> : pixels (espacement interne)</li>
                </ul>
            </div>

            <h3>Option 2 : Widget</h3>
            <p>Allez dans <strong>Apparence > Widgets</strong> et ajoutez le widget <strong>"Discord Bot - JLG"</strong> dans votre sidebar</p>

            <h3>Option 3 : Code PHP</h3>
            <p>Pour les développeurs, dans vos templates PHP :</p>
            <code style="background: white; padding: 10px; display: block; border-radius: 4px;">
                &lt;?php echo do_shortcode('[discord_stats show_discord_icon="true"]'); ?&gt;
            </code>

            <h3>💡 Configurations recommandées</h3>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 15px;">
                <div style="background: white; padding: 15px; border-radius: 4px;">
                    <strong>Pour une sidebar :</strong><br>
                    <code style="font-size: 12px;">[discord_stats layout="vertical" show_discord_icon="true" discord_icon_position="top" compact="true"]</code>
                </div>
                <div style="background: white; padding: 15px; border-radius: 4px;">
                    <strong>Pour un header :</strong><br>
                    <code style="font-size: 12px;">[discord_stats show_discord_icon="true" show_title="true" title="Join us!" align="center" width="100%"]</code>
                </div>
                <div style="background: white; padding: 15px; border-radius: 4px;">
                    <strong>Pour un footer :</strong><br>
                    <code style="font-size: 12px;">[discord_stats theme="dark" show_discord_icon="true" compact="true"]</code>
                </div>
                <div style="background: white; padding: 15px; border-radius: 4px;">
                    <strong>Style gaming :</strong><br>
                    <code style="font-size: 12px;">[discord_stats theme="dark" icon_online="🎮" label_online="Players" show_discord_icon="true"]</code>
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
            <h2>❓ Dépannage</h2>
            <ul>
                <li><strong>Erreur de connexion ?</strong> Vérifiez que le bot est bien sur votre serveur</li>
                <li><strong>Stats à 0 ?</strong> Assurez-vous que le widget est activé dans les paramètres Discord</li>
                <li><strong>Token invalide ?</strong> Régénérez le token dans le Developer Portal</li>
                <li><strong>Cache ?</strong> Les stats sont mises à jour toutes les 5 minutes par défaut</li>
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
                printf(
                    '%s | Développé par Jérôme Le Gousse |',
                    esc_html(sprintf('Discord Bot - JLG v%s', DISCORD_BOT_JLG_VERSION))
                );
                ?>
               <a href="https://discord.com/developers/docs/intro" target="_blank">Documentation Discord API</a> |
               <a href="#" onclick="return false;">Besoin d'aide ?</a>
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
            'discord-bot_page_discord-bot-demo',
        );

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

        if (!empty($options['demo_mode'])) {
            echo '<div class="notice notice-info"><p>🎨 Mode démonstration activé - Les données affichées sont fictives</p></div>';
            return;
        }

        $this->api->clear_cache();
        $stats = $this->api->get_stats(
            array(
                'bypass_cache' => true,
            )
        );

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
                '<div class="notice notice-success"><p>✅ Connexion réussie ! Serveur : %1$s - %2$s en ligne / %3$s membres</p></div>',
                esc_html($server_name),
                esc_html(number_format_i18n($online_count)),
                $total_display
            );
        } elseif (is_array($stats) && !empty($stats['is_demo'])) {
            echo '<div class="notice notice-warning"><p>⚠️ Pas de configuration Discord détectée. Mode démo actif.</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>❌ Échec de la connexion. Vérifiez vos identifiants.</p></div>';
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
                'style'       => true,
                'data-demo'   => true,
                'data-refresh'=> true,
                'data-value'  => true,
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
            'class'      => true,
            'viewbox'    => true,
            'xmlns'      => true,
            'role'       => true,
            'aria-hidden'=> true,
        );

        $allowed_tags['path'] = array(
            'd'    => true,
            'fill' => true,
        );

        return $allowed_tags;
    }
}
