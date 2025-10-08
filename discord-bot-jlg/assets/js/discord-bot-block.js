(function (blocks, element, components, blockEditor, i18n, serverSideRender) {
    if (!blocks || !element || !components || !blockEditor || !i18n) {
        return;
    }

    var registerBlockType = blocks.registerBlockType;
    var createElement = element.createElement;
    var Fragment = element.Fragment;
    var useState = element.useState;
    var useEffect = element.useEffect;
    var __ = i18n.__;
    var InspectorControls = blockEditor.InspectorControls;
    var blockEditorComponents = (blockEditor && blockEditor.components) || {};
    var BlockControls = blockEditorComponents.BlockControls || blockEditor.BlockControls;
    var ToolbarGroup = blockEditorComponents.ToolbarGroup || components.ToolbarGroup;
    var ToolbarButton = blockEditorComponents.ToolbarButton || components.ToolbarButton;
    var Button = components.Button;

    if (!BlockControls) {
        BlockControls = function (props) {
            return createElement(Fragment, null, props && props.children);
        };
    }

    if (!ToolbarGroup) {
        ToolbarGroup = function (props) {
            return createElement(Fragment, null, props && props.children);
        };
    }

    if (!ToolbarButton && Button) {
        ToolbarButton = function (props) {
            var buttonProps = {};

            for (var key in props) {
                if (!Object.prototype.hasOwnProperty.call(props, key)) {
                    continue;
                }

                if (key === 'icon' || key === 'isPressed' || key === 'showTooltip' || key === 'label') {
                    continue;
                }

                buttonProps[key] = props[key];
            }

            buttonProps.type = buttonProps.type || 'button';
            buttonProps.className = (buttonProps.className || '') + ' discord-bot-toolbar-fallback-button';
            buttonProps['aria-label'] = props.label || buttonProps['aria-label'];
            buttonProps.title = props.label || buttonProps.title;

            if (typeof props.isPressed !== 'undefined') {
                buttonProps['aria-pressed'] = props.isPressed ? 'true' : 'false';
            }

            var children = props.children;

            if (!children && props.icon) {
                children = createElement('span', {
                    className: 'dashicons dashicons-' + props.icon,
                    'aria-hidden': 'true'
                });
            }

            return createElement(Button, buttonProps, children || props.label);
        };
    }
    var useBlockProps = blockEditor.useBlockProps || function () { return {}; };
    var PanelBody = components.PanelBody;
    var ToolsPanel = components.__experimentalToolsPanel;
    var ToolsPanelItem = components.__experimentalToolsPanelItem;
    var VStack = components.__experimentalVStack || components.VStack;
    var ToggleControl = components.ToggleControl;
    var TextControl = components.TextControl;
    var SelectControl = components.SelectControl;
    var RangeControl = components.RangeControl;
    var NumberControl = components.NumberControl || components.__experimentalNumberControl;
    var BaseControl = components.BaseControl;
    var PanelColorSettings = blockEditor.PanelColorSettings || blockEditor.__experimentalPanelColorSettings;
    var ColorPalette = (blockEditor && blockEditor.ColorPalette) || components.ColorPalette;
    var RefreshIntervalControl = NumberControl || RangeControl || TextControl;
    var SparklineDaysControl = NumberControl || RangeControl || TextControl;
    var ServerSideRender = serverSideRender;

    if (!registerBlockType || !InspectorControls) {
        return;
    }

    var blockName = 'discord-bot-jlg/discord-stats';

    var layoutOptions = [
        { label: __('Horizontal', 'discord-bot-jlg'), value: 'horizontal' },
        { label: __('Vertical', 'discord-bot-jlg'), value: 'vertical' }
    ];

    var themeOptions = [
        { label: __('Discord', 'discord-bot-jlg'), value: 'discord' },
        { label: __('Sombre', 'discord-bot-jlg'), value: 'dark' },
        { label: __('Clair', 'discord-bot-jlg'), value: 'light' },
        { label: __('Minimal', 'discord-bot-jlg'), value: 'minimal' },
        { label: __('Radix Structure', 'discord-bot-jlg'), value: 'radix' },
        { label: __('Headless Essence', 'discord-bot-jlg'), value: 'headless' },
        { label: __('Shadcn Minimal', 'discord-bot-jlg'), value: 'shadcn' },
        { label: __('Bootstrap Fluent', 'discord-bot-jlg'), value: 'bootstrap' },
        { label: __('Semantic Harmony', 'discord-bot-jlg'), value: 'semantic' },
        { label: __('Anime Pulse', 'discord-bot-jlg'), value: 'anime' }
    ];

    var alignOptions = [
        { label: __('Gauche', 'discord-bot-jlg'), value: 'left' },
        { label: __('Centre', 'discord-bot-jlg'), value: 'center' },
        { label: __('Droite', 'discord-bot-jlg'), value: 'right' }
    ];

    var iconPositionOptions = [
        { label: __('À gauche', 'discord-bot-jlg'), value: 'left' },
        { label: __('En haut', 'discord-bot-jlg'), value: 'top' },
        { label: __('À droite', 'discord-bot-jlg'), value: 'right' }
    ];

    var ctaStyleOptions = [
        { label: __('Plein', 'discord-bot-jlg'), value: 'solid' },
        { label: __('Contour', 'discord-bot-jlg'), value: 'outline' }
    ];

    var blockConfig = window.discordBotJlgBlockConfig || {};
    var globalDefaults = blockConfig.defaults || {};

    function pickDefault(key, fallback) {
        if (!globalDefaults || typeof globalDefaults !== 'object') {
            return fallback;
        }

        var value = globalDefaults[key];

        if (typeof value === 'undefined' || value === null || value === '') {
            return fallback;
        }

        return value;
    }

    var profileChoices = Array.isArray(blockConfig.profiles) ? blockConfig.profiles : [];
    var profileOptions = profileChoices.map(function (choice) {
        if (!choice || typeof choice !== 'object') {
            return { label: '', value: '' };
        }

        var value = choice.key || '';
        var baseLabel = choice.label || '';
        var serverId = choice.server_id || '';
        var computedLabel = baseLabel || serverId || value;

        if (serverId) {
            computedLabel += ' (' + serverId + ')';
        }

        return {
            label: computedLabel,
            value: value
        };
    });

    profileOptions.unshift({
        label: __('Configuration globale', 'discord-bot-jlg'),
        value: ''
    });

    var defaultAttributes = {
        layout: 'horizontal',
        show_online: true,
        show_total: true,
        show_presence_breakdown: false,
        show_approximate_member_count: false,
        show_premium_subscriptions: false,
        show_title: false,
        title: '',
        theme: 'discord',
        animated: true,
        refresh: false,
        refresh_interval: '60',
        compact: false,
        align: 'left',
        width: '',
        icon_online: pickDefault('icon_online', '🟢'),
        icon_total: pickDefault('icon_total', '👥'),
        icon_presence: pickDefault('icon_presence', '📊'),
        icon_approximate: pickDefault('icon_approximate', '📈'),
        icon_premium: pickDefault('icon_premium', '💎'),
        label_online: pickDefault('label_online', __('En ligne', 'discord-bot-jlg')),
        label_total: pickDefault('label_total', __('Membres', 'discord-bot-jlg')),
        label_presence: pickDefault('label_presence', __('Présence par statut', 'discord-bot-jlg')),
        label_presence_online: pickDefault('label_presence_online', __('En ligne', 'discord-bot-jlg')),
        label_presence_idle: pickDefault('label_presence_idle', __('Inactif', 'discord-bot-jlg')),
        label_presence_dnd: pickDefault('label_presence_dnd', __('Ne pas déranger', 'discord-bot-jlg')),
        label_presence_offline: pickDefault('label_presence_offline', __('Hors ligne', 'discord-bot-jlg')),
        label_presence_streaming: pickDefault('label_presence_streaming', __('En direct', 'discord-bot-jlg')),
        label_presence_other: pickDefault('label_presence_other', __('Autres', 'discord-bot-jlg')),
        label_approximate: pickDefault('label_approximate', __('Membres (approx.)', 'discord-bot-jlg')),
        label_premium: pickDefault('label_premium', __('Boosts serveur', 'discord-bot-jlg')),
        label_premium_singular: pickDefault('label_premium_singular', __('Boost serveur', 'discord-bot-jlg')),
        label_premium_plural: pickDefault('label_premium_plural', __('Boosts serveur', 'discord-bot-jlg')),
        hide_labels: false,
        hide_icons: false,
        border_radius: 8,
        gap: 20,
        padding: 15,
        stat_bg_color: '',
        stat_text_color: '',
        accent_color: '',
        accent_color_alt: '',
        accent_text_color: '',
        demo: false,
        show_discord_icon: false,
        discord_icon_position: 'left',
        show_server_name: false,
        show_server_avatar: false,
        avatar_size: 128,
        invite_url: '',
        invite_label: '',
        cta_enabled: false,
        cta_label: '',
        cta_url: '',
        cta_style: 'solid',
        cta_new_tab: true,
        cta_tooltip: '',
        profile: '',
        server_id: '',
        bot_token: '',
        show_sparkline: false,
        sparkline_metric: 'online',
        sparkline_days: 7
    };

    var metricsToggleConfigs = [
        {
            attribute: 'show_online',
            label: __('Afficher les membres en ligne', 'discord-bot-jlg'),
            defaultValue: defaultAttributes.show_online
        },
        {
            attribute: 'show_total',
            label: __('Afficher le total des membres', 'discord-bot-jlg'),
            defaultValue: defaultAttributes.show_total
        },
        {
            attribute: 'show_presence_breakdown',
            label: __('Afficher la répartition des présences', 'discord-bot-jlg'),
            defaultValue: defaultAttributes.show_presence_breakdown
        },
        {
            attribute: 'show_approximate_member_count',
            label: __('Afficher le total approximatif', 'discord-bot-jlg'),
            defaultValue: defaultAttributes.show_approximate_member_count
        },
        {
            attribute: 'show_premium_subscriptions',
            label: __('Afficher les boosts Nitro', 'discord-bot-jlg'),
            defaultValue: defaultAttributes.show_premium_subscriptions
        },
        {
            attribute: 'show_sparkline',
            label: __('Afficher la mini-sparkline', 'discord-bot-jlg'),
            defaultValue: defaultAttributes.show_sparkline,
            help: __('Nécessite l’historique généré par le cron pour afficher une tendance.', 'discord-bot-jlg')
        }
    ];

    var animationToggleConfigs = [
        {
            attribute: 'animated',
            label: __('Activer les animations', 'discord-bot-jlg'),
            defaultValue: defaultAttributes.animated
        },
        {
            attribute: 'refresh',
            label: __('Rafraîchissement automatique', 'discord-bot-jlg'),
            defaultValue: defaultAttributes.refresh
        }
    ];

    var sparklineMetricOptions = [
        { label: __('Membres en ligne', 'discord-bot-jlg'), value: 'online' },
        { label: __('Présence approximative', 'discord-bot-jlg'), value: 'presence' },
        { label: __('Boosts Nitro', 'discord-bot-jlg'), value: 'premium' }
    ];

    var REFRESH_INTERVAL_MIN = 10;
    var REFRESH_INTERVAL_MAX = 3600;
    var REFRESH_INTERVAL_FALLBACK = 60;

    function normalizeRefreshInterval(value) {
        var parsed = parseInt(value, 10);
        var normalized = Math.max(REFRESH_INTERVAL_MIN, parsed || REFRESH_INTERVAL_FALLBACK);

        if (typeof REFRESH_INTERVAL_MAX === 'number') {
            normalized = Math.min(REFRESH_INTERVAL_MAX, normalized);
        }

        return normalized;
    }

    function normalizeAvatarSize(value) {
        var allowedSizes = [16, 32, 64, 128, 256, 512, 1024, 2048, 4096];
        var parsed = parseInt(value, 10);

        if (isNaN(parsed) || parsed <= 0) {
            parsed = 128;
        }

        for (var i = 0; i < allowedSizes.length; i++) {
            if (parsed <= allowedSizes[i]) {
                return allowedSizes[i];
            }
        }

        return allowedSizes[allowedSizes.length - 1];
    }

    var STATIC_PREVIEW_AVATAR_BASE = 'https://cdn.discordapp.com/embed/avatars/0.png';
    var DISCORD_LOGO_SVG = '<svg class="discord-logo-svg" aria-hidden="true" focusable="false" viewBox="0 0 127.14 96.36" xmlns="http://www.w3.org/2000/svg"><path d="M107.7,8.07A105.15,105.15,0,0,0,81.47,0a72.06,72.06,0,0,0-3.36,6.83A97.68,97.68,0,0,0,49,6.83,72.37,72.37,0,0,0,45.64,0,105.89,105.89,0,0,0,19.39,8.09C2.79,32.65-1.71,56.6.54,80.21h0A105.73,105.73,0,0,0,32.71,96.36,77.7,77.7,0,0,0,39.6,85.25a68.42,68.42,0,0,1-10.85-5.18c.91-.66,1.8-1.34,2.66-2a75.57,75.57,0,0,0,64.32,0c.87.71,1.76,1.39,2.66,2a68.68,68.68,0,0,1-10.87,5.19,77,77,0,0,0,6.89,11.1A105.25,105.25,0,0,0,126.6,80.22h0C129.24,52.84,122.09,29.11,107.7,8.07ZM42.45,65.69C36.18,65.69,31,60,31,53s5-12.74,11.43-12.74S54,46,53.89,53,48.84,65.69,42.45,65.69Zm42.24,0C78.41,65.69,73.25,60,73.25,53s5-12.74,11.44-12.74S96.23,46,96.12,53,91.08,65.69,84.69,65.69Z"/></svg>';

    function mergeAttributesWithDefaults(attributes) {
        var merged = {};

        for (var key in defaultAttributes) {
            if (Object.prototype.hasOwnProperty.call(defaultAttributes, key)) {
                merged[key] = defaultAttributes[key];
            }
        }

        if (attributes && typeof attributes === 'object') {
            for (var attrKey in attributes) {
                if (Object.prototype.hasOwnProperty.call(attributes, attrKey)) {
                    merged[attrKey] = attributes[attrKey];
                }
            }
        }

        return merged;
    }

    function formatStaticNumber(value) {
        var parsed = parseInt(value, 10);

        if (isNaN(parsed)) {
            return '0';
        }

        if (typeof parsed.toLocaleString === 'function') {
            return parsed.toLocaleString();
        }

        return String(parsed);
    }

    function generateStaticPreviewData(attributes) {
        var mergedAttributes = mergeAttributesWithDefaults(attributes);
        mergedAttributes.avatar_size = normalizeAvatarSize(mergedAttributes.avatar_size);

        var baseOnline = 42;
        var baseTotal = 256;
        var date = new Date();
        var hour = date.getUTCHours ? date.getUTCHours() : date.getHours();
        var variation = Math.sin(hour * 0.26) * 10;
        var onlineValue = Math.round(baseOnline + variation);

        if (!isFinite(onlineValue)) {
            onlineValue = baseOnline;
        }

        var demoServerName = __('Serveur Démo', 'discord-bot-jlg');
        var avatarUrl = STATIC_PREVIEW_AVATAR_BASE + '?size=' + mergedAttributes.avatar_size;

        var presenceBreakdown = {
            online: Math.max(0, Math.round(onlineValue * 0.68)),
            idle: Math.max(0, Math.round(onlineValue * 0.22))
        };

        presenceBreakdown.dnd = Math.max(0, onlineValue - presenceBreakdown.online - presenceBreakdown.idle);

        var stats = {
            online: onlineValue,
            total: baseTotal,
            has_total: true,
            total_is_approximate: false,
            is_demo: true,
            fallback_demo: false,
            stale: false,
            last_updated: null,
            server_name: demoServerName,
            server_avatar_url: avatarUrl,
            server_avatar_base_url: STATIC_PREVIEW_AVATAR_BASE,
            approximate_presence_count: onlineValue,
            approximate_member_count: baseTotal,
            presence_count_by_status: presenceBreakdown,
            premium_subscription_count: 6
        };

        return {
            attributes: mergedAttributes,
            stats: stats
        };
    }

    function sanitizeClassName(value) {
        if (typeof value !== 'string') {
            return '';
        }

        return value.trim().replace(/\s+/g, '-');
    }

    function createLogoElement(position) {
        if (!position) {
            return null;
        }

        var className = 'discord-logo-container';

        if (position === 'top') {
            className += ' discord-logo-top';
        }

        return createElement('div', {
            className: className,
            dangerouslySetInnerHTML: { __html: DISCORD_LOGO_SVG }
        });
    }

    function createServerHeaderElement(createAvatar, showName, stats, attributes) {
        if (!createAvatar && !showName) {
            return null;
        }

        var children = [];

        if (createAvatar) {
            var avatarAlt = __('Avatar du serveur Discord', 'discord-bot-jlg');

            if (stats.server_name) {
                avatarAlt = avatarAlt + ' ' + stats.server_name;
            }

            children.push(createElement(
                'div',
                { className: 'discord-server-avatar', 'data-role': 'discord-server-avatar' },
                createElement('img', {
                    className: 'discord-server-avatar__image',
                    src: stats.server_avatar_url,
                    alt: avatarAlt,
                    loading: 'lazy',
                    decoding: 'async',
                    width: attributes.avatar_size,
                    height: attributes.avatar_size
                })
            ));
        }

        if (showName && stats.server_name) {
            children.push(createElement(
                'div',
                { className: 'discord-server-name', 'data-role': 'discord-server-name' },
                createElement('span', { className: 'discord-server-name__text' }, stats.server_name)
            ));
        }

        if (!children.length) {
            return null;
        }

        return createElementWithChildren('div', { className: 'discord-server-header', 'data-role': 'discord-server-header' }, children);
    }

    function createElementWithChildren(type, props, children) {
        var args = [type, props || null];

        if (Array.isArray(children)) {
            for (var i = 0; i < children.length; i++) {
                if (typeof children[i] === 'undefined' || children[i] === null) {
                    continue;
                }

                args.push(children[i]);
            }
        } else if (typeof children !== 'undefined') {
            args.push(children);
        }

        return createElement.apply(null, args);
    }

    function renderStaticPreview(attributes) {
        var previewData = generateStaticPreviewData(attributes);
        var previewAttributes = previewData.attributes;
        var stats = previewData.stats;

        var hideLabels = !!previewAttributes.hide_labels;
        var hideIcons = !!previewAttributes.hide_icons;
        var showOnline = !!previewAttributes.show_online;
        var showTotal = !!previewAttributes.show_total;
        var showPresence = !!previewAttributes.show_presence_breakdown;
        var showApproximate = !!previewAttributes.show_approximate_member_count;
        var showPremium = !!previewAttributes.show_premium_subscriptions;
        var showTitle = !!previewAttributes.show_title && previewAttributes.title;
        var showDiscordIcon = !!previewAttributes.show_discord_icon;
        var showServerName = !!previewAttributes.show_server_name;
        var showServerAvatar = !!previewAttributes.show_server_avatar;
        var hasTotal = !!stats.has_total;

        var layoutClass = (previewAttributes.layout || 'horizontal').toString().toLowerCase();
        var themeClass = (previewAttributes.theme || 'discord').toString().toLowerCase();
        var alignClass = (previewAttributes.align || 'left').toString().toLowerCase();
        var logoPosition = showDiscordIcon
            ? (previewAttributes.discord_icon_position || 'left').toString().toLowerCase()
            : '';

        var containerClasses = ['discord-stats-container'];

        if (layoutClass) {
            containerClasses.push('discord-layout-' + sanitizeClassName(layoutClass));
        }

        if (themeClass) {
            containerClasses.push('discord-theme-' + sanitizeClassName(themeClass));
        }

        if (alignClass) {
            containerClasses.push('discord-align-' + sanitizeClassName(alignClass));
        }

        if (previewAttributes.compact) {
            containerClasses.push('discord-compact');
        }

        if (previewAttributes.animated) {
            containerClasses.push('discord-animated');
        }

        containerClasses.push('discord-demo-mode');

        if (!hasTotal) {
            containerClasses.push('discord-total-missing');
        }

        if (showDiscordIcon) {
            containerClasses.push('discord-with-logo');

            if (logoPosition) {
                containerClasses.push('discord-logo-' + sanitizeClassName(logoPosition));
            }
        }

        if (previewAttributes.invite_url) {
            containerClasses.push('discord-has-invite');
        }

        if (previewAttributes.cta_enabled) {
            containerClasses.push('discord-has-cta');
            containerClasses.push('discord-cta-style-' + sanitizeClassName(previewAttributes.cta_style || 'solid'));
        }

        var hasPresenceData = !!(stats && ((typeof stats.approximate_presence_count === 'number') || (stats.presence_count_by_status && Object.keys(stats.presence_count_by_status).length)));
        var hasApproximateMembers = typeof stats.approximate_member_count === 'number';

        if (showPresence && hasPresenceData) {
            containerClasses.push('discord-has-presence-breakdown');
        }

        if (showApproximate && hasApproximateMembers) {
            containerClasses.push('discord-has-approximate-total');
        }

        if (showPremium) {
            containerClasses.push('discord-has-premium');
        }

        if (showServerAvatar) {
            containerClasses.push('discord-avatar-enabled');

            if (stats.server_avatar_url) {
                containerClasses.push('discord-has-server-avatar');
            }
        }

        var customClassSources = [];

        if (previewAttributes.className) {
            customClassSources.push(previewAttributes.className);
        }

        if (previewAttributes.class) {
            customClassSources.push(previewAttributes.class);
        }

        if (customClassSources.length) {
            for (var i = 0; i < customClassSources.length; i++) {
                var value = customClassSources[i];

                if (typeof value !== 'string') {
                    continue;
                }

                var parts = value.split(/\s+/);

                for (var j = 0; j < parts.length; j++) {
                    if (parts[j]) {
                        containerClasses.push(parts[j]);
                    }
                }
            }
        }

        var style = {};

        var gapValue = parseInt(previewAttributes.gap, 10);
        if (isNaN(gapValue)) {
            gapValue = parseInt(defaultAttributes.gap, 10) || 20;
        }
        style['--discord-gap'] = gapValue + 'px';

        var paddingValue = parseInt(previewAttributes.padding, 10);
        if (isNaN(paddingValue)) {
            paddingValue = parseInt(defaultAttributes.padding, 10) || 15;
        }
        style['--discord-padding'] = paddingValue + 'px';

        var radiusValue = parseInt(previewAttributes.border_radius, 10);
        if (isNaN(radiusValue)) {
            radiusValue = parseInt(defaultAttributes.border_radius, 10) || 8;
        }
        style['--discord-radius'] = radiusValue + 'px';

        if (previewAttributes.stat_bg_color) {
            style['--discord-surface-background'] = previewAttributes.stat_bg_color;
        }

        if (previewAttributes.stat_text_color) {
            style['--discord-surface-text'] = previewAttributes.stat_text_color;
        }

        if (previewAttributes.accent_color) {
            style['--discord-accent'] = previewAttributes.accent_color;
            style['--discord-logo-color'] = previewAttributes.accent_color;
        }

        if (previewAttributes.accent_color_alt) {
            style['--discord-accent-secondary'] = previewAttributes.accent_color_alt;
        }

        if (previewAttributes.accent_text_color) {
            style['--discord-accent-contrast'] = previewAttributes.accent_text_color;
        }

        if (previewAttributes.width) {
            var widthValue = String(previewAttributes.width);
            style.maxWidth = widthValue;

            var keywords = ['auto', 'fit-content', 'max-content', 'min-content'];
            if (keywords.indexOf(widthValue.toLowerCase()) >= 0) {
                style.width = widthValue;
            } else {
                style.width = '100%';
            }
        }

        var containerProps = {
            className: containerClasses.join(' '),
            'data-demo': 'true',
            'data-fallback-demo': 'false',
            'data-stale': 'false',
            'data-hide-labels': hideLabels ? 'true' : 'false',
            'data-static-preview': 'true',
            style: style
        };

        if (showServerName) {
            containerProps['data-show-server-name'] = 'true';

            if (stats.server_name) {
                containerProps['data-server-name'] = stats.server_name;
            }
        }

        if (showServerAvatar) {
            containerProps['data-show-server-avatar'] = 'true';
            containerProps['data-avatar-size'] = previewAttributes.avatar_size;

            if (stats.server_avatar_url) {
                containerProps['data-server-avatar-url'] = stats.server_avatar_url;
            }

            if (stats.server_avatar_base_url) {
                containerProps['data-server-avatar-base-url'] = stats.server_avatar_base_url;
            }
        }

        var children = [];

        children.push(createElement('div', {
            className: 'discord-demo-badge discord-static-preview-badge'
        }, __('Mode Démo', 'discord-bot-jlg') + ' · ' + __('Aperçu statique', 'discord-bot-jlg')));

        if (showTitle) {
            children.push(createElement('div', { className: 'discord-stats-title' }, previewAttributes.title));
        }

        var statsMainChildren = [];

        if (showDiscordIcon && logoPosition === 'left') {
            statsMainChildren.push(createLogoElement('left'));
        }

        if (showDiscordIcon && logoPosition === 'top') {
            statsMainChildren.push(createLogoElement('top'));
        }

        var statsWrapperChildren = [];
        var headerElement = createServerHeaderElement(
            showServerAvatar && !!stats.server_avatar_url,
            showServerName,
            stats,
            previewAttributes
        );

        if (headerElement) {
            statsWrapperChildren.push(headerElement);
        }

        if (showOnline) {
            var onlineLabelClasses = ['discord-label'];

            if (hideLabels) {
                onlineLabelClasses.push('screen-reader-text');
            }

            var onlineStatChildren = [];

            if (!hideIcons) {
                onlineStatChildren.push(createElement('span', { className: 'discord-icon' }, previewAttributes.icon_online));
            }

            onlineStatChildren.push(createElement('span', {
                className: 'discord-number',
                role: 'status',
                'aria-live': 'polite'
            }, formatStaticNumber(stats.online)));

            onlineStatChildren.push(createElement('span', { className: onlineLabelClasses.join(' ') },
                createElement('span', { className: 'discord-label-text' }, previewAttributes.label_online)
            ));

            statsWrapperChildren.push(createElementWithChildren('div', {
                className: 'discord-stat discord-online',
                'data-value': stats.online,
                'data-label-online': previewAttributes.label_online,
                'data-hide-labels': hideLabels ? 'true' : 'false'
            }, onlineStatChildren));
        }

        if (showTotal) {
            var totalClasses = ['discord-stat', 'discord-total'];

            if (!hasTotal) {
                totalClasses.push('discord-total-unavailable');
            } else if (stats.total_is_approximate) {
                totalClasses.push('discord-total-approximate');
            }

            var totalLabelClasses = ['discord-label'];

            if (hideLabels) {
                totalLabelClasses.push('screen-reader-text');
            }

            var totalChildren = [];

            if (!hideIcons) {
                totalChildren.push(createElement('span', { className: 'discord-icon' }, previewAttributes.icon_total));
            }

            totalChildren.push(createElement('span', {
                className: 'discord-number',
                role: 'status',
                'aria-live': 'polite'
            }, hasTotal ? formatStaticNumber(stats.total) : '—'));

            totalChildren.push(createElement('span', {
                className: 'discord-approx-indicator',
                'aria-hidden': 'true',
                hidden: !stats.total_is_approximate
            }, '≈'));

            totalChildren.push(createElement('span', { className: totalLabelClasses.join(' ') },
                createElement('span', { className: 'discord-label-text' }, hasTotal ? previewAttributes.label_total : __('Total indisponible', 'discord-bot-jlg')),
                createElement('span', { className: 'discord-label-extra screen-reader-text' }, stats.total_is_approximate ? __('approx.', 'discord-bot-jlg') : '')
            ));

            var totalProps = {
                className: totalClasses.join(' '),
                'data-label-total': previewAttributes.label_total,
                'data-label-unavailable': __('Total indisponible', 'discord-bot-jlg'),
                'data-label-approx': __('approx.', 'discord-bot-jlg'),
                'data-placeholder': '—'
            };

            if (hasTotal) {
                totalProps['data-value'] = stats.total;
            }

            statsWrapperChildren.push(createElementWithChildren('div', totalProps, totalChildren));
        }

        if (showPresence) {
            var presenceLabelClasses = ['discord-label'];
            if (hideLabels) {
                presenceLabelClasses.push('screen-reader-text');
            }

            var presenceBreakdown = stats.presence_count_by_status || {};
            var preferredPresenceOrder = ['online', 'idle', 'dnd', 'offline', 'streaming', 'other'];
            var presenceOrder = [];

            for (var presenceIndex = 0; presenceIndex < preferredPresenceOrder.length; presenceIndex++) {
                var preferredStatus = preferredPresenceOrder[presenceIndex];
                if (Object.prototype.hasOwnProperty.call(presenceBreakdown, preferredStatus)) {
                    presenceOrder.push(preferredStatus);
                }
            }

            var remainingPresenceKeys = Object.keys(presenceBreakdown).sort();
            for (var remainingIndex = 0; remainingIndex < remainingPresenceKeys.length; remainingIndex++) {
                var remainingStatus = remainingPresenceKeys[remainingIndex];
                if (presenceOrder.indexOf(remainingStatus) === -1) {
                    presenceOrder.push(remainingStatus);
                }
            }

            var presenceValue = typeof stats.approximate_presence_count === 'number'
                ? stats.approximate_presence_count
                : presenceOrder.reduce(function (accumulator, status) {
                    var statusValue = presenceBreakdown[status];
                    if (typeof statusValue !== 'number') {
                        statusValue = parseInt(statusValue, 10) || 0;
                    }
                    return accumulator + Math.max(0, statusValue);
                }, 0);

            if (typeof presenceValue !== 'number' || !isFinite(presenceValue) || presenceValue < 0) {
                presenceValue = 0;
            }

            var presenceSummary = createElementWithChildren('div', { className: 'discord-presence-summary' }, [
                createElement('span', { className: 'discord-number', role: 'status', 'aria-live': 'polite' }, formatStaticNumber(presenceValue)),
                createElement('span', { className: presenceLabelClasses.join(' ') },
                    createElement('span', { className: 'discord-label-text' }, previewAttributes.label_presence)
                )
            ]);

            var presenceContentChildren = [presenceSummary];

            if (presenceOrder.length) {
                var presenceItems = [];
                for (var presenceItemIndex = 0; presenceItemIndex < presenceOrder.length; presenceItemIndex++) {
                    var statusKey = presenceOrder[presenceItemIndex];
                    var rawCount = presenceBreakdown[statusKey];
                    if (typeof rawCount !== 'number') {
                        rawCount = parseInt(rawCount, 10) || 0;
                    }

                    var safeCount = Math.max(0, rawCount);
                    var labelMap = {
                        online: previewAttributes.label_presence_online,
                        idle: previewAttributes.label_presence_idle,
                        dnd: previewAttributes.label_presence_dnd,
                        offline: previewAttributes.label_presence_offline,
                        streaming: previewAttributes.label_presence_streaming,
                        other: previewAttributes.label_presence_other
                    };
                    var statusLabel = labelMap[statusKey] || statusKey.charAt(0).toUpperCase() + statusKey.slice(1);

                    presenceItems.push(createElementWithChildren('li', {
                        className: 'discord-presence-item discord-presence-' + sanitizeClassName(statusKey),
                        'data-status': statusKey,
                        'data-label': statusLabel
                    }, [
                        createElement('span', { className: 'discord-presence-dot', 'aria-hidden': 'true' }),
                        createElement('span', { className: 'discord-presence-item-label' }, statusLabel),
                        createElement('span', { className: 'discord-presence-item-value' }, formatStaticNumber(safeCount))
                    ]));
                }

                presenceContentChildren.push(createElementWithChildren('ul', { className: 'discord-presence-list' }, presenceItems));
            }

            var presenceChildren = [];
            if (!hideIcons) {
                presenceChildren.push(createElement('span', { className: 'discord-icon' }, previewAttributes.icon_presence));
            }

            presenceChildren.push(createElementWithChildren('div', { className: 'discord-presence-content' }, presenceContentChildren));

            statsWrapperChildren.push(createElementWithChildren('div', {
                className: 'discord-stat discord-presence-breakdown',
                'data-role': 'discord-presence-breakdown',
                'data-label-presence': previewAttributes.label_presence,
                'data-label-online': previewAttributes.label_presence_online,
                'data-label-idle': previewAttributes.label_presence_idle,
                'data-label-dnd': previewAttributes.label_presence_dnd,
                'data-label-offline': previewAttributes.label_presence_offline,
                'data-label-streaming': previewAttributes.label_presence_streaming,
                'data-label-other': previewAttributes.label_presence_other,
                'data-hide-labels': hideLabels ? 'true' : 'false',
                'data-value': presenceValue
            }, presenceChildren));
        }

        if (showApproximate) {
            var approximateLabelClasses = ['discord-label'];
            if (hideLabels) {
                approximateLabelClasses.push('screen-reader-text');
            }

            var approximateValue = typeof stats.approximate_member_count === 'number'
                ? stats.approximate_member_count
                : stats.total;

            if (typeof approximateValue !== 'number' || !isFinite(approximateValue)) {
                approximateValue = stats.total || 0;
            }

            var approximateChildren = [];
            if (!hideIcons) {
                approximateChildren.push(createElement('span', { className: 'discord-icon' }, previewAttributes.icon_approximate));
            }

            approximateChildren.push(createElement('span', {
                className: 'discord-number',
                role: 'status',
                'aria-live': 'polite'
            }, formatStaticNumber(approximateValue)));

            approximateChildren.push(createElement('span', {
                className: 'discord-approx-indicator',
                'aria-hidden': 'true'
            }, '≈'));

            approximateChildren.push(createElement('span', { className: approximateLabelClasses.join(' ') },
                createElement('span', { className: 'discord-label-text' }, previewAttributes.label_approximate)
            ));

            statsWrapperChildren.push(createElementWithChildren('div', {
                className: 'discord-stat discord-approximate-members',
                'data-role': 'discord-approximate-members',
                'data-label-approximate': previewAttributes.label_approximate,
                'data-placeholder': '—',
                'data-value': approximateValue
            }, approximateChildren));
        }

        if (showPremium) {
            var premiumLabelClasses = ['discord-label'];
            if (hideLabels) {
                premiumLabelClasses.push('screen-reader-text');
            }

            var premiumValue = typeof stats.premium_subscription_count === 'number'
                ? stats.premium_subscription_count
                : 0;

            if (!isFinite(premiumValue) || premiumValue < 0) {
                premiumValue = 0;
            }

            var premiumLabel = premiumValue === 1
                ? previewAttributes.label_premium_singular
                : previewAttributes.label_premium_plural;

            if (!premiumLabel) {
                premiumLabel = previewAttributes.label_premium;
            }

            var premiumChildren = [];
            if (!hideIcons) {
                premiumChildren.push(createElement('span', { className: 'discord-icon' }, previewAttributes.icon_premium));
            }

            premiumChildren.push(createElement('span', {
                className: 'discord-number',
                role: 'status',
                'aria-live': 'polite'
            }, formatStaticNumber(premiumValue)));

            premiumChildren.push(createElement('span', { className: premiumLabelClasses.join(' ') },
                createElement('span', { className: 'discord-label-text' }, premiumLabel)
            ));

            statsWrapperChildren.push(createElementWithChildren('div', {
                className: 'discord-stat discord-premium-subscriptions',
                'data-role': 'discord-premium-subscriptions',
                'data-label-premium': previewAttributes.label_premium,
                'data-label-premium-singular': previewAttributes.label_premium_singular,
                'data-label-premium-plural': previewAttributes.label_premium_plural,
                'data-placeholder': '0',
                'data-value': premiumValue
            }, premiumChildren));
        }

        statsMainChildren.push(createElementWithChildren('div', { className: 'discord-stats-wrapper' }, statsWrapperChildren));

        if (showDiscordIcon && logoPosition === 'right') {
            statsMainChildren.push(createLogoElement('right'));
        }

        if (previewAttributes.cta_enabled && previewAttributes.cta_url) {
            statsMainChildren.push(createElement('div', { className: 'discord-cta', 'data-role': 'discord-cta' },
                createElement('a', {
                    className: 'discord-cta-button discord-cta-button--' + sanitizeClassName(previewAttributes.cta_style || 'solid'),
                    href: previewAttributes.cta_url,
                    target: previewAttributes.cta_new_tab ? '_blank' : null,
                    rel: previewAttributes.cta_new_tab ? 'noopener noreferrer' : null,
                    title: previewAttributes.cta_tooltip || null,
                    'aria-label': previewAttributes.cta_tooltip || null
                }, createElement('span', { className: 'discord-cta-button__label' }, previewAttributes.cta_label || __('Rejoindre la communauté', 'discord-bot-jlg')))
            ));
        }

        children.push(createElementWithChildren('div', { className: 'discord-stats-main' }, statsMainChildren));

        if (previewAttributes.invite_url) {
            var inviteButtonClasses = ['discord-invite-button', 'wp-element-button'];

            if (previewAttributes.compact) {
                inviteButtonClasses.push('discord-invite-button--compact');
            }

            children.push(createElement('div', { className: 'discord-invite' },
                createElement('a', {
                    className: inviteButtonClasses.join(' '),
                    href: previewAttributes.invite_url,
                    target: '_blank',
                    rel: 'noopener noreferrer nofollow'
                }, createElement('span', { className: 'discord-invite-button__label' }, previewAttributes.invite_label || __('Rejoindre le serveur', 'discord-bot-jlg')))
            ));
        }

        return createElementWithChildren('div', containerProps, children);
    }

    function attributesToShortcode(attributes) {
        var pairs = [];

        for (var key in defaultAttributes) {
            if (!Object.prototype.hasOwnProperty.call(defaultAttributes, key)) {
                continue;
            }

            var defaultValue = defaultAttributes[key];
            var value = Object.prototype.hasOwnProperty.call(attributes, key) ? attributes[key] : defaultValue;

            if (value === defaultValue || value === '' || value === undefined || value === null) {
                continue;
            }

            var normalized = typeof value === 'boolean' ? (value ? 'true' : 'false') : String(value);

            if (key === 'refresh_interval') {
                normalized = String(normalizeRefreshInterval(normalized));
            }

            if (normalized === '') {
                continue;
            }

            normalized = normalized.replace(/"/g, '&quot;');
            pairs.push(key + '="' + normalized + '"');
        }

        var normalizedClassName = '';

        if (
            attributes
            && Object.prototype.hasOwnProperty.call(attributes, 'className')
            && attributes.className
        ) {
            normalizedClassName = String(attributes.className);
        }

        if (
            !normalizedClassName
            && attributes
            && Object.prototype.hasOwnProperty.call(attributes, 'class')
            && attributes.class
        ) {
            normalizedClassName = String(attributes.class);
        }

        if (normalizedClassName) {
            var sanitizedClassName = normalizedClassName.trim();

            if (sanitizedClassName) {
                pairs.push('className="' + sanitizedClassName.replace(/"/g, '&quot;') + '"');
            }
        }

        return '[discord_stats' + (pairs.length ? ' ' + pairs.join(' ') : '') + ']';
    }

    function updateAttribute(setAttributes, name) {
        return function (value) {
            var newValue = value;

            if (name === 'refresh_interval') {
                newValue = String(normalizeRefreshInterval(value));
            } else if (name === 'avatar_size') {
                newValue = normalizeAvatarSize(value);
            } else if (typeof defaultAttributes[name] === 'boolean') {
                newValue = !!value;
            }

            var update = {};
            update[name] = newValue;
            setAttributes(update);
        };
    }

    function updateColorAttribute(setAttributes, name) {
        return function (value) {
            var newValue = value;

            if (!newValue || typeof newValue !== 'string') {
                newValue = '';
            }

            var update = {};
            update[name] = newValue;
            setAttributes(update);
        };
    }

    registerBlockType(blockName, {
        edit: function (props) {
            var attributes = props.attributes || {};
            var setAttributes = props.setAttributes;
            var blockProps = useBlockProps
                ? useBlockProps()
                : { className: props.className || '' };

            if (!blockProps) {
                blockProps = {};
            }

            var hasClassNameAttribute = Object.prototype.hasOwnProperty.call(attributes, 'className');

            if (
                setAttributes
                && attributes.class
                && (!hasClassNameAttribute || typeof attributes.className === 'undefined')
            ) {
                setAttributes({ className: attributes.class });
            }

            var hasUseState = typeof useState === 'function';
            var defaultPreviewRenderer = renderStaticPreview;
            var stateTuple = hasUseState
                ? useState(defaultPreviewRenderer)
                : [defaultPreviewRenderer, function () {}];
            var previewRenderer = stateTuple[0];
            var setPreviewRenderer = stateTuple[1];
            var errorSignatureTuple = hasUseState
                ? useState(null)
                : [null, function () {}];
            var lastErrorSignature = errorSignatureTuple[0];
            var setLastErrorSignature = errorSignatureTuple[1];
            var canUseDynamicPreview = hasUseState && !!ServerSideRender;
            var hasUseEffect = typeof useEffect === 'function';
            if (typeof previewRenderer !== 'function') {
                previewRenderer = defaultPreviewRenderer;
            }
            var isDynamicPreview = canUseDynamicPreview && previewRenderer === ServerSideRender;

            if (!isDynamicPreview && previewRenderer !== defaultPreviewRenderer) {
                previewRenderer = defaultPreviewRenderer;
            }

            var trimmedProfile = typeof attributes.profile === 'string'
                ? attributes.profile.trim()
                : '';
            var trimmedServerId = typeof attributes.server_id === 'string'
                ? attributes.server_id.trim()
                : '';
            var trimmedToken = typeof attributes.bot_token === 'string'
                ? attributes.bot_token.trim()
                : '';
            var credentialsSignature = [trimmedProfile, trimmedServerId, trimmedToken].join('|');
            var hasProfileCredentials = !!trimmedProfile;
            var hasManualCredentials = !!trimmedServerId && !!trimmedToken;
            var hasCredentials = hasProfileCredentials || hasManualCredentials;

            if (hasUseEffect && typeof setPreviewRenderer === 'function') {
                useEffect(function () {
                    if (!canUseDynamicPreview) {
                        if (previewRenderer === ServerSideRender) {
                            setPreviewRenderer(function () {
                                return defaultPreviewRenderer;
                            });
                        }

                        if (typeof setLastErrorSignature === 'function' && lastErrorSignature) {
                            setLastErrorSignature(function () {
                                return null;
                            });
                        }

                        return;
                    }

                    if (typeof setLastErrorSignature === 'function'
                        && lastErrorSignature
                        && lastErrorSignature !== credentialsSignature
                    ) {
                        setLastErrorSignature(function () {
                            return null;
                        });
                    }

                    if (!hasCredentials) {
                        if (previewRenderer === ServerSideRender) {
                            setPreviewRenderer(function () {
                                return defaultPreviewRenderer;
                            });
                        }

                        return;
                    }

                    if (lastErrorSignature && lastErrorSignature === credentialsSignature) {
                        return;
                    }

                    if (hasCredentials && previewRenderer !== ServerSideRender) {
                        setPreviewRenderer(function () {
                            return ServerSideRender;
                        });
                    }
                }, [
                    canUseDynamicPreview,
                    ServerSideRender,
                    credentialsSignature,
                    hasCredentials,
                    lastErrorSignature,
                    previewRenderer
                ]);
            }

            var LoadingPlaceholder = function () {
                var skeletonCards = [0, 1, 2].map(function (index) {
                    return createElement(
                        'div',
                        { className: 'discord-bot-jlg-preview-loading__card', key: 'loading-card-' + index },
                        createElement('div', { className: 'discord-bot-jlg-preview-loading__icon discord-bot-jlg-preview-loading__shimmer' }),
                        createElement(
                            'div',
                            { className: 'discord-bot-jlg-preview-loading__card-lines' },
                            createElement('div', { className: 'discord-bot-jlg-preview-loading__line discord-bot-jlg-preview-loading__line--short discord-bot-jlg-preview-loading__shimmer' }),
                            createElement('div', { className: 'discord-bot-jlg-preview-loading__line discord-bot-jlg-preview-loading__shimmer' })
                        )
                    );
                });

                return createElement(
                    'div',
                    {
                        className: 'discord-bot-jlg-preview-loading',
                        role: 'status',
                        'aria-live': 'polite'
                    },
                    createElement('span', { className: 'screen-reader-text' }, __('Chargement de l\'aperçu dynamique…', 'discord-bot-jlg')),
                    createElement(
                        'div',
                        { className: 'discord-bot-jlg-preview-loading__header' },
                        createElement('div', { className: 'discord-bot-jlg-preview-loading__avatar discord-bot-jlg-preview-loading__shimmer' }),
                        createElement(
                            'div',
                            { className: 'discord-bot-jlg-preview-loading__titles' },
                            createElement('div', { className: 'discord-bot-jlg-preview-loading__title discord-bot-jlg-preview-loading__shimmer' }),
                            createElement('div', { className: 'discord-bot-jlg-preview-loading__subtitle discord-bot-jlg-preview-loading__shimmer' })
                        )
                    ),
                    createElement(
                        'div',
                        { className: 'discord-bot-jlg-preview-loading__cards' },
                        skeletonCards
                    ),
                    createElement('div', { className: 'discord-bot-jlg-preview-loading__cta discord-bot-jlg-preview-loading__shimmer' })
                );
            };

            var ErrorPlaceholder = function () {
                if (hasUseEffect && typeof setPreviewRenderer === 'function') {
                    useEffect(function () {
                        setPreviewRenderer(function () {
                            return defaultPreviewRenderer;
                        });

                        if (typeof setLastErrorSignature === 'function') {
                            setLastErrorSignature(function () {
                                return credentialsSignature;
                            });
                        }
                    }, []);
                } else if (typeof setPreviewRenderer === 'function') {
                    setTimeout(function () {
                        setPreviewRenderer(function () {
                            return defaultPreviewRenderer;
                        });

                        if (typeof setLastErrorSignature === 'function') {
                            setLastErrorSignature(function () {
                                return credentialsSignature;
                            });
                        }
                    }, 0);
                }

                return createElement(
                    'div',
                    { className: 'discord-bot-jlg-preview-error' },
                    createElement('p', { className: 'discord-bot-jlg-preview-error__title' },
                        __('Impossible de charger l\'aperçu dynamique pour le moment.', 'discord-bot-jlg')
                    ),
                    createElement('p', { className: 'discord-bot-jlg-preview-error__description' },
                        __('L\'API ne répond pas ou a retourné une erreur inattendue. Un aperçu statique est affiché ci-dessous.', 'discord-bot-jlg')
                    ),
                    createElement(
                        'div',
                        { className: 'discord-bot-jlg-preview-error__fallback' },
                        defaultPreviewRenderer(attributes)
                    )
                );
            };

            var preview = (ServerSideRender && isDynamicPreview)
                ? createElement(ServerSideRender, {
                    block: blockName,
                    attributes: attributes,
                    LoadingResponsePlaceholder: LoadingPlaceholder,
                    ErrorResponsePlaceholder: ErrorPlaceholder,
                    EmptyResponsePlaceholder: function () {
                        return createElement('div', { className: 'discord-bot-jlg-preview-error' },
                            createElement('p', null, __('Aucun aperçu dynamique disponible pour le moment.', 'discord-bot-jlg'))
                        );
                    }
                })
                : previewRenderer(attributes);

            var colorPanel = null;

            if (ColorPalette) {
                var colorSettings = [
                    {
                        value: attributes.stat_bg_color,
                        onChange: updateColorAttribute(setAttributes, 'stat_bg_color'),
                        label: __('Fond des cartes', 'discord-bot-jlg')
                    },
                    {
                        value: attributes.stat_text_color,
                        onChange: updateColorAttribute(setAttributes, 'stat_text_color'),
                        label: __('Texte des cartes', 'discord-bot-jlg')
                    },
                    {
                        value: attributes.accent_color,
                        onChange: updateColorAttribute(setAttributes, 'accent_color'),
                        label: __('Couleur principale (bouton/logo)', 'discord-bot-jlg')
                    },
                    {
                        value: attributes.accent_color_alt,
                        onChange: updateColorAttribute(setAttributes, 'accent_color_alt'),
                        label: __('Couleur secondaire du bouton', 'discord-bot-jlg')
                    },
                    {
                        value: attributes.accent_text_color,
                        onChange: updateColorAttribute(setAttributes, 'accent_text_color'),
                        label: __('Texte du bouton', 'discord-bot-jlg')
                    }
                ];

                if (PanelColorSettings) {
                    colorPanel = createElement(PanelColorSettings, {
                        title: __('Couleurs', 'discord-bot-jlg'),
                        initialOpen: false,
                        colorSettings: colorSettings
                    });
                } else {
                    var fallbackControls = colorSettings.map(function (setting, index) {
                        if (BaseControl) {
                            return createElement(
                                BaseControl,
                                {
                                    label: setting.label,
                                    key: 'color-control-' + index
                                },
                                createElement(ColorPalette, {
                                    value: setting.value,
                                    onChange: setting.onChange
                                })
                            );
                        }

                        return createElement(
                            'div',
                            { className: 'discord-bot-jlg-color-control', key: 'color-control-' + index },
                            createElement('p', null, setting.label),
                            createElement(ColorPalette, {
                                value: setting.value,
                                onChange: setting.onChange
                            })
                        );
                    });

                    colorPanel = createElement(
                        PanelBody,
                        { title: __('Couleurs', 'discord-bot-jlg'), initialOpen: false },
                        fallbackControls
                    );
                }
            }

            var hasToolsPanelSupport = !!(ToolsPanel && ToolsPanelItem);

            function renderToggleControlFromConfig(config) {
                if (!config || !config.attribute) {
                    return null;
                }

                var toggleProps = {
                    key: config.attribute,
                    label: config.label,
                    checked: !!attributes[config.attribute],
                    onChange: updateAttribute(setAttributes, config.attribute)
                };

                if (config.help) {
                    toggleProps.help = config.help;
                }

                return createElement(ToggleControl, toggleProps);
            }

            function resetGroupAttributes(configs) {
                if (!Array.isArray(configs)) {
                    return;
                }

                var update = {};
                var hasUpdates = false;

                for (var i = 0; i < configs.length; i++) {
                    var item = configs[i];

                    if (!item || !item.attribute) {
                        continue;
                    }

                    var attributeName = item.attribute;
                    var defaultValue = Object.prototype.hasOwnProperty.call(item, 'defaultValue')
                        ? item.defaultValue
                        : defaultAttributes[attributeName];

                    update[attributeName] = defaultValue;
                    hasUpdates = true;
                }

                if (hasUpdates) {
                    setAttributes(update);
                }
            }

            function renderToggleGroup(label, configs, groupKey) {
                if (!Array.isArray(configs) || !configs.length) {
                    return null;
                }

                if (hasToolsPanelSupport) {
                    var items = configs.map(function (config) {
                        if (!config || !config.attribute) {
                            return null;
                        }

                        var attributeName = config.attribute;
                        var defaultValue = Object.prototype.hasOwnProperty.call(config, 'defaultValue')
                            ? config.defaultValue
                            : defaultAttributes[attributeName];

                        return createElement(
                            ToolsPanelItem,
                            {
                                key: attributeName,
                                hasValue: function () {
                                    var normalizedDefault = !!defaultValue;
                                    var hasAttribute = Object.prototype.hasOwnProperty.call(attributes, attributeName);
                                    var rawValue = hasAttribute ? attributes[attributeName] : defaultValue;
                                    var currentValue = !!rawValue;

                                    return currentValue !== normalizedDefault;
                                },
                                label: config.itemLabel || config.label,
                                isShownByDefault: true,
                                onDeselect: function () {
                                    var deselectUpdate = {};
                                    deselectUpdate[attributeName] = defaultValue;
                                    setAttributes(deselectUpdate);
                                }
                            },
                            renderToggleControlFromConfig(config)
                        );
                    });

                    var filteredItems = [];

                    for (var itemIndex = 0; itemIndex < items.length; itemIndex++) {
                        if (items[itemIndex]) {
                            filteredItems.push(items[itemIndex]);
                        }
                    }

                    if (!filteredItems.length) {
                        return null;
                    }

                    return createElement(
                        ToolsPanel,
                        {
                            key: groupKey,
                            label: label,
                            panelId: 'discord-bot-jlg-' + groupKey,
                            resetAll: function () {
                                resetGroupAttributes(configs);
                            }
                        },
                        filteredItems
                    );
                }

                var stackChildren = [];

                for (var index = 0; index < configs.length; index++) {
                    var toggle = renderToggleControlFromConfig(configs[index]);

                    if (!toggle) {
                        continue;
                    }

                    stackChildren.push(createElement(
                        'div',
                        {
                            key: (configs[index] && configs[index].attribute) || 'toggle-' + index,
                            className: 'discord-bot-jlg-toggle-group__item'
                        },
                        toggle
                    ));
                }

                if (!stackChildren.length) {
                    return null;
                }

                var stackWrapperProps = {
                    className: 'discord-bot-jlg-toggle-group__controls'
                };

                var stackWrapper;

                if (VStack) {
                    var vStackProps = {};

                    for (var key in stackWrapperProps) {
                        if (Object.prototype.hasOwnProperty.call(stackWrapperProps, key)) {
                            vStackProps[key] = stackWrapperProps[key];
                        }
                    }

                    vStackProps.spacing = 2;

                    stackWrapper = createElement(
                        VStack,
                        vStackProps,
                        stackChildren
                    );
                } else {
                    var fallbackProps = {};

                    for (var propKey in stackWrapperProps) {
                        if (Object.prototype.hasOwnProperty.call(stackWrapperProps, propKey)) {
                            fallbackProps[propKey] = stackWrapperProps[propKey];
                        }
                    }

                    fallbackProps.style = {
                        display: 'grid',
                        rowGap: '12px'
                    };

                    stackWrapper = createElement(
                        'div',
                        fallbackProps,
                        stackChildren
                    );
                }

                return createElement(
                    'div',
                    {
                        key: groupKey,
                        className: 'discord-bot-jlg-toggle-group'
                    },
                    createElement(
                        'span',
                        {
                            className: 'discord-bot-jlg-toggle-group__label components-base-control__label'
                        },
                        label
                    ),
                    stackWrapper
                );
            }

            var toolbarControls = null;
            var hasToolbarSupport = !!(BlockControls && ToolbarGroup && ToolbarButton);

            if (hasToolbarSupport) {
                var setLayoutAttribute = updateAttribute(setAttributes, 'layout');
                var setThemeAttribute = updateAttribute(setAttributes, 'theme');
                var setCompactAttribute = updateAttribute(setAttributes, 'compact');
                var setServerAvatarAttribute = updateAttribute(setAttributes, 'show_server_avatar');
                var setServerNameAttribute = updateAttribute(setAttributes, 'show_server_name');

                var normalizedLayout = (attributes.layout || defaultAttributes.layout || 'horizontal').toString().toLowerCase();
                var layoutIsVertical = normalizedLayout === 'vertical';
                var currentThemeValue = attributes.theme || defaultAttributes.theme || 'discord';
                var currentThemeOption = null;
                var nextThemeValue = currentThemeValue;

                if (Array.isArray(themeOptions) && themeOptions.length) {
                    for (var themeIndex = 0; themeIndex < themeOptions.length; themeIndex++) {
                        var option = themeOptions[themeIndex];

                        if (!option || typeof option.value === 'undefined') {
                            continue;
                        }

                        if (option.value === currentThemeValue) {
                            currentThemeOption = option;
                            var nextIndex = (themeIndex + 1) % themeOptions.length;
                            nextThemeValue = themeOptions[nextIndex] && themeOptions[nextIndex].value
                                ? themeOptions[nextIndex].value
                                : currentThemeValue;
                            break;
                        }
                    }

                    if (!currentThemeOption) {
                        currentThemeOption = themeOptions[0];
                        nextThemeValue = themeOptions.length > 1 && themeOptions[1]
                            ? themeOptions[1].value
                            : currentThemeOption.value;
                    }
                }

                var toolbarButtons = [];

                toolbarButtons.push(createElement(ToolbarButton, {
                    key: 'discord-toggle-layout',
                    icon: 'leftright',
                    label: __('Basculer horizontal/vertical', 'discord-bot-jlg'),
                    showTooltip: true,
                    onClick: function () {
                        setLayoutAttribute(layoutIsVertical ? 'horizontal' : 'vertical');
                    },
                    isPressed: layoutIsVertical
                }));

                if (currentThemeOption && Array.isArray(themeOptions) && themeOptions.length > 1 && typeof nextThemeValue !== 'undefined') {
                    toolbarButtons.push(createElement(ToolbarButton, {
                        key: 'discord-cycle-theme',
                        icon: 'admin-appearance',
                        label: __('Changer de thème', 'discord-bot-jlg') + ' · ' + (currentThemeOption.label || currentThemeOption.value),
                        showTooltip: true,
                        onClick: function () {
                            setThemeAttribute(nextThemeValue);
                        }
                    }));
                }

                var isCompact = !!attributes.compact;
                toolbarButtons.push(createElement(ToolbarButton, {
                    key: 'discord-toggle-compact',
                    icon: 'editor-contract',
                    label: isCompact
                        ? __('Désactiver le mode compact', 'discord-bot-jlg')
                        : __('Activer le mode compact', 'discord-bot-jlg'),
                    showTooltip: true,
                    onClick: function () {
                        setCompactAttribute(!isCompact);
                    },
                    isPressed: isCompact
                }));

                var showServerAvatar = !!attributes.show_server_avatar;
                toolbarButtons.push(createElement(ToolbarButton, {
                    key: 'discord-toggle-server-avatar',
                    icon: 'id-alt',
                    label: showServerAvatar
                        ? __('Masquer l\'avatar du serveur', 'discord-bot-jlg')
                        : __('Afficher l\'avatar du serveur', 'discord-bot-jlg'),
                    showTooltip: true,
                    onClick: function () {
                        setServerAvatarAttribute(!showServerAvatar);
                    },
                    isPressed: showServerAvatar
                }));

                var showServerName = !!attributes.show_server_name;
                toolbarButtons.push(createElement(ToolbarButton, {
                    key: 'discord-toggle-server-name',
                    icon: 'admin-users',
                    label: showServerName
                        ? __('Masquer le nom du serveur', 'discord-bot-jlg')
                        : __('Afficher le nom du serveur', 'discord-bot-jlg'),
                    showTooltip: true,
                    onClick: function () {
                        setServerNameAttribute(!showServerName);
                    },
                    isPressed: showServerName
                }));

                if (toolbarButtons.length) {
                    toolbarControls = createElement(
                        BlockControls,
                        null,
                        createElement(
                            ToolbarGroup,
                            { label: __('Options rapides Discord', 'discord-bot-jlg') },
                            toolbarButtons
                        )
                    );
                }
            }

            return createElement(
                Fragment,
                null,
                toolbarControls,
                createElement(
                    InspectorControls,
                    null,
                    createElement(
                        PanelBody,
                        { title: __('Paramètres essentiels', 'discord-bot-jlg'), initialOpen: true },
                        createElement(ToggleControl, {
                            label: __('Activer l\'aperçu dynamique', 'discord-bot-jlg'),
                            checked: !!isDynamicPreview,
                            onChange: function (value) {
                                if (!canUseDynamicPreview) {
                                    setPreviewRenderer(function () {
                                        return defaultPreviewRenderer;
                                    });
                                    return;
                                }

                                setPreviewRenderer(function () {
                                    return value ? ServerSideRender : defaultPreviewRenderer;
                                });
                            },
                            disabled: !canUseDynamicPreview,
                            help: canUseDynamicPreview
                                ? __('Basculer entre l\'aperçu statique et le rendu dynamique fourni par l\'API. L\'aperçu en direct s\'active automatiquement dès qu\'un profil ou un token valide est détecté.', 'discord-bot-jlg')
                                : __('L\'aperçu dynamique nécessite la prise en charge du rendu côté serveur.', 'discord-bot-jlg')
                        }),
                        createElement(SelectControl, {
                            label: __('Disposition', 'discord-bot-jlg'),
                            value: attributes.layout,
                            options: layoutOptions,
                            onChange: updateAttribute(setAttributes, 'layout')
                        }),
                        createElement(SelectControl, {
                            label: __('Thème', 'discord-bot-jlg'),
                            value: attributes.theme,
                            options: themeOptions,
                            onChange: updateAttribute(setAttributes, 'theme')
                        }),
                        createElement(SelectControl, {
                            label: __('Alignement', 'discord-bot-jlg'),
                            value: attributes.align,
                            options: alignOptions,
                            onChange: updateAttribute(setAttributes, 'align')
                        }),
                        createElement(TextControl, {
                            label: __('Largeur (CSS)', 'discord-bot-jlg'),
                            value: attributes.width,
                            onChange: updateAttribute(setAttributes, 'width'),
                            help: __('Utilisez une valeur CSS valide, ex. 100% ou 320px.', 'discord-bot-jlg')
                        }),
                        renderToggleControlFromConfig({
                            attribute: 'show_title',
                            label: __('Afficher le titre', 'discord-bot-jlg')
                        }),
                        renderToggleControlFromConfig({
                            attribute: 'compact',
                            label: __('Mode compact', 'discord-bot-jlg')
                        }),
                        !!attributes.show_title && createElement(TextControl, {
                            label: __('Titre personnalisé', 'discord-bot-jlg'),
                            value: attributes.title,
                            onChange: updateAttribute(setAttributes, 'title')
                        }),
                    ),
                    createElement(
                        PanelBody,
                        { title: __('Affichage & animation', 'discord-bot-jlg'), initialOpen: false },
                        renderToggleGroup(
                            __('Affichage des métriques', 'discord-bot-jlg'),
                            metricsToggleConfigs,
                            'metrics'
                        ),
                        !!attributes.show_sparkline && createElement(SelectControl, {
                            label: __('Métrique pour la sparkline', 'discord-bot-jlg'),
                            value: attributes.sparkline_metric || defaultAttributes.sparkline_metric,
                            options: sparklineMetricOptions,
                            onChange: updateAttribute(setAttributes, 'sparkline_metric')
                        }),
                        !!attributes.show_sparkline && createElement(SparklineDaysControl, {
                            label: __('Fenêtre de calcul (jours)', 'discord-bot-jlg'),
                            value: Math.max(3, Math.min(30, parseInt(attributes.sparkline_days, 10) || defaultAttributes.sparkline_days)),
                            min: 3,
                            max: 30,
                            step: 1,
                            onChange: function (value) {
                                var parsed = parseInt(value, 10);
                                if (isNaN(parsed)) {
                                    parsed = defaultAttributes.sparkline_days;
                                }

                                parsed = Math.max(3, Math.min(30, parsed));
                                setAttributes({ sparkline_days: parsed });
                            }
                        }),
                        renderToggleGroup(
                            __('Options d\'animation', 'discord-bot-jlg'),
                            animationToggleConfigs,
                            'animation'
                        ),
                        !!attributes.refresh && createElement(RefreshIntervalControl, {
                            label: __('Intervalle de rafraîchissement (secondes)', 'discord-bot-jlg'),
                            value: normalizeRefreshInterval(attributes.refresh_interval),
                            onChange: updateAttribute(setAttributes, 'refresh_interval'),
                            min: REFRESH_INTERVAL_MIN,
                            max: REFRESH_INTERVAL_MAX,
                            step: 5,
                            help: __('Minimum 10 secondes afin d’éviter les limitations de Discord.', 'discord-bot-jlg')
                        })
                    ),
                    createElement(
                        PanelBody,
                        { title: __('Connexion au serveur', 'discord-bot-jlg'), initialOpen: false },
                        createElement(SelectControl, {
                            label: __('Profil enregistré', 'discord-bot-jlg'),
                            value: attributes.profile || '',
                            options: profileOptions,
                            onChange: updateAttribute(setAttributes, 'profile'),
                            help: profileOptions.length > 1
                                ? __('Sélectionnez un profil sauvegardé pour utiliser ses identifiants.', 'discord-bot-jlg')
                                : __('Gérez vos profils depuis la page d’administration du plugin.', 'discord-bot-jlg')
                        }),
                        createElement(TextControl, {
                            label: __('ID du serveur (prioritaire)', 'discord-bot-jlg'),
                            value: attributes.server_id || '',
                            onChange: updateAttribute(setAttributes, 'server_id'),
                            help: __('Remplace l’ID défini globalement ou dans le profil sélectionné.', 'discord-bot-jlg')
                        }),
                        createElement(TextControl, {
                            label: __('Token du bot (prioritaire)', 'discord-bot-jlg'),
                            type: 'password',
                            value: attributes.bot_token || '',
                            onChange: updateAttribute(setAttributes, 'bot_token'),
                            help: __('Laisser vide pour conserver le token fourni par le profil ou la configuration globale.', 'discord-bot-jlg')
                        })
                    ),
                    createElement(
                        PanelBody,
                        { title: __('Apparence avancée', 'discord-bot-jlg'), initialOpen: false },
                        renderToggleControlFromConfig({
                            attribute: 'show_discord_icon',
                            label: __('Afficher l\'icône Discord', 'discord-bot-jlg')
                        }),
                        !!attributes.show_discord_icon && createElement(SelectControl, {
                            label: __('Position de l\'icône', 'discord-bot-jlg'),
                            value: attributes.discord_icon_position,
                            options: iconPositionOptions,
                            onChange: updateAttribute(setAttributes, 'discord_icon_position')
                        }),
                        createElement(RangeControl, {
                            label: __('Rayon des bords (px)', 'discord-bot-jlg'),
                            value: attributes.border_radius,
                            onChange: updateAttribute(setAttributes, 'border_radius'),
                            min: 0,
                            max: 50
                        }),
                        createElement(RangeControl, {
                            label: __('Espacement interne (px)', 'discord-bot-jlg'),
                            value: attributes.padding,
                            onChange: updateAttribute(setAttributes, 'padding'),
                            min: 0,
                            max: 60
                        }),
                        createElement(RangeControl, {
                            label: __('Espacement entre les éléments (px)', 'discord-bot-jlg'),
                            value: attributes.gap,
                            onChange: updateAttribute(setAttributes, 'gap'),
                            min: 0,
                            max: 80
                        })
                    ),
                    colorPanel,
                    createElement(
                        PanelBody,
                        { title: __('Libellés et icônes', 'discord-bot-jlg'), initialOpen: false },
                        createElement(TextControl, {
                            label: __('Icône "En ligne"', 'discord-bot-jlg'),
                            value: attributes.icon_online,
                            onChange: updateAttribute(setAttributes, 'icon_online')
                        }),
                        createElement(TextControl, {
                            label: __('Icône "Membres"', 'discord-bot-jlg'),
                            value: attributes.icon_total,
                            onChange: updateAttribute(setAttributes, 'icon_total')
                        }),
                        createElement(TextControl, {
                            label: __('Libellé "En ligne"', 'discord-bot-jlg'),
                            value: attributes.label_online,
                            onChange: updateAttribute(setAttributes, 'label_online'),
                            placeholder: __('En ligne', 'discord-bot-jlg')
                        }),
                        createElement(TextControl, {
                            label: __('Libellé "Membres"', 'discord-bot-jlg'),
                            value: attributes.label_total,
                            onChange: updateAttribute(setAttributes, 'label_total'),
                            placeholder: __('Membres', 'discord-bot-jlg')
                        }),
                        renderToggleControlFromConfig({
                            attribute: 'hide_labels',
                            label: __('Masquer les libellés', 'discord-bot-jlg')
                        }),
                        renderToggleControlFromConfig({
                            attribute: 'hide_icons',
                            label: __('Masquer les icônes', 'discord-bot-jlg')
                        })
                    ),
                    createElement(
                        PanelBody,
                        { title: __('Identité du serveur', 'discord-bot-jlg'), initialOpen: false },
                        renderToggleControlFromConfig({
                            attribute: 'show_server_name',
                            label: __('Afficher le nom du serveur', 'discord-bot-jlg')
                        }),
                        renderToggleControlFromConfig({
                            attribute: 'show_server_avatar',
                            label: __('Afficher l\'avatar du serveur', 'discord-bot-jlg')
                        }),
                        !!attributes.show_server_avatar && createElement(NumberControl, {
                            label: __('Taille de l\'avatar (px)', 'discord-bot-jlg'),
                            value: attributes.avatar_size,
                            min: 16,
                            max: 4096,
                            step: 16,
                            onChange: function (value) {
                                updateAttribute(setAttributes, 'avatar_size')(value);
                            },
                            help: __('Utilisez une puissance de deux (ex. 128, 256, 512) pour une image nette.', 'discord-bot-jlg')
                        })
                    ),
                    createElement(
                        PanelBody,
                        { title: __('Bouton d\'action', 'discord-bot-jlg'), initialOpen: false },
                        renderToggleControlFromConfig({
                            attribute: 'cta_enabled',
                            label: __('Afficher le bouton d\'action', 'discord-bot-jlg')
                        }),
                        !!attributes.cta_enabled && createElement(TextControl, {
                            label: __('Libellé du bouton', 'discord-bot-jlg'),
                            value: attributes.cta_label,
                            onChange: updateAttribute(setAttributes, 'cta_label'),
                            placeholder: __('Rejoindre la communauté', 'discord-bot-jlg')
                        }),
                        !!attributes.cta_enabled && createElement(TextControl, {
                            label: __('URL du bouton', 'discord-bot-jlg'),
                            value: attributes.cta_url,
                            onChange: updateAttribute(setAttributes, 'cta_url'),
                            type: 'url',
                            placeholder: 'https://discord.gg/xxxx',
                            help: __('Incluez l’URL complète (avec https://).', 'discord-bot-jlg')
                        }),
                        !!attributes.cta_enabled && createElement(SelectControl, {
                            label: __('Style du bouton', 'discord-bot-jlg'),
                            value: attributes.cta_style,
                            options: ctaStyleOptions,
                            onChange: updateAttribute(setAttributes, 'cta_style')
                        }),
                        !!attributes.cta_enabled && renderToggleControlFromConfig({
                            attribute: 'cta_new_tab',
                            label: __('Ouvrir dans un nouvel onglet', 'discord-bot-jlg'),
                            help: __('Ajoute les attributs target="_blank" et rel="noopener".', 'discord-bot-jlg')
                        }),
                        !!attributes.cta_enabled && createElement(TextControl, {
                            label: __('Info-bulle (optionnel)', 'discord-bot-jlg'),
                            value: attributes.cta_tooltip,
                            onChange: updateAttribute(setAttributes, 'cta_tooltip'),
                            placeholder: __('Découvrir le serveur Discord', 'discord-bot-jlg')
                        })
                    ),
                    createElement(
                        PanelBody,
                        { title: __('Invitation', 'discord-bot-jlg'), initialOpen: false },
                        createElement(TextControl, {
                            label: __('URL d\'invitation', 'discord-bot-jlg'),
                            value: attributes.invite_url,
                            onChange: updateAttribute(setAttributes, 'invite_url'),
                            type: 'url',
                            placeholder: 'https://discord.gg/xxxx'
                        }),
                        createElement(TextControl, {
                            label: __('Libellé du bouton', 'discord-bot-jlg'),
                            value: attributes.invite_label,
                            onChange: updateAttribute(setAttributes, 'invite_label'),
                            placeholder: __('Rejoindre le serveur', 'discord-bot-jlg')
                        })
                    ),
                    createElement(
                        PanelBody,
                        { title: __('Options développeur', 'discord-bot-jlg'), initialOpen: false },
                        renderToggleControlFromConfig({
                            attribute: 'demo',
                            label: __('Forcer le mode démo', 'discord-bot-jlg')
                        })
                    )
                ),
                createElement(
                    'div',
                    blockProps,
                    preview
                )
            );
        },
        save: function (props) {
            var attributes = (props && props.attributes) || {};
            return attributesToShortcode(attributes);
        }
    });
})(
    window.wp && window.wp.blocks,
    window.wp && window.wp.element,
    window.wp && window.wp.components,
    (window.wp && (window.wp.blockEditor || window.wp.editor)) || {},
    window.wp && window.wp.i18n,
    window.wp && window.wp.serverSideRender
);
