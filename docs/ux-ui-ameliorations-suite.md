# Améliorations UX/UI supplémentaires inspirées des outils professionnels

## 1. Tableau comparatif multi-profils
- **Constat** : chaque instance du bloc ou du shortcode ne pointe que vers un profil serveur unique via l’attribut `profile`, ce qui limite les comparaisons simultanées.【F:discord-bot-jlg/block/discord-stats/block.json†L263-L269】【F:discord-bot-jlg/inc/class-discord-shortcode.php†L666-L672】
- **Inspiration pro** : les suites analytics Discord/Slack destinées aux équipes communautaires offrent des vues côte à côte pour benchmarquer plusieurs espaces.
- **Proposition** : permettre la sélection de plusieurs profils dans Gutenberg (multi-select ou picker à tags), avec un rendu en colonnes ou onglets synchronisés et un récapitulatif commun (moyenne, min/max). Ajouter un mode "comparaison" qui met en évidence l’écart relatif par rapport à un profil de référence.

## 2. Explorateur de présence segmenté
- **Constat** : le front affiche une simple liste statique des statuts (`ul.discord-presence-list`) sans possibilité d’isoler un segment ou de visualiser les tendances, alors que les snapshots stockent déjà un `presence_breakdown` détaillé à chaque collecte.【F:discord-bot-jlg/inc/class-discord-shortcode.php†L872-L939】【F:discord-bot-jlg/inc/class-discord-analytics.php†L123-L155】
- **Inspiration pro** : les dashboards RH/CSM proposent des filtres par statut, heatmaps et comparaisons temporelles pour détecter les pics d’engagement.
- **Proposition** : transformer la liste en cartes filtrables (chips ou toggle) qui mettent à jour dynamiquement les graphes/sparklines. Ajouter un second volet "Evolution" exploitant `presence_breakdown` pour afficher un histogramme des statuts sur la période sélectionnée.

## 3. Timeline analytique enrichie côté administration
- **Constat** : le panneau analytics admin trace seulement trois séries linéaires sans filtres temporels ni annotations (configuration Chart.js par défaut).【F:discord-bot-jlg/assets/js/discord-bot-jlg-admin-analytics.js†L168-L294】
- **Inspiration pro** : les consoles SaaS incluent des sélecteurs de période (7/30/90 jours), un zoom temporel, des marqueurs d’événements (campagnes, incidents) et des exports directs.
- **Proposition** : ajouter des contrôles de plage temporelle, le pan & zoom, des annotations que l’on peut attacher aux pics, et un bouton d’export CSV/PNG. Prévoir une superposition des moyennes glissantes et un mode "comparaison" vs. période précédente.

## 4. Signalétique d’état proactive sur le widget
- **Constat** : le rendu expose bien des attributs `data-demo`, `data-fallback-demo` et `data-stale`, mais l’utilisateur ne voit qu’un bandeau texte minimal quand les données proviennent du cache.【F:discord-bot-jlg/inc/class-discord-shortcode.php†L604-L742】
- **Inspiration pro** : les widgets enterprise affichent des badges de statut, des barres de progression de rafraîchissement et des bulles d’aide détaillant l’impact d’un mode dégradé.
- **Proposition** : introduire une bannière d’état colorée avec icône, minuterie avant prochain refresh et lien "Voir le journal". En mode fallback/demo, afficher une tooltip expliquant l’origine des chiffres et proposer des actions (forcer une synchro, ouvrir la page de statut).

## 5. Sparkline multi-couches orientée action
- **Constat** : l’embed analytics ne trace qu’une métrique à la fois et sur une fenêtre fixe (3–30 jours), sans seuils ni comparaison croisée.【F:discord-bot-jlg/inc/class-discord-shortcode.php†L236-L249】【F:discord-bot-jlg/inc/class-discord-shortcode.php†L1005-L1015】【F:discord-bot-jlg/assets/js/discord-bot-block.js†L224-L226】
- **Inspiration pro** : les outils de community management affichent plusieurs séries (online vs. total vs. boosts), des bandes de tolérance et des alertes quand un KPI sort de la plage attendue.
- **Proposition** : proposer un switch multi-métriques ou une superposition translucide des séries, ajouter des lignes de référence configurables (objectif de présence, seuil de boosts) et un badge qui signale automatiquement les anomalies (écart standard > X %).
