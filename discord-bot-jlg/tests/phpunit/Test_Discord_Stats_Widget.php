<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/bootstrap.php';

class Test_Discord_Stats_Widget extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        $GLOBALS['discord_bot_jlg_last_shortcode'] = null;
        $GLOBALS['wp_test_options'] = array();
    }

    public function test_update_accepts_minimal_theme() {
        $widget = new Discord_Stats_Widget();

        $new_instance = array(
            'theme' => 'minimal',
        );

        $updated = $widget->update($new_instance, array());

        $this->assertSame('minimal', $updated['theme']);
    }

    public function test_widget_shortcode_includes_minimal_theme() {
        $widget = new Discord_Stats_Widget();

        $args = array(
            'before_widget' => '',
            'after_widget'  => '',
            'before_title'  => '',
            'after_title'   => '',
        );

        $instance = array(
            'theme'  => 'minimal',
            'layout' => 'vertical',
        );

        ob_start();
        $widget->widget($args, $instance);
        ob_end_clean();

        $this->assertNotNull($GLOBALS['discord_bot_jlg_last_shortcode']);
        $this->assertStringContainsString('theme="minimal"', $GLOBALS['discord_bot_jlg_last_shortcode']);
    }

    public function test_update_sanitizes_connection_overrides() {
        $widget = new Discord_Stats_Widget();

        $new_instance = array(
            'profile_key'        => 'Profil Personnel',
            'server_id_override' => 'abc123456',
            'bot_token_override' => " token\n",
        );

        $updated = $widget->update($new_instance, array());

        $this->assertSame('profil-personnel', $updated['profile_key']);
        $this->assertSame('123456', $updated['server_id_override']);
        $this->assertSame('token', $updated['bot_token_override']);
    }

    public function test_update_handles_metric_toggles() {
        $widget = new Discord_Stats_Widget();

        $new_instance = array(
            'show_presence_breakdown'       => '1',
            'show_approximate_member_count' => 'yes',
            'show_premium_subscriptions'    => 'on',
        );

        $updated = $widget->update($new_instance, array());

        $this->assertSame(1, $updated['show_presence_breakdown']);
        $this->assertSame(1, $updated['show_approximate_member_count']);
        $this->assertSame(1, $updated['show_premium_subscriptions']);
    }

    public function test_widget_shortcode_includes_connection_overrides() {
        $widget = new Discord_Stats_Widget();

        $args = array(
            'before_widget' => '',
            'after_widget'  => '',
            'before_title'  => '',
            'after_title'   => '',
        );

        $instance = array(
            'profile_key'        => 'profil-special',
            'server_id_override' => '987654321',
            'bot_token_override' => 'widget-token',
        );

        ob_start();
        $widget->widget($args, $instance);
        ob_end_clean();

        $this->assertNotNull($GLOBALS['discord_bot_jlg_last_shortcode']);
        $this->assertStringContainsString('profile="profil-special"', $GLOBALS['discord_bot_jlg_last_shortcode']);
        $this->assertStringContainsString('server_id="987654321"', $GLOBALS['discord_bot_jlg_last_shortcode']);
        $this->assertStringContainsString('bot_token="widget-token"', $GLOBALS['discord_bot_jlg_last_shortcode']);
    }

    public function test_widget_shortcode_includes_metric_toggles() {
        $widget = new Discord_Stats_Widget();

        $args = array(
            'before_widget' => '',
            'after_widget'  => '',
            'before_title'  => '',
            'after_title'   => '',
        );

        $instance = array(
            'show_presence_breakdown'       => 1,
            'show_approximate_member_count' => 1,
            'show_premium_subscriptions'    => 1,
        );

        ob_start();
        $widget->widget($args, $instance);
        ob_end_clean();

        $this->assertNotNull($GLOBALS['discord_bot_jlg_last_shortcode']);
        $this->assertStringContainsString('show_presence_breakdown="true"', $GLOBALS['discord_bot_jlg_last_shortcode']);
        $this->assertStringContainsString('show_approximate_member_count="true"', $GLOBALS['discord_bot_jlg_last_shortcode']);
        $this->assertStringContainsString('show_premium_subscriptions="true"', $GLOBALS['discord_bot_jlg_last_shortcode']);
    }
}
