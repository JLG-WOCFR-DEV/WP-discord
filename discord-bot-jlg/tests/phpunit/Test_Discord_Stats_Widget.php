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
}
