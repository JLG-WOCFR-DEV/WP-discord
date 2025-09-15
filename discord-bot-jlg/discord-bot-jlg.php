<?php
/**
 * Plugin Name: Discord Bot - JLG
 * Plugin URI: https://yourwebsite.com/
 * Description: Affiche les statistiques de votre serveur Discord (membres en ligne et total)
 * Version: 1.0
 * Author: Jérôme Le Gousse
 * License: GPL v2 or later
 */

// Empêcher l'accès direct
if (!defined('ABSPATH')) {
    exit;
}

// Classe principale du plugin
class DiscordServerStats {
    
    private $option_name = 'discord_server_stats_options';
    private $cache_key = 'discord_server_stats_cache';
    private $cache_duration = 300; // 5 minutes en secondes
    
    public function __construct() {
        // Hooks d'activation/désactivation
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'settings_init'));
        
        // Shortcode
        add_shortcode('discord_stats', array($this, 'render_shortcode'));
        
        // Widget
        add_action('widgets_init', array($this, 'register_widget'));

        // Styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_styles'));
        
        // AJAX pour refresh manuel
        add_action('wp_ajax_refresh_discord_stats', array($this, 'ajax_refresh_stats'));
    }
    
    // Activation du plugin
    public function activate() {
        $default_options = array(
            'server_id' => '',
            'bot_token' => '',
            'demo_mode' => false,
            'show_online' => true,
            'show_total' => true,
            'custom_css' => '',
            'widget_title' => 'Discord Server',
            'cache_duration' => 300
        );
        add_option($this->option_name, $default_options);
    }
    
    // Désactivation du plugin
    public function deactivate() {
        delete_transient($this->cache_key);
    }
    
    // Menu admin
    public function add_admin_menu() {
        // Icône Discord en base64 (version simplifiée pour le menu)
        $discord_icon = 'data:image/svg+xml;base64,PHN2ZyB2aWV3Qm94PSIwIDAgMjQgMjQiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PHBhdGggZmlsbD0iI2E0YWFiOCIgZD0iTTIwLjMxNyA0LjM3YTE5LjggMTkuOCAwIDAwLTQuODg1LTEuNTE1LjA3NC4wNzQgMCAwMC0uMDc5LjAzN2MtLjIxLjM3NS0uNDQ0Ljg2NC0uNjA4IDEuMjVhMTguMjcgMTguMjcgMCAwMC01LjQ4NyAwYy0uMTY1LS4zOTctLjQwNC0uODg1LS42MTgtMS4yNWEuMDc3LjA3NyAwIDAwLS4wNzktLjAzN0ExOS43NCAxOS43NCAwIDAwMy42NzcgNC4zN2EuMDcuMDcgMCAwMC0uMDMyLjAyN0MuNTMzIDkuMDQ2LS4zMiAxMy41OC4wOTkgMTguMDU3YS4wOC4wOCAwIDAwLjAzMS4wNTdBMTkuOSAxOS45IDAgMDA2LjA3MyAyMWEuMDc4LjA3OCAwIDAwLjA4NC0uMDI4IDEzLjQgMTMuNCAwIDAwMS4xNTUtMi4xLjA3Ni4wNzYgMCAwMC0uMDQxLS4xMDYgMTMuMSAxMy4xIDAgMDEtMS44NzItLjg5Mi4wNzcuMDc3IDAgMDEtLjAwOC0uMTI4IDE0IDE0IDAgMDAuMzctLjI5Mi4wNzQuMDc0IDAgMDEuMDc3LS4wMWMzLjkyNyAxLjc5MyA4LjE4IDEuNzkzIDEyLjA2IDAgYS4wNzQuMDc0IDAgMDEuMDc4LjAwOWMuMTE5LjA5OS4yNDYuMTk4LjM3My4yOTJhLjA3Ny4wNzcgMCAwMS0uMDA2LjEyNyAxMi4zIDEyLjMgMCAwMS0xLjg3My44OTIuMDc3LjA3NyAwIDAwLS4wNDEuMTA3YzMzOC43NDQgMS40MDMgMS4xNTUgMi4xLS4wODQuMDI4YS4wNzguMDc4IDAgMDAxOS45MDItMS45MDMuMDc2LjA3NiAwIDAwLjAzLS4wNTdjLjUzNy00LjU4LS45MDQtOC41NTMtMy44MjMtMTIuMDU3YS4wNi4wNiAwIDAwLS4wMzEtLjAyOHpNOC4wMiAxNS4yNzhjLTEuMTgzIDAtMi4xNTctMS4wODUtMi4xNTctMi40MiAwLTEuMzMzLjk1Ni0yLjQxOSAyLjE1Ny0yLjQxOSAxLjIxIDAgMi4xNzYgMS4wOTYgMi4xNTcgMi40MiAwIDEuMzM0LS45NTYgMi40MTktMi4xNTcgMi40MTl6bTcuOTc1IDBjLTEuMTgzIDAtMi4xNTctMS4wODUtMi4xNTctMi40MiAwLTEuMzMzLjk1NS0yLjQxOSAyLjE1Ny0yLjQxOXMyLjE1NyAxLjA5NiAyLjE1NyAyLjQyYzAgMS4zMzQtLjk1NiAyLjQxOS0yLjE1NyAyLjQxOXoiLz48L3N2Zz4=';
        
        // Menu principal
        add_menu_page(
            'Discord Bot - JLG',           // Titre de la page
            'Discord Bot',                 // Titre du menu
            'manage_options',              // Capacité requise
            'discord-bot-jlg',            // Slug du menu
            array($this, 'options_page'), // Fonction callback
            $discord_icon,                // Icône
            30                            // Position (30 = après Médias)
        );
        
        // Sous-menu Configuration
        add_submenu_page(
            'discord-bot-jlg',
            'Configuration',
            'Configuration',
            'manage_options',
            'discord-bot-jlg',
            array($this, 'options_page')
        );
        
        // Sous-menu Guide & Démo
        add_submenu_page(
            'discord-bot-jlg',
            'Guide & Démo',
            'Guide & Démo',
            'manage_options',
            'discord-bot-demo',
            array($this, 'demo_page')
        );
    }
    
    // Initialisation des paramètres
    public function settings_init() {
        register_setting('discord_stats_settings', $this->option_name, [
            'sanitize_callback' => [ $this, 'sanitize_options' ],
        ]);
        
        // Section API
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
        
        // Section Affichage
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

    // Sanitize and validate options
    public function sanitize_options($input) {
        $sanitized = [];

        // IDs and tokens
        $sanitized['server_id']    = isset($input['server_id']) ? (string) absint($input['server_id']) : '';
        $sanitized['bot_token']    = isset($input['bot_token']) ? sanitize_text_field($input['bot_token']) : '';

        // Booleans
        $sanitized['demo_mode']    = isset($input['demo_mode']) ? wp_validate_boolean($input['demo_mode']) : false;
        $sanitized['show_online']  = isset($input['show_online']) ? wp_validate_boolean($input['show_online']) : false;
        $sanitized['show_total']   = isset($input['show_total']) ? wp_validate_boolean($input['show_total']) : false;

        // Texte et entiers
        $sanitized['widget_title']  = isset($input['widget_title']) ? sanitize_text_field($input['widget_title']) : '';
        $sanitized['cache_duration'] = isset($input['cache_duration']) ? absint($input['cache_duration']) : 300;

        // CSS personnalisé
        $sanitized['custom_css'] = isset($input['custom_css']) ? wp_strip_all_tags($input['custom_css']) : '';

        return $sanitized;
    }

    // Callbacks pour les sections
    public function api_section_callback() {
        ?>
        <div style="background: #f0f4ff; padding: 20px; border-radius: 8px; margin: 20px 0;">
            <h3 style="margin-top: 0;">📚 Guide étape par étape</h3>
            
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
                <li>Dans "Bot Permissions", sélectionnez :
                    <ul>
                        <li>✅ View Channels</li>
                        <li>✅ Read Messages</li>
                        <li>✅ Send Messages (optionnel)</li>
                    </ul>
                </li>
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
            
            <div style="background: #fff3cd; padding: 10px; border-radius: 4px; margin-top: 15px;">
                <strong>💡 Conseil :</strong> Après avoir rempli les champs ci-dessous, utilisez le bouton "Tester la connexion" pour vérifier que tout fonctionne !
                <div style="margin: 20px 0;">
                    <h4>Avec logo Discord officiel :</h4>
                    <?php echo do_shortcode('[discord_stats demo="true" show_discord_icon="true" discord_icon_position="left"]'); ?>
                </div>
                
                <div style="margin: 20px 0;">
                    <h4>Logo Discord centré en haut :</h4>
                    <?php echo do_shortcode('[discord_stats demo="true" show_discord_icon="true" discord_icon_position="top" align="center" theme="dark"]'); ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    public function display_section_callback() {
        echo '<p>Personnalisez l\'affichage des statistiques Discord.</p>';
    }
    
    // Champs de formulaire
    public function server_id_render() {
        $options = get_option($this->option_name);
        ?>
        <input type="text" name="<?php echo $this->option_name; ?>[server_id]" 
               value="<?php echo esc_attr($options['server_id']); ?>" 
               class="regular-text" />
        <p class="description">L'ID de votre serveur Discord</p>
        <?php
    }
    
    public function bot_token_render() {
        $options = get_option($this->option_name);
        ?>
        <input type="password" name="<?php echo $this->option_name; ?>[bot_token]" 
               value="<?php echo esc_attr($options['bot_token']); ?>" 
               class="regular-text" />
        <p class="description">Le token de votre bot Discord (gardez-le secret !)</p>
        <?php
    }
    
    public function demo_mode_render() {
        $options = get_option($this->option_name);
        $demo_mode = isset($options['demo_mode']) ? $options['demo_mode'] : false;
        ?>
        <input type="checkbox" name="<?php echo $this->option_name; ?>[demo_mode]" 
               value="1" <?php checked($demo_mode, 1); ?> />
        <label>Activer le mode démonstration (affiche des données fictives pour tester l'apparence)</label>
        <p class="description">🎨 Parfait pour tester les styles et dispositions sans configuration Discord</p>
        <?php
    }
    
    public function show_online_render() {
        $options = get_option($this->option_name);
        ?>
        <input type="checkbox" name="<?php echo $this->option_name; ?>[show_online]" 
               value="1" <?php checked($options['show_online'], 1); ?> />
        <label>Afficher le nombre d'utilisateurs en ligne</label>
        <?php
    }
    
    public function show_total_render() {
        $options = get_option($this->option_name);
        ?>
        <input type="checkbox" name="<?php echo $this->option_name; ?>[show_total]" 
               value="1" <?php checked($options['show_total'], 1); ?> />
        <label>Afficher le nombre total de membres</label>
        <?php
    }
    
    public function widget_title_render() {
        $options = get_option($this->option_name);
        ?>
        <input type="text" name="<?php echo $this->option_name; ?>[widget_title]" 
               value="<?php echo esc_attr($options['widget_title']); ?>" 
               class="regular-text" />
        <?php
    }
    
    public function cache_duration_render() {
        $options = get_option($this->option_name);
        ?>
        <input type="number" name="<?php echo $this->option_name; ?>[cache_duration]" 
               value="<?php echo esc_attr($options['cache_duration']); ?>" 
               min="60" max="3600" class="small-text" />
        <p class="description">Minimum 60 secondes, maximum 3600 secondes (1 heure)</p>
        <?php
    }
    
    public function custom_css_render() {
        $options = get_option($this->option_name);
        ?>
        <textarea name="<?php echo $this->option_name; ?>[custom_css]" 
                  rows="5" cols="50"><?php echo esc_textarea($options['custom_css']); ?></textarea>
        <p class="description">CSS personnalisé pour styliser l'affichage</p>
        <?php
    }
    
    // Page d'options (Configuration)
    public function options_page() {
        ?>
        <div class="wrap">
            <h1>🎮 Discord Bot - JLG - Configuration</h1>
            
            <?php
            // Test de connexion
            if (isset($_GET['test_connection'])) {
                $this->test_discord_connection();
            }
            ?>
            
            <div style="display: flex; gap: 20px; margin-top: 20px;">
                <!-- Colonne principale -->
                <div style="flex: 1;">
                    <form action="options.php" method="post">
                        <?php
                        settings_fields('discord_stats_settings');
                        do_settings_sections('discord_stats_settings');
                        submit_button();
                        ?>
                    </form>
                </div>
                
                <!-- Colonne latérale -->
                <div style="width: 300px;">
                    <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                        <h3 style="margin-top: 0;">🔧 Test de connexion</h3>
                        <p>Vérifiez que votre configuration fonctionne :</p>
                        <p>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=discord-bot-jlg&test_connection=1')); ?>"
                               class="button button-secondary" style="width: 100%;">Tester la connexion</a>
                        </p>
                    </div>
                    
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
                </div>
            </div>
            
            <div style="margin-top: 30px; padding: 15px; background: #f0f0f0; border-radius: 8px; text-align: center;">
                <p style="margin: 0;">Discord Bot - JLG v1.0 | Développé par Jérôme Le Gousse</p>
            </div>
        </div>
        <?php
    }
    
    // Page Guide & Démo
    public function demo_page() {
        ?>
        <div class="wrap">
            <h1>📖 Guide & Démonstration</h1>
            
            <div style="background: #fff3cd; padding: 10px 20px; border-radius: 8px; margin: 20px 0;">
                <p><strong>💡 Astuce :</strong> Tous les exemples ci-dessous utilisent le mode démo. Vous pouvez les copier-coller directement !</p>
            </div>
            
            <hr style="margin: 30px 0;">
            
            <div style="background: #e3f2fd; padding: 20px; border-radius: 8px;">
                <h2>🎨 Prévisualisation en direct</h2>
                <p>Testez différentes configurations visuelles :</p>
                
                <div style="margin: 20px 0;">
                    <h4>Standard horizontal :</h4>
                    <?php echo do_shortcode('[discord_stats demo="true"]'); ?>
                </div>
                
                <div style="margin: 20px 0;">
                    <h4>Vertical pour sidebar :</h4>
                    <div style="max-width: 300px;">
                        <?php echo do_shortcode('[discord_stats demo="true" layout="vertical" theme="minimal"]'); ?>
                    </div>
                </div>
                
                <div style="margin: 20px 0;">
                    <h4>Compact mode sombre :</h4>
                    <?php echo do_shortcode('[discord_stats demo="true" compact="true" theme="dark"]'); ?>
                </div>
                
                <div style="margin: 20px 0;">
                    <h4>Avec titre personnalisé :</h4>
                    <?php echo do_shortcode('[discord_stats demo="true" show_title="true" title="🎮 Notre Communauté Gaming" align="center"]'); ?>
                </div>
                
                <div style="margin: 20px 0;">
                    <h4>Icônes personnalisées :</h4>
                    <?php echo do_shortcode('[discord_stats demo="true" icon_online="🔥" label_online="Actifs" icon_total="⚔️" label_total="Guerriers"]'); ?>
                </div>
                
                <div style="margin: 20px 0;">
                    <h4>Minimaliste (nombres uniquement) :</h4>
                    <?php echo do_shortcode('[discord_stats demo="true" hide_labels="true" hide_icons="true" theme="minimal"]'); ?>
                </div>
            </div>
            
            <hr style="margin: 30px 0;">
            
            <div style="background: #f8f9fa; padding: 20px; border-radius: 8px;">
                <h2>🔧 Test de connexion</h2>
                <p>Vérifiez que votre configuration fonctionne correctement :</p>
                <p>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=discord-bot-jlg&test_connection=1')); ?>"
                       class="button button-secondary">Tester la connexion Discord</a>
                </p>
            </div>
            
            <hr style="margin: 30px 0;">
            
            <div style="background: #e8f5e9; padding: 20px; border-radius: 8px;">
                <h2>📖 Guide d'utilisation</h2>
                
                <h3>Option 1 : Shortcode (avec paramètres)</h3>
                <p>Copiez ce code dans n'importe quelle page ou article :</p>
                <code style="background: white; padding: 10px; display: inline-block; border-radius: 4px;">[discord_stats]</code>
                
                <h4>Exemples avec paramètres :</h4>
                <pre style="background: white; padding: 15px; border-radius: 4px; overflow-x: auto;">
// BASIQUES
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
// Auto-refresh toutes les 30 secondes
[discord_stats refresh="true" refresh_interval="30" show_discord_icon="true"]

// Afficher seulement les membres en ligne avec logo
[discord_stats show_online="true" show_total="false" show_discord_icon="true"]

// MODE DÉMO (pour tester l'apparence)
[discord_stats demo="true" show_discord_icon="true" theme="dark" layout="vertical"]
                </pre>
                
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
                        <li><strong>refresh_interval</strong> : secondes (min 10)</li>
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
            
            <hr style="margin: 30px 0;">
            
            <div style="background: #fff8e1; padding: 20px; border-radius: 8px;">
                <h2>❓ Dépannage</h2>
                <ul>
                    <li><strong>Erreur de connexion ?</strong> Vérifiez que le bot est bien sur votre serveur</li>
                    <li><strong>Stats à 0 ?</strong> Assurez-vous que le widget est activé dans les paramètres Discord</li>
                    <li><strong>Token invalide ?</strong> Régénérez le token dans le Developer Portal</li>
                    <li><strong>Cache ?</strong> Les stats sont mises à jour toutes les 5 minutes par défaut</li>
                </ul>
            </div>
            
            <div style="margin-top: 30px; padding: 15px; background: #f0f0f0; border-radius: 8px; text-align: center;">
                <p style="margin: 0;">Discord Bot - JLG v1.0 | Développé par Jérôme Le Gousse | 
                   <a href="https://discord.com/developers/docs/intro" target="_blank">Documentation Discord API</a> | 
                   <a href="#" onclick="return false;">Besoin d'aide ?</a>
                </p>
            </div>
        </div>
        <?php
    }
    
    // Récupération des stats Discord via API
    private function get_discord_stats() {
        $options = get_option($this->option_name);
        
        // Mode démonstration - retourner des données fictives
        if (!empty($options['demo_mode'])) {
            return $this->get_demo_stats();
        }
        
        // Vérifier le cache
        $cached_stats = get_transient($this->cache_key);
        if ($cached_stats !== false) {
            return $cached_stats;
        }
        
        if (empty($options['server_id']) || empty($options['bot_token'])) {
            // Si pas configuré, retourner les stats de démo
            return $this->get_demo_stats();
        }
        
        // Utiliser l'API Discord via widget.json (méthode publique)
        // Alternative : utiliser l'API REST avec le bot token
        $widget_url = 'https://discord.com/api/guilds/' . $options['server_id'] . '/widget.json';
        
        $response = wp_remote_get($widget_url, array(
            'timeout' => 10,
            'headers' => array(
                'User-Agent' => 'WordPress Discord Stats Plugin'
            )
        ));

        if (is_wp_error($response)) {
            // Essayer avec l'API Bot si le widget échoue
            return $this->get_discord_stats_via_bot();
        }

        $code = wp_remote_retrieve_response_code($response);
        if (200 !== $code) {
            return $this->get_discord_stats_via_bot();
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!$data || !isset($data['presence_count'])) {
            return $this->get_discord_stats_via_bot();
        }
        
        $stats = array(
            'online' => intval($data['presence_count']),
            'total' => intval($data['member_count']),
            'server_name' => $data['name']
        );
        
        // Mettre en cache
        set_transient($this->cache_key, $stats, $options['cache_duration']);
        
        return $stats;
    }
    
    // Récupération via Bot API (plus fiable)
    private function get_discord_stats_via_bot() {
        $options = get_option($this->option_name);
        
        if (empty($options['bot_token'])) {
            return $this->get_demo_stats();
        }
        
        // API endpoint pour obtenir les infos du serveur
        $api_url = 'https://discord.com/api/v10/guilds/' . $options['server_id'] . '?with_counts=true';
        
        $response = wp_remote_get($api_url, array(
            'timeout' => 10,
            'headers' => array(
                'Authorization' => 'Bot ' . $options['bot_token'],
                'User-Agent' => 'WordPress Discord Stats Plugin'
            )
        ));

        if (is_wp_error($response)) {
            return $this->get_demo_stats();
        }

        $code = wp_remote_retrieve_response_code($response);
        if (200 !== $code) {
            return $this->get_demo_stats();
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!$data || !isset($data['approximate_presence_count'])) {
            return $this->get_demo_stats();
        }
        
        $stats = array(
            'online' => intval($data['approximate_presence_count']),
            'total' => intval($data['approximate_member_count']),
            'server_name' => $data['name']
        );
        
        // Mettre en cache
        set_transient($this->cache_key, $stats, $options['cache_duration']);
        
        return $stats;
    }
    
    // Générer des stats de démonstration
    private function get_demo_stats() {
        // Créer des nombres réalistes qui changent légèrement
        $base_online = 42;
        $base_total = 256;
        
        // Ajouter une variation pour rendre plus réaliste
        $hour = intval(date('H'));
        $variation = sin($hour * 0.26) * 10; // Variation selon l'heure
        
        return array(
            'online' => round($base_online + $variation),
            'total' => $base_total,
            'server_name' => 'Serveur Démo',
            'is_demo' => true
        );
    }
    
    // Test de connexion
    private function test_discord_connection() {
        $options = get_option($this->option_name);
        
        // Si mode démo activé
        if (!empty($options['demo_mode'])) {
            echo '<div class="notice notice-info"><p>🎨 Mode démonstration activé - Les données affichées sont fictives</p></div>';
            return;
        }
        
        // Forcer la récupération sans cache
        delete_transient($this->cache_key);
        $stats = $this->get_discord_stats();
        
        if ($stats && empty($stats['is_demo'])) {
            echo '<div class="notice notice-success"><p>✅ Connexion réussie ! Serveur : ' . 
                 esc_html($stats['server_name']) . ' - ' .
                 $stats['online'] . ' en ligne / ' . $stats['total'] . ' membres</p></div>';
        } elseif ($stats && !empty($stats['is_demo'])) {
            echo '<div class="notice notice-warning"><p>⚠️ Pas de configuration Discord détectée. Mode démo actif.</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>❌ Échec de la connexion. Vérifiez vos identifiants.</p></div>';
        }
    }
    
    // Rendu du shortcode
    public function render_shortcode($atts) {
        // Récupérer les options par défaut
        $options = get_option($this->option_name);
        
        // Attributs du shortcode avec valeurs par défaut
        $atts = shortcode_atts(array(
            'layout' => 'horizontal',        // horizontal, vertical, compact
            'show_online' => $options['show_online'] ? 'true' : 'false',
            'show_total' => $options['show_total'] ? 'true' : 'false',
            'show_title' => 'false',
            'title' => $options['widget_title'],
            'theme' => 'discord',            // discord, dark, light, minimal, custom
            'animated' => 'true',
            'refresh' => 'false',            // Auto-refresh via AJAX
            'refresh_interval' => '60',      // Secondes
            'compact' => 'false',
            'align' => 'left',               // left, center, right
            'width' => '',                   // Largeur personnalisée
            'class' => '',                   // Classes CSS additionnelles
            'icon_online' => '🟢',
            'icon_total' => '👥',
            'label_online' => 'En ligne',
            'label_total' => 'Membres',
            'hide_labels' => 'false',
            'hide_icons' => 'false',
            'border_radius' => '8',
            'gap' => '20',
            'padding' => '15',
            'demo' => 'false',               // Forcer le mode démo pour ce shortcode
            'show_discord_icon' => 'false',  // Afficher l'icône Discord
            'discord_icon_position' => 'left', // left, right, top
        ), $atts, 'discord_stats');
        
        // Convertir les chaînes en booléens
        $show_online = filter_var($atts['show_online'], FILTER_VALIDATE_BOOLEAN);
        $show_total = filter_var($atts['show_total'], FILTER_VALIDATE_BOOLEAN);
        $show_title = filter_var($atts['show_title'], FILTER_VALIDATE_BOOLEAN);
        $animated = filter_var($atts['animated'], FILTER_VALIDATE_BOOLEAN);
        $refresh = filter_var($atts['refresh'], FILTER_VALIDATE_BOOLEAN);
        $compact = filter_var($atts['compact'], FILTER_VALIDATE_BOOLEAN);
        $hide_labels = filter_var($atts['hide_labels'], FILTER_VALIDATE_BOOLEAN);
        $hide_icons = filter_var($atts['hide_icons'], FILTER_VALIDATE_BOOLEAN);
        $force_demo = filter_var($atts['demo'], FILTER_VALIDATE_BOOLEAN);
        $show_discord_icon = filter_var($atts['show_discord_icon'], FILTER_VALIDATE_BOOLEAN);
        
        // Récupérer les stats (mode démo si forcé par shortcode)
        if ($force_demo) {
            $stats = $this->get_demo_stats();
        } else {
            $stats = $this->get_discord_stats();
        }
        
        if (!$stats) {
            return '<div class="discord-stats-error">Impossible de récupérer les stats Discord</div>';
        }
        
        // Générer un ID unique pour cette instance
        $unique_id = 'discord-stats-' . wp_rand(1000, 9999);
        
        // Classes CSS
        $container_classes = array(
            'discord-stats-container',
            'discord-layout-' . esc_attr($atts['layout']),
            'discord-theme-' . esc_attr($atts['theme']),
            'discord-align-' . esc_attr($atts['align'])
        );
        
        if ($compact) {
            $container_classes[] = 'discord-compact';
        }
        
        if ($animated) {
            $container_classes[] = 'discord-animated';
        }
        
        if (!empty($stats['is_demo'])) {
            $container_classes[] = 'discord-demo-mode';
        }
        
        if ($show_discord_icon) {
            $container_classes[] = 'discord-with-logo';
            $container_classes[] = 'discord-logo-' . esc_attr($atts['discord_icon_position']);
        }
        
        if (!empty($atts['class'])) {
            $container_classes[] = esc_attr($atts['class']);
        }
        
        // SVG Discord Icon
        $discord_svg = '<svg class="discord-logo-svg" viewBox="0 0 127.14 96.36" xmlns="http://www.w3.org/2000/svg"><path d="M107.7,8.07A105.15,105.15,0,0,0,81.47,0a72.06,72.06,0,0,0-3.36,6.83A97.68,97.68,0,0,0,49,6.83,72.37,72.37,0,0,0,45.64,0,105.89,105.89,0,0,0,19.39,8.09C2.79,32.65-1.71,56.6.54,80.21h0A105.73,105.73,0,0,0,32.71,96.36,77.7,77.7,0,0,0,39.6,85.25a68.42,68.42,0,0,1-10.85-5.18c.91-.66,1.8-1.34,2.66-2a75.57,75.57,0,0,0,64.32,0c.87.71,1.76,1.39,2.66,2a68.68,68.68,0,0,1-10.87,5.19,77,77,0,0,0,6.89,11.1A105.25,105.25,0,0,0,126.6,80.22h0C129.24,52.84,122.09,29.11,107.7,8.07ZM42.45,65.69C36.18,65.69,31,60,31,53s5-12.74,11.43-12.74S54,46,53.89,53,48.84,65.69,42.45,65.69Zm42.24,0C78.41,65.69,73.25,60,73.25,53s5-12.74,11.44-12.74S96.23,46,96.12,53,91.08,65.69,84.69,65.69Z"/></svg>';
        
        ob_start();
        ?>
        <div id="<?php echo $unique_id; ?>" 
             class="<?php echo implode(' ', $container_classes); ?>"
             <?php if (!empty($atts['width'])): ?>style="width: <?php echo esc_attr($atts['width']); ?>;"<?php endif; ?>
             <?php if ($refresh && empty($stats['is_demo'])): ?>data-refresh="<?php echo esc_attr($atts['refresh_interval']); ?>"<?php endif; ?>>
            
            <?php if (!empty($stats['is_demo'])): ?>
            <div class="discord-demo-badge">Mode Démo</div>
            <?php endif; ?>
            
            <?php if ($show_title): ?>
            <div class="discord-stats-title"><?php echo esc_html($atts['title']); ?></div>
            <?php endif; ?>
            
            <div class="discord-stats-main">
                <?php if ($show_discord_icon && $atts['discord_icon_position'] === 'left'): ?>
                <div class="discord-logo-container">
                    <?php echo $discord_svg; ?>
                </div>
                <?php endif; ?>
                
                <?php if ($show_discord_icon && $atts['discord_icon_position'] === 'top'): ?>
                <div class="discord-logo-container discord-logo-top">
                    <?php echo $discord_svg; ?>
                </div>
                <?php endif; ?>
                
                <div class="discord-stats-wrapper">
                    <?php if ($show_online) : ?>
                    <div class="discord-stat discord-online" data-value="<?php echo $stats['online']; ?>">
                        <?php if (!$hide_icons): ?>
                        <span class="discord-icon"><?php echo esc_html($atts['icon_online']); ?></span>
                        <?php endif; ?>
                        <span class="discord-number"><?php echo number_format($stats['online']); ?></span>
                        <?php if (!$hide_labels): ?>
                        <span class="discord-label"><?php echo esc_html($atts['label_online']); ?></span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($show_total) : ?>
                    <div class="discord-stat discord-total" data-value="<?php echo $stats['total']; ?>">
                        <?php if (!$hide_icons): ?>
                        <span class="discord-icon"><?php echo esc_html($atts['icon_total']); ?></span>
                        <?php endif; ?>
                        <span class="discord-number"><?php echo number_format($stats['total']); ?></span>
                        <?php if (!$hide_labels): ?>
                        <span class="discord-label"><?php echo esc_html($atts['label_total']); ?></span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
                
                <?php if ($show_discord_icon && $atts['discord_icon_position'] === 'right'): ?>
                <div class="discord-logo-container">
                    <?php echo $discord_svg; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <style type="text/css">
            #<?php echo $unique_id; ?> {
                --discord-gap: <?php echo intval($atts['gap']); ?>px;
                --discord-padding: <?php echo intval($atts['padding']); ?>px;
                --discord-radius: <?php echo intval($atts['border_radius']); ?>px;
                position: relative;
            }
            
            #<?php echo $unique_id; ?>.discord-stats-container .discord-stats-wrapper {
                gap: var(--discord-gap);
            }
            
            #<?php echo $unique_id; ?> .discord-stat {
                padding: var(--discord-padding) calc(var(--discord-padding) * 1.33);
                border-radius: var(--discord-radius);
            }
            
            /* Discord Logo Styles */
            #<?php echo $unique_id; ?> .discord-stats-main {
                display: flex;
                align-items: center;
                gap: 20px;
            }
            
            #<?php echo $unique_id; ?> .discord-logo-container {
                flex-shrink: 0;
            }
            
            #<?php echo $unique_id; ?> .discord-logo-svg {
                width: 40px;
                height: 30px;
                fill: #5865F2;
                transition: all 0.3s ease;
            }
            
            #<?php echo $unique_id; ?>.discord-compact .discord-logo-svg {
                width: 30px;
                height: 23px;
            }
            
            /* Logo position variations */
            #<?php echo $unique_id; ?>.discord-logo-top .discord-stats-main {
                flex-direction: column;
            }
            
            #<?php echo $unique_id; ?>.discord-logo-top .discord-logo-container {
                margin-bottom: 15px;
            }
            
            /* Theme-based logo colors */
            #<?php echo $unique_id; ?>.discord-theme-discord .discord-logo-svg {
                fill: white;
            }
            
            #<?php echo $unique_id; ?>.discord-theme-dark .discord-logo-svg {
                fill: white;
            }
            
            #<?php echo $unique_id; ?>.discord-theme-light .discord-logo-svg {
                fill: #5865F2;
            }
            
            #<?php echo $unique_id; ?>.discord-theme-minimal .discord-logo-svg {
                fill: currentColor;
            }
            
            /* Hover effect on logo */
            #<?php echo $unique_id; ?>.discord-animated .discord-logo-svg:hover {
                transform: scale(1.1) rotate(-5deg);
            }
            
            /* Badge Mode Démo */
            #<?php echo $unique_id; ?> .discord-demo-badge {
                position: absolute;
                top: -10px;
                right: -10px;
                background: linear-gradient(45deg, #ff6b6b, #f06292);
                color: white;
                padding: 2px 8px;
                border-radius: 10px;
                font-size: 10px;
                font-weight: bold;
                text-transform: uppercase;
                z-index: 10;
                animation: pulse 2s infinite;
            }
            
            @keyframes pulse {
                0%, 100% { opacity: 1; transform: scale(1); }
                50% { opacity: 0.8; transform: scale(1.05); }
            }
            
            /* Animation pour mode démo */
            #<?php echo $unique_id; ?>.discord-demo-mode .discord-online .discord-number {
                animation: demo-variation 10s ease-in-out infinite;
            }
            
            @keyframes demo-variation {
                0%, 100% { opacity: 1; }
                50% { opacity: 0.7; }
            }
            
            /* Layout Vertical */
            #<?php echo $unique_id; ?>.discord-layout-vertical .discord-stats-wrapper {
                flex-direction: column;
            }
            
            #<?php echo $unique_id; ?>.discord-layout-vertical .discord-stat {
                width: 100%;
                justify-content: center;
            }
            
            #<?php echo $unique_id; ?>.discord-layout-vertical.discord-logo-left .discord-stats-main,
            #<?php echo $unique_id; ?>.discord-layout-vertical.discord-logo-right .discord-stats-main {
                flex-direction: column;
            }
            
            /* Layout Compact */
            #<?php echo $unique_id; ?>.discord-compact .discord-stat {
                padding: 8px 12px;
            }
            
            #<?php echo $unique_id; ?>.discord-compact .discord-number {
                font-size: 18px;
            }
            
            #<?php echo $unique_id; ?>.discord-compact .discord-icon {
                font-size: 16px;
            }
            
            /* Theme Dark */
            #<?php echo $unique_id; ?>.discord-theme-dark .discord-stat {
                background: #2c2f33;
                color: white;
            }
            
            /* Theme Light */
            #<?php echo $unique_id; ?>.discord-theme-light .discord-stat {
                background: #f6f6f6;
                color: #2c2f33;
                border: 1px solid #e3e5e8;
            }
            
            /* Theme Minimal */
            #<?php echo $unique_id; ?>.discord-theme-minimal .discord-stat {
                background: transparent;
                color: inherit;
                box-shadow: none;
                border: 1px solid currentColor;
            }
            
            /* Alignements */
            #<?php echo $unique_id; ?>.discord-align-center {
                justify-content: center;
            }
            
            #<?php echo $unique_id; ?>.discord-align-center .discord-stats-wrapper {
                justify-content: center;
            }
            
            #<?php echo $unique_id; ?>.discord-align-right {
                justify-content: flex-end;
            }
            
            #<?php echo $unique_id; ?>.discord-align-right .discord-stats-wrapper {
                justify-content: flex-end;
            }
            
            /* Animations améliorées */
            #<?php echo $unique_id; ?>.discord-animated .discord-number {
                transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
            }
            
            #<?php echo $unique_id; ?>.discord-animated .discord-stat:hover .discord-number {
                transform: scale(1.1);
            }
            
            /* Titre */
            #<?php echo $unique_id; ?> .discord-stats-title {
                font-size: 18px;
                font-weight: bold;
                margin-bottom: 15px;
                text-align: <?php echo esc_attr($atts['align']); ?>;
            }
        </style>
            
            /* Animations améliorées */
            #<?php echo $unique_id; ?>.discord-animated .discord-number {
                transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
            }
            
            #<?php echo $unique_id; ?>.discord-animated .discord-stat:hover .discord-number {
                transform: scale(1.1);
            }
            
            /* Titre */
            #<?php echo $unique_id; ?> .discord-stats-title {
                font-size: 18px;
                font-weight: bold;
                margin-bottom: 15px;
                text-align: <?php echo esc_attr($atts['align']); ?>;
            }
        </style>
        
        <?php if (!empty($options['custom_css'])) : ?>
        <style type="text/css">
            <?php echo $options['custom_css']; ?>
        </style>
        <?php endif; ?>
        
        <?php if ($refresh): ?>
        <script type="text/javascript">
        (function() {
            var container = document.getElementById('<?php echo $unique_id; ?>');
            var interval = parseInt(container.dataset.refresh) * 1000;
            var nonce = '<?php echo wp_create_nonce('refresh_discord_stats'); ?>';

            function updateStats() {
                fetch('<?php echo esc_url(admin_url('admin-ajax.php')); ?>?action=refresh_discord_stats&_ajax_nonce=' + nonce)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            var online = container.querySelector('.discord-online .discord-number');
                            var total = container.querySelector('.discord-total .discord-number');
                            
                            if (online) {
                                online.textContent = new Intl.NumberFormat('fr-FR').format(data.data.online);
                                online.style.transform = 'scale(1.2)';
                                setTimeout(() => online.style.transform = 'scale(1)', 300);
                            }
                            
                            if (total) {
                                total.textContent = new Intl.NumberFormat('fr-FR').format(data.data.total);
                                total.style.transform = 'scale(1.2)';
                                setTimeout(() => total.style.transform = 'scale(1)', 300);
                            }
                        }
                    });
            }
            
            if (interval > 0) {
                setInterval(updateStats, interval);
            }
        })();
        </script>
        <?php endif; ?>
        
        <?php
        return ob_get_clean();
    }
    
    // AJAX refresh
    public function ajax_refresh_stats() {
        check_ajax_referer('refresh_discord_stats');
        $options = get_option($this->option_name);
        
        // Ne pas rafraîchir si mode démo
        if (!empty($options['demo_mode'])) {
            wp_send_json_error('Mode démo actif');
            return;
        }
        
        delete_transient($this->cache_key);
        $stats = $this->get_discord_stats();
        
        if ($stats && empty($stats['is_demo'])) {
            wp_send_json_success($stats);
        } else {
            wp_send_json_error('Impossible de récupérer les stats');
        }
    }
    
    // Styles par défaut
    public function enqueue_styles() {
        wp_enqueue_style(
            'discord-bot-jlg',
            plugin_dir_url(__FILE__) . 'assets/css/discord-bot-jlg.css',
            array(),
            '1.0'
        );
    }

    // Styles pour l'admin
    public function enqueue_admin_styles() {
        wp_enqueue_style(
            'discord-bot-jlg-admin',
            plugin_dir_url(__FILE__) . 'assets/css/discord-bot-jlg-admin.css',
            array(),
            '1.0'
        );
    }
    
    // Enregistrer le widget
    public function register_widget() {
        register_widget('Discord_Stats_Widget');
    }
}

// Classe Widget
class Discord_Stats_Widget extends WP_Widget {
    
    public function __construct() {
        parent::__construct(
            'discord_stats_widget',
            'Discord Bot - JLG',
            array('description' => 'Affiche les statistiques de votre serveur Discord')
        );
    }
    
    public function widget($args, $instance) {
        echo $args['before_widget'];
        
        $options = get_option('discord_server_stats_options');
        $title = !empty($options['widget_title']) ? $options['widget_title'] : 'Discord Server';
        
        if (!empty($title)) {
            echo $args['before_title'] . apply_filters('widget_title', $title) . $args['after_title'];
        }
        
        echo do_shortcode('[discord_stats]');
        
        echo $args['after_widget'];
    }
    
    public function form($instance) {
        ?>
        <p>
            Configurez les options dans le menu principal <a href="<?php echo esc_url(admin_url('admin.php?page=discord-bot-jlg')); ?>">
            Discord Bot</a>
        </p>
        <?php
    }
}

// Initialiser le plugin
new DiscordServerStats();