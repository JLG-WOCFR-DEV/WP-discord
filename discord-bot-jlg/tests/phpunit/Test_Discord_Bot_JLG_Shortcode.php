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

    public function test_render_shortcode_accepts_calc_with_nested_functions() {
        $shortcode = $this->get_shortcode_instance();

        $width = 'calc(min(100%, 320px) - 2rem)';

        $html = $shortcode->render_shortcode(array(
            'width' => $width,
        ));

        $this->assertStringContainsString('width: ' . $width, $html);
    }
}
