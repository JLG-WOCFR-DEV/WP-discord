<?php

if (!defined('ABSPATH')) {
    exit;
}

class Discord_Bot_JLG_Token_Store {
    const TABLE_SUFFIX = 'discord_bot_jlg_tokens';

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
            $prefix = ($this->wpdb && isset($this->wpdb->prefix)) ? $this->wpdb->prefix : 'wp_';
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
                token LONGTEXT NULL,
                rotated_at BIGINT UNSIGNED NOT NULL DEFAULT 0,
                expires_at BIGINT UNSIGNED NOT NULL DEFAULT 0,
                status VARCHAR(64) NOT NULL DEFAULT \'\',
                metadata LONGTEXT NULL,
                created_at BIGINT UNSIGNED NOT NULL DEFAULT 0,
                updated_at BIGINT UNSIGNED NOT NULL DEFAULT 0,
                PRIMARY KEY  (id),
                UNIQUE KEY profile_key (profile_key),
                KEY expires_at (expires_at)
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

    public function get_token($profile_key) {
        $profile_key = $this->sanitize_profile_key($profile_key);

        if ('' === $profile_key) {
            return null;
        }

        if ($this->use_memory_storage) {
            if (!isset($this->memory_storage[$profile_key])) {
                return null;
            }

            return $this->memory_storage[$profile_key];
        }

        if (!method_exists($this->wpdb, 'get_row')) {
            return null;
        }

        $sql = $this->wpdb->prepare(
            "SELECT profile_key, token, rotated_at, expires_at, status, metadata, created_at, updated_at FROM {$this->table_name} WHERE profile_key = %s",
            $profile_key
        );

        $row = $this->wpdb->get_row($sql, ARRAY_A);

        if (!is_array($row)) {
            return null;
        }

        return $this->format_row($row);
    }

    public function get_all_tokens() {
        if ($this->use_memory_storage) {
            return array_values($this->memory_storage);
        }

        if (!method_exists($this->wpdb, 'get_results')) {
            return array();
        }

        $sql = "SELECT profile_key, token, rotated_at, expires_at, status, metadata, created_at, updated_at FROM {$this->table_name}";
        $results = $this->wpdb->get_results($sql, ARRAY_A);

        if (!is_array($results)) {
            return array();
        }

        return array_values(array_map(array($this, 'format_row'), $results));
    }

    public function save_token($profile_key, array $data) {
        $profile_key = $this->sanitize_profile_key($profile_key);

        if ('' === $profile_key) {
            return false;
        }

        $now = $this->current_timestamp();
        $existing = $this->use_memory_storage
            ? (isset($this->memory_storage[$profile_key]) ? $this->memory_storage[$profile_key] : null)
            : $this->get_token($profile_key);

        if (is_array($existing)) {
            if (!array_key_exists('token', $data)) {
                $data['token'] = isset($existing['token']) ? $existing['token'] : '';
            }
            if (!array_key_exists('rotated_at', $data)) {
                $data['rotated_at'] = isset($existing['rotated_at']) ? (int) $existing['rotated_at'] : 0;
            }
            if (!array_key_exists('expires_at', $data)) {
                $data['expires_at'] = isset($existing['expires_at']) ? (int) $existing['expires_at'] : 0;
            }
            if (!array_key_exists('status', $data)) {
                $data['status'] = isset($existing['status']) ? $existing['status'] : '';
            }
            if (!array_key_exists('metadata', $data)) {
                $data['metadata'] = isset($existing['metadata']) ? $existing['metadata'] : null;
            }
            if (!array_key_exists('created_at', $data)) {
                $data['created_at'] = isset($existing['created_at']) ? (int) $existing['created_at'] : 0;
            }
        }

        $normalized = $this->normalize_token_data($data);

        if (is_array($existing)) {
            if (empty($normalized['created_at'])) {
                $normalized['created_at'] = isset($existing['created_at']) ? (int) $existing['created_at'] : $now;
            }
        } elseif (empty($normalized['created_at'])) {
            $normalized['created_at'] = $now;
        }

        $normalized['updated_at'] = $now;

        if ($this->use_memory_storage) {
            $row = array(
                'profile_key' => $profile_key,
                'token'       => $normalized['token'],
                'rotated_at'  => $normalized['rotated_at'],
                'expires_at'  => $normalized['expires_at'],
                'status'      => $normalized['status'],
                'metadata'    => $normalized['metadata'],
                'created_at'  => $normalized['created_at'],
                'updated_at'  => $normalized['updated_at'],
            );

            $this->memory_storage[$profile_key] = $this->format_row($row);
            return true;
        }

        if (!method_exists($this->wpdb, 'insert') || !method_exists($this->wpdb, 'update')) {
            return false;
        }

        $db_row = $normalized;
        $db_row['profile_key'] = $profile_key;

        if (is_array($existing)) {
            $update = $db_row;
            unset($update['profile_key']);

            $formats = array(
                '%s', // token
                '%d', // rotated_at
                '%d', // expires_at
                '%s', // status
                '%s', // metadata
                '%d', // created_at
                '%d', // updated_at
            );

            return false !== $this->wpdb->update(
                $this->table_name,
                $update,
                array('profile_key' => $profile_key),
                $formats,
                array('%s')
            );
        }

        $formats = array(
            '%s', // profile_key
            '%s', // token
            '%d', // rotated_at
            '%d', // expires_at
            '%s', // status
            '%s', // metadata
            '%d', // created_at
            '%d', // updated_at
        );

        return false !== $this->wpdb->insert($this->table_name, $db_row, $formats);
    }

