# Audit des fonctions à renforcer

## 1. `Discord_Bot_JLG_API::get_stats()`

*Constat :* cette méthode concentre toute la logique de récupération (cache, appels widget/bot, bascules démo) ainsi que la gestion des erreurs et du verrouillage runtime.【F:discord-bot-jlg/inc/class-discord-api.php†L240-L358】 Cette centralisation rend la fonction difficile à étendre et empêche l’instrumentation fine (traces, métriques) attendue sur des applications professionnelles.

*Pistes inspirées des apps pro :*

- Extraire les appels réseau, la fusion et la persistance dans des services dédiés (pattern « use-case + gateway ») pour pouvoir monitorer chaque étape et appliquer des stratégies de retry ou de circuit-breaker indépendantes.【F:discord-bot-jlg/inc/class-discord-api.php†L334-L358】
- Ajouter de la télémétrie structurée (logs normalisés, événements analytics) avant/après chaque point de sortie pour faciliter l’observabilité en production.
- Déporter les rafraîchissements lourds dans une file asynchrone (cron, queue, Action Scheduler) afin de ne pas bloquer les requêtes front-office tout en conservant des garanties de fraîcheur similaires aux bots professionnels.

## 2. `Discord_Bot_JLG_Admin::sanitize_options()`

*Constat :* la méthode valide et nettoie toutes les options mais repose sur l’état précédent pour conserver les secrets, sans re-chiffrement systématique ni audit des profils multiples.【F:discord-bot-jlg/inc/class-discord-admin.php†L283-L472】 Les solutions pro appliquent souvent des politiques de rotation des secrets, de validation schématique et d’isolement par profil.

*Pistes inspirées des apps pro :*

- Introduire une couche de validation déclarative (par ex. objets Value Object ou librairie de schémas) afin d’éviter les nombreux `isset`/`sanitize_*` dispersés.【F:discord-bot-jlg/inc/class-discord-admin.php†L330-L416】
- Assurer la rotation/expiration automatique des tokens en enregistrant la date de chiffrement et en forçant leur renouvellement passé un délai (pratique courante sur les intégrations SaaS).
- Séparer la persistance des profils serveur dans une structure dédiée (CPT/options par profil) pour faciliter le multi-tenant et appliquer des contrôles d’accès granulaires, comme le proposent les dashboards pro.

## 3. `DiscordServerStats::reschedule_cron_event()`

*Constat :* le replanificateur réinitialise simplement le hook puis programme un nouvel événement basé sur `time()`, sans tenir compte des exécutions concurrentes ni des échecs précédents.【F:discord-bot-jlg/discord-bot-jlg.php†L401-L417】 Les produits professionnels combinent généralement planification idempotente, files tampon et backoff exponentiel.

*Pistes inspirées des apps pro :*

- Enregistrer l’horodatage de la dernière exécution réussie et mettre en place un backoff adaptatif en cas d’échec répété au lieu de reprogrammer systématiquement après `time()+interval`.
- Utiliser un système de jobs (Action Scheduler, queues Redis) pour éviter les doublons de cron si plusieurs sites partagent la même configuration, et suivre chaque tentative via des métriques consolidées.
- Vérifier l’état du verrou côté API (`Discord_Bot_JLG_API`) avant planification afin de prévenir les chevauchements de rafraîchissements, à l’image des orchestrateurs de bots professionnels.

## Plan d’action priorisé

| Priorité | Action | Livrable attendu | Statut |
| --- | --- | --- | --- |
| 🚨 Haute | Extraire un service `StatsRefreshJob` qui encapsule la logique de cron et le verrouillage pour permettre l’ajout d’un backoff exponentiel configurable.【F:discord-bot-jlg/discord-bot-jlg.php†L394-L417】 | Nouvelle classe + tests d’intégration cron | À cadrer |
| 🚨 Haute | Scinder `Discord_Bot_JLG_API::get_stats()` en façade + connecteurs HTTP séparés (widget/bot) avec instrumentation PSR-3 pour suivre les échecs et la latence.【F:discord-bot-jlg/inc/class-discord-api.php†L240-L358】 | Services dédiés + journalisation structurée | À cadrer |
| ⚠️ Moyenne | Introduire un gestionnaire de profils (`ProfilesRepository`) afin de sortir la persistance des tokens de la méthode `sanitize_options()` et préparer le chiffrement applicatif.【F:discord-bot-jlg/inc/class-discord-admin.php†L283-L472】 | Classe repository + migration d’option | À prioriser |
| ⚠️ Moyenne | Ajouter des hooks d’observabilité (actions/filters) autour des appels Discord pour brancher des compteurs Prometheus ou des webhooks d’alerte.【F:discord-bot-jlg/inc/class-discord-api.php†L1991-L2133】 | Hooks documentés + échantillons de métriques | À prioriser |
| ✅ Faible | Documenter un scénario de tests automatisés couvrant la fusion `merge_stats()` avec des fixtures multi-sources pour préparer l’extraction en stratégie pluggable.【F:discord-bot-jlg/inc/class-discord-api.php†L444-L538】 | Cas de tests + checklist QA | En cours de rédaction |

> ℹ️ Statuts mis à jour le 2024-07-02. Synchroniser cette table avec le plan global (`docs/code-review.md`) à chaque sprint.

