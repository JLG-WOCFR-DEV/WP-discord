# Priorités de développement – mise à jour Q3 2024

Cette note complète la synthèse présente dans `docs/comparaison-apps-pro.md` et formalise
les chantiers qui doivent être engagés en priorité pour rapprocher le plugin des pratiques
professionnelles. Elle inclut également une revue des manques par rapport à la roadmap
interne et à la concurrence directe (Orbit, Common Room, Commsor, suites SaaS de
monitoring Discord).

## 1. Priorités opérationnelles immédiates

| Priorité | Sujet | Objectifs détaillés | Livrables attendus |
| --- | --- | --- | --- |
| 🔴 Haute | **Résilience du connecteur Discord** | Implémenter un backoff exponentiel conscient des en-têtes Discord (`Retry-After`), introduire un circuit breaker par profil et consigner chaque tentative dans le journal REST. | Service `DiscordHttpClient` isolé, plan de tests rate-limit, alertes Site Health enrichies. |
| 🔴 Haute | **Rotation planifiée des secrets** | Déporter les tokens dans un stockage par profil, automatiser les rappels de rotation et documenter une procédure CLI/cron. | Table `wp_discord_bot_tokens`, commande `wp discord-bot rotate-token`, guide d’exploitation. |
| 🟠 Moyenne | **Exports analytics & diffusion** | Publier des exports CSV/JSON paginés, proposer des webhooks de notification et des presets de comparaison multi-profils. | Endpoint REST `/analytics/export`, planificateur de diffusion, composants UI Gutenberg mis à jour. |
| 🟠 Moyenne | **Segmentation des accès** | Étendre les nouvelles capacités (`manage_discord_profiles`, `view_discord_analytics`) avec des clés API limitées par profil et un audit trail. | API de gestion des clés, journal d’accès, matrice d’autorisations documentée. |
| 🟡 Basse | **Design system & presets** | Industrialiser les presets UI (Headless, Shadcn, Radix) via les variations Gutenberg et la documentation associée. | Variations de bloc, tokens CSS, bibliothèque de screenshots de référence. |

Ces priorités prolongent le backlog établi dans `docs/comparaison-apps-pro.md` en apportant un
niveau de détail suffisant pour lancer le développement (définition des livrables et des
attendus QA).

## 2. Points manquants par rapport à la roadmap interne

La roadmap 2024 fixe plusieurs jalons (lots L1 à L5). Les écarts actuels sont :

1. **Lot L1 – Options & secrets** : l’isolation des tokens par profil n’est pas entamée et
   aucun outil de rotation n’est disponible. Les notices d’alerte existent mais ne déclenchent
   pas d’action automatisée.
2. **Lot L2 – Cache & verrous** : la logique de replanification du cron repose encore sur
   `time() + interval` sans backoff ni verrou distribué, ce qui expose aux collisions lors des
   incidents API.
3. **Lot L3 – Connecteur Discord** : le client HTTP n’est pas externalisé ; les stratégies
   de retry, de circuit breaker et la télémétrie PSR-3 restent à concevoir.
4. **Lot L4 – Analytics & journal** : les exports CSV/JSON sont encore en préparation et
   aucun webhook n’est disponible pour diffuser les alertes.
5. **Lot L5 – Interfaces admin** : les écrans demeurent monolithiques ; la segmentation par
   profils et les presets UI n’ont pas été livrés.

## 3. Écarts face à la concurrence

En comparant le plugin aux suites professionnelles identifiées, on observe encore les écarts
suivants :

- **Supervision centralisée** : absence de pipelines Prometheus/OpenTelemetry et d’intégrations
  webhook natives pour alimenter des SOC/SIEM.
- **Multi-tenancy avancé** : stockage mutualisé des profils et des tokens, là où les solutions
  concurrentes offrent des espaces par locataire avec cloisonnement complet et audit trail.
- **Expérience analytics** : pas de comparaisons multi-profils, pas de timeline interactive ni
  d’exports prêts à l’usage pour les équipes marketing ou produit.
- **Gouvernance des accès** : modèle de permissions encore global, sans délégation fine ni clés
  API limitées par périmètre.
- **Design system** : presets et variations UI encore manuels, alors que les concurrents
  fournissent des templates packagés avec démonstrations en temps réel.

La priorisation ci-dessus cible ces écarts pour réduire l’écart fonctionnel et préparer la
livraison des lots roadmap.
