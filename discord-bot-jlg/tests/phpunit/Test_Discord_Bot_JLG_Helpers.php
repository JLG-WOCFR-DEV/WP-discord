<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/bootstrap.php';

/**
 * @group discord-bot-jlg
 */
class Test_Discord_Bot_JLG_Helpers extends TestCase {

    private const AUTH_KEY  = 'test-auth-key-0123456789abcdef0123456789abcdef';
    private const AUTH_SALT = 'test-auth-salt-fedcba9876543210fedcba9876543210';

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_encrypt_decrypt_round_trip_succeeds() {
        $this->define_auth_constants();

        $plaintext = 'discord-secret-token';

        $encrypted = discord_bot_jlg_encrypt_secret($plaintext);

        $this->assertFalse(is_wp_error($encrypted));
        $this->assertIsString($encrypted);
        $this->assertNotSame('', $encrypted);
        $this->assertTrue(discord_bot_jlg_is_encrypted_secret($encrypted));

        $decrypted = discord_bot_jlg_decrypt_secret($encrypted);

        $this->assertFalse(is_wp_error($decrypted));
        $this->assertSame($plaintext, $decrypted);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_encrypt_secret_generates_unique_ciphertext_each_time() {
        $this->define_auth_constants();

        $plaintext = 'discord-secret-token';

        $first = discord_bot_jlg_encrypt_secret($plaintext);
        $second = discord_bot_jlg_encrypt_secret($plaintext);

        $this->assertFalse(is_wp_error($first));
        $this->assertFalse(is_wp_error($second));
        $this->assertNotSame($first, $second);

        $decrypted_first = discord_bot_jlg_decrypt_secret($first);
        $decrypted_second = discord_bot_jlg_decrypt_secret($second);

        $this->assertFalse(is_wp_error($decrypted_first));
        $this->assertFalse(is_wp_error($decrypted_second));
        $this->assertSame($plaintext, $decrypted_first);
        $this->assertSame($plaintext, $decrypted_second);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_encrypt_secret_returns_error_when_constants_missing() {
        $result = discord_bot_jlg_encrypt_secret('token');

        $this->assertTrue(is_wp_error($result));
        $this->assertSame('discord_bot_jlg_encrypt_secret_missing_keys', $result->get_error_code());
        $this->assertSame(
            'Les constantes AUTH_KEY et AUTH_SALT sont requises pour chiffrer le token Discord.',
            $result->get_error_message()
        );
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_encrypt_secret_returns_error_when_constants_empty_even_if_filter_allows() {
        if (!defined('AUTH_KEY')) {
            define('AUTH_KEY', '');
        }

        if (!defined('AUTH_SALT')) {
            define('AUTH_SALT', '');
        }

        add_filter('discord_bot_jlg_has_auth_constants', '__return_true');

        $result = discord_bot_jlg_encrypt_secret('token');

        $this->assertTrue(is_wp_error($result));
        $this->assertSame('discord_bot_jlg_encrypt_secret_missing_keys', $result->get_error_code());
        $this->assertSame(
            'Les constantes AUTH_KEY et AUTH_SALT sont requises pour chiffrer le token Discord.',
            $result->get_error_message()
        );

        remove_all_filters('discord_bot_jlg_has_auth_constants');
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_encrypt_secret_returns_error_when_openssl_is_unavailable() {
        $this->define_auth_constants();

        add_filter('discord_bot_jlg_has_openssl_encrypt', function () {
            return false;
        });

        $result = discord_bot_jlg_encrypt_secret('token');

        $this->assertTrue(is_wp_error($result));
        $this->assertSame('discord_bot_jlg_encrypt_secret_missing_openssl', $result->get_error_code());
        $this->assertSame(
            'La bibliothèque OpenSSL est requise pour chiffrer le token Discord.',
            $result->get_error_message()
        );
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_decrypt_secret_returns_error_when_constants_missing() {
        $payload = DISCORD_BOT_JLG_SECRET_PREFIX . base64_encode(str_repeat('A', 48));

        $result = discord_bot_jlg_decrypt_secret($payload);

        $this->assertTrue(is_wp_error($result));
        $this->assertSame('discord_bot_jlg_decrypt_secret_missing_keys', $result->get_error_code());
        $this->assertSame(
            'Les constantes AUTH_KEY et AUTH_SALT sont requises pour déchiffrer le token Discord.',
            $result->get_error_message()
        );
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_decrypt_secret_returns_error_when_constants_empty_even_if_filter_allows() {
        if (!defined('AUTH_KEY')) {
            define('AUTH_KEY', '');
        }

        if (!defined('AUTH_SALT')) {
            define('AUTH_SALT', '');
        }

        add_filter('discord_bot_jlg_has_auth_constants', '__return_true');

        $payload = DISCORD_BOT_JLG_SECRET_PREFIX . base64_encode(str_repeat('A', 48));

        $result = discord_bot_jlg_decrypt_secret($payload);

        $this->assertTrue(is_wp_error($result));
        $this->assertSame('discord_bot_jlg_decrypt_secret_missing_keys', $result->get_error_code());
        $this->assertSame(
            'Les constantes AUTH_KEY et AUTH_SALT sont requises pour déchiffrer le token Discord.',
            $result->get_error_message()
        );

        remove_all_filters('discord_bot_jlg_has_auth_constants');
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_decrypt_secret_returns_error_when_openssl_is_unavailable() {
        $this->define_auth_constants();

        $encrypted = discord_bot_jlg_encrypt_secret('token');
        $this->assertFalse(is_wp_error($encrypted));

        add_filter('discord_bot_jlg_has_openssl_decrypt', function () {
            return false;
        });

        $result = discord_bot_jlg_decrypt_secret($encrypted);

        $this->assertTrue(is_wp_error($result));
        $this->assertSame('discord_bot_jlg_decrypt_secret_missing_openssl', $result->get_error_code());
        $this->assertSame(
            'La bibliothèque OpenSSL est requise pour déchiffrer le token Discord.',
            $result->get_error_message()
        );
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_decrypt_secret_returns_error_when_value_is_not_recognized() {
        $result = discord_bot_jlg_decrypt_secret('not-encrypted');

        $this->assertTrue(is_wp_error($result));
        $this->assertSame('discord_bot_jlg_decrypt_secret_unrecognized_prefix', $result->get_error_code());
        $this->assertSame(
            'Le format du token enregistré n’est pas reconnu comme un secret chiffré.',
            $result->get_error_message()
        );
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_decrypt_secret_returns_error_when_payload_is_not_base64() {
        $this->define_auth_constants();

        $result = discord_bot_jlg_decrypt_secret(DISCORD_BOT_JLG_SECRET_PREFIX . '@@@');

        $this->assertTrue(is_wp_error($result));
        $this->assertSame('discord_bot_jlg_decrypt_secret_invalid_payload', $result->get_error_code());
        $this->assertSame(
            'Le token chiffré est corrompu ou incomplet.',
            $result->get_error_message()
        );
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_decrypt_secret_returns_error_when_mac_is_invalid() {
        $this->define_auth_constants();

        $encrypted = discord_bot_jlg_encrypt_secret('token');
        $this->assertFalse(is_wp_error($encrypted));

        $payload  = substr($encrypted, strlen(DISCORD_BOT_JLG_SECRET_PREFIX));
        $decoded  = base64_decode($payload, true);
        $this->assertNotFalse($decoded);
        $corrupted = substr($decoded, 0, -1) . chr(ord(substr($decoded, -1)) ^ 0xFF);
        $tampered = DISCORD_BOT_JLG_SECRET_PREFIX . base64_encode($corrupted);

        $result = discord_bot_jlg_decrypt_secret($tampered);

        $this->assertTrue(is_wp_error($result));
        $this->assertSame('discord_bot_jlg_decrypt_secret_mac_mismatch', $result->get_error_code());
        $this->assertSame(
            'Le token chiffré n’a pas pu être vérifié.',
            $result->get_error_message()
        );
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_decrypt_secret_migrates_legacy_secret() {
        $this->define_auth_constants();

        $plaintext = 'legacy-token-value';

        $key_material = hash('sha256', self::AUTH_KEY, true);
        $iv_material  = hash('sha256', self::AUTH_SALT . self::AUTH_KEY, true);
        $iv           = substr($iv_material, 0, 16);
        $ciphertext   = openssl_encrypt($plaintext, 'aes-256-cbc', $key_material, OPENSSL_RAW_DATA, $iv);
        $mac          = hash_hmac('sha256', $ciphertext, self::AUTH_SALT, true);
        $legacy       = DISCORD_BOT_JLG_SECRET_PREFIX_LEGACY . base64_encode($ciphertext . $mac);

        $this->assertTrue(discord_bot_jlg_is_encrypted_secret($legacy));

        $captured = null;
        add_action('discord_bot_jlg_secret_migrated', function ($migrated, $original) use (&$captured) {
            $captured = array($migrated, $original);
        }, 10, 2);

        $decrypted = discord_bot_jlg_decrypt_secret($legacy);

        $this->assertFalse(is_wp_error($decrypted));
        $this->assertSame($plaintext, $decrypted);
        $this->assertIsArray($captured);
        $this->assertSame($legacy, $captured[1]);
        $this->assertNotSame($legacy, $captured[0]);
        $this->assertTrue(discord_bot_jlg_is_encrypted_secret($captured[0]));
        $this->assertStringStartsWith(DISCORD_BOT_JLG_SECRET_PREFIX, $captured[0]);
        $this->assertNotSame($legacy, $captured[0]);

        remove_all_filters('discord_bot_jlg_secret_migrated');
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_decrypt_secret_migrates_secret_without_iv_using_current_prefix() {
        $this->define_auth_constants();

        $plaintext = 'token-without-iv';

        $key_material = hash('sha256', self::AUTH_KEY, true);
        $iv_material  = hash('sha256', self::AUTH_SALT . self::AUTH_KEY, true);
        $iv           = substr($iv_material, 0, 16);
        $ciphertext   = openssl_encrypt($plaintext, 'aes-256-cbc', $key_material, OPENSSL_RAW_DATA, $iv);
        $mac          = hash_hmac('sha256', $ciphertext, self::AUTH_SALT, true);
        $legacy       = DISCORD_BOT_JLG_SECRET_PREFIX . base64_encode($ciphertext . $mac);

        $captured = null;
        add_action('discord_bot_jlg_secret_migrated', function ($migrated, $original) use (&$captured) {
            $captured = array($migrated, $original);
        }, 10, 2);

        $decrypted = discord_bot_jlg_decrypt_secret($legacy);

        $this->assertFalse(is_wp_error($decrypted));
        $this->assertSame($plaintext, $decrypted);
        $this->assertIsArray($captured);
        $this->assertSame($legacy, $captured[1]);
        $this->assertNotSame($legacy, $captured[0]);
        $this->assertTrue(discord_bot_jlg_is_encrypted_secret($captured[0]));
        $this->assertStringStartsWith(DISCORD_BOT_JLG_SECRET_PREFIX, $captured[0]);
        $this->assertNotSame($legacy, $captured[0]);

        remove_all_filters('discord_bot_jlg_secret_migrated');
    }

    public function test_get_auto_text_color_prefers_dark_on_light_background() {
        $this->assertSame('#0b1120', discord_bot_jlg_get_auto_text_color('#ffffff'));
    }

    public function test_get_auto_text_color_prefers_light_on_dark_background() {
        $this->assertSame('#ffffff', discord_bot_jlg_get_auto_text_color('#0f172a'));
    }

    public function test_get_auto_text_color_handles_transparent_background() {
        $this->assertSame('#0b1120', discord_bot_jlg_get_auto_text_color('rgba(255, 255, 255, 0.6)'));
    }

    /**
     * @dataProvider provide_sanitize_profile_key_inputs
     */
    public function test_sanitize_profile_key_handles_typical_inputs($input, $expected) {
        $this->assertSame($expected, discord_bot_jlg_sanitize_profile_key($input));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public function provide_sanitize_profile_key_inputs() {
        return array(
            'spaces converted to underscore' => array('My Profile Name', 'my_profile_name'),
            'multiple spaces collapsed' => array('Multiple   Spaces  Allowed', 'multiple_spaces_allowed'),
            'mixed case trimmed' => array('  Mixed Case Label  ', 'mixed_case_label'),
            'punctuation removed' => array('Spacing & punctuation!', 'spacing_punctuation'),
            'hyphen retained' => array('Already-Slug', 'already-slug'),
            'underscore retained' => array('Already_Slug', 'already_slug'),
            'combined separators' => array('Profile_With Mixed-Separators', 'profile_with_mixed-separators'),
            'punctuation squashed into underscore' => array('Custom Key!!', 'custom_key'),
            'mixed whitespace collapsed' => array("Tabs\t and\n new lines", 'tabs_and_new_lines'),
        );
    }

    public function test_remove_query_arg_handles_associative_arrays() {
        $input = array(
            'size'   => '256',
            'foo'    => 'bar',
            'nested' => array('alpha' => 'beta'),
        );

        $result = remove_query_arg(array('size', 'missing'), $input);

        $this->assertSame(
            array(
                'foo'    => 'bar',
                'nested' => array('alpha' => 'beta'),
            ),
            $result
        );
    }

    private function define_auth_constants(): void {
        if (!defined('AUTH_KEY')) {
            define('AUTH_KEY', self::AUTH_KEY);
        }

        if (!defined('AUTH_SALT')) {
            define('AUTH_SALT', self::AUTH_SALT);
        }
    }
}

