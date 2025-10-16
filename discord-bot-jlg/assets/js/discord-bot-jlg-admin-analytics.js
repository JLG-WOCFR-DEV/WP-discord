(function (global) {
    'use strict';

    var COLOR_PALETTE = [
        { border: '#1d4ed8', background: 'rgba(29, 78, 216, 0.16)' },
        { border: '#16a34a', background: 'rgba(22, 163, 74, 0.16)' },
        { border: '#dc2626', background: 'rgba(220, 38, 38, 0.16)' },
        { border: '#9333ea', background: 'rgba(147, 51, 234, 0.16)' },
        { border: '#f97316', background: 'rgba(249, 115, 22, 0.16)' },
        { border: '#0ea5e9', background: 'rgba(14, 165, 233, 0.16)' },
        { border: '#64748b', background: 'rgba(100, 116, 139, 0.16)' },
        { border: '#ef4444', background: 'rgba(239, 68, 68, 0.18)' },
        { border: '#14b8a6', background: 'rgba(20, 184, 166, 0.16)' },
        { border: '#f59e0b', background: 'rgba(245, 158, 11, 0.18)' }
    ];

    function cloneArray(input) {
        return Array.isArray(input) ? input.slice() : [];
    }

    function pickColor(index) {
        var palette = COLOR_PALETTE[index % COLOR_PALETTE.length];
        return {
            border: palette.border,
            background: palette.background
        };
    }

    function ensureArray(value) {
        if (Array.isArray(value)) {
            return value;
        }

        if (value === null || typeof value === 'undefined') {
            return [];
        }

        return [value];
    }

    function normalizeAnnotations(entries) {
        if (!Array.isArray(entries)) {
            return [];
        }

        return entries.reduce(function (acc, entry) {
            if (!entry || typeof entry !== 'object') {
                return acc;
            }

            var timestamp = parseInt(entry.timestamp, 10);
            if (!timestamp || timestamp <= 0) {
                return acc;
            }

            var label = '';
            if (typeof entry.label === 'string' && entry.label.trim()) {
                label = entry.label.trim();
            } else if (typeof entry.title === 'string' && entry.title.trim()) {
                label = entry.title.trim();
            }

            if (!label) {
                return acc;
            }

            var profiles = [];
            if (Array.isArray(entry.profiles)) {
                profiles = entry.profiles.filter(function (value) {
                    return typeof value === 'string' && value.trim();
                });
            } else if (Array.isArray(entry.profile_keys)) {
                profiles = entry.profile_keys.filter(function (value) {
                    return typeof value === 'string' && value.trim();
                });
            } else if (typeof entry.profile_key === 'string' && entry.profile_key.trim()) {
                profiles = [entry.profile_key.trim()];
            } else if (entry.profile_key === '*') {
                profiles = ['*'];
            }

            var metric = typeof entry.metric === 'string' && entry.metric.trim()
                ? entry.metric.trim()
                : '';

            acc.push({
                timestamp: timestamp,
                label: label,
                profiles: profiles.length ? profiles : ['*'],
                metric: metric ? metric : '*'
            });

            return acc;
        }, []);
    }

    function prepareConfig(raw) {
        var config = raw && typeof raw === 'object' ? raw : {};

        config.restUrl = typeof config.restUrl === 'string' ? config.restUrl : '';
        config.nonce = typeof config.nonce === 'string' ? config.nonce : '';
        config.canvasId = typeof config.canvasId === 'string' ? config.canvasId : 'discord-analytics-chart';
        config.containerId = typeof config.containerId === 'string' ? config.containerId : 'discord-analytics-panel';
        config.days = typeof config.days === 'number' && config.days > 0 ? config.days : 7;

        config.labels = config.labels && typeof config.labels === 'object' ? config.labels : {};

        config.profiles = Array.isArray(config.profiles) ? config.profiles : [];
        config.profiles = config.profiles.map(function (profile) {
            var key = profile && typeof profile.key === 'string' ? profile.key : '';
            if (!key) {
                return null;
            }

            return {
                key: key,
                label: typeof profile.label === 'string' && profile.label ? profile.label : key,
                server_id: typeof profile.server_id === 'string' ? profile.server_id : ''
            };
        }).filter(Boolean);

        var profileKeys = config.profiles.map(function (profile) { return profile.key; });
        if (!profileKeys.length) {
            profileKeys.push('default');
            config.profiles = [{ key: 'default', label: 'default' }];
        }

        config.defaultMetric = typeof config.defaultMetric === 'string' && config.defaultMetric ? config.defaultMetric : 'presence';

        config.defaultProfiles = ensureArray(config.defaultProfiles)
            .map(function (key) { return typeof key === 'string' ? key : ''; })
            .filter(function (key) { return key && profileKeys.indexOf(key) !== -1; });

        if (!config.defaultProfiles.length) {
            config.defaultProfiles = profileKeys.slice(0, 2);
            if (!config.defaultProfiles.length) {
                config.defaultProfiles = [profileKeys[0]];
            }
        }

        config.requestedProfiles = ensureArray(config.requestedProfiles)
            .map(function (key) { return typeof key === 'string' ? key : ''; })
            .filter(function (key) { return key; });

        if (!config.requestedProfiles.length) {
            config.requestedProfiles = profileKeys;
        }

        config.comparisonPresets = Array.isArray(config.comparisonPresets) ? config.comparisonPresets : [];
        config.comparisonPresets = config.comparisonPresets.map(function (preset) {
            if (!preset || typeof preset !== 'object') {
                return null;
            }

            var keys = ensureArray(preset.profiles).map(function (key) {
                return typeof key === 'string' ? key : '';
            }).filter(function (key) {
                return key && profileKeys.indexOf(key) !== -1;
            });

            if (!keys.length) {
                return null;
            }

            return {
                id: typeof preset.id === 'string' && preset.id ? preset.id : keys.join('-'),
                label: typeof preset.label === 'string' && preset.label ? preset.label : keys.join(', '),
                profiles: keys
            };
        }).filter(Boolean);

        config.annotations = normalizeAnnotations(config.annotations);

        return config;
    }

    function formatNumber(value) {
        if (typeof value !== 'number' || !isFinite(value)) {
            return '—';
        }

        try {
            return new Intl.NumberFormat(global.navigator && global.navigator.language || 'fr-FR', {
                maximumFractionDigits: 0
            }).format(value);
        } catch (error) {
            return Math.round(value).toString();
        }
    }

    function formatDelta(delta) {
        if (typeof delta !== 'number' || !isFinite(delta) || delta === 0) {
            return delta === 0 ? '±0' : '—';
        }

        var prefix = delta > 0 ? '▲' : '▼';
        return prefix + Math.abs(delta);
    }

    function formatTimestamp(timestamp) {
        if (typeof timestamp !== 'number' || timestamp <= 0) {
            return '';
        }

        try {
            return new Date(timestamp * 1000).toLocaleString(global.navigator && global.navigator.language || 'fr-FR');
        } catch (error) {
            return '';
        }
    }

    function formatDateInput(timestamp) {
        if (typeof timestamp !== 'number' || !isFinite(timestamp)) {
            return '';
        }

        var date = new Date(timestamp * 1000);
        if (isNaN(date.getTime())) {
            return '';
        }

        var year = date.getFullYear();
        var month = String(date.getMonth() + 1).padStart(2, '0');
        var day = String(date.getDate()).padStart(2, '0');

        return year + '-' + month + '-' + day;
    }

    function parseDateInput(value, includeEndOfDay) {
        if (!value || typeof value !== 'string') {
            return null;
        }

        var date = new Date(value + 'T00:00:00');
        if (isNaN(date.getTime())) {
            return null;
        }

        var timestamp = Math.floor(date.getTime() / 1000);
        if (includeEndOfDay) {
            timestamp += 86399;
        }

        return timestamp;
    }
    function computeTimeline(seriesMap, profileOrder) {
        var timestamps = {};

        profileOrder.forEach(function (key) {
            var entry = seriesMap[key];
            if (!entry || !Array.isArray(entry.timeseries)) {
                return;
            }

            entry.timeseries.forEach(function (point) {
                if (!point || typeof point.timestamp !== 'number') {
                    return;
                }
                var timestamp = point.timestamp;
                if (timestamp > 0) {
                    timestamps[timestamp] = true;
                }
            });
        });

        return Object.keys(timestamps).map(function (key) {
            return parseInt(key, 10);
        }).sort(function (a, b) { return a - b; });
    }

    function filterTimeseriesByRange(timeseries, startTimestamp, endTimestamp) {
        if (!Array.isArray(timeseries)) {
            return [];
        }

        return timeseries.filter(function (point) {
            if (!point || typeof point.timestamp !== 'number') {
                return false;
            }

            if (typeof startTimestamp === 'number' && point.timestamp < startTimestamp) {
                return false;
            }

            if (typeof endTimestamp === 'number' && point.timestamp > endTimestamp) {
                return false;
            }

            return true;
        });
    }

    function filterSeriesForRange(seriesMap, profileOrder, rangeState) {
        var start = null;
        var end = null;

        if (rangeState.range === 'custom') {
            start = rangeState.customStart;
            end = rangeState.customEnd;
        } else if (rangeState.range !== 'auto') {
            var days = parseInt(rangeState.range, 10);
            if (!isNaN(days) && days > 0) {
                var timeline = computeTimeline(seriesMap, profileOrder);
                if (timeline.length) {
                    end = timeline[timeline.length - 1];
                    start = end - (days * 86400);
                }
            }
        }

        var filtered = {};

        profileOrder.forEach(function (key) {
            var entry = seriesMap[key];
            if (!entry) {
                return;
            }

            var timeseries = filterTimeseriesByRange(entry.timeseries, start, end);
            filtered[key] = {
                label: entry.label,
                timeseries: timeseries
            };
        });

        return filtered;
    }

    function summarizeSeries(timeseries) {
        if (!Array.isArray(timeseries) || !timeseries.length) {
            return {
                presence: null,
                online: null,
                total: null,
                peak: null,
                peakTimestamp: null,
                premiumDelta: null,
                latestPremium: null
            };
        }

        var sums = {
            presence: 0,
            presenceCount: 0,
            online: 0,
            onlineCount: 0,
            total: 0,
            totalCount: 0
        };
        var peakPresence = { value: null, timestamp: null };
        var firstPremium = null;
        var lastPremium = null;

        timeseries.forEach(function (point) {
            if (!point) {
                return;
            }

            if (typeof point.presence === 'number' && isFinite(point.presence)) {
                sums.presence += point.presence;
                sums.presenceCount += 1;
                if (peakPresence.value === null || point.presence > peakPresence.value) {
                    peakPresence.value = point.presence;
                    peakPresence.timestamp = point.timestamp;
                }
            }

            if (typeof point.online === 'number' && isFinite(point.online)) {
                sums.online += point.online;
                sums.onlineCount += 1;
            }

            if (typeof point.total === 'number' && isFinite(point.total)) {
                sums.total += point.total;
                sums.totalCount += 1;
            }

            if (typeof point.premium === 'number' && isFinite(point.premium)) {
                if (firstPremium === null) {
                    firstPremium = point.premium;
                }
                lastPremium = point.premium;
            }
        });

        return {
            presence: sums.presenceCount ? sums.presence / sums.presenceCount : null,
            online: sums.onlineCount ? sums.online / sums.onlineCount : null,
            total: sums.totalCount ? sums.total / sums.totalCount : null,
            peak: peakPresence.value,
            peakTimestamp: peakPresence.timestamp,
            premiumDelta: (lastPremium !== null && firstPremium !== null) ? (lastPremium - firstPremium) : null,
            latestPremium: lastPremium
        };
    }

    function buildDatasets(options) {
        var timeline = Array.isArray(options.timeline) ? options.timeline : [];
        var seriesMap = options.seriesMap || {};
        var activeProfiles = Array.isArray(options.activeProfiles) ? options.activeProfiles : [];
        var metric = options.metric;
        var showAnnotations = options.showAnnotations !== false;
        var labels = options.labels || {};
        var annotations = Array.isArray(options.annotations) ? options.annotations : [];

        var datasets = [];
        var annotationMeta = {};

        var metricLabels = {
            presence: labels.metricPresence || 'Présence',
            online: labels.metricOnline || 'En ligne',
            total: labels.metricTotal || 'Total',
            premium: labels.metricPremium || 'Boosts'
        };

        var timelineIndex = {};
        timeline.forEach(function (timestamp, index) {
            timelineIndex[timestamp] = index;
        });

        activeProfiles.forEach(function (profileKey, index) {
            var entry = seriesMap[profileKey];
            if (!entry) {
                return;
            }

            var color = pickColor(index);
            var points = Array.isArray(entry.timeseries) ? entry.timeseries : [];
            var pointMap = {};
            points.forEach(function (point) {
                if (point && typeof point.timestamp === 'number') {
                    pointMap[point.timestamp] = point;
                }
            });

            var data = timeline.map(function (timestamp) {
                var point = pointMap[timestamp];
                if (!point) {
                    return null;
                }

                switch (metric) {
                    case 'online':
                        return typeof point.online === 'number' ? point.online : null;
                    case 'total':
                        return typeof point.total === 'number' ? point.total : null;
                    case 'premium':
                        return typeof point.premium === 'number' ? point.premium : null;
                    case 'presence':
                    default:
                        return typeof point.presence === 'number' ? point.presence : null;
                }
            });

            var pointRadius = timeline.map(function () { return 2; });
            var hoverRadius = timeline.map(function () { return 5; });
            var metaForDataset = {};

            if (showAnnotations) {
                var bestValue = null;
                var bestIndex = null;

                data.forEach(function (value, dataIndex) {
                    if (typeof value !== 'number' || !isFinite(value)) {
                        return;
                    }

                    if (bestValue === null || value > bestValue) {
                        bestValue = value;
                        bestIndex = dataIndex;
                    }
                });

                if (bestIndex !== null && bestValue !== null) {
                    pointRadius[bestIndex] = 6;
                    hoverRadius[bestIndex] = 8;
                    metaForDataset[bestIndex] = [
                        (labels.annotations || 'Annotations') + ' • ' + formatNumber(bestValue) + ' • ' + formatTimestamp(timeline[bestIndex])
                    ];
                }

                annotations.forEach(function (annotation) {
                    if (!annotation || typeof annotation.timestamp !== 'number') {
                        return;
                    }

                    if (annotation.metric !== '*' && annotation.metric !== metric) {
                        return;
                    }

                    if (annotation.profiles && annotation.profiles.indexOf(profileKey) === -1 && annotation.profiles.indexOf('*') === -1) {
                        return;
                    }

                    var annotationIndex = timelineIndex[annotation.timestamp];
                    if (typeof annotationIndex === 'undefined') {
                        return;
                    }

                    pointRadius[annotationIndex] = Math.max(pointRadius[annotationIndex], 7);
                    hoverRadius[annotationIndex] = Math.max(hoverRadius[annotationIndex], 9);
                    metaForDataset[annotationIndex] = metaForDataset[annotationIndex] || [];
                    metaForDataset[annotationIndex].push(annotation.label + ' • ' + formatTimestamp(annotation.timestamp));
                });
            }

            annotationMeta[datasets.length] = metaForDataset;

            datasets.push({
                label: entry.label + ' — ' + (metricLabels[metric] || metric),
                data: data,
                borderColor: color.border,
                backgroundColor: color.background,
                fill: false,
                tension: 0.3,
                pointRadius: pointRadius,
                pointHoverRadius: hoverRadius,
                spanGaps: true
            });
        });

        return {
            datasets: datasets,
            annotationMeta: annotationMeta
        };
    }

    function buildExportRows(seriesMap, timeline, targetProfiles) {
        var rows = [
            ['profile_key', 'timestamp', 'datetime', 'online', 'presence', 'total', 'premium']
        ];

        var profiles = Array.isArray(targetProfiles) ? targetProfiles : [];

        timeline.forEach(function (timestamp) {
            var isoDate = '';
            try {
                isoDate = new Date(timestamp * 1000).toISOString();
            } catch (error) {
                isoDate = '';
            }

            profiles.forEach(function (profileKey) {
                var entry = seriesMap[profileKey];
                if (!entry || !Array.isArray(entry.timeseries)) {
                    return;
                }

                var point = entry.timeseries.find(function (item) {
                    return item && item.timestamp === timestamp;
                });

                if (!point) {
                    return;
                }

                rows.push([
                    profileKey,
                    timestamp,
                    isoDate,
                    point && typeof point.online === 'number' && isFinite(point.online) ? point.online : '',
                    point && typeof point.presence === 'number' && isFinite(point.presence) ? point.presence : '',
                    point && typeof point.total === 'number' && isFinite(point.total) ? point.total : '',
                    point && typeof point.premium === 'number' && isFinite(point.premium) ? point.premium : ''
                ]);
            });
        });

        return rows;
    }

    function buildCsvContent(rows) {
        return rows.map(function (row) {
            return row.map(function (value) {
                var text = (value === null || typeof value === 'undefined') ? '' : String(value);
                if (text.indexOf(',') !== -1 || text.indexOf('"') !== -1 || text.indexOf('\n') !== -1) {
                    text = '"' + text.replace(/"/g, '""') + '"';
                }
                return text;
            }).join(',');
        }).join('\r\n');
    }
    function installChromeRuntimeErrorGuard(windowObject) {
        if (!windowObject || typeof windowObject.addEventListener !== 'function') {
            return;
        }

        if (windowObject.__discordBotJlgChromeRuntimeGuardInstalled) {
            return;
        }

        windowObject.__discordBotJlgChromeRuntimeGuardInstalled = true;

        windowObject.addEventListener('unhandledrejection', function (event) {
            if (!event || !event.reason) {
                return;
            }

            var reason = event.reason;
            var message = '';

            if (typeof reason === 'string') {
                message = reason;
            } else if (reason && typeof reason.message === 'string') {
                message = reason.message;
            }

            if (!message) {
                return;
            }

            var asyncResponseFragment = 'A listener indicated an asynchronous response';
            var messageChannelFragment = 'message channel closed before a response was received';

            if (
                message.indexOf(asyncResponseFragment) !== -1 &&
                message.indexOf(messageChannelFragment) !== -1
            ) {
                event.preventDefault();

                if (typeof console !== 'undefined' && typeof console.warn === 'function') {
                    console.warn('Suppressed runtime messaging error from browser extension:', message);
                }
            }
        });
    }

    function bootstrap(windowObject, documentObject) {
        var config = prepareConfig(windowObject.discordBotJlgAdminAnalytics || {});

        var state = {
            config: config,
            chart: null,
            annotationMeta: {},
            activeProfiles: cloneArray(config.defaultProfiles),
            metric: config.defaultMetric,
            range: 'auto',
            customStart: null,
            customEnd: null,
            showAnnotations: true,
            seriesMap: {},
            filteredSeries: {},
            timeline: [],
            filteredTimeline: [],
            controls: {}
        };

        function ensureActiveProfiles() {
            state.activeProfiles = state.activeProfiles.filter(function (key) {
                return config.profiles.some(function (profile) { return profile.key === key; });
            });

            if (!state.activeProfiles.length && config.profiles.length) {
                state.activeProfiles = [config.profiles[0].key];
            }
        }

        function getPanel() {
            return documentObject.getElementById(config.containerId || 'discord-analytics-panel');
        }

        function buildRequestUrl() {
            if (!config.restUrl) {
                return '';
            }

            try {
                var origin = windowObject.location && windowObject.location.origin ? windowObject.location.origin : undefined;
                var url = new URL(config.restUrl, origin);
                if (Array.isArray(config.requestedProfiles) && config.requestedProfiles.length) {
                    url.searchParams.set('profile_keys', config.requestedProfiles.join(','));
                }
                if (config.days) {
                    url.searchParams.set('days', String(config.days));
                }

                return url.toString();
            } catch (error) {
                return config.restUrl;
            }
        }

        function fetchAnalyticsData() {
            var endpoint = buildRequestUrl();
            if (!endpoint) {
                return Promise.reject(new Error('Missing analytics endpoint'));
            }

            var options = {
                method: 'GET',
                credentials: 'same-origin'
            };

            if (config.nonce) {
                options.headers = {
                    'X-WP-Nonce': config.nonce
                };
            }

            return fetch(endpoint, options)
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
                        throw new Error('Analytics payload missing');
                    }

                    return payload.data || {};
                });
        }

        function ingestAnalyticsPayload(data) {
            var series = {};

            if (data && Array.isArray(data.series) && data.series.length) {
                data.series.forEach(function (entry) {
                    if (!entry || typeof entry !== 'object') {
                        return;
                    }

                    var key = typeof entry.profile_key === 'string' && entry.profile_key ? entry.profile_key : 'default';
                    series[key] = {
                        label: typeof entry.label === 'string' && entry.label ? entry.label : key,
                        timeseries: Array.isArray(entry.timeseries) ? entry.timeseries : [],
                        averages: entry.averages || {},
                        peak_presence: entry.peak_presence || {},
                        boost_trend: entry.boost_trend || {}
                    };
                });
            } else if (data && Array.isArray(data.timeseries)) {
                var fallbackKey = typeof data.profile_key === 'string' && data.profile_key ? data.profile_key : 'default';
                series[fallbackKey] = {
                    label: typeof data.label === 'string' && data.label ? data.label : fallbackKey,
                    timeseries: data.timeseries,
                    averages: data.averages || {},
                    peak_presence: data.peak_presence || {},
                    boost_trend: data.boost_trend || {}
                };
            }

            state.seriesMap = series;
            state.timeline = computeTimeline(state.seriesMap, config.profiles.map(function (profile) { return profile.key; }));
            state.filteredSeries = series;
            state.filteredTimeline = state.timeline.slice();
        }

        function setNotice(message, isError) {
            var panel = getPanel();
            if (!panel) {
                return;
            }

            var notice = panel.querySelector('[data-role="analytics-notice"]');
            if (!notice) {
                notice = documentObject.createElement('p');
                notice.setAttribute('data-role', 'analytics-notice');
                panel.appendChild(notice);
            }

            notice.textContent = message || '';
            if (message) {
                notice.classList.toggle('error', !!isError);
            } else {
                notice.classList.remove('error');
            }
        }

        function updateExportButtonsState() {
            var hasData = Array.isArray(state.filteredTimeline) && state.filteredTimeline.length > 0;

            if (state.controls.exportCsvButton) {
                state.controls.exportCsvButton.disabled = !hasData;
            }

            if (state.controls.exportPngButton) {
                state.controls.exportPngButton.disabled = !hasData || !state.chart || typeof state.chart.toBase64Image !== 'function';
            }
        }

        function updateExportTargets() {
            if (!state.controls.exportTarget) {
                return;
            }

            while (state.controls.exportTarget.firstChild) {
                state.controls.exportTarget.removeChild(state.controls.exportTarget.firstChild);
            }

            var optionCombined = documentObject.createElement('option');
            optionCombined.value = 'combined';
            optionCombined.textContent = config.labels.exportCombined || 'Combiné';
            state.controls.exportTarget.appendChild(optionCombined);

            state.activeProfiles.forEach(function (profileKey) {
                var option = documentObject.createElement('option');
                option.value = 'profile:' + profileKey;
                var profile = config.profiles.find(function (item) { return item.key === profileKey; }) || state.seriesMap[profileKey];
                option.textContent = profile && profile.label ? profile.label : profileKey;
                state.controls.exportTarget.appendChild(option);
            });
        }

        function updateRangeInputs() {
            if (!state.controls.startInput || !state.controls.endInput) {
                return;
            }

            if (!state.timeline.length) {
                state.controls.startInput.value = '';
                state.controls.endInput.value = '';
                return;
            }

            if (!state.controls.startInput.value) {
                state.controls.startInput.value = formatDateInput(state.timeline[0]);
            }

            if (!state.controls.endInput.value) {
                state.controls.endInput.value = formatDateInput(state.timeline[state.timeline.length - 1]);
            }
        }

        function renderSummary() {
            var panel = getPanel();
            if (!panel) {
                return;
            }

            var container = panel.querySelector('[data-role="analytics-summary"]');
            if (!container) {
                return;
            }

            while (container.firstChild) {
                container.removeChild(container.firstChild);
            }

            if (!state.activeProfiles.length) {
                return;
            }

            var title = documentObject.createElement('h3');
            title.textContent = config.labels.summaryTitle || 'Synthèse';
            container.appendChild(title);

            state.activeProfiles.forEach(function (profileKey) {
                var entry = state.filteredSeries[profileKey];
                if (!entry || !Array.isArray(entry.timeseries)) {
                    return;
                }

                var summary = summarizeSeries(entry.timeseries);

                var card = documentObject.createElement('div');
                card.className = 'discord-analytics-summary__card';

                var heading = documentObject.createElement('h4');
                var profile = config.profiles.find(function (item) { return item.key === profileKey; }) || { label: profileKey };
                heading.textContent = profile.label || profileKey;
                card.appendChild(heading);

                var list = documentObject.createElement('ul');
                list.className = 'discord-analytics-summary__list';

                var presenceItem = documentObject.createElement('li');
                presenceItem.textContent = (config.labels.summaryPresence || 'Présence moyenne') + ': ' + formatNumber(summary.presence);
                list.appendChild(presenceItem);

                var onlineItem = documentObject.createElement('li');
                onlineItem.textContent = (config.labels.summaryOnline || 'Moy. en ligne') + ': ' + formatNumber(summary.online);
                list.appendChild(onlineItem);

                var totalItem = documentObject.createElement('li');
                totalItem.textContent = (config.labels.summaryTotal || 'Moy. membres') + ': ' + formatNumber(summary.total);
                list.appendChild(totalItem);

                var peakItem = documentObject.createElement('li');
                var peakLabel = formatNumber(summary.peak);
                var peakTime = formatTimestamp(summary.peakTimestamp);
                peakItem.textContent = (config.labels.summaryPeak || 'Pic de présence') + ': ' + (peakTime ? peakLabel + ' (' + peakTime + ')' : peakLabel);
                list.appendChild(peakItem);

                var boostItem = documentObject.createElement('li');
                var deltaLabel = formatDelta(summary.premiumDelta);
                var latest = formatNumber(summary.latestPremium);
                boostItem.textContent = (config.labels.summaryBoost || 'Boosts') + ': ' + (latest === '—' ? '—' : latest + ' ' + deltaLabel);
                list.appendChild(boostItem);

                card.appendChild(list);
                container.appendChild(card);
            });
        }
        function renderChart() {
            var panel = getPanel();
            if (!panel) {
                return;
            }

            var canvas = documentObject.getElementById(config.canvasId || 'discord-analytics-chart');
            if (!canvas || typeof windowObject.Chart === 'undefined') {
                updateExportButtonsState();
                return;
            }

            if (!state.filteredTimeline.length) {
                if (state.chart) {
                    state.chart.destroy();
                    state.chart = null;
                }
                state.annotationMeta = {};
                updateExportButtonsState();
                return;
            }

            var context = canvas.getContext('2d');
            if (!context) {
                updateExportButtonsState();
                return;
            }

            if (state.chart) {
                state.chart.destroy();
                state.chart = null;
            }

            var datasetResult = buildDatasets({
                timeline: state.filteredTimeline,
                seriesMap: state.filteredSeries,
                activeProfiles: state.activeProfiles,
                metric: state.metric,
                showAnnotations: state.showAnnotations,
                labels: config.labels,
                annotations: config.annotations
            });

            state.annotationMeta = datasetResult.annotationMeta || {};

            state.chart = new windowObject.Chart(context, {
                type: 'line',
                data: {
                    labels: state.filteredTimeline.map(formatTimestamp),
                    datasets: datasetResult.datasets
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        intersect: false,
                        mode: 'index'
                    },
                    plugins: {
                        legend: {
                            position: 'bottom'
                        },
                        tooltip: {
                            callbacks: {
                                afterBody: function (items) {
                                    if (!items || !items.length) {
                                        return;
                                    }

                                    var item = items[0];
                                    var datasetMeta = state.annotationMeta[item.datasetIndex] || {};
                                    var notes = datasetMeta[item.dataIndex];
                                    if (!notes) {
                                        return;
                                    }

                                    if (Array.isArray(notes)) {
                                        return notes;
                                    }

                                    return [notes];
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            ticks: {
                                maxRotation: 45,
                                minRotation: 0
                            }
                        },
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });

            updateExportButtonsState();
        }

        function refreshDataViews() {
            ensureActiveProfiles();

            var filtered = filterSeriesForRange(state.seriesMap, state.activeProfiles, {
                range: state.range,
                customStart: state.customStart,
                customEnd: state.customEnd
            });

            state.filteredSeries = filtered;
            state.filteredTimeline = computeTimeline(filtered, state.activeProfiles);

            renderSummary();
            renderChart();
            updateExportTargets();
        }

        function exportCsv() {
            if (!state.filteredTimeline.length) {
                return;
            }

            var targetValue = state.controls.exportTarget ? state.controls.exportTarget.value : 'combined';
            var targetProfiles;

            if (targetValue && targetValue.indexOf('profile:') === 0) {
                targetProfiles = [targetValue.replace('profile:', '')];
            } else {
                targetProfiles = state.activeProfiles.slice();
            }

            if (!targetProfiles.length) {
                return;
            }

            var rows = buildExportRows(state.filteredSeries, state.filteredTimeline, targetProfiles);
            var csvContent = buildCsvContent(rows);

            var blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            var url = windowObject.URL.createObjectURL(blob);
            var link = documentObject.createElement('a');
            var filename = 'discord-analytics-' + new Date().toISOString().slice(0, 10) + '.csv';

            link.setAttribute('href', url);
            link.setAttribute('download', filename);
            documentObject.body.appendChild(link);
            link.click();
            documentObject.body.removeChild(link);
            windowObject.URL.revokeObjectURL(url);
        }

        function exportPng() {
            if (!state.chart || typeof state.chart.toBase64Image !== 'function') {
                return;
            }

            var url = state.chart.toBase64Image('image/png', 1);
            if (!url) {
                return;
            }

            var link = documentObject.createElement('a');
            link.setAttribute('href', url);
            link.setAttribute('download', 'discord-analytics-' + new Date().toISOString().slice(0, 10) + '.png');
            documentObject.body.appendChild(link);
            link.click();
            documentObject.body.removeChild(link);
        }

        function handleRangeChange(value) {
            state.range = value;
            var isCustom = value === 'custom';

            if (state.controls.startInput) {
                state.controls.startInput.disabled = !isCustom;
            }

            if (state.controls.endInput) {
                state.controls.endInput.disabled = !isCustom;
            }

            if (!isCustom) {
                state.customStart = null;
                state.customEnd = null;
            } else {
                if (state.controls.startInput && state.controls.startInput.value) {
                    state.customStart = parseDateInput(state.controls.startInput.value, false);
                }

                if (state.controls.endInput && state.controls.endInput.value) {
                    state.customEnd = parseDateInput(state.controls.endInput.value, true);
                }
            }

            refreshDataViews();
        }

        function updateProfileCheckboxes() {
            if (!state.controls.profileCheckboxes) {
                return;
            }

            state.controls.profileCheckboxes.forEach(function (item) {
                item.checkbox.checked = state.activeProfiles.indexOf(item.key) !== -1;
            });
        }
        function buildControls(panel) {
            var filtersContainer = panel.querySelector('[data-role="analytics-filters"]');
            if (!filtersContainer) {
                filtersContainer = documentObject.createElement('div');
                filtersContainer.setAttribute('data-role', 'analytics-filters');
                filtersContainer.className = 'discord-analytics-controls';
                panel.insertBefore(filtersContainer, panel.firstChild);
            }

            var container = panel.querySelector('[data-role="analytics-controls"]');
            if (!container) {
                container = documentObject.createElement('div');
                container.setAttribute('data-role', 'analytics-controls');
                container.className = 'discord-analytics-controls';
                filtersContainer.appendChild(container);
            }

            if (container.getAttribute('data-initialized') === '1') {
                return;
            }

            container.setAttribute('data-initialized', '1');

            var controlsWrapper = documentObject.createElement('div');
            controlsWrapper.className = 'discord-analytics-controls__inner';
            container.appendChild(controlsWrapper);

            var profileWrapper = documentObject.createElement('div');
            profileWrapper.className = 'discord-analytics-controls__profiles';
            var profileLabel = documentObject.createElement('strong');
            profileLabel.textContent = config.labels.profileFilterLabel || 'Profils à comparer';
            profileWrapper.appendChild(profileLabel);

            var profileList = documentObject.createElement('div');
            profileList.className = 'discord-analytics-controls__profiles-list';
            profileWrapper.appendChild(profileList);

            state.controls.profileCheckboxes = [];

            config.profiles.forEach(function (profile) {
                var checkboxId = 'analytics-profile-' + profile.key;
                var label = documentObject.createElement('label');
                label.setAttribute('for', checkboxId);

                var checkbox = documentObject.createElement('input');
                checkbox.type = 'checkbox';
                checkbox.id = checkboxId;
                checkbox.value = profile.key;
                checkbox.checked = state.activeProfiles.indexOf(profile.key) !== -1;
                checkbox.addEventListener('change', function (event) {
                    if (event.target.checked) {
                        if (state.activeProfiles.indexOf(profile.key) === -1) {
                            state.activeProfiles.push(profile.key);
                        }
                    } else {
                        state.activeProfiles = state.activeProfiles.filter(function (key) { return key !== profile.key; });
                    }
                    ensureActiveProfiles();
                    updateProfileCheckboxes();
                    refreshDataViews();
                });

                state.controls.profileCheckboxes.push({ key: profile.key, checkbox: checkbox });

                label.appendChild(checkbox);
                label.appendChild(documentObject.createTextNode(' ' + profile.label));
                profileList.appendChild(label);
            });

            var profileActions = documentObject.createElement('div');
            profileActions.className = 'discord-analytics-controls__profile-actions';

            var selectAll = documentObject.createElement('button');
            selectAll.type = 'button';
            selectAll.className = 'button button-link';
            selectAll.textContent = config.labels.profileSelectAll || 'Tout sélectionner';
            selectAll.addEventListener('click', function () {
                state.activeProfiles = config.profiles.map(function (profile) { return profile.key; });
                ensureActiveProfiles();
                updateProfileCheckboxes();
                refreshDataViews();
            });
            profileActions.appendChild(selectAll);

            var selectNone = documentObject.createElement('button');
            selectNone.type = 'button';
            selectNone.className = 'button button-link';
            selectNone.textContent = config.labels.profileSelectNone || 'Tout désélectionner';
            selectNone.addEventListener('click', function () {
                state.activeProfiles = [];
                ensureActiveProfiles();
                updateProfileCheckboxes();
                refreshDataViews();
            });
            profileActions.appendChild(selectNone);

            profileWrapper.appendChild(profileActions);
            controlsWrapper.appendChild(profileWrapper);

            if (Array.isArray(config.comparisonPresets) && config.comparisonPresets.length) {
                var presetWrapper = documentObject.createElement('div');
                presetWrapper.className = 'discord-analytics-controls__presets';
                var presetLabel = documentObject.createElement('strong');
                presetLabel.textContent = config.labels.presetLabel || 'Presets de comparaison';
                presetWrapper.appendChild(presetLabel);

                config.comparisonPresets.forEach(function (preset) {
                    var button = documentObject.createElement('button');
                    button.type = 'button';
                    button.className = 'button button-secondary';
                    button.textContent = preset.label;
                    button.addEventListener('click', function () {
                        state.activeProfiles = preset.profiles.slice();
                        ensureActiveProfiles();
                        updateProfileCheckboxes();
                        refreshDataViews();
                    });
                    presetWrapper.appendChild(button);
                });

                controlsWrapper.appendChild(presetWrapper);
            }

            var rangeLabel = documentObject.createElement('label');
            rangeLabel.className = 'discord-analytics-controls__range';
            rangeLabel.textContent = (config.labels.rangeLabel) ? config.labels.rangeLabel : 'Plage :';

            var rangeSelect = documentObject.createElement('select');
            rangeSelect.setAttribute('aria-label', rangeLabel.textContent);

            var rangeOptions = [
                { value: 'auto', label: (config.labels.rangeAll) ? config.labels.rangeAll : 'Toute la période' },
                { value: '7', label: '7 jours' },
                { value: '14', label: '14 jours' },
                { value: '30', label: '30 jours' },
                { value: 'custom', label: (config.labels.rangeCustom) ? config.labels.rangeCustom : 'Personnalisé' }
            ];

            rangeOptions.forEach(function (optionDef) {
                var option = documentObject.createElement('option');
                option.value = optionDef.value;
                option.textContent = optionDef.label;
                if (optionDef.value === state.range) {
                    option.selected = true;
                }
                rangeSelect.appendChild(option);
            });

            rangeSelect.addEventListener('change', function (event) {
                handleRangeChange(event.target.value);
            });

            rangeLabel.appendChild(rangeSelect);
            controlsWrapper.appendChild(rangeLabel);
            state.controls.rangeSelect = rangeSelect;

            var customRangeWrapper = documentObject.createElement('div');
            customRangeWrapper.className = 'discord-analytics-controls__custom-range';

            var startLabel = documentObject.createElement('label');
            startLabel.textContent = (config.labels.rangeStart) ? config.labels.rangeStart : 'Début';
            var startInput = documentObject.createElement('input');
            startInput.type = 'date';
            startInput.disabled = state.range !== 'custom';
            startInput.addEventListener('change', function () {
                state.customStart = parseDateInput(startInput.value, false);
                refreshDataViews();
            });
            startLabel.appendChild(startInput);
            customRangeWrapper.appendChild(startLabel);

            var endLabel = documentObject.createElement('label');
            endLabel.textContent = (config.labels.rangeEnd) ? config.labels.rangeEnd : 'Fin';
            var endInput = documentObject.createElement('input');
            endInput.type = 'date';
            endInput.disabled = state.range !== 'custom';
            endInput.addEventListener('change', function () {
                state.customEnd = parseDateInput(endInput.value, true);
                refreshDataViews();
            });
            endLabel.appendChild(endInput);
            customRangeWrapper.appendChild(endLabel);

            controlsWrapper.appendChild(customRangeWrapper);
            state.controls.startInput = startInput;
            state.controls.endInput = endInput;

            var metricWrapper = documentObject.createElement('label');
            metricWrapper.className = 'discord-analytics-controls__metric';
            metricWrapper.textContent = (config.labels.metricLabel) ? config.labels.metricLabel : 'Métrique';

            var metricSelect = documentObject.createElement('select');
            [
                { value: 'presence', label: config.labels.metricPresence || 'Présence' },
                { value: 'online', label: config.labels.metricOnline || 'En ligne' },
                { value: 'total', label: config.labels.metricTotal || 'Total' },
                { value: 'premium', label: config.labels.metricPremium || 'Boosts' }
            ].forEach(function (optionDef) {
                var option = documentObject.createElement('option');
                option.value = optionDef.value;
                option.textContent = optionDef.label;
                if (optionDef.value === state.metric) {
                    option.selected = true;
                }
                metricSelect.appendChild(option);
            });

            metricSelect.addEventListener('change', function (event) {
                state.metric = event.target.value;
                refreshDataViews();
            });

            metricWrapper.appendChild(metricSelect);
            controlsWrapper.appendChild(metricWrapper);
            state.controls.metricSelect = metricSelect;

            var annotationsWrapper = documentObject.createElement('div');
            annotationsWrapper.className = 'discord-analytics-controls__annotations';
            var annotationsLabel = documentObject.createElement('label');
            var annotationsToggle = documentObject.createElement('input');
            annotationsToggle.type = 'checkbox';
            annotationsToggle.checked = state.showAnnotations;
            annotationsToggle.addEventListener('change', function (event) {
                state.showAnnotations = !!event.target.checked;
                refreshDataViews();
            });
            annotationsLabel.appendChild(annotationsToggle);
            annotationsLabel.appendChild(documentObject.createTextNode(' ' + ((config.labels.annotationsToggle)
                ? config.labels.annotationsToggle
                : 'Annotations')));
            annotationsWrapper.appendChild(annotationsLabel);
            controlsWrapper.appendChild(annotationsWrapper);

            var exportWrapper = documentObject.createElement('div');
            exportWrapper.className = 'discord-analytics-controls__exports';

            var exportTargetLabel = documentObject.createElement('label');
            exportTargetLabel.textContent = config.labels.exportTargetLabel || 'Exporter';
            var exportSelect = documentObject.createElement('select');
            exportTargetLabel.appendChild(exportSelect);
            exportWrapper.appendChild(exportTargetLabel);
            state.controls.exportTarget = exportSelect;

            var exportCsvButton = documentObject.createElement('button');
            exportCsvButton.type = 'button';
            exportCsvButton.className = 'button button-secondary';
            exportCsvButton.textContent = (config.labels.exportCsv) ? config.labels.exportCsv : 'Exporter CSV';
            exportCsvButton.addEventListener('click', exportCsv);
            exportWrapper.appendChild(exportCsvButton);
            state.controls.exportCsvButton = exportCsvButton;

            var exportPngButton = documentObject.createElement('button');
            exportPngButton.type = 'button';
            exportPngButton.className = 'button button-secondary';
            exportPngButton.textContent = (config.labels.exportPng) ? config.labels.exportPng : 'Exporter PNG';
            exportPngButton.addEventListener('click', exportPng);
            exportWrapper.appendChild(exportPngButton);
            state.controls.exportPngButton = exportPngButton;

            controlsWrapper.appendChild(exportWrapper);
        }

        function initializePanel() {
            installChromeRuntimeErrorGuard(windowObject);

            var panel = getPanel();
            if (!panel) {
                return;
            }

            buildControls(panel);
            setNotice('', false);
            updateExportButtonsState();

            fetchAnalyticsData().then(function (data) {
                ingestAnalyticsPayload(data);
                ensureActiveProfiles();
                updateProfileCheckboxes();
                updateRangeInputs();
                updateExportTargets();

                if (!state.timeline.length) {
                    setNotice(config.labels.noData ? config.labels.noData : 'Aucune donnée disponible.', false);
                } else {
                    setNotice('', false);
                }

                refreshDataViews();
            }).catch(function (error) {
                if (typeof console !== 'undefined' && typeof console.error === 'function') {
                    console.error('Discord Bot JLG analytics error:', error);
                }
                setNotice((config.labels && config.labels.noData) ? config.labels.noData : 'Aucune donnée disponible.', true);
            }).finally(function () {
                updateExportButtonsState();
            });
        }

        if (documentObject.readyState === 'loading') {
            documentObject.addEventListener('DOMContentLoaded', initializePanel);
        } else {
            initializePanel();
        }
    }

    var AnalyticsModule = {
        prepareConfig: prepareConfig,
        computeTimeline: computeTimeline,
        filterSeriesForRange: filterSeriesForRange,
        buildDatasets: buildDatasets,
        buildExportRows: buildExportRows,
        buildCsvContent: buildCsvContent,
        bootstrap: bootstrap
    };

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = AnalyticsModule;
    }

    if (typeof global !== 'undefined' && global.document && !global.__DISCORD_BOT_JLG_DISABLE_AUTO_BOOTSTRAP) {
        bootstrap(global, global.document);
    }

    global.discordBotJlgAdminAnalyticsInternal = AnalyticsModule;
})(typeof window !== 'undefined' ? window : globalThis);
