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
