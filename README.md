# Discord Bot - JLG

Plugin WordPress permettant d'afficher les statistiques d'un serveur Discord.

- **Nom** : Discord Bot - JLG
- **Objectif** : afficher les statistiques d’un serveur Discord
- **Auteur** : Jérôme Le Gousse
- **Licence** : GPLv2

## Installation
1. Copier le dossier `discord-bot-jlg` dans `wp-content/plugins/`.
2. Activer le plugin via l’interface d’administration WordPress.

## Configuration
Accédez à la page **Discord Bot** dans l’administration pour :
- Saisir le token de votre bot Discord ;
- Indiquer l’ID du serveur à surveiller ;
- Définir la durée du cache des statistiques (minimum 60 secondes, alignées sur le cron de rafraîchissement) ;
- Choisir les éléments affichés par défaut (nom/avatar du serveur, rafraîchissement automatique, thème) ;
- Personnaliser les icônes et libellés proposés par défaut (cartes principales, répartition des présences, boosts) ;
- Ajouter du CSS personnalisé.

> ℹ️ Les tableaux de bord d’analytics et l’API REST `discord-bot-jlg/v1/analytics` ne sont accessibles qu’aux utilisateurs connectés disposant de la capacité `manage_options` (ou via une clé API déclarée à l’aide du filtre `discord_bot_jlg_rest_access_key`).

### Définir le token via une constante

Il est possible de forcer l'utilisation d'un token spécifique en définissant la constante `DISCORD_BOT_JLG_TOKEN` dans votre fichier `wp-config.php` ou dans un plugin mu. Lorsque cette constante est présente (et non vide), elle est utilisée à la place de la valeur enregistrée dans l'administration et le champ correspondant devient en lecture seule.

### Ajuster la taille maximale des réponses HTTP

Par défaut, le client HTTP plafonne les réponses distantes à 1 048 576 octets pour éviter de charger des fichiers trop volumineux. Si vous devez assouplir ou renforcer cette limite (par exemple pour supporter des payloads plus importants renvoyés par un proxy), vous pouvez utiliser le filtre `discord_bot_jlg_http_max_bytes` :

```php
add_filter('discord_bot_jlg_http_max_bytes', function ($max_bytes, $url, $context) {
    if ('widget' === $context) {
        return 2 * MB_IN_BYTES; // Autorise jusqu'à 2 Mo pour les appels du widget.
    }

    return $max_bytes;
}, 10, 3);
```

Le callback reçoit la valeur par défaut (1 048 576), l’URL ciblée et le contexte (`widget`, `bot`, etc.). Retournez une valeur entière strictement positive pour activer la nouvelle limite. Toute valeur inférieure ou égale à zéro réappliquera la limite par défaut.

## Utilisation
### Shortcode
```
[discord_stats]
```
Options disponibles : `layout`, `theme`, `demo`, `refresh`, etc.

Pour activer l'auto-actualisation, utilisez par exemple :

```
[discord_stats refresh="true" refresh_interval="60"]
```

💡 Les cases à cocher et listes de la page **Configuration** servent de pré-sélection lors de l’insertion du shortcode, du bloc ou du widget. Cocher « Afficher le nom du serveur » ou « Afficher l’avatar » renseigne automatiquement `show_server_name="true"` et `show_server_avatar="true"`. Le sélecteur de thème alimente l’attribut `theme`, tandis que l’option « Rafraîchissement auto par défaut » coche `refresh="true"` et initialise `refresh_interval` avec l’intervalle numérique défini. Les nouveaux panneaux « Icônes » et « Libellés par défaut » remplissent les attributs `icon_*` et `label_*` du bloc, du widget et du shortcode afin d’éviter de retaper vos émojis et textes favoris.

L'attribut optionnel `width` accepte uniquement des longueurs CSS valides comme `320px`, `75%`, `42rem`, ainsi que les mots-clés `auto`, `fit-content`, `min-content` et `max-content`. Les expressions `calc(...)` sont prises en charge lorsqu'elles ne contiennent que des nombres, des unités usuelles et les opérateurs arithmétiques de base. Toute valeur non conforme est ignorée afin d'éviter l'injection de styles indésirables.

Le paramètre `refresh_interval` est exprimé en secondes et doit être d'au moins 10 secondes (10 000 ms). Toute valeur plus basse est automatiquement portée à 10 secondes pour éviter les erreurs 429 de Discord. L’interface du bloc Gutenberg impose également cette limite via un champ numérique (incréments de 5, valeur minimale : 10).

#### Couleurs personnalisées

Les attributs suivants alimentent les variables CSS du composant et acceptent des couleurs hexadécimales (`#112233`) ou des notations `rgb()/rgba()` :

- `stat_bg_color` : couleur de fond des cartes statistiques (`--discord-surface-background`).
- `stat_text_color` : couleur du texte des cartes (`--discord-surface-text`).
- `accent_color` : couleur principale du bouton et du logo (`--discord-accent`, `--discord-logo-color`).
- `accent_color_alt` : seconde couleur du dégradé du bouton (`--discord-accent-secondary`).
- `accent_text_color` : couleur du texte du bouton (`--discord-accent-contrast`).

