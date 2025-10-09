<?php
if (!defined('ABSPATH')) {
    exit;
}

class Discord_Bot_JLG_Event_Logger {
    const OPTION_NAME = 'discord_bot_jlg_event_log';
    const DEFAULT_MAX_ENTRIES = 200;
    const DEFAULT_RETENTION = 604800; // 7 days.

    /**
     * @var string
     */
    private $option_name;

    /**
     * @var int
     */
    private $max_entries;

    /**
     * @var int
     */
    private $default_retention;

    public function __construct($option_name = self::OPTION_NAME, $max_entries = self::DEFAULT_MAX_ENTRIES, $default_retention = self::DEFAULT_RETENTION) {
        $this->option_name = (string) $option_name;

        $max_entries = (int) apply_filters('discord_bot_jlg_event_log_max_entries', $max_entries, $this->option_name);
        if ($max_entries < 50) {
            $max_entries = self::DEFAULT_MAX_ENTRIES;
        }
        $this->max_entries = $max_entries;

        $default_retention = (int) apply_filters('discord_bot_jlg_event_log_default_retention', $default_retention, $this->option_name);
        if ($default_retention < 0) {
            $default_retention = 0;
        }
        $this->default_retention = $default_retention;
    }

    public function get_option_name() {
        return $this->option_name;
    }

    public function log($type, array $context = array()) {
        $state = $this->load_state();

        $sequence = isset($state['sequence']) ? (int) $state['sequence'] : 0;
        $sequence++;

        $event = array(
            'id'        => $sequence,
            'timestamp' => $this->current_timestamp(),
            'type'      => $this->sanitize_type($type),
            'context'   => $this->sanitize_context($context),
        );

        $state['sequence'] = $sequence;
        if (!isset($state['events']) || !is_array($state['events'])) {
            $state['events'] = array();
        }

        $state['events'][] = $event;

        $state['events'] = $this->truncate_events($state['events']);

        $purged = $this->purge_from_state($state, $this->default_retention);
        if ($purged > 0) {
            $state['events'] = $this->truncate_events($state['events']);
        }

        $this->persist_state($state);

        /**
         * Signale l'enregistrement d'un nouvel événement.
         *
         * @param array $event Événement juste enregistré.
         */
        do_action('discord_bot_jlg_event_logged', $event);

        return $event;
    }

    public function get_events($args = array()) {
        $defaults = array(
            'limit'    => 50,
            'type'     => '',
            'after_id' => 0,
            'after'    => 0,
        );

        $args = wp_parse_args($args, $defaults);
        $limit = (int) $args['limit'];
        if ($limit <= 0) {
            $limit = $defaults['limit'];
        } elseif ($limit > $this->max_entries) {
            $limit = $this->max_entries;
        }

        $raw_type_filter = isset($args['type']) ? $args['type'] : '';
        $type_filter = $this->sanitize_type($raw_type_filter);
        $after_id = max(0, (int) $args['after_id']);
        $after_timestamp = max(0, (int) $args['after']);

        $state = $this->load_state();
        $events = isset($state['events']) && is_array($state['events']) ? $state['events'] : array();


        if ('' !== trim((string) $raw_type_filter)) {
            $events = array_filter(
                $events,
                function ($event) use ($type_filter) {
                    return isset($event['type']) && $event['type'] === $type_filter;
                }
            );
        }

        if ($after_id > 0 || $after_timestamp > 0) {
            $events = array_filter(
                $events,
                function ($event) use ($after_id, $after_timestamp) {
                    if (!is_array($event)) {
                        return false;
                    }

                    if ($after_id > 0 && (!isset($event['id']) || (int) $event['id'] <= $after_id)) {
                        return false;
                    }

                    if ($after_timestamp > 0 && (!isset($event['timestamp']) || (int) $event['timestamp'] <= $after_timestamp)) {
                        return false;
                    }

                    return true;
                }
            );
        }

        $events = array_values($events);
        $events = array_reverse($events);

        if (count($events) > $limit) {
            $events = array_slice($events, 0, $limit);
        }

        return $events;
    }

