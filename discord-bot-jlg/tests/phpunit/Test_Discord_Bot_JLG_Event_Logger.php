<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/bootstrap.php';

class Test_Discord_Bot_JLG_Event_Logger extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        delete_option('discord_bot_jlg_test_event_log');
    }

    public function test_log_stores_event_with_incrementing_id() {
        $logger = new Discord_Bot_JLG_Event_Logger('discord_bot_jlg_test_event_log', 10, 3600);
        $logger->reset();

        $event = $logger->log('discord_http', array('foo' => 'bar', 'nested' => array('baz' => 1)));

        $this->assertArrayHasKey('id', $event);
        $this->assertSame(1, $event['id']);
        $this->assertSame('discord_http', $event['type']);
        $this->assertArrayHasKey('context', $event);
        $this->assertSame('bar', $event['context']['foo']);
        $this->assertSame(1, $event['context']['nested']['baz']);

        $state = get_option('discord_bot_jlg_test_event_log');
        $this->assertIsArray($state);
        $this->assertSame(1, $state['sequence']);
        $this->assertCount(1, $state['events']);
    }

    public function test_get_events_respects_limit_and_filters() {
        $logger = new Discord_Bot_JLG_Event_Logger('discord_bot_jlg_test_event_log', 10, 3600);
        $logger->reset();

        $first  = $logger->log('discord_http', array('index' => 1));
        $second = $logger->log('custom', array('index' => 2));

        $latest = $logger->get_events(array('limit' => 1));
        $this->assertCount(1, $latest);
        $this->assertSame($second['id'], $latest[0]['id']);

        $filtered = $logger->get_events(array('type' => 'discord_http'));
        $this->assertCount(1, $filtered);
        $this->assertSame($first['id'], $filtered[0]['id']);

        $after = $logger->get_events(array('after_id' => $first['id']));
        $this->assertCount(1, $after);
        $this->assertSame($second['id'], $after[0]['id']);
    }

    public function test_purge_removes_old_entries() {
        $logger = new Discord_Bot_JLG_Event_Logger('discord_bot_jlg_test_event_log', 10, 3600);
        $logger->reset();

        $logger->log('discord_http', array('index' => 1));
        $state = get_option('discord_bot_jlg_test_event_log');
        $this->assertIsArray($state);
        $this->assertCount(1, $state['events']);

        $state['events'][0]['timestamp'] = time() - 1000;
        update_option('discord_bot_jlg_test_event_log', $state);

        $purged = $logger->purge(10);
        $this->assertSame(1, $purged);

        $state_after = get_option('discord_bot_jlg_test_event_log');
        $this->assertIsArray($state_after);
        $this->assertCount(0, $state_after['events']);
    }
}
