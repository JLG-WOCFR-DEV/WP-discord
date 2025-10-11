<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/inc/class-discord-stats-service.php';
require_once dirname(__DIR__, 2) . '/inc/class-discord-stats-fetcher.php';

class Test_Discord_Bot_JLG_Stats_Service extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        $GLOBALS['wp_test_transients'] = array();
    }

    public function test_execute_returns_cached_stats_without_fetch() {
        $cache_key = 'service_cache_key';
        $cached_stats = array('online' => 42, 'total' => 84);

        $cache_gateway = new Test_Discord_Bot_JLG_Stats_Service_Cache_Gateway(array(
            $cache_key => $cached_stats,
        ));

        $fetch_calls = 0;
        $fetcher = new Test_Discord_Bot_JLG_Stats_Service_Fetcher(function ($options) use (&$fetch_calls) {
            $fetch_calls++;
            return array(
                'stats' => array('online' => 1),
                'has_usable_stats' => true,
                'options' => $options,
            );
        });

        $fallback_calls = 0;
        $service = new Discord_Bot_JLG_Stats_Service(
            $cache_gateway,
            $fetcher,
            null,
            new Test_Discord_Bot_JLG_Stats_Service_Event_Logger()
        );

        $result = $service->execute(array(
            'cache_key' => $cache_key,
            'options' => array('server_id' => '123456789'),
            'fallback_provider' => function () use (&$fallback_calls) {
                $fallback_calls++;
                return array('fallback' => true);
            },
        ));

        $this->assertSame($cached_stats, $result['stats']);
        $this->assertTrue($result['used_cache']);
        $this->assertFalse($result['fallback_used']);
        $this->assertSame(0, $fetch_calls, 'Fetcher should not be called when cache is hit.');
        $this->assertSame(0, $fallback_calls, 'Fallback should not be invoked on cache hit.');
    }

    public function test_execute_handles_existing_lock_with_fallback() {
        $cache_key = 'locked_cache_key';
        $lock_key = $cache_key . Discord_Bot_JLG_API::REFRESH_LOCK_SUFFIX;
        set_transient($lock_key, array(
            'locked_at' => time() - 1,
            'expires_at' => time() + 45,
        ), 45);

        $cache_gateway = new Test_Discord_Bot_JLG_Stats_Service_Cache_Gateway();
        $fetch_calls = 0;
        $fetcher = new Test_Discord_Bot_JLG_Stats_Service_Fetcher(function ($options) use (&$fetch_calls) {
            $fetch_calls++;
            return array(
                'stats' => array('online' => 99),
                'has_usable_stats' => true,
                'options' => $options,
            );
        });

        $fallback_calls = 0;
        $persist_fallback_calls = 0;
        $stored_retry_after = null;
        $register_calls = 0;

        $service = new Discord_Bot_JLG_Stats_Service(
            $cache_gateway,
            $fetcher,
            null,
            new Test_Discord_Bot_JLG_Stats_Service_Event_Logger()
        );

        $result = $service->execute(array(
            'cache_key' => $cache_key,
            'options' => array('server_id' => '123456789'),
            'fallback_provider' => function () use (&$fallback_calls) {
                $fallback_calls++;
                return array('fallback' => true);
            },
            'persist_fallback' => function ($stats) use (&$persist_fallback_calls) {
                $persist_fallback_calls++;
                return $stats;
            },
            'read_retry_after' => function () {
                return 15;
            },
            'store_retry_after' => function ($retry_after) use (&$stored_retry_after) {
                $stored_retry_after = $retry_after;
            },
            'register_cache_key' => function () use (&$register_calls) {
                $register_calls++;
            },
        ));

        $this->assertTrue($result['fallback_used']);
        $this->assertFalse($result['lock_acquired']);
        $this->assertFalse($result['lock_released']);
        $this->assertSame(0, $fetch_calls, 'Fetcher should not run when lock exists.');
        $this->assertSame(1, $fallback_calls, 'Fallback should be triggered when lock blocks execution.');
        $this->assertSame(1, $persist_fallback_calls, 'Fallback persistence callback should run.');
        $this->assertSame(1, $register_calls, 'Cache key registration should be attempted before locking.');
        $this->assertSame(45, $result['retry_after']);
        $this->assertSame(45, $stored_retry_after);
    }

    public function test_execute_persists_stats_and_logs_on_success() {
        $cache_key = 'success_cache_key';
        $cache_gateway = new Test_Discord_Bot_JLG_Stats_Service_Cache_Gateway();

        $fetch_calls = 0;
        $fetcher = new Test_Discord_Bot_JLG_Stats_Service_Fetcher(function ($options) use (&$fetch_calls) {
            $fetch_calls++;
            return array(
                'stats' => array('online' => 7, 'total' => 11),
                'has_usable_stats' => true,
                'options' => $options,
            );
        });

        $persisted_stats = null;
        $persisted_context = null;
        $event_logger = new Test_Discord_Bot_JLG_Stats_Service_Event_Logger();
        $register_calls = 0;

        $service = new Discord_Bot_JLG_Stats_Service(
            $cache_gateway,
            $fetcher,
            null,
            $event_logger
        );

        $result = $service->execute(array(
            'cache_key' => $cache_key,
            'options' => array('server_id' => '555'),
            'persist_success' => function ($stats, $options, $context) use (&$persisted_stats, &$persisted_context) {
                $persisted_stats = $stats;
                $persisted_context = $context;
            },
            'register_cache_key' => function () use (&$register_calls) {
                $register_calls++;
            },
        ));

        $this->assertFalse($result['used_cache']);
        $this->assertFalse($result['fallback_used']);
        $this->assertTrue($result['lock_acquired']);
        $this->assertTrue($result['lock_released']);
        $this->assertSame(array('online' => 7, 'total' => 11), $result['stats']);
        $this->assertSame(array('online' => 7, 'total' => 11), $persisted_stats);
        $this->assertIsArray($persisted_context);
        $this->assertSame(1, $fetch_calls);
        $this->assertSame(1, $register_calls);
        $this->assertEmpty(get_transient($cache_key . Discord_Bot_JLG_API::REFRESH_LOCK_SUFFIX));

        $this->assertNotEmpty($event_logger->events);
        $last_event = end($event_logger->events);
        $this->assertSame('discord_stats_pipeline', $last_event['type']);
        $this->assertSame('success', $last_event['context']['stage']);
    }

    public function test_execute_uses_fallback_when_stats_invalid() {
        $cache_key = 'invalid_cache_key';
        $cache_gateway = new Test_Discord_Bot_JLG_Stats_Service_Cache_Gateway();

        $fetcher = new Test_Discord_Bot_JLG_Stats_Service_Fetcher(function ($options) {
            return array(
                'stats' => array('online' => 1),
                'has_usable_stats' => false,
                'options' => $options,
            );
        });

        $persist_success_called = false;
        $persist_fallback_calls = 0;
        $stored_retry_after = null;
        $event_logger = new Test_Discord_Bot_JLG_Stats_Service_Event_Logger();

        $service = new Discord_Bot_JLG_Stats_Service(
            $cache_gateway,
            $fetcher,
            null,
            $event_logger
        );

        $result = $service->execute(array(
            'cache_key' => $cache_key,
            'options' => array('server_id' => '777'),
            'persist_success' => function () use (&$persist_success_called) {
                $persist_success_called = true;
            },
            'persist_fallback' => function ($stats) use (&$persist_fallback_calls) {
                $persist_fallback_calls++;
                if (!is_array($stats)) {
                    $stats = array('fallback' => true);
                }
                $stats['from_persist'] = true;
                return $stats;
            },
            'fallback_provider' => function () {
                return array('fallback' => true);
            },
            'read_retry_after' => function () {
                return 120;
            },
            'store_retry_after' => function ($retry_after) use (&$stored_retry_after) {
                $stored_retry_after = $retry_after;
            },
        ));

        $this->assertTrue($result['fallback_used']);
        $this->assertSame(array('fallback' => true, 'from_persist' => true), $result['stats']);
        $this->assertSame(120, $result['retry_after']);
        $this->assertSame(120, $stored_retry_after);
        $this->assertSame(1, $persist_fallback_calls);
        $this->assertFalse($persist_success_called, 'Persist success should not run when stats are invalid.');
        $this->assertTrue($result['lock_released']);

        $this->assertNotEmpty($event_logger->events);
        $last_event = end($event_logger->events);
        $this->assertSame('fallback', $last_event['context']['stage']);
    }
}

class Test_Discord_Bot_JLG_Stats_Service_Cache_Gateway extends Discord_Bot_JLG_Cache_Gateway {
    private $store = array();

    public function __construct(array $prefill = array()) {
        $this->store = $prefill;
    }

    public function get($cache_key) {
        if (array_key_exists($cache_key, $this->store)) {
            return $this->store[$cache_key];
        }

        return parent::get($cache_key);
    }

    public function set($cache_key, $value, $expiration) {
        $this->store[$cache_key] = $value;
        parent::set($cache_key, $value, $expiration);
    }
}

class Test_Discord_Bot_JLG_Stats_Service_Fetcher extends Discord_Bot_JLG_Stats_Fetcher {
    private $callback;

    public function __construct(callable $callback) {
        $this->callback = $callback;
    }

    public function fetch(array $options) {
        return call_user_func($this->callback, $options);
    }
}

class Test_Discord_Bot_JLG_Stats_Service_Event_Logger extends Discord_Bot_JLG_Event_Logger {
    public $events = array();

    public function __construct() {
        // Bypass parent constructor to avoid option lookups.
    }

    public function log($type, array $context = array()) {
        $event = array(
            'type' => $type,
            'context' => $context,
        );

        $this->events[] = $event;

        return $event;
    }
}
