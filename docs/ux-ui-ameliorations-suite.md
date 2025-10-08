# Améliorations UX/UI supplémentaires inspirées des outils professionnels

## Synthèse rapide (2024-07)

- **Comparaison multi-profils** : objectif sprint +2 pour un MVP en deux panneaux (desktop) et carrousel (mobile). Nécessite la mutualisation du cache et un nouvel attribut `profiles[]` partagé entre bloc/shortcode.【F:docs/ux-ui-ameliorations-suite.md†L5-L27】
- **Explorateur de présence segmenté** : dépend de l’extension de l’API analytics pour exposer les séries historiques `presence_breakdown`. Prévoir une itération de recherche utilisateur avec les CM pilotes.【F:docs/ux-ui-ameliorations-suite.md†L29-L57】
- **Timeline analytique enrichie** : à coupler avec le chantier observabilité pour consolider annotations et exports dans le back-office.【F:docs/ux-ui-ameliorations-suite.md†L59-L83】
- **Signalétique proactive & sparkline multi-couches** : livrables complémentaires pour renforcer la perception de fraîcheur des données et l’orientation action.【F:docs/ux-ui-ameliorations-suite.md†L85-L133】

## 1. Tableau comparatif multi-profils
- **Constat** : chaque instance du bloc ou du shortcode ne pointe que vers un profil serveur unique via l’attribut `profile`, ce qui limite les comparaisons simultanées.【F:discord-bot-jlg/block/discord-stats/block.json†L263-L269】【F:discord-bot-jlg/inc/class-discord-shortcode.php†L666-L672】
- **Inspiration pro** : les suites analytics Discord/Slack destinées aux équipes communautaires offrent des vues côte à côte pour benchmarquer plusieurs espaces.
- **Proposition** : permettre la sélection de plusieurs profils dans Gutenberg (multi-select ou picker à tags), avec un rendu en colonnes ou onglets synchronisés et un récapitulatif commun (moyenne, min/max). Ajouter un mode "comparaison" qui met en évidence l’écart relatif par rapport à un profil de référence.
- **Fonctionnalités clés** :
  - Champ de sélection multi-profils avec auto-complétion, badges de couleur et option de profil de référence (étoile ou pastille).
  - Disposition responsive : bascule en colonnes sur desktop, carrousel à onglets sur mobile pour conserver la lisibilité.
  - Bandeau de synthèse affichant les deltas (∆) sur les principaux KPI et un indicateur visuel (vert/orange/rouge) en fonction de seuils configurables.
- **Parcours utilisateur** :
  1. L’éditeur ajoute le bloc et choisit 2 à 4 profils dans la modale.
  2. Le visiteur peut activer un mode "Aligner les courbes" pour comparer à échelle identique ou utiliser le bouton "Profil de référence" pour recalculer les deltas.
  3. Un CTA "Exporter la comparaison" déclenche un export CSV regroupant les métriques agrégées.
- **Impacts techniques** :
  - Étendre le schéma de bloc (attribut `profiles[]` + `referenceProfile`).
  - Adapter le resolver côté PHP pour hydrater plusieurs jeux de données et recalculer les agrégations.
  - Mutualiser le cache en regroupant les profils demandés pour limiter les requêtes API.

## 2. Explorateur de présence segmenté
- **Constat** : le front affiche une simple liste statique des statuts (`ul.discord-presence-list`) sans possibilité d’isoler un segment ou de visualiser les tendances, alors que les snapshots stockent déjà un `presence_breakdown` détaillé à chaque collecte.【F:discord-bot-jlg/inc/class-discord-shortcode.php†L872-L939】【F:discord-bot-jlg/inc/class-discord-analytics.php†L123-L155】
- **Inspiration pro** : les dashboards RH/CSM proposent des filtres par statut, heatmaps et comparaisons temporelles pour détecter les pics d’engagement.
- **Proposition** : transformer la liste en cartes filtrables (chips ou toggle) qui mettent à jour dynamiquement les graphes/sparklines. Ajouter un second volet "Evolution" exploitant `presence_breakdown` pour afficher un histogramme des statuts sur la période sélectionnée.
- **Fonctionnalités clés** :
  - Filtres persistants (online, idle, dnd, offline) affichés sous forme de pills interactives avec compteur et variation (% vs. veille).
  - Zone latérale "Insights" proposant automatiquement les segments dominants et les heures de pics, calculées via un regroupement par tranche horaire.
  - Heatmap hebdomadaire permettant de visualiser la répartition des statuts par jour/heure.
