<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/bootstrap.php';

/**
 * Phase 2 — headers WP 7.1, charte wp-admin, JS iframe-safe.
 *
 * @group discord-bot-jlg
 */
class Test_Discord_Bot_JLG_Phase2 extends TestCase {

    /**
     * @var string
     */
    private $plugin_root;

    /**
     * @var array
     */
    private $saved_get;

    /**
     * @var array
     */
    private $saved_request;

    /**
     * @var array
     */
    private $saved_server;

    protected function setUp(): void {
        parent::setUp();

        $this->plugin_root = dirname(__DIR__, 2);
        $this->saved_get     = isset($_GET) ? $_GET : array();
        $this->saved_request = isset($_REQUEST) ? $_REQUEST : array();
        $this->saved_server  = isset($_SERVER) ? $_SERVER : array();

        $GLOBALS['wp_test_is_admin']        = false;
        $GLOBALS['wp_test_is_block_editor'] = false;
        $GLOBALS['wp_test_current_screen']  = null;
        $GLOBALS['wp_test_registered_styles']  = array();
        $GLOBALS['wp_test_enqueued_styles']    = array();
        $GLOBALS['wp_test_inline_styles']      = array();
        $GLOBALS['wp_test_registered_scripts'] = array();
        $GLOBALS['wp_test_enqueued_scripts']   = array();
        $GLOBALS['wp_test_localized_scripts']  = array();
        $GLOBALS['wp_test_inline_scripts']     = array();

        $_GET     = array();
        $_REQUEST = array();
        unset($_SERVER['REQUEST_URI']);

        $this->reset_shortcode_static_state();
    }

    protected function tearDown(): void {
        $_GET     = $this->saved_get;
        $_REQUEST = $this->saved_request;
        $_SERVER  = $this->saved_server;

        $GLOBALS['wp_test_is_admin']        = false;
        $GLOBALS['wp_test_is_block_editor'] = false;
        $GLOBALS['wp_test_current_screen']  = null;

        parent::tearDown();
    }

    private function reset_shortcode_static_state() {
        $reflection = new ReflectionClass(Discord_Bot_JLG_Shortcode::class);
        foreach (array('assets_registered', 'inline_css_added', 'footer_hook_added') as $property_name) {
            $property = $reflection->getProperty($property_name);
            $property->setAccessible(true);
            $property->setValue(null, false);
        }
    }

    private function get_shortcode_instance() {
        $api = $this->getMockBuilder(Discord_Bot_JLG_API::class)
            ->disableOriginalConstructor()
            ->onlyMethods(array('get_plugin_options', 'get_stats', 'get_demo_stats'))
            ->getMock();

        $options = array(
            'show_online'              => true,
            'show_total'               => true,
            'widget_title'             => 'Mon serveur',
            'custom_css'               => '',
            'show_server_name'         => true,
            'show_server_avatar'       => true,
            'show_presence_breakdown'  => false,
            'default_refresh_enabled'  => true,
            'default_refresh_interval' => 45,
            'default_theme'            => 'dark',
        );

        $stats = array(
            'online'               => 12,
            'total'                => 42,
            'has_total'            => true,
            'total_is_approximate' => false,
            'stale'                => false,
            'fallback_demo'        => false,
            'is_demo'              => false,
            'server_name'          => 'Test Guild',
            'server_avatar_url'    => '',
            'presence_count_by_status' => array(),
        );

        $api->method('get_plugin_options')->willReturn($options);
        $api->method('get_stats')->willReturn($stats);
        $api->method('get_demo_stats')->willReturn($stats);

        return new Discord_Bot_JLG_Shortcode(DISCORD_BOT_JLG_OPTION_NAME, $api);
    }

    public function test_plugin_header_declares_wp_71_and_php() {
        $header = file_get_contents($this->plugin_root . '/discord-bot-jlg.php');

        $this->assertNotFalse($header);
        $this->assertMatchesRegularExpression('/Requires at least:\s*5\.2/', $header);
        $this->assertMatchesRegularExpression('/Requires PHP:\s*7\.4/', $header);
        $this->assertMatchesRegularExpression('/Tested up to:\s*7\.1/', $header);
    }

