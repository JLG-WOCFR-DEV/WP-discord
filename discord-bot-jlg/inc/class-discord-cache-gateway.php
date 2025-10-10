<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Centralise les interactions avec l'API de cache WordPress.
 */
class Discord_Bot_JLG_Cache_Gateway {

    /**
     * Récupère une valeur depuis le cache transitoire.
     *
     * @param string $cache_key Clé de cache.
     *
     * @return mixed Valeur stockée ou false lorsqu'absente.
     */
    public function get($cache_key) {
        $cache_key = (string) $cache_key;

        if ('' === $cache_key) {
            return false;
        }

        return get_transient($cache_key);
    }

    /**
     * Stocke une valeur dans le cache transitoire.
     *
     * @param string $cache_key  Clé de cache.
     * @param mixed  $value      Valeur à stocker.
     * @param int    $expiration Durée de vie en secondes.
     *
     * @return void
     */
    public function set($cache_key, $value, $expiration) {
        $cache_key = (string) $cache_key;

        if ('' === $cache_key) {
            return;
        }

        $expiration = max(0, (int) $expiration);

        set_transient($cache_key, $value, $expiration);
    }

    /**
     * Supprime une valeur du cache transitoire.
     *
     * @param string $cache_key Clé de cache.
     *
     * @return void
     */
    public function delete($cache_key) {
        $cache_key = (string) $cache_key;

        if ('' === $cache_key) {
            return;
        }

        delete_transient($cache_key);
    }
}
