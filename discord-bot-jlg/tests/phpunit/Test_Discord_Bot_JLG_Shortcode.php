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

    private function get_shortcode_instance($custom_css = '', callable $configure = null) {
        $api = $this->getMockBuilder(Discord_Bot_JLG_API::class)
            ->disableOriginalConstructor()
            ->onlyMethods(array('get_plugin_options', 'get_stats', 'get_demo_stats', 'register_temporary_token_override'))
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
        $api->method('register_temporary_token_override')->willReturn('');

        if (null !== $configure) {
            $configure($api);
        }

        return new Discord_Bot_JLG_Shortcode(DISCORD_BOT_JLG_OPTION_NAME, $api);
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

    public function test_render_shortcode_exposes_token_reference_instead_of_value() {
        $expected_key = 'securetokenref';

        $shortcode = $this->get_shortcode_instance('', function ($api) use ($expected_key) {
            $api->expects($this->once())
                ->method('register_temporary_token_override')
                ->with('override-token')
                ->willReturn($expected_key);
        });

        $html = $shortcode->render_shortcode(array(
            'bot_token' => ' override-token ',
        ));

        $this->assertStringContainsString('data-token-key="' . $expected_key . '"', $html);
        $this->assertStringNotContainsString('data-bot-token-override', $html);
        $this->assertStringNotContainsString('override-token', $html);
    }
}
