(function (blocks, element, components, blockEditor, i18n, serverSideRender) {
    if (!blocks || !element || !components || !blockEditor || !i18n) {
        return;
    }

    var registerBlockType = blocks.registerBlockType;
    var createElement = element.createElement;
    var Fragment = element.Fragment;
    var __ = i18n.__;
    var InspectorControls = blockEditor.InspectorControls;
    var useBlockProps = blockEditor.useBlockProps || function () { return {}; };
    var PanelBody = components.PanelBody;
    var PanelRow = components.PanelRow;
    var ToggleControl = components.ToggleControl;
    var TextControl = components.TextControl;
    var SelectControl = components.SelectControl;
    var RangeControl = components.RangeControl;
    var NumberControl = components.NumberControl || components.__experimentalNumberControl;
    var BaseControl = components.BaseControl;
    var PanelColorSettings = blockEditor.PanelColorSettings || blockEditor.__experimentalPanelColorSettings;
    var ColorPalette = (blockEditor && blockEditor.ColorPalette) || components.ColorPalette;
    var RefreshIntervalControl = NumberControl || RangeControl || TextControl;
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
        { label: __('Minimal', 'discord-bot-jlg'), value: 'minimal' }
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

    var defaultAttributes = {
        layout: 'horizontal',
        show_online: true,
        show_total: true,
        show_title: false,
        title: '',
        theme: 'discord',
        animated: true,
        refresh: false,
        refresh_interval: '60',
        compact: false,
        align: 'left',
        width: '',
        icon_online: '🟢',
        icon_total: '👥',
        label_online: 'En ligne',
        label_total: 'Membres',
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
        cta_tooltip: ''
    };

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
            server_avatar_base_url: STATIC_PREVIEW_AVATAR_BASE
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

            var preview = ServerSideRender
                ? createElement(ServerSideRender, {
                    block: blockName,
                    attributes: attributes
                })
                : renderStaticPreview(attributes);

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

            return createElement(
                Fragment,
                null,
                createElement(
                    InspectorControls,
                    null,
                    createElement(
                        PanelBody,
                        { title: __('Paramètres essentiels', 'discord-bot-jlg'), initialOpen: true },
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
                        createElement(
                            PanelRow,
                            null,
                            createElement(ToggleControl, {
                                label: __('Afficher les membres en ligne', 'discord-bot-jlg'),
                                checked: !!attributes.show_online,
                                onChange: updateAttribute(setAttributes, 'show_online')
                            }),
                            createElement(ToggleControl, {
                                label: __('Afficher le total des membres', 'discord-bot-jlg'),
                                checked: !!attributes.show_total,
                                onChange: updateAttribute(setAttributes, 'show_total')
                            })
                        ),
                        createElement(
                            PanelRow,
                            null,
                            createElement(ToggleControl, {
                                label: __('Afficher le titre', 'discord-bot-jlg'),
                                checked: !!attributes.show_title,
                                onChange: updateAttribute(setAttributes, 'show_title')
                            }),
                            createElement(ToggleControl, {
                                label: __('Mode compact', 'discord-bot-jlg'),
                                checked: !!attributes.compact,
                                onChange: updateAttribute(setAttributes, 'compact')
                            })
                        ),
                        !!attributes.show_title && createElement(TextControl, {
                            label: __('Titre personnalisé', 'discord-bot-jlg'),
                            value: attributes.title,
                            onChange: updateAttribute(setAttributes, 'title')
                        }),
                        createElement(
                            PanelRow,
                            null,
                            createElement(ToggleControl, {
                                label: __('Activer les animations', 'discord-bot-jlg'),
                                checked: !!attributes.animated,
                                onChange: updateAttribute(setAttributes, 'animated')
                            }),
                            createElement(ToggleControl, {
                                label: __('Rafraîchissement automatique', 'discord-bot-jlg'),
                                checked: !!attributes.refresh,
                                onChange: updateAttribute(setAttributes, 'refresh')
                            })
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
                        { title: __('Apparence avancée', 'discord-bot-jlg'), initialOpen: false },
                        createElement(
                            PanelRow,
                            null,
                            createElement(ToggleControl, {
                                label: __('Afficher l\'icône Discord', 'discord-bot-jlg'),
                                checked: !!attributes.show_discord_icon,
                                onChange: updateAttribute(setAttributes, 'show_discord_icon')
                            })
                        ),
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
                        createElement(
                            PanelRow,
                            null,
                            createElement(ToggleControl, {
                                label: __('Masquer les libellés', 'discord-bot-jlg'),
                                checked: !!attributes.hide_labels,
                                onChange: updateAttribute(setAttributes, 'hide_labels')
                            }),
                            createElement(ToggleControl, {
                                label: __('Masquer les icônes', 'discord-bot-jlg'),
                                checked: !!attributes.hide_icons,
                                onChange: updateAttribute(setAttributes, 'hide_icons')
                            })
                        )
                    ),
                    createElement(
                        PanelBody,
                        { title: __('Identité du serveur', 'discord-bot-jlg'), initialOpen: false },
                        createElement(
                            PanelRow,
                            null,
                            createElement(ToggleControl, {
                                label: __('Afficher le nom du serveur', 'discord-bot-jlg'),
                                checked: !!attributes.show_server_name,
                                onChange: updateAttribute(setAttributes, 'show_server_name')
                            }),
                            createElement(ToggleControl, {
                                label: __('Afficher l\'avatar du serveur', 'discord-bot-jlg'),
                                checked: !!attributes.show_server_avatar,
                                onChange: updateAttribute(setAttributes, 'show_server_avatar')
                            })
                        ),
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
                        createElement(
                            PanelRow,
                            null,
                            createElement(ToggleControl, {
                                label: __('Afficher le bouton d\'action', 'discord-bot-jlg'),
                                checked: !!attributes.cta_enabled,
                                onChange: updateAttribute(setAttributes, 'cta_enabled')
                            })
                        ),
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
                        !!attributes.cta_enabled && createElement(
                            PanelRow,
                            null,
                            createElement(ToggleControl, {
                                label: __('Ouvrir dans un nouvel onglet', 'discord-bot-jlg'),
                                checked: !!attributes.cta_new_tab,
                                onChange: updateAttribute(setAttributes, 'cta_new_tab'),
                                help: __('Ajoute les attributs target="_blank" et rel="noopener".', 'discord-bot-jlg')
                            })
                        ),
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
                        createElement(
                            PanelRow,
                            null,
                            createElement(ToggleControl, {
                                label: __('Forcer le mode démo', 'discord-bot-jlg'),
                                checked: !!attributes.demo,
                                onChange: updateAttribute(setAttributes, 'demo')
                            })
                        )
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
