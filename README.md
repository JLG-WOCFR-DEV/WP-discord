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

Le paramètre `refresh_interval` est exprimé en secondes et doit être d'au moins 10 secondes (10 000 ms). Toute valeur plus basse est automatiquement portée à 10 secondes pour éviter les erreurs 429 de Discord.

Les rafraîchissements publics (visiteurs non connectés) n'exigent plus de nonce WordPress ; seuls les administrateurs connectés utilisent un jeton de sécurité pour l'action AJAX `refresh_discord_stats`.

### Widget
Un widget « Discord Bot - JLG » est disponible via le menu « Widgets ».

## Fonctionnalités
- Affichage du nombre de membres en ligne et du total ;
- Mise en cache des statistiques ;
- Journalisation des erreurs de communication avec l'API Discord (accessible via `WP_DEBUG_LOG`) ;
- Page de démonstration intégrée.

## Désinstallation
La suppression du plugin depuis WordPress efface automatiquement l’option `discord_server_stats_options` et le transient `discord_server_stats_cache` associés aux statistiques du serveur.

## Support
- Portail développeur Discord : https://discord.com/developers/applications
- Notes de version disponibles dans l’interface du plugin.
