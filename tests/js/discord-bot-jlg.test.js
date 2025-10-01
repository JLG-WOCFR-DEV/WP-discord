const path = require('path');

const scriptPath = path.resolve(__dirname, '../../discord-bot-jlg/assets/js/discord-bot-jlg.js');

const readyStateDescriptor = Object.getOwnPropertyDescriptor(document, 'readyState');
let setTimeoutSpy;
let lastTimeoutCallIndex = -1;

class MockFormData {
    constructor() {
        this.entries = [];
    }

    append(key, value) {
        this.entries.push([key, value]);
    }
}

function flushPromises() {
    return Promise.resolve()
        .then(() => Promise.resolve())
        .then(() => Promise.resolve());
}

function createContainer(options = {}) {
    const {
        refresh = '15',
        showServerName = 'true',
        stale = false,
        lastUpdated = null,
        fallbackDemo = 'false',
        demo = 'false'
    } = options;

    const container = document.createElement('div');
    container.className = 'discord-stats-container';
    container.dataset.refresh = refresh;
    container.dataset.showServerName = showServerName;
    container.dataset.fallbackDemo = fallbackDemo;
    container.dataset.demo = demo;
    if (stale) {
        container.dataset.stale = 'true';
        if (lastUpdated !== null) {
            container.dataset.lastUpdated = String(lastUpdated);
        }
    }

    const wrapper = document.createElement('div');
    wrapper.className = 'discord-stats-wrapper';

    const online = document.createElement('div');
    online.className = 'discord-online';
    const onlineNumber = document.createElement('span');
    onlineNumber.className = 'discord-number';
    onlineNumber.textContent = '0';
    online.appendChild(onlineNumber);

    const total = document.createElement('div');
    total.className = 'discord-total';
    total.dataset.placeholder = '—';
    total.dataset.labelTotal = 'Total';
    total.dataset.labelUnavailable = 'Unavailable';
    total.dataset.labelApprox = '~';

    const totalNumber = document.createElement('span');
    totalNumber.className = 'discord-number';
    totalNumber.textContent = '0';

    const totalLabelText = document.createElement('span');
    totalLabelText.className = 'discord-label-text';

    const totalLabelExtra = document.createElement('span');
    totalLabelExtra.className = 'discord-label-extra';

    const indicator = document.createElement('span');
    indicator.className = 'discord-approx-indicator';
    indicator.hidden = true;

    total.appendChild(totalNumber);
    total.appendChild(totalLabelText);
    total.appendChild(totalLabelExtra);
    total.appendChild(indicator);

    wrapper.appendChild(online);
    wrapper.appendChild(total);
    container.appendChild(wrapper);

    document.body.appendChild(container);
    return container;
}

function loadScript() {
    jest.isolateModules(() => {
        require(scriptPath);
    });
    document.dispatchEvent(new window.Event('DOMContentLoaded'));
}

function runTimerByDelay(delay) {
    if (!setTimeoutSpy) {
        throw new Error('setTimeout spy is not initialized');
    }

    const callIndex = setTimeoutSpy.mock.calls.findIndex((call, index) => index > lastTimeoutCallIndex && call[1] === delay);

    if (callIndex === -1) {
        throw new Error('No timer scheduled for delay ' + delay);
    }

    lastTimeoutCallIndex = callIndex;
    const callback = setTimeoutSpy.mock.calls[callIndex][0];
    callback();
}

