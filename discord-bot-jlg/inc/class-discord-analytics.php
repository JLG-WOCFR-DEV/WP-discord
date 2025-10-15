<?php

if (!defined('ABSPATH')) {
    exit;
}

class Discord_Bot_JLG_Analytics {
    const TABLE_SUFFIX = 'discord_bot_jlg_snapshots';
    const DEFAULT_RETENTION_DAYS = 90;
    const MAX_POINTS = 500;

    /**
     * @var wpdb|null
     */
    private $wpdb;

    /**
     * @var string
     */
    private $table_name;

    /**
     * @var bool
     */
    private $use_memory_storage;

    /**
     * @var array
     */
    private $memory_storage;

    public function __construct($wpdb = null, $table_name = '') {
        if (null === $wpdb && isset($GLOBALS['wpdb']) && is_object($GLOBALS['wpdb'])) {
            $wpdb = $GLOBALS['wpdb'];
        }

        $this->wpdb = (is_object($wpdb) && method_exists($wpdb, 'get_results')) ? $wpdb : null;
        $this->use_memory_storage = (null === $this->wpdb);
        $this->memory_storage = array();

        if ('' === $table_name) {
            $prefix = $this->wpdb && isset($this->wpdb->prefix) ? $this->wpdb->prefix : 'wp_';
            $this->table_name = $prefix . self::TABLE_SUFFIX;
        } else {
            $this->table_name = $table_name;
        }
    }

    public function get_table_name() {
        return $this->table_name;
    }

    public function install() {
        if ($this->use_memory_storage) {
            return true;
        }

        $charset_collate = '';
        if (isset($this->wpdb->charset) && $this->wpdb->charset) {
            $charset_collate = 'CHARACTER SET ' . $this->wpdb->charset;
        }
        if (isset($this->wpdb->collate) && $this->wpdb->collate) {
            $charset_collate .= ' COLLATE ' . $this->wpdb->collate;
        }

        $sql = sprintf(
            'CREATE TABLE %1$s (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                profile_key VARCHAR(191) NOT NULL,
                server_id VARCHAR(64) NOT NULL,
                snapshot_time DATETIME NOT NULL,
                online_count INT DEFAULT NULL,
                total_count INT DEFAULT NULL,
                approximate_presence_count INT DEFAULT NULL,
                approximate_member_count INT DEFAULT NULL,
                premium_subscription_count INT DEFAULT NULL,
                presence_breakdown LONGTEXT DEFAULT NULL,
                PRIMARY KEY  (id),
                KEY profile_key_time (profile_key, snapshot_time),
                KEY snapshot_time (snapshot_time)
            ) %2$s',
            $this->table_name,
            $charset_collate
        );

        if (!function_exists('dbDelta')) {
            $upgrade_file = rtrim(ABSPATH, '/\\') . '/wp-admin/includes/upgrade.php';
            if (file_exists($upgrade_file)) {
                require_once $upgrade_file;
            }
        }

        if (function_exists('dbDelta')) {
            dbDelta($sql);
            return true;
        }

