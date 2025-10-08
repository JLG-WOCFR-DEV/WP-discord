# Revue de code & opportunités de refactoring

## Vue d'ensemble
Le plugin « Discord Bot - JLG » fournit une intégration très complète (cron, REST API, widget, bloc Gutenberg, analytics, journalisation…). Les fonctionnalités sont riches, mais plusieurs classes centrales sont devenues monolithiques ; elles mêlent logique métier, orchestration WordPress et couches techniques (HTTP, cache, analytics). Cela complique la maintenance et limite la testabilité.

## Points à refactorer en priorité

### 1. Service principal trop volumineux (`Discord_Bot_JLG_API`)
* Le service d'accès aux statistiques dépasse 3 200 lignes et regroupe des responsabilités hétérogènes : lecture/écriture des options, gestion du cache transient, orchestration des appels HTTP, gestion des retours d'erreur, journalisation et pilotage des analytics.【F:discord-bot-jlg/inc/class-discord-api.php†L15-L246】【F:discord-bot-jlg/inc/class-discord-api.php†L600-L849】【F:discord-bot-jlg/inc/class-discord-api.php†L2137-L2219】
* Recommandation : extraire des classes dédiées (par exemple `OptionsRepository`, `CacheManager`, `DiscordConnector`, `RefreshPolicy`, `AnalyticsLogger`). Injecter ces services dans une façade plus fine réduit la taille de chaque fichier et facilite les tests unitaires ciblés.

### 2. Classe d'administration monolithique (`Discord_Bot_JLG_Admin`)
* L'intégralité des écrans, sections, callbacks de sanitisation et rendu HTML est centralisée dans une unique classe de 2 300+ lignes.【F:discord-bot-jlg/inc/class-discord-admin.php†L8-L155】
* Recommandation : séparer l'enregistrement des réglages de leur rendu, introduire des objets « Field » ou des classes par section (ex. `ProfilesSettings`, `DisplaySettings`). Cela permettra de couvrir chaque bloc par des tests unitaires simples et de limiter l'impact des évolutions UI.

### 3. Bootstrap & hooks globaux
* Le fichier principal gère à la fois la définition des constantes, les `require_once`, la désinstallation, le wiring des dépendances, l'enregistrement des hooks et la configuration WP‑CLI.【F:discord-bot-jlg/discord-bot-jlg.php†L33-L152】【F:discord-bot-jlg/discord-bot-jlg.php†L160-L215】
* Recommandation : déplacer la logique de bootstrap dans une classe dédiée (ex. `PluginServiceProvider`) et remplacer les multiples `require_once` par un autoloader PSR‑4. La fonction `discord_bot_jlg_uninstall()` pourrait déléguer la purge des tables/cron à des services réutilisés par l'activation/désactivation pour éviter la duplication.【F:discord-bot-jlg/discord-bot-jlg.php†L64-L120】

### 4. Module d'aides utilitaires trop générique
* `inc/helpers.php` mélange des responsabilités variées : thèmes UI, sanitisation de couleurs, encodage de secrets, helpers de date, validations booléennes…【F:discord-bot-jlg/inc/helpers.php†L14-L160】
* Recommandation : regrouper ces helpers par domaine (`ThemeOptions`, `ColorSanitizer`, `TokenCipher`, etc.) ou les intégrer directement dans les classes consommatrices afin de réduire le couplage global.

## Nettoyage & dette technique

