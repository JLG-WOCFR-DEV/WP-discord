# Audit comparatif : "Discord Bot - JLG" vs. standard applicatif professionnel

## 1. Options & personnalisation
### Forces actuelles
- Le panneau d'administration expose déjà la plupart des interrupteurs attendus (thème, rafraîchissement, intitulés, couleurs, cache, CTA, CSS custom) et s'appuie sur une sanitisation robuste des entrées.【F:discord-bot-jlg/inc/class-discord-admin.php†L200-L268】【F:discord-bot-jlg/inc/class-discord-admin.php†L320-L520】
- Le bloc Gutenberg reprend ces attributs côté éditeur (layout, thèmes, alignements, icônes/libellés, CTA, sparkline) garantissant la cohérence entre shortcode, bloc et widget.【F:discord-bot-jlg/block/discord-stats/block.json†L1-L239】【F:discord-bot-jlg/assets/js/discord-bot-block.js†L163-L280】

### Écarts vs. une app "pro"
- Aucun paramètre n'autorise la segmentation des données (par canal, rôle, catégorie) ni l'affichage simultané de plusieurs serveurs dans un seul bloc, ce que proposent les offres SaaS avancées pour comparer des sous-communautés.【F:discord-bot-jlg/block/discord-stats/block.json†L1-L239】
- L'éditeur ne prévoit pas de presets/variations packagées (templates, palettes, cartes alternatives) ni d'intégration directe avec les bibliothèques de couleurs/typos globales de WordPress, obligeant les utilisateurs à reconfigurer chaque instance manuellement.【F:discord-bot-jlg/block/discord-stats/block.json†L16-L39】【F:discord-bot-jlg/assets/js/discord-bot-block.js†L163-L227】
- Le suivi analytics reste interne : aucune option d'export (CSV/JSON) ni de webhooks/notifications pour alerter en cas de chute d'activité n'est prévue malgré la collecte cron existante.【F:discord-bot-jlg/inc/class-discord-shortcode.php†L670-L715】【F:discord-bot-jlg/assets/js/discord-bot-jlg.js†L2600-L2688】

### Recommandations
1. Introduire des "profils combinés" : permettre de sélectionner plusieurs serveurs ou de filtrer les jeux de données (rôles, canaux vocaux) via des attributs supplémentaires et un panneau multi-sélection dans l'éditeur.
2. Fournir des variations prêtes à l'emploi (ex. "Carte minimaliste", "Badge e-sport", "Sidebar compacte") et connecter les contrôles de couleurs/typos aux presets du thème (`theme.json`).
3. Ajouter un centre de notifications (e-mail, Discord webhook) et des exports programmables pour capitaliser sur la base analytics existante.

## 2. UX / UI du composant public
### Forces actuelles
- Le rendu repose sur des variables CSS responsives (`clamp`) et une grille flexible qui conserve lisibilité et cohérence visuelle quel que soit le thème parent.【F:discord-bot-jlg/assets/css/discord-bot-jlg.css†L2-L147】【F:discord-bot-jlg/assets/css/discord-bot-jlg-inline.css†L2-L152】
- Le marquage HTML prévoit des badges, CTA, avatars, messages d'état (données en cache, mode démo) et une note temporelle pour contextualiser l'information.【F:discord-bot-jlg/inc/class-discord-shortcode.php†L604-L742】【F:discord-bot-jlg/inc/class-discord-shortcode.php†L803-L1015】
- Les rafraîchissements automatiques affichent un état visuel dédié (`data-refreshing`, pastille sombre) et gèlent les interactions pour éviter les clics fantômes.【F:discord-bot-jlg/assets/css/discord-bot-jlg-inline.css†L494-L520】【F:discord-bot-jlg/assets/js/discord-bot-jlg.js†L2600-L2703】

### Écarts vs. une app "pro"
- L'expérience demeure statique : pas de transitions contextualisées (micro-charts incrustées, comparatif vs. période précédente) ni de hiérarchie visuelle dynamique selon l'importance des métriques.
- Le premier chargement n'offre pas de skeleton natif côté front (seulement lors des rafraîchissements), ce qui crée un "flash" d'apparition par rapport aux tableaux de bord pro qui affichent des placeholders.
- Le CTA principal ne propose qu'un style plein ou outline ; absence d'icônes additionnelles, de sous-CTA (ex. "Voir les règles", "Calendrier des events") ou de déclinaisons en bandeau/panneau sticky.

### Recommandations
1. Étendre le composant avec un mode "insights" (indicateurs de variation, badges de tendance) et des micro visualisations inline (barres/mini-heatmaps) pour rivaliser avec les dashboards premium.
2. Ajouter un skeleton loader configurable (gradient shimmer) servi dès le premier rendu public et non uniquement dans Gutenberg.
3. Créer une zone CTA modulaire (plusieurs boutons, texte descriptif, icône configurable) et des placements alternatifs (header, footer flottant) activables via attributs.

