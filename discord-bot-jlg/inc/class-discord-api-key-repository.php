<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('discord_bot_jlg_sanitize_profile_key')) {
    require_once __DIR__ . '/helpers.php';
}

/**
 * Gère la génération, la révocation et la validation des clés API REST.
 */
class Discord_Bot_JLG_API_Key_Repository {
    const TABLE_SUFFIX = 'discord_bot_jlg_api_keys';
    const ALLOWED_SCOPES = array('stats', 'analytics', 'export');

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

    /**
     * @var int
     */
    private $memory_sequence;

    public function __construct($wpdb = null, $table_name = '') {
        if (null === $wpdb && isset($GLOBALS['wpdb']) && is_object($GLOBALS['wpdb'])) {
            $wpdb = $GLOBALS['wpdb'];
        }

        $this->wpdb = (is_object($wpdb) && method_exists($wpdb, 'get_results')) ? $wpdb : null;
        $this->use_memory_storage = (null === $this->wpdb);
        $this->memory_storage = array();
        $this->memory_sequence = 0;

        if ('' === $table_name) {
            $prefix = $this->wpdb && isset($this->wpdb->prefix) ? $this->wpdb->prefix : 'wp_';
            $this->table_name = $prefix . self::TABLE_SUFFIX;
        } else {
            $this->table_name = (string) $table_name;
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
                fingerprint CHAR(64) NOT NULL,
                key_hash VARCHAR(255) NOT NULL,
                label VARCHAR(191) NOT NULL DEFAULT \'\',
                profiles LONGTEXT NOT NULL,
                scopes LONGTEXT NOT NULL,
                created_at DATETIME NOT NULL,
                created_by BIGINT UNSIGNED DEFAULT 0,
                expires_at DATETIME DEFAULT NULL,
                revoked_at DATETIME DEFAULT NULL,
                last_used_at DATETIME DEFAULT NULL,
                usage_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
                PRIMARY KEY  (id),
                UNIQUE KEY fingerprint (fingerprint),
                KEY expires_at (expires_at),
                KEY revoked_at (revoked_at)
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

        if (method_exists($this->wpdb, 'query')) {
            $this->wpdb->query($sql);
        }

        return true;
    }

    public function get_allowed_scopes() {
        return self::ALLOWED_SCOPES;
    }

    public function create_key(array $args = array()) {
        $label = isset($args['label']) ? wp_strip_all_tags($args['label']) : '';
        $label = ('' !== $label) ? $label : __('Clé API', 'discord-bot-jlg');

        $profiles = $this->sanitize_profiles(isset($args['profile_keys']) ? $args['profile_keys'] : array());
        $scopes   = $this->sanitize_scopes(isset($args['scopes']) ? $args['scopes'] : array());

        if (empty($scopes)) {
            $scopes = array('stats');
        }

        $expires_at = $this->sanitize_datetime(isset($args['expires_at']) ? $args['expires_at'] : '');
        $created_by = isset($args['created_by']) ? (int) $args['created_by'] : 0;

        $secret = $this->generate_secret();
        $fingerprint = $this->compute_fingerprint($secret);
        $hash = $this->hash_secret($secret);

        $record = array(
            'fingerprint' => $fingerprint,
            'key_hash'    => $hash,
            'label'       => $label,
            'profiles'    => wp_json_encode(array_values($profiles)),
            'scopes'      => wp_json_encode(array_values($scopes)),
            'created_at'  => current_time('mysql', true),
            'created_by'  => $created_by,
            'expires_at'  => $expires_at,
            'revoked_at'  => null,
            'last_used_at'=> null,
            'usage_count' => 0,
        );

        if ($this->use_memory_storage) {
            $this->memory_sequence++;
            $record['id'] = $this->memory_sequence;
            $this->memory_storage[$record['id']] = $record;

            return array(
                'id'          => $record['id'],
                'key'         => $secret,
                'fingerprint' => $fingerprint,
                'label'       => $label,
                'profiles'    => $profiles,
                'scopes'      => $scopes,
                'created_at'  => $record['created_at'],
                'expires_at'  => $expires_at,
            );
        }

        $data = $record;
        unset($data['id']);

        if (method_exists($this->wpdb, 'insert')) {
            $this->wpdb->insert(
                $this->table_name,
                $data,
                array('%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%d')
            );
        }

        $insert_id = isset($this->wpdb->insert_id) ? (int) $this->wpdb->insert_id : 0;

        return array(
            'id'          => $insert_id,
            'key'         => $secret,
            'fingerprint' => $fingerprint,
            'label'       => $label,
            'profiles'    => $profiles,
            'scopes'      => $scopes,
            'created_at'  => $record['created_at'],
            'expires_at'  => $expires_at,
        );
    }

    public function list_keys($args = array()) {
        $defaults = array(
            'include_revoked' => true,
        );
        $args = wp_parse_args($args, $defaults);
        $include_revoked = (bool) $args['include_revoked'];

        $records = $this->load_all_records();

        if (!$include_revoked) {
            $records = array_filter(
                $records,
                function ($record) {
                    return empty($record['revoked_at']);
                }
            );
        }

        usort(
            $records,
            function ($a, $b) {
                $a_time = isset($a['created_at']) ? strtotime($a['created_at'] . ' +0000') : 0;
                $b_time = isset($b['created_at']) ? strtotime($b['created_at'] . ' +0000') : 0;

                if ($a_time === $b_time) {
                    return $a['id'] < $b['id'] ? -1 : 1;
                }

                return ($a_time < $b_time) ? -1 : 1;
            }
        );

        return array_values($records);
    }

    public function get_key_by_id($key_id) {
        $key_id = (int) $key_id;
        if ($key_id <= 0) {
            return null;
        }

        $records = $this->load_all_records();

        foreach ($records as $record) {
            if ((int) $record['id'] === $key_id) {
                return $record;
            }
        }

        return null;
    }

    public function get_key_by_fingerprint($fingerprint) {
        $fingerprint = $this->sanitize_fingerprint($fingerprint);
        if ('' === $fingerprint) {
            return null;
        }

        $records = $this->load_all_records();

        foreach ($records as $record) {
            if (isset($record['fingerprint']) && $record['fingerprint'] === $fingerprint) {
                return $record;
            }
        }

        return null;
    }

    public function validate_key_for_request($raw_key, array $context = array()) {
        $raw_key = is_string($raw_key) ? trim($raw_key) : '';
        if ('' === $raw_key) {
            return array(
                'valid' => false,
                'error' => 'missing_key',
            );
        }

        $fingerprint = $this->compute_fingerprint($raw_key);
        $record = $this->get_key_by_fingerprint($fingerprint);
        if (null === $record) {
            return array(
                'valid'       => false,
                'error'       => 'not_found',
                'fingerprint' => $fingerprint,
            );
        }

        if (!empty($record['revoked_at'])) {
            return array(
                'valid' => false,
                'error' => 'revoked',
                'key'   => $record,
            );
        }

        if (!empty($record['expires_at'])) {
            $expires_at = strtotime($record['expires_at'] . ' +0000');
            if ($expires_at > 0 && $expires_at <= current_time('timestamp', true)) {
                return array(
                    'valid' => false,
                    'error' => 'expired',
                    'key'   => $record,
                );
            }
        }

        if (!$this->verify_secret($raw_key, isset($record['key_hash']) ? $record['key_hash'] : '')) {
            return array(
                'valid' => false,
                'error' => 'mismatch',
                'key'   => $record,
            );
        }

        $required_scope = isset($context['scope']) ? sanitize_key($context['scope']) : '';
        $required_scope = ('' !== $required_scope) ? $required_scope : 'stats';
        $required_profile = isset($context['profile_key'])
            ? discord_bot_jlg_sanitize_profile_key($context['profile_key'])
            : 'default';
        if ('' === $required_profile) {
            $required_profile = 'default';
        }

        $allowed_scopes = isset($record['scopes']) && is_array($record['scopes'])
            ? $record['scopes']
            : array();

        if (!in_array($required_scope, $allowed_scopes, true)) {
            return array(
                'valid' => false,
                'error' => 'scope_not_allowed',
                'key'   => $record,
            );
        }

        $allowed_profiles = isset($record['profiles']) && is_array($record['profiles'])
            ? $record['profiles']
            : array('default');

        $profile_allowed = in_array($required_profile, $allowed_profiles, true)
            || in_array('__all__', $allowed_profiles, true)
            || in_array('all', $allowed_profiles, true)
            || in_array('*', $allowed_profiles, true);

        if (!$profile_allowed) {
            return array(
                'valid' => false,
                'error' => 'profile_not_allowed',
                'key'   => $record,
            );
        }

        return array(
            'valid'       => true,
            'key'         => $record,
            'fingerprint' => $fingerprint,
        );
    }

    public function record_usage($key_id) {
        $key = $this->get_key_by_id($key_id);
        if (null === $key) {
            return false;
        }

        $now = current_time('mysql', true);
        $usage_count = isset($key['usage_count']) ? (int) $key['usage_count'] : 0;
        $usage_count++;

        if ($this->use_memory_storage) {
            $key['last_used_at'] = $now;
            $key['usage_count']  = $usage_count;
            $this->memory_storage[$key['id']] = $key;

            return true;
        }

        if (method_exists($this->wpdb, 'update')) {
            $this->wpdb->update(
                $this->table_name,
                array(
                    'last_used_at' => $now,
                    'usage_count'  => $usage_count,
                ),
                array('id' => $key_id),
                array('%s', '%d'),
                array('%d')
            );
        }

        return true;
    }

    public function revoke_key($key_id) {
        $key_id = (int) $key_id;
        if ($key_id <= 0) {
            return false;
        }

        $now = current_time('mysql', true);

        if ($this->use_memory_storage) {
            if (!isset($this->memory_storage[$key_id])) {
                return false;
            }

            $this->memory_storage[$key_id]['revoked_at'] = $now;
            return true;
        }

        if (method_exists($this->wpdb, 'update')) {
            $this->wpdb->update(
                $this->table_name,
                array('revoked_at' => $now),
                array('id' => $key_id),
                array('%s'),
                array('%d')
            );
        }

        return true;
    }

    private function load_all_records() {
        if ($this->use_memory_storage) {
            return array_map(array($this, 'normalize_record'), array_values($this->memory_storage));
        }

        if (!method_exists($this->wpdb, 'get_results')) {
            return array();
        }

        $results = $this->wpdb->get_results('SELECT * FROM ' . $this->table_name, ARRAY_A);
        if (!is_array($results)) {
            return array();
        }

        return array_map(array($this, 'normalize_record'), $results);
    }

    private function normalize_record($record) {
        if (!is_array($record)) {
            return array();
        }

        $record['id'] = isset($record['id']) ? (int) $record['id'] : 0;
        $record['fingerprint'] = isset($record['fingerprint']) ? $this->sanitize_fingerprint($record['fingerprint']) : '';
        $record['label'] = isset($record['label']) ? wp_strip_all_tags($record['label']) : '';

        $profiles = array();
        if (!empty($record['profiles'])) {
            $decoded = json_decode($record['profiles'], true);
            if (is_array($decoded)) {
                $profiles = array_values(array_map('discord_bot_jlg_sanitize_profile_key', $decoded));
            }
        }
        $record['profiles'] = $this->sanitize_profiles($profiles);

        $scopes = array();
        if (!empty($record['scopes'])) {
            $decoded_scopes = json_decode($record['scopes'], true);
            if (is_array($decoded_scopes)) {
                $scopes = $this->sanitize_scopes($decoded_scopes);
            }
        }
        $record['scopes'] = $scopes;

        $record['created_at'] = isset($record['created_at']) ? $this->sanitize_datetime($record['created_at']) : '';
        $record['created_by'] = isset($record['created_by']) ? (int) $record['created_by'] : 0;
        $record['expires_at'] = isset($record['expires_at']) ? $this->sanitize_datetime($record['expires_at']) : '';
        $record['revoked_at'] = isset($record['revoked_at']) ? $this->sanitize_datetime($record['revoked_at']) : '';
        $record['last_used_at'] = isset($record['last_used_at']) ? $this->sanitize_datetime($record['last_used_at']) : '';
        $record['usage_count'] = isset($record['usage_count']) ? (int) $record['usage_count'] : 0;

        return $record;
    }

    private function sanitize_profiles($profiles) {
        if (!is_array($profiles)) {
            $profiles = array($profiles);
        }

        $sanitized = array();

        foreach ($profiles as $profile) {
            if (!is_string($profile)) {
                continue;
            }

            $profile_key = discord_bot_jlg_sanitize_profile_key($profile);
            if ('' === $profile_key && in_array($profile, array('__all__', 'all', '*'), true)) {
                $profile_key = $profile;
            }

            if ('' === $profile_key) {
                continue;
            }

            $sanitized[$profile_key] = $profile_key;
        }

        if (empty($sanitized)) {
            $sanitized = array('default');
        }

        return array_values($sanitized);
    }

    private function sanitize_scopes($scopes) {
        if (!is_array($scopes)) {
            $scopes = array($scopes);
        }

        $scopes = array_map('sanitize_key', $scopes);
        $scopes = array_filter(
            $scopes,
            function ($scope) {
                return in_array($scope, self::ALLOWED_SCOPES, true);
            }
        );

        return array_values(array_unique($scopes));
    }

    private function sanitize_datetime($value) {
        if (!is_string($value) || '' === trim($value)) {
            return '';
        }

        $timestamp = strtotime($value . ' +0000');
        if (false === $timestamp) {
            return '';
        }

        return gmdate('Y-m-d H:i:s', $timestamp);
    }

    private function sanitize_fingerprint($fingerprint) {
        $fingerprint = is_string($fingerprint) ? trim($fingerprint) : '';
        if ('' === $fingerprint) {
            return '';
        }

        return strtolower(preg_replace('/[^a-f0-9]/i', '', $fingerprint));
    }

    private function generate_secret() {
        if (function_exists('random_bytes')) {
            return substr(bin2hex(random_bytes(24)), 0, 48);
        }

        return wp_generate_password(48, false, false);
    }

    private function compute_fingerprint($secret) {
        return hash('sha256', (string) $secret);
    }

    private function hash_secret($secret) {
        if (function_exists('wp_hash_password')) {
            return wp_hash_password((string) $secret);
        }

        return password_hash((string) $secret, PASSWORD_BCRYPT);
    }

    private function verify_secret($secret, $hash) {
        if (!is_string($hash) || '' === $hash) {
            return false;
        }

        if (function_exists('wp_check_password')) {
            return wp_check_password((string) $secret, $hash, 0);
        }

        if (function_exists('password_verify')) {
            return password_verify((string) $secret, $hash);
        }

        return hash_equals($hash, $secret);
    }
}
