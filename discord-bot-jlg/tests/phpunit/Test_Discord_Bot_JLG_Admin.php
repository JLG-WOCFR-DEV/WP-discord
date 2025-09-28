<?php

require_once __DIR__ . '/includes/bootstrap.php';

/**
 * @group discord-bot-jlg
 */
class Test_Discord_Bot_JLG_Admin extends WP_UnitTestCase {

    /**
     * @var Discord_Bot_JLG_Admin
     */
    protected $admin;

    /**
     * @var Discord_Bot_JLG_API
     */
    protected $api;

    /**
     * @var array
     */
    protected $saved_options;

    public function setUp(): void {
        parent::setUp();

        $this->saved_options = array(
            'server_id'      => '424242424242424242',
            'bot_token'      => 'stored-token',
            'demo_mode'      => 1,
            'show_online'    => 1,
            'show_total'     => 1,
            'widget_title'   => 'Existing title',
            'cache_duration' => 450,
            'custom_css'     => '.existing { color: blue; }',
            'trusted_proxy_ips' => "198.51.100.5\n2001:db8::5",
        );

        update_option(DISCORD_BOT_JLG_OPTION_NAME, $this->saved_options);

        $this->api = new Discord_Bot_JLG_API(
            DISCORD_BOT_JLG_OPTION_NAME,
            DISCORD_BOT_JLG_CACHE_KEY,
            DISCORD_BOT_JLG_DEFAULT_CACHE_DURATION
        );

        $this->admin = new Discord_Bot_JLG_Admin(DISCORD_BOT_JLG_OPTION_NAME, $this->api);
    }

    protected function tearDown(): void {
        delete_option(DISCORD_BOT_JLG_OPTION_NAME);

        parent::tearDown();
    }

    public function sanitize_options_data_provider(): array {
        $sanitized_css = sanitize_textarea_field("body { color: red; }\n<script>alert('test');</script>");

        return array(
            'invalid-server-id' => array(
                array(
                    'server_id'    => 'abc123',
                    'bot_token'    => ' new token ',
                    'demo_mode'    => '1',
                    'show_online'  => '1',
                    'show_total'   => '',
                    'widget_title' => ' <strong>Stats</strong> ',
                    'cache_duration' => '45',
                    'custom_css'   => "body { color: red; }\n<script>alert('test');</script>",
                    'trusted_proxy_ips' => "198.51.100.10\ninvalid\n2001:db8::1",
                ),
                array(
                    'server_id'    => '',
                    'bot_token'    => sanitize_text_field(' new token '),
                    'demo_mode'    => 1,
                    'show_online'  => 1,
                    'show_total'   => 0,
                    'widget_title' => sanitize_text_field(' <strong>Stats</strong> '),
                    'cache_duration' => 45,
                    'custom_css'   => $sanitized_css,
                    'trusted_proxy_ips' => "198.51.100.10\n2001:db8::1",
                ),
            ),
            'valid-server-id-below-min-cache' => array(
                array(
                    'server_id'      => '123456789012345678',
                    'cache_duration' => '5',
                    'demo_mode'      => '',
                    'show_online'    => 'yes',
                    'show_total'     => 'yes',
                ),
                array(
                    'server_id'      => '123456789012345678',
                    'cache_duration' => Discord_Bot_JLG_API::MIN_PUBLIC_REFRESH_INTERVAL,
                    'demo_mode'      => 0,
                    'show_online'    => 1,
                    'show_total'     => 1,
                ),
            ),
            'cache-duration-above-max' => array(
                array(
                    'cache_duration' => 7200,
                ),
                array(
                    'cache_duration' => 3600,
                ),
            ),
            'empty-cache-duration-fallback' => array(
                array(
                    'cache_duration' => '',
                ),
                array(
                    'cache_duration' => 450,
                ),
            ),
        );
    }

    /**
     * @dataProvider sanitize_options_data_provider
     */
    public function test_sanitize_options(array $input, array $expected_overrides) {
        $result = $this->admin->sanitize_options($input);
        $expected = array_merge($this->get_expected_defaults(), $expected_overrides);

        $this->assertSame($expected, $result);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_sanitize_options_preserves_bot_token_when_constant_defined() {
        define('DISCORD_BOT_JLG_TOKEN', 'constant-token');

        $input = array(
            'bot_token' => '',
        );

        $result = $this->admin->sanitize_options($input);
        $expected = $this->get_expected_defaults();

        $this->assertSame($expected['bot_token'], $result['bot_token']);
        $this->assertSame($expected, $result);
    }

    public function test_sanitize_options_clears_stored_bot_token_when_input_empty() {
        $input = array(
            'bot_token' => '',
        );

        $result   = $this->admin->sanitize_options($input);
        $expected = $this->get_expected_defaults();
        $expected['bot_token'] = '';

        $this->assertSame($expected, $result);
    }

    private function get_expected_defaults(): array {
        return array(
            'server_id'      => '',
            'bot_token'      => $this->saved_options['bot_token'],
            'demo_mode'      => 0,
            'show_online'    => 0,
            'show_total'     => 0,
            'widget_title'   => '',
            'cache_duration' => max(
                Discord_Bot_JLG_API::MIN_PUBLIC_REFRESH_INTERVAL,
                min(3600, (int) $this->saved_options['cache_duration'])
            ),
            'custom_css'     => '',
            'trusted_proxy_ips' => $this->saved_options['trusted_proxy_ips'],
        );
    }
}
