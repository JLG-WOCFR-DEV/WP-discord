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
                : createElement('div', { className: 'discord-bot-jlg-block-placeholder' }, __('Prévisualisation indisponible.', 'discord-bot-jlg'));

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