    public function test_readme_txt_declares_wp_71_and_php() {
        $readme_path = $this->plugin_root . '/readme.txt';

        $this->assertFileExists($readme_path);

        $readme = file_get_contents($readme_path);

        $this->assertMatchesRegularExpression('/Requires at least:\s*5\.2/', $readme);
        $this->assertMatchesRegularExpression('/Requires PHP:\s*7\.4/', $readme);
        $this->assertMatchesRegularExpression('/Tested up to:\s*7\.1/', $readme);
        $this->assertMatchesRegularExpression('/Stable tag:\s*1\.0\.1/', $readme);
    }

    public function test_admin_markup_follows_wp_admin_charter() {
        $admin_php = file_get_contents($this->plugin_root . '/inc/class-discord-admin.php');

        $this->assertNotFalse($admin_php);
        $this->assertStringContainsString('class="wrap"', $admin_php);
        $this->assertStringContainsString('<h1>', $admin_php);
        $this->assertStringContainsString('nav-tab-wrapper', $admin_php);
        $this->assertStringContainsString('register_setting(', $admin_php);
        $this->assertStringContainsString('settings_fields(', $admin_php);
        $this->assertStringContainsString('class="form-table"', $admin_php);
        $this->assertStringContainsString('submit_button(', $admin_php);
        $this->assertStringContainsString('button-primary', $admin_php);
        $this->assertStringContainsString('notice notice-', $admin_php);
        $this->assertStringContainsString('notice notice-warning', $admin_php);
        $this->assertStringContainsString('notice notice-success', $admin_php);
        $this->assertStringContainsString('notice notice-error', $admin_php);
        $this->assertStringContainsString('notice notice-info', $admin_php);
    }

    public function test_admin_tabs_do_not_use_emoji_icons() {
        $admin_php = file_get_contents($this->plugin_root . '/inc/class-discord-admin.php');

        $this->assertStringNotContainsString('discord-bot-tab-icon', $admin_php);
        $this->assertDoesNotMatchRegularExpression("/'icon'\\s*=>\\s*'[^']*[🔌🎨⚙️🔑📊💡]/u", $admin_php);
    }

    public function test_admin_css_does_not_restyle_wp_chrome() {
        $admin_css = file_get_contents($this->plugin_root . '/assets/css/discord-bot-jlg-admin.css');

        $this->assertNotFalse($admin_css);
        $this->assertStringNotContainsString('#adminmenu', $admin_css);
        $this->assertStringNotContainsString('#wpbody', $admin_css);
        $this->assertStringNotContainsString('#wpcontent', $admin_css);
        $this->assertStringNotContainsString('body.wp-admin', $admin_css);
        $this->assertDoesNotMatchRegularExpression('/\\.button-primary\\s*\\{/', $admin_css);
        $this->assertDoesNotMatchRegularExpression('/\\.nav-tab\\s*\\{/', $admin_css);
        $this->assertDoesNotMatchRegularExpression('/\\.notice\\s*\\{/', $admin_css);
        $this->assertStringNotContainsString("wp_enqueue_style('wp-components')", file_get_contents($this->plugin_root . '/inc/class-discord-admin.php'));
    }

    public function test_preview_notice_uses_core_notice_class() {
        $admin_php = file_get_contents($this->plugin_root . '/inc/class-discord-admin.php');
        $admin_css = file_get_contents($this->plugin_root . '/assets/css/discord-bot-jlg-admin.css');

        $this->assertStringContainsString('notice notice-info', $admin_php);
        $this->assertStringNotContainsString('discord-preview-notice', $admin_php);
        $this->assertStringNotContainsString('.discord-preview-notice', $admin_css);
    }

