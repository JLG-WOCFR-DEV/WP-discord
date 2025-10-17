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
        );

        $updated = $widget->update($new_instance, array());

        $this->assertSame('profil-personnel', $updated['profile_key']);
        $this->assertSame('123456', $updated['server_id_override']);
    }

    public function test_update_normalizes_profile_key_with_accents() {
        $widget = new Discord_Stats_Widget();

        $new_instance = array(
            'profile_key' => 'Profil Élève',
        );

        $updated = $widget->update($new_instance, array());

        $this->assertSame('profil-eleve', $updated['profile_key']);
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
            'profile_key'        => 'Profil Personnel',
            'server_id_override' => '987654321',
        );

        ob_start();
        $widget->widget($args, $instance);
        ob_end_clean();

        $this->assertNotNull($GLOBALS['discord_bot_jlg_last_shortcode']);
        $this->assertStringContainsString('profile="profil-personnel"', $GLOBALS['discord_bot_jlg_last_shortcode']);
        $this->assertStringContainsString('server_id="987654321"', $GLOBALS['discord_bot_jlg_last_shortcode']);
        $this->assertStringNotContainsString('bot_token=', $GLOBALS['discord_bot_jlg_last_shortcode']);
    }

    /**
     * @dataProvider profile_key_source_provider
     */
    public function test_widget_shortcode_uses_hyphenated_profile_key($raw_profile_key) {
        $widget = new Discord_Stats_Widget();

        $args = array(
            'before_widget' => '',
            'after_widget'  => '',
            'before_title'  => '',
            'after_title'   => '',
        );

        $instance = array(
            'profile_key' => $raw_profile_key,
        );

        ob_start();
        $widget->widget($args, $instance);
        ob_end_clean();

        $this->assertNotNull($GLOBALS['discord_bot_jlg_last_shortcode']);
        $this->assertStringContainsString('profile="profil-personnel"', $GLOBALS['discord_bot_jlg_last_shortcode']);
    }

    public function profile_key_source_provider() {
        return array(
            'stored_settings' => array('profil-personnel'),
            'ad_hoc_instance' => array('Profil Personnel'),
        );
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