Exemple :

```
[discord_stats demo="true" stat_bg_color="#0f172a" stat_text_color="rgba(255,255,255,0.92)" accent_color="#38bdf8" accent_text_color="#0b1120" align="center"]
```

Le bloc Gutenberg expose ces réglages via un panneau « Couleurs » basé sur les `ColorPalette`, ce qui permet d’ajuster rapidement la charte sans écrire de CSS additionnel.

Pendant une requête d'actualisation, le conteneur affiche désormais un indicateur d'état (`role="status"`) et applique l'attribut `data-refreshing="true"`. L'opacité des statistiques diminue légèrement, les interactions sont bloquées et une pastille sombre (contraste élevé) accompagnée d'un spinner discret signalent l'opération. Dès que la réponse est reçue — succès ou erreur — l'indicateur est retiré et `data-refreshing` repasse automatiquement à `false`. L'animation est désactivée lorsque le visiteur préfère réduire les mouvements (`prefers-reduced-motion`).

Les rafraîchissements publics (visiteurs non connectés) n'exigent plus de nonce WordPress ; seuls les administrateurs connectés utilisent un jeton de sécurité pour l'action AJAX `refresh_discord_stats`.

### Accessibilité

Le plugin embarque sa propre définition `.screen-reader-text`, incluse à la fois dans les feuilles de style principales et inline chargées par le shortcode. Ce fallback reprend le pattern WordPress (position absolue, dimensions réduites à 1px, `clip-path: inset(50%)`, etc.) afin que les libellés masqués restent interprétables par les lecteurs d'écran même si le thème actif ne fournit pas cette classe utilitaire.

La zone des compteurs expose également `aria-live="polite"` et bascule `aria-busy` durant les rafraîchissements. Ainsi, les lecteurs d'écran (NVDA 2024.1 et VoiceOver sous macOS Sonoma, testés avec Firefox/Chrome et Safari) annoncent l'arrivée de nouvelles valeurs sans interrompre la navigation en cours.

### Ré-initialisation manuelle du widget

Si vous chargez dynamiquement du HTML contenant de nouveaux conteneurs `.discord-stats-container`, vous pouvez relancer l'initialisation automatique en appelant l'API publique exposée par le script côté client :

```js
window.discordBotJlgInit();
// ou, si vous préférez conserver la configuration existante sur `window.discordBotJlg`
window.discordBotJlg.init();
```

Cette méthode relit la configuration globale (`window.discordBotJlg`) et programme les rafraîchissements pour tous les conteneurs présents dans le DOM.

### Widget
Un widget « Discord Bot - JLG » est disponible via le menu « Widgets ».

## Fonctionnalités

### Collecte des données et cache
- Récupération combinée des statistiques publiques du widget et, si besoin, des informations du bot afin de consolider les compteurs en un seul jeu de données exploitable, avec bascule automatique vers un mode démo ou des statistiques de secours en cas d'échec.【F:discord-bot-jlg/inc/class-discord-api.php†L240-L358】【F:discord-bot-jlg/inc/class-discord-api.php†L406-L489】
- Mise en cache des réponses (transients) avec planification automatique du rafraîchissement via un cron dédié ajustable entre 60 et 3600 secondes, et purge du cache lors des changements de configuration sensibles.【F:discord-bot-jlg/discord-bot-jlg.php†L30-L88】【F:discord-bot-jlg/discord-bot-jlg.php†L394-L433】
- Journalisation des instantanés dans une table dédiée pour alimenter les analytics, avec rétention configurable et purge des entrées expirées.【F:discord-bot-jlg/inc/class-discord-analytics.php†L53-L165】【F:discord-bot-jlg/inc/class-discord-analytics.php†L164-L217】

### Configuration & administration
- Page d’options complète pour définir l’ID du serveur, les tokens (y compris plusieurs profils avec labels, IDs et jetons distincts), le mode démo, la durée du cache, les thèmes, les icônes/libellés par défaut, l’URL et le libellé d’invitation, ainsi qu’un champ CSS personnalisé.【F:discord-bot-jlg/inc/class-discord-admin.php†L38-L213】【F:discord-bot-jlg/inc/class-discord-admin.php†L213-L356】【F:discord-bot-jlg/inc/class-discord-admin.php†L428-L642】【F:discord-bot-jlg/inc/class-discord-admin.php†L775-L1007】
- Sous-menu « Guide & Démo » pour visualiser le rendu et les instructions directement depuis l’administration.【F:discord-bot-jlg/inc/class-discord-admin.php†L60-L103】
- Validation stricte des entrées (intervalle de rafraîchissement, couleurs, textes, profils) afin d’éviter l’injection de valeurs invalides et de conserver les secrets existants si aucun nouveau jeton n’est fourni.【F:discord-bot-jlg/inc/class-discord-admin.php†L251-L423】【F:discord-bot-jlg/inc/class-discord-admin.php†L642-L770】

