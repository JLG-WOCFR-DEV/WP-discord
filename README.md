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

## Utilisation
### Shortcode
```
[discord_stats]
```
Options disponibles : `layout`, `theme`, `demo`, etc.

### Widget
Un widget « Discord Bot - JLG » est disponible via le menu « Widgets ».

## Fonctionnalités
- Affichage du nombre de membres en ligne et du total ;
- Mise en cache des statistiques ;
- Page de démonstration intégrée.

## Support
- Portail développeur Discord : https://discord.com/developers/applications
- Notes de version disponibles dans l’interface du plugin.
