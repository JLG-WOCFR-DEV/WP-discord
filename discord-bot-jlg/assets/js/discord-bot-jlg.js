(function () {
    'use strict';

    function updateStats(container, config, formatter) {
        var url = config.ajaxUrl + '?action=refresh_discord_stats&_ajax_nonce=' + encodeURIComponent(config.nonce);

        fetch(url)
            .then(function (response) {
                return response.json();
            })
            .then(function (data) {
                if (!data || !data.success || !data.data) {
                    return;
                }

                var online = container.querySelector('.discord-online .discord-number');
                if (online) {
                    online.textContent = formatter.format(data.data.online);
                    online.style.transform = 'scale(1.2)';
                    setTimeout(function () {
                        online.style.transform = 'scale(1)';
                    }, 300);
                }

                var total = container.querySelector('.discord-total .discord-number');
                if (total) {
                    total.textContent = formatter.format(data.data.total);
                    total.style.transform = 'scale(1.2)';
                    setTimeout(function () {
                        total.style.transform = 'scale(1)';
                    }, 300);
                }
            })
            .catch(function () {
                // Ignorer les erreurs réseau afin de ne pas casser l'interface.
            });
    }

    function initializeDiscordBot() {
        if (typeof window.fetch !== 'function') {
            return;
        }

        var config = window.discordBotJlg || {};
        if (!config.ajaxUrl || !config.nonce) {
            return;
        }

        var locale = config.locale || 'fr-FR';
        var formatter;

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
            if (!interval || interval <= 0) {
                return;
            }

            setInterval(function () {
                updateStats(container, config, formatter);
            }, interval * 1000);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeDiscordBot);
    } else {
        initializeDiscordBot();
    }
})();
