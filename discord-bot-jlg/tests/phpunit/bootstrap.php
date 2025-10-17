<?php
$GLOBALS['wp_test_options']    = array();
$GLOBALS['wp_test_transients'] = array();
$GLOBALS['wp_test_current_action'] = 'wp_ajax_nopriv_refresh_discord_stats';
$GLOBALS['wp_test_filters'] = array();
$GLOBALS['wp_test_last_remote_request'] = null;
$GLOBALS['wp_test_nonce_validations'] = array();
$GLOBALS['wp_test_is_user_logged_in'] = false;

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

if (!defined('DAY_IN_SECONDS')) {
    define('DAY_IN_SECONDS', 86400);
}

if (!function_exists('get_file_data')) {
    function get_file_data($file, $default_headers, $context = '') {
        unset($context);

        $data = array();

        if (!is_array($default_headers)) {
            $default_headers = array();
        }

        foreach ($default_headers as $header_key => $header_name) {
            $data[$header_key] = '';
        }

        if (!is_readable($file)) {
            return $data;
        }

        $file_data = file_get_contents($file, false, null, 0, 8192);

        if (false === $file_data) {
            return $data;
        }

        foreach ($default_headers as $header_key => $header_name) {
            $pattern = '/^[ \t\/*#@]*' . preg_quote($header_name, '/') . ':(.*)$/mi';

            if (preg_match($pattern, $file_data, $matches) && isset($matches[1])) {
                $value = trim($matches[1]);
                $value = preg_replace('/[\r\n].*/', '', $value);
                $data[$header_key] = $value;
            }
        }

        return $data;
    }
}

if (!function_exists('plugin_dir_path')) {
    function plugin_dir_path($file) {
        return rtrim(dirname($file), '/\\') . '/';
    }
}

if (!function_exists('plugin_dir_url')) {
    function plugin_dir_url($file) {
        $path = trim(str_replace(dirname(dirname($file)), '', dirname($file)), '/\\');

        if ('' !== $path) {
            $path .= '/';
        }

        return 'https://example.com/wp-content/plugins/' . $path;
    }
}

if (!function_exists('plugin_basename')) {
    function plugin_basename($file) {
        return ltrim(str_replace('\\', '/', $file), '/');
    }
}

if (!function_exists('register_activation_hook')) {
    function register_activation_hook($file, $callback) {
        $GLOBALS['wp_test_activation_hooks'][$file] = $callback;
    }
}

if (!function_exists('register_deactivation_hook')) {
    function register_deactivation_hook($file, $callback) {
        $GLOBALS['wp_test_deactivation_hooks'][$file] = $callback;
    }
}

if (!function_exists('register_uninstall_hook')) {
    function register_uninstall_hook($file, $callback) {
        $GLOBALS['wp_test_uninstall_hooks'][$file] = $callback;
    }
}

if (!function_exists('load_plugin_textdomain')) {
    function load_plugin_textdomain($domain, $deprecated = false, $plugin_rel_path = '') {
        $GLOBALS['wp_test_loaded_textdomains'][$domain] = array(
            'path' => $plugin_rel_path,
        );
    }
}

if (!function_exists('wp_schedule_event')) {
    function wp_schedule_event($timestamp, $recurrence, $hook, $args = array()) {
        $GLOBALS['wp_test_scheduled_events'][] = array(
            'timestamp'  => $timestamp,
            'recurrence' => $recurrence,
            'hook'       => $hook,
            'args'       => $args,
        );

        return true;
    }
}

if (!function_exists('wp_schedule_single_event')) {
    function wp_schedule_single_event($timestamp, $hook, $args = array()) {
        return wp_schedule_event($timestamp, 'single', $hook, $args);
    }
}

if (!function_exists('wp_next_scheduled')) {
    function wp_next_scheduled($hook) {
        if (!isset($GLOBALS['wp_test_scheduled_events']) || !is_array($GLOBALS['wp_test_scheduled_events'])) {
            return false;
        }

        $next = false;

        foreach ($GLOBALS['wp_test_scheduled_events'] as $event) {
            if (!isset($event['hook']) || $event['hook'] !== $hook) {
                continue;
            }

            $timestamp = isset($event['timestamp']) ? (int) $event['timestamp'] : 0;

            if (0 === $timestamp) {
                continue;
            }

            if (false === $next || $timestamp < $next) {
                $next = $timestamp;
            }
        }

        return $next;
    }
}

