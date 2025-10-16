beforeAll(() => {
    global.__DISCORD_BOT_JLG_DISABLE_AUTO_BOOTSTRAP = true;
});

const analytics = require('../../discord-bot-jlg/assets/js/discord-bot-jlg-admin-analytics.js');

describe('discord-bot-jlg-admin-analytics helpers', () => {
    test('prepareConfig fills defaults and keeps snapshots stable', () => {
        const config = analytics.prepareConfig({
            profiles: [
                { key: 'alpha', label: 'Alpha' },
                { key: 'beta' }
            ],
            defaultProfiles: ['alpha'],
            requestedProfiles: ['alpha', 'beta'],
            comparisonPresets: [
                { id: 'solo-alpha', label: 'Alpha only', profiles: ['alpha'] }
            ],
            annotations: [
                { timestamp: 1700000000, label: 'Launch', profile_key: 'alpha', metric: 'presence' }
            ]
        });

        expect(config).toMatchSnapshot();
    });

    test('buildDatasets generates aligned datasets with annotations', () => {
        const timeline = [100, 200, 300];
        const seriesMap = {
            alpha: {
                label: 'Alpha',
                timeseries: [
                    { timestamp: 100, presence: 10, online: 5, total: 50, premium: 1 },
                    { timestamp: 200, presence: 15, online: 6, total: 55, premium: 1 },
                    { timestamp: 300, presence: 12, online: 7, total: 60, premium: 2 }
                ]
            },
            beta: {
                label: 'Beta',
                timeseries: [
                    { timestamp: 100, presence: 20, online: 8, total: 70, premium: 0 },
                    { timestamp: 300, presence: 24, online: 9, total: 73, premium: 1 }
                ]
            }
        };

        const annotations = [
            { timestamp: 200, label: 'Milestone', profiles: ['alpha'], metric: 'presence' },
            { timestamp: 300, label: 'Campaign', profiles: ['*'], metric: '*' }
        ];

        const result = analytics.buildDatasets({
            timeline,
            seriesMap,
            activeProfiles: ['alpha', 'beta'],
            metric: 'presence',
            showAnnotations: true,
            labels: {
                metricPresence: 'Présence',
                annotations: 'Annotations'
            },
            annotations
        });

        expect(result.datasets.length).toBe(2);
        expect(result).toMatchSnapshot();
    });

    test('buildExportRows flattens multi-profile data', () => {
        const seriesMap = {
            alpha: {
                timeseries: [
                    { timestamp: 100, online: 5, presence: 10, total: 50, premium: 1 },
                    { timestamp: 200, online: 6, presence: 12, total: 52, premium: 1 }
                ]
            },
            beta: {
                timeseries: [
                    { timestamp: 100, online: 8, presence: 20, total: 70, premium: 0 }
                ]
            }
        };

        const rows = analytics.buildExportRows(seriesMap, [100, 200], ['alpha', 'beta']);
        expect(rows).toEqual([
            ['profile_key', 'timestamp', 'datetime', 'online', 'presence', 'total', 'premium'],
            ['alpha', 100, new Date(100 * 1000).toISOString(), 5, 10, 50, 1],
            ['beta', 100, new Date(100 * 1000).toISOString(), 8, 20, 70, 0],
            ['alpha', 200, new Date(200 * 1000).toISOString(), 6, 12, 52, 1]
        ]);
    });
});