    public function test_editor_preview_helpers_detect_canvas_and_block_renderer() {
        $this->assertFalse(discord_bot_jlg_is_block_editor_preview_context());
        $this->assertTrue(discord_bot_jlg_should_enqueue_frontend_script());

        $GLOBALS['wp_test_is_block_editor'] = true;
        $this->assertTrue(discord_bot_jlg_is_block_editor_preview_context());
        $this->assertFalse(discord_bot_jlg_should_enqueue_frontend_script());
        $GLOBALS['wp_test_is_block_editor'] = false;

        $_GET['canvas'] = 'edit';
        $this->assertTrue(discord_bot_jlg_is_block_editor_preview_context());
        unset($_GET['canvas']);

        $_REQUEST['context'] = 'edit';
        $this->assertTrue(discord_bot_jlg_is_block_editor_preview_context());
        unset($_REQUEST['context']);

        $_SERVER['REQUEST_URI'] = '/wp-json/wp/v2/block-renderer/discord-bot-jlg/discord-stats';
        $this->assertTrue(discord_bot_jlg_is_block_editor_preview_context());
        unset($_SERVER['REQUEST_URI']);

        $GLOBALS['wp_test_is_admin'] = true;
        $this->assertFalse(discord_bot_jlg_is_block_editor_preview_context());
        $this->assertFalse(discord_bot_jlg_should_enqueue_frontend_script());
        $GLOBALS['wp_test_is_admin'] = false;
    }

    public function test_shortcode_skips_frontend_js_in_editor_but_keeps_css() {
        $shortcode = $this->get_shortcode_instance();

        $html = $shortcode->render_shortcode(array());

        $this->assertArrayHasKey('discord-bot-jlg-frontend', $GLOBALS['wp_test_enqueued_scripts']);
        $this->assertArrayHasKey('discord-bot-jlg', $GLOBALS['wp_test_enqueued_styles']);
        $this->assertStringNotContainsString('data-discord-bot-editor="true"', $html);

        $this->reset_shortcode_static_state();
        $GLOBALS['wp_test_enqueued_scripts'] = array();
        $GLOBALS['wp_test_enqueued_styles']  = array();
        $GLOBALS['wp_test_registered_scripts'] = array();
        $GLOBALS['wp_test_registered_styles']  = array();

        $_SERVER['REQUEST_URI'] = '/wp-json/wp/v2/block-renderer/discord-bot-jlg/discord-stats';

        $shortcode = $this->get_shortcode_instance();
        $html      = $shortcode->render_shortcode(array());

        $this->assertArrayNotHasKey('discord-bot-jlg-frontend', $GLOBALS['wp_test_enqueued_scripts']);
        $this->assertArrayHasKey('discord-bot-jlg', $GLOBALS['wp_test_enqueued_styles']);
        $this->assertStringContainsString('data-discord-bot-editor="true"', $html);
    }

    public function test_editor_canvas_guard_is_hooked_and_admin_only() {
        $plugin_php = file_get_contents($this->plugin_root . '/discord-bot-jlg.php');

        $this->assertStringContainsString("add_action('enqueue_block_assets'", $plugin_php);
        $this->assertStringContainsString('discord_bot_jlg_enqueue_editor_canvas_guard', $plugin_php);

        $GLOBALS['wp_test_is_admin'] = false;
        $GLOBALS['wp_test_enqueued_scripts']   = array();
        $GLOBALS['wp_test_registered_scripts'] = array();
        $GLOBALS['wp_test_inline_scripts']     = array();

        discord_bot_jlg_enqueue_editor_canvas_guard();

        $this->assertArrayNotHasKey('discord-bot-jlg-editor-canvas-guard', $GLOBALS['wp_test_enqueued_scripts']);

        $GLOBALS['wp_test_is_admin'] = true;
        discord_bot_jlg_enqueue_editor_canvas_guard();

        $this->assertArrayHasKey('discord-bot-jlg-editor-canvas-guard', $GLOBALS['wp_test_enqueued_scripts']);
        $this->assertArrayHasKey('discord-bot-jlg-editor-canvas-guard', $GLOBALS['wp_test_inline_scripts']);
        $this->assertStringContainsString(
            'DISCORD_BOT_JLG_IS_EDITOR',
            $GLOBALS['wp_test_inline_scripts']['discord-bot-jlg-editor-canvas-guard'][0]['data']
        );
    }
}
