# Synthèse des refontes prioritaires

## Refontes techniques critiques

L'audit combiné de `get_stats()`, `sanitize_options()` et du cron de rafraîchissement confirme la nécessité d'extraire des services dédiés et de définir des stratégies de résilience (backoff, circuit breaker, queue) avant toute nouvelle fonctionnalité. Ces actions servent de prérequis pour fiabiliser le cache, sécuriser la gestion des secrets et aligner la planification sur les contraintes de production.【F:docs/audit-fonctions.md†L3-L71】【F:discord-bot-jlg/docs/improvement-plan.md†L6-L71】

## Alignement avec les solutions professionnelles

La comparaison avec les suites professionnelles identifie trois écarts majeurs — multi-tenant, observabilité temps réel et gestion avancée des secrets — et propose des chantiers concrets pour converger vers les standards SaaS (isolation par profil, exports métriques, rotation automatisée). Ces axes doivent accompagner les refontes techniques pour livrer une expérience comparable aux offres enterprise.【F:docs/comparaison-apps-pro.md†L1-L142】
