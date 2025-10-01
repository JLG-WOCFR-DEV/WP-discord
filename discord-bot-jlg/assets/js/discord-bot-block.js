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
    var ToggleControl = components.ToggleControl;
    var TextControl = components.TextControl;
    var SelectControl = components.SelectControl;
    var RangeControl = components.RangeControl;
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
        class: '',
        icon_online: '🟢',
        icon_total: '👥',
        label_online: 'En ligne',
        label_total: 'Membres',
        hide_labels: false,
        hide_icons: false,
        border_radius: 8,
        gap: 20,
        padding: 15,
        demo: false,
        show_discord_icon: false,
        discord_icon_position: 'left',
        show_server_name: false
    };

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
                var parsedInterval = parseInt(normalized, 10);
                var sanitizedInterval = Math.max(10, isNaN(parsedInterval) ? 60 : parsedInterval);
                normalized = String(sanitizedInterval);
            }

            if (normalized === '') {
                continue;
            }

            normalized = normalized.replace(/"/g, '&quot;');
            pairs.push(key + '="' + normalized + '"');
        }

        return '[discord_stats' + (pairs.length ? ' ' + pairs.join(' ') : '') + ']';
    }

    function updateAttribute(setAttributes, name) {
        return function (value) {
            var newValue = value;

            if (name === 'refresh_interval') {
                var parsed = parseInt(value, 10);
                var safeValue = Math.max(10, isNaN(parsed) ? 60 : parsed);
                newValue = String(safeValue);
            } else if (typeof defaultAttributes[name] === 'boolean') {
                newValue = !!value;
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
            var blockProps = useBlockProps ? useBlockProps() : {};

            var preview = ServerSideRender
                ? createElement(ServerSideRender, {
                    block: blockName,
                    attributes: attributes
                })
                : createElement('div', { className: 'discord-bot-jlg-block-placeholder' }, __('Prévisualisation indisponible.', 'discord-bot-jlg'));

            return createElement(
                Fragment,
                null,
                createElement(
                    InspectorControls,
                    null,
                    createElement(
                        PanelBody,
                        { title: __('Affichage', 'discord-bot-jlg'), initialOpen: true },
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
                        createElement(TextControl, {
                            label: __('Classe(s) supplémentaire(s)', 'discord-bot-jlg'),
                            value: attributes.class,
                            onChange: updateAttribute(setAttributes, 'class')
                        }),
                        createElement(ToggleControl, {
                            label: __('Mode compact', 'discord-bot-jlg'),
                            checked: !!attributes.compact,
                            onChange: updateAttribute(setAttributes, 'compact')
                        }),
                        createElement(ToggleControl, {
                            label: __('Activer les animations', 'discord-bot-jlg'),
                            checked: !!attributes.animated,
                            onChange: updateAttribute(setAttributes, 'animated')
                        }),
                        createElement(ToggleControl, {
                            label: __('Afficher l\'icône Discord', 'discord-bot-jlg'),
                            checked: !!attributes.show_discord_icon,
                            onChange: updateAttribute(setAttributes, 'show_discord_icon')
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
                    createElement(
                        PanelBody,
                        { title: __('Contenu', 'discord-bot-jlg'), initialOpen: false },
                        createElement(ToggleControl, {
                            label: __('Afficher les membres en ligne', 'discord-bot-jlg'),
                            checked: !!attributes.show_online,
                            onChange: updateAttribute(setAttributes, 'show_online')
                        }),
                        createElement(ToggleControl, {
                            label: __('Afficher le total des membres', 'discord-bot-jlg'),
                            checked: !!attributes.show_total,
                            onChange: updateAttribute(setAttributes, 'show_total')
                        }),
                        createElement(ToggleControl, {
                            label: __('Afficher le titre', 'discord-bot-jlg'),
                            checked: !!attributes.show_title,
                            onChange: updateAttribute(setAttributes, 'show_title')
                        }),
                        !!attributes.show_title && createElement(TextControl, {
                            label: __('Titre personnalisé', 'discord-bot-jlg'),
                            value: attributes.title,
                            onChange: updateAttribute(setAttributes, 'title')
                        }),
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
                        createElement(ToggleControl, {
                            label: __('Masquer les libellés', 'discord-bot-jlg'),
                            checked: !!attributes.hide_labels,
                            onChange: updateAttribute(setAttributes, 'hide_labels')
                        }),
                        createElement(ToggleControl, {
                            label: __('Masquer les icônes', 'discord-bot-jlg'),
                            checked: !!attributes.hide_icons,
                            onChange: updateAttribute(setAttributes, 'hide_icons')
                        }),
                        createElement(ToggleControl, {
                            label: __('Afficher le nom du serveur', 'discord-bot-jlg'),
                            checked: !!attributes.show_server_name,
                            onChange: updateAttribute(setAttributes, 'show_server_name')
                        })
                    ),
                    createElement(
                        PanelBody,
                        { title: __('Interactions', 'discord-bot-jlg'), initialOpen: false },
                        createElement(ToggleControl, {
                            label: __('Activer le rafraîchissement automatique', 'discord-bot-jlg'),
                            checked: !!attributes.refresh,
                            onChange: updateAttribute(setAttributes, 'refresh')
                        }),
                        !!attributes.refresh && createElement(RangeControl, {
                            label: __('Intervalle de rafraîchissement (secondes)', 'discord-bot-jlg'),
                            value: parseInt(attributes.refresh_interval, 10) || 60,
                            onChange: updateAttribute(setAttributes, 'refresh_interval'),
                            min: 10,
                            max: 3600,
                            step: 5,
                            help: __('Minimum 10 secondes afin d’éviter les limitations de Discord.', 'discord-bot-jlg')
                        }),
                        createElement(ToggleControl, {
                            label: __('Forcer le mode démo', 'discord-bot-jlg'),
                            checked: !!attributes.demo,
                            onChange: updateAttribute(setAttributes, 'demo')
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
