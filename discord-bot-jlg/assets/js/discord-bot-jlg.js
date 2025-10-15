(function () {
    'use strict';

    var ERROR_CLASS = 'discord-stats-error';
    var ERROR_MESSAGE_CLASS = 'discord-error-message';
    var STALE_NOTICE_CLASS = 'discord-stale-notice';
    var REFRESH_STATUS_CLASS = 'discord-refresh-status';
    var REFRESH_OVERLAY_CLASS = 'has-refresh-overlay';
    var DEMO_BADGE_CLASS = 'discord-demo-badge';
    var globalConfig = {};
    var REFRESH_STATE_PROP = '__discordBotJlgRefreshState';
    var SERVER_NAME_SELECTOR = '[data-role="discord-server-name"]';
    var SERVER_NAME_CLASS = 'discord-server-name';
    var SERVER_NAME_TEXT_CLASS = 'discord-server-name__text';
    var SERVER_HEADER_SELECTOR = '[data-role="discord-server-header"]';
    var SERVER_HEADER_CLASS = 'discord-server-header';
    var SERVER_AVATAR_SELECTOR = '[data-role="discord-server-avatar"]';
    var SERVER_AVATAR_CLASS = 'discord-server-avatar';
    var SERVER_AVATAR_IMAGE_CLASS = 'discord-server-avatar__image';
    var ALLOWED_AVATAR_SIZES = [16, 32, 64, 128, 256, 512, 1024, 2048, 4096];
    var SYNTHETIC_LABEL_SELECTOR = '[data-region-synthetic-label="true"]';
    var SYNTHETIC_LABEL_CLASS = 'discord-region-label';
    var SCREEN_READER_TEXT_CLASS = 'screen-reader-text';
    var ANALYTICS_CACHE_TTL = 5 * 60 * 1000;
    var analyticsCache = {};
    var STATUS_BADGE_SELECTOR = '[data-status-badge]';
    var STATUS_PANEL_SELECTOR = '[data-status-panel]';
    var STATUS_TOGGLE_SELECTOR = '[data-status-toggle]';
    var STATUS_CLOSE_SELECTOR = '[data-status-close]';
    var STATUS_HISTORY_LIST_SELECTOR = '[data-status-history]';
    var STATUS_HISTORY_TOGGLE_SELECTOR = '[data-status-history-toggle]';
    var STATUS_HISTORY_EMPTY_SELECTOR = '[data-status-history-empty]';
    var STATUS_LABEL_SELECTOR = '[data-status-label]';
    var STATUS_COUNTDOWN_SELECTOR = '[data-status-countdown]';
    var STATUS_PROGRESS_SELECTOR = '.discord-status-badge__progress-indicator';
    var STATUS_MODE_SELECTOR = '[data-status-mode]';
    var STATUS_LAST_SYNC_SELECTOR = '[data-status-last-sync]';
    var STATUS_NEXT_SYNC_SELECTOR = '[data-status-next-sync]';
    var STATUS_NEXT_RETRY_SELECTOR = '[data-status-next-retry]';
    var STATUS_FORCE_SELECTOR = '[data-status-force-refresh]';
    var STATUS_LOG_LINK_SELECTOR = '[data-status-log-link]';
    var STATUS_STATE_KEY = '__discordStatusState';
    var COMPARISON_EXPORT_SELECTOR = '[data-role="discord-comparison-export"]';
    var PRESENCE_EXPLORER_SELECTOR = '[data-role="discord-presence-explorer"]';
    var PRESENCE_FILTERS_SELECTOR = '[data-role="discord-presence-filters"]';
    var PRESENCE_CHIP_SELECTOR = '[data-role="discord-presence-chip"]';
    var PRESENCE_SELECTED_VALUE_SELECTOR = '[data-role="discord-presence-selected-value"]';
    var PRESENCE_SELECTED_SHARE_SELECTOR = '[data-role="discord-presence-selected-share"]';
    var PRESENCE_META_VALUE_SELECTOR = '[data-role="discord-presence-meta-value"]';
    var PRESENCE_META_SHARE_SELECTOR = '[data-role="discord-presence-meta-share"]';
    var PRESENCE_LIST_SELECTOR = '[data-role="discord-presence-list"]';
    var PRESENCE_HEATMAP_SELECTOR = '[data-role="discord-presence-heatmap"]';
    var PRESENCE_HEATMAP_EMPTY_SELECTOR = '[data-role="discord-presence-heatmap-empty"]';
    var PRESENCE_TIMELINE_SELECTOR = '[data-role="discord-presence-timeline"]';
    var PRESENCE_TIMELINE_TOOLBAR_SELECTOR = '[data-role="discord-presence-timeline-toolbar"]';
    var PRESENCE_TIMELINE_BODY_SELECTOR = '[data-role="discord-presence-timeline-body"]';
    var PRESENCE_TIMELINE_EMPTY_SELECTOR = '[data-role="discord-presence-timeline-empty"]';
    var PRESENCE_TOTAL_SELECTOR = '[data-role="discord-presence-total"]';
    var PRESENCE_DEFAULT_RANGE = 7;
    var PRESENCE_DEFAULT_METRIC = 'presence';
    var FOCUSABLE_ELEMENTS_SELECTOR = [
        'a[href]:not([tabindex="-1"])',
        'area[href]:not([tabindex="-1"])',
        'button:not([disabled]):not([tabindex="-1"])',
        'input:not([disabled]):not([type="hidden"]):not([tabindex="-1"])',
        'select:not([disabled]):not([tabindex="-1"])',
        'textarea:not([disabled]):not([tabindex="-1"])',
        '[tabindex]:not([tabindex="-1"])',
        '[contenteditable="true"]'
    ].join(', ');
    var containerStateStore = (typeof WeakMap === 'function') ? new WeakMap() : null;

    function storeContainerState(container, state) {
        if (!container) {
            return;
        }

        if (containerStateStore) {
            containerStateStore.set(container, state);
            return;
        }

        container[REFRESH_STATE_PROP] = state;
    }

    function getContainerState(container) {
        if (!container) {
            return null;
        }

        if (containerStateStore) {
            return containerStateStore.get(container) || null;
        }

        if (Object.prototype.hasOwnProperty.call(container, REFRESH_STATE_PROP)) {
            return container[REFRESH_STATE_PROP];
        }

        return null;
    }

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

    function getStatusLabelByVariant(variant) {
        var map = {
            live: 'statusLabelLive',
            cache: 'statusLabelCache',
            fallback: 'statusLabelFallback',
            demo: 'statusLabelDemo',
            unknown: 'statusLabelUnknown'
        };

        var key = map[variant] || map.unknown;
        var fallback = variant ? variant.charAt(0).toUpperCase() + variant.slice(1) : 'Statut';

        return getLocalizedString(key, fallback);
    }

    function getStatusDescriptionByVariant(variant) {
        var map = {
            live: 'statusDescriptionLive',
            cache: 'statusDescriptionCache',
            fallback: 'statusDescriptionFallback',
            demo: 'statusDescriptionDemo',
            unknown: 'statusDescriptionUnknown'
        };

        var key = map[variant] || map.unknown;

        return getLocalizedString(key, '');
    }

    function formatStatusTimestamp(timestamp, locale) {
        if (typeof timestamp !== 'number' || !isFinite(timestamp) || timestamp <= 0) {
            return '';
        }

        var date;
        try {
            date = new Date(timestamp * 1000);
        } catch (error) {
            date = null;
        }

        if (!date || isNaN(date.getTime())) {
            return '';
        }

        try {
            return date.toLocaleString(locale || undefined);
        } catch (error) {
            try {
                return date.toLocaleString('fr-FR');
            } catch (fallbackError) {
                return date.toISOString();
            }
        }
    }

    function formatStatusCountdown(seconds) {
        if (typeof seconds !== 'number' || isNaN(seconds)) {
            return '';
        }

        var remaining = Math.floor(seconds);

        if (remaining <= 0) {
            return getLocalizedString('statusCountdownReady', 'Actualisation imminente');
        }

        var hours = Math.floor(remaining / 3600);
        var minutes = Math.floor((remaining % 3600) / 60);
        var secs = remaining % 60;

        if (hours > 0) {
            return String(hours) + ':' + String(minutes).padStart(2, '0') + ':' + String(secs).padStart(2, '0');
        }

        return String(minutes).padStart(2, '0') + ':' + String(secs).padStart(2, '0');
    }

    function normalizeStatusMeta(meta) {
        var base = {
            variant: 'unknown',
            isDemo: false,
            isFallbackDemo: false,
            isStale: false,
            lastUpdated: null,
            refreshInterval: null,
            cacheDuration: null,
            nextRefresh: null,
            generatedAt: Math.floor(Date.now() / 1000),
            retryAfter: null,
            nextRetry: null,
            profileKey: '',
            serverId: '',
            forceDemo: false,
            canForceRefresh: false,
            logsUrl: '',
            fallbackDetails: {
                timestamp: 0,
                reason: '',
                nextRetry: 0
            },
            history: []
        };

        if (!meta || typeof meta !== 'object') {
            return base;
        }

        if (typeof meta.variant === 'string' && meta.variant) {
            base.variant = meta.variant.toLowerCase();
        }

        base.isDemo = !!meta.isDemo;
        base.isFallbackDemo = !!meta.isFallbackDemo;
        base.isStale = !!meta.isStale;
        base.forceDemo = !!meta.forceDemo;
        base.canForceRefresh = !!meta.canForceRefresh;

        if (typeof meta.profileKey === 'string') {
            base.profileKey = meta.profileKey;
        }

        if (typeof meta.serverId === 'string') {
            base.serverId = meta.serverId;
        }

        if (typeof meta.logsUrl === 'string') {
            base.logsUrl = meta.logsUrl;
        }

        var lastUpdated = parseInt(meta.lastUpdated, 10);
        if (!isNaN(lastUpdated) && lastUpdated > 0) {
            base.lastUpdated = lastUpdated;
        }

        var refreshInterval = parseInt(meta.refreshInterval, 10);
        if (!isNaN(refreshInterval) && refreshInterval > 0) {
            base.refreshInterval = refreshInterval;
        }

        var cacheDuration = parseInt(meta.cacheDuration, 10);
        if (!isNaN(cacheDuration) && cacheDuration > 0) {
            base.cacheDuration = cacheDuration;
        }

        var retryAfter = parseInt(meta.retryAfter, 10);
        if (!isNaN(retryAfter) && retryAfter >= 0) {
            base.retryAfter = retryAfter;
        }

        var nextRetry = parseInt(meta.nextRetry, 10);
        if (!isNaN(nextRetry) && nextRetry > 0) {
            base.nextRetry = nextRetry;
        }

        var nextRefresh = parseInt(meta.nextRefresh, 10);
        if (!isNaN(nextRefresh) && nextRefresh > 0) {
            base.nextRefresh = nextRefresh;
        }

        if (typeof meta.generatedAt !== 'undefined') {
            var generatedAt = parseInt(meta.generatedAt, 10);
            if (!isNaN(generatedAt) && generatedAt > 0) {
                base.generatedAt = generatedAt;
            }
        }

        var fallback = meta.fallbackDetails || meta.fallback || {};
        var fallbackTimestamp = parseInt(fallback.timestamp, 10);
        var fallbackNextRetry = fallback.nextRetry;
        if (typeof fallbackNextRetry === 'undefined') {
            fallbackNextRetry = fallback.next_retry;
        }
        fallbackNextRetry = parseInt(fallbackNextRetry, 10);

        base.fallbackDetails = {
            timestamp: (!isNaN(fallbackTimestamp) && fallbackTimestamp > 0) ? fallbackTimestamp : 0,
            reason: (typeof fallback.reason === 'string') ? fallback.reason : '',
            nextRetry: (!isNaN(fallbackNextRetry) && fallbackNextRetry > 0) ? fallbackNextRetry : 0
        };

        if (!base.nextRetry && base.fallbackDetails.nextRetry) {
            base.nextRetry = base.fallbackDetails.nextRetry;
        }

        if (!Array.isArray(meta.history)) {
            base.history = [];
        } else {
            base.history = meta.history.map(function (entry) {
                if (!entry || typeof entry !== 'object') {
                    return null;
                }

                var entryTimestamp = parseInt(entry.timestamp, 10);
                var entryLabel = typeof entry.label === 'string' ? entry.label : '';
                var entryReason = typeof entry.reason === 'string' ? entry.reason : '';
                var entryType = typeof entry.type === 'string' ? entry.type : '';

                if (isNaN(entryTimestamp) || entryTimestamp <= 0) {
                    entryTimestamp = 0;
                }

                return {
                    timestamp: entryTimestamp,
                    label: entryLabel,
                    reason: entryReason,
                    type: entryType
                };
            }).filter(function (entry) {
                return !!entry;
            });
        }

        if (!base.nextRefresh) {
            if (base.retryAfter) {
                base.nextRefresh = base.generatedAt + base.retryAfter;
            } else if (base.refreshInterval) {
                var baseTimestamp = base.lastUpdated ? base.lastUpdated : base.generatedAt;
                base.nextRefresh = baseTimestamp + base.refreshInterval;
            }
        }

        return base;
    }

    function mergeStatusMeta(base, updates) {
        if (!updates || typeof updates !== 'object') {
            return base;
        }

        var merged = {};

        Object.keys(base).forEach(function (key) {
            merged[key] = base[key];
        });

        Object.keys(updates).forEach(function (key) {
            var value = updates[key];

            if (typeof value === 'undefined' || value === null) {
                return;
            }

            if ('fallbackDetails' === key && typeof value === 'object') {
                merged.fallbackDetails = value;
                return;
            }

            if ('history' === key && Array.isArray(value)) {
                merged.history = value;
                return;
            }

            merged[key] = value;
        });

        return merged;
    }

    function ensureStatusState(state) {
        if (!state) {
            return null;
        }

        if (!state[STATUS_STATE_KEY]) {
            state[STATUS_STATE_KEY] = {
                meta: null,
                elements: null,
                countdownId: null,
                nextTimestamp: null,
                totalDuration: null,
                isPaused: false,
                historyExpanded: false,
                isOpen: false,
                previousFocus: null,
                panelKeydownHandler: null,
                focusInHandler: null,
                addedPanelTabIndex: false
            };
        }

        return state[STATUS_STATE_KEY];
    }

    function getStatusElements(container, statusState) {
        if (!statusState) {
            return null;
        }

        if (!statusState.elements) {
            statusState.elements = {
                badge: container.querySelector(STATUS_BADGE_SELECTOR),
                label: container.querySelector(STATUS_LABEL_SELECTOR),
                countdown: container.querySelector(STATUS_COUNTDOWN_SELECTOR),
                progress: container.querySelector(STATUS_PROGRESS_SELECTOR),
                panel: container.querySelector(STATUS_PANEL_SELECTOR),
                toggle: container.querySelector(STATUS_TOGGLE_SELECTOR),
                close: container.querySelector(STATUS_CLOSE_SELECTOR),
                historyList: container.querySelector(STATUS_HISTORY_LIST_SELECTOR),
                historyToggle: container.querySelector(STATUS_HISTORY_TOGGLE_SELECTOR),
                historyEmpty: container.querySelector(STATUS_HISTORY_EMPTY_SELECTOR),
                mode: container.querySelector(STATUS_MODE_SELECTOR),
                lastSync: container.querySelector(STATUS_LAST_SYNC_SELECTOR),
                nextSync: container.querySelector(STATUS_NEXT_SYNC_SELECTOR),
                nextRetry: container.querySelector(STATUS_NEXT_RETRY_SELECTOR),
                forceButton: container.querySelector(STATUS_FORCE_SELECTOR),
                logLink: container.querySelector(STATUS_LOG_LINK_SELECTOR)
            };
        }

        return statusState.elements;
    }

    function isElementVisibleForFocus(element) {
        if (!element) {
            return false;
        }

        if (typeof element.hidden === 'boolean' && element.hidden) {
            return false;
        }

        if (element.hasAttribute && element.hasAttribute('hidden')) {
            return false;
        }

        if (element.getAttribute && element.getAttribute('aria-hidden') === 'true') {
            return false;
        }

        var style = null;

        if (typeof window !== 'undefined' && window.getComputedStyle) {
            style = window.getComputedStyle(element);
        }

        if (style && (style.visibility === 'hidden' || style.display === 'none')) {
            return false;
        }

        if (element.offsetParent === null && typeof element.getClientRects === 'function') {
            var rects = element.getClientRects();

            if (!rects || rects.length === 0) {
                var nodeName = element.nodeName ? element.nodeName.toLowerCase() : '';

                if ('body' !== nodeName) {
                    return false;
                }
            }
        }

        return true;
    }

    function getFocusableElements(root) {
        if (!root || !root.querySelectorAll) {
            return [];
        }

        var candidates = root.querySelectorAll(FOCUSABLE_ELEMENTS_SELECTOR);

        return Array.prototype.filter.call(candidates, function (element) {
            if (!element) {
                return false;
            }

            if (typeof element.disabled === 'boolean' && element.disabled) {
                return false;
            }

            if (element.hasAttribute && element.hasAttribute('disabled')) {
                return false;
            }

            if (element.getAttribute && element.getAttribute('aria-disabled') === 'true') {
                return false;
            }

            return isElementVisibleForFocus(element);
        });
    }

    function focusElement(element) {
        if (!element || typeof element.focus !== 'function') {
            return;
        }

        try {
            element.focus({ preventScroll: true });
        } catch (error) {
            element.focus();
        }
    }

    function updateCountdownDisplay(container, statusState, remainingSeconds) {
        var elements = getStatusElements(container, statusState);

        if (!elements || !elements.countdown) {
            return;
        }

        if (statusState.isPaused) {
            elements.countdown.textContent = getLocalizedString('statusCountdownPaused', 'En pause');
        } else if (typeof remainingSeconds === 'number' && !isNaN(remainingSeconds)) {
            if (remainingSeconds <= 0) {
                elements.countdown.textContent = getLocalizedString('statusCountdownReady', 'Actualisation imminente');
            } else {
                elements.countdown.textContent = formatStatusCountdown(remainingSeconds);
            }
        } else {
            elements.countdown.textContent = '';
        }

        if (!elements.progress) {
            return;
        }

        if (statusState.isPaused || !statusState.nextTimestamp) {
            elements.progress.style.strokeDashoffset = 100;
            return;
        }

        var total = statusState.totalDuration;

        if (typeof total !== 'number' || !isFinite(total) || total <= 0) {
            elements.progress.style.strokeDashoffset = 100;
            return;
        }

        var remaining = (typeof remainingSeconds === 'number' && !isNaN(remainingSeconds)) ? remainingSeconds : (statusState.nextTimestamp - Math.floor(Date.now() / 1000));

        if (remaining <= 0) {
            elements.progress.style.strokeDashoffset = 0;
            return;
        }

        if (remaining > total) {
            remaining = total;
        }

        var progress = 100 - (remaining / total) * 100;
        elements.progress.style.strokeDashoffset = progress;
    }

    function stopStatusCountdown(state) {
        var statusState = ensureStatusState(state);

        if (!statusState) {
            return;
        }

        if (statusState.countdownId) {
            window.clearInterval(statusState.countdownId);
            statusState.countdownId = null;
        }
    }

    function startStatusCountdown(container, state) {
        var statusState = ensureStatusState(state);

        if (!statusState || statusState.isPaused) {
            updateCountdownDisplay(container, statusState, null);
            return;
        }

        stopStatusCountdown(state);

        if (!statusState.nextTimestamp) {
            updateCountdownDisplay(container, statusState, null);
            return;
        }

        function tick() {
            var now = Math.floor(Date.now() / 1000);
            var remaining = statusState.nextTimestamp - now;
            updateCountdownDisplay(container, statusState, remaining);

            if (remaining <= 0) {
                stopStatusCountdown(state);
            }
        }

        tick();

        statusState.countdownId = window.setInterval(tick, 1000);
    }

    function setStatusNextRefresh(container, state, timestampSeconds, durationSeconds) {
        var statusState = ensureStatusState(state);

        if (!statusState) {
            return;
        }

        if (typeof timestampSeconds === 'number' && !isNaN(timestampSeconds) && timestampSeconds > 0) {
            statusState.nextTimestamp = Math.floor(timestampSeconds);
        } else {
            statusState.nextTimestamp = null;
        }

        if (typeof durationSeconds === 'number' && !isNaN(durationSeconds) && durationSeconds > 0) {
            statusState.totalDuration = durationSeconds;
        } else if (statusState.meta && statusState.meta.refreshInterval) {
            statusState.totalDuration = statusState.meta.refreshInterval;
        } else {
            statusState.totalDuration = null;
        }

        if (!statusState.nextTimestamp) {
            stopStatusCountdown(state);
            updateCountdownDisplay(container, statusState, null);
            return;
        }

        startStatusCountdown(container, state);
    }

    function pauseStatusCountdown(container, state) {
        var statusState = ensureStatusState(state);

        if (!statusState) {
            return;
        }

        statusState.isPaused = true;
        stopStatusCountdown(state);
        updateCountdownDisplay(container, statusState, null);
    }

    function resumeStatusCountdown(container, state) {
        var statusState = ensureStatusState(state);

        if (!statusState) {
            return;
        }

        statusState.isPaused = false;
        startStatusCountdown(container, state);
    }

    function renderStatusHistory(container, statusState, locale) {
        var elements = getStatusElements(container, statusState);

        if (!elements || !elements.historyList || !elements.historyEmpty) {
            return;
        }

        var history = (statusState.meta && Array.isArray(statusState.meta.history))
            ? statusState.meta.history.slice(0, 5)
            : [];

        elements.historyList.innerHTML = '';

        if (!history.length) {
            elements.historyList.hidden = true;
            elements.historyList.setAttribute('aria-hidden', 'true');
            elements.historyList.removeAttribute('tabindex');
            elements.historyEmpty.textContent = getLocalizedString('statusHistoryEmpty', 'Aucun incident récent.');
            elements.historyEmpty.hidden = false;

            if (elements.historyToggle) {
                elements.historyToggle.disabled = true;
                elements.historyToggle.setAttribute('aria-disabled', 'true');
                elements.historyToggle.textContent = getLocalizedString('statusHistoryShow', 'Voir le journal');
                elements.historyToggle.setAttribute('aria-expanded', 'false');
            }

            return;
        }

        elements.historyEmpty.hidden = true;

        if (elements.historyToggle) {
            elements.historyToggle.disabled = false;
            elements.historyToggle.removeAttribute('aria-disabled');
            elements.historyToggle.textContent = statusState.historyExpanded
                ? getLocalizedString('statusHistoryHide', 'Masquer le journal')
                : getLocalizedString('statusHistoryShow', 'Voir le journal');
            elements.historyToggle.setAttribute('aria-expanded', statusState.historyExpanded ? 'true' : 'false');
        }

        elements.historyList.hidden = !statusState.historyExpanded;
        elements.historyList.setAttribute('aria-hidden', statusState.historyExpanded ? 'false' : 'true');
        elements.historyList.tabIndex = -1;

        var fragment = document.createDocumentFragment();

        history.forEach(function (entry) {
            if (!entry || typeof entry !== 'object') {
                return;
            }

            var item = document.createElement('li');
            item.className = 'discord-status-history__item';
            item.setAttribute('data-status-history-entry', 'true');
            item.setAttribute('role', 'listitem');
            item.tabIndex = 0;

            var title = document.createElement('div');
            title.className = 'discord-status-history__item-title';
            var titleLabel = typeof entry.label === 'string' && entry.label
                ? entry.label
                : getStatusLabelByVariant(entry.type === 'fallback' ? 'fallback' : statusState.meta.variant);
            title.textContent = titleLabel;
            item.appendChild(title);

            var metaLine = document.createElement('div');
            metaLine.className = 'discord-status-history__item-meta';
            metaLine.textContent = formatStatusTimestamp(entry.timestamp, locale)
                || getLocalizedString('statusNoData', 'Non disponible');
            item.appendChild(metaLine);

            if (entry.reason) {
                var reason = document.createElement('div');
                reason.className = 'discord-status-history__item-reason';
                reason.textContent = entry.reason;
                item.appendChild(reason);
            }

            fragment.appendChild(item);
        });

        elements.historyList.appendChild(fragment);
    }

    function applyStatusMeta(container, state, meta, locale, overrides) {
        var statusState = ensureStatusState(state);

        if (!statusState) {
            return;
        }

        var normalized = normalizeStatusMeta(meta);

        if (statusState.meta) {
            normalized = mergeStatusMeta(statusState.meta, normalized);
        }

        if (overrides) {
            normalized = mergeStatusMeta(normalized, overrides);
        }

        statusState.meta = normalized;

        if (container && container.dataset) {
            try {
                container.dataset.statusMeta = JSON.stringify(normalized);
            } catch (error) {
                // Ignore serialization issues.
            }

            if (normalized.variant) {
                container.dataset.statusVariant = normalized.variant;
            }

            container.dataset.canForceRefresh = normalized.canForceRefresh ? 'true' : 'false';

            if (typeof normalized.cacheDuration === 'number' && isFinite(normalized.cacheDuration) && normalized.cacheDuration > 0) {
                container.dataset.cacheDuration = String(Math.round(normalized.cacheDuration));
            } else if (Object.prototype.hasOwnProperty.call(container.dataset, 'cacheDuration')) {
                try {
                    delete container.dataset.cacheDuration;
                } catch (datasetError) {
                    container.dataset.cacheDuration = '';
                }
            }
        } else if (container && container.setAttribute) {
            container.setAttribute('data-status-variant', normalized.variant || 'unknown');
            container.setAttribute('data-can-force-refresh', normalized.canForceRefresh ? 'true' : 'false');
        }

        var elements = getStatusElements(container, statusState);

        if (elements && elements.badge) {
            var label = getStatusLabelByVariant(normalized.variant);

            if (elements.label) {
                elements.label.textContent = label;
            }

            var ariaLabel = getLocalizedString('statusBadgeAriaLabel', 'Statut des données Discord') + ' : ' + label;
            elements.badge.setAttribute('aria-label', ariaLabel);
        }

        if (elements && elements.mode) {
            var description = getStatusDescriptionByVariant(normalized.variant);
            var modeText = getStatusLabelByVariant(normalized.variant);

            if (description) {
                modeText += ' – ' + description;
            }

            elements.mode.textContent = modeText;
        }

        if (elements && elements.lastSync) {
            var lastSyncText = formatStatusTimestamp(normalized.lastUpdated, locale);
            elements.lastSync.textContent = lastSyncText || getLocalizedString('statusNoData', 'Non disponible');
        }

        if (elements && elements.nextSync) {
            var nextSyncText = formatStatusTimestamp(normalized.nextRefresh, locale);
            elements.nextSync.textContent = nextSyncText || getLocalizedString('statusNoData', 'Non disponible');
        }

        if (elements && elements.nextRetry) {
            var nextRetryText = formatStatusTimestamp(normalized.nextRetry, locale);
            elements.nextRetry.textContent = nextRetryText || getLocalizedString('statusNoData', 'Non disponible');
        }

        if (elements && elements.forceButton) {
            if (normalized.canForceRefresh) {
                elements.forceButton.classList.remove('is-disabled');
                elements.forceButton.removeAttribute('disabled');
            } else {
                elements.forceButton.classList.add('is-disabled');
                elements.forceButton.setAttribute('disabled', 'disabled');
            }
        }

        if (elements && elements.logLink) {
            if (normalized.logsUrl) {
                elements.logLink.href = normalized.logsUrl;
                elements.logLink.hidden = false;
            } else {
                elements.logLink.hidden = true;
            }
        }

        renderStatusHistory(container, statusState, locale);

        if (typeof normalized.nextRefresh === 'number' && normalized.nextRefresh > 0) {
            var durationSeconds = null;

            if (typeof normalized.retryAfter === 'number' && normalized.retryAfter > 0) {
                durationSeconds = normalized.retryAfter;
            } else if (typeof normalized.refreshInterval === 'number' && normalized.refreshInterval > 0) {
                durationSeconds = normalized.refreshInterval;
            }

            setStatusNextRefresh(container, state, normalized.nextRefresh, durationSeconds);
        } else {
            setStatusNextRefresh(container, state, null, null);
        }
    }

    function openStatusPanel(container, statusState) {
        var elements = getStatusElements(container, statusState);

        if (!elements || !elements.panel || statusState.isOpen) {
            return;
        }

        var activeElement = (typeof document !== 'undefined') ? document.activeElement : null;

        if (activeElement && activeElement !== document.body) {
            statusState.previousFocus = activeElement;
        } else {
            statusState.previousFocus = null;
        }

        statusState.isOpen = true;

        elements.panel.hidden = false;
        elements.panel.setAttribute('aria-modal', 'true');

        if (elements.toggle) {
            elements.toggle.setAttribute('aria-expanded', 'true');
        }

        var focusableElements = getFocusableElements(elements.panel);
        var initialFocus = focusableElements.length ? focusableElements[0] : null;

        if (!initialFocus) {
            if (!elements.panel.hasAttribute('tabindex')) {
                elements.panel.setAttribute('tabindex', '-1');
                statusState.addedPanelTabIndex = true;
            }

            initialFocus = elements.panel;
        }

        focusElement(initialFocus);

        if (statusState.panelKeydownHandler) {
            elements.panel.removeEventListener('keydown', statusState.panelKeydownHandler);
        }

        statusState.panelKeydownHandler = function (event) {
            var key = event.key || event.keyCode;

            if ('Escape' === key || 'Esc' === key || key === 27) {
                event.preventDefault();
                closeStatusPanel(container, statusState);
                return;
            }

            if ('Tab' === key || key === 9) {
                var focusable = getFocusableElements(elements.panel);

                if (!focusable.length) {
                    event.preventDefault();
                    focusElement(elements.panel);
                    return;
                }

                var first = focusable[0];
                var last = focusable[focusable.length - 1];
                var active = (typeof document !== 'undefined') ? document.activeElement : null;
                var index = focusable.indexOf(active);

                if (event.shiftKey) {
                    if (index <= 0) {
                        event.preventDefault();
                        focusElement(last);
                    }
                } else if (index === -1 || index >= focusable.length - 1) {
                    event.preventDefault();
                    focusElement(first);
                }
            }
        };

        elements.panel.addEventListener('keydown', statusState.panelKeydownHandler);

        if (statusState.focusInHandler) {
            document.removeEventListener('focusin', statusState.focusInHandler, true);
        }

        statusState.focusInHandler = function (event) {
            if (!statusState.isOpen) {
                return;
            }

            if (!elements.panel.contains(event.target)) {
                var focusable = getFocusableElements(elements.panel);
                var target = focusable.length ? focusable[0] : elements.panel;

                focusElement(target);
            }
        };

        document.addEventListener('focusin', statusState.focusInHandler, true);
    }

    function closeStatusPanel(container, statusState) {
        var elements = getStatusElements(container, statusState);

        if (!elements || !elements.panel) {
            return;
        }

        if (statusState.panelKeydownHandler) {
            elements.panel.removeEventListener('keydown', statusState.panelKeydownHandler);
            statusState.panelKeydownHandler = null;
        }

        if (statusState.focusInHandler) {
            document.removeEventListener('focusin', statusState.focusInHandler, true);
            statusState.focusInHandler = null;
        }

        elements.panel.setAttribute('aria-modal', 'false');
        elements.panel.hidden = true;

        if (statusState.addedPanelTabIndex) {
            elements.panel.removeAttribute('tabindex');
            statusState.addedPanelTabIndex = false;
        }

        if (elements.toggle) {
            elements.toggle.setAttribute('aria-expanded', 'false');
        }

        var rootElement = (typeof document !== 'undefined' && document.documentElement) ? document.documentElement : null;
        var focusTarget = null;

        if (statusState.isOpen) {
            if (statusState.previousFocus && rootElement && rootElement.contains(statusState.previousFocus)) {
                focusTarget = statusState.previousFocus;
            } else if (elements.toggle) {
                focusTarget = elements.toggle;
            }
        }

        statusState.isOpen = false;
        statusState.previousFocus = null;

        if (focusTarget) {
            focusElement(focusTarget);
        }
    }

    function toggleStatusPanel(container, statusState) {
        if (!statusState) {
            return;
        }

        if (statusState.isOpen) {
            closeStatusPanel(container, statusState);
        } else {
            openStatusPanel(container, statusState);
        }
    }

    function parseStatusMetaFromDataset(container) {
        if (!container || !container.dataset || !container.dataset.statusMeta) {
            return null;
        }

        try {
            return JSON.parse(container.dataset.statusMeta);
        } catch (error) {
            return null;
        }
    }

    function initializeStatusPanel(container, state, locale) {
        var statusState = ensureStatusState(state);

        if (!statusState) {
            return;
        }

        var elements = getStatusElements(container, statusState);

        if (!elements || !elements.badge) {
            return;
        }

        var initialMeta = parseStatusMetaFromDataset(container);
        applyStatusMeta(container, state, initialMeta, locale);

        if (elements.toggle) {
            elements.toggle.addEventListener('click', function () {
                toggleStatusPanel(container, statusState);
            });
        }

        if (elements.close) {
            elements.close.addEventListener('click', function () {
                closeStatusPanel(container, statusState);
            });
        }

        if (elements.historyToggle) {
            elements.historyToggle.addEventListener('click', function () {
                statusState.historyExpanded = !statusState.historyExpanded;
                renderStatusHistory(container, statusState, locale);

                if (statusState.historyExpanded) {
                    window.setTimeout(function () {
                        if (!elements.historyList) {
                            return;
                        }

                        var firstEntry = elements.historyList.querySelector('[data-status-history-entry]');

                        if (firstEntry) {
                            focusElement(firstEntry);
                        } else {
                            focusElement(elements.historyList);
                        }
                    }, 0);
                } else if (elements.historyToggle) {
                    focusElement(elements.historyToggle);
                }
            });
        }

        if (elements.forceButton) {
            elements.forceButton.addEventListener('click', function (event) {
                event.preventDefault();

                if (!statusState.meta || !statusState.meta.canForceRefresh) {
                    return;
                }

                if (typeof state.forceRefresh === 'function') {
                    state.forceRefresh();
                    closeStatusPanel(container, statusState);
                }
            });
        }
    }

    function collectConnectionOverrides(container, config) {
        var overrides = {
            profileKey: '',
            serverId: ''
        };

        if (config && typeof config === 'object') {
            if (typeof config.profileKey === 'string' && config.profileKey) {
                overrides.profileKey = config.profileKey;
            }

            if (typeof config.serverId === 'string' && config.serverId) {
                overrides.serverId = config.serverId;
            }
        }

        var dataset = container && container.dataset ? container.dataset : null;

        if (dataset && Object.prototype.hasOwnProperty.call(dataset, 'botTokenOverride')) {
            try {
                delete dataset.botTokenOverride;
            } catch (error) {
                dataset.botTokenOverride = '';
            }
        }

        if (dataset) {
            if (typeof dataset.profileKey === 'string' && dataset.profileKey) {
                overrides.profileKey = dataset.profileKey;
            }

            if (typeof dataset.serverIdOverride === 'string' && dataset.serverIdOverride) {
                overrides.serverId = dataset.serverIdOverride;
            }
        }

        overrides.profileKey = overrides.profileKey ? String(overrides.profileKey).trim() : '';
        overrides.serverId = overrides.serverId ? String(overrides.serverId).trim() : '';

        return overrides;
    }

    function buildRestRequestUrl(baseUrl, overrides) {
        if (typeof baseUrl !== 'string' || !baseUrl) {
            return '';
        }

        var profileKey = overrides && overrides.profileKey ? overrides.profileKey : '';
        var serverId = overrides && overrides.serverId ? overrides.serverId : '';

        var urlObject = null;
        if (typeof URL === 'function') {
            try {
                var origin = (typeof window !== 'undefined' && window.location && window.location.origin)
                    ? window.location.origin
                    : undefined;
                urlObject = new URL(baseUrl, origin);
            } catch (error) {
                urlObject = null;
            }
        }

        if (urlObject && urlObject.searchParams) {
            if (profileKey) {
                urlObject.searchParams.set('profile_key', profileKey);
            }

            if (serverId) {
                urlObject.searchParams.set('server_id', serverId);
            }

            return urlObject.toString();
        }

        var queryParts = [];
        if (profileKey) {
            queryParts.push('profile_key=' + encodeURIComponent(profileKey));
        }

        if (serverId) {
            queryParts.push('server_id=' + encodeURIComponent(serverId));
        }

        if (!queryParts.length) {
            return baseUrl;
        }

        var separator = baseUrl.indexOf('?') === -1 ? '?' : '&';

        return baseUrl + separator + queryParts.join('&');
    }

    function requestStatsViaAjax(config, overrides, extraOptions) {
        if (!config || typeof config.ajaxUrl !== 'string' || !config.ajaxUrl) {
            return Promise.reject(new Error('Missing AJAX endpoint URL'));
        }

        var formData = new FormData();
        formData.append('action', config.action || 'refresh_discord_stats');

        if (config.requiresNonce && config.nonce) {
            formData.append('_ajax_nonce', config.nonce);
        }

        if (overrides && overrides.profileKey) {
            formData.append('profile_key', overrides.profileKey);
        }

        if (overrides && overrides.serverId) {
            formData.append('server_id', overrides.serverId);
        }

        if (extraOptions && extraOptions.forceRefresh) {
            formData.append('force_refresh', 'true');
        }

        return fetch(config.ajaxUrl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        });
    }

    function requestStatsViaRest(config, overrides) {
        if (!config || typeof config.restUrl !== 'string' || !config.restUrl) {
            return Promise.reject(new Error('Missing REST endpoint URL'));
        }

        var endpointUrl = buildRestRequestUrl(config.restUrl, overrides);
        var options = {
            method: 'GET',
            credentials: 'same-origin'
        };

        if (config.restNonce) {
            options.headers = {
                'X-WP-Nonce': config.restNonce
            };
        }

        return fetch(endpointUrl, options);
    }

    function buildAnalyticsRequestUrl(baseUrl, overrides, days) {
        if (typeof baseUrl !== 'string' || !baseUrl) {
            return '';
        }

        try {
            var origin = (typeof window !== 'undefined' && window.location && window.location.origin)
                ? window.location.origin
                : undefined;
            var url = new URL(baseUrl, origin);

            if (overrides && overrides.profileKey) {
                url.searchParams.set('profile_key', overrides.profileKey);
            }

            if (overrides && overrides.serverId) {
                url.searchParams.set('server_id', overrides.serverId);
            }

            if (typeof days === 'number' && isFinite(days) && days > 0) {
                url.searchParams.set('days', String(days));
            }

            return url.toString();
        } catch (error) {
            var queryParts = [];

            if (overrides && overrides.profileKey) {
                queryParts.push('profile_key=' + encodeURIComponent(overrides.profileKey));
            }

            if (overrides && overrides.serverId) {
                queryParts.push('server_id=' + encodeURIComponent(overrides.serverId));
            }

            if (typeof days === 'number' && isFinite(days) && days > 0) {
                queryParts.push('days=' + encodeURIComponent(days));
            }

            if (!queryParts.length) {
                return baseUrl;
            }

            var separator = baseUrl.indexOf('?') === -1 ? '?' : '&';
            return baseUrl + separator + queryParts.join('&');
        }
    }

    function getAnalyticsCacheKey(overrides, days) {
        var profileKey = overrides && overrides.profileKey ? overrides.profileKey : 'default';
        var serverId = overrides && overrides.serverId ? overrides.serverId : '';
        var normalizedDays = (typeof days === 'number' && isFinite(days) && days > 0) ? days : 7;

        return [profileKey, serverId, normalizedDays].join('|');
    }

    function requestAnalyticsSnapshot(config, overrides, days, forceRefresh) {
        if (!config || typeof config.analyticsRestUrl !== 'string' || !config.analyticsRestUrl) {
            return Promise.reject(new Error('Analytics endpoint unavailable'));
        }

        var cacheKey = getAnalyticsCacheKey(overrides, days);
        var now = Date.now();
        var entry = analyticsCache[cacheKey];

        if (!forceRefresh && entry && entry.data && (now - entry.timestamp) < ANALYTICS_CACHE_TTL) {
            return Promise.resolve(entry.data);
        }

        if (!forceRefresh && entry && entry.pending) {
            return entry.pending;
        }

        var endpoint = buildAnalyticsRequestUrl(config.analyticsRestUrl, overrides, days);
        if (!endpoint) {
            return Promise.reject(new Error('Invalid analytics URL'));
        }

        var options = {
            method: 'GET',
            credentials: 'same-origin'
        };

        if (config.analyticsRestNonce) {
            options.headers = {
                'X-WP-Nonce': config.analyticsRestNonce
            };
        }

        var fetchPromise = fetch(endpoint, options)
            .then(function (response) {
                if (!response.ok) {
                    var error = new Error('HTTP ' + response.status);
                    error.status = response.status;
                    throw error;
                }

                return response.json();
            })
            .then(function (payload) {
                if (!payload || payload.success === false) {
                    throw new Error('Invalid analytics payload');
                }

                var data = payload.data || {};
                analyticsCache[cacheKey] = {
                    timestamp: Date.now(),
                    data: data
                };

                return data;
            })
            .catch(function (error) {
                delete analyticsCache[cacheKey];
                throw error;
            });

        analyticsCache[cacheKey] = analyticsCache[cacheKey] || {};
        analyticsCache[cacheKey].pending = fetchPromise;

        return fetchPromise;
    }

    function formatSparklineNumber(value, locale) {
        if (typeof value !== 'number' || !isFinite(value)) {
            return '—';
        }

        try {
            return new Intl.NumberFormat(locale || (globalConfig && globalConfig.locale) || 'fr-FR', {
                maximumFractionDigits: 0
            }).format(value);
        } catch (error) {
            return Math.round(value).toString();
        }
    }

    function renderSparklineEmbed(embedElement, analyticsData, settings, config) {
        if (!embedElement) {
            return;
        }

        var sparklineContainer = embedElement.querySelector('[data-role="discord-sparkline"]');
        var noteElement = embedElement.querySelector('[data-role="discord-sparkline-note"]');

        if (!sparklineContainer) {
            return;
        }

        sparklineContainer.innerHTML = '';

        var metric = settings && settings.metric ? settings.metric : 'online';
        var timeseries = analyticsData && Array.isArray(analyticsData.timeseries)
            ? analyticsData.timeseries
            : [];

        var field = 'online';
        if ('presence' === metric) {
            field = 'presence';
        } else if ('premium' === metric) {
            field = 'premium';
        }

        var values = [];
        var processedPoints = [];

        for (var index = 0; index < timeseries.length; index++) {
            var point = timeseries[index];
            if (!point || typeof point !== 'object') {
                processedPoints.push(null);
                continue;
            }

            var rawValue = point[field];
            if (typeof rawValue !== 'number' || !isFinite(rawValue)) {
                processedPoints.push(null);
                continue;
            }

            processedPoints.push(rawValue);
            values.push(rawValue);
        }

        if (!values.length) {
            if (noteElement) {
                noteElement.textContent = (config && config.sparklineNoData)
                    ? config.sparklineNoData
                    : '';
            }

            return;
        }

        var width = sparklineContainer.clientWidth || 160;
        var height = sparklineContainer.clientHeight || 56;
        var minValue = Math.min.apply(null, values);
        var maxValue = Math.max.apply(null, values);

        if (minValue === maxValue) {
            minValue = minValue - 1;
            maxValue = maxValue + 1;
        }

        var svgNS = 'http://www.w3.org/2000/svg';
        var svg = document.createElementNS(svgNS, 'svg');
        svg.setAttribute('viewBox', '0 0 ' + width + ' ' + height);
        svg.setAttribute('preserveAspectRatio', 'none');

        var strokeColor = '#22c55e';
        if ('presence' === metric) {
            strokeColor = '#3b82f6';
        } else if ('premium' === metric) {
            strokeColor = '#a855f7';
        }

        var pathData = '';
        var pointCount = processedPoints.length;

        for (var i = 0; i < pointCount; i++) {
            var value = processedPoints[i];
            if (typeof value !== 'number' || !isFinite(value)) {
                continue;
            }

            var x = pointCount === 1 ? width : (i / (pointCount - 1)) * width;
            var normalized = (value - minValue) / (maxValue - minValue);

            if (!isFinite(normalized)) {
                normalized = 0.5;
            }

            var y = height - (normalized * height);

            if (y < 0) {
                y = 0;
            } else if (y > height) {
                y = height;
            }

            pathData += (pathData ? ' L' : 'M') + x.toFixed(2) + ' ' + y.toFixed(2);
        }

        var path = document.createElementNS(svgNS, 'path');
        path.setAttribute('d', pathData);
        path.setAttribute('fill', 'none');
        path.setAttribute('stroke', strokeColor);
        path.setAttribute('stroke-width', 2);
        path.setAttribute('stroke-linecap', 'round');
        path.setAttribute('stroke-linejoin', 'round');

        svg.appendChild(path);
        sparklineContainer.appendChild(svg);

        if (noteElement) {
            var lastValue = values[values.length - 1];
            var firstValue = values[0];
            var delta = lastValue - firstValue;
            var deltaLabel;

            if (delta > 0) {
                deltaLabel = '▲' + formatSparklineNumber(Math.abs(delta), config && config.locale);
            } else if (delta < 0) {
                deltaLabel = '▼' + formatSparklineNumber(Math.abs(delta), config && config.locale);
            } else {
                deltaLabel = '±0';
            }

            noteElement.textContent = formatSparklineNumber(lastValue, config && config.locale) + ' (' + deltaLabel + ')';
        }
    }

    function updateSparklineForContainer(container, config, forceRefresh) {
        if (!container || !config || container.dataset.showSparkline !== 'true') {
            return;
        }

        if (!config.analyticsRestUrl) {
            return;
        }

        var embed = container.querySelector('[data-role="discord-analytics-embed"]');
        if (!embed) {
            return;
        }

        var metric = embed.dataset.metric || container.dataset.sparklineMetric || 'online';
        var daysRaw = embed.dataset.days || container.dataset.sparklineDays || '7';
        var days = parseInt(daysRaw, 10);

        if (isNaN(days) || days <= 0) {
            days = 7;
        }

        var overrides = collectConnectionOverrides(container, config);

        requestAnalyticsSnapshot(config, overrides, days, !!forceRefresh)
            .then(function (data) {
                renderSparklineEmbed(embed, data, { metric: metric }, config);
            })
            .catch(function () {
                var note = embed.querySelector('[data-role="discord-sparkline-note"]');
                if (note) {
                    note.textContent = (config && config.sparklineError)
                        ? config.sparklineError
                        : '';
                }
            });
    }

    function setupSparklinePanels(config) {
        if (!config || !config.analyticsRestUrl || typeof document === 'undefined') {
            return;
        }

        var sparklineContainers = document.querySelectorAll('.discord-stats-container[data-show-sparkline="true"]');
        if (!sparklineContainers.length) {
            return;
        }

        Array.prototype.forEach.call(sparklineContainers, function (container) {
            updateSparklineForContainer(container, config, false);
        });
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

    function generateSyntheticRegionLabelId(container) {
        if (!container) {
            return 'discord-region-label-' + Math.random().toString(36).slice(2);
        }

        if (container.getAttribute) {
            var containerId = container.getAttribute('id');
            if (containerId) {
                return containerId + '-region-label';
            }
        }

        return 'discord-region-label-' + Math.random().toString(36).slice(2);
    }

    function getSyntheticRegionLabelElement(container, syntheticId) {
        if (!container || typeof document === 'undefined') {
            return null;
        }

        if (syntheticId) {
            var byId = document.getElementById(syntheticId);
            if (byId && byId.getAttribute('data-region-synthetic-label') === 'true') {
                return byId;
            }
        }

        return container.querySelector(SYNTHETIC_LABEL_SELECTOR);
    }

    function ensureSyntheticRegionLabel(container, syntheticId, labelText) {
        if (!container || typeof document === 'undefined') {
            return null;
        }

        var element = getSyntheticRegionLabelElement(container, syntheticId);

        if (!element) {
            element = document.createElement('span');
            element.className = SCREEN_READER_TEXT_CLASS + ' ' + SYNTHETIC_LABEL_CLASS;
            element.setAttribute('data-region-synthetic-label', 'true');

            if (container.firstChild) {
                container.insertBefore(element, container.firstChild);
            } else {
                container.appendChild(element);
            }
        }

        var finalId = syntheticId;

        if (!finalId && element.getAttribute) {
            var existingId = element.getAttribute('id');
            if (existingId) {
                finalId = existingId;
            }
        }

        if (!finalId) {
            finalId = generateSyntheticRegionLabelId(container);
        }

        if (finalId) {
            element.setAttribute('id', finalId);
        }

        if (typeof labelText === 'string' && element.textContent !== labelText) {
            element.textContent = labelText;
        }

        var dataset = container.dataset || null;
        if (dataset) {
            dataset.regionSyntheticId = finalId;
        } else if (container.setAttribute && finalId) {
            container.setAttribute('data-region-synthetic-id', finalId);
        }

        return element;
    }

    function removeSyntheticRegionLabel(container, syntheticId) {
        if (!container || typeof document === 'undefined') {
            return;
        }

        var element = getSyntheticRegionLabelElement(container, syntheticId);

        if (element && element.parentNode) {
            element.parentNode.removeChild(element);
        }
    }

    function refreshRegionAccessibility(container) {
        if (!container) {
            return;
        }

        if (container.setAttribute && container.getAttribute('role') !== 'region') {
            container.setAttribute('role', 'region');
        }

        var dataset = container.dataset || null;

        var syntheticId = '';
        if (dataset && dataset.regionSyntheticId) {
            syntheticId = String(dataset.regionSyntheticId).trim();
        }

        if (!syntheticId && container.getAttribute) {
            var attrSyntheticId = container.getAttribute('data-region-synthetic-id');
            if (attrSyntheticId && attrSyntheticId.trim()) {
                syntheticId = attrSyntheticId.trim();
            }
        }

        var baseLabel = dataset && dataset.regionLabelBase
            ? dataset.regionLabelBase
            : (container.getAttribute ? container.getAttribute('data-region-label-base') : '') || '';
        var pattern = dataset && dataset.regionLabelPattern
            ? dataset.regionLabelPattern
            : (container.getAttribute ? container.getAttribute('data-region-label-pattern') : '') || '';

        var serverName = '';
        if (dataset && dataset.regionServerName && dataset.regionServerName.trim()) {
            serverName = dataset.regionServerName.trim();
        } else if (dataset && dataset.serverName && dataset.serverName.trim()) {
            serverName = dataset.serverName.trim();
        } else if (container.getAttribute) {
            var attrServerName = container.getAttribute('data-region-server-name')
                || container.getAttribute('data-server-name');
            if (attrServerName && attrServerName.trim()) {
                serverName = attrServerName.trim();
            }
        }

        var label = '';
        if (serverName) {
            if (/%(\d+\$)?s/.test(pattern)) {
                label = pattern.replace(/%(\d+\$)?s/g, serverName);
            } else if (pattern) {
                label = pattern + ' ' + serverName;
            } else if (baseLabel) {
                label = baseLabel + ' – ' + serverName;
            } else {
                label = serverName;
            }
        } else if (pattern && /%(\d+\$)?s/.test(pattern)) {
            if (baseLabel) {
                label = baseLabel;
            } else {
                label = pattern.replace(/%(\d+\$)?s/g, '').trim();
            }
        }

        if (!label) {
            label = baseLabel || serverName || 'Discord';
        }

        if (typeof label === 'string') {
            label = label.trim();
        }

        var labelElements = [];
        var seenElements = [];

        function addLabelElementById(id) {
            if (!id || typeof document === 'undefined') {
                return;
            }

            var trimmed = String(id).trim();
            if (!trimmed) {
                return;
            }

            var element = document.getElementById(trimmed);
            if (!element) {
                return;
            }

            if (seenElements.indexOf(element) !== -1) {
                return;
            }

            seenElements.push(element);
            labelElements.push({
                element: element,
                synthetic: element.getAttribute && element.getAttribute('data-region-synthetic-label') === 'true'
            });
        }

        if (dataset && dataset.regionTitleId) {
            addLabelElementById(dataset.regionTitleId);
        } else if (container.getAttribute) {
            addLabelElementById(container.getAttribute('data-region-title-id'));
        }

        if (dataset && dataset.regionServerId) {
            addLabelElementById(dataset.regionServerId);
        } else if (container.getAttribute) {
            addLabelElementById(container.getAttribute('data-region-server-id'));
        }

        var existingLabelIds = dataset && dataset.regionLabelIds
            ? dataset.regionLabelIds
            : (container.getAttribute ? container.getAttribute('data-region-label-ids') : '');

        if (existingLabelIds) {
            existingLabelIds.split(/\s+/).forEach(function (candidate) {
                addLabelElementById(candidate);
            });
        }

        var hasVisibleLabel = labelElements.some(function (detail) {
            return detail && !detail.synthetic;
        });

        if (!hasVisibleLabel) {
            var syntheticElement = ensureSyntheticRegionLabel(container, syntheticId, label);
            if (syntheticElement) {
                var alreadyTracked = labelElements.some(function (detail) {
                    return detail.element === syntheticElement;
                });

                if (!alreadyTracked) {
                    labelElements.push({
                        element: syntheticElement,
                        synthetic: true
                    });
                }
            }
        } else {
            removeSyntheticRegionLabel(container, syntheticId);

            labelElements = labelElements.filter(function (detail) {
                return detail && !detail.synthetic;
            });
        }

        var labelIds = [];
        labelElements.forEach(function (detail) {
            if (!detail || !detail.element || !detail.element.getAttribute) {
                return;
            }

            var elementId = detail.element.getAttribute('id');
            if (elementId && labelIds.indexOf(elementId) === -1) {
                labelIds.push(elementId);
            }
        });

        if (labelIds.length > 0) {
            var joined = labelIds.join(' ');
            container.setAttribute('aria-labelledby', joined);

            if (dataset) {
                dataset.regionLabelIds = joined;
                dataset.regionLabelling = 'labelledby';
            } else {
                container.setAttribute('data-region-label-ids', joined);
                container.setAttribute('data-region-labelling', 'labelledby');
            }
        } else if (container.removeAttribute) {
            container.removeAttribute('aria-labelledby');

            if (dataset) {
                delete dataset.regionLabelIds;
                dataset.regionLabelling = 'label';
            } else {
                container.removeAttribute('data-region-label-ids');
                container.setAttribute('data-region-labelling', 'label');
            }
        }

        var finalHasVisibleLabel = labelElements.some(function (detail) {
            return detail && !detail.synthetic;
        });

        if (!finalHasVisibleLabel) {
            container.setAttribute('aria-label', label);

            if (dataset) {
                dataset.regionLabel = label;
            } else {
                container.setAttribute('data-region-label', label);
            }
        } else {
            if (container.removeAttribute) {
                container.removeAttribute('aria-label');
            }

            if (dataset) {
                delete dataset.regionLabel;
            } else {
                container.removeAttribute('data-region-label');
            }
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
        refreshRegionAccessibility(container);
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

        var labelId = null;
        if (container && container.dataset && container.dataset.regionServerId) {
            labelId = container.dataset.regionServerId;
        } else if (container && container.getAttribute) {
            labelId = container.getAttribute('data-region-server-id');
        }

        if (labelId && textElement.getAttribute('id') !== labelId) {
            textElement.setAttribute('id', labelId);
        }

        return {
            element: element,
            textElement: textElement
        };
    }

    function updateServerName(container, serverName) {
        if (!container) {
            return;
        }

        var dataset = container.dataset || null;
        var safeName = '';

        if (typeof serverName === 'string') {
            safeName = serverName.trim();
        }

        if (dataset) {
            if (safeName) {
                dataset.regionServerName = safeName;
            } else {
                delete dataset.regionServerName;
            }
        } else if (container.setAttribute) {
            if (safeName) {
                container.setAttribute('data-region-server-name', safeName);
            } else {
                container.removeAttribute('data-region-server-name');
            }
        }

        if (!dataset || dataset.showServerName !== 'true') {
            removeServerNameElement(container);
            return;
        }

        if (!safeName) {
            removeServerNameElement(container);
            return;
        }

        var elements = ensureServerNameElement(container);
        if (!elements) {
            refreshRegionAccessibility(container);
            return;
        }

        if (elements.textElement.textContent !== safeName) {
            elements.textElement.textContent = safeName;
        }

        var labelId = dataset && dataset.regionServerId
            ? dataset.regionServerId
            : (container.getAttribute ? container.getAttribute('data-region-server-id') : '');

        if (labelId) {
            elements.textElement.setAttribute('id', labelId);
        }

        if (dataset) {
            dataset.serverName = safeName;
        }

        refreshRegionAccessibility(container);
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

    function elementHasClass(element, className) {
        if (!element || !className) {
            return false;
        }

        if (element.classList && typeof element.classList.contains === 'function') {
            return element.classList.contains(className);
        }

        if (typeof element.className === 'string' && element.className) {
            return (' ' + element.className + ' ').indexOf(' ' + className + ' ') !== -1;
        }

        return false;
    }

    function ensureNumberValueElement(element) {
        if (!element) {
            return null;
        }

        var existing = element.querySelector('.discord-number-value');
        if (existing) {
            return existing;
        }

        var childNodes = [];
        if (element.childNodes && element.childNodes.length) {
            for (var i = 0; i < element.childNodes.length; i++) {
                childNodes.push(element.childNodes[i]);
            }
        }

        var doc = element.ownerDocument || (typeof document !== 'undefined' ? document : null);
        if (!doc || typeof doc.createElement !== 'function') {
            return null;
        }

        var valueElement = doc.createElement('span');
        valueElement.className = 'discord-number-value';

        var insertionPoint = null;
        for (var j = 0; j < childNodes.length; j++) {
            var child = childNodes[j];
            if (child && child.nodeType === 1 && elementHasClass(child, 'screen-reader-text')) {
                insertionPoint = child;
                break;
            }
        }

        if (insertionPoint && typeof element.insertBefore === 'function') {
            element.insertBefore(valueElement, insertionPoint);
        } else if (typeof element.appendChild === 'function') {
            element.appendChild(valueElement);
        }

        for (var k = 0; k < childNodes.length; k++) {
            var node = childNodes[k];
            if (!node || node === valueElement) {
                continue;
            }

            if (node.nodeType === 3) {
                if (node.parentNode === element) {
                    element.removeChild(node);
                }
            }
        }

        return valueElement;
    }

    function setNumberElementText(element, text) {
        if (!element) {
            return;
        }

        var target = element;

        if (!elementHasClass(element, 'discord-number-value')) {
            target = ensureNumberValueElement(element);
        }

        if (!target) {
            return;
        }

        if (typeof text === 'undefined' || text === null) {
            text = '';
        }

        target.textContent = text;
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

        setNumberElementText(element, safeFormatter.format(value));

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

    var presenceExplorerStateStore = (typeof WeakMap === 'function') ? new WeakMap() : null;
    var PRESENCE_STATE_PROP = '__discordPresenceExplorerState';
    var COMPARISON_EXPORT_PROP = '__discordComparisonExportInitialized';

    function setPresenceExplorerState(card, state) {
        if (!card) {
            return;
        }

        if (presenceExplorerStateStore) {
            presenceExplorerStateStore.set(card, state);
            return;
        }

        card[PRESENCE_STATE_PROP] = state;
    }

    function getPresenceExplorerState(card) {
        if (!card) {
            return null;
        }

        if (presenceExplorerStateStore) {
            return presenceExplorerStateStore.get(card) || null;
        }

        if (Object.prototype.hasOwnProperty.call(card, PRESENCE_STATE_PROP)) {
            return card[PRESENCE_STATE_PROP];
        }

        return null;
    }

    function getPresenceFormatter(formatter) {
        if (formatter && typeof formatter.format === 'function') {
            return formatter;
        }

        return DEFAULT_NUMBER_FORMATTER;
    }

    function formatPresenceNumber(value, formatter) {
        if (typeof value !== 'number' || !isFinite(value)) {
            return '—';
        }

        var safeFormatter = getPresenceFormatter(formatter);

        try {
            return safeFormatter.format(Math.max(0, Math.round(value)));
        } catch (error) {
            return String(Math.max(0, Math.round(value)));
        }
    }

    function parseJsonDatasetValue(element, property) {
        if (!element || !element.dataset) {
            return null;
        }

        if (!Object.prototype.hasOwnProperty.call(element.dataset, property)) {
            return null;
        }

        var raw = element.dataset[property];

        if (typeof raw !== 'string' || !raw) {
            return null;
        }

        try {
            return JSON.parse(raw);
        } catch (error) {
            return null;
        }
    }

    function normalizePresenceBreakdown(source) {
        if (!source || typeof source !== 'object') {
            return {};
        }

        var normalized = {};
        Object.keys(source).forEach(function (key) {
            if (!Object.prototype.hasOwnProperty.call(source, key)) {
                return;
            }

            var slug = key;
            if (typeof slug === 'string') {
                slug = slug.trim();
            }

            if (!slug) {
                return;
            }

            var count = source[key];
            if (typeof count !== 'number') {
                count = parseInt(count, 10);
            }

            if (isNaN(count) || count < 0) {
                count = 0;
            }

            normalized[slug] = count;
        });

        return normalized;
    }

    function computePresenceTotal(breakdown) {
        if (!breakdown || typeof breakdown !== 'object') {
            return 0;
        }

        var total = 0;
        Object.keys(breakdown).forEach(function (key) {
            var value = breakdown[key];
            if (typeof value !== 'number') {
                value = parseInt(value, 10);
            }

            if (!isNaN(value)) {
                total += Math.max(0, value);
            }
        });

        return total;
    }

    function parsePresenceBreakdownFromDataset(card) {
        if (!card) {
            return {};
        }

        var breakdown = parseJsonDatasetValue(card, 'presenceBreakdown');
        if (breakdown) {
            return normalizePresenceBreakdown(breakdown);
        }

        var listItems = card.querySelectorAll('.discord-presence-item');
        if (!listItems.length) {
            return {};
        }

        var extracted = {};
        Array.prototype.forEach.call(listItems, function (item) {
            var status = item.getAttribute('data-status') || '';
            if (!status) {
                return;
            }

            var rawValue = item.getAttribute('data-count');
            if (rawValue === null) {
                rawValue = item.getAttribute('data-value');
            }

            var count = parseInt(rawValue, 10);
            if (isNaN(count) || count < 0) {
                count = 0;
            }

            extracted[status] = count;
        });

        return normalizePresenceBreakdown(extracted);
    }

    function parsePresenceLabels(card) {
        if (!card) {
            return {};
        }

        var labels = parseJsonDatasetValue(card, 'presenceLabels');
        if (labels && typeof labels === 'object') {
            return labels;
        }

        var map = {};
        var listItems = card.querySelectorAll('.discord-presence-item');
        Array.prototype.forEach.call(listItems, function (item) {
            var status = item.getAttribute('data-status');
            var label = item.getAttribute('data-label');
            if (status) {
                map[status] = label || status;
            }
        });

        return map;
    }

    function updatePresenceChipActiveState(state) {
        if (!state || !state.chips) {
            return;
        }

        var active = state.activeStatuses || ['all'];

        state.chips.forEach(function (chip) {
            if (!chip || !chip.element) {
                return;
            }

            var isActive = active.indexOf('all') !== -1
                ? chip.status === 'all'
                : active.indexOf(chip.status) !== -1;

            if (chip.element.classList && typeof chip.element.classList.toggle === 'function') {
                chip.element.classList.toggle('is-active', isActive);
            } else {
                chip.element.setAttribute('data-active', isActive ? 'true' : 'false');
            }

            if (typeof chip.element.setAttribute === 'function') {
                chip.element.setAttribute('aria-pressed', isActive ? 'true' : 'false');
            }
        });
    }

    function updatePresenceChipValues(state) {
        if (!state || !state.chips) {
            return;
        }

        var formatter = state.formatter;
        var breakdown = state.breakdown || {};

        state.chips.forEach(function (chip) {
            if (!chip || !chip.element) {
                return;
            }

            var value = chip.status === 'all'
                ? state.total
                : breakdown[chip.status] || 0;

            var valueElement = chip.element.querySelector('[data-role="discord-presence-chip-value"]');
            if (valueElement) {
                valueElement.textContent = formatPresenceNumber(value, formatter);
            }

            chip.element.setAttribute('data-count', String(Math.max(0, value || 0)));
        });
    }

    function updatePresenceList(state) {
        if (!state || !state.listElement) {
            return;
        }

        var formatter = state.formatter;
        var breakdown = state.breakdown || {};
        var total = state.total || 0;
        var items = state.listElement.querySelectorAll('.discord-presence-item');

        Array.prototype.forEach.call(items, function (item) {
            var status = item.getAttribute('data-status');
            if (!status) {
                return;
            }

            var count = breakdown[status] || 0;
            var share = total > 0 ? Math.round((count / total) * 100) : 0;

            item.setAttribute('data-count', String(Math.max(0, count)));
            item.setAttribute('data-share', String(Math.max(0, share)));

            var valueElement = item.querySelector('.discord-presence-item-value');
            if (valueElement) {
                valueElement.textContent = formatPresenceNumber(count, formatter);
            }

            var shareElement = item.querySelector('.discord-presence-item-share');
            if (shareElement) {
                shareElement.textContent = share + '%';
            }
        });
    }

    function applyPresenceExplorerFilters(state) {
        if (!state) {
            return;
        }

        var active = state.activeStatuses && state.activeStatuses.length
            ? state.activeStatuses.slice()
            : ['all'];

        if (active.indexOf('all') !== -1 && active.length > 1) {
            active = active.filter(function (status) {
                return status !== 'all';
            });
        }

        if (!active.length) {
            active = ['all'];
        }

        state.activeStatuses = active;

        var breakdown = state.breakdown || {};
        var total = state.total || 0;
        var selectedTotal;

        if (active.indexOf('all') !== -1) {
            selectedTotal = total;
        } else {
            selectedTotal = 0;
            active.forEach(function (status) {
                var value = breakdown[status];
                if (typeof value !== 'number') {
                    value = parseInt(value, 10);
                }

                if (!isNaN(value)) {
                    selectedTotal += Math.max(0, value);
                }
            });
        }

        if (state.listElement) {
            var items = state.listElement.querySelectorAll('.discord-presence-item');
            Array.prototype.forEach.call(items, function (item) {
                var status = item.getAttribute('data-status');
                var shouldShow = active.indexOf('all') !== -1 || active.indexOf(status) !== -1;

                if (shouldShow) {
                    item.removeAttribute('hidden');
                    item.setAttribute('data-visible', 'true');
                } else {
                    item.setAttribute('hidden', 'hidden');
                    item.setAttribute('data-visible', 'false');
                }
            });
        }

        var formatter = state.formatter;
        var formattedSelected = formatPresenceNumber(selectedTotal, formatter);
        var share = total > 0 ? Math.round((selectedTotal / total) * 100) : 0;
        var shareText = share + '%';

        if (state.selectionValueElement) {
            state.selectionValueElement.textContent = formattedSelected;
        }

        if (state.selectionShareElement) {
            state.selectionShareElement.textContent = shareText;
        }

        if (state.metaValueElement) {
            state.metaValueElement.textContent = formattedSelected;
        }

        if (state.metaShareElement) {
            state.metaShareElement.textContent = shareText;
        }
    }

    function formatHourLabel(hour, locale) {
        var date;
        try {
            date = new Date(Date.UTC(2023, 0, 1, hour, 0, 0));
        } catch (error) {
            date = null;
        }

        if (!date || isNaN(date.getTime())) {
            return hour + 'h';
        }

        try {
            return date.toLocaleTimeString(locale || undefined, { hour: '2-digit', minute: '2-digit' });
        } catch (error) {
            return hour + 'h';
        }
    }

    function getWeekdayLabels(locale) {
        var labels = [];
        var dayOrder = [1, 2, 3, 4, 5, 6, 0];

        for (var i = 0; i < dayOrder.length; i++) {
            var dayIndex = dayOrder[i];
            var date;
            try {
                date = new Date(Date.UTC(2023, 0, 1 + dayIndex));
            } catch (error) {
                date = null;
            }

            var label;
            if (!date || isNaN(date.getTime())) {
                var fallback = ['Dim', 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam'];
                label = fallback[dayIndex] || 'Jour';
            } else {
                try {
                    label = date.toLocaleDateString(locale || undefined, { weekday: 'short' });
                } catch (error) {
                    var fallbackLabels = ['Dim', 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam'];
                    label = fallbackLabels[dayIndex] || 'Jour';
                }
            }

            labels.push(label);
        }

        return labels;
    }

    function buildHeatmapMatrix(timeseries, metric) {
        var dayBuckets = {};
        var maxValue = 0;

        if (!Array.isArray(timeseries)) {
            return { rows: [], max: maxValue };
        }

        for (var i = 0; i < timeseries.length; i++) {
            var entry = timeseries[i];
            if (!entry) {
                continue;
            }

            var value = entry[metric];
            if (typeof value !== 'number') {
                value = parseInt(value, 10);
            }

            if (!isFinite(value) || value < 0) {
                continue;
            }

            var timestamp = entry.timestamp;
            if (typeof timestamp !== 'number') {
                timestamp = parseInt(timestamp, 10);
            }

            if (!isFinite(timestamp)) {
                continue;
            }

            var date;
            try {
                date = new Date(timestamp * 1000);
            } catch (error) {
                date = null;
            }

            if (!date || isNaN(date.getTime())) {
                continue;
            }

            var day = date.getDay();
            var hour = date.getHours();
            var key = day + ':' + hour;

            if (!dayBuckets[key]) {
                dayBuckets[key] = { total: 0, count: 0 };
            }

            dayBuckets[key].total += value;
            dayBuckets[key].count += 1;
        }

        var rows = [];
        var dayOrder = [1, 2, 3, 4, 5, 6, 0];

        for (var d = 0; d < dayOrder.length; d++) {
            var dayIndex = dayOrder[d];
            var cells = [];

            for (var h = 0; h < 24; h++) {
                var bucket = dayBuckets[dayIndex + ':' + h];
                var average = null;

                if (bucket && bucket.count > 0) {
                    average = bucket.total / bucket.count;
                    if (average > maxValue) {
                        maxValue = average;
                    }
                }

                cells.push({ hour: h, value: average });
            }

            rows.push({ day: dayIndex, cells: cells });
        }

        return {
            rows: rows,
            max: maxValue
        };
    }

    function getPresenceMetricLabel(state, metric) {
        if (!state) {
            return metric;
        }

        switch (metric) {
            case 'presence':
                return state.presenceLabel || getLocalizedString('presenceMetricPresence', 'Présence');
            case 'online':
                return state.onlineLabel || getLocalizedString('presenceMetricOnline', 'En ligne');
            case 'total':
                return state.totalLabel || getLocalizedString('presenceMetricTotal', 'Membres');
            default:
                return metric;
        }
    }

    function formatHeatmapSummary(template, metricLabel) {
        if (typeof template !== 'string') {
            return '';
        }

        if (template.indexOf('%s') !== -1) {
            return template.replace('%s', metricLabel || '');
        }

        if (!metricLabel) {
            return template;
        }

        return metricLabel + ' — ' + template;
    }

    function renderPresenceHeatmap(state, matrix, metric) {
        if (!state || !state.heatmapElement) {
            return;
        }

        var container = state.heatmapElement;
        container.innerHTML = '';

        if (state.heatmapEmptyElement) {
            state.heatmapEmptyElement.setAttribute('hidden', 'hidden');
        }

        var maxValue = matrix && typeof matrix.max === 'number' ? matrix.max : 0;
        var rows = matrix && matrix.rows ? matrix.rows : [];
        var labels = getWeekdayLabels(state.locale);
        var hours = [];

        for (var hourIndex = 0; hourIndex < 24; hourIndex++) {
            hours.push(hourIndex);
        }

        var summaryId = container.id ? container.id + '-summary' : '';
        var summary = document.createElement('p');
        summary.className = 'discord-presence-heatmap__summary screen-reader-text';
        summary.setAttribute('data-role', 'discord-presence-heatmap-summary');
        if (summaryId) {
            summary.id = summaryId;
        }

        var summaryTemplate = container.dataset && container.dataset.labelHeatmapSummary
            ? container.dataset.labelHeatmapSummary
            : getLocalizedString(
                'presenceHeatmapSummary',
                'Carte de chaleur présentant la répartition de %s par jour et par heure. Consultez le tableau suivant pour les valeurs détaillées.'
            );

        var emptySummaryTemplate = container.dataset && container.dataset.labelHeatmapSummaryEmpty
            ? container.dataset.labelHeatmapSummaryEmpty
            : getLocalizedString(
                'presenceHeatmapSummaryEmpty',
                'Aucune donnée de présence n’est disponible pour générer la carte de chaleur pour le moment.'
            );

        var metricLabel = getPresenceMetricLabel(state, metric);
        var hasRows = rows.length > 0;
        summary.textContent = hasRows
            ? formatHeatmapSummary(summaryTemplate, metricLabel)
            : emptySummaryTemplate;

        container.appendChild(summary);

        if (!hasRows) {
            if (state.heatmapEmptyElement) {
                var placeholderMessage = state.analyticsError
                    ? getLocalizedString('presenceAnalyticsError', 'Données analytics indisponibles.')
                    : (state.heatmapEmptyDefaultText || getLocalizedString('presenceAnalyticsEmpty', 'En attente de données historiques…'));
                state.heatmapEmptyElement.textContent = placeholderMessage;
                state.heatmapEmptyElement.removeAttribute('hidden');
                container.appendChild(state.heatmapEmptyElement);
            }

            if (summaryId) {
                container.setAttribute('aria-describedby', summaryId);
            } else {
                container.removeAttribute('aria-describedby');
            }

            return;
        }

        var describedbyIds = summaryId ? [summaryId] : [];

        for (var i = 0; i < rows.length; i++) {
            var row = rows[i];
            var rowElement = document.createElement('div');
            rowElement.className = 'discord-presence-heatmap__row';

            var labelElement = document.createElement('span');
            labelElement.className = 'discord-presence-heatmap__label';
            labelElement.textContent = labels[i] || '';
            rowElement.appendChild(labelElement);

            var cellsWrapper = document.createElement('div');
            cellsWrapper.className = 'discord-presence-heatmap__cells';

            var cells = row && row.cells ? row.cells : [];
            for (var j = 0; j < cells.length; j++) {
                var cellData = cells[j];
                var cell = document.createElement('span');
                cell.className = 'discord-presence-heatmap__cell';

                var value = cellData && typeof cellData.value === 'number' ? cellData.value : null;
                var ratio = 0;

                if (value !== null && isFinite(value) && maxValue > 0) {
                    ratio = Math.min(1, Math.max(0, value / maxValue));
                }

                cell.setAttribute('data-hour', String(cellData.hour));
                cell.setAttribute('data-value', value === null ? '' : String(Math.round(value)));
                cell.setAttribute('data-intensity', ratio.toFixed(2));
                cell.style.setProperty('--intensity', ratio.toFixed(3));

                var tooltipValue = value === null ? '—' : formatPresenceNumber(value, state.formatter);
                var hourLabel = formatHourLabel(cellData.hour, state.locale);
                var dayLabel = labels[i] || '';
                cell.setAttribute('title', dayLabel + ' ' + hourLabel + ' • ' + tooltipValue);
                cell.setAttribute('aria-label', dayLabel + ' ' + hourLabel + ' • ' + tooltipValue);

                cellsWrapper.appendChild(cell);
            }

            rowElement.appendChild(cellsWrapper);
            container.appendChild(rowElement);
        }

        if (rows.length) {
            var table = document.createElement('table');
            table.className = 'discord-presence-heatmap__table screen-reader-text';
            table.setAttribute('role', 'table');

            var captionText = state.card && state.card.dataset && state.card.dataset.labelHeatmapTable
                ? state.card.dataset.labelHeatmapTable
                : getLocalizedString('presenceHeatmapTableCaption', 'Presence breakdown by day and hour');

            var caption = document.createElement('caption');
            caption.textContent = captionText;
            table.appendChild(caption);

            var dayHeaderText = state.card && state.card.dataset && state.card.dataset.labelHeatmapDay
                ? state.card.dataset.labelHeatmapDay
                : getLocalizedString('presenceHeatmapDayLabel', 'Day');

            var hourHeaderText = state.card && state.card.dataset && state.card.dataset.labelHeatmapHour
                ? state.card.dataset.labelHeatmapHour
                : getLocalizedString('presenceHeatmapHourLabel', 'Hour');

            var thead = document.createElement('thead');
            var headerRow = document.createElement('tr');

            var cornerHeader = document.createElement('th');
            cornerHeader.scope = 'col';
            cornerHeader.textContent = dayHeaderText;
            headerRow.appendChild(cornerHeader);

            hours.forEach(function (hour) {
                var th = document.createElement('th');
                th.scope = 'col';
                th.textContent = formatHourLabel(hour, state.locale) || hourHeaderText + ' ' + hour;
                headerRow.appendChild(th);
            });

            thead.appendChild(headerRow);
            table.appendChild(thead);

            var tbody = document.createElement('tbody');

            for (var rowIndex = 0; rowIndex < rows.length; rowIndex++) {
                var matrixRow = rows[rowIndex];
                var tr = document.createElement('tr');
                var rowHeader = document.createElement('th');
                rowHeader.scope = 'row';
                rowHeader.textContent = labels[rowIndex] || dayHeaderText;
                tr.appendChild(rowHeader);

                for (var hourPosition = 0; hourPosition < hours.length; hourPosition++) {
                    var hourValue = hours[hourPosition];
                    var tableCellData = matrixRow && matrixRow.cells ? matrixRow.cells[hourValue] : null;
                    var td = document.createElement('td');
                    var numericValue = tableCellData && typeof tableCellData.value === 'number'
                        ? tableCellData.value
                        : null;

                    td.textContent = numericValue === null
                        ? '—'
                        : formatPresenceNumber(numericValue, state.formatter);

                    if (tableCellData) {
                        td.setAttribute('data-hour', String(tableCellData.hour));
                    }

                    tr.appendChild(td);
                }

                tbody.appendChild(tr);
            }

            table.appendChild(tbody);
            container.appendChild(table);

            if (container.id) {
                var tableId = container.id + '-table';
                table.id = tableId;
                describedbyIds.push(tableId);
            }
        }

        if (describedbyIds.length) {
            container.setAttribute('aria-describedby', describedbyIds.join(' '));
        } else {
            container.removeAttribute('aria-describedby');
        }

        if (container.dataset) {
            container.dataset.metric = metric;
        }
    }

    function aggregateTimeseriesByDay(timeseries, metric) {
        var buckets = {};

        if (!Array.isArray(timeseries)) {
            return [];
        }

        timeseries.forEach(function (entry) {
            if (!entry) {
                return;
            }

            var timestamp = entry.timestamp;
            if (typeof timestamp !== 'number') {
                timestamp = parseInt(timestamp, 10);
            }

            if (!isFinite(timestamp)) {
                return;
            }

            var date;
            try {
                date = new Date(timestamp * 1000);
            } catch (error) {
                date = null;
            }

            if (!date || isNaN(date.getTime())) {
                return;
            }

            var dayKey = date.getFullYear() + '-' + (date.getMonth() + 1) + '-' + date.getDate();
            if (!buckets[dayKey]) {
                buckets[dayKey] = { total: 0, count: 0, timestamp: timestamp };
            }

            var value = entry[metric];
            if (typeof value !== 'number') {
                value = parseInt(value, 10);
            }

            if (!isNaN(value) && value >= 0) {
                buckets[dayKey].total += value;
                buckets[dayKey].count += 1;
            }

            if (timestamp > buckets[dayKey].timestamp) {
                buckets[dayKey].timestamp = timestamp;
            }
        });

        var aggregated = Object.keys(buckets).map(function (key) {
            var bucket = buckets[key];
            var average = bucket.count > 0 ? bucket.total / bucket.count : null;
            if (average === null) {
                return null;
            }

            return {
                timestamp: bucket.timestamp,
                value: average
            };
        }).filter(function (entry) { return !!entry; });

        aggregated.sort(function (a, b) {
            return a.timestamp - b.timestamp;
        });

        return aggregated;
    }

    function formatTimelineDate(timestamp, locale) {
        if (typeof timestamp !== 'number' || !isFinite(timestamp)) {
            return '';
        }

        var date;
        try {
            date = new Date(timestamp * 1000);
        } catch (error) {
            date = null;
        }

        if (!date || isNaN(date.getTime())) {
            return '';
        }

        try {
            return date.toLocaleDateString(locale || undefined, { month: 'short', day: 'numeric' });
        } catch (error) {
            return date.getDate() + '/' + (date.getMonth() + 1);
        }
    }

    function renderPresenceTimeline(state, aggregated, metric) {
        if (!state || !state.timelineBodyElement) {
            return;
        }

        var container = state.timelineBodyElement;
        container.innerHTML = '';

        var captionText = state.timelineTableCaption
            || getLocalizedString('presenceTimelineTableCaption', 'Presence trend over the selected period');
        var dateHeaderText = state.timelineDateLabel
            || getLocalizedString('presenceTimelineDateLabel', 'Date');
        var valueHeaderText = state.timelineValueLabel
            || getLocalizedString('presenceTimelineValueLabel', 'Average value');

        if (!aggregated || !aggregated.length) {
            if (state.timelineEmptyElement) {
                var emptyMessage = state.analyticsError
                    ? getLocalizedString('presenceAnalyticsError', 'Données analytics indisponibles.')
                    : (state.timelineEmptyDefaultText || getLocalizedString('presenceAnalyticsEmpty', 'Aucune donnée historique disponible pour le moment.'));
                state.timelineEmptyElement.textContent = emptyMessage;
                state.timelineEmptyElement.removeAttribute('hidden');
            }
            return;
        }

        if (state.timelineEmptyElement) {
            state.timelineEmptyElement.setAttribute('hidden', 'hidden');
        }

        var maxValue = 0;
        aggregated.forEach(function (entry) {
            if (entry && typeof entry.value === 'number' && entry.value > maxValue) {
                maxValue = entry.value;
            }
        });

        if (maxValue <= 0) {
            maxValue = 1;
        }

        var list = document.createElement('ol');
        list.className = 'discord-presence-timeline__list';
        var formatter = state.formatter;
        var tableRows = [];

        for (var i = 0; i < aggregated.length; i++) {
            var entry = aggregated[i];
            var item = document.createElement('li');
            item.className = 'discord-presence-timeline__item';

            var label = document.createElement('span');
            label.className = 'discord-presence-timeline__timestamp';
            var formattedDate = formatTimelineDate(entry.timestamp, state.locale);
            label.textContent = formattedDate;
            item.appendChild(label);

            var bar = document.createElement('div');
            bar.className = 'discord-presence-timeline__bar';
            var value = entry && typeof entry.value === 'number' ? entry.value : 0;
            var ratio = value > 0 ? Math.min(1, value / maxValue) : 0;
            bar.style.setProperty('--value', ratio.toFixed(3));
            bar.setAttribute('aria-valuemin', '0');
            bar.setAttribute('aria-valuemax', String(Math.round(maxValue)));
            bar.setAttribute('aria-valuenow', String(Math.round(value)));
            bar.setAttribute('role', 'img');
            var formattedValue = formatPresenceNumber(value, formatter);
            bar.setAttribute('title', formattedValue);
            bar.setAttribute('aria-label', (formattedDate || dateHeaderText) + ' • ' + formattedValue);

            var barValue = document.createElement('span');
            barValue.className = 'discord-presence-timeline__value';
            barValue.textContent = formattedValue;
            bar.appendChild(barValue);

            item.appendChild(bar);
            list.appendChild(item);

            tableRows.push({
                timestamp: entry && typeof entry.timestamp === 'number' ? entry.timestamp : null,
                date: formattedDate,
                value: formattedValue,
                numericValue: typeof value === 'number' ? value : null
            });
        }

        container.appendChild(list);

        var table = document.createElement('table');
        table.className = 'discord-presence-timeline__table screen-reader-text';
        table.setAttribute('role', 'table');

        if (captionText) {
            var caption = document.createElement('caption');
            caption.textContent = captionText;
            table.appendChild(caption);
        }

        var thead = document.createElement('thead');
        var headerRow = document.createElement('tr');
        var dateHeader = document.createElement('th');
        dateHeader.scope = 'col';
        dateHeader.textContent = dateHeaderText;
        headerRow.appendChild(dateHeader);

        var valueHeader = document.createElement('th');
        valueHeader.scope = 'col';
        valueHeader.textContent = valueHeaderText;
        headerRow.appendChild(valueHeader);

        thead.appendChild(headerRow);
        table.appendChild(thead);

        var tbody = document.createElement('tbody');

        tableRows.forEach(function (row) {
            var tr = document.createElement('tr');

            var dateCell = document.createElement('th');
            dateCell.scope = 'row';
            dateCell.textContent = row.date || dateHeaderText;
            if (row.timestamp !== null) {
                dateCell.setAttribute('data-timestamp', String(row.timestamp));
            }
            tr.appendChild(dateCell);

            var valueCell = document.createElement('td');
            if (row.numericValue === null || isNaN(row.numericValue)) {
                valueCell.textContent = '—';
            } else {
                valueCell.textContent = row.value;
            }
            tr.appendChild(valueCell);

            tbody.appendChild(tr);
        });

        table.appendChild(tbody);
        container.appendChild(table);

        if (table.dataset) {
            table.dataset.metric = metric;
        }

        if (container.dataset) {
            container.dataset.metric = metric;
        }
    }

    function createPresenceMetricToolbar(state) {
        if (!state || !state.timelineToolbarElement) {
            return;
        }

        var toolbar = state.timelineToolbarElement;
        toolbar.innerHTML = '';

        var metrics = [
            { key: 'presence', label: state.presenceLabel || getLocalizedString('presenceMetricPresence', 'Présence') },
            { key: 'online', label: state.onlineLabel || getLocalizedString('presenceMetricOnline', 'En ligne') },
            { key: 'total', label: state.totalLabel || getLocalizedString('presenceMetricTotal', 'Membres') }
        ];

        metrics.forEach(function (metric) {
            var button = document.createElement('button');
            button.type = 'button';
            button.className = 'discord-presence-timeline__metric';
            button.setAttribute('data-metric', metric.key);
            button.setAttribute('aria-pressed', 'false');
            button.textContent = metric.label || metric.key;
            button.addEventListener('click', function () {
                if (state.metric === metric.key) {
                    return;
                }

                state.metric = metric.key;
                updatePresenceMetricButtons(state);
                renderPresenceExplorerAnalytics(state);
            });

            toolbar.appendChild(button);
        });

        updatePresenceMetricButtons(state);
    }

    function updatePresenceMetricButtons(state) {
        if (!state || !state.timelineToolbarElement) {
            return;
        }

        var buttons = state.timelineToolbarElement.querySelectorAll('.discord-presence-timeline__metric');
        Array.prototype.forEach.call(buttons, function (button) {
            var metric = button.getAttribute('data-metric');
            var isActive = metric === state.metric;

            if (button.classList && typeof button.classList.toggle === 'function') {
                button.classList.toggle('is-active', isActive);
            } else if (isActive) {
                button.setAttribute('data-active', 'true');
            } else {
                button.removeAttribute('data-active');
            }

            if (typeof button.setAttribute === 'function') {
                button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
            }
        });
    }

    function renderPresenceExplorerAnalytics(state) {
        if (!state) {
            return;
        }

        updatePresenceMetricButtons(state);

        var heatmapEmpty = state.heatmapEmptyElement;
        var timelineEmpty = state.timelineEmptyElement;

        if (!state.analytics || !Array.isArray(state.analytics.timeseries) || !state.analytics.timeseries.length) {
            if (heatmapEmpty) {
                var heatmapMessage = state.analyticsError
                    ? getLocalizedString('presenceAnalyticsError', 'Données analytics indisponibles.')
                    : (state.heatmapEmptyDefaultText || getLocalizedString('presenceAnalyticsEmpty', 'En attente de données historiques…'));
                heatmapEmpty.textContent = heatmapMessage;
                heatmapEmpty.removeAttribute('hidden');
            }

            if (timelineEmpty) {
                var timelineMessage = state.analyticsError
                    ? getLocalizedString('presenceAnalyticsError', 'Données analytics indisponibles.')
                    : (state.timelineEmptyDefaultText || getLocalizedString('presenceAnalyticsEmpty', 'Aucune donnée historique disponible pour le moment.'));
                timelineEmpty.textContent = timelineMessage;
                timelineEmpty.removeAttribute('hidden');
            }

            if (state.heatmapElement) {
                state.heatmapElement.innerHTML = '';
                if (heatmapEmpty) {
                    state.heatmapElement.appendChild(heatmapEmpty);
                }
                state.heatmapElement.removeAttribute('aria-describedby');
            }

            if (state.timelineBodyElement) {
                state.timelineBodyElement.innerHTML = '';
            }

            return;
        }

        if (heatmapEmpty) {
            heatmapEmpty.setAttribute('hidden', 'hidden');
        }

        if (timelineEmpty) {
            timelineEmpty.setAttribute('hidden', 'hidden');
        }

        var timeseries = state.analytics.timeseries.slice();
        var metric = state.metric || PRESENCE_DEFAULT_METRIC;

        var matrix = buildHeatmapMatrix(timeseries, metric);
        renderPresenceHeatmap(state, matrix, metric);

        var aggregated = aggregateTimeseriesByDay(timeseries, metric);
        renderPresenceTimeline(state, aggregated, metric);
    }

    function loadPresenceExplorerAnalytics(state, forceRefresh) {
        if (!state || !state.config || !state.config.analyticsRestUrl) {
            return;
        }

        if (state.analyticsLoading) {
            return;
        }

        if (!forceRefresh && state.analyticsLoaded) {
            renderPresenceExplorerAnalytics(state);
            return;
        }

        state.analyticsLoading = true;

        var overrides = collectConnectionOverrides(state.container, state.config);
        requestAnalyticsSnapshot(state.config, overrides, state.range, !!forceRefresh)
            .then(function (data) {
                state.analyticsLoading = false;
                state.analyticsLoaded = true;
                state.analyticsError = null;
                state.analytics = data || {};
                renderPresenceExplorerAnalytics(state);
            })
            .catch(function (error) {
                state.analyticsLoading = false;
                state.analyticsLoaded = false;
                state.analyticsError = error;
                state.analytics = null;
                renderPresenceExplorerAnalytics(state);
            });
    }

    function initializePresenceExplorer(container, config, formatter) {
        if (!container) {
            return;
        }

        var card = container.querySelector('[data-role="discord-presence-breakdown"]');
        if (!card) {
            return;
        }

        if (getPresenceExplorerState(card)) {
            return;
        }

        var explorer = card.querySelector(PRESENCE_EXPLORER_SELECTOR);
        if (!explorer) {
            return;
        }

        var filters = explorer.querySelector(PRESENCE_FILTERS_SELECTOR);
        var listElement = card.querySelector(PRESENCE_LIST_SELECTOR);
        var heatmapElement = card.querySelector(PRESENCE_HEATMAP_SELECTOR);
        var heatmapEmptyElement = card.querySelector(PRESENCE_HEATMAP_EMPTY_SELECTOR);
        var timelineElement = card.querySelector(PRESENCE_TIMELINE_SELECTOR);
        var timelineToolbarElement = card.querySelector(PRESENCE_TIMELINE_TOOLBAR_SELECTOR);
        var timelineBodyElement = card.querySelector(PRESENCE_TIMELINE_BODY_SELECTOR);
        var timelineEmptyElement = card.querySelector(PRESENCE_TIMELINE_EMPTY_SELECTOR);
        var selectionValueElement = card.querySelector(PRESENCE_SELECTED_VALUE_SELECTOR);
        var selectionShareElement = card.querySelector(PRESENCE_SELECTED_SHARE_SELECTOR);
        var metaValueElement = card.querySelector(PRESENCE_META_VALUE_SELECTOR);
        var metaShareElement = card.querySelector(PRESENCE_META_SHARE_SELECTOR);
        var totalElement = card.querySelector(PRESENCE_TOTAL_SELECTOR);

        var state = {
            container: container,
            card: card,
            explorer: explorer,
            config: config || {},
            formatter: getPresenceFormatter(formatter),
            locale: (config && config.locale) || (globalConfig && globalConfig.locale) || undefined,
            filtersElement: filters,
            listElement: listElement,
            heatmapElement: heatmapElement,
            heatmapEmptyElement: heatmapEmptyElement,
            heatmapEmptyDefaultText: heatmapEmptyElement ? heatmapEmptyElement.textContent : '',
            timelineElement: timelineElement,
            timelineToolbarElement: timelineToolbarElement,
            timelineBodyElement: timelineBodyElement,
            timelineEmptyElement: timelineEmptyElement,
            timelineEmptyDefaultText: timelineEmptyElement ? timelineEmptyElement.textContent : '',
            timelineTableCaption: timelineElement && timelineElement.dataset && timelineElement.dataset.labelTimelineTable
                ? timelineElement.dataset.labelTimelineTable
                : '',
            timelineDateLabel: timelineElement && timelineElement.dataset && timelineElement.dataset.labelTimelineDate
                ? timelineElement.dataset.labelTimelineDate
                : '',
            timelineValueLabel: timelineElement && timelineElement.dataset && timelineElement.dataset.labelTimelineValue
                ? timelineElement.dataset.labelTimelineValue
                : '',
            selectionValueElement: selectionValueElement,
            selectionShareElement: selectionShareElement,
            metaValueElement: metaValueElement,
            metaShareElement: metaShareElement,
            totalElement: totalElement,
            activeStatuses: ['all'],
            breakdown: {},
            total: 0,
            metric: PRESENCE_DEFAULT_METRIC,
            range: PRESENCE_DEFAULT_RANGE,
            presenceLabel: card.dataset && card.dataset.labelPresence ? card.dataset.labelPresence : '',
            onlineLabel: card.dataset && card.dataset.labelOnlineMetric ? card.dataset.labelOnlineMetric : '',
            totalLabel: card.dataset && card.dataset.labelTotal ? card.dataset.labelTotal : '',
            analyticsLoaded: false,
            analyticsLoading: false,
            analytics: null,
            analyticsError: null
        };

        setPresenceExplorerState(card, state);

        state.breakdown = parsePresenceBreakdownFromDataset(card);
        state.labels = parsePresenceLabels(card);

        var datasetTotal = card.dataset && card.dataset.presenceTotal
            ? parseInt(card.dataset.presenceTotal, 10)
            : null;
        if (isNaN(datasetTotal) || datasetTotal < 0) {
            datasetTotal = computePresenceTotal(state.breakdown);
        }
        state.total = datasetTotal;

        if (state.totalElement) {
            state.totalElement.textContent = formatPresenceNumber(state.total, state.formatter);
        }

        state.chips = [];
        if (filters) {
            var chipElements = filters.querySelectorAll(PRESENCE_CHIP_SELECTOR);
            Array.prototype.forEach.call(chipElements, function (element) {
                var status = element.getAttribute('data-status') || 'all';
                state.chips.push({ element: element, status: status });
                element.addEventListener('click', function () {
                    var current = state.activeStatuses.slice();
                    if (status === 'all') {
                        current = ['all'];
                    } else {
                        var index = current.indexOf(status);
                        if (index === -1) {
                            current = current.filter(function (item) {
                                return item !== 'all';
                            });
                            current.push(status);
                        } else {
                            current.splice(index, 1);
                        }
                    }

                    if (!current.length) {
                        current = ['all'];
                    }

                    state.activeStatuses = current;
                    updatePresenceChipActiveState(state);
                    applyPresenceExplorerFilters(state);
                });
            });
        }

        updatePresenceChipValues(state);
        updatePresenceChipActiveState(state);
        updatePresenceList(state);
        applyPresenceExplorerFilters(state);
        createPresenceMetricToolbar(state);

        if (state.config && state.config.analyticsRestUrl) {
            loadPresenceExplorerAnalytics(state, false);
        }
    }

    function refreshPresenceExplorerBreakdown(container, breakdown, formatter) {
        if (!container) {
            return;
        }

        var card = container.querySelector('[data-role="discord-presence-breakdown"]');
        if (!card) {
            return;
        }

        var state = getPresenceExplorerState(card);
        if (!state) {
            initializePresenceExplorer(container, globalConfig, formatter);
            state = getPresenceExplorerState(card);
            if (!state) {
                return;
            }
        }

        if (formatter && typeof formatter.format === 'function') {
            state.formatter = formatter;
        }

        var normalized = normalizePresenceBreakdown(breakdown);
        if (!Object.keys(normalized).length) {
            normalized = parsePresenceBreakdownFromDataset(card);
        }

        state.breakdown = normalized;

        var datasetTotal = card.dataset && card.dataset.presenceTotal
            ? parseInt(card.dataset.presenceTotal, 10)
            : null;
        if (isNaN(datasetTotal) || datasetTotal < 0) {
            datasetTotal = computePresenceTotal(normalized);
        }
        state.total = datasetTotal;

        if (state.totalElement) {
            state.totalElement.textContent = formatPresenceNumber(state.total, state.formatter);
        }

        if (card.dataset) {
            try {
                card.dataset.presenceBreakdown = JSON.stringify(normalized);
            } catch (error) {
                card.dataset.presenceBreakdown = '';
            }
            card.dataset.presenceTotal = String(Math.max(0, state.total));
        }

        updatePresenceChipValues(state);
        updatePresenceList(state);
        updatePresenceChipActiveState(state);
        applyPresenceExplorerFilters(state);
    }

    function parseComparisonPayload(container) {
        if (!container || !container.dataset || !container.dataset.comparisonPayload) {
            return null;
        }

        try {
            return JSON.parse(container.dataset.comparisonPayload);
        } catch (error) {
            return null;
        }
    }

    function formatComparisonMetric(value, formatter) {
        if (typeof value !== 'number') {
            value = parseInt(value, 10);
        }

        if (isNaN(value)) {
            return '';
        }

        var safeFormatter = getPresenceFormatter(formatter);
        try {
            return safeFormatter.format(value);
        } catch (error) {
            return String(value);
        }
    }

    function formatComparisonDelta(value, formatter) {
        if (typeof value !== 'number') {
            value = parseInt(value, 10);
        }

        if (isNaN(value)) {
            return '';
        }

        if (value === 0) {
            return '0';
        }

        var prefix = value > 0 ? '+' : '−';
        return prefix + formatComparisonMetric(Math.abs(value), formatter);
    }

    function escapeCsvValue(value) {
        if (value === null || typeof value === 'undefined') {
            return '';
        }

        var stringValue = String(value);
        if (stringValue.indexOf('"') !== -1) {
            stringValue = stringValue.replace(/"/g, '""');
        }

        if (/[";\n]/.test(stringValue)) {
            return '"' + stringValue + '"';
        }

        return stringValue;
    }

    function slugifyFilenameSegment(value) {
        if (typeof value !== 'string') {
            value = String(value || '');
        }

        var slug = value
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '');

        return slug || 'export';
    }

    function buildComparisonFilename(payload) {
        var reference = payload && payload.reference ? payload.reference : {};
        var label = reference.label || reference.profile || 'reference';
        var now = new Date();
        var iso = now.toISOString ? now.toISOString() : '';
        var timestamp = iso ? iso.replace(/[:]/g, '-').replace(/\..+$/, '') : String(now.getTime());
        return slugifyFilenameSegment(label) + '-' + timestamp + '.csv';
    }

    function buildComparisonCsv(payload, formatter, labels) {
        if (!payload || !payload.entries || !payload.entries.length) {
            return '';
        }

        var entries = payload.entries;
        var labelMap = labels || {};
        var headers = [
            'Profil',
            'Libellé',
            'Serveur',
            'Statut',
            'Type',
            labelMap.online || 'En ligne',
            labelMap.presence || 'Présence',
            labelMap.total || 'Membres',
            labelMap.premium || 'Boosts',
            'Δ ' + (labelMap.online || 'En ligne'),
            'Δ ' + (labelMap.presence || 'Présence'),
            'Δ ' + (labelMap.total || 'Membres'),
            'Δ ' + (labelMap.premium || 'Boosts')
        ];

        var rows = [headers.map(escapeCsvValue).join(';')];

        entries.forEach(function (entry) {
            if (!entry) {
                return;
            }

            var metrics = entry.metrics || {};
            var deltas = entry.deltas || {};

            rows.push([
                entry.profile || '',
                entry.label || '',
                entry.server_name || '',
                entry.status_label || '',
                entry.is_reference ? 'Référence' : 'Comparé',
                formatComparisonMetric(metrics.online, formatter),
                formatComparisonMetric(metrics.presence, formatter),
                formatComparisonMetric(metrics.total, formatter),
                formatComparisonMetric(metrics.premium, formatter),
                formatComparisonDelta(deltas.online, formatter),
                formatComparisonDelta(deltas.presence, formatter),
                formatComparisonDelta(deltas.total, formatter),
                formatComparisonDelta(deltas.premium, formatter)
            ].map(escapeCsvValue).join(';'));
        });

        return rows.join('\r\n');
    }

    function triggerDownload(content, filename, mimeType) {
        if (typeof content !== 'string' || !content) {
            return;
        }

        var blob;
        try {
            blob = new Blob([content], { type: mimeType || 'text/plain;charset=utf-8' });
        } catch (error) {
            return;
        }

        if (typeof navigator !== 'undefined' && navigator.msSaveOrOpenBlob) {
            navigator.msSaveOrOpenBlob(blob, filename);
            return;
        }

        var url = (typeof URL !== 'undefined' && typeof URL.createObjectURL === 'function')
            ? URL.createObjectURL(blob)
            : null;

        if (!url) {
            return;
        }

        var link = document.createElement('a');
        link.href = url;
        link.download = filename || 'export.csv';
        document.body.appendChild(link);
        link.click();
        setTimeout(function () {
            document.body.removeChild(link);
            URL.revokeObjectURL(url);
        }, 0);
    }

    function handleComparisonExport(container, button) {
        var payload = parseComparisonPayload(container);
        if (!payload || !payload.entries || !payload.entries.length) {
            var emptyMessage = getLocalizedString('comparisonExportEmpty', 'Aucune donnée de comparaison à exporter.');
            if (typeof window !== 'undefined' && typeof window.alert === 'function') {
                window.alert(emptyMessage);
            }
            return;
        }

        var labels = {
            online: container.dataset && container.dataset.labelOnline ? container.dataset.labelOnline : getLocalizedString('labelOnline', 'En ligne'),
            presence: container.dataset && container.dataset.labelPresence ? container.dataset.labelPresence : getLocalizedString('labelPresence', 'Présence'),
            total: container.dataset && container.dataset.labelTotal ? container.dataset.labelTotal : getLocalizedString('labelTotal', 'Membres'),
            premium: container.dataset && container.dataset.labelPremium ? container.dataset.labelPremium : getLocalizedString('labelPremium', 'Boosts serveur')
        };

        var csv = buildComparisonCsv(payload, null, labels);
        if (!csv) {
            var errorMessage = getLocalizedString('comparisonExportError', 'Impossible de générer le fichier d\'export.');
            if (typeof window !== 'undefined' && typeof window.alert === 'function') {
                window.alert(errorMessage);
            }
            return;
        }

        var filename = buildComparisonFilename(payload);
        triggerDownload(csv, filename, 'text/csv;charset=utf-8');
    }

    function initializeComparisonExport(container) {
        if (!container) {
            return;
        }

        var button = container.querySelector(COMPARISON_EXPORT_SELECTOR);
        if (!button) {
            return;
        }

        if (button.dataset && button.dataset.exportInitialized === 'true') {
            return;
        }

        if (button.dataset) {
            button.dataset.exportInitialized = 'true';
        } else {
            button[COMPARISON_EXPORT_PROP] = true;
        }

        button.addEventListener('click', function (event) {
            if (event && typeof event.preventDefault === 'function') {
                event.preventDefault();
            }

            handleComparisonExport(container, button);
        });
    }

    function updatePresenceBreakdown(container, payload, formatter) {
        if (!container) {
            return;
        }

        var card = container.querySelector('[data-role="discord-presence-breakdown"]');

        if (!card) {
            if (container.classList && typeof container.classList.remove === 'function') {
                container.classList.remove('discord-has-presence-breakdown');
            }
            return;
        }

        var breakdownSource = payload && typeof payload.presence_count_by_status === 'object'
            ? payload.presence_count_by_status
            : null;

        var normalizedBreakdown = {};

        if (breakdownSource) {
            for (var key in breakdownSource) {
                if (!Object.prototype.hasOwnProperty.call(breakdownSource, key)) {
                    continue;
                }

                var rawValue = breakdownSource[key];
                var value = typeof rawValue === 'number' ? rawValue : parseInt(rawValue, 10);

                if (isNaN(value) || value < 0) {
                    value = 0;
                }

                var normalizedKey = '';

                if (typeof key === 'string' || typeof key === 'number') {
                    normalizedKey = String(key).toLowerCase().trim();
                }

                if (!normalizedKey) {
                    continue;
                }

                if (normalizedKey === 'do_not_disturb') {
                    normalizedKey = 'dnd';
                }

                if (normalizedKey === 'invisible') {
                    normalizedKey = 'offline';
                }

                if (['online', 'idle', 'dnd', 'offline', 'streaming', 'other'].indexOf(normalizedKey) === -1) {
                    normalizedKey = 'other';
                }

                if (!Object.prototype.hasOwnProperty.call(normalizedBreakdown, normalizedKey)) {
                    normalizedBreakdown[normalizedKey] = 0;
                }

                normalizedBreakdown[normalizedKey] += value;
            }
        }

        var presenceValue = null;

        if (payload && typeof payload.approximate_presence_count === 'number') {
            presenceValue = payload.approximate_presence_count;
        } else if (breakdownSource) {
            presenceValue = 0;
            for (var breakdownKey in normalizedBreakdown) {
                if (!Object.prototype.hasOwnProperty.call(normalizedBreakdown, breakdownKey)) {
                    continue;
                }

                presenceValue += normalizedBreakdown[breakdownKey];
            }
        }

        if (presenceValue !== null && presenceValue < 0) {
            presenceValue = 0;
        }

        if (presenceValue !== null) {
            updateStatElement(card, '.discord-number', presenceValue, formatter);

            if (card.dataset) {
                card.dataset.value = presenceValue;

                try {
                    card.dataset.presenceBreakdown = JSON.stringify(normalizedBreakdown);
                } catch (error) {
                    card.dataset.presenceBreakdown = '';
                }
            }
        } else if (card.dataset) {
            delete card.dataset.value;

            try {
                card.dataset.presenceBreakdown = JSON.stringify(normalizedBreakdown);
            } catch (error) {
                card.dataset.presenceBreakdown = '';
            }
        }

        var list = card.querySelector('.discord-presence-list');

        if (list) {
            while (list.firstChild) {
                list.removeChild(list.firstChild);
            }

            var preferredOrder = ['online', 'idle', 'dnd', 'offline', 'streaming', 'other'];
            var orderedKeys = [];

            for (var i = 0; i < preferredOrder.length; i++) {
                var preferredKey = preferredOrder[i];
                if (Object.prototype.hasOwnProperty.call(normalizedBreakdown, preferredKey)) {
                    orderedKeys.push(preferredKey);
                }
            }

            var remainingKeys = Object.keys(normalizedBreakdown).sort();

            for (var j = 0; j < remainingKeys.length; j++) {
                if (orderedKeys.indexOf(remainingKeys[j]) === -1) {
                    orderedKeys.push(remainingKeys[j]);
                }
            }

            var labelMap = {
                online: card.dataset && card.dataset.labelOnline ? card.dataset.labelOnline : '',
                idle: card.dataset && card.dataset.labelIdle ? card.dataset.labelIdle : '',
                dnd: card.dataset && card.dataset.labelDnd ? card.dataset.labelDnd : '',
                offline: card.dataset && card.dataset.labelOffline ? card.dataset.labelOffline : '',
                streaming: card.dataset && card.dataset.labelStreaming ? card.dataset.labelStreaming : '',
                other: card.dataset && card.dataset.labelOther ? card.dataset.labelOther : ''
            };

            var formatterToUse = formatter && typeof formatter.format === 'function'
                ? formatter
                : DEFAULT_NUMBER_FORMATTER;

            var fragment = document.createDocumentFragment();

            for (var k = 0; k < orderedKeys.length; k++) {
                var statusKey = orderedKeys[k];
                var countValue = normalizedBreakdown[statusKey];

                if (typeof countValue !== 'number') {
                    countValue = parseInt(countValue, 10);
                }

                if (isNaN(countValue) || countValue < 0) {
                    countValue = 0;
                }

                var label = labelMap[statusKey] || statusKey.charAt(0).toUpperCase() + statusKey.slice(1);

                var item = document.createElement('li');
                item.className = 'discord-presence-item discord-presence-' + statusKey;
                item.setAttribute('data-status', statusKey);
                item.setAttribute('data-label', label);

                var dot = document.createElement('span');
                dot.className = 'discord-presence-dot';
                dot.setAttribute('aria-hidden', 'true');
                item.appendChild(dot);

                var labelElement = document.createElement('span');
                labelElement.className = 'discord-presence-item-label';
                labelElement.textContent = label;
                item.appendChild(labelElement);

                var valueElement = document.createElement('span');
                valueElement.className = 'discord-presence-item-value';
                valueElement.textContent = formatterToUse.format(countValue);
                item.appendChild(valueElement);

                fragment.appendChild(item);
            }

            list.appendChild(fragment);
        }

        refreshPresenceExplorerBreakdown(container, normalizedBreakdown, formatter);

        if (container.classList && typeof container.classList.toggle === 'function') {
            var hasPresenceData = !!(presenceValue !== null || (breakdownSource && Object.keys(breakdownSource).length));
            container.classList.toggle('discord-has-presence-breakdown', hasPresenceData);
        }
    }

    function updateApproximateMembers(container, payload, formatter) {
        if (!container) {
            return;
        }

        var card = container.querySelector('[data-role="discord-approximate-members"]');

        if (!card) {
            if (container.classList && typeof container.classList.remove === 'function') {
                container.classList.remove('discord-has-approximate-total');
            }
            return;
        }

        var placeholder = card.dataset && card.dataset.placeholder ? card.dataset.placeholder : '—';
        var value = null;

        if (payload && typeof payload.approximate_member_count === 'number') {
            value = payload.approximate_member_count;
        }

        if (value === null) {
            var numberElement = card.querySelector('.discord-number');
            if (numberElement) {
                setNumberElementText(numberElement, placeholder);
            }

            if (card.dataset && Object.prototype.hasOwnProperty.call(card.dataset, 'value')) {
                delete card.dataset.value;
            }

            if (container.classList && typeof container.classList.remove === 'function') {
                container.classList.remove('discord-has-approximate-total');
            }

            return;
        }

        updateStatElement(card, '.discord-number', value, formatter);

        if (card.dataset) {
            card.dataset.value = value;
        }

        if (container.classList && typeof container.classList.add === 'function') {
            container.classList.add('discord-has-approximate-total');
        }
    }

    function updatePremiumSubscriptions(container, payload, formatter) {
        if (!container) {
            return;
        }

        var card = container.querySelector('[data-role="discord-premium-subscriptions"]');

        if (!card) {
            if (container.classList && typeof container.classList.remove === 'function') {
                container.classList.remove('discord-has-premium');
            }
            return;
        }

        var value = 0;

        if (payload && typeof payload.premium_subscription_count === 'number') {
            value = payload.premium_subscription_count;
        }

        if (value < 0 || !isFinite(value)) {
            value = 0;
        }

        updateStatElement(card, '.discord-number', value, formatter);

        if (card.dataset) {
            card.dataset.value = value;
        }

        var labelElement = card.querySelector('.discord-label-text');
        if (labelElement) {
            var singular = card.dataset && card.dataset.labelPremiumSingular ? card.dataset.labelPremiumSingular : '';
            var plural = card.dataset && card.dataset.labelPremiumPlural ? card.dataset.labelPremiumPlural : '';
            var baseLabel = card.dataset && card.dataset.labelPremium ? card.dataset.labelPremium : '';
            var text = baseLabel;

            if (value === 1 && singular) {
                text = singular;
            } else if (value !== 1 && plural) {
                text = plural;
            } else if (!text) {
                text = value === 1 ? singular : plural;
            }

            if (!text) {
                text = baseLabel;
            }

            labelElement.textContent = text;
        }

        if (container.classList && typeof container.classList.add === 'function') {
            container.classList.add('discord-has-premium');
        }
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

        var numberElement = onlineStat.querySelector('.discord-number');
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

        var labelId = labelTextElement.getAttribute('id');

        if (!labelId) {
            if (onlineStat.dataset && onlineStat.dataset.labelId) {
                labelId = onlineStat.dataset.labelId;
            }

            if (!labelId && onlineStat.id) {
                labelId = onlineStat.id + '-label';
            }

            if (!labelId && container && container.id) {
                labelId = container.id + '-label-online';
            }

            if (!labelId) {
                labelId = 'discord-label-online-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2);
            }

            labelTextElement.setAttribute('id', labelId);

            if (onlineStat.dataset) {
                onlineStat.dataset.labelId = labelId;
            }
        }

        if (numberElement && labelId) {
            var labelledbyAttr = typeof numberElement.getAttribute === 'function'
                ? numberElement.getAttribute('aria-labelledby')
                : null;

            var tokens = labelledbyAttr ? labelledbyAttr.split(/\s+/) : [];
            var filtered = [];

            for (var idx = 0; idx < tokens.length; idx++) {
                var token = tokens[idx];
                if (token) {
                    filtered.push(token);
                }
            }

            if (filtered.indexOf(labelId) === -1) {
                filtered.push(labelId);
            }

            numberElement.setAttribute('aria-labelledby', filtered.join(' '));
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

        if (container.setAttribute) {
            container.setAttribute('aria-busy', 'true');

            if (!container.hasAttribute('aria-live')) {
                container.setAttribute('aria-live', 'polite');
            }
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

        if (container.setAttribute) {
            container.setAttribute('aria-busy', 'false');
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

    function updateStats(container, config, formatter, locale, options) {
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

        var overrides = collectConnectionOverrides(container, config);
        var requestOptions = options || {};
        var forceRefresh = !!requestOptions.forceRefresh;

        var useRestEndpoint = !!(config && typeof config.restUrl === 'string' && config.restUrl);
        var fetchPromise;

        if (useRestEndpoint && !forceRefresh) {
            fetchPromise = requestStatsViaRest(config, overrides)
                .then(function (response) {
                    if (response && response.status === 404 && config && config.ajaxUrl) {
                        return requestStatsViaAjax(config, overrides, requestOptions);
                    }

                    return response;
                })
                .catch(function (error) {
                    if (config && config.ajaxUrl) {
                        return requestStatsViaAjax(config, overrides, requestOptions);
                    }

                    throw error;
                });
        } else {
            fetchPromise = requestStatsViaAjax(config, overrides, requestOptions);
        }

        var requestPromise = fetchPromise
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

                var stateForMeta = getContainerState(container);
                var payloadData = data.data || {};
                var statusMetaPayload = payloadData.status_meta || null;
                var fallbackDetailsPayload = payloadData.fallback_details || null;
                var metaOverrides = {
                    lastUpdated: lastUpdated > 0 ? lastUpdated : null,
                    isDemo: isDemo,
                    isFallbackDemo: isFallbackDemo,
                    isStale: isStale
                };

                var nowSeconds = Math.floor(Date.now() / 1000);
                metaOverrides.generatedAt = nowSeconds;

                var cacheDurationAttr = container && container.dataset && container.dataset.cacheDuration
                    ? parseInt(container.dataset.cacheDuration, 10)
                    : NaN;

                if (!isNaN(cacheDurationAttr) && cacheDurationAttr > 0) {
                    metaOverrides.cacheDuration = cacheDurationAttr;
                }

                if (!statusMetaPayload) {
                    metaOverrides.variant = isFallbackDemo ? 'fallback' : (isDemo ? 'demo' : (isStale ? 'cache' : 'live'));

                    if (fallbackDetailsPayload) {
                        metaOverrides.fallbackDetails = fallbackDetailsPayload;
                    }
                } else if (fallbackDetailsPayload && typeof statusMetaPayload.fallbackDetails === 'undefined') {
                    statusMetaPayload.fallbackDetails = fallbackDetailsPayload;
                }

                if (resultInfo.retryAfter && resultInfo.retryAfter > 0) {
                    var retrySeconds = Math.max(1, Math.round(resultInfo.retryAfter / 1000));
                    metaOverrides.retryAfter = retrySeconds;
                    metaOverrides.nextRefresh = nowSeconds + retrySeconds;
                    metaOverrides.refreshInterval = retrySeconds;
                } else if (stateForMeta && typeof stateForMeta.intervalMs === 'number' && stateForMeta.intervalMs > 0) {
                    var intervalSeconds = Math.round(stateForMeta.intervalMs / 1000);

                    if (intervalSeconds > 0) {
                        if (typeof metaOverrides.refreshInterval === 'undefined') {
                            metaOverrides.refreshInterval = intervalSeconds;
                        }

                        if (typeof metaOverrides.nextRefresh === 'undefined') {
                            metaOverrides.nextRefresh = nowSeconds + intervalSeconds;
                        }
                    }
                }

                if (stateForMeta) {
                    applyStatusMeta(container, stateForMeta, statusMetaPayload, locale, metaOverrides);
                }

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
                            setNumberElementText(numberElement, placeholder);
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

                payloadData = data.data || {};
                updatePresenceBreakdown(container, payloadData, formatter);
                updateApproximateMembers(container, payloadData, formatter);
                updatePremiumSubscriptions(container, payloadData, formatter);
                refreshRegionAccessibility(container);

                if (container && container.dataset && container.dataset.showSparkline === 'true') {
                    updateSparklineForContainer(container, config, false);
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
            refreshRegionAccessibility(container);
        });
    }

    function isSiteEditorPreview() {
        if (typeof window === 'undefined') {
            return false;
        }

        var frameElement = null;

        try {
            frameElement = window.frameElement || null;
        } catch (error) {
            frameElement = null;
        }

        if (frameElement) {
            var frameId = frameElement.id || '';
            if (frameId && frameId.indexOf('editor-canvas') !== -1) {
                return true;
            }

            var frameClassList = frameElement.classList;
            if (frameClassList && (frameClassList.contains('edit-site-iframe') || frameClassList.contains('is-site-editor'))) {
                return true;
            }

            var frameName = frameElement.getAttribute ? frameElement.getAttribute('name') : null;
            if (frameName && frameName.indexOf('site-editor') !== -1) {
                return true;
            }
        }

        var location = window.location || {};
        var search = location.search || '';
        var pathname = location.pathname || '';

        if (typeof pathname === 'string' && pathname.indexOf('site-editor.php') !== -1) {
            return true;
        }

        if (typeof search === 'string' && search.indexOf('canvas=edit-site%2F') !== -1) {
            return true;
        }

        return false;
    }

    function initializeDiscordBot() {
        var config = window.discordBotJlg || {};
        globalConfig = config;
        var missingFeatures = [];

        applyInitialOverlayClasses();

        if (isSiteEditorPreview()) {
            config.autoRefreshDisabled = true;
            return;
        }

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

        setupSparklinePanels(config);

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

        var staticContainers = document.querySelectorAll('.discord-stats-container:not([data-refresh])');
        Array.prototype.forEach.call(staticContainers, function (container) {
            var state = getContainerState(container);

            if (!state) {
                state = {
                    intervalMs: minIntervalMs,
                    minIntervalMs: minIntervalMs,
                    timeoutId: null,
                    inFlight: false,
                    isActive: true,
                    pendingDelay: null,
                    pendingImmediate: false,
                    lastScheduledDelay: null
                };

                storeContainerState(container, state);
            }

            ensureStatusState(state);
            initializeStatusPanel(container, state, locale);
            pauseStatusCountdown(container, state);
            initializePresenceExplorer(container, config, formatter);
            initializeComparisonExport(container);

            state.forceRefresh = function () {
                activateContainer(container);
                triggerRefresh(container, state, state.minIntervalMs, { forceRefresh: true });
            };
        });

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

            state.lastScheduledDelay = effectiveDelay;

            var statusState = ensureStatusState(state);
            if (statusState) {
                var nextTimestampSeconds = Math.floor((Date.now() + effectiveDelay) / 1000);
                var durationSeconds = Math.max(1, Math.round(effectiveDelay / 1000));
                setStatusNextRefresh(container, state, nextTimestampSeconds, durationSeconds);
            }

            if (!state.isActive) {
                state.pendingDelay = effectiveDelay;
                return;
            }

            state.pendingDelay = null;

            state.timeoutId = window.setTimeout(function () {
                state.timeoutId = null;
                if (state.inFlight) {
                    scheduleNextRefresh(container, state, state.minIntervalMs);
                    return;
                }

                if (!state.isActive) {
                    state.pendingDelay = effectiveDelay;
                    return;
                }

                triggerRefresh(container, state);
            }, effectiveDelay);
        }

        function triggerRefresh(container, state, overrideDelay, requestOptions) {
            if (!state || state.inFlight || !state.isActive) {
                return;
            }

            state.inFlight = true;

            showRefreshIndicator(container);

            function resetInFlight() {
                state.inFlight = false;
            }

            updateStats(container, config, formatter, locale, requestOptions).then(function (result) {
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

        var supportsIntersectionObserver = typeof window !== 'undefined'
            && typeof window.IntersectionObserver === 'function';
        var intersectionObserver = null;

        function activateContainer(container) {
            var state = getContainerState(container);

            if (!state || state.isActive) {
                return;
            }

            state.isActive = true;
            resumeStatusCountdown(container, state);

            if (state.pendingImmediate) {
                state.pendingImmediate = false;
                triggerRefresh(container, state, state.intervalMs);
                return;
            }

            var delay = state.pendingDelay;
            if (typeof delay !== 'number' || isNaN(delay) || delay < 0) {
                delay = state.intervalMs;
            }

            state.pendingDelay = null;
            scheduleNextRefresh(container, state, delay);
        }

        function deactivateContainer(container) {
            var state = getContainerState(container);

            if (!state || !state.isActive) {
                return;
            }

            state.isActive = false;
            pauseStatusCountdown(container, state);

            if (state.timeoutId) {
                clearTimeout(state.timeoutId);
                state.timeoutId = null;
            }

            if (typeof state.lastScheduledDelay === 'number' && !isNaN(state.lastScheduledDelay)) {
                state.pendingDelay = state.lastScheduledDelay;
            } else {
                state.pendingDelay = state.intervalMs;
            }
        }

        if (supportsIntersectionObserver) {
            try {
                intersectionObserver = new window.IntersectionObserver(
                    function (entries) {
                        if (!entries || !entries.length) {
                            return;
                        }

                        entries.forEach(function (entry) {
                            if (!entry || !entry.target) {
                                return;
                            }

                            if (entry.isIntersecting || entry.intersectionRatio > 0) {
                                activateContainer(entry.target);
                            } else {
                                deactivateContainer(entry.target);
                            }
                        });
                    },
                    {
                        rootMargin: '200px 0px',
                        threshold: [0, 0.1]
                    }
                );
            } catch (error) {
                supportsIntersectionObserver = false;
                intersectionObserver = null;
            }
        }

        Array.prototype.forEach.call(containers, function (container) {
            initializePresenceExplorer(container, config, formatter);
            initializeComparisonExport(container);

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

            var shouldForceImmediateRefresh = (container.dataset && container.dataset.stale === 'true')
                || (container.dataset && container.dataset.fallbackDemo === 'true');

            var state = {
                intervalMs: intervalMs,
                minIntervalMs: minIntervalMs,
                timeoutId: null,
                inFlight: false,
                isActive: !supportsIntersectionObserver || !intersectionObserver,
                pendingDelay: null,
                pendingImmediate: shouldForceImmediateRefresh,
                lastScheduledDelay: null
            };

            storeContainerState(container, state);
            ensureStatusState(state);
            initializeStatusPanel(container, state, locale);

            state.forceRefresh = function () {
                activateContainer(container);
                triggerRefresh(container, state, state.minIntervalMs, { forceRefresh: true });
            };

            if (supportsIntersectionObserver && intersectionObserver) {
                if (!shouldForceImmediateRefresh) {
                    scheduleNextRefresh(container, state, state.intervalMs);
                }

                intersectionObserver.observe(container);

                if (shouldForceImmediateRefresh) {
                    // Ensure the next activation triggers immediately once visible.
                    state.pendingImmediate = true;
                }
            } else {
                state.isActive = true;

                if (shouldForceImmediateRefresh) {
                    state.pendingImmediate = false;
                    triggerRefresh(container, state, state.intervalMs);
                } else {
                    scheduleNextRefresh(container, state, state.intervalMs);
                }
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
