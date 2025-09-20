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
                    if (data.data && data.data.nonce_expired) {
                        if (data.data.new_nonce) {
                            config.nonce = data.data.new_nonce;
                        }

                        if (container && container.classList) {
                            container.classList.remove(ERROR_CLASS);
                        }

                        // Les caches frontaux peuvent invalider les requêtes POST :
                        // en cas de nonce expiré on relance immédiatement avec le nouveau jeton.
                        updateStats(container, config, formatter);

                        return;
                    }

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

                var hasTotalInfo = data.data && typeof data.data.has_total !== 'undefined';
                var onlineValue = typeof data.data.online === 'number' ? data.data.online : null;
                var totalValue = typeof data.data.total === 'number' ? data.data.total : null;

                if (onlineValue === null && totalValue === null && !hasTotalInfo) {
                    return;
                }

                if (container && container.classList) {
                    container.classList.remove(ERROR_CLASS);
                }

                updateStatElement(container, '.discord-online .discord-number', onlineValue, formatter);

                var totalElement = container.querySelector('.discord-total');
                if (totalElement) {
                    var placeholder = totalElement.dataset && totalElement.dataset.placeholder ? totalElement.dataset.placeholder : '—';
                    var totalLabel = totalElement.dataset && totalElement.dataset.labelTotal ? totalElement.dataset.labelTotal : '';
                    var totalUnavailableLabel = totalElement.dataset && totalElement.dataset.labelUnavailable ? totalElement.dataset.labelUnavailable : totalLabel;
                    var approxLabel = totalElement.dataset && totalElement.dataset.labelApprox ? totalElement.dataset.labelApprox : '';
                    var labelTextElement = totalElement.querySelector('.discord-label-text');
                    var labelExtraElement = totalElement.querySelector('.discord-label-extra');
                    var indicatorElement = totalElement.querySelector('.discord-approx-indicator');
                    var numberElement = totalElement.querySelector('.discord-number');
                    var hasTotal = !!(data.data && data.data.has_total) && totalValue !== null;
                    var isApproximate = !!(data.data && data.data.total_is_approximate) && hasTotal;

                    if (container && container.classList) {
                        container.classList.toggle('discord-total-missing', !hasTotal);
                    }

                    if (!hasTotal) {
                        totalElement.classList.add('discord-total-unavailable');
                        totalElement.classList.remove('discord-total-approximate');

                        if (numberElement) {
                            numberElement.textContent = placeholder;
                            numberElement.style.transform = 'scale(1)';
                        }

                        if (labelTextElement) {
                            labelTextElement.textContent = totalUnavailableLabel;
                        }

                        if (labelExtraElement) {
                            labelExtraElement.textContent = '';
                        }

                        if (indicatorElement) {
                            indicatorElement.hidden = true;
                        }

                        if (totalElement.dataset) {
                            delete totalElement.dataset.value;
                        }
                    } else {
                        totalElement.classList.remove('discord-total-unavailable');
                        totalElement.classList.toggle('discord-total-approximate', isApproximate);

                        updateStatElement(container, '.discord-total .discord-number', totalValue, formatter);

                        if (labelTextElement) {
                            labelTextElement.textContent = totalLabel;
                        }

                        if (labelExtraElement) {
                            labelExtraElement.textContent = isApproximate ? approxLabel : '';
                        }

                        if (indicatorElement) {
                            indicatorElement.hidden = !isApproximate;
                        }

                        if (totalElement.dataset) {
                            totalElement.dataset.value = totalValue;
                        }
                    }
                }
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
