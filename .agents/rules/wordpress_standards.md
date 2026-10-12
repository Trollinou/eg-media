---
trigger: always_on
---

# Directives WordPress 7.1 & PHPStan Level 8+

## 1. Typage Strict WP_HTML_Tag_Processor
- Toujours passer un tableau associatif typé pour le filtrage de balises dans `next_tag()` :
  `$processor->next_tag( [ 'tag_name' => 'img' ] );`
  (Passer une chaîne de caractères brute déclenche une erreur d'analyse statique PHPStan WordPress).

## 2. Enregistrement d'Assets & Typage non-empty-string
- Utiliser systématiquement `plugin_dir_url( __FILE__ ) . 'assets/...'` lors de `wp_register_script()` et `wp_register_style()` pour garantir le type `non-empty-string` sous PHPStan niveau 8+.

## 3. Interactivity API Déclarative (WordPress 7.1)
- Pour tout bloc Gutenberg avec `"supports": { "interactivity": true }` :
  - Utiliser impérativement le store déclaratif `@wordpress/interactivity` (`store()`, `getContext()`, `getElement()`).
  - Utiliser les directives HTML déclaratives dans les templates serveur (`data-wp-interactive`, `data-wp-context`, `data-wp-bind--*`, `data-wp-on--*`, `data-wp-class--*`, `data-wp-watch`).
  - Proscrire toute manipulation manuelle impérative du DOM (`querySelector`, `addEventListener`).

## 4. Architecture & Formats Média Modernes
- Favoriser le chargement contextuel (lazy-loading des modules Admin / REST / Front) via un orchestrateur centralisé `Plugin`.
- Prendre en charge nativement le format **AVIF** (`image/avif`) en plus de **WebP**, **JPEG** et **PNG**.

## 5. Chaîne de Compilation Hybride & Organisation des Scripts
- **Blocs Gutenberg** : Utiliser impérativement **Webpack via `@wordpress/scripts`** (`wp-scripts build --experimental-modules`) vers `build/blocks/` pour gérer les `block.json`, Script Modules (`viewScriptModule`) et dépendances WordPress.
- **Scripts TypeScript/JavaScript autonomes** : Utiliser **`esbuild`** (`scripts/build-js.js`) vers `assets/js/` afin de garantir des bundles légers et **proscrire tout fichier `.asset.php` parasite dans `assets/js/`**.
- **Styles SCSS & RTL** : Utiliser **Dart Sass (`sass`) + `rtlcss`** (`scripts/build-css.js`) vers `assets/css/*.css` avec génération automatique des variantes `*-rtl.css`.
- **Organisation `scripts/`** : Tous les scripts Node utilitaires (`build-js.js`, `build-css.js`, `package.cjs`, `version-sync.cjs`) doivent résider exclusivement dans le sous-dossier `scripts/`, jamais à la racine du projet.
- **Versionnage PHP** : Les classes PHP ne doivent jamais coder de version en dur ; elles consomment la constante globale racine (ex: `EG_MEDIA_VERSION`).

## 6. Optimisation des Assets & Couverture de Code (DevTools Coverage / PageSpeed)
- **Séparation stricte Register vs Enqueue** : Ne jamais appeler `wp_enqueue_*()` de manière inconditionnelle ou globale. Toujours enregistrer les ressources via `wp_register_style()` / `wp_register_script()` avant de les mettre en file selon le besoin.
- **Chargement contextuel à la demande** :
  - *Front-end* : Enfiler les assets uniquement dans le callback du shortcode (`render`), ou laisser `block.json` gérer les styles/scripts des blocs Gutenberg à la demande.
  - *Back-end / Admin* : Conditionner systématiquement la mise en file d'attente à la page active en vérifiant `$hook_suffix` (`admin_enqueue_scripts`) ou le `$post_type`.
  - *Éviter les redondances* : Ne jamais ré-enfiler un style ou script déjà géré par un module parent ou connexe.
- **Stratégie de chargement des scripts** : Spécifier systématiquement la stratégie `defer` ou `async` via l'argument `$args` de `wp_register_script()` / `wp_enqueue_script()` :
  `array( 'strategy' => 'defer', 'in_footer' => true )`.
