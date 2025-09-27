<?php
$GLOBALS['wp_test_options']    = array();
$GLOBALS['wp_test_transients'] = array();
$GLOBALS['wp_test_current_action'] = 'wp_ajax_nopriv_refresh_discord_stats';

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

if (!defined('DAY_IN_SECONDS')) {
    define('DAY_IN_SECONDS', 86400);
}

require_once __DIR__ . '/../../inc/class-discord-http.php';
require_once __DIR__ . '/../../inc/class-discord-api.php';
require_once __DIR__ . '/../../inc/class-discord-widget.php';

if (!defined('DISCORD_BOT_JLG_OPTION_NAME')) {
    define('DISCORD_BOT_JLG_OPTION_NAME', 'discord_server_stats_options');
}

if (!class_exists('WP_Widget')) {
    class WP_Widget {
        public $id_base;

        public function __construct($id_base = '', $name = '', $widget_options = array()) {
            $this->id_base = $id_base;
        }

        public function get_field_id($field_name) {
            return $this->id_base . '-' . $field_name;
        }

        public function get_field_name($field_name) {
            return $this->id_base . '[' . $field_name . ']';
        }
    }
}

if (!function_exists('esc_html__')) {
    function esc_html__($text, $domain = null) {
        return $text;
    }
}

if (!function_exists('esc_html_e')) {
    function esc_html_e($text, $domain = null) {
        echo esc_html__($text, $domain);
    }
}

if (!function_exists('esc_html')) {
    function esc_html($text) {
        return is_string($text) ? $text : (string) $text;
    }
}

if (!function_exists('esc_attr')) {
    function esc_attr($text) {
        return is_string($text) ? $text : (string) $text;
    }
}

if (!function_exists('do_shortcode')) {
    function do_shortcode($shortcode) {
        $GLOBALS['discord_bot_jlg_last_shortcode'] = $shortcode;
        return $shortcode;
    }
}

if (!function_exists('current_time')) {
    function current_time($type, $gmt = 0) {
        $reference = isset($GLOBALS['wp_test_current_timestamp'])
            ? (int) $GLOBALS['wp_test_current_timestamp']
            : time();

        if ('timestamp' === $type) {
            if ($gmt) {
                return $reference;
            }

            $timezone_string = isset($GLOBALS['wp_test_timezone_string']) ? $GLOBALS['wp_test_timezone_string'] : 'UTC';

            try {
                $timezone = new DateTimeZone($timezone_string);
                $datetime = new DateTimeImmutable('@' . $reference);
                return $reference + $timezone->getOffset($datetime);
            } catch (Exception $exception) {
                return $reference;
            }
        }

        if ('mysql' === $type) {
            return gmdate('Y-m-d H:i:s', current_time('timestamp', (bool) $gmt));
        }

        return $reference;
    }
}

if (!function_exists('wp_date')) {
    function wp_date($format, $timestamp = null, $timezone = null) {
        if (null === $timestamp) {
            $timestamp = current_time('timestamp', true);
        }

        if (null === $timezone) {
            $timezone = isset($GLOBALS['wp_test_timezone_string']) ? $GLOBALS['wp_test_timezone_string'] : 'UTC';
        }

        if (!$timezone instanceof DateTimeZone) {
            try {
                $timezone = new DateTimeZone($timezone);
            } catch (Exception $exception) {
                $timezone = new DateTimeZone('UTC');
            }
        }

        $datetime = new DateTimeImmutable('@' . (int) $timestamp);
        $datetime = $datetime->setTimezone($timezone);

        return $datetime->format($format);
    }
}

function wp_parse_args($args, $defaults = array()) {
    if (is_object($args)) {
        $args = get_object_vars($args);
    }

    if (!is_array($args)) {
        $args = array();
    }

    if (!is_array($defaults)) {
        $defaults = array();
    }

    return array_merge($defaults, $args);
}

function get_option($name) {
    return isset($GLOBALS['wp_test_options'][$name]) ? $GLOBALS['wp_test_options'][$name] : false;
}

function set_transient($key, $value, $expiration) {
    $expiration = (int) $expiration;
    $expires_at = ($expiration > 0) ? time() + $expiration : 0;

    $GLOBALS['wp_test_transients'][$key] = array(
        'value'     => $value,
        'expires_at'=> $expires_at,
        'ttl'       => $expiration,
    );

    return true;
}

function get_transient($key) {
    if (!isset($GLOBALS['wp_test_transients'][$key])) {
        return false;
    }

    $entry = $GLOBALS['wp_test_transients'][$key];

    if ($entry['expires_at'] > 0 && $entry['expires_at'] <= time()) {
        unset($GLOBALS['wp_test_transients'][$key]);
        return false;
    }

    return $entry['value'];
}

