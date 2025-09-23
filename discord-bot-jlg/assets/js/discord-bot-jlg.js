(function () {
    'use strict';

    var ERROR_CLASS = 'discord-stats-error';
    var ERROR_MESSAGE_CLASS = 'discord-error-message';
    var STALE_NOTICE_CLASS = 'discord-stale-notice';
    var globalConfig = {};

    if (typeof window !== 'undefined' && window.discordBotJlg) {
        globalConfig = window.discordBotJlg;
    }

    function getLocalizedString(key, fallback) {
        if (
            globalConfig
            && Object.prototype.hasOwnProperty.call(globalConfig, key)
            && typeof globalConfig[key] === 'string'
            && globalConfig[key]
        ) {
            return globalConfig[key];
        }

        return fallback;
    }

    function getOrCreateErrorMessageElement(container) {
        if (!container) {
            return null;
        }

        var messageElement = container.querySelector('.' + ERROR_MESSAGE_CLASS);
        if (!messageElement) {
            messageElement = document.createElement('div');
            messageElement.className = ERROR_MESSAGE_CLASS;
            messageElement.setAttribute('role', 'alert');
            container.appendChild(messageElement);
        }

        return messageElement;
    }

    function getOrCreateStaleNoticeElement(container) {
        if (!container) {
            return null;
        }

        var notice = container.querySelector('.' + STALE_NOTICE_CLASS);
        if (!notice) {
            notice = document.createElement('div');
            notice.className = STALE_NOTICE_CLASS;
            container.appendChild(notice);
        }

        return notice;
    }

    function removeStaleNotice(container) {
        if (!container) {
            return;
        }

        var notice = container.querySelector('.' + STALE_NOTICE_CLASS);
        if (notice && notice.parentNode) {
            notice.parentNode.removeChild(notice);
        }
    }

    function formatStaleMessage(timestamp, locale) {
        if (!timestamp) {
            return getLocalizedString('staleNotice', 'Données mises en cache');
        }

        var date;
        try {
            date = new Date(timestamp * 1000);
        } catch (error) {
            date = null;
        }

        if (!date || isNaN(date.getTime())) {
            return getLocalizedString('staleNotice', 'Données mises en cache');
        }

        var formatted;

        try {
            formatted = date.toLocaleString(locale || undefined);
        } catch (error) {
            try {
                formatted = date.toLocaleString('fr-FR');
            } catch (fallbackError) {
                formatted = date.toISOString();
            }
        }

        var template = getLocalizedString('staleNotice', 'Données mises en cache du %s');

        if (template.indexOf('%s') === -1) {
            return template + ' ' + formatted;
        }

        return template.replace('%s', formatted);
    }

    function updateStaleNotice(container, isStale, timestamp, locale) {
        if (!container) {
            return;
        }

        if (!isStale) {
            removeStaleNotice(container);
            if (container.dataset) {
                container.dataset.stale = 'false';
                delete container.dataset.lastUpdated;
            }
            return;
        }

        var notice = getOrCreateStaleNoticeElement(container);
        if (!notice) {
            return;
        }

        if (container.dataset) {
            container.dataset.stale = 'true';
            if (timestamp) {
                container.dataset.lastUpdated = String(timestamp);
            } else {
                delete container.dataset.lastUpdated;
            }
        }

        notice.textContent = formatStaleMessage(timestamp, locale);
    }

    function showErrorMessage(container, message) {
        if (!container) {
            return;
        }

        if (container.classList) {
            container.classList.add(ERROR_CLASS);
        }

        var messageElement = getOrCreateErrorMessageElement(container);
        if (messageElement) {
            messageElement.textContent = message;
        }
    }

    function clearErrorMessage(container) {
        if (!container) {
            return;
        }

        if (container.classList) {
            container.classList.remove(ERROR_CLASS);
        }

        var messageElement = container.querySelector('.' + ERROR_MESSAGE_CLASS);
        if (messageElement && messageElement.parentNode) {
            messageElement.parentNode.removeChild(messageElement);
        }
    }

    var DEFAULT_NUMBER_FORMATTER = {
        format: function (value) {
            return String(value);
        }
    };

    function createNumberFormatter(locale) {
        if (typeof Intl === 'undefined' || typeof Intl.NumberFormat !== 'function') {
            return DEFAULT_NUMBER_FORMATTER;
        }

        try {
            return new Intl.NumberFormat(locale);
        } catch (error) {
            try {
                return new Intl.NumberFormat('fr-FR');
            } catch (fallbackError) {
                return DEFAULT_NUMBER_FORMATTER;
            }
        }
    }

    function updateStatElement(container, selector, value, formatter) {
        if (value === null) {
            return;
        }

        var element = container.querySelector(selector);
        if (!element) {
            return;
        }

        var safeFormatter = formatter && typeof formatter.format === 'function'
            ? formatter
            : DEFAULT_NUMBER_FORMATTER;

        element.textContent = safeFormatter.format(value);
        element.style.transform = 'scale(1.2)';
        setTimeout(function () {
            element.style.transform = 'scale(1)';
        }, 300);
    }

    function getDemoBadgeLabel(container) {
        var fallbackLabel = getLocalizedString('demoBadgeLabel', 'Mode Démo');

        if (!container) {
            return fallbackLabel;
        }

        if (container.dataset && container.dataset.demoBadgeLabel) {
            return container.dataset.demoBadgeLabel || fallbackLabel;
        }

        var existingBadge = container.querySelector('.discord-demo-badge');
        if (existingBadge && existingBadge.textContent) {
            var label = existingBadge.textContent;
            if (container.dataset) {
                container.dataset.demoBadgeLabel = label;
            }
            return label;
        }

        var defaultLabel = fallbackLabel;

        if (container.dataset) {
            container.dataset.demoBadgeLabel = defaultLabel;
        }

        return defaultLabel;
    }

    function updateDemoBadge(container, shouldShow) {
        if (!container) {
            return;
        }

        var badge = container.querySelector('.discord-demo-badge');

        if (shouldShow) {
            if (!badge) {
                badge = document.createElement('div');
                badge.className = 'discord-demo-badge';
                badge.textContent = getDemoBadgeLabel(container);
                container.insertBefore(badge, container.firstChild);
            } else if (!badge.textContent) {
                badge.textContent = getDemoBadgeLabel(container);
            }

            return;
        }

        if (badge && badge.parentNode) {
            if (container.dataset && !container.dataset.demoBadgeLabel && badge.textContent) {
                container.dataset.demoBadgeLabel = badge.textContent;
            }

            badge.parentNode.removeChild(badge);
        }
    }

    function applyDemoState(container, isDemo, isFallbackDemo) {
        if (!container) {
            return;
        }

        var wasForcedDemo = container.dataset
            && container.dataset.demo === 'true'
            && container.dataset.fallbackDemo !== 'true';

        if (container.dataset) {
            container.dataset.demo = isDemo ? 'true' : 'false';
            container.dataset.fallbackDemo = isFallbackDemo ? 'true' : 'false';
        }

        if (container.classList) {
            container.classList.toggle('discord-demo-mode', !!isDemo);
        }

        var shouldShowBadge = !!isDemo && (isFallbackDemo || !wasForcedDemo);
        updateDemoBadge(container, shouldShowBadge);
    }

    function updateStats(container, config, formatter, locale) {
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
                        var nonceMessage = data.data.message
                            || getLocalizedString(
                                'nonceExpiredFallback',
                                'Votre session a expiré, veuillez recharger la page.'
                            );
                        console.warn(nonceMessage);
                        showErrorMessage(container, nonceMessage);
                        return;
                    }

                    var errorMessage = '';
                    if (data.data && data.data.message) {
                        errorMessage = data.data.message;
                    } else if (typeof data.data === 'string') {
                        errorMessage = data.data;
                    }

                    if (errorMessage) {
                        console.warn(errorMessage);
                        showErrorMessage(container, errorMessage);
                    } else if (container) {
                        showErrorMessage(
                            container,
                            getLocalizedString(
                                'genericError',
                                'Une erreur est survenue lors de la récupération des statistiques.'
                            )
                        );
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
                var isDemo = !!(data.data && data.data.is_demo);
                var isFallbackDemo = !!(data.data && data.data.fallback_demo);
                var isStale = !!(data.data && data.data.stale);
                var lastUpdated = null;

                if (data.data && typeof data.data.last_updated !== 'undefined') {
                    var parsed = parseInt(data.data.last_updated, 10);
                    if (!isNaN(parsed) && parsed > 0) {
                        lastUpdated = parsed;
                    }
                }

                var onlineValue = typeof data.data.online === 'number' ? data.data.online : null;
                var totalValue = typeof data.data.total === 'number' ? data.data.total : null;

                if (onlineValue === null && totalValue === null && !hasTotalInfo) {
                    return;
                }

                clearErrorMessage(container);

                applyDemoState(container, isDemo, isFallbackDemo);

                updateStaleNotice(container, isStale, lastUpdated, locale);

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
                console.error(
                    getLocalizedString(
                        'consoleErrorPrefix',
                        'Erreur lors de la mise à jour des statistiques Discord :'
                    ),
                    error
                );
                showErrorMessage(
                    container,
                    getLocalizedString(
                        'genericError',
                        'Une erreur est survenue lors de la récupération des statistiques.'
                    )
                );
            });
    }

    function initializeDiscordBot() {
        if (typeof window.fetch !== 'function' || typeof window.FormData !== 'function') {
            return;
        }

        var config = window.discordBotJlg || {};
        globalConfig = config;
        if (!config.ajaxUrl || !config.nonce) {
            return;
        }

        var locale = config.locale || 'fr-FR';
        var formatter = createNumberFormatter(locale);

        var minIntervalSeconds = parseInt(config.minRefreshInterval, 10);
        if (isNaN(minIntervalSeconds) || minIntervalSeconds <= 0) {
            minIntervalSeconds = 10;
        }
        var minIntervalMs = minIntervalSeconds * 1000;

        var containers = document.querySelectorAll('.discord-stats-container[data-refresh]');
        if (!containers.length) {
            return;
        }

        Array.prototype.forEach.call(containers, function (container) {
            var isForcedDemo = container.dataset.demo === 'true' && container.dataset.fallbackDemo !== 'true';

            if (isForcedDemo) {
                return;
            }

            var interval = parseInt(container.dataset.refresh, 10);
            if (isNaN(interval) || interval <= 0) {
                return;
            }

            if (container.dataset && container.dataset.stale === 'true') {
                var initialTimestamp = container.dataset.lastUpdated ? parseInt(container.dataset.lastUpdated, 10) : null;
                if (!isNaN(initialTimestamp) && initialTimestamp > 0) {
                    updateStaleNotice(container, true, initialTimestamp, locale);
                } else {
                    updateStaleNotice(container, true, null, locale);
                }
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
                updateStats(container, config, formatter, locale);
            }, intervalMs);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeDiscordBot);
    } else {
        initializeDiscordBot();
    }
})();
