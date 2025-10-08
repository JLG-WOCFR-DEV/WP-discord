<?php

if (!defined('ABSPATH')) {
    exit;
}

class Discord_Bot_JLG_Options_Repository {

    /**
     * Name of the WordPress option storing the plugin configuration.
     *
     * @var string
     */
    private $option_name;

    /**
     * Cached copy of the options to avoid repeated lookups.
     *
     * @var array|null
     */
    private $options_cache;

    /**
     * Callable returning the default options for the plugin.
     *
     * @var callable|null
     */
    private $default_options_provider;

    /**
     * Default retention value used when no configuration is provided.
     *
     * @var int
     */
    private $default_retention_days;

    /**
     * @param string        $option_name              WordPress option name storing the plugin settings.
     * @param callable|null $default_options_provider Optional callable returning the default options array.
     * @param int|null      $default_retention_days   Optional default retention value in days.
     */
    public function __construct($option_name, $default_options_provider = null, $default_retention_days = null) {
        $this->option_name = (string) $option_name;
        $this->options_cache = null;
        $this->default_options_provider = is_callable($default_options_provider)
            ? $default_options_provider
            : null;
        if (null !== $default_retention_days) {
            $this->default_retention_days = (int) $default_retention_days;
        } elseif (defined('DISCORD_BOT_JLG_ANALYTICS_RETENTION_DEFAULT')) {
            $this->default_retention_days = (int) DISCORD_BOT_JLG_ANALYTICS_RETENTION_DEFAULT;
        } elseif (class_exists('Discord_Bot_JLG_Analytics')) {
            $this->default_retention_days = (int) Discord_Bot_JLG_Analytics::DEFAULT_RETENTION_DAYS;
        } else {
            $this->default_retention_days = 90;
        }
    }

    /**
     * Clears the cached options so the next call fetches fresh values.
     *
     * @return void
     */
    public function flush_cache() {
        $this->options_cache = null;
    }

    /**
     * Retrieves the plugin options from WordPress, merging them with defaults when available.
     *
     * @param bool $force_refresh Whether to bypass the in-memory cache.
     *
     * @return array
     */
    public function get_options($force_refresh = false) {
        if (true === $force_refresh || !is_array($this->options_cache)) {
            $options = $this->read_from_database();

            if (!is_array($options)) {
                $options = array();
            }

            $defaults = $this->get_default_options();

            if (!empty($defaults)) {
                $options = array_merge($defaults, $options);
            }

            $this->options_cache = $options;
        }

        $options = $this->options_cache;

        if (function_exists('apply_filters')) {
            $filtered = apply_filters('option_' . $this->option_name, $options, $this->option_name);

            if (is_array($filtered)) {
                $options = $filtered;
            }
        }

        return $options;
    }

    /**
     * Returns the analytics retention period in days.
     *
     * @param array|null $options Optional pre-fetched options array.
     *
     * @return int
     */
    public function get_analytics_retention_days($options = null) {
        if (!is_array($options)) {
            $options = $this->get_options();
        }

        $retention = isset($options['analytics_retention_days'])
            ? (int) $options['analytics_retention_days']
            : $this->default_retention_days;

        if ($retention < 0) {
            return 0;
        }

        return $retention;
    }

    /**
     * Reads the plugin options from the database without applying any transformation.
     *
     * @return array
     */
    private function read_from_database() {
        $options = get_option($this->option_name);

        if (!is_array($options)) {
            return array();
        }

        return $options;
    }

    /**
     * Returns the default options from the configured provider, if any.
     *
     * @return array
     */
    private function get_default_options() {
        if (null === $this->default_options_provider) {
            return array();
        }

        $defaults = call_user_func($this->default_options_provider);

        if (!is_array($defaults)) {
            return array();
        }

        return $defaults;
    }
}