1. **Adopter un autoloader/composer** : en introduisant `composer.json` et un namespace, on élimine les `require_once` conditionnels éparpillés et on clarifie les dépendances PHP. Cela simplifierait aussi les tests (mocking plus naturel).
2. **Isoler la configuration cron** : `discord_bot_jlg_register_cron_schedule()` et la gestion de l'intervalle pourraient être encapsulées dans un service de scheduling, ce qui faciliterait l'ajustement dynamique du cron et éviterait les accès directs aux options dans plusieurs fichiers.【F:discord-bot-jlg/inc/cron.php†L12-L61】
3. **Réduire les dépendances globales** : la classe `DiscordServerStats` instancie directement analytics, event logger et API, ce qui rend les tests end-to-end indispensables. En acceptant ces dépendances via le constructeur ou un container, il deviendrait possible de substituer des doubles de test.【F:discord-bot-jlg/discord-bot-jlg.php†L173-L215】
4. **Nettoyage du dépôt JS** : le dépôt versionne `node_modules`, alors que `package.json` n'embarque que Jest et `jest-environment-jsdom` pour les tests.【025793†L1-L3】【F:package.json†L2-L17】 Ajouter `node_modules/` au `.gitignore` et installer les dépendances à la demande allégerait drastiquement le repo.

## Gains attendus
* **Lisibilité accrue** : chaque composant aurait une responsabilité claire, ce qui réduit le temps nécessaire pour comprendre ou modifier une fonctionnalité.
* **Testabilité** : des classes plus petites et injectées rendent possible la couverture unitaire des scénarios critiques (rate limiting, fallback, sanitisation des options) sans devoir charger l'ensemble de WordPress.
* **Maintenance** : la séparation des couches évite que des changements dans le front (ex. nouveaux champs de formulaire) n'impactent la logique réseau ou le suivi analytique.

> Mise à jour 2024-07 : une première étape d’instrumentation est en place via les hooks `discord_bot_jlg_pre_http_request`, `discord_bot_jlg_after_http_request` et le filtre `discord_bot_jlg_discord_http_event_context`, ce qui facilite l’agrégation de métriques externes en attendant l’extraction complète des services.