- **Parcours utilisateur** :
  1. L’utilisateur choisit une période (7/14/30 jours) dans une barre supérieure.
  2. La sélection d’un statut rafraîchit instantanément les sparklines et la heatmap via transitions fluides.
  3. Un bouton "Comparer à la semaine précédente" juxtapose une mini heatmap grisée pour souligner les variations.
- **Impacts techniques** :
  - Étendre l’API interne pour exposer les séries historiques de `presence_breakdown`.
  - Utiliser un state manager léger (ex. Zustand) côté bloc React pour synchroniser filtres et graphiques.
  - Prévoir une fallback expérience (liste statique) si les données historiques sont absentes.

## 3. Timeline analytique enrichie côté administration
- **Constat** : le panneau analytics admin trace seulement trois séries linéaires sans filtres temporels ni annotations (configuration Chart.js par défaut).【F:discord-bot-jlg/assets/js/discord-bot-jlg-admin-analytics.js†L168-L294】
- **Inspiration pro** : les consoles SaaS incluent des sélecteurs de période (7/30/90 jours), un zoom temporel, des marqueurs d’événements (campagnes, incidents) et des exports directs.
- **Proposition** : ajouter des contrôles de plage temporelle, le pan & zoom, des annotations que l’on peut attacher aux pics, et un bouton d’export CSV/PNG. Prévoir une superposition des moyennes glissantes et un mode "comparaison" vs. période précédente.
- **Fonctionnalités clés** :
  - Toolbar supérieure avec quick-picks (7/14/30/90 jours), date-range picker et toggle "Comparer N-1".
  - Annotations inline (tags "Campagne newsletter", "Incident API") associées à la date via un panneau latéral d’événements.
  - Overlays de moyennes glissantes (7j & 30j) + bandes de confiance.
- **Parcours utilisateur** :
  1. L’administrateur sélectionne une plage et ajoute une annotation depuis un bouton "+ Événement".
  2. Les courbes se recalculent avec un effet de zoom progressif et les overlays s’ajustent.
  3. Le bouton "Exporter" génère un CSV/PNG incluant annotations et filtres en vigueur, avec log d’activité dans la base.
- **Impacts techniques** :
  - Passer Chart.js en mode "interaction" (pan/zoom) via `chartjs-plugin-zoom` et gérer la persistance des annotations dans la table analytics.
  - Étendre l’endpoint REST admin pour filtrer par plage de dates et servir les moyennes glissantes pré-calculées.
  - Ajouter une file WP Cron pour préparer les exports lourds afin de ne pas bloquer l’interface.

## 4. Signalétique d’état proactive sur le widget
- **Constat** : le rendu expose bien des attributs `data-demo`, `data-fallback-demo` et `data-stale`, mais l’utilisateur ne voit qu’un bandeau texte minimal quand les données proviennent du cache.【F:discord-bot-jlg/inc/class-discord-shortcode.php†L604-L742】
- **Inspiration pro** : les widgets enterprise affichent des badges de statut, des barres de progression de rafraîchissement et des bulles d’aide détaillant l’impact d’un mode dégradé.
- **Proposition** : introduire une bannière d’état colorée avec icône, minuterie avant prochain refresh et lien "Voir le journal". En mode fallback/demo, afficher une tooltip expliquant l’origine des chiffres et proposer des actions (forcer une synchro, ouvrir la page de statut).
- **Fonctionnalités clés** :
  - Badge sticky en haut à droite qui indique "Live", "Cache" ou "Demo" avec code couleur (vert/ambre/bleu).
  - Compteur circulaire représentant le temps restant avant le prochain `refresh_ttl`, animé pour donner un sentiment de réactivité.
  - Bouton "En savoir plus" ouvrant un panneau latéral (drawer) listant les derniers jobs de synchronisation et l’état de la connexion bot.