describe('discord-bot-jlg integration', () => {
    beforeEach(() => {
        jest.resetModules();
        jest.useFakeTimers();
        setTimeoutSpy = jest.spyOn(global, 'setTimeout');
        lastTimeoutCallIndex = -1;
        document.body.innerHTML = '';
        window.discordBotJlg = undefined;
        global.fetch = jest.fn();
        window.fetch = global.fetch;
        global.FormData = MockFormData;
        window.FormData = MockFormData;
        Object.defineProperty(document, 'readyState', {
            configurable: true,
            get: () => 'loading'
        });
    });

    afterEach(() => {
        jest.clearAllTimers();
        jest.useRealTimers();
        if (setTimeoutSpy) {
            setTimeoutSpy.mockRestore();
            setTimeoutSpy = null;
        }
        delete global.fetch;
        delete window.fetch;
        delete global.FormData;
        delete window.FormData;
        delete window.discordBotJlg;
        if (readyStateDescriptor) {
            Object.defineProperty(document, 'readyState', readyStateDescriptor);
        } else {
            delete document.readyState;
        }
    });

    test('successful refresh updates DOM elements and clears errors', async () => {
        const container = createContainer();

        window.discordBotJlg = {
            ajaxUrl: 'https://example.com/wp-admin/admin-ajax.php',
            nonce: 'nonce',
            requiresNonce: true,
            locale: 'en-US',
            minRefreshInterval: '5',
            staleNotice: 'Cached data from %s',
            demoBadgeLabel: 'Demo Mode'
        };

        global.fetch.mockResolvedValue({
            ok: true,
            json: () => Promise.resolve({
                success: true,
                data: {
                    online: 42,
                    total: 128,
                    has_total: true,
                    total_is_approximate: false,
                    stale: false,
                    is_demo: false,
                    fallback_demo: false,
                    server_name: 'Test Server',
                    last_updated: 1700000000
                }
            })
        });

        loadScript();

        runTimerByDelay(15000);
        await flushPromises();

        const onlineNumber = container.querySelector('.discord-online .discord-number');
        const totalNumber = container.querySelector('.discord-total .discord-number');
        const labelExtra = container.querySelector('.discord-total .discord-label-extra');
        const demoBadge = container.querySelector('.discord-demo-badge');

        expect(global.fetch).toHaveBeenCalledTimes(1);
        expect(global.fetch).toHaveBeenCalledWith(
            'https://example.com/wp-admin/admin-ajax.php',
            expect.objectContaining({
                method: 'POST',
                credentials: 'same-origin'
            })
        );
        expect(onlineNumber.textContent).toBe('42');
        expect(totalNumber.textContent).toBe('128');
        expect(labelExtra.textContent).toBe('');
        expect(container.dataset.serverName).toBe('Test Server');
        expect(container.classList.contains('discord-stats-error')).toBe(false);
        expect(demoBadge).toBeNull();

        const setTimeoutCalls = setTimeoutSpy.mock.calls;
        expect(setTimeoutCalls[setTimeoutCalls.length - 1][1]).toBe(15000);
    });

    test('stats update does not animate when container is not marked animated', async () => {
        const container = createContainer();
        container.className = 'discord-stats-container';

        window.discordBotJlg = {
            ajaxUrl: 'https://example.com/wp-admin/admin-ajax.php',
            nonce: 'nonce',
            requiresNonce: true,
            locale: 'en-US',
            minRefreshInterval: '5',
            staleNotice: 'Cached data from %s',
            demoBadgeLabel: 'Demo Mode'
        };

        global.fetch.mockResolvedValue({
            ok: true,
            json: () => Promise.resolve({
                success: true,
                data: {
                    online: 10,
                    total: 20,
                    has_total: true,
                    total_is_approximate: false,
                    stale: false,
                    is_demo: false,
                    fallback_demo: false,
                    server_name: 'Test Server',
                    last_updated: 1700000000
                }
            })
        });

        loadScript();

        runTimerByDelay(15000);
        await flushPromises();

        const onlineNumber = container.querySelector('.discord-online .discord-number');
        const totalNumber = container.querySelector('.discord-total .discord-number');

        expect(onlineNumber.textContent).toBe('10');
        expect(totalNumber.textContent).toBe('20');
        expect(onlineNumber.style.transform).toBe('');
        expect(onlineNumber.getAttribute('style')).toBeNull();
        expect(totalNumber.style.transform).toBe('');
        expect(totalNumber.getAttribute('style')).toBeNull();

        const hasAnimationTimer = setTimeoutSpy.mock.calls.some((call) => call[1] === 300);
        expect(hasAnimationTimer).toBe(false);
    });

    test('rate limited response surfaces error and delays next refresh based on retry_after', async () => {
        const container = createContainer();

        window.discordBotJlg = {
            ajaxUrl: 'https://example.com/wp-admin/admin-ajax.php',
            nonce: 'nonce',
            requiresNonce: true,
            locale: 'en-US',
            minRefreshInterval: '5'
        };

        global.fetch.mockResolvedValue({
            ok: true,
            json: () => Promise.resolve({
                success: true,
                data: {
                    rate_limited: true,
                    message: 'Please slow down',
                    retry_after: '60'
                }
            })
        });

        loadScript();

        runTimerByDelay(15000);
        await flushPromises();

        const errorMessage = container.querySelector('.discord-error-message');
        expect(errorMessage).not.toBeNull();
        expect(errorMessage.textContent).toBe('Please slow down');
        expect(container.classList.contains('discord-stats-error')).toBe(true);

        const lastCall = setTimeoutSpy.mock.calls[setTimeoutSpy.mock.calls.length - 1];
        expect(lastCall[1]).toBe(60000);
    });

    test('fallback success response propagates retry_after delay to next refresh', async () => {
        const container = createContainer({ fallbackDemo: 'true', demo: 'true' });

        window.discordBotJlg = {
            ajaxUrl: 'https://example.com/wp-admin/admin-ajax.php',
            nonce: 'nonce',
            requiresNonce: true,
            locale: 'en-US',
            minRefreshInterval: '5'
        };

        global.fetch.mockResolvedValue({
            ok: true,
            json: () => Promise.resolve({
                success: true,
                data: {
                    online: 5,
                    total: 15,
                    has_total: true,
                    total_is_approximate: false,
                    stale: true,
                    is_demo: true,
                    fallback_demo: true,
                    retry_after: 42
                }
            })
        });

        loadScript();
        await flushPromises();

        const lastCall = setTimeoutSpy.mock.calls[setTimeoutSpy.mock.calls.length - 1];
        expect(lastCall[1]).toBe(42000);
    });

    test('network failure surfaces generic error without altering state and keeps default schedule', async () => {
        const container = createContainer();

        window.discordBotJlg = {
            ajaxUrl: 'https://example.com/wp-admin/admin-ajax.php',
            nonce: 'nonce',
            requiresNonce: true,
            locale: 'en-US',
            minRefreshInterval: '5'
        };

        global.fetch.mockImplementation(() => Promise.reject(new Error('Network down')));

        loadScript();

        runTimerByDelay(15000);
        await flushPromises();

        const errorMessage = container.querySelector('.discord-error-message');
        expect(errorMessage).not.toBeNull();
        expect(errorMessage.textContent).toBe('Une erreur est survenue lors de la récupération des statistiques.');

        expect(container.classList.contains('discord-stats-error')).toBe(true);
        expect(container.classList.contains('discord-demo-mode')).toBe(false);
        expect(container.querySelector('.discord-demo-badge')).toBeNull();
        expect(container.querySelector('.discord-stale-notice')).toBeNull();
        expect(container.dataset.stale).toBeUndefined();
        expect(container.dataset.demo).toBe('false');
        expect(container.dataset.fallbackDemo).toBe('false');

        const setTimeoutCalls = setTimeoutSpy.mock.calls;
        expect(setTimeoutCalls[setTimeoutCalls.length - 1][1]).toBe(15000);
    });

    test('failed refresh clears inFlight flag and allows subsequent refresh', async () => {
        const container = createContainer();

        window.discordBotJlg = {
            ajaxUrl: 'https://example.com/wp-admin/admin-ajax.php',
            nonce: 'nonce',
            requiresNonce: true,
            locale: 'en-US',
            minRefreshInterval: '5'
        };

        global.fetch
            .mockImplementationOnce(() => Promise.reject(new Error('Temporary failure')))
            .mockImplementationOnce(() => Promise.resolve({
                ok: true,
                json: () => Promise.resolve({
                    success: true,
                    data: {
                        online: 7,
                        total: 11,
                        has_total: true,
                        total_is_approximate: false,
                        stale: false,
                        is_demo: false,
                        fallback_demo: false,
                        server_name: 'Recovered Server',
                        last_updated: 1700000100
                    }
                })
            }));

        loadScript();

        runTimerByDelay(15000);
        await flushPromises();

        const errorMessage = container.querySelector('.discord-error-message');
        expect(errorMessage).not.toBeNull();

        runTimerByDelay(15000);
        await flushPromises();

        const onlineNumber = container.querySelector('.discord-online .discord-number');
        const totalNumber = container.querySelector('.discord-total .discord-number');

        expect(global.fetch).toHaveBeenCalledTimes(2);
        expect(onlineNumber.textContent).toBe('7');
        expect(totalNumber.textContent).toBe('11');
        expect(container.classList.contains('discord-stats-error')).toBe(false);

        const lastCall = setTimeoutSpy.mock.calls[setTimeoutSpy.mock.calls.length - 1];
        expect(lastCall[1]).toBe(15000);
    });

    test('stale notice renders with formatted timestamp', async () => {
        const container = createContainer({ stale: true, lastUpdated: 1700000001 });

        const toLocaleSpy = jest.spyOn(Date.prototype, 'toLocaleString').mockReturnValue('January 1, 2024, 10:00 AM');

        window.discordBotJlg = {
            ajaxUrl: 'https://example.com/wp-admin/admin-ajax.php',
            nonce: 'nonce',
            requiresNonce: true,
            locale: 'en-US',
            minRefreshInterval: '5',
            staleNotice: 'Cached data from %s'
        };

        global.fetch.mockResolvedValue({
            ok: true,
            json: () => Promise.resolve({
                success: true,
                data: {
                    online: 10,
                    total: 20,
                    has_total: true,
                    total_is_approximate: false,
                    stale: true,
                    is_demo: false,
                    fallback_demo: false,
                    server_name: 'Server',
                    last_updated: 1700000001
                }
            })
        });

        loadScript();
        await flushPromises();

        const notice = container.querySelector('.discord-stale-notice');
        expect(notice).not.toBeNull();
        expect(notice.textContent).toBe('Cached data from January 1, 2024, 10:00 AM');
        expect(container.dataset.stale).toBe('true');
        expect(container.dataset.lastUpdated).toBe('1700000001');

        toLocaleSpy.mockRestore();
    });

    test('demo badge toggles based on response payload', async () => {
        const container = createContainer();

        window.discordBotJlg = {
            ajaxUrl: 'https://example.com/wp-admin/admin-ajax.php',
            nonce: 'nonce',
            requiresNonce: true,
            locale: 'en-US',
            minRefreshInterval: '5',
            demoBadgeLabel: 'Demo Mode'
        };

        global.fetch
            .mockImplementationOnce(() => Promise.resolve({
                ok: true,
                json: () => Promise.resolve({
                    success: true,
                    data: {
                        online: 10,
                        total: 20,
                        has_total: true,
                        total_is_approximate: false,
                        stale: false,
                        is_demo: true,
                        fallback_demo: false,
                        server_name: 'Demo Server',
                        last_updated: 1700000000
                    }
                })
            }))
            .mockImplementationOnce(() => Promise.resolve({
                ok: true,
                json: () => Promise.resolve({
                    success: true,
                    data: {
                        online: 12,
                        total: 25,
                        has_total: true,
                        total_is_approximate: false,
                        stale: false,
                        is_demo: false,
                        fallback_demo: false,
                        server_name: 'Demo Server',
                        last_updated: 1700000002
                    }
                })
            }))
            .mockResolvedValue({
                ok: true,
                json: () => Promise.resolve({
                    success: true,
                    data: {
                        online: 12,
                        total: 25,
                        has_total: true,
                        total_is_approximate: false,
                        stale: false,
                        is_demo: false,
                        fallback_demo: false,
                        server_name: 'Demo Server',
                        last_updated: 1700000002
                    }
                })
            });

        loadScript();

        runTimerByDelay(15000);
        await flushPromises();

        const badgeAfterFirstRefresh = container.querySelector('.discord-demo-badge');
        expect(badgeAfterFirstRefresh).not.toBeNull();
        expect(badgeAfterFirstRefresh.textContent).toBe('Demo Mode');
        expect(container.classList.contains('discord-demo-mode')).toBe(true);

        runTimerByDelay(15000);
        await flushPromises();

        if (global.fetch.mock.calls.length === 1) {
            runTimerByDelay(5000);
            await flushPromises();
        }

        expect(global.fetch.mock.calls.length).toBeGreaterThanOrEqual(2);

        const badgeAfterSecondRefresh = container.querySelector('.discord-demo-badge');
        expect(badgeAfterSecondRefresh).toBeNull();
        expect(container.classList.contains('discord-demo-mode')).toBe(false);
    });

    test('refresh interval respects minimum scheduling window', () => {
        createContainer({ refresh: '2' });

        window.discordBotJlg = {
            ajaxUrl: 'https://example.com/wp-admin/admin-ajax.php',
            nonce: 'nonce',
            requiresNonce: true,
            locale: 'en-US',
            minRefreshInterval: '5'
        };

        global.fetch.mockResolvedValue({
            ok: true,
            json: () => Promise.resolve({ success: false })
        });

        loadScript();

        const firstCall = setTimeoutSpy.mock.calls[0];
        expect(firstCall[1]).toBe(5000);
    });

    test('public init API re-initializes new containers', async () => {
        createContainer({ refresh: '15' });

        window.discordBotJlg = {
            ajaxUrl: 'https://example.com/wp-admin/admin-ajax.php',
            nonce: 'nonce',
            requiresNonce: true,
            locale: 'en-US',
            minRefreshInterval: '10'
        };

        global.fetch.mockResolvedValue({
            ok: true,
            json: () => Promise.resolve({
                success: true,
                data: {
                    online: 7,
                    total: 42,
                    has_total: true,
                    total_is_approximate: false,
                    stale: false,
                    is_demo: false,
                    fallback_demo: false,
                    server_name: 'Second Server',
                    last_updated: 1700000100
                }
            })
        });

        loadScript();

        expect(typeof window.discordBotJlgInit).toBe('function');
        expect(window.discordBotJlg.init).toBe(window.discordBotJlgInit);

        const existingCalls = setTimeoutSpy.mock.calls.length;

        const newContainer = createContainer({ refresh: '30' });
        expect(newContainer.dataset.refresh).toBe('30');

        window.discordBotJlgInit();

        const newCalls = setTimeoutSpy.mock.calls.slice(existingCalls);
        const hasNewTimer = newCalls.some((call) => call[1] === 30000);
        expect(hasNewTimer).toBe(true);

        const fetchCallsBefore = global.fetch.mock.calls.length;
        runTimerByDelay(30000);
        await flushPromises();

        expect(global.fetch.mock.calls.length).toBe(fetchCallsBefore + 1);
    });
});