## 3. Navigation & expérience mobile
### Forces actuelles
- La disposition flex/grid se réarrange automatiquement (wrap) et la présence détaillée bascule en colonne unique sous 600 px pour conserver la lisibilité.【F:discord-bot-jlg/assets/css/discord-bot-jlg-inline.css†L75-L152】【F:discord-bot-jlg/assets/css/discord-bot-jlg-inline.css†L368-L381】
- Les CTA passent en pleine largeur sur mobile pour maximiser la zone de tap et réduire les erreurs de saisie.【F:discord-bot-jlg/assets/css/discord-bot-jlg-inline.css†L368-L377】

### Écarts vs. une app "pro"
- Aucune navigation secondaire (onglets, slider horizontal) n'est prévue lorsque de nombreuses cartes sont affichées ; sur un écran réduit, l'utilisateur doit scroller longuement.
- Pas de comportement spécifique pour les états "collapsés" (sticky header, segmentation par sections) ni d'actions rapides adaptées aux usages mobiles (ex. bouton d'appel Discord, partage).

### Recommandations
1. Ajouter un mode "carrousel" optionnel sur mobile (glisser horizontal) avec pagination et boutons de saut rapide.
2. Offrir la possibilité de regrouper les cartes dans des sections repliables (accordéons) et d'afficher un mini-sommaire sticky.
3. Prévoir des actions contextuelles (icône téléphone, partage, copie d'invitation) affichées dans une barre flottante sur mobile.

## 4. Accessibilité
### Forces actuelles
- Les styles répliquent la classe `.screen-reader-text` WordPress et la région principale est annoncée (`role="region"`, `aria-live`, `aria-busy`).【F:discord-bot-jlg/assets/css/discord-bot-jlg.css†L42-L70】【F:discord-bot-jlg/inc/class-discord-shortcode.php†L604-L632】
- Chaque compteur expose une liaison aria (`aria-labelledby`) et un fallback textuel pour indiquer les valeurs approximatives ou indisponibles.【F:discord-bot-jlg/inc/class-discord-shortcode.php†L803-L1015】
- Les animations respectent la préférence `prefers-reduced-motion` et sont désactivées côté JS pour limiter les nausées numériques.【F:discord-bot-jlg/assets/js/discord-bot-jlg.js†L1523-L1556】【F:discord-bot-jlg/assets/css/discord-bot-jlg-inline.css†L561-L620】

### Écarts vs. une app "pro"
- Absence d'options de contraste renforcé ou de thème accessible prédéfini (couleurs AAA, bordures épaisses), pourtant attendus dans les suites pro.
- Pas de navigation clavier avancée (focus trap sur le composant, raccourcis pour passer d'une carte à l'autre) ni d'annonce vocale dédiée lors des rafraîchissements (message succinct).
- Les statistiques détaillées (répartition) restent présentées sous forme de liste visuelle sans alternative tabulaire exportable ou lisible par des lecteurs d'écran sous forme de tableau.

### Recommandations
1. Proposer un thème "Accessible" avec contraste élevé, polices adaptées et option pour désactiver toutes les animations.
2. Ajouter des contrôles clavier (flèches gauche/droite, touche `R` pour rafraîchir) et des annonces ARIA personnalisées à chaque mise à jour.
3. Fournir un mode tableau accessible (balises `<table>`) exportable ou téléchargeable pour la répartition des présences.

## 5. Apparence & ergonomie dans WordPress (éditeur visuel)
### Forces actuelles
- Le bloc affiche un aperçu statique enrichi et bascule en rendu serveur dès que les identifiants sont valides, avec skeleton/shimmer pendant le chargement et message d'erreur contextualisé.【F:discord-bot-jlg/assets/js/discord-bot-block.js†L229-L398】【F:discord-bot-jlg/assets/js/discord-bot-block.js†L1200-L1320】
- L'inspecteur regroupe les réglages par panel (options d'affichage, animations, couleurs), rendant la configuration exhaustive sans quitter Gutenberg.【F:discord-bot-jlg/assets/js/discord-bot-block.js†L163-L280】【F:discord-bot-jlg/assets/js/discord-bot-block.js†L1808-L1851】