    public function purge($max_age_seconds) {
        $max_age_seconds = (int) $max_age_seconds;
        if ($max_age_seconds <= 0) {
            return 0;
        }

        $state = $this->load_state();
        $purged = $this->purge_from_state($state, $max_age_seconds);

        if ($purged > 0) {
            $state['events'] = $this->truncate_events($state['events']);
            $this->persist_state($state);
        }

        return $purged;
    }

    public function reset() {
        delete_option($this->option_name);
    }

    private function truncate_events($events) {
        if (!is_array($events)) {
            return array();
        }

        $count = count($events);
        if ($count <= $this->max_entries) {
            return array_values($events);
        }

        $offset = $count - $this->max_entries;
        if ($offset <= 0) {
            return array_values($events);
        }

        return array_slice($events, $offset);
    }

    private function load_state() {
        $state = get_option($this->option_name);

        if (!is_array($state)) {
            $state = array(
                'sequence' => 0,
                'events'   => array(),
            );
        }

        if (!isset($state['events']) || !is_array($state['events'])) {
            $state['events'] = array();
        }

        if (!isset($state['sequence'])) {
            $state['sequence'] = 0;
        }

        return $state;
    }

    private function persist_state($state) {
        update_option($this->option_name, $state, false);
    }

    private function sanitize_type($type) {
        $sanitized = sanitize_key($type);

        if ('' === $sanitized) {
            $sanitized = 'general';
        }

        return $sanitized;
    }

    private function sanitize_context(array $context, $depth = 0) {
        if ($depth >= 5) {
            return array();
        }

        $sanitized = array();

        foreach ($context as $key => $value) {
            if (is_int($key)) {
                $sanitized_key = $key;
            } else {
                $sanitized_key = sanitize_key($key);
                if ('' === $sanitized_key) {
                    continue;
                }
            }

            if (is_array($value)) {
                $sanitized[$sanitized_key] = $this->sanitize_context($value, $depth + 1);
                continue;
            }

            if ($value instanceof DateTimeInterface) {
                $sanitized[$sanitized_key] = $value->format(DateTimeInterface::ATOM);
                continue;
            }

            if (is_object($value)) {
                $value = (array) $value;
                $sanitized[$sanitized_key] = $this->sanitize_context($value, $depth + 1);
                continue;
            }

            if (is_bool($value)) {
                $sanitized[$sanitized_key] = (bool) $value;
            } elseif (is_int($value) || is_float($value)) {
                $sanitized[$sanitized_key] = $value + 0;
            } elseif (null === $value) {
                $sanitized[$sanitized_key] = null;
            } else {
                $string_value = wp_strip_all_tags((string) $value);
                if (strlen($string_value) > 500) {
                    $string_value = substr($string_value, 0, 497) . '…';
                }
                $sanitized[$sanitized_key] = $string_value;
            }
        }

        return $sanitized;
    }

    private function purge_from_state(&$state, $max_age_seconds) {
        $max_age_seconds = (int) $max_age_seconds;
        if ($max_age_seconds <= 0) {
            return 0;
        }

        $threshold = $this->current_timestamp() - $max_age_seconds;
        $original_count = isset($state['events']) && is_array($state['events']) ? count($state['events']) : 0;

        if ($original_count <= 0) {
            return 0;
        }

        $state['events'] = array_values(array_filter(
            $state['events'],
            function ($event) use ($threshold) {
                if (!is_array($event) || !isset($event['timestamp'])) {
                    return false;
                }

                return (int) $event['timestamp'] >= $threshold;
            }
        ));

        $removed = $original_count - count($state['events']);

        return ($removed > 0) ? $removed : 0;
    }

    private function current_timestamp() {
        if (function_exists('current_time')) {
            return (int) current_time('timestamp', true);
        }

        return time();
    }
}
