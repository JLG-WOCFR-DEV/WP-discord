# Revue des fonctionnalités non intégrées

## Orchestration des rafraîchissements
- **Attendu** : introduire un backoff exponentiel et journaliser les échecs successifs dans `reschedule_cron_event()` afin d’aligner le cron sur les pratiques observées dans les apps professionnelles.【F:docs/audit-fonctions.md†L34-L41】
- **Constat actuel** : la méthode `reschedule_cron_event()` se contente de purger le hook puis de programmer un nouvel événement à `time() + interval` sans prise en compte des erreurs précédentes ni d’un délai adaptatif.【F:discord-bot-jlg/discord-bot-jlg.php†L386-L422】
- **Impact** : absence de stratégie de backoff lors des indisponibilités API, ce qui peut saturer les quotas Discord et empêcher le respect des SLA évoqués dans la roadmap.

## Timeline analytics enrichie
- **Attendu** : ajouter une timeline Chart.js avancée (sélection de plage temporelle, exports CSV/PNG, annotations) pour rapprocher l’UX des tableaux de bord professionnels.【F:docs/ux-ui-ameliorations-suite.md†L84-L117】
- **Constat actuel** : le panneau admin trace toujours trois séries linéaires fixes sans contrôles de plage, export ni annotations, via un simple appel `new Chart` alimenté par la timeserie brute.【F:discord-bot-jlg/assets/js/discord-bot-jlg-admin-analytics.js†L200-L297】
- **Impact** : les équipes analytics ne peuvent ni comparer des périodes ni générer des exports directement depuis l’interface, ce qui freine les cas d’usage métier cités dans le programme.

## Observabilité & diffusion temps réel
- **Attendu** : exposer des métriques exportables (Prometheus/OpenTelemetry) et des webhooks d’alerte afin de sortir les données d’observabilité du plugin et alimenter des outils externes.【F:docs/comparaison-apps-pro.md†L5-L40】
- **Constat actuel** : le contrôleur REST ne publie que les routes `stats`, `analytics`, `events` et `analytics/export` ; aucune route `metrics`, webhook ou format compatible Prometheus n’est disponible.【F:discord-bot-jlg/inc/class-discord-rest.php†L1-L184】
- **Impact** : l’observabilité reste confinée à WordPress, ce qui empêche la supervision centralisée et les alertes automatisées prévues par le programme professionnel.

## Rotation automatisée des secrets
- **Attendu** : mettre en place une rotation automatique ou assistée des tokens (coffres par profil, déclencheurs planifiés) plutôt que de se limiter à un simple horodatage.【F:docs/comparaison-apps-pro.md†L5-L33】
- **Constat actuel** : l’interface admin se borne à afficher une notice listant les tokens dépassant le seuil et invite l’opérateur à les réenregistrer manuellement, sans job de rotation ni segmentation par profil.【F:discord-bot-jlg/inc/class-discord-admin.php†L1051-L1118】
- **Impact** : la gouvernance des secrets reste manuelle et mutualisée, loin des exigences SOC2/ISO décrites dans le programme.

## Presets Gutenberg documentés
- **Attendu** : ajouter des variations/presets sélectionnables dans Gutenberg (`theme_preset`, variations de bloc) pour capitaliser sur les styles Headless/Shadcn/Radix recensés dans la documentation design.【F:docs/presets-ui.md†L90-L117】
- **Constat actuel** : le schéma du bloc expose uniquement l’attribut `theme` et ne propose ni variations ni contrôle dédié aux presets listés, ce qui oblige à paramétrer manuellement chaque instance.【F:discord-bot-jlg/block/discord-stats/block.json†L1-L120】
- **Impact** : l’éditeur doit reproduire les configurations à la main, ce qui rallonge le temps de mise en page par rapport aux objectifs du programme.