if (!function_exists('wp_strip_all_tags')) {
    function wp_strip_all_tags($string, $remove_breaks = false) {
        $string = strip_tags((string) $string);

        if ($remove_breaks) {
            $string = preg_replace('/[\r\n\t ]+/', ' ', $string);
        }

        return trim($string);
    }
}

if (!function_exists('wp_clear_scheduled_hook')) {
    function wp_clear_scheduled_hook($hook) {
        if (!isset($GLOBALS['wp_test_scheduled_events']) || !is_array($GLOBALS['wp_test_scheduled_events'])) {
            return;
        }

        $GLOBALS['wp_test_scheduled_events'] = array_values(array_filter(
            $GLOBALS['wp_test_scheduled_events'],
            function ($event) use ($hook) {
                return isset($event['hook']) && $event['hook'] !== $hook;
            }
        ));
    }
}

if (!function_exists('register_block_type')) {
    function register_block_type($path, $args = array()) {
        $GLOBALS['wp_test_registered_blocks'][] = array(
            'path' => $path,
            'args' => $args,
        );

        return true;
    }
}

if (!function_exists('wp_set_script_translations')) {
    function wp_set_script_translations($handle, $domain, $path = '') {
        $GLOBALS['wp_test_script_translations'][$handle] = array(
            'domain' => $domain,
            'path'   => $path,
        );
    }
}

if (!function_exists('remove_query_arg')) {
    function remove_query_arg($keys, $query = false) {
        $keys = (array) $keys;

        if (is_array($query)) {
            foreach ($keys as $key) {
                if (array_key_exists($key, $query)) {
                    unset($query[$key]);
                }
            }

            return $query;
        }

        if (!is_string($query)) {
            return '';
        }

        $fragment = '';
        $fragment_position = strpos($query, '#');
        if (false !== $fragment_position) {
            $fragment = substr($query, $fragment_position);
            $query    = substr($query, 0, $fragment_position);
        }

        $base = $query;
        $query_string = '';

        $query_position = strpos($query, '?');
        if (false !== $query_position) {
            $base         = substr($query, 0, $query_position);
            $query_string = substr($query, $query_position + 1);
        }

        if ('' === $query_string) {
            return $base . $fragment;
        }

        parse_str($query_string, $params);

        if (!is_array($params)) {
            return $base . $fragment;
        }

        foreach ($keys as $key) {
            if (array_key_exists($key, $params)) {
                unset($params[$key]);
            }
        }

        if (empty($params)) {
            return $base . $fragment;
        }

        $new_query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);

        if ('' === $new_query) {
            return $base . $fragment;
        }

        return $base . '?' . $new_query . $fragment;
    }
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

if (!defined('AUTH_KEY')) {
    define('AUTH_KEY', 'tests-fixed-auth-key');
}

if (!defined('AUTH_SALT')) {
    define('AUTH_SALT', 'tests-fixed-auth-salt');
}

if (!function_exists('esc_html__')) {
    function esc_html__($text, $domain = null) {
        return $text;
    }
}

if (!function_exists('__')) {
    function __($text, $domain = null) {
        return $text;
    }
}

if (!function_exists('sanitize_title')) {
    function sanitize_title($title, $fallback_title = '', $context = 'save') {
        unset($fallback_title, $context);

        if (!is_string($title)) {
            if (is_scalar($title)) {
                $title = (string) $title;
            } else {
                return '';
            }
        }

        $title = strip_tags($title);
        $title = html_entity_decode($title, ENT_QUOTES, 'UTF-8');

        $converted = false;
        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $title);
        }

        if (false !== $converted && null !== $converted && '' !== $converted) {
            $title = $converted;
        } else {
            $replacements = array(
                'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a',
                'À' => 'a', 'Á' => 'a', 'Â' => 'a', 'Ã' => 'a', 'Ä' => 'a', 'Å' => 'a',
                'ç' => 'c', 'Ç' => 'c',
                'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
                'È' => 'e', 'É' => 'e', 'Ê' => 'e', 'Ë' => 'e',
                'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
                'Ì' => 'i', 'Í' => 'i', 'Î' => 'i', 'Ï' => 'i',
                'ñ' => 'n', 'Ñ' => 'n',
                'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o', 'ø' => 'o',
                'Ò' => 'o', 'Ó' => 'o', 'Ô' => 'o', 'Õ' => 'o', 'Ö' => 'o', 'Ø' => 'o',
                'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
                'Ù' => 'u', 'Ú' => 'u', 'Û' => 'u', 'Ü' => 'u',
                'ý' => 'y', 'ÿ' => 'y', 'Ý' => 'y',
            );

            $title = strtr($title, $replacements);
        }

        $title = strtolower($title);
        $title = preg_replace('/[^a-z0-9_\-\s]/', '', $title);
        $title = preg_replace('/[\s]+/', '-', $title);
        $title = trim($title, '-');

        return $title;
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

