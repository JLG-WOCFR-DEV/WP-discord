(function () {
    'use strict';

    var ERROR_CLASS = 'discord-stats-error';
    var ERROR_MESSAGE_CLASS = 'discord-error-message';
    var STALE_NOTICE_CLASS = 'discord-stale-notice';
    var REFRESH_STATUS_CLASS = 'discord-refresh-status';
    var REFRESH_OVERLAY_CLASS = 'has-refresh-overlay';
    var DEMO_BADGE_CLASS = 'discord-demo-badge';
    var globalConfig = {};
    var SERVER_NAME_SELECTOR = '[data-role="discord-server-name"]';
    var SERVER_NAME_CLASS = 'discord-server-name';
    var SERVER_NAME_TEXT_CLASS = 'discord-server-name__text';
    var SERVER_HEADER_SELECTOR = '[data-role="discord-server-header"]';
    var SERVER_HEADER_CLASS = 'discord-server-header';
    var SERVER_AVATAR_SELECTOR = '[data-role="discord-server-avatar"]';
    var SERVER_AVATAR_CLASS = 'discord-server-avatar';
    var SERVER_AVATAR_IMAGE_CLASS = 'discord-server-avatar__image';
    var ALLOWED_AVATAR_SIZES = [16, 32, 64, 128, 256, 512, 1024, 2048, 4096];

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

    function getServerNameWrapper(container) {
        if (!container) {
            return null;
        }

        return container.querySelector('.discord-stats-wrapper');
    }

    function getServerHeader(container) {
        var wrapper = getServerNameWrapper(container);
        if (!wrapper) {
            return null;
        }

        return wrapper.querySelector(SERVER_HEADER_SELECTOR);
    }

    function ensureServerHeader(container) {
        var wrapper = getServerNameWrapper(container);
        if (!wrapper) {
            return null;
        }

        var header = wrapper.querySelector(SERVER_HEADER_SELECTOR);
        if (!header) {
            header = document.createElement('div');
            header.className = SERVER_HEADER_CLASS;
            header.setAttribute('data-role', 'discord-server-header');
            var firstStat = wrapper.querySelector('.discord-stat');
            wrapper.insertBefore(header, firstStat || wrapper.firstChild);
        }

        return header;
    }

    function cleanupServerHeader(container) {
        var wrapper = getServerNameWrapper(container);
        if (!wrapper) {
            return;
        }

        var header = wrapper.querySelector(SERVER_HEADER_SELECTOR);
        if (!header) {
            return;
        }

        var hasContent = header.querySelector(SERVER_NAME_SELECTOR) || header.querySelector(SERVER_AVATAR_SELECTOR);

        if (!hasContent && header.parentNode) {
            header.parentNode.removeChild(header);
        }
    }

    function removeServerNameElement(container) {
        if (!container) {
            return;
        }

        var element = container.querySelector(SERVER_NAME_SELECTOR);
        if (element && element.parentNode) {
            element.parentNode.removeChild(element);
        }

        if (container.dataset) {
            delete container.dataset.serverName;
        }

        cleanupServerHeader(container);
    }

    function ensureServerNameElement(container) {
        var wrapper = getServerNameWrapper(container);
        if (!wrapper) {
            return null;
        }

        var header = ensureServerHeader(container);
        if (!header) {
            return null;
        }

        var element = header.querySelector(SERVER_NAME_SELECTOR);
        if (!element) {
            element = document.createElement('div');
            element.className = SERVER_NAME_CLASS;
            element.setAttribute('data-role', 'discord-server-name');
            header.appendChild(element);
        }

        var textElement = element.querySelector('.' + SERVER_NAME_TEXT_CLASS);
        if (!textElement) {
            textElement = document.createElement('span');
            textElement.className = SERVER_NAME_TEXT_CLASS;
            element.appendChild(textElement);
        }

        return {
            element: element,
            textElement: textElement
        };
    }

    function updateServerName(container, serverName) {
        if (!container || !container.dataset || container.dataset.showServerName !== 'true') {
            removeServerNameElement(container);
            return;
        }

        var safeName = '';

        if (typeof serverName === 'string') {
            safeName = serverName.trim();
        }

        if (!safeName) {
            removeServerNameElement(container);
            return;
        }

        var elements = ensureServerNameElement(container);
        if (!elements) {
            return;
        }

        elements.textElement.textContent = safeName;

        if (container.dataset) {
            container.dataset.serverName = safeName;
        }
    }

    function ensureServerAvatarElement(container) {
        if (!container) {
            return null;
        }

        var header = ensureServerHeader(container);
        if (!header) {
            return null;
        }

        var avatarWrapper = header.querySelector(SERVER_AVATAR_SELECTOR);
        if (!avatarWrapper) {
            avatarWrapper = document.createElement('div');
            avatarWrapper.className = SERVER_AVATAR_CLASS;
            avatarWrapper.setAttribute('data-role', 'discord-server-avatar');
            header.insertBefore(avatarWrapper, header.firstChild);
        }

        var image = avatarWrapper.querySelector('.' + SERVER_AVATAR_IMAGE_CLASS);
        if (!image) {
            image = document.createElement('img');
            image.className = SERVER_AVATAR_IMAGE_CLASS;
            image.setAttribute('loading', 'lazy');
            image.setAttribute('decoding', 'async');
            avatarWrapper.appendChild(image);
        }

        return {
            wrapper: avatarWrapper,
            image: image
        };
    }

    function removeServerAvatarElement(container) {
        if (!container) {
            return;
        }

        var avatarWrapper = container.querySelector(SERVER_AVATAR_SELECTOR);
        if (avatarWrapper && avatarWrapper.parentNode) {
            avatarWrapper.parentNode.removeChild(avatarWrapper);
        }

        if (container.dataset) {
            delete container.dataset.serverAvatarUrl;
            delete container.dataset.serverAvatarBaseUrl;
        }

        cleanupServerHeader(container);
    }

    function normalizeAvatarSize(value, fallback) {
        var parsed = parseInt(value, 10);
        if (isNaN(parsed) || parsed <= 0) {
            parsed = typeof fallback === 'number' && !isNaN(fallback) && fallback > 0 ? fallback : 128;
        }

        for (var i = 0; i < ALLOWED_AVATAR_SIZES.length; i++) {
            if (parsed <= ALLOWED_AVATAR_SIZES[i]) {
                return ALLOWED_AVATAR_SIZES[i];
            }
        }

        return ALLOWED_AVATAR_SIZES[ALLOWED_AVATAR_SIZES.length - 1];
    }

    function stripSizeParameter(url) {
        if (!url) {
            return '';
        }

        var stripped = url.replace(/([?&])size=\d+/g, function (match, prefix) {
            return prefix === '?' ? '?' : '';
        });

        stripped = stripped.replace(/\?&/, '?');

        if (stripped.slice(-1) === '?' || stripped.slice(-1) === '&') {
            stripped = stripped.slice(0, -1);
        }

        return stripped;
    }

    function buildAvatarUrl(baseUrl, size) {
        if (!baseUrl) {
            return '';
        }

        var normalizedSize = normalizeAvatarSize(size, size);
        var withoutSize = stripSizeParameter(baseUrl);
        var separator = withoutSize.indexOf('?') === -1 ? '?' : '&';

        return withoutSize + separator + 'size=' + normalizedSize;
    }

    function updateServerAvatar(container, avatarUrl, avatarBaseUrl, serverName) {
        if (!container || !container.dataset || container.dataset.showServerAvatar !== 'true') {
            removeServerAvatarElement(container);
            if (container && container.classList) {
                container.classList.remove('discord-has-server-avatar');
                container.classList.remove('discord-avatar-enabled');
            }
            return;
        }

        if (container.classList) {
            container.classList.add('discord-avatar-enabled');
        }

        var normalizedSize = normalizeAvatarSize(container.dataset.avatarSize, 128);
        container.dataset.avatarSize = String(normalizedSize);

        var baseUrl = '';
        if (typeof avatarBaseUrl === 'string' && avatarBaseUrl) {
            baseUrl = stripSizeParameter(avatarBaseUrl);
        } else if (container.dataset.serverAvatarBaseUrl) {
            baseUrl = container.dataset.serverAvatarBaseUrl;
        }

        var rawUrl = '';
        if (typeof avatarUrl === 'string' && avatarUrl) {
            rawUrl = avatarUrl;
        } else if (container.dataset.serverAvatarUrl) {
            rawUrl = container.dataset.serverAvatarUrl;
        }

        var finalUrl = '';

        if (baseUrl) {
            finalUrl = buildAvatarUrl(baseUrl, normalizedSize);
        } else if (rawUrl) {
            finalUrl = buildAvatarUrl(rawUrl, normalizedSize);
            baseUrl = stripSizeParameter(rawUrl);
        }

        if (!finalUrl) {
            removeServerAvatarElement(container);
            if (container.classList) {
                container.classList.remove('discord-has-server-avatar');
            }
            return;
        }

        var dataset = container.dataset || {};
        dataset.serverAvatarUrl = finalUrl;
        if (baseUrl) {
            dataset.serverAvatarBaseUrl = stripSizeParameter(baseUrl);
        }

        var elements = ensureServerAvatarElement(container);
        if (!elements) {
            return;
        }

        if (container.classList) {
            container.classList.add('discord-has-server-avatar');
        }

        if (elements.image.getAttribute('src') !== finalUrl) {
            elements.image.setAttribute('src', finalUrl);
        }

        var fallbackAlt = getLocalizedString('serverAvatarAltFallback', 'Avatar du serveur Discord');
        var altTemplate = getLocalizedString('serverAvatarAltTemplate', 'Avatar du serveur Discord %s');
        var safeName = '';

        if (typeof serverName === 'string' && serverName.trim()) {
            safeName = serverName.trim();
        } else if (dataset.serverName && dataset.serverName.trim()) {
            safeName = dataset.serverName.trim();
        }

        var altText = fallbackAlt;

        if (altTemplate && altTemplate.indexOf('%s') !== -1) {
            if (safeName) {
                altText = altTemplate.replace('%s', safeName);
            } else {
                altText = altTemplate.replace('%s', '').trim() || fallbackAlt;
            }
        } else if (safeName) {
            altText = fallbackAlt + ' ' + safeName;
        }

        elements.image.setAttribute('alt', altText);
        elements.image.setAttribute('width', String(normalizedSize));
        elements.image.setAttribute('height', String(normalizedSize));
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

    function convertRetryAfterToMilliseconds(rawValue) {
        if (rawValue === null || typeof rawValue === 'undefined') {
            return null;
        }

        var numericValue = null;

        if (typeof rawValue === 'string') {
            var trimmedValue = rawValue.trim();

            if (!trimmedValue) {
                return null;
            }

            var lowerValue = trimmedValue.toLowerCase();
            if (lowerValue.slice(-2) === 'ms') {
                var parsedMs = parseFloat(trimmedValue.slice(0, -2));
                if (!isNaN(parsedMs) && parsedMs >= 0) {
                    numericValue = parsedMs;
                }
            } else {
                var parsedSeconds = parseFloat(trimmedValue);
                if (!isNaN(parsedSeconds) && parsedSeconds >= 0) {
                    numericValue = parsedSeconds * 1000;
                }
            }
        } else if (typeof rawValue === 'number') {
            if (!isNaN(rawValue) && rawValue >= 0) {
                numericValue = rawValue * 1000;
            }
        }

        if (numericValue === null) {
            return null;
        }

        return numericValue;
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

        function isAnimationEnabled(target) {
            if (!target) {
                return false;
            }

            var hasAnimatedClass = false;

            if (target.classList && typeof target.classList.contains === 'function') {
                hasAnimatedClass = target.classList.contains('discord-animated');
            } else if (typeof target.className === 'string' && target.className) {
                hasAnimatedClass = (' ' + target.className + ' ').indexOf(' discord-animated ') !== -1;
            }

            if (hasAnimatedClass) {
                return true;
            }

            var dataValue = null;

            if (target.dataset && Object.prototype.hasOwnProperty.call(target.dataset, 'animated')) {
                dataValue = target.dataset.animated;
            } else if (typeof target.getAttribute === 'function') {
                dataValue = target.getAttribute('data-animated');
            }

            if (dataValue === null || typeof dataValue === 'undefined') {
                return false;
            }

            if (dataValue === '' || dataValue === true || dataValue === 'true' || dataValue === '1') {
                return true;
            }

            if (typeof dataValue === 'string') {
                var normalized = dataValue.toLowerCase();
                return normalized !== 'false' && normalized !== '0' && normalized !== 'off' && normalized !== 'no';
            }

            return !!dataValue;
        }

        function resetTransform() {
            if (!element || !element.style) {
                return;
            }

            if (typeof element.style.removeProperty === 'function') {
                element.style.removeProperty('transform');
            } else {
                element.style.transform = '';
            }

            if (typeof element.getAttribute === 'function' && element.getAttribute('style') === '') {
                element.removeAttribute('style');
            }
        }

        var prefersReducedMotion = false;
        if (typeof window !== 'undefined' && typeof window.matchMedia === 'function') {
            try {
                prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            } catch (error) {
                prefersReducedMotion = false;
            }
        }

        if (prefersReducedMotion || !isAnimationEnabled(container)) {
            resetTransform();
            return;
        }

        element.style.transform = 'scale(1.2)';
        setTimeout(function () {
            resetTransform();
        }, 300);
    }

    function ensureOnlineLabelElement(container) {
        if (!container) {
            return;
        }

        var onlineStat = container.querySelector('.discord-online');
        if (!onlineStat) {
            return;
        }

        var hideLabels = false;
        if (onlineStat.dataset && Object.prototype.hasOwnProperty.call(onlineStat.dataset, 'hideLabels')) {
            hideLabels = onlineStat.dataset.hideLabels === 'true';
        } else if (container.dataset && Object.prototype.hasOwnProperty.call(container.dataset, 'hideLabels')) {
            hideLabels = container.dataset.hideLabels === 'true';
        }

        var labelText = '';
        if (onlineStat.dataset && Object.prototype.hasOwnProperty.call(onlineStat.dataset, 'labelOnline')) {
            labelText = onlineStat.dataset.labelOnline;
        }

        if (!labelText) {
            labelText = getLocalizedString('labelOnline', 'En ligne');
        }

        var labelElement = onlineStat.querySelector('.discord-label');
        if (!labelElement) {
            labelElement = document.createElement('span');
            labelElement.className = 'discord-label';
            onlineStat.appendChild(labelElement);
        }

        if (hideLabels) {
            if (labelElement.classList && !labelElement.classList.contains('screen-reader-text')) {
                labelElement.classList.add('screen-reader-text');
            } else if (!labelElement.classList && typeof labelElement.className === 'string') {
                var existingClasses = labelElement.className.trim();
                if (existingClasses) {
                    var classParts = existingClasses.split(/\s+/);
                    if (classParts.indexOf('screen-reader-text') === -1) {
                        classParts.push('screen-reader-text');
                        labelElement.className = classParts.join(' ');
                    }
                } else {
                    labelElement.className = 'screen-reader-text';
                }
            }
        } else if (labelElement.classList) {
            labelElement.classList.remove('screen-reader-text');
        } else if (typeof labelElement.className === 'string' && labelElement.className) {
            labelElement.className = labelElement.className
                .split(/\s+/)
                .filter(function (cls) {
                    return cls && cls !== 'screen-reader-text';
                })
                .join(' ');
        }

        var labelTextElement = labelElement.querySelector('.discord-label-text');
        if (!labelTextElement) {
            labelTextElement = document.createElement('span');
            labelTextElement.className = 'discord-label-text';
            labelElement.appendChild(labelTextElement);
        }

        if (labelTextElement.textContent !== labelText) {
            labelTextElement.textContent = labelText;
        }
    }

    function getDemoBadgeLabel(container) {
        var fallbackLabel = getLocalizedString('demoBadgeLabel', 'Mode Démo');

        if (!container) {
            return fallbackLabel;
        }

        if (container.dataset && container.dataset.demoBadgeLabel) {
            return container.dataset.demoBadgeLabel || fallbackLabel;
        }

        var existingBadge = container.querySelector('.' + DEMO_BADGE_CLASS);
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

    function syncRefreshOverlayClass(container) {
        if (!container || !container.classList) {
            return;
        }

        var hasRefreshStatus = !!container.querySelector('.' + REFRESH_STATUS_CLASS);
        var hasDemoBadge = !!container.querySelector('.' + DEMO_BADGE_CLASS);

        container.classList.toggle(REFRESH_OVERLAY_CLASS, hasRefreshStatus || hasDemoBadge);
    }

    function updateDemoBadge(container, shouldShow) {
        if (!container) {
            return;
        }

        var badge = container.querySelector('.' + DEMO_BADGE_CLASS);

        if (shouldShow) {
            if (!badge) {
                badge = document.createElement('div');
                badge.className = DEMO_BADGE_CLASS;
                badge.textContent = getDemoBadgeLabel(container);
                container.insertBefore(badge, container.firstChild);
            } else if (!badge.textContent) {
                badge.textContent = getDemoBadgeLabel(container);
            }

            syncRefreshOverlayClass(container);
            return;
        }

        if (badge && badge.parentNode) {
            if (container.dataset && !container.dataset.demoBadgeLabel && badge.textContent) {
                container.dataset.demoBadgeLabel = badge.textContent;
            }

            badge.parentNode.removeChild(badge);
        }

        syncRefreshOverlayClass(container);
    }

    function showRefreshIndicator(container) {
        if (!container) {
            return;
        }

        if (container.dataset) {
            container.dataset.refreshing = 'true';
        }

        var status = container.querySelector('.' + REFRESH_STATUS_CLASS);
        var label = getLocalizedString('refreshingStatus', 'Actualisation…');

        if (!status) {
            status = document.createElement('div');
            status.className = REFRESH_STATUS_CLASS;
            status.setAttribute('role', 'status');
            status.setAttribute('aria-live', 'polite');
            status.textContent = label;
            container.appendChild(status);
        } else {
            status.textContent = label;
        }

        syncRefreshOverlayClass(container);
    }

    function hideRefreshIndicator(container) {
        if (!container) {
            return;
        }

        if (container.dataset) {
            container.dataset.refreshing = 'false';
        }

        var status = container.querySelector('.' + REFRESH_STATUS_CLASS);
        if (status && status.parentNode) {
            status.parentNode.removeChild(status);
        }

        syncRefreshOverlayClass(container);
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
        var managesRefreshIndicator = false;

        if (container) {
            var isAlreadyRefreshing = container.dataset && container.dataset.refreshing === 'true';

            if (!isAlreadyRefreshing) {
                showRefreshIndicator(container);
                managesRefreshIndicator = true;
            }
        }

        var resultInfo = {
            success: false,
            rateLimited: false,
            retryAfter: null
        };

        var formData = new FormData();
        formData.append('action', config.action || 'refresh_discord_stats');

        if (config.requiresNonce && config.nonce) {
            formData.append('_ajax_nonce', config.nonce);
        }

        var requestPromise = fetch(config.ajaxUrl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
            .then(function (response) {
                if (!response || typeof response !== 'object') {
                    var invalidResponseError = new Error('Invalid network response');
                    invalidResponseError.userMessage = getLocalizedString(
                        'genericError',
                        'Une erreur est survenue lors de la récupération des statistiques.'
                    );
                    throw invalidResponseError;
                }

                if (!response.ok) {
                    var statusError = new Error('HTTP error ' + response.status);
                    statusError.status = response.status;
                    statusError.statusText = response.statusText;

                    var jsonSource = typeof response.clone === 'function'
                        ? response.clone()
                        : response;

                    return jsonSource
                        .json()
                        .then(function (data) {
                            if (data && typeof data === 'object') {
                                var errorData = data.data && typeof data.data === 'object'
                                    ? data.data
                                    : data;

                                if (
                                    typeof data.message !== 'undefined'
                                    && (
                                        typeof errorData.message === 'undefined'
                                        || errorData.message === null
                                    )
                                ) {
                                    errorData.message = data.message;
                                }

                                var retryAfterSource;
                                if (
                                    errorData
                                    && typeof errorData.retry_after !== 'undefined'
                                ) {
                                    retryAfterSource = errorData.retry_after;
                                } else if (
                                    typeof data.retry_after !== 'undefined'
                                ) {
                                    retryAfterSource = data.retry_after;
                                }

                                var retryAfterMs = convertRetryAfterToMilliseconds(
                                    retryAfterSource
                                );

                                if (retryAfterMs !== null) {
                                    errorData.retry_after = retryAfterMs;
                                    statusError.retryAfter = retryAfterMs;
                                }

                                statusError.data = errorData;
                            }

                            throw statusError;
                        })
                        .catch(function (jsonError) {
                            if (jsonError === statusError) {
                                throw statusError;
                            }

                            if (typeof response.text === 'function') {
                                return response
                                    .text()
                                    .then(function (text) {
                                        statusError.responseText = text;
                                        throw statusError;
                                    })
                                    .catch(function () {
                                        throw statusError;
                                    });
                            }

                            throw statusError;
                        });
                }

                return response.json().catch(function (error) {
                    var parseError = new Error('Invalid JSON response');
                    parseError.originalError = error;
                    throw parseError;
                });
            })
            .then(function (data) {
                if (!data || typeof data !== 'object') {
                    return resultInfo;
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
                        return resultInfo;
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

                    return resultInfo;
                }

                if (!data.data || data.data.rate_limited) {
                    var rateLimitMessage = '';

                    if (data.data && data.data.message) {
                        rateLimitMessage = data.data.message;
                    } else {
                        rateLimitMessage = getLocalizedString(
                            'rateLimited',
                            'Actualisation trop fréquente, veuillez patienter avant de réessayer.'
                        );
                    }

                    if (rateLimitMessage) {
                        console.warn(rateLimitMessage);
                        showErrorMessage(container, rateLimitMessage);
                    }

                    if (data.data && typeof data.data.retry_after !== 'undefined') {
                        var retryAfterValue = convertRetryAfterToMilliseconds(
                            data.data.retry_after
                        );

                        if (retryAfterValue !== null) {
                            resultInfo.retryAfter = retryAfterValue;
                        }
                    }

                    resultInfo.rateLimited = true;

                    return resultInfo;
                }

                var hasTotalInfo = data.data && typeof data.data.has_total !== 'undefined';
                var isDemo = !!(data.data && data.data.is_demo);
                var isFallbackDemo = !!(data.data && data.data.fallback_demo);
                var isStale = !!(data.data && data.data.stale);
                var lastUpdated = null;
                var serverNameValue = '';

                if (data.data && typeof data.data.last_updated !== 'undefined') {
                    var parsed = parseInt(data.data.last_updated, 10);
                    if (!isNaN(parsed) && parsed > 0) {
                        lastUpdated = parsed;
                    }
                }

                if (data.data && typeof data.data.server_name === 'string') {
                    serverNameValue = data.data.server_name;
                }

                var serverAvatarUrlValue = '';
                var serverAvatarBaseValue = '';

                if (data.data && typeof data.data.server_avatar_url === 'string') {
                    serverAvatarUrlValue = data.data.server_avatar_url;
                }

                if (data.data && typeof data.data.server_avatar_base_url === 'string') {
                    serverAvatarBaseValue = data.data.server_avatar_base_url;
                }

                var onlineValue = typeof data.data.online === 'number' ? data.data.online : null;
                var totalValue = typeof data.data.total === 'number' ? data.data.total : null;

                if (onlineValue === null && totalValue === null && !hasTotalInfo) {
                    return resultInfo;
                }

                if (data.data && typeof data.data.retry_after !== 'undefined') {
                    var successRetryAfter = convertRetryAfterToMilliseconds(data.data.retry_after);

                    if (successRetryAfter !== null) {
                        resultInfo.retryAfter = successRetryAfter;
                    }
                }

                clearErrorMessage(container);

                applyDemoState(container, isDemo, isFallbackDemo);

                updateStaleNotice(container, isStale, lastUpdated, locale);

                updateServerName(container, serverNameValue);
                updateServerAvatar(container, serverAvatarUrlValue, serverAvatarBaseValue, serverNameValue);

                ensureOnlineLabelElement(container);
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

                resultInfo.success = true;

                return resultInfo;
            })
            .catch(function (error) {
                console.error(
                    getLocalizedString(
                        'consoleErrorPrefix',
                        'Erreur lors de la mise à jour des statistiques Discord :'
                    ),
                    error
                );

                if (!container) {
                    return;
                }

                var fallbackMessage = getLocalizedString(
                    'genericError',
                    'Une erreur est survenue lors de la récupération des statistiques.'
                );

                var message = fallbackMessage;
                if (error) {
                    var dataMessage = null;

                    if (error.data) {
                        if (typeof error.data === 'string') {
                            dataMessage = error.data;
                        } else if (typeof error.data === 'object') {
                            if (
                                typeof error.data.message === 'string'
                                && error.data.message
                            ) {
                                dataMessage = error.data.message;
                            } else if (
                                typeof error.data.error === 'string'
                                && error.data.error
                            ) {
                                dataMessage = error.data.error;
                            } else if (
                                typeof error.data.detail === 'string'
                                && error.data.detail
                            ) {
                                dataMessage = error.data.detail;
                            }
                        }
                    }

                    if (dataMessage) {
                        message = dataMessage;
                    } else if (error.userMessage) {
                        message = error.userMessage;
                    } else if (error.responseText) {
                        var trimmed = String(error.responseText).trim();
                        if (trimmed && trimmed.indexOf('<') === -1 && trimmed.length < 500) {
                            message = trimmed;
                        }
                    } else if (error.statusText) {
                        message = error.statusText;
                    }

                    if (
                        typeof resultInfo.retryAfter !== 'number'
                        && typeof error.retryAfter === 'number'
                        && error.retryAfter >= 0
                    ) {
                        resultInfo.retryAfter = error.retryAfter;
                    }
                }

                showErrorMessage(container, message);
                return resultInfo;
            });

        if (managesRefreshIndicator) {
            requestPromise = requestPromise.then(
                function (value) {
                    hideRefreshIndicator(container);
                    return value;
                },
                function (error) {
                    hideRefreshIndicator(container);
                    throw error;
                }
            );
        }

        return requestPromise;
    }

    function applyInitialOverlayClasses() {
        if (typeof document === 'undefined') {
            return;
        }

        var containers = document.querySelectorAll('.discord-stats-container');
        if (!containers.length) {
            return;
        }

        Array.prototype.forEach.call(containers, function (container) {
            syncRefreshOverlayClass(container);
        });
    }

    function initializeDiscordBot() {
        var config = window.discordBotJlg || {};
        globalConfig = config;
        var missingFeatures = [];

        applyInitialOverlayClasses();

        if (typeof window.fetch !== 'function') {
            missingFeatures.push('fetch');
        }

        if (typeof window.Promise !== 'function') {
            missingFeatures.push('Promise');
        }

        if (typeof window.FormData !== 'function') {
            missingFeatures.push('FormData');
        }

        if (missingFeatures.length) {
            config.autoRefreshDisabled = true;

            if (typeof console !== 'undefined' && typeof console.warn === 'function') {
                console.warn(
                    'Discord Bot JLG auto-refresh disabled: missing browser APIs ('
                    + missingFeatures.join(', ')
                    + ').'
                );
            }

            return;
        }

        var requiresNonce = typeof config.requiresNonce === 'undefined'
            ? true
            : !!config.requiresNonce;

        config.requiresNonce = requiresNonce;

        if (!config.ajaxUrl || (config.requiresNonce && !config.nonce)) {
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

        function scheduleNextRefresh(container, state, delayMs) {
            if (!state) {
                return;
            }

            if (state.timeoutId) {
                clearTimeout(state.timeoutId);
                state.timeoutId = null;
            }

            var effectiveDelay;
            if (typeof delayMs === 'number' && !isNaN(delayMs)) {
                effectiveDelay = Math.max(delayMs, 0);
                if (effectiveDelay > 0 && effectiveDelay < state.minIntervalMs) {
                    effectiveDelay = state.minIntervalMs;
                }
            } else {
                effectiveDelay = state.intervalMs;
            }

            state.timeoutId = window.setTimeout(function () {
                state.timeoutId = null;
                if (state.inFlight) {
                    scheduleNextRefresh(container, state, state.minIntervalMs);
                    return;
                }

                triggerRefresh(container, state);
            }, effectiveDelay);
        }

        function triggerRefresh(container, state, overrideDelay) {
            if (!state || state.inFlight) {
                return;
            }

            state.inFlight = true;

            showRefreshIndicator(container);

            function resetInFlight() {
                state.inFlight = false;
            }

            updateStats(container, config, formatter, locale).then(function (result) {
                var nextDelay = state.intervalMs;

                if (result && typeof result.retryAfter === 'number' && result.retryAfter >= 0) {
                    nextDelay = Math.max(result.retryAfter, state.intervalMs);
                } else if (typeof overrideDelay === 'number') {
                    nextDelay = overrideDelay;
                }

                scheduleNextRefresh(container, state, nextDelay);
                resetInFlight();
                hideRefreshIndicator(container);
            }).catch(function () {
                scheduleNextRefresh(container, state, state.intervalMs);
                resetInFlight();
                hideRefreshIndicator(container);
            });
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

            var state = {
                intervalMs: intervalMs,
                minIntervalMs: minIntervalMs,
                timeoutId: null,
                inFlight: false
            };

            var shouldForceImmediateRefresh = (container.dataset && container.dataset.stale === 'true')
                || (container.dataset && container.dataset.fallbackDemo === 'true');

            if (shouldForceImmediateRefresh) {
                triggerRefresh(container, state, state.intervalMs);
            } else {
                scheduleNextRefresh(container, state, state.intervalMs);
            }
        });
    }

    if (typeof window !== 'undefined') {
        if (!window.discordBotJlg) {
            window.discordBotJlg = {};
        }

        window.discordBotJlgInit = initializeDiscordBot;
        window.discordBotJlg.init = initializeDiscordBot;
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeDiscordBot);
    } else {
        initializeDiscordBot();
    }
})();