## Étapes suggérées
1. Introduire un autoloader et définir un namespace de base (`DiscordBotJLG\`).
2. Extraire progressivement les responsabilités principales (`OptionsRepository`, `CacheManager`, `DiscordHttpClient`, `SettingsPage`…).
3. Couvrir les services extraits par des tests ciblés (unitaires côté PHP, tests d'intégration limités pour les hooks WordPress).
4. Nettoyer le dépôt (`.gitignore` pour `node_modules/`, documentation de la stack de build/test).

En procédant par itérations (extraction d'un service à la fois), le refactoring restera maîtrisé tout en apportant des bénéfices immédiats sur la qualité du code.

## Plan d'action court terme

| Statut | Étape | Description | Livrables associés |
| --- | --- | --- | --- |
| ⏳ À planifier | Mettre en place un autoloader PSR-4 | Déplacer les `require_once` vers Composer et introduire un `PluginServiceProvider` pour centraliser le bootstrap. | Schéma d’autoload + documentation d’installation.【F:docs/code-review.md†L25-L43】 |
| ⏳ À planifier | Dédier des services au cache et aux appels HTTP | Extraire `CacheManager` et `DiscordHttpClient` afin d’alléger `Discord_Bot_JLG_API` et d’autoriser le mocking. | Nouveaux services + tests unitaires ciblés.【F:docs/code-review.md†L15-L33】 |
| 🛠️ Préparation | Segmenter l’administration en sous-modules | Créer des classes par section d’écran (Profils, Présentation, Analytics) avec vues dédiées. | Carte des écrans + plan de migration.【F:docs/code-review.md†L35-L48】 |
| ✅ Prêt pour dev | Nettoyer le dépôt JS | Retirer `node_modules/` du suivi Git, documenter l’installation et automatiser les tests Jest. | `.gitignore` mis à jour + guide contributeur.【F:docs/code-review.md†L63-L66】 |

Ce plan servira de checklist lors des prochains cycles de développement. Les statuts sont à mettre à jour au fur et à mesure des livraisons.

## Architecture cible (vision 2024 Q4)

Pour converger vers un socle proche des standards PSR, l'architecture applicative du plugin peut être organisée autour des couches suivantes :

1. **Domaine** : objets métier (`Profile`, `StatsSnapshot`, `RefreshSchedule`) responsables des invariants. Ces classes ne connaissent pas WordPress et peuvent être testées de manière isolée.
2. **Application/Use-cases** : services comme `FetchStats`, `RotateSecrets`, `RescheduleRefresh` qui orchestrent la logique métier en s'appuyant sur des ports (interfaces) décrivant les dépendances externes.
3. **Infrastructure** : implémentations des ports (`WpOptionsRepository`, `TransientCache`, `DiscordHttpClient`, `WpCronScheduler`) qui encapsulent l'API WordPress, HTTP et les librairies tierces.
4. **Interface** : adaptateurs pour l'administration, le bloc Gutenberg, les shortcodes et REST endpoints. Cette couche se contente de mapper les requêtes utilisateur vers les use-cases.

Le bootstrap (`PluginServiceProvider`) assemble ces briques en injectant les implémentations concrètes et en exposant les hooks nécessaires. Cette découpe permet de cibler progressivement les refactors : chaque extraction consiste à introduire un port dans la couche application, puis à migrer la logique existante de la classe monolithique vers une implémentation infrastructure.

## Checklist de migration par lot

| Lot | Objectif | Tâches clés | Livrables |
| --- | --- | --- | --- |
| L1 – Options & secrets | Isoler la gestion des options et sécuriser les tokens | Créer `OptionsRepository`, déplacer lecture/écriture des options, introduire un chiffrage via Sodium | Nouvelle classe + tests unitaires, documentation de rotation | 
| L2 – Cache & verrous | Centraliser les transients et verrous anti-concurrence | Implémenter `CacheManager`, déléguer les accès à `get_transient`/`set_transient`, ajouter un verrou redistribuable | Classe `CacheManager`, tests sur TTL/verrous, hooks d'observabilité |
| L3 – Connecteur Discord | Encapsuler les appels HTTP, retries et instrumentation | Créer `DiscordHttpClient`, déplacer la logique `wp_remote_request`, gérer `Retry-After` et instrumentation PSR-3 | Client dédié, tests de résilience (mocks HTTP), logs structurés |
| L4 – Analytics & journal | Sortir la persistance analytics et les événements REST | Définir `AnalyticsLogger`, migrer `persist_successful_stats` et le journal REST, préparer l'export CSV | Classes analytics, endpoint REST refactoré, script d'export |
| L5 – Interfaces admin | Segmenter les écrans d'administration | Introduire des sous-modules (`ProfilesSettings`, `DisplaySettings`), migrer les callbacks, ajouter tests d'intégration | Nouvelles classes UI, couverture Jest/React, guide de contribution |

Chaque lot doit être livré avec :

- **Tests automatisés** couvrant la nouvelle classe et les régressions critiques.
- **Documentation** dans `docs/` (diagramme de séquence, guide d'intégration).
- **Journal de migration** (notes dans CHANGELOG ou README) pour faciliter les retours arrière.

## Indicateurs de réussite

- **Couverture unitaire** : atteindre au moins 70 % de couverture sur les nouvelles classes extraites (`OptionsRepository`, `CacheManager`, `DiscordHttpClient`).
- **Temps moyen d'exécution des tests** : rester sous 90 s sur la suite PHPUnit pour garantir un feedback rapide en CI.
- **Complexité cyclomatique** : réduire la complexité des méthodes critiques (`Discord_Bot_JLG_API::get_stats`, `Discord_Bot_JLG_Admin::render_settings_page`) en dessous de 15 via les extractions proposées.
- **Stabilité** : zéro régression signalée dans les logs Site Health (`discord_bot_jlg`) sur deux cycles de release consécutifs après refactor.

## Suivi opérationnel

Mettre en place un tableau de bord (Notion, Jira ou GitHub Projects) reprenant les lots ci-dessus, avec pour chaque tâche :

- Description, critères d'acceptation, tests à exécuter.
- Estimation (en points ou en jours) et dépendances identifiées.
- Checklists de revue (code review, QA, documentation) pour sécuriser les merges.

Un point hebdomadaire de synchronisation entre développeurs backend, frontend et QA permettra d'ajuster la priorisation et d'identifier en amont les impacts croisés (ex. bloc Gutenberg vs. API REST).