if (!function_exists('esc_url')) {
    function esc_url($url) {
        return is_string($url) ? filter_var($url, FILTER_SANITIZE_URL) : '';
    }
}

if (!function_exists('esc_url_raw')) {
    function esc_url_raw($url) {
        return esc_url($url);
    }
}

if (!function_exists('esc_attr')) {
    function esc_attr($text) {
        return is_string($text) ? $text : (string) $text;
    }
}

if (!function_exists('esc_attr__')) {
    function esc_attr__($text, $domain = null) {
        return esc_attr($text);
    }
}

if (!function_exists('do_shortcode')) {
    function do_shortcode($shortcode) {
        $GLOBALS['discord_bot_jlg_last_shortcode'] = $shortcode;
        return $shortcode;
    }
}

if (!function_exists('add_shortcode')) {
    function add_shortcode($tag, $callback) {
        $GLOBALS['wp_test_shortcodes'][$tag] = $callback;
    }
}

if (!function_exists('add_query_arg')) {
    function add_query_arg($key, $value = null, $url = '') {
        if (is_array($key)) {
            $params = $key;
            $url    = (string) $value;
        } else {
            $params = array($key => $value);
            $url    = (string) $url;
        }

        $parts = parse_url($url);

        if (false === $parts) {
            $parts = array('path' => $url);
        }

        $query = array();

        if (isset($parts['query'])) {
            parse_str($parts['query'], $query);
        }

        foreach ($params as $param_key => $param_value) {
            $query[$param_key] = $param_value;
        }

        $parts['query'] = http_build_query($query, '', '&', PHP_QUERY_RFC3986);

        $result = '';

        if (!empty($parts['scheme'])) {
            $result .= $parts['scheme'] . '://';
        }

        if (!empty($parts['host'])) {
            $result .= $parts['host'];
        }

        if (!empty($parts['port'])) {
            $result .= ':' . $parts['port'];
        }

        if (!empty($parts['path'])) {
            $result .= $parts['path'];
        }

        if ('' !== $parts['query']) {
            $result .= '?' . $parts['query'];
        }

        if (!empty($parts['fragment'])) {
            $result .= '#' . $parts['fragment'];
        }

        return $result;
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

if (!function_exists('date_i18n')) {
    function date_i18n($format, $timestamp = null, $gmt = false) {
        $GLOBALS['discord_bot_jlg_last_date_i18n_args'] = array(
            'format'    => $format,
            'timestamp' => $timestamp,
            'gmt'       => $gmt,
        );

        if (null === $timestamp) {
            $timestamp = current_time('timestamp', (bool) $gmt);
        }

        $formatted = gmdate($format, (int) $timestamp);

        return apply_filters('date_i18n', $formatted, $format, $timestamp, $gmt);
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

function update_option($name, $value, $autoload = null) {
    $GLOBALS['wp_test_options'][$name] = $value;

    return true;
}

function add_option($name, $value = '', $deprecated = '', $autoload = 'yes') {
    if (isset($GLOBALS['wp_test_options'][$name])) {
        return false;
    }

    $GLOBALS['wp_test_options'][$name] = $value;

    return true;
}

function delete_option($name) {
    if (isset($GLOBALS['wp_test_options'][$name])) {
        unset($GLOBALS['wp_test_options'][$name]);
        return true;
    }

    return false;
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
    $key = preg_replace('/\s+/', '_', $key);
    $key = preg_replace('/[^a-z0-9_\-]/', '', $key);
    $key = trim($key, '_-');

    return $key;
}

function shortcode_atts($pairs, $atts, $shortcode = '') {
    $atts = (array) $atts;
    $out  = array();

    foreach ($pairs as $name => $default) {
        if (array_key_exists($name, $atts)) {
            $out[$name] = $atts[$name];
        } else {
            $out[$name] = $default;
        }
    }

    foreach ($atts as $name => $value) {
        if (!array_key_exists($name, $pairs)) {
            $out[$name] = $value;
        }
    }

    return $out;
}

function sanitize_html_class($class, $fallback = '') {
    $class = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $class);

    if ('' === $class && '' !== $fallback) {
        $class = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $fallback);
    }

    return strtolower($class);
}

function number_format_i18n($number, $decimals = 0) {
    return number_format((float) $number, (int) $decimals, ',', ' ');
}

$GLOBALS['wp_test_registered_styles'] = array();
$GLOBALS['wp_test_enqueued_styles']   = array();
$GLOBALS['wp_test_inline_styles']     = array();
$GLOBALS['wp_test_registered_scripts'] = array();
$GLOBALS['wp_test_enqueued_scripts']   = array();
$GLOBALS['wp_test_localized_scripts']  = array();
$GLOBALS['wp_test_inline_scripts']     = array();

function wp_register_style($handle, $src = '', $deps = array(), $ver = false, $media = 'all') {
    $GLOBALS['wp_test_registered_styles'][$handle] = array(
        'src'   => $src,
        'deps'  => $deps,
        'ver'   => $ver,
        'media' => $media,
    );

    return true;
}

function wp_enqueue_style($handle) {
    $GLOBALS['wp_test_enqueued_styles'][$handle] = true;
    return true;
}

function wp_add_inline_style($handle, $css) {
    if (!isset($GLOBALS['wp_test_inline_styles'][$handle])) {
        $GLOBALS['wp_test_inline_styles'][$handle] = array();
    }

    $GLOBALS['wp_test_inline_styles'][$handle][] = (string) $css;
    return true;
}

function wp_register_script($handle, $src = '', $deps = array(), $ver = false, $in_footer = false) {
    $GLOBALS['wp_test_registered_scripts'][$handle] = array(
        'src'       => $src,
        'deps'      => $deps,
        'ver'       => $ver,
        'in_footer' => $in_footer,
    );

    return true;
}

function wp_enqueue_script($handle) {
    $GLOBALS['wp_test_enqueued_scripts'][$handle] = true;
    return true;
}

function wp_add_inline_script($handle, $data, $position = 'after') {
    if (!isset($GLOBALS['wp_test_inline_scripts'][$handle])) {
        $GLOBALS['wp_test_inline_scripts'][$handle] = array();
    }

    $GLOBALS['wp_test_inline_scripts'][$handle][] = array(
        'data'     => (string) $data,
        'position' => $position,
    );

    return true;
}

function wp_localize_script($handle, $object_name, $l10n) {
    $GLOBALS['wp_test_localized_scripts'][$handle] = array(
        'object_name' => $object_name,
        'data'        => $l10n,
    );

    return true;
}

function wp_style_is($handle, $list = 'enqueued') {
    if ('enqueued' === $list) {
        return !empty($GLOBALS['wp_test_enqueued_styles'][$handle]);
    }

    return false;
}

function wp_print_styles($handle = '') {
    return true;
}

function is_user_logged_in() {
    return !empty($GLOBALS['wp_test_is_user_logged_in']);
}

function admin_url($path = '', $scheme = 'admin') {
    $base = 'https://example.com/wp-admin/';

    return $base . ltrim((string) $path, '/');
}

function rest_url($path = '', $scheme = 'rest') {
    $base = 'https://example.com/wp-json/';

    return $base . ltrim((string) $path, '/');
}

function wp_create_nonce($action = -1) {
    return 'test-nonce';
}

function get_locale() {
    return 'fr_FR';
}

function current_action() {
    return $GLOBALS['wp_test_current_action'];
}

function wp_verify_nonce($nonce, $action) {
    if (isset($GLOBALS['wp_test_nonce_validations']) && is_array($GLOBALS['wp_test_nonce_validations'])) {
        if (array_key_exists($action, $GLOBALS['wp_test_nonce_validations'])) {
            return (bool) $GLOBALS['wp_test_nonce_validations'][$action];
        }
    }

    return true;
}

function add_filter($hook, $callback, $priority = 10, $accepted_args = 1) {
    if (!is_string($hook) || '' === $hook) {
        return false;
    }

    if (!isset($GLOBALS['wp_test_filters'][$hook])) {
        $GLOBALS['wp_test_filters'][$hook] = array();
    }

    if (!isset($GLOBALS['wp_test_filters'][$hook][$priority])) {
        $GLOBALS['wp_test_filters'][$hook][$priority] = array();
    }

    $GLOBALS['wp_test_filters'][$hook][$priority][] = array(
        'callback'      => $callback,
        'accepted_args' => max(1, (int) $accepted_args),
    );

    return true;
}

function remove_filter($hook, $callback, $priority = 10) {
    if (!isset($GLOBALS['wp_test_filters'][$hook][$priority])) {
        return false;
    }

    foreach ($GLOBALS['wp_test_filters'][$hook][$priority] as $index => $registered) {
        if ($registered['callback'] === $callback) {
            unset($GLOBALS['wp_test_filters'][$hook][$priority][$index]);

            if (empty($GLOBALS['wp_test_filters'][$hook][$priority])) {
                unset($GLOBALS['wp_test_filters'][$hook][$priority]);
            }

            if (empty($GLOBALS['wp_test_filters'][$hook])) {
                unset($GLOBALS['wp_test_filters'][$hook]);
            }

            return true;
        }
    }

    return false;
}

function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {
    return add_filter($hook, $callback, $priority, $accepted_args);
}

function remove_all_filters($hook) {
    if (isset($GLOBALS['wp_test_filters'][$hook])) {
        unset($GLOBALS['wp_test_filters'][$hook]);
    }
}

function do_action($hook, ...$args) {
    if (!isset($GLOBALS['wp_test_filters'][$hook])) {
        return;
    }

    ksort($GLOBALS['wp_test_filters'][$hook]);

    foreach ($GLOBALS['wp_test_filters'][$hook] as $priority => $callbacks) {
        foreach ($callbacks as $callback) {
            $params = array();

            if ($callback['accepted_args'] > 0) {
                $params = array_slice($args, 0, $callback['accepted_args']);
            }

            call_user_func_array($callback['callback'], $params);
        }
    }
}

function apply_filters($hook, $value, ...$args) {
    if (!isset($GLOBALS['wp_test_filters'][$hook])) {
        return $value;
    }

    $args = array_values($args);
    ksort($GLOBALS['wp_test_filters'][$hook]);

    foreach ($GLOBALS['wp_test_filters'][$hook] as $priority => $callbacks) {
        foreach ($callbacks as $callback) {
            $params = array($value);

            if ($callback['accepted_args'] > 1) {
                $params = array_merge(
                    $params,
                    array_slice($args, 0, $callback['accepted_args'] - 1)
                );
            }

            $value = call_user_func_array($callback['callback'], $params);
        }
    }

    return $value;
}

require_once __DIR__ . '/../../discord-bot-jlg.php';

function wp_safe_remote_get($url, $args = array()) {
    $GLOBALS['wp_test_last_remote_request'] = array(
        'url'  => $url,
        'args' => $args,
    );

    return array(
        'response' => array(
            'code'    => 200,
            'message' => 'OK',
        ),
        'body'    => '',
        'headers' => array(),
    );
}

function current_user_can($capability) {
    if (isset($GLOBALS['wp_test_current_user_can'])) {
        $current_user_can = $GLOBALS['wp_test_current_user_can'];

        if (is_callable($current_user_can)) {
            return (bool) call_user_func($current_user_can, $capability);
        }

        if (is_array($current_user_can)) {
            if (array_key_exists($capability, $current_user_can)) {
                return (bool) $current_user_can[$capability];
            }

            if (array_key_exists('*', $current_user_can)) {
                return (bool) $current_user_can['*'];
            }

            if (
                'manage_options' !== $capability
                && array_key_exists('manage_options', $current_user_can)
            ) {
                return (bool) $current_user_can['manage_options'];
            }
        }

        if (is_bool($current_user_can)) {
            return $current_user_can;
        }
    }

    return true;
}

if (!function_exists('__return_true')) {
    function __return_true() {
        return true;
    }
}

if (!function_exists('__return_false')) {
    function __return_false() {
        return false;
    }
}

if (!class_exists('WP_REST_Server')) {
    class WP_REST_Server {
        const READABLE = 'GET';
        const CREATABLE = 'POST';
        const EDITABLE = 'PUT';
        const DELETABLE = 'DELETE';
    }
}

if (!class_exists('WP_REST_Request')) {
    class WP_REST_Request {
        private $params = array();
        private $headers = array();
        private $route = '';
        private $body = '';
        private $json_params = array();

        public function __construct($method = 'GET', $route = '') {
            $this->params  = array();
            $this->headers = array();
            $this->route   = is_string($route) ? $route : '';
            $this->body    = '';
            $this->json_params = array();
        }

        public function set_param($key, $value) {
            $this->params[$key] = $value;
        }

        public function get_param($key) {
            return isset($this->params[$key]) ? $this->params[$key] : null;
        }

        public function get_params() {
            return $this->params;
        }

        public function set_body($body) {
            $this->body = (string) $body;
        }

        public function get_body() {
            return $this->body;
        }

        public function set_json_params($params) {
            $this->json_params = is_array($params) ? $params : array();
        }

        public function get_json_params() {
            if (!empty($this->json_params)) {
                return $this->json_params;
            }

            if ('' === $this->body) {
                return array();
            }

            $decoded = json_decode($this->body, true);
            return is_array($decoded) ? $decoded : array();
        }

        public function set_header($key, $value) {
            if (!is_string($key)) {
                return;
            }

            $this->headers[strtolower($key)] = $value;
        }

        public function get_header($key) {
            if (!is_string($key)) {
                return '';
            }

            $normalized = strtolower($key);

            return isset($this->headers[$normalized]) ? $this->headers[$normalized] : '';
        }

        public function set_route($route) {
            if (!is_string($route)) {
                return;
            }

            $this->route = $route;
        }

        public function get_route() {
            return $this->route;
        }
    }
}

if (!class_exists('WP_REST_Response')) {
    class WP_REST_Response {
        protected $data;
        protected $status;
        protected $headers = array();

        public function __construct($data = null, $status = 200, $headers = array()) {
            $this->data    = $data;
            $this->status  = (int) $status;
            $this->headers = array();

            if (is_array($headers)) {
                foreach ($headers as $key => $value) {
                    $this->header($key, $value);
                }
            }
        }

        public function get_data() {
            return $this->data;
        }

        public function set_data($data) {
            $this->data = $data;
        }

        public function get_status() {
            return (int) $this->status;
        }

        public function set_status($status) {
            $this->status = (int) $status;
        }

        public function header($key, $value) {
            $key = strtolower((string) $key);
            $this->headers[$key] = $value;

            return $this;
        }

        public function get_headers() {
            return $this->headers;
        }
    }
}

function rest_ensure_response($response) {
    if ($response instanceof WP_REST_Response) {
        return $response;
    }

    return new WP_REST_Response($response);
}

function register_rest_route($namespace, $route, $args = array(), $override = false) {
    if (!isset($GLOBALS['wp_test_rest_routes'])) {
        $GLOBALS['wp_test_rest_routes'] = array();
    }

    $GLOBALS['wp_test_rest_routes'][] = array(
        'namespace' => $namespace,
        'route'     => $route,
        'args'      => $args,
        'override'  => $override,
    );

    return true;
}

if (!function_exists('wp_validate_boolean')) {
    try {
        $api = new Discord_Bot_JLG_API('discord_bot_jlg_test_option', 'discord_bot_jlg_test_cache');

        $stats = $api->get_stats(
            array(
                'force_demo'   => 'true',
                'bypass_cache' => 'false',
            )
        );
    } catch (Throwable $throwable) {
        throw new RuntimeException(
            'Discord Bot JLG API should load without wp_validate_boolean.',
            0,
            $throwable
        );
    }

    if (!is_array($stats)) {
        throw new RuntimeException('Discord Bot JLG API should load without wp_validate_boolean.');
    }
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

class WP_Error {
    private $code;
    private $message;
    private $data;

    public function __construct($code = '', $message = '', $data = null) {
        $this->code    = (string) $code;
        $this->message = $message;
        $this->data    = $data;
    }

    public function get_error_code() {
        return $this->code;
    }

    public function get_error_message() {
        return $this->message;
    }

    public function get_error_data() {
        return $this->data;
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
