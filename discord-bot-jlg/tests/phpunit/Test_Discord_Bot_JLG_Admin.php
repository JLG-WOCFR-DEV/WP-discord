<?php

require_once __DIR__ . '/includes/bootstrap.php';

class Discord_Bot_JLG_Admin_Success_Http_Client extends Discord_Bot_JLG_Http_Client {
    public function get($url, array $args = array(), $context = '') {
        if ('widget' !== $context) {
            return new WP_Error('unexpected_context', 'Unexpected context: ' . $context);
        }

        $payload = array(
            'presence_count' => 12,
            'name'           => 'Admin Test Guild',
            'members'        => array(
                array('id' => 1),
                array('id' => 2),
                array('id' => 3),
            ),
        );

        return array(
            'response' => array(
                'code'    => 200,
                'message' => 'OK',
            ),
            'body'    => wp_json_encode($payload),
            'headers' => array(),
        );
    }
}

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
            'default_theme'  => 'dark',
            'default_refresh_interval' => 120,
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
        $malicious_css     = "body { color: red; }\n<script>alert('test');</script>";
        $sanitized_css     = discord_bot_jlg_sanitize_custom_css($malicious_css);
        $media_query_css   = "@media (min-width: 600px) {\n  .wrapper > .item { color: red; }\n}\n";
        $sanitized_media   = discord_bot_jlg_sanitize_custom_css($media_query_css);

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
                    'custom_css'   => $malicious_css,
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
            'custom-css-media-query-preserved' => array(
                array(
                    'custom_css' => $media_query_css,
                ),
                array(
                    'custom_css' => $sanitized_media,
                ),
            ),
            'custom-css-script-removed' => array(
                array(
                    'custom_css' => $malicious_css,
                ),
                array(
                    'custom_css' => $sanitized_css,
                ),
            ),
            'server-header-checkboxes' => array(
                array(
                    'show_server_name'       => 'yes',
                    'show_server_avatar'     => '1',
                    'default_refresh_enabled'=> 'on',
                ),
                array(
                    'show_server_name'       => 1,
                    'show_server_avatar'     => 1,
                    'default_refresh_enabled'=> 1,
                ),
            ),
            'default-theme-valid' => array(
                array(
                    'default_theme' => 'light',
                ),
                array(
                    'default_theme' => 'light',
                ),
            ),
            'default-theme-empty' => array(
                array(
                    'default_theme' => '',
                ),
                array(
                    'default_theme' => 'dark',
                ),
            ),
            'default-theme-invalid' => array(
                array(
                    'default_theme' => 'neon',
                ),
                array(
                    'default_theme' => 'discord',
                ),
            ),
            'refresh-interval-below-min' => array(
                array(
                    'default_refresh_interval' => '5',
                ),
                array(
                    'default_refresh_interval' => Discord_Bot_JLG_API::MIN_PUBLIC_REFRESH_INTERVAL,
                ),
            ),
            'refresh-interval-above-max' => array(
                array(
                    'default_refresh_interval' => 7200,
                ),
                array(
                    'default_refresh_interval' => 3600,
                ),
            ),
            'refresh-interval-empty-fallback' => array(
                array(
                    'default_refresh_interval' => '',
                ),
                array(
                    'default_refresh_interval' => 120,
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

        $result_token   = isset($result['bot_token']) ? $result['bot_token'] : '';
        $expected_token = $this->saved_options['bot_token'];

        if (array_key_exists('bot_token', $expected_overrides)) {
            $expected_token = $expected_overrides['bot_token'];
        }

        unset($expected['bot_token'], $result['bot_token']);

        $this->assertSame($expected, $result);

        if ('' === $expected_token) {
            $this->assertSame('', $result_token);
        } else {
            $this->assertTrue(discord_bot_jlg_is_encrypted_secret($result_token));
            $decrypted = discord_bot_jlg_decrypt_secret($result_token);

            $this->assertFalse(is_wp_error($decrypted));
            $this->assertSame($expected_token, $decrypted);
        }
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

        unset($expected['bot_token'], $result['bot_token']);

        $this->assertSame($expected, $result);
    }

    public function test_sanitize_options_preserves_bot_token_when_updating_other_fields() {
        $input = array(
            'widget_title' => 'Updated title',
            'bot_token'    => '',
        );

        $result   = $this->admin->sanitize_options($input);
        $expected = $this->get_expected_defaults();
        $expected['widget_title'] = sanitize_text_field('Updated title');

        $this->assertTrue(discord_bot_jlg_is_encrypted_secret($result['bot_token']));

        $decrypted = discord_bot_jlg_decrypt_secret($result['bot_token']);

        $this->assertFalse(is_wp_error($decrypted));
        $this->assertSame($this->saved_options['bot_token'], $decrypted);

        unset($expected['bot_token'], $result['bot_token']);

        $this->assertSame($expected, $result);
    }

    public function test_sanitize_options_removes_bot_token_when_delete_requested() {
        $input = array(
            'bot_token_delete' => '1',
        );

        $result   = $this->admin->sanitize_options($input);
        $expected = $this->get_expected_defaults();
        $expected['bot_token'] = '';

        $this->assertSame('', $result['bot_token']);

        unset($expected['bot_token'], $result['bot_token']);

        $this->assertSame($expected, $result);
    }

    public function test_fallback_notice_cleared_after_successful_refresh() {
        $options = $this->saved_options;
        $options['demo_mode'] = 0;
        $options['bot_token'] = '';

        update_option(DISCORD_BOT_JLG_OPTION_NAME, $options);

        $http_client = new Discord_Bot_JLG_Admin_Success_Http_Client();
        $this->api   = new Discord_Bot_JLG_API(
            DISCORD_BOT_JLG_OPTION_NAME,
            DISCORD_BOT_JLG_CACHE_KEY,
            DISCORD_BOT_JLG_DEFAULT_CACHE_DURATION,
            $http_client
        );
        $this->admin = new Discord_Bot_JLG_Admin(DISCORD_BOT_JLG_OPTION_NAME, $this->api);

        $fallback_reason = 'Connexion à Discord interrompue';
        update_option(
            Discord_Bot_JLG_API::LAST_FALLBACK_OPTION,
            array(
                'timestamp' => 1700000000,
                'reason'    => $fallback_reason,
            )
        );

        $retry_key = DISCORD_BOT_JLG_CACHE_KEY . Discord_Bot_JLG_API::FALLBACK_RETRY_SUFFIX;
        set_transient($retry_key, time() + 120, 120);

        ob_start();
        $this->admin->test_discord_connection();
        $first_output = ob_get_clean();

        $this->assertStringContainsString('notice-warning', $first_output);
        $this->assertStringContainsString($fallback_reason, $first_output);
        $this->assertFalse(get_option(Discord_Bot_JLG_API::LAST_FALLBACK_OPTION));

        ob_start();
        $this->admin->test_discord_connection();
        $second_output = ob_get_clean();

        $this->assertStringNotContainsString('notice-warning', $second_output);
    }

    public function test_test_discord_connection_uses_date_i18n_when_wp_date_unavailable() {
        $options = $this->saved_options;
        $options['demo_mode'] = 0;
        update_option(DISCORD_BOT_JLG_OPTION_NAME, $options);

        update_option('date_format', 'd/m/Y');
        update_option('time_format', 'H:i');

        $fallback_timestamp = 1700000000;
        $next_retry         = 1700000600;
        $fallback_reason    = 'Test fallback';

        update_option(
            Discord_Bot_JLG_API::LAST_FALLBACK_OPTION,
            array(
                'timestamp'  => $fallback_timestamp,
                'reason'     => $fallback_reason,
                'next_retry' => $next_retry,
            )
        );

        $GLOBALS['discord_bot_jlg_disable_wp_date'] = true;

        $captured_calls = array();
        $callback       = function($formatted, $format, $timestamp, $gmt) use (&$captured_calls) {
            $captured_calls[] = array(
                'formatted' => $formatted,
                'format'    => $format,
                'timestamp' => $timestamp,
                'gmt'       => $gmt,
            );

            return $formatted;
        };

        add_filter('date_i18n', $callback, 10, 4);

        ob_start();
        try {
            $this->admin->test_discord_connection();
        } finally {
            $output = ob_get_clean();
            remove_filter('date_i18n', $callback, 10);
            unset($GLOBALS['discord_bot_jlg_disable_wp_date']);
        }

        $this->assertStringContainsString($fallback_reason, $output);

        $expected_formatted_time = gmdate('d/m/Y H:i', $fallback_timestamp);
        $expected_retry_time     = gmdate('d/m/Y H:i', $next_retry);

        $this->assertStringContainsString($expected_formatted_time, $output);
        $this->assertStringContainsString($expected_retry_time, $output);

        $this->assertNotEmpty($captured_calls);
        $last_call = end($captured_calls);
        $this->assertSame('d/m/Y H:i', $last_call['format']);
        $this->assertSame($next_retry, $last_call['timestamp']);

        delete_option(Discord_Bot_JLG_API::LAST_FALLBACK_OPTION);
    }

    private function get_expected_defaults(): array {
        return array(
            'server_id'      => '',
            'bot_token'      => $this->saved_options['bot_token'],
            'demo_mode'      => 0,
            'show_online'    => 0,
            'show_total'     => 0,
            'show_server_name'   => 0,
            'show_server_avatar' => 0,
            'default_refresh_enabled' => 0,
            'default_theme'   => 'dark',
            'widget_title'   => '',
            'cache_duration' => max(
                Discord_Bot_JLG_API::MIN_PUBLIC_REFRESH_INTERVAL,
                min(3600, (int) $this->saved_options['cache_duration'])
            ),
            'custom_css'     => '',
            'default_refresh_interval' => max(
                Discord_Bot_JLG_API::MIN_PUBLIC_REFRESH_INTERVAL,
                min(3600, (int) $this->saved_options['default_refresh_interval'])
            ),
        );
    }
}
