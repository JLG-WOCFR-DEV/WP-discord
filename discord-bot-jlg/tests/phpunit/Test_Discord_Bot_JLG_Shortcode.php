<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/bootstrap.php';

class Test_Discord_Bot_JLG_Shortcode extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        $GLOBALS['wp_test_options'] = array();
    }

    private function get_shortcode_instance() {
        $api = new class {
            public function get_plugin_options() {
                return array(
                    'show_online'   => true,
                    'show_total'    => true,
                    'widget_title'  => 'Mon serveur',
                    'custom_css'    => '',
                );
            }

            public function get_stats() {
                return array(
                    'online'             => 12,
                    'total'              => 42,
                    'has_total'          => true,
                    'total_is_approximate' => false,
                    'stale'              => false,
                    'fallback_demo'      => false,
                    'is_demo'            => false,
                    'instant_invite'     => 'https://discord.gg/example',
                );
            }

            public function get_demo_stats() {
                return $this->get_stats();
            }
        };

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

        $html = $shortcode->render_shortcode(array(
            'show_invite_button' => 'true',
            'invite_label'       => '<strong>Rejoindre</strong>',
        ));

        $this->assertStringContainsString('discord-invite-button', $html);
        $this->assertStringContainsString('role="button"', $html);
        $this->assertStringContainsString('href="https://discord.gg/example"', $html);
        $this->assertStringContainsString('>Rejoindre<', $html);
    }
}