        $this->wpdb->query($sql);
        return true;
    }

    public function log_snapshot($profile_key, $server_id, array $stats) {
        $profile_key = discord_bot_jlg_sanitize_profile_key($profile_key);
        if ('' === $profile_key) {
            $profile_key = 'default';
        }

        $server_id = preg_replace('/[^0-9]/', '', (string) $server_id);
        $snapshot_time = current_time('mysql', true);

        $online  = isset($stats['online']) ? (int) $stats['online'] : null;
        $total   = isset($stats['total']) && null !== $stats['total'] ? (int) $stats['total'] : null;
        $approx_presence = isset($stats['approximate_presence_count'])
            ? (int) $stats['approximate_presence_count']
            : null;
        $approx_members = isset($stats['approximate_member_count'])
            ? (int) $stats['approximate_member_count']
            : null;
        $premium = isset($stats['premium_subscription_count'])
            ? (int) $stats['premium_subscription_count']
            : null;

        $presence_breakdown = null;
        if (!empty($stats['presence_count_by_status']) && is_array($stats['presence_count_by_status'])) {
            $presence_breakdown = wp_json_encode($this->sanitize_presence_counts($stats['presence_count_by_status']));
        }

        if ($this->use_memory_storage) {
            $this->memory_storage[] = array(
                'profile_key' => $profile_key,
                'server_id'   => $server_id,
                'snapshot_time' => $snapshot_time,
                'online_count'   => $online,
                'total_count'    => $total,
                'approximate_presence_count' => $approx_presence,
                'approximate_member_count'   => $approx_members,
                'premium_subscription_count' => $premium,
                'presence_breakdown'         => $presence_breakdown,
            );
            return true;
        }

        $data = array(
            'profile_key' => $profile_key,
            'server_id'   => $server_id,
            'snapshot_time' => $snapshot_time,
            'online_count'   => $online,
            'total_count'    => $total,
            'approximate_presence_count' => $approx_presence,
            'approximate_member_count'   => $approx_members,
            'premium_subscription_count' => $premium,
            'presence_breakdown'         => $presence_breakdown,
        );

        $formats = array('%s', '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%s');

        if (method_exists($this->wpdb, 'insert')) {
            $this->wpdb->insert($this->table_name, $data, $formats);
        }

        return true;
    }

    public function purge_old_entries($retention_days) {
        $retention_days = (int) $retention_days;
        if ($retention_days <= 0) {
            return 0;
        }

        $cutoff = current_time('timestamp', true) - ($retention_days * DAY_IN_SECONDS);
        if ($cutoff <= 0) {
            return 0;
        }

        $cutoff_mysql = gmdate('Y-m-d H:i:s', $cutoff);

        if ($this->use_memory_storage) {
            $before = count($this->memory_storage);
            $this->memory_storage = array_values(array_filter(
                $this->memory_storage,
                function ($entry) use ($cutoff_mysql) {
                    return isset($entry['snapshot_time']) && $entry['snapshot_time'] > $cutoff_mysql;
                }
            ));
            return $before - count($this->memory_storage);
        }

        $sql = $this->wpdb->prepare(
            "DELETE FROM {$this->table_name} WHERE snapshot_time <= %s",
            $cutoff_mysql
        );

        if (method_exists($this->wpdb, 'query')) {
            return (int) $this->wpdb->query($sql);
        }

        return 0;
    }

    public function get_aggregates($args = array()) {
        $defaults = array(
            'profile_key' => '',
            'server_id'   => '',
            'days'        => 7,
            'limit'       => self::MAX_POINTS,
        );

        $args = wp_parse_args($args, $defaults);
        $profile_key = discord_bot_jlg_sanitize_profile_key($args['profile_key']);
        $server_id   = preg_replace('/[^0-9]/', '', (string) $args['server_id']);
        $days        = max(1, (int) $args['days']);
        $limit       = max(1, (int) $args['limit']);

        $end_timestamp = current_time('timestamp', true);
        $start_timestamp = $end_timestamp - ($days * DAY_IN_SECONDS);
        $start_mysql = gmdate('Y-m-d H:i:s', $start_timestamp);

        $entries = $this->use_memory_storage
            ? $this->filter_memory_entries($profile_key, $server_id, $start_mysql, $limit)
            : $this->query_entries($profile_key, $server_id, $start_mysql, $limit);

        return $this->build_aggregate_payload($entries, $start_timestamp, $end_timestamp, $days);
    }

    private function sanitize_presence_counts($counts) {
        if (!is_array($counts)) {
            return array();
        }

        $sanitized = array();
        foreach ($counts as $status => $value) {
            $key = sanitize_key($status);
            if ('' === $key) {
                continue;
            }

            $sanitized[$key] = (int) $value;
        }

        return $sanitized;
    }

    private function filter_memory_entries($profile_key, $server_id, $start_mysql, $limit) {
        $filtered = array();
        foreach ($this->memory_storage as $entry) {
            if ($profile_key && (!isset($entry['profile_key']) || $entry['profile_key'] !== $profile_key)) {
                continue;
            }
            if ($server_id && (!isset($entry['server_id']) || $entry['server_id'] !== $server_id)) {
                continue;
            }
            if (!isset($entry['snapshot_time']) || $entry['snapshot_time'] < $start_mysql) {
                continue;
            }
            $filtered[] = $entry;
        }

        usort($filtered, function ($a, $b) {
            $time_a = isset($a['snapshot_time']) ? strtotime($a['snapshot_time']) : 0;
            $time_b = isset($b['snapshot_time']) ? strtotime($b['snapshot_time']) : 0;
            if ($time_a === $time_b) {
                return 0;
            }
            return ($time_a < $time_b) ? -1 : 1;
        });

        if (count($filtered) > $limit) {
            $filtered = array_slice($filtered, count($filtered) - $limit);
        }

        return $filtered;
    }

    private function query_entries($profile_key, $server_id, $start_mysql, $limit) {
        $conditions = array('snapshot_time >= %s');
        $params = array($start_mysql);

        if ('' !== $profile_key) {
            $conditions[] = 'profile_key = %s';
            $params[] = $profile_key;
        }

        if ('' !== $server_id) {
            $conditions[] = 'server_id = %s';
            $params[] = $server_id;
        }

        $where_clause = implode(' AND ', $conditions);
        $sql = "SELECT snapshot_time, profile_key, server_id, online_count, total_count, approximate_presence_count, approximate_member_count, premium_subscription_count, presence_breakdown FROM {$this->table_name} WHERE {$where_clause} ORDER BY snapshot_time ASC";
        if ($limit > 0) {
            $sql .= ' LIMIT ' . (int) $limit;
        }

        if (!empty($params)) {
            $sql = $this->wpdb->prepare($sql, $params);
        }

        $results = $this->wpdb->get_results($sql, ARRAY_A);
        if (!is_array($results)) {
            return array();
        }

        return $results;
    }

    private function build_aggregate_payload($entries, $start_timestamp, $end_timestamp, $days) {
        $timeseries = array();
        $online_sum = 0;
        $online_count = 0;
        $presence_sum = 0;
        $presence_count = 0;
        $total_sum = 0;
        $total_count = 0;
        $peak_presence = array('count' => null, 'timestamp' => null);
        $first_premium = null;
        $last_premium = null;

        foreach ($entries as $entry) {
            $timestamp = isset($entry['snapshot_time']) ? strtotime($entry['snapshot_time'] . ' UTC') : 0;
            if ($timestamp <= 0) {
                continue;
            }

            $online = isset($entry['online_count']) ? (int) $entry['online_count'] : null;
            $total  = isset($entry['total_count']) ? (int) $entry['total_count'] : null;
            $presence = isset($entry['approximate_presence_count'])
                ? (int) $entry['approximate_presence_count']
                : null;
            if (null === $presence && isset($entry['online_count'])) {
                $presence = (int) $entry['online_count'];
            }
            $premium = isset($entry['premium_subscription_count'])
                ? (int) $entry['premium_subscription_count']
                : null;

            $timeseries[] = array(
                'timestamp' => $timestamp,
                'online'    => $online,
                'presence'  => $presence,
                'total'     => $total,
                'premium'   => $premium,
            );

            if (null !== $online) {
                $online_sum += $online;
                $online_count++;
            }

            if (null !== $total) {
                $total_sum += $total;
                $total_count++;
            }

            if (null !== $presence) {
                $presence_sum += $presence;
                $presence_count++;
                if (null === $peak_presence['count'] || $presence > $peak_presence['count']) {
                    $peak_presence['count'] = $presence;
                    $peak_presence['timestamp'] = $timestamp;
                }
            }

            if (null !== $premium) {
                if (null === $first_premium) {
                    $first_premium = $premium;
                }
                $last_premium = $premium;
            }
        }

        $averages = array(
            'online'   => $online_count > 0 ? $online_sum / (float) $online_count : null,
            'presence' => $presence_count > 0 ? $presence_sum / (float) $presence_count : null,
            'total'    => $total_count > 0 ? $total_sum / (float) $total_count : null,
        );

        $boost_trend = array(
            'latest'   => $last_premium,
            'initial'  => $first_premium,
            'delta'    => (null !== $last_premium && null !== $first_premium) ? ($last_premium - $first_premium) : null,
        );

        return array(
            'range' => array(
                'start' => $start_timestamp,
                'end'   => $end_timestamp,
                'days'  => $days,
            ),
            'averages' => $averages,
            'peak_presence' => $peak_presence,
            'boost_trend' => $boost_trend,
            'timeseries' => $timeseries,
        );
    }
}