- **Parcours utilisateur** :
  1. L’utilisateur voit instantanément le badge d’état et peut cliquer pour afficher les détails.
  2. Si le mode dégradé est actif, une tooltip contextuelle explique les limitations et offre un CTA "Tenter une nouvelle synchro".
  3. Un historique compact affiche les trois dernières actualisations avec timestamp et résultat (succès, timeout, erreur API).
- **Impacts techniques** :
  - Sérialiser dans l’attribut data un objet `status_meta` (ttl, dernière synchro, source des données).
  - Ajouter un endpoint AJAX sécurisé pour déclencher une resynchronisation et récupérer l’historique de jobs.
  - Mettre en place des styles modulaires (CSS variables) pour harmoniser la charte et permettre le theming.

## 5. Sparkline multi-couches orientée action
- **Constat** : l’embed analytics ne trace qu’une métrique à la fois et sur une fenêtre fixe (3–30 jours), sans seuils ni comparaison croisée.【F:discord-bot-jlg/inc/class-discord-shortcode.php†L236-L249】【F:discord-bot-jlg/inc/class-discord-shortcode.php†L1005-L1015】【F:discord-bot-jlg/assets/js/discord-bot-block.js†L224-L226】
- **Inspiration pro** : les outils de community management affichent plusieurs séries (online vs. total vs. boosts), des bandes de tolérance et des alertes quand un KPI sort de la plage attendue.
- **Proposition** : proposer un switch multi-métriques ou une superposition translucide des séries, ajouter des lignes de référence configurables (objectif de présence, seuil de boosts) et un badge qui signale automatiquement les anomalies (écart standard > X %).
- **Fonctionnalités clés** :
  - Sélecteur de métriques (présence active, nouveaux membres, messages/jour, boosts) avec possibilité de cocher plusieurs séries simultanément.
  - Bandes de tolérance calculées à partir de la médiane ± écart-type et affichées en arrière-plan.
  - Badge "Action requise" qui apparaît lorsque la série franchit une zone rouge, avec suggestions contextuelles ("Planifier une AMA", "Relancer les boosts").
- **Parcours utilisateur** :
  1. Le visiteur choisit les indicateurs à afficher ou utilise un preset ("Engagement", "Croissance").
  2. Les sparklines se superposent avec légende condensée et animation discrète.
  3. Les badges d’alerte ouvrent une modale listant les actions recommandées, alimentées par une table de playbooks.
- **Impacts techniques** :
  - Étendre la configuration Chart.js pour gérer plusieurs datasets, légendes dynamiques et zones colorées.
  - Ajouter une logique de détection d’anomalies côté PHP ou JS (calcul d’écart-type glissant) et stocker les playbooks associés dans une option WordPress.
  - Prévoir un mécanisme de presets (JSON) pour enregistrer/partager des configurations de métriques via le bloc.

## Tableau de suivi UX/UI

| Statut | Sujet | Prochaine décision | Références |
| --- | --- | --- | --- |
| 🛠️ Design en cours | Tableau comparatif multi-profils | Valider le rendu responsive (colonnes vs. carrousel) et définir le coût API. | 【F:docs/ux-ui-ameliorations-suite.md†L1-L42】 |
| ⏳ À prioriser | Explorateur de présence segmenté | Choisir la librairie graphique (Chart.js vs. D3) et cadrer la volumétrie analytics. | 【F:docs/ux-ui-ameliorations-suite.md†L44-L82】 |
| ⏳ À prioriser | Timeline analytics enrichie | Identifier les besoins d’export (CSV/PNG) et les droits d’accès associés. | 【F:docs/ux-ui-ameliorations-suite.md†L84-L117】 |
| ✅ Livraison initiale | Signalétique d’état proactive | Consolider les métriques affichées et brancher l’export CSV depuis le panneau d’état. | 【F:docs/ux-ui-ameliorations-suite.md†L119-L164】 |
| 🟢 Idea bank | Sparkline multi-couches | Déterminer les métriques à exposer par défaut et la logique d’alerting. | 【F:docs/ux-ui-ameliorations-suite.md†L166-L210】 |

Ce tableau complète la roadmap produit en fournissant un état synthétique des initiatives UX. Mettre à jour les statuts à mesure des validations ateliers/design.
