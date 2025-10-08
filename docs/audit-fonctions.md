# Audit des fonctions à renforcer

## 1. `Discord_Bot_JLG_API::get_stats()`

*Constat :* cette méthode concentre toute la logique de récupération (cache, appels widget/bot, bascules démo) ainsi que la gestion des erreurs et du verrouillage runtime.【F:discord-bot-jlg/inc/class-discord-api.php†L240-L358】 Cette centralisation rend la fonction difficile à étendre et empêche l’instrumentation fine (traces, métriques) attendue sur des applications professionnelles.

*Pistes inspirées des apps pro :*

- Extraire les appels réseau, la fusion et la persistance dans des services dédiés (pattern « use-case + gateway ») pour pouvoir monitorer chaque étape et appliquer des stratégies de retry ou de circuit-breaker indépendantes.【F:discord-bot-jlg/inc/class-discord-api.php†L334-L358】
- Ajouter de la télémétrie structurée (logs normalisés, événements analytics) avant/après chaque point de sortie pour faciliter l’observabilité en production. _Mise à jour 2024-07 : les nouveaux hooks `discord_bot_jlg_pre_http_request` / `discord_bot_jlg_after_http_request` exposent les métadonnées nécessaires aux métriques externes._
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


## Priorités court terme

| Fonction | Action recommandée | Bénéfice | Références |
| --- | --- | --- | --- |
| `get_stats()` | Découper en services (`ProfileResolver`, `StatsFetcher`, `SnapshotWriter`) et ajouter un logger PSR-3. | Facilite les tests ciblés et l’observabilité temps réel. | 【F:docs/audit-fonctions.md†L3-L33】【F:docs/comparaison-apps-pro.md†L38-L64】 |
| `sanitize_options()` | Passer à une validation déclarative (schéma) avec rotation automatique des tokens. | Sécurise la configuration et prépare la gouvernance multi-profils. | 【F:docs/audit-fonctions.md†L35-L54】 |
| `reschedule_cron_event()` | Introduire un backoff exponentiel et consigner les échecs successifs. | Améliore la résilience face aux quotas API Discord. | 【F:docs/audit-fonctions.md†L56-L71】 |
| `persist_successful_stats()` | Déléguer l’écriture analytics à une file asynchrone (Action Scheduler). | Évite que l’échec du reporting bloque le cache front. | 【F:discord-bot-jlg/inc/class-discord-api.php†L552-L582】 |

Ces actions sont alignées avec la comparaison « apps pro » et peuvent être traitées indépendamment pour livrer de la valeur rapidement.
