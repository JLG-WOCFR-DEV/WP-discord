<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/bootstrap.php';

class Test_Discord_Bot_JLG_Analytics extends TestCase {
    protected function tearDown(): void {
        unset($GLOBALS['wp_test_current_timestamp']);
        parent::tearDown();
    }

    public function test_log_snapshot_and_get_aggregates() {
        $analytics = new Discord_Bot_JLG_Analytics(null, 'test_snapshots');
        $GLOBALS['wp_test_current_timestamp'] = 1_700_000_000;

        $analytics->log_snapshot('default', '123', array(
            'online' => 15,
            'approximate_presence_count' => 18,
            'premium_subscription_count' => 3,
        ));

        $aggregates = $analytics->get_aggregates(array('profile_key' => 'default', 'days' => 7));

        $this->assertArrayHasKey('timeseries', $aggregates);
        $this->assertCount(1, $aggregates['timeseries']);
        $point = $aggregates['timeseries'][0];
        $this->assertSame(15, $point['online']);
        $this->assertSame(18, $point['presence']);
        $this->assertSame(3, $point['premium']);
        $this->assertArrayHasKey('averages', $aggregates);
        $this->assertSame(15.0, $aggregates['averages']['online']);
    }

    public function test_purge_old_entries_removes_outdated_records() {
        $analytics = new Discord_Bot_JLG_Analytics(null, 'test_snapshots');

        $GLOBALS['wp_test_current_timestamp'] = 1_700_000_000;
        $analytics->log_snapshot('default', '123', array('online' => 10));

        $GLOBALS['wp_test_current_timestamp'] = 1_700_086_400; // +1 day
        $analytics->log_snapshot('default', '123', array('online' => 20));

        $deleted = $analytics->purge_old_entries(1);
        $this->assertSame(1, $deleted);

        $aggregates = $analytics->get_aggregates(array('profile_key' => 'default', 'days' => 7));
        $this->assertCount(1, $aggregates['timeseries']);
        $this->assertSame(20, $aggregates['timeseries'][0]['online']);
    }
}
