const path = require('path');

const scriptPath = path.resolve(__dirname, '../../discord-bot-jlg/assets/js/discord-bot-jlg.js');

function createContainer() {
    const container = document.createElement('div');
    container.className = 'discord-stats-container';
    container.dataset.refresh = '15';
    document.body.appendChild(container);
    return container;
}

function loadScript() {
    jest.isolateModules(() => {
        require(scriptPath);
    });
    document.dispatchEvent(new window.Event('DOMContentLoaded'));
}

describe('Editor iframe guard', () => {
    let setTimeoutSpy;

    beforeEach(() => {
        jest.resetModules();
        jest.useFakeTimers();
        setTimeoutSpy = jest.spyOn(global, 'setTimeout');
        document.body.innerHTML = '';
        document.body.className = '';
        window.discordBotJlg = undefined;
        delete window.DISCORD_BOT_JLG_IS_EDITOR;
        global.fetch = jest.fn();
        window.fetch = global.fetch;
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
        if (typeof window.discordBotJlgInit === 'function') {
            document.removeEventListener('DOMContentLoaded', window.discordBotJlgInit);
        }
        delete global.fetch;
        delete window.fetch;
        delete window.DISCORD_BOT_JLG_IS_EDITOR;
        delete window.discordBotJlg;
        delete window.discordBotJlgInit;
        document.body.innerHTML = '';
        document.body.className = '';
    });

    function bootWithRefresh() {
        createContainer();
        window.discordBotJlg = {
            ajaxUrl: 'https://example.com/wp-admin/admin-ajax.php',
            nonce: 'nonce',
            requiresNonce: true,
            locale: 'fr-FR',
            minRefreshInterval: '10'
        };
        loadScript();
    }

    test('exposes isEditorCanvas and skips refresh when the editor flag is set', () => {
        window.DISCORD_BOT_JLG_IS_EDITOR = true;
        bootWithRefresh();

        expect(typeof window.discordBotJlg.isEditorCanvas).toBe('function');
        expect(window.discordBotJlg.isEditorCanvas()).toBe(true);
        expect(window.discordBotJlg.autoRefreshDisabled).toBe(true);
        expect(global.fetch).not.toHaveBeenCalled();
        expect(setTimeoutSpy.mock.calls.some((call) => call[1] === 15000)).toBe(false);
    });

    test('detects the iframed canvas body class', () => {
        document.body.classList.add('block-editor-iframe__body');
        bootWithRefresh();

        expect(window.discordBotJlg.isEditorCanvas()).toBe(true);
        expect(window.discordBotJlg.autoRefreshDisabled).toBe(true);
        expect(setTimeoutSpy.mock.calls.some((call) => call[1] === 15000)).toBe(false);
    });

    test('detects data-discord-bot-editor on the markup', () => {
        const container = createContainer();
        container.setAttribute('data-discord-bot-editor', 'true');
        window.discordBotJlg = {
            ajaxUrl: 'https://example.com/wp-admin/admin-ajax.php',
            nonce: 'nonce',
            requiresNonce: true,
            locale: 'fr-FR',
            minRefreshInterval: '10'
        };
        loadScript();

        expect(window.discordBotJlg.isEditorCanvas()).toBe(true);
        expect(window.discordBotJlg.autoRefreshDisabled).toBe(true);
    });

    test('detects the editor-canvas iframe name used by WP 7.1', () => {
        const frame = document.createElement('iframe');
        frame.setAttribute('name', 'editor-canvas');
        frame.className = 'editor-canvas__iframe';
        Object.defineProperty(window, 'frameElement', {
            configurable: true,
            value: frame
        });

        bootWithRefresh();

        expect(window.discordBotJlg.isEditorCanvas()).toBe(true);
        expect(window.discordBotJlg.autoRefreshDisabled).toBe(true);

        delete window.frameElement;
    });
});
