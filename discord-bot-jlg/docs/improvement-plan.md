# Plan d'amélioration des fonctions critiques

Ce document recense les zones du plugin qui gagneraient à être rapprochées des pratiques usuelles observées dans des applications professionnelles (WordPress SaaS, dashboards de monitoring temps réel, etc.). Pour chaque fonction, nous listons les limites actuelles et les axes d'amélioration possibles.

## `Discord_Bot_JLG_API::get_stats()`
* **Complexité excessive** : la méthode orchestre la validation des arguments, la résolution du profil, l'accès au cache, les appels HTTP (widget/bot), la fusion des réponses et la persistance des résultats dans un même bloc de plus de 100 lignes. Les projets pro privilégient une séparation claire (patrons « query service », « repository », middleware de résilience) afin de réduire les effets de bord et faciliter les tests unitaires ciblés.【F:discord-bot-jlg/inc/class-discord-api.php†L240-L360】
* **Résilience perfectible** : l'unique point de sortie pour les erreurs retourne une charge démo, mais il n'existe pas de stratégie de repli graduelle (ex. circuit breaker, quotas par profil, métriques Prometheus) pourtant courante dans les intégrations tierces. Factoriser la gestion des erreurs/rate limiters dans un composant dédié permettrait de suivre le débit réel et de déclencher des alertes proactives.
* **Observabilité** : aucun hook ou journal structuré n'est exposé avant/après chaque appel distant. Les plateformes pro tracent les échecs via des interfaces (`PSR-3 Logger`, `OpenTelemetry`) pour corréler les anomalies et identifier les profils concernés.

## `Discord_Bot_JLG_API::merge_stats()`
* **Règles métiers figées** : la fusion des statistiques donne la priorité au widget sans vérifier l'ancienneté, la cohérence des totaux ou les conflits entre sources. Les solutions professionnelles stockent généralement des horodatages et choisissent la source la plus fraîche ou appliquent une pondération configurable.【F:discord-bot-jlg/inc/class-discord-api.php†L444-L538】
* **Agrégation limitée** : la répartition des présences est simplement sommée, sans normalisation des clés (`online`, `dnd`, etc.) ni contrôle des doublons. Une table de correspondance configurable, des totaux recalculés et une validation des pourcentages éviteraient les écarts constatés sur des dashboards pro.
* **Extensibilité** : la méthode est `private`, ce qui rend difficile l'introduction d'une stratégie alternative (ex. priorité au bot pour les serveurs privés). Rendre la fusion surchargable (Strategy/Filter) alignerait le code avec les plugins premium qui permettent de personnaliser la consolidation des métriques.

## `DiscordServerStats::handle_settings_update()`
* **Réactions en cascade** : la fonction mélange purge du cache, replanification cron, gestion de la rétention analytics et comparaison de profils. Les applications pro séparent ces responsabilités (pattern « domain events ») pour prévenir les régressions lors de l'ajout d'une nouvelle option.【F:discord-bot-jlg/discord-bot-jlg.php†L470-L528】 
* **Manque de rétroaction** : aucun audit log ou notification d'administration n'est produit lors des changements critiques (token, ID serveur). Les solutions pro consignent ces changements et, idéalement, invalident les sessions concernées.
* **Testabilité** : la dépendance directe à l'API WordPress (`wp_clear_scheduled_hook`, `wp_schedule_event`) empêche l'injection de doubles de test. Introduire une couche d'abstraction (service scheduler) faciliterait les tests d'intégration de haut niveau.

## `Discord_Bot_JLG_Admin::sanitize_options()`
* **Méthode monolithique** : près de 200 lignes gèrent la validation de chaque champ. Les projets pro factorisent le nettoyage (ex. map de callbacks par option, Value Objects) pour fiabiliser l'ajout d'options et limiter les régressions lors des migrations.【F:discord-bot-jlg/inc/class-discord-admin.php†L300-L520】
* **Règles dispersées** : la logique `min/max` est dupliquée (cache vs refresh interval) alors qu'un validateur centralisé améliorerait la cohérence. Une configuration déclarative (schéma JSON ou `Settings API` custom) est plus proche des standards des extensions premium.
* **Sécurité renforçable** : l'encryptage du token bot est déclenché à l'enregistrement, mais aucune journalisation ni rotation automatique n'est proposée. Les outils pro déclenchent des alertes en cas d'échec et offrent une rotation assistée via UI/CLI.

## `Discord_Bot_JLG_API::persist_successful_stats()`
* **Couplage fort avec l'analytics** : l'écriture en base et le logging analytics sont imbriqués. Les applications pro utilisent des bus d'événements ou des jobs asynchrones pour éviter que des erreurs de reporting n'empêchent le cache d'être écrit.【F:discord-bot-jlg/inc/class-discord-api.php†L552-L582】
* **Absence de métadonnées temporelles** : seul l'instant de mise en cache est implicite. En production, on stocke souvent un horodatage, la latence des appels et la source (widget/bot) pour diagnostiquer les incohérences.
* **Observabilité** : pas de métriques sur la fraîcheur des snapshots ni de limites pour éviter un flood analytics. Ajouter des quotas et une télémétrie compatible StatsD/Prometheus rapprocherait le plugin des pratiques entreprises.

## Synthèse & prochaines étapes

| Action | Objectif | Dépendances | Échéance cible | Statut |
| --- | --- | --- | --- | --- |
| Décomposer `get_stats()` en orchestrateur + services spécialisés | Réduire la complexité cyclomatique, introduire instrumentation PSR-3 | Décision d’architecture (docs/code-review.md) | Sprint +1 | À planifier |
| Introduire un scheduler résilient (`StatsRefreshJob`) | Assurer backoff, idempotence et observabilité des rafraîchissements | Extraction cron (`docs/audit-fonctions.md`) | Sprint +2 | À planifier |
| Créer un dépôt de profils sécurisé | Chiffrer/rotater les tokens, préparer le multi-tenant | Choix stockage (table custom vs CPT) | Sprint +3 | À cadrer |
| Étendre l’API analytics (historique présence, annotations) | Supporter les fonctionnalités UX (comparatif, timeline enrichie) | Alignement avec `docs/ux-ui-ameliorations-suite.md` | Sprint +3 | Dépend du refactoring |
| Mettre en place une télémétrie exportable | Alimenter Prometheus/webhooks pour observabilité | Choix outillage (Stack SRE) | Sprint +4 | À cadrer |

> Mise à jour : 2024-07-02 — synchroniser cette table avec `docs/audit-professionnel.md` et `docs/audit-fonctions.md` pour conserver une vue cohérente produit/technique.
