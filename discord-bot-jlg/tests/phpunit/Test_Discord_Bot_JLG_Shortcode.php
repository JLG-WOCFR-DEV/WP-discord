<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/bootstrap.php';

class Test_Discord_Bot_JLG_Shortcode extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        $GLOBALS['wp_test_options'] = array();
        $GLOBALS['wp_test_registered_styles'] = array();
        $GLOBALS['wp_test_enqueued_styles']   = array();
        $GLOBALS['wp_test_inline_styles']     = array();
        $GLOBALS['wp_test_registered_scripts'] = array();
        $GLOBALS['wp_test_enqueued_scripts']   = array();
        $GLOBALS['wp_test_localized_scripts']  = array();
        $GLOBALS['wp_test_inline_scripts']     = array();

        $this->reset_shortcode_static_state();
    }

    private function reset_shortcode_static_state() {
        $reflection = new ReflectionClass(Discord_Bot_JLG_Shortcode::class);
        foreach (array('assets_registered', 'inline_css_added', 'footer_hook_added') as $property_name) {
            $property = $reflection->getProperty($property_name);
            $property->setAccessible(true);
            $property->setValue(null, false);
        }
    }

    private function get_shortcode_instance($custom_css = '') {
        $api = $this->getMockBuilder(Discord_Bot_JLG_API::class)
            ->disableOriginalConstructor()
            ->onlyMethods(array('get_plugin_options', 'get_stats', 'get_demo_stats'))
            ->getMock();

        $options = array(
            'show_online'   => true,
            'show_total'    => true,
            'widget_title'  => 'Mon serveur',
            'custom_css'    => $custom_css,
            'show_server_name' => true,
            'show_server_avatar' => true,
            'default_refresh_enabled' => true,
            'default_refresh_interval' => 45,
            'default_theme' => 'dark',
            'stat_bg_color' => '#123456',
            'stat_text_color' => '#f0f0f0',
            'accent_color' => '#654321',
            'accent_color_alt' => '#765432',
            'accent_text_color' => '#111111',
            'default_icon_online' => '🔥',
            'default_icon_total' => '🧑‍🤝‍🧑',
            'default_icon_presence' => '🛰️',
            'default_icon_approximate' => '📐',
            'default_icon_premium' => '💠',
            'default_label_online' => 'Actifs',
            'default_label_total' => 'Membres inscrits',
            'default_label_presence' => 'Répartition des membres',
            'default_label_presence_online' => 'Connectés',
            'default_label_presence_idle' => 'En pause',
            'default_label_presence_dnd' => 'Occupés',
            'default_label_presence_offline' => 'Déconnectés',
            'default_label_presence_streaming' => 'En stream',
            'default_label_presence_other' => 'Autres statuts',
            'default_label_approximate' => 'Total approx.',
            'default_label_premium' => 'Boosts actifs',
            'default_label_premium_singular' => 'Boost actif',
            'default_label_premium_plural' => 'Boosts actifs',
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
            'server_avatar_base_url' => 'https://cdn.discordapp.com/icons/123456789/abcdef.png',
            'server_avatar_url'    => 'https://cdn.discordapp.com/icons/123456789/abcdef.png?size=64',
        );

        $api->method('get_plugin_options')->willReturn($options);
        $api->method('get_stats')->willReturn($stats);
        $api->method('get_demo_stats')->willReturn($stats);

        return new Discord_Bot_JLG_Shortcode(DISCORD_BOT_JLG_OPTION_NAME, $api);
    }

    private function invoke_prepare_avatar_url(Discord_Bot_JLG_Shortcode $shortcode, $base_url, $fallback_url, $size) {
        $reflection = new ReflectionClass(Discord_Bot_JLG_Shortcode::class);
        $method     = $reflection->getMethod('prepare_avatar_url');
        $method->setAccessible(true);

        return $method->invoke($shortcode, $base_url, $fallback_url, $size);
    }

    public function test_render_shortcode_rejects_malicious_width() {
        $shortcode = $this->get_shortcode_instance();

        $html = $shortcode->render_shortcode(array(
            'width' => '100%;position:fixed',
        ));

        $this->assertStringNotContainsString('position:fixed', $html);
        $this->assertStringNotContainsString('width: 100%;position:fixed', $html);
        $this->assertStringNotContainsString('width:100%;position:fixed', $html);
    }

    public function test_render_shortcode_accepts_min_function_width() {
        $shortcode = $this->get_shortcode_instance();

        $html = $shortcode->render_shortcode(array(
            'width' => 'min(100%, 50vw)',
        ));

        $this->assertStringContainsString('style="', $html);
        $this->assertStringContainsString('width: 100%', $html);
        $this->assertStringContainsString('max-width: min(100%, 50vw)', $html);
    }

    public function test_render_shortcode_accepts_max_function_width() {
        $shortcode = $this->get_shortcode_instance();

        $html = $shortcode->render_shortcode(array(
            'width' => 'max(300px, var(--discord-width))',
        ));

        $this->assertStringContainsString('style="', $html);
        $this->assertStringContainsString('width: 100%', $html);
        $this->assertStringContainsString('max-width: max(300px, var(--discord-width))', $html);
    }

    public function test_render_shortcode_accepts_clamp_function_width() {
        $shortcode = $this->get_shortcode_instance();

        $html = $shortcode->render_shortcode(array(
            'width' => 'clamp(200px, 50%, var(--max-width))',
        ));

        $this->assertStringContainsString('style="', $html);
        $this->assertStringContainsString('width: 100%', $html);
        $this->assertStringContainsString('max-width: clamp(200px, 50%, var(--max-width))', $html);
    }

    public function test_render_shortcode_accepts_nested_calc_inside_width_functions() {
        $shortcode = $this->get_shortcode_instance();

        $html = $shortcode->render_shortcode(array(
            'width' => 'clamp(240px, calc(50vw + 10px), 720px)',
        ));

        $this->assertStringContainsString('style="', $html);
        $this->assertStringContainsString('width: 100%', $html);
        $this->assertStringContainsString('max-width: clamp(240px, calc(50vw + 10px), 720px)', $html);
    }

    public function test_render_shortcode_adds_max_width_and_fluid_width() {
        $shortcode = $this->get_shortcode_instance();

        $html = $shortcode->render_shortcode(array(
            'width' => '600px',
        ));

        $this->assertStringContainsString('style="', $html);
        $this->assertStringContainsString('width: 100%', $html);
        $this->assertStringContainsString('max-width: 600px', $html);
        $this->assertStringContainsString('width: 100%; max-width: 600px', $html);
    }

    public function test_enqueue_assets_preserves_media_query_css() {
        $custom_css = "@media (min-width: 600px) {\n  .wrapper > .item { color: red; }\n}";
        $shortcode = $this->get_shortcode_instance($custom_css);

        $shortcode->render_shortcode(array());

        $this->assertArrayHasKey('discord-bot-jlg-inline', $GLOBALS['wp_test_inline_styles']);
        $this->assertNotEmpty($GLOBALS['wp_test_inline_styles']['discord-bot-jlg-inline']);

        $injected_css = end($GLOBALS['wp_test_inline_styles']['discord-bot-jlg-inline']);
        $this->assertSame(discord_bot_jlg_sanitize_custom_css($custom_css), $injected_css);
    }

    public function test_enqueue_assets_strips_script_payload_before_injection() {
        $custom_css = "body { color: red; }\n<script>alert('hack');</script>";
        $shortcode  = $this->get_shortcode_instance($custom_css);

        $shortcode->render_shortcode(array());

        $this->assertArrayHasKey('discord-bot-jlg-inline', $GLOBALS['wp_test_inline_styles']);
        $this->assertNotEmpty($GLOBALS['wp_test_inline_styles']['discord-bot-jlg-inline']);

        $injected_css = end($GLOBALS['wp_test_inline_styles']['discord-bot-jlg-inline']);

        $this->assertSame(discord_bot_jlg_sanitize_custom_css($custom_css), $injected_css);
        $this->assertStringNotContainsString('<script', $injected_css);
        $this->assertStringNotContainsString('</', $injected_css);
    }

    public function test_register_assets_does_not_force_legacy_polyfill_dependencies() {
        $shortcode = $this->get_shortcode_instance();

        $shortcode->render_shortcode(array());

        $this->assertArrayHasKey('discord-bot-jlg-frontend', $GLOBALS['wp_test_registered_scripts']);

        $script = $GLOBALS['wp_test_registered_scripts']['discord-bot-jlg-frontend'];

        $this->assertIsArray($script['deps']);
        $this->assertNotContains('wp-polyfill', $script['deps']);
        $this->assertNotContains('wp-api-fetch', $script['deps']);
        $this->assertSame(array(), $script['deps']);
    }

    public function test_render_shortcode_inherits_defaults_from_options() {
        $shortcode = $this->get_shortcode_instance();

        $html = $shortcode->render_shortcode(array());

        $this->assertStringContainsString('discord-theme-dark', $html);
        $this->assertStringContainsString('data-refresh="45"', $html);
        $this->assertStringContainsString('data-show-server-name="true"', $html);
        $this->assertStringContainsString('data-show-server-avatar="true"', $html);
        $this->assertStringContainsString('data-server-name="Test Guild"', $html);
        $this->assertStringContainsString('data-server-avatar-url="https://cdn.discordapp.com/icons/123456789/abcdef.png?size=128"', $html);
        $this->assertStringContainsString('--discord-surface-background: #123456', $html);
        $this->assertStringContainsString('--discord-accent: #654321', $html);
        $this->assertStringContainsString('--discord-accent-secondary: #765432', $html);
        $this->assertStringContainsString('--discord-accent-contrast: #111111', $html);
        $this->assertStringContainsString('data-label-online="Actifs"', $html);
        $this->assertStringContainsString('discord-icon">🔥<', $html);
        $this->assertStringContainsString('data-label-total="Membres inscrits"', $html);
        $this->assertStringContainsString('data-label-presence="Répartition des membres"', $html);
        $this->assertStringContainsString('data-label-other="Autres statuts"', $html);
        $this->assertStringContainsString('data-label-premium="Boosts actifs"', $html);
    }

    public function test_render_shortcode_includes_custom_colors() {
        $shortcode = $this->get_shortcode_instance();

        $html = $shortcode->render_shortcode(array(
            'stat_bg_color'     => '#abcdef',
            'stat_text_color'   => 'rgb(10, 20, 30)',
            'accent_color'      => '#ff00aa',
            'accent_text_color' => '#0f0f0f',
        ));

        $this->assertStringContainsString('--discord-surface-background: #abcdef', $html);
        $this->assertStringContainsString('--discord-surface-text: rgb(10, 20, 30)', $html);
        $this->assertStringContainsString('--discord-accent: #ff00aa', $html);
        $this->assertStringContainsString('--discord-accent-secondary: #ff00aa', $html);
        $this->assertStringContainsString('--discord-accent-contrast: #0f0f0f', $html);
    }

    public function test_prepare_avatar_url_preserves_fragment_and_nested_query_arguments() {
        $shortcode = $this->get_shortcode_instance();

        $base_url = 'https://cdn.discordapp.com/icons/123456789/abcdef.png?size=128&foo=bar&meta[color]=red#profile';
        $result   = $this->invoke_prepare_avatar_url($shortcode, $base_url, '', 513);

        $this->assertStringEndsWith('#profile', $result);

        $parts = parse_url($result);
        $this->assertIsArray($parts);
        $this->assertSame('profile', $parts['fragment']);
        $this->assertSame('/icons/123456789/abcdef.png', $parts['path']);

        $query_args = array();
        parse_str($parts['query'], $query_args);

        $this->assertSame(
            array(
                'foo'  => 'bar',
                'meta' => array('color' => 'red'),
                'size' => '1024',
            ),
            $query_args
        );
    }

    public function test_prepare_avatar_url_uses_fallback_url_when_base_is_empty() {
        $shortcode = $this->get_shortcode_instance();

        $fallback_url = 'https://cdn.discordapp.com/embed/avatars/0.png?size=32&ref=widget#fallback';
        $result       = $this->invoke_prepare_avatar_url($shortcode, '', $fallback_url, 20);

        $parts = parse_url($result);
        $this->assertIsArray($parts);
        $this->assertSame('fallback', $parts['fragment']);
        $this->assertSame('/embed/avatars/0.png', $parts['path']);

        $query_args = array();
        parse_str($parts['query'], $query_args);

        $this->assertSame(
            array(
                'ref'  => 'widget',
                'size' => '32',
            ),
            $query_args
        );
    }

    public function test_frontend_script_has_no_forced_polyfill_dependencies() {
        $shortcode = $this->get_shortcode_instance();

        $shortcode->render_shortcode(array());

        $this->assertArrayHasKey('discord-bot-jlg-frontend', $GLOBALS['wp_test_registered_scripts']);

        $registered_script = $GLOBALS['wp_test_registered_scripts']['discord-bot-jlg-frontend'];

        $this->assertIsArray($registered_script['deps']);
        $this->assertSame(array(), $registered_script['deps']);
    }
}
