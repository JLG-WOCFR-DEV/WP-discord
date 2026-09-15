=== Discord Bot - JLG ===
Contributors: jeromelegousse
Tags: discord, stats, shortcode, block, widget
Requires at least: 5.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Affiche les statistiques d’un serveur Discord (membres en ligne et total) via shortcode, bloc et widget.

== Description ==

Discord Bot - JLG affiche les statistiques de votre serveur Discord dans WordPress : membres en ligne, total, répartition des présences et boosts.

L’écran de réglages suit la charte wp-admin (`wrap`, `h1`, `nav-tab`, Settings API, `form-table`, `button-primary`, `notice-*`). Le JavaScript front ne s’exécute pas dans l’éditeur iframé de WordPress 7.1.

== Installation ==

1. Copier le dossier `discord-bot-jlg` dans `wp-content/plugins/`.
2. Activer le plugin.
3. Aller dans Réglages → Discord Bot.

== Changelog ==

= 1.0.1 =
* Déclare Requires PHP 7.4 et Tested up to 7.1.
* Empêche le JS front de tourner dans le canvas Gutenberg iframé.
* Aligne l’admin sur la charte wp-admin (plus d’emoji d’onglets, plus de restyle du chrome WP).