    public function delete_token($profile_key) {
        $profile_key = $this->sanitize_profile_key($profile_key);

        if ('' === $profile_key) {
            return false;
        }

        if ($this->use_memory_storage) {
            if (!isset($this->memory_storage[$profile_key])) {
                return false;
            }

            unset($this->memory_storage[$profile_key]);
            return true;
        }

        if (!method_exists($this->wpdb, 'delete')) {
            return false;
        }

        $deleted = $this->wpdb->delete(
            $this->table_name,
            array('profile_key' => $profile_key),
            array('%s')
        );

        return false !== $deleted;
    }

    private function format_row(array $row) {
        $row['profile_key'] = $this->sanitize_profile_key(isset($row['profile_key']) ? $row['profile_key'] : '');
        $row['token']       = isset($row['token']) ? (string) $row['token'] : '';
        $row['rotated_at']  = isset($row['rotated_at']) ? (int) $row['rotated_at'] : 0;
        $row['expires_at']  = isset($row['expires_at']) ? (int) $row['expires_at'] : 0;
        $row['status']      = $this->sanitize_status(isset($row['status']) ? $row['status'] : '');
        $row['metadata']    = $this->maybe_unserialize(isset($row['metadata']) ? $row['metadata'] : null);
        $row['created_at']  = isset($row['created_at']) ? (int) $row['created_at'] : 0;
        $row['updated_at']  = isset($row['updated_at']) ? (int) $row['updated_at'] : 0;

        return $row;
    }

    private function normalize_token_data(array $data) {
        $token      = isset($data['token']) ? (string) $data['token'] : '';
        $rotated_at = isset($data['rotated_at']) ? (int) $data['rotated_at'] : 0;
        $expires_at = isset($data['expires_at']) ? (int) $data['expires_at'] : 0;
        $status     = $this->sanitize_status(isset($data['status']) ? $data['status'] : '');
        $metadata   = isset($data['metadata']) ? $data['metadata'] : null;
        $created_at = isset($data['created_at']) ? (int) $data['created_at'] : 0;
        $updated_at = isset($data['updated_at']) ? (int) $data['updated_at'] : 0;

        return array(
            'token'      => $token,
            'rotated_at' => max(0, $rotated_at),
            'expires_at' => max(0, $expires_at),
            'status'     => $status,
            'metadata'   => $this->maybe_serialize($metadata),
            'created_at' => max(0, $created_at),
            'updated_at' => max(0, $updated_at),
        );
    }

    private function sanitize_profile_key($profile_key) {
        if (function_exists('discord_bot_jlg_sanitize_profile_key')) {
            return discord_bot_jlg_sanitize_profile_key($profile_key);
        }

        if (!is_string($profile_key)) {
            if (is_scalar($profile_key)) {
                $profile_key = (string) $profile_key;
            } else {
                return '';
            }
        }

        $profile_key = strtolower($profile_key);
        return preg_replace('/[^a-z0-9_-]/', '', $profile_key);
    }

    private function sanitize_status($status) {
        if (function_exists('sanitize_key')) {
            return sanitize_key($status);
        }

        if (!is_string($status)) {
            if (is_scalar($status)) {
                $status = (string) $status;
            } else {
                return '';
            }
        }

        $status = strtolower($status);
        return preg_replace('/[^a-z0-9_]/', '', $status);
    }

    private function maybe_serialize($value) {
        if (function_exists('maybe_serialize')) {
            return maybe_serialize($value);
        }

        if (is_array($value) || is_object($value)) {
            return serialize($value);
        }

        return $value;
    }

    private function maybe_unserialize($value) {
        if (function_exists('maybe_unserialize')) {
            return maybe_unserialize($value);
        }

        if (!is_string($value)) {
            return $value;
        }

        $trimmed = trim($value);
        if ('' === $trimmed) {
            return '';
        }

        $first = substr($trimmed, 0, 2);
        if ('a:' === $first || 'O:' === $first || 's:' === $first || 'i:' === $first || 'b:' === $first || 'd:' === $first || 'N;' === $trimmed) {
            $unserialized = @unserialize($trimmed);
            if (false !== $unserialized || 'b:0;' === $trimmed) {
                return $unserialized;
            }
        }

        return $value;
    }

    private function current_timestamp() {
        if (function_exists('current_time')) {
            return (int) current_time('timestamp', true);
        }

        return time();
    }
}
