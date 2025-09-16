(function () {
    'use strict';

    var ERROR_CLASS = 'discord-stats-error';

    function updateStats(container, config, formatter) {
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
                if (!data || !data.success || !data.data) {
                    return;
                }

                if (container && container.classList) {
                    container.classList.remove(ERROR_CLASS);
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
            .catch(function (error) {
                console.error('Erreur lors de la mise à jour des statistiques Discord :', error);

                if (container && container.classList) {
                    container.classList.add(ERROR_CLASS);
                }
            });
    }

    function initializeDiscordBot() {
        if (typeof window.fetch !== 'function' || typeof window.FormData !== 'function') {
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
            if (isNaN(interval) || interval <= 0) {
                return;
            }

            // L'attribut data-refresh est exprimé en secondes côté PHP.
            var intervalMs = interval * 1000;
            if (intervalMs < 10000) {
                return;
            }

            setInterval(function () {
                updateStats(container, config, formatter);
            }, intervalMs);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeDiscordBot);
    } else {
        initializeDiscordBot();
    }
})();
