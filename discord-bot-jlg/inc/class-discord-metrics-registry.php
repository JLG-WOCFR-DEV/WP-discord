<?php
if (!defined('ABSPATH')) {
    exit;
}

class Discord_Bot_JLG_Metrics_Registry {
    const OPTION_NAME = 'discord_bot_jlg_metrics_state';

    /**
     * @var string
     */
    private $option_name;

    /**
     * @var array
     */
    private $state = array();

    public function __construct($option_name = self::OPTION_NAME) {
        $this->option_name = (string) $option_name;
        $this->state       = $this->load_state();
    }

    public function record_http_request($response, $url, $args, $context, $request_id, $duration_ms) {
        $context = sanitize_key($context);
        if ('' === $context) {
            $context = 'default';
        }

        $outcome     = 'success';
        $status_code = 0;
        $quota       = null;

        if (is_wp_error($response)) {
            $outcome = 'error';
        } elseif (is_array($response)) {
            if (function_exists('wp_remote_retrieve_response_code')) {
                $status_code = (int) wp_remote_retrieve_response_code($response);
            }

            if ($status_code >= 400) {
                $outcome = 'error';
            }

            if (function_exists('wp_remote_retrieve_header')) {
                $remaining_header = wp_remote_retrieve_header($response, 'x-ratelimit-remaining');
                if ('' !== $remaining_header && null !== $remaining_header) {
                    $quota = (int) $remaining_header;
                }
            }
        }

        if (!isset($this->state['http'])) {
            $this->state['http'] = $this->default_http_state();
        }

        if (!isset($this->state['http']['by_context'][$context])) {
            $this->state['http']['by_context'][$context] = $this->default_http_context_state();
        }

        if (!isset($this->state['http']['by_context'][$context]['total'][$outcome])) {
            $this->state['http']['by_context'][$context]['total'][$outcome] = 0;
        }

        $this->state['http']['by_context'][$context]['total'][$outcome]++;

        if (!isset($this->state['http']['total'][$outcome])) {
            $this->state['http']['total'][$outcome] = 0;
        }
        $this->state['http']['total'][$outcome]++;

        $duration_ms = max(0, (int) $duration_ms);
        $this->state['http']['duration_ms_sum']   += $duration_ms;
        $this->state['http']['duration_ms_count'] += 1;

        if ($status_code > 0) {
            if (!isset($this->state['http']['status'][$status_code])) {
                $this->state['http']['status'][$status_code] = 0;
            }
            $this->state['http']['status'][$status_code]++;
        }

        if (null !== $quota) {
            $this->state['http']['quota']['last'] = $quota;

            if (null === $this->state['http']['quota']['min'] || $quota < $this->state['http']['quota']['min']) {
                $this->state['http']['quota']['min'] = $quota;
            }
        }

        $this->persist_state();
    }

    public function record_event($event) {
        if (!is_array($event) || empty($event['type'])) {
            return;
        }

        $type = sanitize_key($event['type']);
        if ('' === $type) {
            return;
        }

        if (!isset($this->state['events'])) {
            $this->state['events'] = $this->default_event_state();
        }

        if (!isset($this->state['events']['by_type'][$type])) {
            $this->state['events']['by_type'][$type] = 0;
        }

        $this->state['events']['by_type'][$type]++;
        $this->state['events']['total']++;

        $this->persist_state();
    }

    public function get_state() {
        if (!isset($this->state['http'])) {
            $this->state['http'] = $this->default_http_state();
        }

        if (!isset($this->state['events'])) {
            $this->state['events'] = $this->default_event_state();
        }

        return $this->state;
    }

    public function reset() {
        $this->state = array(
            'http'   => $this->default_http_state(),
            'events' => $this->default_event_state(),
        );

        update_option($this->option_name, $this->state);
    }

    private function default_http_state() {
        return array(
            'total'            => array('success' => 0, 'error' => 0),
            'by_context'       => array(),
            'status'           => array(),
            'duration_ms_sum'  => 0,
            'duration_ms_count'=> 0,
            'quota'            => array(
                'last' => null,
                'min'  => null,
            ),
        );
    }

    private function default_http_context_state() {
        return array(
            'total' => array('success' => 0, 'error' => 0),
        );
    }

    private function default_event_state() {
        return array(
            'total'   => 0,
            'by_type' => array(),
        );
    }

    private function load_state() {
        $state = get_option($this->option_name);

        if (!is_array($state)) {
            $state = array();
        }

        return $state;
    }

    private function persist_state() {
        update_option($this->option_name, $this->state);
    }
}
