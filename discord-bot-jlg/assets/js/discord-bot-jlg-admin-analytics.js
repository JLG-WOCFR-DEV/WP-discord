(function (window, document) {
    'use strict';

    if (!window || !document) {
        return;
    }

    var config = window.discordBotJlgAdminAnalytics || {};
    var chartInstance = null;

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

    function renderChart(data) {
        var canvas = document.getElementById(config.canvasId || 'discord-analytics-chart');
        if (!canvas || typeof window.Chart === 'undefined') {
            return;
        }

        var timeseries = Array.isArray(data.timeseries) ? data.timeseries : [];
        if (!timeseries.length) {
            if (chartInstance) {
                chartInstance.destroy();
                chartInstance = null;
            }
            return;
        }

        var labels = timeseries.map(function (point) {
            return formatTimestamp(point.timestamp);
        });

        var onlineDataset = timeseries.map(function (point) {
            return typeof point.online === 'number' ? point.online : null;
        });
        var presenceDataset = timeseries.map(function (point) {
            return typeof point.presence === 'number' ? point.presence : null;
        });
        var premiumDataset = timeseries.map(function (point) {
            return typeof point.premium === 'number' ? point.premium : null;
        });

        var context = canvas.getContext('2d');
        if (!context) {
            return;
        }

        if (chartInstance) {
            chartInstance.destroy();
            chartInstance = null;
        }

        chartInstance = new window.Chart(context, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: config.labels && config.labels.averageOnline ? config.labels.averageOnline : 'En ligne',
                        data: onlineDataset,
                        borderColor: '#22c55e',
                        backgroundColor: 'rgba(34, 197, 94, 0.16)',
                        fill: false,
                        tension: 0.3,
                    },
                    {
                        label: config.labels && config.labels.averagePresence ? config.labels.averagePresence : 'Présence',
                        data: presenceDataset,
                        borderColor: '#3b82f6',
                        backgroundColor: 'rgba(59, 130, 246, 0.16)',
                        fill: false,
                        tension: 0.3,
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
                ]
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
    }

    function initializePanel() {
        var panel = document.getElementById(config.containerId || 'discord-analytics-panel');
        if (!panel) {
            return;
        }

        setNotice('', false);

        fetchAnalyticsData().then(function (data) {
            var hasData = data && Array.isArray(data.timeseries) && data.timeseries.length;
            if (!hasData) {
                setNotice(config.labels && config.labels.noData ? config.labels.noData : 'Aucune donnée disponible.', false);
            } else {
                setNotice('', false);
            }

            updateSummary(data || {});
            renderChart(data || {});
        }).catch(function (error) {
            console.error('Discord Bot JLG analytics error:', error);
            setNotice((config.labels && config.labels.noData) ? config.labels.noData : 'Aucune donnée disponible.', true);
        });
    }

    ready(initializePanel);
})(window, document);