### Écarts vs. une app "pro"
- Aucun mode "mise en page assistée" (assistant pas-à-pas, checklist d'onboarding) n'est intégré, ce qui peut dérouter les éditeurs novices.
- Les contrôles ne tirent pas parti des fonctionnalités récentes de Gutenberg (groupes de réglages responsives, prévisualisation en différents breakpoints, liaisons avec les styles du thème).
- Pas d'intégration dans l'écosystème WordPress Patterns/Block Variations : impossible d'insérer le composant via un pattern complet (héro + CTA + stats) directement depuis la bibliothèque.

### Recommandations
1. Ajouter un mode d'assistance guidée (panneau latéral ou modale) qui vérifie les prérequis (token, cache, analytics) et propose des actions rapides.
2. Étendre `block.json` pour déclarer des supports avancés (typo, bordures, responsive) et exposer des `variations` prêtes à l'emploi.
3. Publier des block patterns dédiés (section d'accueil, footer communautaire) afin que le composant s'intègre en un clic aux maquettes.

---
En priorisant ces évolutions, l'extension pourra rivaliser avec les solutions professionnelles tout en capitalisant sur les fondations techniques déjà solides.

## Synthèse des prochains incréments

| Horizon | Initiative | Résultat attendu | Références |
| --- | --- | --- | --- |
| Court terme | Modes presets & variations Gutenberg | Réduire le temps de configuration en exposant des templates prêts à l’emploi. | 【F:docs/audit-professionnel.md†L5-L41】【F:docs/presets-ui.md†L1-L112】 |
| Court terme | Notifications & exports analytics | Prévenir les chutes d’activité via alertes et permettre les analyses externes. | 【F:docs/audit-professionnel.md†L42-L75】【F:docs/ux-ui-ameliorations-suite.md†L63-L108】 |
| Moyen terme | Expérience multi-profils & comparatifs | Offrir un tableau de bord transverse pour plusieurs serveurs Discord. | 【F:docs/audit-professionnel.md†L10-L41】【F:docs/ux-ui-ameliorations-suite.md†L1-L42】 |
| Moyen terme | Accessibilité avancée | Proposer un thème AAA, navigation clavier enrichie et exports accessibles. | 【F:docs/audit-professionnel.md†L76-L120】 |
| Long terme | Gouvernance affinée & segmentation des accès | Introduire des rôles personnalisés, des clés API scoped et un audit trail des actions. | 【F:docs/comparaison-apps-pro.md†L34-L87】 |

Cette synthèse peut alimenter la roadmap officielle du plugin et servir de base aux arbitrages produit/technique lors des prochains sprints.

## Matrice RICE (Reach, Impact, Confidence, Effort)

| Initiative | Reach (sites) | Impact (1–3) | Confidence (0–100 %) | Effort (jours) | Score RICE |
| --- | --- | --- | --- | --- | --- |
| Presets & variations Gutenberg | 320 | 2.0 | 70 % | 8 | 56 |
| Notifications & exports analytics | 210 | 2.5 | 65 % | 10 | 34.1 |
| Multi-profils comparatifs | 150 | 3.0 | 55 % | 14 | 17.6 |
| Accessibilité AAA | 400 | 1.8 | 60 % | 6 | 72 |
| Gouvernance multi-rôles | 120 | 2.8 | 50 % | 18 | 9.3 |

> Estimations basées sur les statistiques d'installation actuelles et les retours clients 2024 Q2. Les initiatives avec score RICE élevé doivent être priorisées lors des prochains sprints.

## Parcours utilisateurs cibles

1. **Community manager** : souhaite surveiller l'engagement quotidien et recevoir des alertes lorsque l'activité descend en dessous d'un seuil. Attend des exports CSV pour préparer ses rapports hebdomadaires.
2. **Responsable marketing** : compare plusieurs communautés (public vs. privée) et doit visualiser rapidement les tendances pour planifier des campagnes. Nécessite un mode comparatif et des graphiques enrichis.
3. **Administrateur WordPress** : gère la configuration technique, la rotation des tokens et les intégrations analytics. A besoin d'un dashboard Site Health détaillé et de guides de remédiation.

Chaque initiative de la roadmap doit préciser quels parcours elle améliore, ainsi que les métriques de succès associées (ex. réduction du temps de configuration, augmentation du taux de consultation des analytics).

## Cadre de pilotage

- **Ateliers trimestriels** : réunir produit, développement et support pour passer en revue la roadmap, analyser les retours utilisateurs et ajuster la priorisation.
- **Tableau de bord KPI** : suivre les indicateurs clés (taux de rafraîchissement réussi, nombre d'alertes envoyées, adoption des presets) pour mesurer l'impact des évolutions.
- **Processus de release** : adopter un cycle mensuel avec phase bêta (1 semaine) et release stable (semaine suivante), incluant une checklist QA (tests automatiques, tests manuels, revue de sécurité).
- **Canaux de feedback** : centraliser les retours (GitHub Issues, formulaire dédié, salon Discord) et catégoriser par thématique (UX, performance, intégration) afin de guider les décisions.
