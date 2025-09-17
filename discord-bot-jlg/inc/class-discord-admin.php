<?php

if (!defined('ABSPATH')) {
    exit;
}

class Discord_Bot_JLG_Admin {

    private $option_name;
    private $api;

    public function __construct($option_name, Discord_Bot_JLG_API $api) {
        $this->option_name = $option_name;
        $this->api         = $api;
    }

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

        add_submenu_page(
            'discord-bot-jlg',
            __('Guide & Démo', 'discord-bot-jlg'),
            __('Guide & Démo', 'discord-bot-jlg'),
            'manage_options',
            'discord-bot-demo',
            array($this, 'demo_page')
        );
    }

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
            __('Options d\'affichage', 'discord-bot-jlg'),
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

    public function api_section_callback() {
        ?>
        <div style="background: #f0f4ff; padding: 20px; border-radius: 8px; margin: 20px 0;">
            <h3 style="margin-top: 0;"><?php echo esc_html__('📚 Guide étape par étape', 'discord-bot-jlg'); ?></h3>
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
        $developer_portal_url = 'https://discord.com/developers/applications';
        ?>
        <h4><?php esc_html_e('Étape 1 : Créer un Bot Discord', 'discord-bot-jlg'); ?></h4>
        <ol>
            <li>
                <?php
                printf(
                    wp_kses(
                        __('Rendez-vous sur <a href="%1$s" target="_blank" style="color: #5865F2;">Discord Developer Portal</a>', 'discord-bot-jlg'),
                        array(
                            'a' => array(
                                'href'   => array(),
                                'style'  => array(),
                                'target' => array(),
                            ),
                        )
                    ),
                    esc_url($developer_portal_url)
                );
                ?>
            </li>
            <li><?php esc_html_e('Cliquez sur "New Application" en haut à droite', 'discord-bot-jlg'); ?></li>
            <li><?php esc_html_e('Donnez un nom à votre application (ex: "Stats Bot")', 'discord-bot-jlg'); ?></li>
            <li><?php echo wp_kses(__('Dans le menu de gauche, cliquez sur <strong>"Bot"</strong>', 'discord-bot-jlg'), array('strong' => array())); ?></li>
            <li><?php echo wp_kses(__('Cliquez sur <strong>"Add Bot"</strong>', 'discord-bot-jlg'), array('strong' => array())); ?></li>
            <li><?php echo wp_kses(__('Sous "Token", cliquez sur <strong>"Copy"</strong> pour copier le token du bot', 'discord-bot-jlg'), array('strong' => array())); ?></li>
            <li><?php echo wp_kses(__('⚠️ <strong>Important :</strong> Gardez ce token secret et ne le partagez jamais !', 'discord-bot-jlg'), array('strong' => array())); ?></li>
        </ol>

        <h4><?php esc_html_e('Étape 2 : Inviter le Bot sur votre serveur', 'discord-bot-jlg'); ?></h4>
        <ol>
            <li><?php echo wp_kses(__('Dans le menu de gauche, allez dans <strong>"OAuth2"</strong> &gt; <strong>"URL Generator"</strong>', 'discord-bot-jlg'), array('strong' => array())); ?></li>
            <li><?php echo wp_kses(__('Dans "Scopes", cochez <strong>"bot"</strong>', 'discord-bot-jlg'), array('strong' => array())); ?></li>
            <li><?php echo wp_kses(__('Dans "Bot Permissions", sélectionnez :', 'discord-bot-jlg'), array()); ?></li>
                <ul>
                    <li><?php esc_html_e('✅ View Channels', 'discord-bot-jlg'); ?></li>
                    <li><?php esc_html_e('✅ Read Messages', 'discord-bot-jlg'); ?></li>
                    <li><?php esc_html_e('✅ Send Messages (optionnel)', 'discord-bot-jlg'); ?></li>
                </ul>
            <li><?php esc_html_e("Copiez l'URL générée en bas de la page", 'discord-bot-jlg'); ?></li>
            <li><?php esc_html_e('Ouvrez cette URL dans votre navigateur', 'discord-bot-jlg'); ?></li>
            <li><?php echo wp_kses(__('Sélectionnez votre serveur et cliquez sur <strong>"Autoriser"</strong>', 'discord-bot-jlg'), array('strong' => array())); ?></li>
        </ol>

        <h4><?php esc_html_e("Étape 3 : Obtenir l'ID de votre serveur", 'discord-bot-jlg'); ?></h4>
        <ol>
            <li><?php esc_html_e('Ouvrez Discord (application ou web)', 'discord-bot-jlg'); ?></li>
            <li><?php echo wp_kses(__('Allez dans <strong>Paramètres utilisateur</strong> (engrenage en bas)', 'discord-bot-jlg'), array('strong' => array())); ?></li>
            <li><?php echo wp_kses(__('Dans <strong>"Avancés"</strong>, activez <strong>"Mode développeur"</strong>', 'discord-bot-jlg'), array('strong' => array())); ?></li>
            <li><?php esc_html_e('Retournez sur votre serveur', 'discord-bot-jlg'); ?></li>
            <li><?php echo wp_kses(__('Faites un <strong>clic droit sur le nom du serveur</strong>', 'discord-bot-jlg'), array('strong' => array())); ?></li>
            <li><?php echo wp_kses(__('Cliquez sur <strong>"Copier l\'ID"</strong>', 'discord-bot-jlg'), array('strong' => array())); ?></li>
        </ol>

        <h4><?php esc_html_e('Étape 4 : Activer le Widget (optionnel mais recommandé)', 'discord-bot-jlg'); ?></h4>
        <ol>
            <li><?php echo wp_kses(__('Dans Discord, allez dans <strong>Paramètres du serveur</strong>', 'discord-bot-jlg'), array('strong' => array())); ?></li>
            <li><?php echo wp_kses(__('Dans <strong>"Widget"</strong>, activez <strong>"Activer le widget du serveur"</strong>', 'discord-bot-jlg'), array('strong' => array())); ?></li>
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
            <?php
            echo wp_kses(
                __('<strong>💡 Conseil :</strong> Après avoir rempli les champs ci-dessous, utilisez le bouton "Tester la connexion" pour vérifier que tout fonctionne !', 'discord-bot-jlg'),
                array('strong' => array())
            );
            ?>
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

    public function display_section_callback() {
        printf(
            '<p>%s</p>',
            esc_html__("Personnalisez l'affichage des statistiques Discord.", 'discord-bot-jlg')
        );
    }

    public function server_id_render() {
        $options = get_option($this->option_name);
        ?>
        <input type="text" name="<?php echo esc_attr($this->option_name); ?>[server_id]"
               value="<?php echo esc_attr(isset($options['server_id']) ? $options['server_id'] : ''); ?>"
               class="regular-text" />
        <p class="description"><?php echo esc_html__("L'ID de votre serveur Discord", 'discord-bot-jlg'); ?></p>
        <?php
    }

    public function bot_token_render() {
        $options = get_option($this->option_name);
        ?>
        <input type="password" name="<?php echo esc_attr($this->option_name); ?>[bot_token]"
               value="<?php echo esc_attr(isset($options['bot_token']) ? $options['bot_token'] : ''); ?>"
               class="regular-text" />
        <p class="description"><?php echo esc_html__("Le token de votre bot Discord (gardez-le secret !)", 'discord-bot-jlg'); ?></p>
        <?php
    }

    public function demo_mode_render() {
        $options   = get_option($this->option_name);
        $demo_mode = isset($options['demo_mode']) ? (int) $options['demo_mode'] : 0;
        ?>
        <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[demo_mode]"
               value="1" <?php checked($demo_mode, 1); ?> />
        <label><?php echo esc_html__("Activer le mode démonstration (affiche des données fictives pour tester l'apparence)", 'discord-bot-jlg'); ?></label>
        <p class="description"><?php echo esc_html__('🎨 Parfait pour tester les styles et dispositions sans configuration Discord', 'discord-bot-jlg'); ?></p>
        <?php
    }

    public function show_online_render() {
        $options = get_option($this->option_name);
        $value   = isset($options['show_online']) ? (int) $options['show_online'] : 0;
        ?>
        <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[show_online]"
               value="1" <?php checked($value, 1); ?> />
        <label><?php echo esc_html__("Afficher le nombre d'utilisateurs en ligne", 'discord-bot-jlg'); ?></label>
        <?php
    }

    public function show_total_render() {
        $options = get_option($this->option_name);
        $value   = isset($options['show_total']) ? (int) $options['show_total'] : 0;
        ?>
        <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[show_total]"
               value="1" <?php checked($value, 1); ?> />
        <label><?php echo esc_html__("Afficher le nombre total de membres", 'discord-bot-jlg'); ?></label>
        <?php
    }

    public function widget_title_render() {
        $options = get_option($this->option_name);
        ?>
        <input type="text" name="<?php echo esc_attr($this->option_name); ?>[widget_title]"
               value="<?php echo esc_attr(isset($options['widget_title']) ? $options['widget_title'] : ''); ?>"
               class="regular-text" />
        <?php
    }

    public function cache_duration_render() {
        $options = get_option($this->option_name);
        ?>
        <input type="number" name="<?php echo esc_attr($this->option_name); ?>[cache_duration]"
               value="<?php echo esc_attr(isset($options['cache_duration']) ? $options['cache_duration'] : ''); ?>"
               min="60" max="3600" class="small-text" />
        <p class="description"><?php echo esc_html__('Minimum 60 secondes, maximum 3600 secondes (1 heure)', 'discord-bot-jlg'); ?></p>
        <?php
    }

    public function custom_css_render() {
        $options = get_option($this->option_name);
        ?>
        <textarea name="<?php echo esc_attr($this->option_name); ?>[custom_css]" rows="5" cols="50"><?php echo esc_textarea(isset($options['custom_css']) ? $options['custom_css'] : ''); ?></textarea>
        <p class="description"><?php echo esc_html__("CSS personnalisé pour styliser l'affichage", 'discord-bot-jlg'); ?></p>
        <?php
    }

    public function options_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('🎮 Discord Bot - JLG - Configuration', 'discord-bot-jlg'); ?></h1>
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
            <h3 style="margin-top: 0;"><?php echo esc_html__('🔧 Test de connexion', 'discord-bot-jlg'); ?></h3>
            <p><?php echo esc_html__('Vérifiez que votre configuration fonctionne :', 'discord-bot-jlg'); ?></p>
            <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>">
                <input type="hidden" name="page" value="discord-bot-jlg" />
                <input type="hidden" name="test_connection" value="1" />
                <?php wp_nonce_field('discord_test_connection'); ?>
                <p>
                    <button type="submit" class="button button-secondary" style="width: 100%;"><?php echo esc_html__('Tester la connexion', 'discord-bot-jlg'); ?></button>
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
            <h3 style="margin-top: 0;"><?php echo esc_html__('🚀 Liens rapides', 'discord-bot-jlg'); ?></h3>
            <ul style="list-style: none; padding: 0;">
                <li style="margin-bottom: 10px;">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=discord-bot-demo')); ?>" class="button button-primary" style="width: 100%;">
                        <?php echo esc_html__('📖 Guide & Démo', 'discord-bot-jlg'); ?>
                    </a>
                </li>
                <li style="margin-bottom: 10px;">
                    <a href="https://discord.com/developers/applications" target="_blank" class="button" style="width: 100%;">
                        <?php echo esc_html__('🔗 Discord Developer Portal', 'discord-bot-jlg'); ?>
                    </a>
                </li>
                <li>
                    <a href="<?php echo esc_url(admin_url('widgets.php')); ?>" class="button" style="width: 100%;">
                        <?php echo esc_html__('📐 Gérer les Widgets', 'discord-bot-jlg'); ?>
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
            <p style="margin: 0;"><?php echo esc_html__('Discord Bot - JLG v1.0 | Développé par Jérôme Le Gousse', 'discord-bot-jlg'); ?></p>
        </div>
        <?php
    }

    public function demo_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('📖 Guide & Démonstration', 'discord-bot-jlg'); ?></h1>
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
            <p>
                <?php
                echo wp_kses(
                    __('<strong>💡 Astuce :</strong> Tous les exemples ci-dessous utilisent le mode démo. Vous pouvez les copier-coller directement !', 'discord-bot-jlg'),
                    array('strong' => array())
                );
                ?>
            </p>
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
        );
        ?>
        <div style="background: #e3f2fd; padding: 20px; border-radius: 8px;">
            <h2><?php echo esc_html__('🎨 Prévisualisation en direct', 'discord-bot-jlg'); ?></h2>
            <p><?php echo esc_html__('Testez différentes configurations visuelles :', 'discord-bot-jlg'); ?></p>
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
        $appearance_items = array(
            __('<strong>layout</strong> : horizontal, vertical, compact', 'discord-bot-jlg'),
            __('<strong>theme</strong> : discord, dark, light, minimal', 'discord-bot-jlg'),
            __('<strong>align</strong> : left, center, right', 'discord-bot-jlg'),
            __('<strong>width</strong> : largeur CSS (ex: "300px", "100%")', 'discord-bot-jlg'),
            __('<strong>compact</strong> : true/false (version réduite)', 'discord-bot-jlg'),
            __('<strong>animated</strong> : true/false (animations hover)', 'discord-bot-jlg'),
            __('<strong>class</strong> : classes CSS additionnelles', 'discord-bot-jlg'),
        );

        $logo_items = array(
            __('<strong>show_discord_icon</strong> : true/false (afficher le logo officiel)', 'discord-bot-jlg'),
            __('<strong>discord_icon_position</strong> : left, right, top (position du logo)', 'discord-bot-jlg'),
        );

        $data_items = array(
            __('<strong>show_online</strong> : true/false', 'discord-bot-jlg'),
            __('<strong>show_total</strong> : true/false', 'discord-bot-jlg'),
            __('<strong>show_title</strong> : true/false', 'discord-bot-jlg'),
            __('<strong>title</strong> : texte du titre', 'discord-bot-jlg'),
            __('<strong>hide_labels</strong> : true/false', 'discord-bot-jlg'),
            __('<strong>hide_icons</strong> : true/false', 'discord-bot-jlg'),
        );

        $custom_text_items = array(
            __('<strong>icon_online</strong> : emoji/texte (défaut: 🟢)', 'discord-bot-jlg'),
            __('<strong>icon_total</strong> : emoji/texte (défaut: 👥)', 'discord-bot-jlg'),
            __('<strong>label_online</strong> : texte personnalisé', 'discord-bot-jlg'),
            __('<strong>label_total</strong> : texte personnalisé', 'discord-bot-jlg'),
        );

        $technical_items = array(
            __('<strong>refresh</strong> : true/false (auto-actualisation)', 'discord-bot-jlg'),
            __('<strong>refresh_interval</strong> : secondes (minimum 10 secondes / 10 000 ms)', 'discord-bot-jlg'),
            __('<strong>demo</strong> : true/false (mode démonstration)', 'discord-bot-jlg'),
            __('<strong>border_radius</strong> : pixels (coins arrondis)', 'discord-bot-jlg'),
            __('<strong>gap</strong> : pixels (espace entre éléments)', 'discord-bot-jlg'),
            __('<strong>padding</strong> : pixels (espacement interne)', 'discord-bot-jlg'),
        );

        $recommended_configs = array(
            array(
                'label' => __('Pour une sidebar :', 'discord-bot-jlg'),
                'code'  => '[discord_stats layout="vertical" show_discord_icon="true" discord_icon_position="top" compact="true"]',
            ),
            array(
                'label' => __('Pour un header :', 'discord-bot-jlg'),
                'code'  => '[discord_stats show_discord_icon="true" show_title="true" title="Join us!" align="center" width="100%"]',
            ),
            array(
                'label' => __('Pour un footer :', 'discord-bot-jlg'),
                'code'  => '[discord_stats theme="dark" show_discord_icon="true" compact="true"]',
            ),
            array(
                'label' => __('Style gaming :', 'discord-bot-jlg'),
                'code'  => '[discord_stats theme="dark" icon_online="🎮" label_online="Players" show_discord_icon="true"]',
            ),
        );
        ?>
        <div style="background: #e8f5e9; padding: 20px; border-radius: 8px;">
            <h2><?php echo esc_html__('📖 Guide d\'utilisation', 'discord-bot-jlg'); ?></h2>

            <h3><?php echo esc_html__('Option 1 : Shortcode (avec paramètres)', 'discord-bot-jlg'); ?></h3>
            <p><?php echo esc_html__('Copiez ce code dans n\'importe quelle page ou article :', 'discord-bot-jlg'); ?></p>
            <code style="background: white; padding: 10px; display: inline-block; border-radius: 4px;">[discord_stats]</code>

            <h4><?php echo esc_html__('Exemples avec paramètres :', 'discord-bot-jlg'); ?></h4>
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

            <p style="margin-top: 10px;"><em><?php echo wp_kses_post(__('ℹ️ L\'auto-refresh nécessite un intervalle d\'au moins 10&nbsp;secondes (10 000&nbsp;ms). Toute valeur inférieure est automatiquement ajustée pour éviter les erreurs 429.', 'discord-bot-jlg')); ?></em></p>

            <h4><?php echo esc_html__('Tous les paramètres disponibles :', 'discord-bot-jlg'); ?></h4>
            <div style="background: white; padding: 15px; border-radius: 4px;">
                <h5><?php echo esc_html__('🎨 Apparence & Layout :', 'discord-bot-jlg'); ?></h5>
                <ul style="columns: 2; column-gap: 30px;">
                    <?php foreach ($appearance_items as $item) : ?>
                        <li><?php echo wp_kses($item, array('strong' => array())); ?></li>
                    <?php endforeach; ?>
                </ul>

                <h5><?php echo esc_html__('🎯 Logo Discord :', 'discord-bot-jlg'); ?></h5>
                <ul>
                    <?php foreach ($logo_items as $item) : ?>
                        <li><?php echo wp_kses($item, array('strong' => array())); ?></li>
                    <?php endforeach; ?>
                </ul>

                <h5><?php echo esc_html__('📊 Données affichées :', 'discord-bot-jlg'); ?></h5>
                <ul style="columns: 2; column-gap: 30px;">
                    <?php foreach ($data_items as $item) : ?>
                        <li><?php echo wp_kses($item, array('strong' => array())); ?></li>
                    <?php endforeach; ?>
                </ul>

                <h5><?php echo esc_html__('✏️ Personnalisation textes/icônes :', 'discord-bot-jlg'); ?></h5>
                <ul style="columns: 2; column-gap: 30px;">
                    <?php foreach ($custom_text_items as $item) : ?>
                        <li><?php echo wp_kses($item, array('strong' => array())); ?></li>
                    <?php endforeach; ?>
                </ul>

                <h5><?php echo esc_html__('⚙️ Paramètres techniques :', 'discord-bot-jlg'); ?></h5>
                <ul style="columns: 2; column-gap: 30px;">
                    <?php foreach ($technical_items as $item) : ?>
                        <li><?php echo wp_kses($item, array('strong' => array())); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <h3><?php echo esc_html__('Option 2 : Widget', 'discord-bot-jlg'); ?></h3>
            <p>
                <?php
                echo wp_kses(
                    __('Allez dans <strong>Apparence &gt; Widgets</strong> et ajoutez le widget <strong>"Discord Bot - JLG"</strong> dans votre sidebar', 'discord-bot-jlg'),
                    array('strong' => array())
                );
                ?>
            </p>

            <h3><?php echo esc_html__('Option 3 : Code PHP', 'discord-bot-jlg'); ?></h3>
            <p><?php echo esc_html__('Pour les développeurs, dans vos templates PHP :', 'discord-bot-jlg'); ?></p>
            <code style="background: white; padding: 10px; display: block; border-radius: 4px;">
                &lt;?php echo do_shortcode('[discord_stats show_discord_icon="true"]'); ?&gt;
            </code>

            <h3><?php echo esc_html__('💡 Configurations recommandées', 'discord-bot-jlg'); ?></h3>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 15px;">
                <?php foreach ($recommended_configs as $config) : ?>
                    <div style="background: white; padding: 15px; border-radius: 4px;">
                        <strong><?php echo esc_html($config['label']); ?></strong><br>
                        <code style="font-size: 12px;"><?php echo esc_html($config['code']); ?></code>
                    </div>
                <?php endforeach; ?>
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
            <h2><?php echo esc_html__('❓ Dépannage', 'discord-bot-jlg'); ?></h2>
            <ul>
                <li><?php echo wp_kses(__('<strong>Erreur de connexion ?</strong> Vérifiez que le bot est bien sur votre serveur', 'discord-bot-jlg'), array('strong' => array())); ?></li>
                <li><?php echo wp_kses(__('<strong>Stats à 0 ?</strong> Assurez-vous que le widget est activé dans les paramètres Discord', 'discord-bot-jlg'), array('strong' => array())); ?></li>
                <li><?php echo wp_kses(__('<strong>Token invalide ?</strong> Régénérez le token dans le Developer Portal', 'discord-bot-jlg'), array('strong' => array())); ?></li>
                <li><?php echo wp_kses(__('<strong>Cache ?</strong> Les stats sont mises à jour toutes les 5 minutes par défaut', 'discord-bot-jlg'), array('strong' => array())); ?></li>
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
            <?php
            $documentation_link = sprintf(
                '<a href="%1$s" target="_blank">%2$s</a>',
                esc_url('https://discord.com/developers/docs/intro'),
                esc_html__('Documentation Discord API', 'discord-bot-jlg')
            );

            $help_link = sprintf(
                '<a href="#" onclick="return false;">%s</a>',
                esc_html__('Besoin d\'aide ?', 'discord-bot-jlg')
            );

            printf(
                '<p style="margin: 0;">%s</p>',
                wp_kses_post(
                    sprintf(
                        __('Discord Bot - JLG v1.0 | Développé par Jérôme Le Gousse | %1$s | %2$s', 'discord-bot-jlg'),
                        $documentation_link,
                        $help_link
                    )
                )
            );
            ?>
        </div>
        <?php
    }

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
            '1.0'
        );
    }

    public function test_discord_connection() {
        $options = get_option($this->option_name);
        if (!is_array($options)) {
            $options = array();
        }

        if (!empty($options['demo_mode'])) {
            printf(
                '<div class="notice notice-info"><p>%s</p></div>',
                esc_html__('🎨 Mode démonstration activé - Les données affichées sont fictives', 'discord-bot-jlg')
            );
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
            $total_count  = isset($stats['total']) ? (int) $stats['total'] : 0;

            $server_name_display = esc_html($server_name);
            $online_display     = esc_html(number_format_i18n($online_count));
            $total_display      = esc_html(number_format_i18n($total_count));

            printf(
                '<div class="notice notice-success"><p>%s</p></div>',
                sprintf(
                    esc_html__('✅ Connexion réussie ! Serveur : %1$s - %2$s en ligne / %3$s membres', 'discord-bot-jlg'),
                    $server_name_display,
                    $online_display,
                    $total_display
                )
            );
        } elseif (is_array($stats) && !empty($stats['is_demo'])) {
            printf(
                '<div class="notice notice-warning"><p>%s</p></div>',
                esc_html__('⚠️ Pas de configuration Discord détectée. Mode démo actif.', 'discord-bot-jlg')
            );
        } else {
            printf(
                '<div class="notice notice-error"><p>%s</p></div>',
                esc_html__('❌ Échec de la connexion. Vérifiez vos identifiants.', 'discord-bot-jlg')
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
