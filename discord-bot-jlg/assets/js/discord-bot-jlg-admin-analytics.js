(function (window, document) {
    'use strict';

    if (!window || !document) {
        return;
    }

    var config = window.discordBotJlgAdminAnalytics || {};
    var chartInstance = null;
    var analyticsState = {
        originalSeries: [],
        filteredSeries: [],
        range: 'auto',
        customStart: null,
        customEnd: null,
        showAnnotations: true
    };
    var controls = {
        rangeSelect: null,
        startInput: null,
        endInput: null,
        exportCsvButton: null,
        exportPngButton: null,
        annotationToggle: null
    };
    var annotationMeta = {};

    function ready(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn);
        } else {
            fn();
        }
    }

    function buildRequestUrl() {
        if (!config.restUrl) {
            return '';
        }

        try {
            var origin = window.location && window.location.origin ? window.location.origin : undefined;
            var url = new URL(config.restUrl, origin);

            if (config.profileKey) {
                url.searchParams.set('profile_key', config.profileKey);
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

        return fetch(endpoint, options).then(function (response) {
            if (!response.ok) {
                var error = new Error('HTTP ' + response.status);
                error.status = response.status;
                throw error;
            }

            return response.json();
        }).then(function (payload) {
            if (!payload || payload.success === false) {
                throw new Error('Analytics payload missing');
            }

            return payload.data || {};
        });
    }

    function formatNumber(value) {
        if (typeof value !== 'number' || !isFinite(value)) {
            return '—';
        }

        try {
            return new Intl.NumberFormat(window.navigator.language || 'fr-FR', {
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
            return new Date(timestamp * 1000).toLocaleString(window.navigator.language || 'fr-FR');
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

    function updateExportButtonsState() {
        var hasData = Array.isArray(analyticsState.filteredSeries) && analyticsState.filteredSeries.length > 0;

        if (controls.exportCsvButton) {
            controls.exportCsvButton.disabled = !hasData;
        }

        if (controls.exportPngButton) {
            controls.exportPngButton.disabled = !hasData || !chartInstance || typeof chartInstance.toBase64Image !== 'function';
        }
    }

    function sortTimeseries(timeseries) {
        if (!Array.isArray(timeseries)) {
            return [];
        }

        return timeseries.slice().sort(function (a, b) {
            var aTimestamp = typeof a.timestamp === 'number' ? a.timestamp : 0;
            var bTimestamp = typeof b.timestamp === 'number' ? b.timestamp : 0;
            return aTimestamp - bTimestamp;
        });
    }

    function updateRangeInputs() {
        if (!Array.isArray(analyticsState.originalSeries) || !analyticsState.originalSeries.length) {
            return;
        }

        var first = analyticsState.originalSeries[0];
        var last = analyticsState.originalSeries[analyticsState.originalSeries.length - 1];

        if (controls.startInput && !controls.startInput.value && first && typeof first.timestamp === 'number') {
            controls.startInput.value = formatDateInput(first.timestamp);
        }

        if (controls.endInput && !controls.endInput.value && last && typeof last.timestamp === 'number') {
            controls.endInput.value = formatDateInput(last.timestamp);
        }
    }

    function updateFilteredSeries() {
        var series = Array.isArray(analyticsState.originalSeries)
            ? analyticsState.originalSeries.slice()
            : [];

        if (!series.length) {
            analyticsState.filteredSeries = [];
            return [];
        }

        var startTimestamp = null;
        var endTimestamp = null;

        if (analyticsState.range === 'custom') {
            startTimestamp = analyticsState.customStart;
            endTimestamp = analyticsState.customEnd;
        } else if (analyticsState.range !== 'auto') {
            var days = parseInt(analyticsState.range, 10);
            if (!isNaN(days) && days > 0) {
                endTimestamp = series[series.length - 1].timestamp;
                startTimestamp = endTimestamp - (days * 86400);
            }
        }

        if (typeof startTimestamp === 'number') {
            series = series.filter(function (point) {
                return typeof point.timestamp === 'number' && point.timestamp >= startTimestamp;
            });
        }

        if (typeof endTimestamp === 'number') {
            series = series.filter(function (point) {
                return typeof point.timestamp === 'number' && point.timestamp <= endTimestamp;
            });
        }

        analyticsState.filteredSeries = series;

        return series;
    }

    function computeAnnotationNotes(series) {
        if (!Array.isArray(series) || !series.length) {
            return [];
        }

        var notes = [];
        var metrics = [
            {
                key: 'online',
                label: (config.labels && config.labels.annotationOnline) ? config.labels.annotationOnline : 'Pic en ligne'
            },
            {
                key: 'presence',
                label: (config.labels && config.labels.annotationPresence) ? config.labels.annotationPresence : 'Pic présence'
            },
            {
                key: 'premium',
                label: (config.labels && config.labels.annotationPremium) ? config.labels.annotationPremium : 'Pic boosts'
            }
        ];

        metrics.forEach(function (metric) {
            var bestValue = -Infinity;
            var bestIndex = -1;

            series.forEach(function (point, index) {
                var value = point && typeof point[metric.key] === 'number' ? point[metric.key] : null;
                if (value === null) {
                    return;
                }

                if (value > bestValue) {
                    bestValue = value;
                    bestIndex = index;
                }
            });

            if (bestIndex >= 0 && isFinite(bestValue)) {
                var bestPoint = series[bestIndex];
                notes.push({
                    index: bestIndex,
                    value: bestValue,
                    label: metric.label + ' • ' + formatNumber(bestValue) + ' • ' + formatTimestamp(bestPoint.timestamp)
                });
            }
        });

        return notes;
    }

    function buildAnnotationDataset(labels, series) {
        var notes = computeAnnotationNotes(series);
        if (!notes.length) {
            return null;
        }

        var data = labels.map(function () {
            return null;
        });
        var notesMap = {};

        notes.forEach(function (note) {
            if (typeof note.index === 'number' && note.index >= 0 && note.index < data.length) {
                data[note.index] = note.value;
                notesMap[note.index] = note.label;
            }
        });

        return {
            dataset: {
                label: (config.labels && config.labels.annotations) ? config.labels.annotations : 'Annotations',
                data: data,
                type: 'line',
                showLine: false,
                pointRadius: 5,
                pointHoverRadius: 7,
                pointBackgroundColor: '#f97316',
                pointBorderColor: '#c2410c',
                borderWidth: 0,
                yAxisID: 'y',
                order: 5
            },
            notes: notesMap
        };
    }

    function setNotice(message, isError) {
        var panel = document.getElementById(config.containerId || 'discord-analytics-panel');
        if (!panel) {
            return;
        }

        var notice = panel.querySelector('[data-role="analytics-notice"]');
        if (!notice) {
            notice = document.createElement('p');
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

    function updateSummary(data) {
        var averages = data.averages || {};
        var peak = data.peak_presence || {};
        var trend = data.boost_trend || {};

        var averageOnlineEl = document.querySelector('[data-role="analytics-average-online"]');
        var averagePresenceEl = document.querySelector('[data-role="analytics-average-presence"]');
        var averageTotalEl = document.querySelector('[data-role="analytics-average-total"]');
        var peakPresenceEl = document.querySelector('[data-role="analytics-peak-presence"]');
        var boostTrendEl = document.querySelector('[data-role="analytics-boost-trend"]');

        if (averageOnlineEl) {
            averageOnlineEl.textContent = formatNumber(averages.online);
        }

        if (averagePresenceEl) {
            averagePresenceEl.textContent = formatNumber(averages.presence);
        }

        if (averageTotalEl) {
            averageTotalEl.textContent = formatNumber(averages.total);
        }

        if (peakPresenceEl) {
            var peakLabel = formatNumber(peak.count);
            var peakTime = formatTimestamp(peak.timestamp);
            peakPresenceEl.textContent = peakTime ? peakLabel + ' (' + peakTime + ')' : peakLabel;
        }

        if (boostTrendEl) {
            var latest = formatNumber(trend.latest);
            var deltaLabel = formatDelta(trend.delta);
            boostTrendEl.textContent = latest === '—' ? '—' : latest + ' ' + deltaLabel;
        }
    }

    function renderChartFromSeries(series) {
        analyticsState.filteredSeries = Array.isArray(series) ? series : [];

        var canvas = document.getElementById(config.canvasId || 'discord-analytics-chart');
        if (!canvas || typeof window.Chart === 'undefined') {
            updateExportButtonsState();
            return;
        }

        if (!analyticsState.filteredSeries.length) {
            if (chartInstance) {
                chartInstance.destroy();
                chartInstance = null;
            }
            annotationMeta = {};
            updateExportButtonsState();
            return;
        }

        var labels = analyticsState.filteredSeries.map(function (point) {
            return formatTimestamp(point.timestamp);
        });

        var onlineDataset = analyticsState.filteredSeries.map(function (point) {
            return typeof point.online === 'number' ? point.online : null;
        });
        var presenceDataset = analyticsState.filteredSeries.map(function (point) {
            return typeof point.presence === 'number' ? point.presence : null;
        });
        var premiumDataset = analyticsState.filteredSeries.map(function (point) {
            return typeof point.premium === 'number' ? point.premium : null;
        });

        var context = canvas.getContext('2d');
        if (!context) {
            updateExportButtonsState();
            return;
        }

        if (chartInstance) {
            chartInstance.destroy();
            chartInstance = null;
        }

        var datasets = [
            {
                label: config.labels && config.labels.averageOnline ? config.labels.averageOnline : 'En ligne',
                data: onlineDataset,
                borderColor: '#22c55e',
                backgroundColor: 'rgba(34, 197, 94, 0.16)',
                fill: false,
                tension: 0.3
            },
            {
                label: config.labels && config.labels.averagePresence ? config.labels.averagePresence : 'Présence',
                data: presenceDataset,
                borderColor: '#3b82f6',
                backgroundColor: 'rgba(59, 130, 246, 0.16)',
                fill: false,
                tension: 0.3
            },
            {
                label: config.labels && config.labels.boostTrend ? config.labels.boostTrend : 'Boosts',
                data: premiumDataset,
                borderColor: '#a855f7',
                backgroundColor: 'rgba(168, 85, 247, 0.16)',
                fill: false,
                tension: 0.3,
                yAxisID: 'yBoosts'
            }
        ];

        annotationMeta = {};
        if (analyticsState.showAnnotations) {
            var annotationInfo = buildAnnotationDataset(labels, analyticsState.filteredSeries);
            if (annotationInfo && annotationInfo.dataset) {
                datasets.push(annotationInfo.dataset);
                annotationMeta = annotationInfo.notes || {};
            }
        }

        chartInstance = new window.Chart(context, {
            type: 'line',
            data: {
                labels: labels,
                datasets: datasets
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
                            afterLabel: function (contextTooltip) {
                                var note = annotationMeta[contextTooltip.dataIndex];
                                return note ? note : undefined;
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
                    },
                    yBoosts: {
                        beginAtZero: true,
                        position: 'right',
                        grid: {
                            drawOnChartArea: false
                        }
                    }
                }
            }
        });

        updateExportButtonsState();
    }

    function refreshChart() {
        var series = updateFilteredSeries();
        renderChartFromSeries(series);
    }

    function handleRangeChange(value) {
        analyticsState.range = value;
        var isCustom = value === 'custom';

        if (controls.startInput) {
            controls.startInput.disabled = !isCustom;
        }

        if (controls.endInput) {
            controls.endInput.disabled = !isCustom;
        }

        if (!isCustom) {
            analyticsState.customStart = null;
            analyticsState.customEnd = null;
        } else {
            if (controls.startInput && controls.startInput.value) {
                analyticsState.customStart = parseDateInput(controls.startInput.value, false);
            }

            if (controls.endInput && controls.endInput.value) {
                analyticsState.customEnd = parseDateInput(controls.endInput.value, true);
            }
        }

        refreshChart();
    }

    function exportCsv() {
        var series = analyticsState.filteredSeries && analyticsState.filteredSeries.length
            ? analyticsState.filteredSeries
            : analyticsState.originalSeries;

        if (!Array.isArray(series) || !series.length) {
            return;
        }

        var rows = [
            ['timestamp', 'datetime', 'online', 'presence', 'premium']
        ];

        series.forEach(function (point) {
            if (!point || typeof point.timestamp !== 'number') {
                return;
            }

            var isoDate;
            try {
                isoDate = new Date(point.timestamp * 1000).toISOString();
            } catch (error) {
                isoDate = '';
            }

            rows.push([
                point.timestamp,
                isoDate,
                (typeof point.online === 'number' && isFinite(point.online)) ? point.online : '',
                (typeof point.presence === 'number' && isFinite(point.presence)) ? point.presence : '',
                (typeof point.premium === 'number' && isFinite(point.premium)) ? point.premium : ''
            ]);
        });

        var csvContent = rows.map(function (row) {
            return row.map(function (value) {
                var text = (value === null || typeof value === 'undefined') ? '' : String(value);
                if (text.indexOf(',') !== -1 || text.indexOf('"') !== -1 || text.indexOf('\n') !== -1) {
                    text = '"' + text.replace(/"/g, '""') + '"';
                }
                return text;
            }).join(',');
        }).join('\r\n');

        var blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        var url = URL.createObjectURL(blob);
        var link = document.createElement('a');
        var filename = 'discord-analytics-' + new Date().toISOString().slice(0, 10) + '.csv';

        link.setAttribute('href', url);
        link.setAttribute('download', filename);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
    }

    function exportPng() {
        if (!chartInstance || typeof chartInstance.toBase64Image !== 'function') {
            return;
        }

        var url = chartInstance.toBase64Image('image/png', 1);
        if (!url) {
            return;
        }

        var link = document.createElement('a');
        link.setAttribute('href', url);
        link.setAttribute('download', 'discord-analytics-' + new Date().toISOString().slice(0, 10) + '.png');
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    function buildControls(panel) {
        if (!panel) {
            return;
        }

        var container = panel.querySelector('[data-role="analytics-controls"]');
        if (!container) {
            container = document.createElement('div');
            container.setAttribute('data-role', 'analytics-controls');
            container.className = 'discord-analytics-controls';
            if (panel.firstChild) {
                panel.insertBefore(container, panel.firstChild);
            } else {
                panel.appendChild(container);
            }
        }

        if (container.getAttribute('data-initialized') === '1') {
            return;
        }

        container.setAttribute('data-initialized', '1');

        var controlsWrapper = document.createElement('div');
        controlsWrapper.className = 'discord-analytics-controls__inner';
        container.appendChild(controlsWrapper);

        var rangeLabel = document.createElement('label');
        rangeLabel.className = 'discord-analytics-controls__range';
        rangeLabel.textContent = (config.labels && config.labels.rangeLabel) ? config.labels.rangeLabel : 'Plage :';

        var rangeSelect = document.createElement('select');
        rangeSelect.setAttribute('aria-label', rangeLabel.textContent);

        var rangeOptions = [
            { value: 'auto', label: (config.labels && config.labels.rangeAll) ? config.labels.rangeAll : 'Toute la période' },
            { value: '7', label: '7 jours' },
            { value: '14', label: '14 jours' },
            { value: '30', label: '30 jours' },
            { value: 'custom', label: (config.labels && config.labels.rangeCustom) ? config.labels.rangeCustom : 'Personnalisé' }
        ];

        rangeOptions.forEach(function (optionDef) {
            var option = document.createElement('option');
            option.value = optionDef.value;
            option.textContent = optionDef.label;
            if (optionDef.value === analyticsState.range) {
                option.selected = true;
            }
            rangeSelect.appendChild(option);
        });

        rangeSelect.addEventListener('change', function (event) {
            handleRangeChange(event.target.value);
        });

        rangeLabel.appendChild(rangeSelect);
        controlsWrapper.appendChild(rangeLabel);
        controls.rangeSelect = rangeSelect;

        var customRangeWrapper = document.createElement('div');
        customRangeWrapper.className = 'discord-analytics-controls__custom-range';

        var startLabel = document.createElement('label');
        startLabel.textContent = (config.labels && config.labels.rangeStart) ? config.labels.rangeStart : 'Début';
        var startInput = document.createElement('input');
        startInput.type = 'date';
        startInput.disabled = analyticsState.range !== 'custom';
        startInput.addEventListener('change', function () {
            analyticsState.customStart = parseDateInput(startInput.value, false);
            refreshChart();
        });
        startLabel.appendChild(startInput);
        customRangeWrapper.appendChild(startLabel);

        var endLabel = document.createElement('label');
        endLabel.textContent = (config.labels && config.labels.rangeEnd) ? config.labels.rangeEnd : 'Fin';
        var endInput = document.createElement('input');
        endInput.type = 'date';
        endInput.disabled = analyticsState.range !== 'custom';
        endInput.addEventListener('change', function () {
            analyticsState.customEnd = parseDateInput(endInput.value, true);
            refreshChart();
        });
        endLabel.appendChild(endInput);
        customRangeWrapper.appendChild(endLabel);

        controlsWrapper.appendChild(customRangeWrapper);
        controls.startInput = startInput;
        controls.endInput = endInput;

        var exportWrapper = document.createElement('div');
        exportWrapper.className = 'discord-analytics-controls__exports';

        var exportCsvButton = document.createElement('button');
        exportCsvButton.type = 'button';
        exportCsvButton.className = 'button button-secondary';
        exportCsvButton.textContent = (config.labels && config.labels.exportCsv) ? config.labels.exportCsv : 'Exporter CSV';
        exportCsvButton.addEventListener('click', exportCsv);
        exportWrapper.appendChild(exportCsvButton);

        var exportPngButton = document.createElement('button');
        exportPngButton.type = 'button';
        exportPngButton.className = 'button button-secondary';
        exportPngButton.textContent = (config.labels && config.labels.exportPng) ? config.labels.exportPng : 'Exporter PNG';
        exportPngButton.addEventListener('click', exportPng);
        exportWrapper.appendChild(exportPngButton);

        controlsWrapper.appendChild(exportWrapper);
        controls.exportCsvButton = exportCsvButton;
        controls.exportPngButton = exportPngButton;

        var annotationsWrapper = document.createElement('div');
        annotationsWrapper.className = 'discord-analytics-controls__annotations';
        var annotationsLabel = document.createElement('label');
        var annotationsToggle = document.createElement('input');
        annotationsToggle.type = 'checkbox';
        annotationsToggle.checked = analyticsState.showAnnotations;
        annotationsToggle.addEventListener('change', function (event) {
            analyticsState.showAnnotations = !!event.target.checked;
            refreshChart();
        });
        annotationsLabel.appendChild(annotationsToggle);
        annotationsLabel.appendChild(document.createTextNode(' ' + ((config.labels && config.labels.annotationsToggle)
            ? config.labels.annotationsToggle
            : 'Annotations')));
        annotationsWrapper.appendChild(annotationsLabel);
        controlsWrapper.appendChild(annotationsWrapper);
        controls.annotationToggle = annotationsToggle;

        updateExportButtonsState();
    }

    function initializePanel() {
        var panel = document.getElementById(config.containerId || 'discord-analytics-panel');
        if (!panel) {
            return;
        }

        buildControls(panel);
        setNotice('', false);
        updateExportButtonsState();

        fetchAnalyticsData().then(function (data) {
            var timeseries = data && Array.isArray(data.timeseries) ? sortTimeseries(data.timeseries) : [];
            analyticsState.originalSeries = timeseries;

            updateRangeInputs();

            if (analyticsState.range === 'custom') {
                if (controls.startInput && controls.startInput.value) {
                    analyticsState.customStart = parseDateInput(controls.startInput.value, false);
                }

                if (controls.endInput && controls.endInput.value) {
                    analyticsState.customEnd = parseDateInput(controls.endInput.value, true);
                }
            }

            var hasData = timeseries.length > 0;
            if (!hasData) {
                setNotice(config.labels && config.labels.noData ? config.labels.noData : 'Aucune donnée disponible.', false);
            } else {
                setNotice('', false);
            }

            updateSummary(data || {});
            refreshChart();
        }).catch(function (error) {
            console.error('Discord Bot JLG analytics error:', error);
            setNotice((config.labels && config.labels.noData) ? config.labels.noData : 'Aucune donnée disponible.', true);
        }).finally(function () {
            updateExportButtonsState();
        });
    }

    ready(initializePanel);
})(window, document);
