# Presets graphiques inspirés de Headless UI, Shadcn UI, Radix UI, Bootstrap, Semantic UI et Anime.js

Ce document rassemble plusieurs pistes de presets graphiques pouvant être intégrés à l'interface du plugin (page d'administration, widgets front-end, documentation). Chaque preset reprend les principes d'une bibliothèque populaire tout en restant compatible avec l'écosystème WordPress (variables CSS, composants modulaires, support du mode réduit en mouvement).

## 1. Preset « Headless Essence » (inspiré de Headless UI)
- **Philosophie** : composants sans styles imposés, accent sur la structure et l'accessibilité. Chaque module expose des classes utilitaires prêtes pour Tailwind ou du CSS personnalisé.
- **Identifiant plugin** : `headless` (`.discord-stats-container.discord-theme-headless`).
- **Palette** : gris froid (#0f172a, #1e293b, #334155) avec accent bleu ardoise (#3b82f6) et focus visibles (#f97316).
- **Typographie** : `Inter`, `system-ui` ou équivalent, tailles fluides (`clamp(0.95rem, 1vw + 0.5rem, 1.1rem)`).
- **Composants clés** :
  - Navigation verticale avec transitions discrètes (`transition-colors`, `focus:ring`).
  - Panneaux accordéon (Disclosure) en CSS pur : `details/summary` stylés, icône rotative animée.
  - Modale/Boîte de dialogue accessible (overlay semi-opaque, `aria-modal="true"`).
- **Animations** : fade/scale doux (`transform: scale(0.98)` -> `scale(1)`) en 150 ms, option `prefers-reduced-motion` pour désactiver.
- **Utilisation suggérée** : pages d'options avancées, affichage des stats en liste hiérarchique.

## 2. Preset « Shadcn Minimal » (inspiré de shadcn/ui)
- **Philosophie** : composants Tailwind pré-stylés avec variantes via classes data-attributes (`data-state="open"`).
- **Identifiant plugin** : `shadcn` (`.discord-stats-container.discord-theme-shadcn`).
- **Palette** : neutres chauds (#f8fafc, #e2e8f0, #64748b) et accent vert céladon (#34d399). Mode sombre automatique (`@media (prefers-color-scheme: dark)`).
- **Typographie** : `Geist`, `Satoshi` ou fallback `Inter`, titres en `font-semibold`.
- **Composants clés** :
  - `Card` modulaires avec header, description, footer d'actions.
  - `Tabs` horizontaux, curseur animé sous l'onglet actif (`before:` pseudo-élément).
  - `Toast`/notifications collantes alignées sur le coin inférieur droit, timers progressifs.
- **Animations** : `transition-all` 200 ms, `data-state` -> slide/fade (ex. `translate-y-2` -> `translate-y-0`).
- **Utilisation suggérée** : tableau de bord analytics, onboarding pas à pas.

## 3. Preset « Radix Structure » (inspiré de Radix UI)
- **Philosophie** : neutralité chromatique, composant piloté par `data-state`/`data-side`, accent sur la cohérence entre les plateformes.
- **Identifiant plugin** : `radix` (`.discord-stats-container.discord-theme-radix`).
- **Palette** : dégradés gris-bleu (`--surface: #18181b`, `--surface-alt: #27272a`, `--accent: #6366f1`), contrastes élevés pour l'accessibilité.
- **Typographie** : `Work Sans` pour titres (`600`), `IBM Plex Sans` pour contenu.
- **Composants clés** :
  - `Popover` contextuel avec flèche CSS, ombre douce (`box-shadow: 0 10px 40px rgba(15,23,42,0.35)`).
  - `Slider` multi-curseurs (pour configurer les intervalles de rafraîchissement).
  - `Context menu` pour actions rapides sur les profils de serveurs.
- **Animations** : keyframes directionnelles (`[data-side="top"] { animation: slideDown 200ms ease; }`).
- **Utilisation suggérée** : configuration granulaire, menus contextualisés dans le bloc Gutenberg.

## 4. Preset « Bootstrap Fluent » (inspiré de Bootstrap 5)
- **Philosophie** : grille responsive, composants classiques (cards, alertes, badges) avec design modernisé (angles de 0.75rem).
- **Identifiant plugin** : `bootstrap` (`.discord-stats-container.discord-theme-bootstrap`).
- **Palette** : bleu primaire (#0d6efd), success (#198754), danger (#dc3545), warning (#ffc107), background clair (#f8f9fa). Mode sombre via classes `.theme-dark`.
- **Typographie** : `"Helvetica Neue", Arial, sans-serif`, titres en uppercase légère (`text-transform: uppercase; letter-spacing: 0.08em`).
- **Composants clés** :
  - `Navbar` sticky avec brand, boutons CTA (ex: « Actualiser les stats »).
  - `Progress`/barres empilées pour visualiser les quotas d'API.
  - `Modal` et `Tooltip` compatibles `data-bs-*` (peuvent être reproduits en vanilla JS).
- **Animations** : transitions standard de Bootstrap (`transition: all .2s ease-in-out;`), boutons hover `filter: brightness(1.05)`.
- **Utilisation suggérée** : pages de configuration générales, compatibilité thématique avec WordPress classique.

## 5. Preset « Semantic Harmony » (inspiré de Semantic UI)
- **Philosophie** : langage visuel riche, classes sémantiques (`.ui.primary.button`, `.ui.segment`).
- **Identifiant plugin** : `semantic` (`.discord-stats-container.discord-theme-semantic`).
- **Palette** : base pastel (primary #2185d0, secondary #a333c8, positive #21ba45, negative #db2828), arrière-plan `#f9fafb`.
- **Typographie** : `Lato` (400, 700), `line-height` généreux (1.6).
- **Composants clés** :
  - `Segments` et `Items` list pour présenter les profils Discord.
  - `Steps` horizontaux pour guider la configuration initiale.
  - `Statistic` avec compteurs XXL et icônes alignées.
- **Animations** : transitions `ease-in-out` 250 ms, `transform-origin` pour les `Steps` (zoom léger). Effets `shimmer` optionnels via `@keyframes shimmer`.
- **Utilisation suggérée** : onboarding, page d'aide visuelle ou tutoriels.

## 6. Preset « Anime Pulse » (inspiré d'Anime.js)
- **Philosophie** : accent sur les micro-animations orchestrées (séquences, timelines), tout en respectant le critère `prefers-reduced-motion`.
- **Identifiant plugin** : `anime` (`.discord-stats-container.discord-theme-anime`).
- **Palette** : fond sombre dégradé (`linear-gradient(135deg,#0f172a,#1f2937)`), accents néon cyan (#38bdf8) et magenta (#f472b6).
- **Typographie** : `Space Grotesk` pour l'effet futuriste, `font-weight: 500-700`.
- **Composants clés** :
  - `Hero`/bannière avec chiffres animés (compteurs incrémentés via API).
  - `Timeline` ou `Stepper` avec points pulsants (`box-shadow` animé).
  - Boutons CTA avec `hover` élastique (`scale(1.05)` + `rotateX(2deg)`).
- **Animations** :
  - `@keyframes pulseGlow` sur les accents (`filter: drop-shadow(0 0 12px currentColor)`).
  - Timelines JS (si Anime.js disponible) : apparition séquencée des cartes (`opacity`, `translateY`, `delay` par index).
  - Fallback CSS pour utilisateurs sans JS : transitions `opacity/transform`.
- **Utilisation suggérée** : pages marketing, démonstrations publiques du widget.

## Bonnes pratiques transverses
- Prévoir une couche de variables CSS (`--discord-surface`, `--discord-accent`) pour appliquer rapidement chaque preset.
- Centraliser les animations dans un module pour activer/désactiver selon les préférences utilisateur et les performances.
- Documenter les dépendances éventuelles (Tailwind, Anime.js) et fournir un fallback CSS natif.
- Tester les presets avec les composants WordPress (`wp-components`) pour assurer la cohérence avec le back-office.

Ces presets peuvent être combinés ou ajustés selon les besoins : par exemple, adopter la structure Headless UI et appliquer les teintes Shadcn UI, ou intégrer les micro-animations Anime.js sur une base Bootstrap.

## Prochaines étapes d'implémentation

| Priorité | Action | Détails | Dépendances |
| --- | --- | --- | --- |
| 🟠 | Formaliser les variables de thème | Définir un fichier source (`scss` ou `css`) regroupant les tokens communs (`--discord-surface`, `--discord-accent`) utilisés par chaque preset. | Refactoring CSS en cours dans `discord-bot-jlg/assets/css/`. |
| 🟡 | Exposer les presets dans Gutenberg | Ajouter des `block.json` variations et panels dédiés pour sélectionner `headless`, `shadcn`, `radix`, etc. | Extension des attributs du bloc et mapping PHP/JS.【F:discord-bot-jlg/block/discord-stats/block.json†L1-L239】 |
| 🟢 | Préparer une librairie de snippets | Documenter des extraits HTML/CSS prêts à l’emploi (navigation, cards, toasts) réutilisables dans les pages d’administration. | Documentation contributeurs dans `docs/`. |
| 🟢 | Tester les interactions `prefers-reduced-motion` | Vérifier que les animations des presets `anime` et `shadcn` respectent la désactivation automatique. | Suite de tests front existante (`tests/js`). |

Les presets peuvent être intégrés de manière incrémentale : commencer par un thème (ex. Headless Essence) puis décliner les autres en tirant parti des mêmes tokens pour limiter la dette de maintenance.

## Tokens CSS communs

- `--discord-surface-background`
- `--discord-surface-text`
- `--discord-accent`
- `--discord-accent-secondary`
- `--discord-accent-contrast`
- `--discord-border-radius`
- `--discord-shadow-elevated`

Ces variables doivent être définies dans un fichier source (`assets/css/themes.css`) et surchargées par chaque preset via un sélecteur racine (`.discord-theme-<preset>`). Les composants (cards, boutons, toasts) consommeront uniquement ces tokens pour rester compatibles avec les thèmes WordPress.

## Guide d'intégration Gutenberg

1. Ajouter un contrôle `SelectControl` ou `ButtonGroup` dans l'inspecteur pour choisir le preset.
2. Propager l'attribut `theme_preset` vers le rendu PHP (shortcode/widget) afin d'appliquer la classe CSS correspondante.
3. Utiliser `useSelect` pour récupérer la palette globale du thème (`theme.json`) et proposer des suggestions cohérentes avec les presets (ex. associer `accent_color` aux couleurs globales).
4. Prévoir un panneau "Prévisualisation" (Storybook interne ou modal) affichant le rendu de chaque preset avec des données fictives afin de faciliter la sélection.

## Roadmap d'implémentation

| Sprint | Livrables | Notes |
| --- | --- | --- |
| S1 | Extraction des tokens CSS + preset Headless Essence | Prioriser l'accessibilité et documenter les variables dans README |
| S2 | Ajout des presets Shadcn Minimal & Bootstrap Fluent | Créer des snippets Gutenberg et mettre à jour la documentation de design |
| S3 | Variations avancées (Radix Structure, Semantic Harmony) | Introduire les transitions `data-state` et tester le mode sombre |
| S4 | Preset Anime Pulse + animations paramétrables | Ajouter un toggle `reducedMotion` et des hooks d'initialisation JS |

Chaque sprint se conclut par :

- Une revue design/QA (compatibilité responsive, accessibilité).
- Des tests manuels dans Gutenberg (insertion, changement de preset, publication).
- Une mise à jour du changelog et des captures d'écran marketing.
