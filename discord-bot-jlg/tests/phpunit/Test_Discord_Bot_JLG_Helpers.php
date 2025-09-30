<?php

require_once __DIR__ . '/includes/bootstrap.php';

/**
 * @group discord-bot-jlg
 */
class Test_Discord_Bot_JLG_Helpers extends WP_UnitTestCase {

    public function test_encrypt_secret_uses_v2_format() {
        $first  = discord_bot_jlg_encrypt_secret('super-secret');
        $second = discord_bot_jlg_encrypt_secret('super-secret');

        $this->assertFalse(is_wp_error($first));
        $this->assertFalse(is_wp_error($second));

        $this->assertStringStartsWith(DISCORD_BOT_JLG_SECRET_PREFIX, $first);
        $this->assertStringStartsWith(DISCORD_BOT_JLG_SECRET_PREFIX, $second);
        $this->assertNotSame($first, $second, 'Le chiffrement doit utiliser un IV aléatoire.');

        $payload = substr($first, strlen(DISCORD_BOT_JLG_SECRET_PREFIX));
        $binary  = base64_decode($payload, true);

        $this->assertIsString($binary);
        $this->assertGreaterThan(48, strlen($binary));

        $decrypted = discord_bot_jlg_decrypt_secret($first);

        $this->assertFalse(is_wp_error($decrypted));
        $this->assertSame('super-secret', $decrypted);
    }

    public function test_decrypt_secret_supports_legacy_format() {
        $legacy_plain  = 'legacy-secret';
        $legacy_secret = $this->encrypt_secret_v1($legacy_plain);

        $this->assertTrue(discord_bot_jlg_is_encrypted_secret($legacy_secret));

        $decrypted = discord_bot_jlg_decrypt_secret($legacy_secret);

        $this->assertFalse(is_wp_error($decrypted));
        $this->assertSame($legacy_plain, $decrypted);
    }

    private function encrypt_secret_v1($plaintext) {
        $key_material = hash('sha256', AUTH_KEY, true);
        $iv_material  = hash('sha256', AUTH_SALT . AUTH_KEY, true);
        $iv           = substr($iv_material, 0, 16);

        $ciphertext = openssl_encrypt($plaintext, 'aes-256-cbc', $key_material, OPENSSL_RAW_DATA, $iv);
        $mac        = hash_hmac('sha256', $ciphertext, AUTH_SALT, true);

        return DISCORD_BOT_JLG_SECRET_PREFIX_V1 . base64_encode($ciphertext . $mac);
    }
}
