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
- Définir la durée du cache des statistiques ;
- Ajouter du CSS personnalisé.

### Définir le token via une constante

Il est possible de forcer l'utilisation d'un token spécifique en définissant la constante `DISCORD_BOT_JLG_TOKEN` dans votre fichier `wp-config.php` ou dans un plugin mu. Lorsque cette constante est présente (et non vide), elle est utilisée à la place de la valeur enregistrée dans l'administration et le champ correspondant devient en lecture seule.

### Nouvelle version du secret chiffré

Les tokens stockés en base de données sont désormais encodés avec le préfixe `dbjlg_enc_v2:` et un vecteur d'initialisation (IV) généré aléatoirement pour chaque chiffrement. Les anciens secrets (`dbjlg_enc_v1:`) restent pris en charge et seront automatiquement déchiffrés. Pour bénéficier du nouvel encodage renforcé, il suffit de ressaisir et enregistrer le token du bot depuis l’interface d’administration (ou de l’effacer puis de le coller à nouveau).

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

L'attribut optionnel `width` accepte uniquement des longueurs CSS valides comme `320px`, `75%`, `42rem`, ainsi que les mots-clés `auto`, `fit-content`, `min-content` et `max-content`. Les expressions `calc(...)` sont prises en charge lorsqu'elles ne contiennent que des nombres, des unités usuelles et les opérateurs arithmétiques de base. Toute valeur non conforme est ignorée afin d'éviter l'injection de styles indésirables.

Le paramètre `refresh_interval` est exprimé en secondes et doit être d'au moins 10 secondes (10 000 ms). Toute valeur plus basse est automatiquement portée à 10 secondes pour éviter les erreurs 429 de Discord.

Les rafraîchissements publics (visiteurs non connectés) n'exigent plus de nonce WordPress ; seuls les administrateurs connectés utilisent un jeton de sécurité pour l'action AJAX `refresh_discord_stats`.

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
- Affichage du nombre de membres en ligne et du total ;
- Mise en cache des statistiques ;
- Journalisation des erreurs de communication avec l'API Discord (accessible via `WP_DEBUG_LOG`) ;
- Page de démonstration intégrée.

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