function delete_transient($key) {
    unset($GLOBALS['wp_test_transients'][$key]);
    return true;
}

function wp_unslash($value) {
    return $value;
}

function sanitize_text_field($value) {
    if (is_array($value)) {
        return array_map('sanitize_text_field', $value);
    }

    return is_string($value) ? trim($value) : $value;
}

function sanitize_key($key) {
    $key = strtolower((string) $key);
    return preg_replace('/[^a-z0-9_]/', '', $key);
}

function current_action() {
    return $GLOBALS['wp_test_current_action'];
}

function wp_verify_nonce($nonce, $action) {
    return true;
}

function apply_filters($hook, $value, ...$args) {
    return $value;
}

function wp_validate_boolean($value) {
    if (is_bool($value)) {
        return $value;
    }

    if (is_string($value)) {
        $value = strtolower($value);
        return in_array($value, array('1', 'true', 'yes', 'on'), true);
    }

    return (bool) $value;
}

function current_user_can($capability) {
    return true;
}

function __($text, $domain = null) {
    return $text;
}

class WP_Error {
    private $message;

    public function __construct($code = '', $message = '') {
        $this->message = $message;
    }

    public function get_error_message() {
        return $this->message;
    }
}

function is_wp_error($thing) {
    return $thing instanceof WP_Error;
}

class WP_Send_JSON_Success extends Exception {
    public $data;

    public function __construct($data) {
        parent::__construct('success');
        $this->data = $data;
    }
}

class WP_Send_JSON_Error extends Exception {
    public $data;
    public $status_code;

    public function __construct($data, $status_code = null) {
        parent::__construct('error');
        $this->data        = $data;
        $this->status_code = $status_code;
    }
}

function wp_send_json_success($data) {
    throw new WP_Send_JSON_Success($data);
}

function wp_send_json_error($data, $status_code = null) {
    throw new WP_Send_JSON_Error($data, $status_code);
}

function wp_privacy_anonymize_ip($ip, $is_ipv6) {
    return $ip;
}

function wp_test_get_transient_entry($key) {
    return isset($GLOBALS['wp_test_transients'][$key]) ? $GLOBALS['wp_test_transients'][$key] : null;
}

if (!function_exists('wp_hash')) {
    function wp_hash($data, $scheme = 'auth') {
        if (!is_scalar($data) && null !== $data) {
            return false;
        }

        $salt_suffix = '';
        if (null !== $scheme) {
            $scheme      = sanitize_key($scheme);
            $salt_suffix = ($scheme !== '') ? ':' . $scheme : '';
        }

        $hash = hash_hmac('md5', (string) $data, 'wordpress-test-salt' . $salt_suffix);

        return (false === $hash) ? false : $hash;
    }
}

if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data, $options = 0, $depth = 512) {
        $options = (int) $options;
        $depth   = (int) $depth;

        if (defined('JSON_PARTIAL_OUTPUT_ON_ERROR')) {
            $options |= JSON_PARTIAL_OUTPUT_ON_ERROR;
        }

        $encoded = json_encode($data, $options, $depth);

        if (false !== $encoded) {
            return $encoded;
        }

        if (JSON_ERROR_UTF8 !== json_last_error()) {
            return false;
        }

        $prepared = wp_json_prepare_data($data);
        $encoded  = json_encode($prepared, $options, $depth);

        return (false === $encoded) ? false : $encoded;
    }

    function wp_json_prepare_data($data) {
        if (is_array($data)) {
            $output = array();
            foreach ($data as $key => $value) {
                $output[$key] = wp_json_prepare_data($value);
            }
            return $output;
        }

        if (is_object($data)) {
            foreach ($data as $key => $value) {
                $data->$key = wp_json_prepare_data($value);
            }
            return $data;
        }

        if (is_string($data)) {
            return wp_sanitize_utf8_string($data);
        }

        return $data;
    }

    function wp_sanitize_utf8_string($string) {
        $string = (string) $string;

        if ('' === $string) {
            return $string;
        }

        if (preg_match('//u', $string)) {
            return $string;
        }

        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'UTF-8//IGNORE', $string);
            if (false !== $converted) {
                return $converted;
            }
        }

        if (function_exists('mb_convert_encoding')) {
            $converted = @mb_convert_encoding($string, 'UTF-8', 'UTF-8');
            if (false !== $converted) {
                return $converted;
            }
        }

        return '';
    }
}
