<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/bootstrap.php';

class Discord_Bot_JLG_API_Site_Health_Stub extends Discord_Bot_JLG_API {
    private $mock_last_error;
    private $has_mock_last_error = false;

    public function set_mock_last_error_message($message) {
        $this->mock_last_error     = $message;
        $this->has_mock_last_error = true;
    }

    public function get_last_error_message() {
        if (true === $this->has_mock_last_error) {
            return $this->mock_last_error;
        }

        return parent::get_last_error_message();
    }
}

class Test_Discord_Bot_JLG_Site_Health extends TestCase {
    protected function setUp(): void {
        parent::setUp();

        $GLOBALS['wp_test_options']    = array();
        $GLOBALS['wp_test_transients'] = array();
        $GLOBALS['wp_test_current_timestamp'] = 1700000000;
        $GLOBALS['wp_test_timezone_string']   = 'UTC';
    }

    protected function tearDown(): void {
        unset($GLOBALS['wp_test_current_timestamp'], $GLOBALS['wp_test_timezone_string']);

        parent::tearDown();
    }

    private function create_api() {
        return new Discord_Bot_JLG_API_Site_Health_Stub(
            DISCORD_BOT_JLG_OPTION_NAME,
            DISCORD_BOT_JLG_CACHE_KEY,
            DISCORD_BOT_JLG_DEFAULT_CACHE_DURATION
        );
    }

    public function test_returns_critical_status_when_server_id_missing() {
        update_option(DISCORD_BOT_JLG_OPTION_NAME, array());

        $api = $this->create_api();
        $site_health = new Discord_Bot_JLG_Site_Health($api);

        $result = $site_health->run_site_health_test();

        $this->assertSame('critical', $result['status']);
        $this->assertStringContainsString(
            "Aucun identifiant de serveur Discord n'est configuré",
            strip_tags($result['description'])
        );
    }

    public function test_returns_recommended_status_when_demo_mode_enabled() {
        update_option(
            DISCORD_BOT_JLG_OPTION_NAME,
            array(
                'demo_mode' => 1,
            )
        );

        $api = $this->create_api();
        $site_health = new Discord_Bot_JLG_Site_Health($api);

        $result = $site_health->run_site_health_test();

        $this->assertSame('recommended', $result['status']);
        $this->assertStringContainsString(
            'Le plugin fonctionne actuellement en mode démonstration',
            strip_tags($result['description'])
        );
    }

    public function test_reports_recent_fallback_details() {
        $timestamp = 1700000000;

        update_option('date_format', 'Y-m-d');
        update_option('time_format', 'H:i');
        update_option(
            DISCORD_BOT_JLG_OPTION_NAME,
            array(
                'server_id' => '123456789012345678',
            )
        );
        update_option(
            Discord_Bot_JLG_API::LAST_FALLBACK_OPTION,
            array(
                'timestamp' => $timestamp,
                'reason'    => 'Widget indisponible',
            )
        );

        $api = $this->create_api();
        $site_health = new Discord_Bot_JLG_Site_Health($api);

        $result = $site_health->run_site_health_test();
        $description = strip_tags($result['description']);

        $this->assertSame('recommended', $result['status']);
        $this->assertStringContainsString('Statistiques de secours utilisées depuis le 2023-11-14 22:13.', $description);
        $this->assertStringContainsString('Dernière erreur signalée : Widget indisponible.', $description);
        $this->assertStringContainsString('Une nouvelle tentative sera effectuée automatiquement dès que possible.', $description);
    }

    public function test_reports_last_error_when_available() {
        update_option(
            DISCORD_BOT_JLG_OPTION_NAME,
            array(
                'server_id' => '123456789012345678',
            )
        );

        $api = $this->create_api();
        $api->set_mock_last_error_message('Erreur API 500');

        $site_health = new Discord_Bot_JLG_Site_Health($api);
        $result = $site_health->run_site_health_test();

        $this->assertSame('recommended', $result['status']);
        $this->assertStringContainsString('Dernière erreur rencontrée : Erreur API 500.', strip_tags($result['description']));
    }

    public function test_returns_good_status_when_everything_is_ok() {
        update_option(
            DISCORD_BOT_JLG_OPTION_NAME,
            array(
                'server_id' => '123456789012345678',
            )
        );

        delete_option(Discord_Bot_JLG_API::LAST_FALLBACK_OPTION);

        $api = $this->create_api();
        $site_health = new Discord_Bot_JLG_Site_Health($api);

        $result = $site_health->run_site_health_test();

        $this->assertSame('good', $result['status']);
        $this->assertStringContainsString(
            'La connexion au serveur Discord fonctionne normalement.',
            strip_tags($result['description'])
        );
    }
}
