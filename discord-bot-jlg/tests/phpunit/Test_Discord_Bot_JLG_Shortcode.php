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
            'show_invite_button' => true,
            'invite_button_label' => 'Rejoindre',
        );

        $stats = array(
            'online'               => 12,
            'total'                => 42,
            'has_total'            => true,
            'total_is_approximate' => false,
            'stale'                => false,
            'fallback_demo'        => false,
            'is_demo'              => false,
            'instant_invite'       => 'https://discord.gg/example',
            'invite_label'         => 'Rejoindre le serveur',
        );

        $api->method('get_plugin_options')->willReturn($options);
        $api->method('get_stats')->willReturn($stats);
        $api->method('get_demo_stats')->willReturn($stats);

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

    public function test_render_shortcode_outputs_invite_button_when_enabled() {
        $shortcode = $this->get_shortcode_instance();

        $html = $shortcode->render_shortcode(array());

        $this->assertStringContainsString('<a class="discord-invite-button"', $html);
        $this->assertStringContainsString('https://discord.gg/example', $html);
    }

    public function test_render_shortcode_hides_invite_button_when_disabled() {
        $shortcode = $this->get_shortcode_instance();

        $html = $shortcode->render_shortcode(array(
            'show_invite_button' => 'false',
        ));

        $this->assertStringNotContainsString('<a class="discord-invite-button"', $html);
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
}