### Affichage public (shortcode, bloc & widget)
- Shortcode `[discord_stats]`, bloc Gutenberg associé et widget classique partageant la même API, avec héritage des options d’administration (thème, libellés, icônes, couleurs, rafraîchissement auto).【F:discord-bot-jlg/discord-bot-jlg.php†L226-L315】【F:discord-bot-jlg/inc/class-discord-shortcode.php†L23-L211】【F:discord-bot-jlg/inc/class-discord-widget.php†L16-L155】
- Large éventail d’attributs : choix de layout, mode compact, alignement, largeur, présence/boosts, affichage du nom et de l’avatar du serveur, position de l’icône Discord, bouton CTA secondaire, surcharge des couleurs et textes, intervalle de rafraîchissement (min. 10 s) et sélection du profil serveur.【F:discord-bot-jlg/inc/class-discord-shortcode.php†L212-L420】【F:discord-bot-jlg/inc/class-discord-shortcode.php†L459-L671】【F:discord-bot-jlg/inc/class-discord-shortcode.php†L704-L1059】
- Ré-initialisation JavaScript publique via `window.discordBotJlgInit()` pour re-synchroniser les conteneurs injectés dynamiquement, et indicateurs visuels/ARIA lors des rafraîchissements automatiques.【F:discord-bot-jlg/assets/js/discord-bot-jlg.js†L2710-L2795】【F:discord-bot-jlg/inc/class-discord-shortcode.php†L731-L1008】

### Accessibilité & UX
- Styles embarqués reproduisant la classe `.screen-reader-text` WordPress, labels cachés, attributs `aria-live`/`aria-busy` et gestion du mode « prefers-reduced-motion » pour des rafraîchissements non intrusifs.【F:discord-bot-jlg/assets/css/discord-bot-jlg.css†L35-L71】【F:discord-bot-jlg/inc/class-discord-shortcode.php†L704-L1040】
- Panneaux Gutenberg et champs d’administration accessibles (légendes cachées, descriptions) pour guider la configuration des icônes et libellés.【F:discord-bot-jlg/inc/class-discord-admin.php†L878-L939】【F:discord-bot-jlg/inc/class-discord-admin.php†L1299-L1385】

### API, analytics & supervision
- Endpoints REST `discord-bot-jlg/v1/stats` et `discord-bot-jlg/v1/analytics` pour récupérer les compteurs en temps réel ou agrégés, protégés par la capacité `manage_options` ou une clé d’accès filtrable (`discord_bot_jlg_rest_access_key`).【F:discord-bot-jlg/inc/class-discord-rest.php†L23-L199】【F:discord-bot-jlg/inc/class-discord-rest.php†L201-L244】
- Endpoint REST `discord-bot-jlg/v1/events` fournissant un journal structuré des appels Discord (durée, statut HTTP, quotas restants, diagnostics) afin d’alimenter des outils d’observabilité ou des alertes automatisées.【F:discord-bot-jlg/inc/class-discord-api.php†L1991-L2133】【F:discord-bot-jlg/inc/class-discord-rest.php†L23-L152】
- Intégration WP-CLI avec commandes `wp discord-bot refresh-cache` et `wp discord-bot clear-cache` pour forcer une synchronisation ou purger les données sans passer par l’UI.【F:discord-bot-jlg/inc/class-discord-cli.php†L24-L81】
- Test « Site Health » dédié indiquant l’état de la connexion Discord, les erreurs récentes et les prochaines tentatives de rafraîchissement.【F:discord-bot-jlg/inc/class-discord-site-health.php†L17-L105】
- Chargement des traductions et assets spécifiques (bloc, scripts, styles) afin d’assurer une intégration native à l’écosystème WordPress.【F:discord-bot-jlg/discord-bot-jlg.php†L105-L180】【F:discord-bot-jlg/discord-bot-jlg.php†L226-L315】

## Désinstallation
La suppression du plugin depuis WordPress efface automatiquement l’option `discord_server_stats_options` et le transient `discord_server_stats_cache` associés aux statistiques du serveur.

## Tests
Le plugin est compatible avec la suite de tests WordPress générée par `wp scaffold plugin-tests`.

1. Installez la bibliothèque de tests WordPress et définissez la variable d'environnement `WP_TESTS_DIR` (ou laissez la valeur par défaut `sys_get_temp_dir()/wordpress-tests-lib`).
2. Depuis le dossier `discord-bot-jlg`, exécutez :

```
phpunit --testsuite discord-bot-jlg
```

La suite couvre à la fois la couche API et la sanitisation des options d'administration (ID serveur, token, durée du cache et CSS personnalisé).

Le fichier `phpunit.xml.dist` du plugin référence automatiquement le bootstrap de la suite de tests.

## Support
- Portail développeur Discord : https://discord.com/developers/applications
- Notes de version disponibles dans l’interface du plugin.

### CLI

Deux commandes WP-CLI facilitent les opérations :

- `wp discord-bot refresh-cache` force l'appel API immédiat en ignorant le cache ;
- `wp discord-bot clear-cache` purge toutes les données (transients, sauvegardes et limites de taux).

Les deux commandes retournent un code de sortie non nul en cas d'erreur afin de permettre l'automatisation dans vos scripts d'exploitation.
