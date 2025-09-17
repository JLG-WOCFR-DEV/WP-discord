(function () {
    'use strict';

    var ERROR_CLASS = 'discord-stats-error';

    function updateStatElement(container, selector, value, formatter) {
        if (value === null) {
            return;
        }

        var element = container.querySelector(selector);
        if (!element) {
            return;
        }

        element.textContent = formatter.format(value);
        element.style.transform = 'scale(1.2)';
        setTimeout(function () {
            element.style.transform = 'scale(1)';
        }, 300);
    }

    function updateStats(container, config, formatter) {
        var formData = new FormData();
        formData.append('action', config.action || 'refresh_discord_stats');
        formData.append('_ajax_nonce', config.nonce);

        fetch(config.ajaxUrl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
            .then(function (response) {
                return response.json();
            })
            .then(function (data) {
                if (!data || typeof data !== 'object') {
                    return;
                }

                if (!data.success) {
                    if (data.data && data.data.message) {
                        console.warn(data.data.message);
                    } else if (typeof data.data === 'string') {
                        console.warn(data.data);
                    }

                    if (container && container.classList) {
                        container.classList.add(ERROR_CLASS);
                    }

                    return;
                }

                if (!data.data || data.data.rate_limited) {
                    if (data.data && data.data.message) {
                        console.warn(data.data.message);
                    }

                    return;
                }

                var onlineValue = typeof data.data.online === 'number' ? data.data.online : null;
                var totalValue = typeof data.data.total === 'number' ? data.data.total : null;

                if (onlineValue === null && totalValue === null) {
                    return;
                }

                if (container && container.classList) {
                    container.classList.remove(ERROR_CLASS);
                }

                updateStatElement(container, '.discord-online .discord-number', onlineValue, formatter);
                updateStatElement(container, '.discord-total .discord-number', totalValue, formatter);
            })
            .catch(function (error) {
                console.error('Erreur lors de la mise à jour des statistiques Discord :', error);

                if (container && container.classList) {
                    container.classList.add(ERROR_CLASS);
                }
            });
    }

    function initializeDiscordBot() {
        if (typeof window.fetch !== 'function' || typeof window.FormData !== 'function') {
            return;
        }

        var config = window.discordBotJlg || {};
        if (!config.ajaxUrl || !config.nonce) {
            return;
        }

        var locale = config.locale || 'fr-FR';
        var formatter;

        var minIntervalSeconds = parseInt(config.minRefreshInterval, 10);
        if (isNaN(minIntervalSeconds) || minIntervalSeconds <= 0) {
            minIntervalSeconds = 10;
        }
        var minIntervalMs = minIntervalSeconds * 1000;

        try {
            formatter = new Intl.NumberFormat(locale);
        } catch (error) {
            formatter = new Intl.NumberFormat('fr-FR');
        }

        var containers = document.querySelectorAll('.discord-stats-container[data-refresh]');
        if (!containers.length) {
            return;
        }

        Array.prototype.forEach.call(containers, function (container) {
            if (container.dataset.demo === 'true') {
                return;
            }

            var interval = parseInt(container.dataset.refresh, 10);
            if (isNaN(interval) || interval <= 0) {
                return;
            }

            // L'attribut data-refresh est exprimé en secondes côté PHP.
            if (interval < minIntervalSeconds) {
                interval = minIntervalSeconds;
            }

            var intervalMs = interval * 1000;
            if (intervalMs < minIntervalMs) {
                intervalMs = minIntervalMs;
            }

            setInterval(function () {
                updateStats(container, config, formatter);
            }, intervalMs);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeDiscordBot);
    } else {
        initializeDiscordBot();
    }
})();
