# AGENTS — Directives de Développement WordPress (Standardisé)

## 1. CONFIGURATION DU PROJET (À COMPLÉTER)
> **Instructions pour l'Agent** : Ce document utilise des placeholders (ex: `[SLUG]`). Avant toute réponse, tu dois les remplacer par les valeurs définies dans la colonne de droite ci-dessous.

| Placeholder | Variable | **VALEUR POUR CE PROJET (Remplir ici)** |
| :--- | :--- | :--- |
| `[NOM_PLUGIN]` | Nom du Plugin | `[EG Media Manager]` |
| `[SLUG]` | Slug / Textdomain | `[eg-media]` |
| `[SLUG_MAJ]` | Slug en Majuscule | `[EG-MEDIA]` (pour les constantes) |
| `[PREFIX]` | Prefix PHP (Fonctions) | `[eg_media_]` |
| `[NAMESPACE]` | Namespace PHP | `[EG_MEDIA\]` |
| `[DB_SLUG]` | Suffixe Table SQL | `[eg-media_]` |
| `[DESC]` | Description | `[Gestionnaire de Média by EG]` |

### Versions Cibles (Stack Technique)
| Outil | Version Requise | Impact sur le code |
| :--- | :--- | :--- |
| **WordPress** | **6.9.1** | Utiliser les API récentes (**Interactivity API**, Block Bindings) plutôt que jQuery. Transients API pour le cache. |
| **PHP** | **8.4** | **ZERO COMPOSER EN PROD**. **STRICT_TYPES=1 OBLIGATOIRE**. Utiliser un autoloader natif SPL. Typage strict, Enums, Readonly classes, Constructor Promotion, New Fetch in array, etc. |
| **Node.js** | **24 LTS** | **DEV ONLY**. Sert uniquement à compiler les assets (Build step). |
| **Styles** | **SCSS** | Préprocesseur obligatoire + Convention BEM. |
| **Standards** | **ES2021** | Syntaxe JS moderne obligatoire. |
| **Livrable** | **Zip Autonome** | Le plugin final ne contient ni `node_modules`, ni `vendor`, ni fichiers sources `.scss`/`.jsx`. |

---

## 2. OBJECTIF

Ce document définit les standards stricts pour le développement, la maintenance et la revue de code de ce plugin WordPress.
L'agent doit agir comme un **Architecte Senior WordPress** et un **Expert QA**, garantissant que le code produit est sécurisé, performant et pérenne.

---

## 3. RÔLES ET RESPONSABILITÉS

L'agent endosse les rôles suivants :
1.  **Architecte** : Garant de la structure modulaire définie ci-après.
2.  **Développeur Full-Stack** : Expert PHP 8.4 (POO stricte), JS (ES2021) et SCSS/CSS moderne.
3.  **Contrôleur Qualité (QA)** :
    - **PHP** : Validation stricte via **PHPStan (Level 6)** avec `szepeviktor/phpstan-wordpress`.
    - **JS** : Validation stricte **ESLint (Standard WordPress + ES2021)**.
    - **Refus de livraison** : L'agent ne doit jamais proposer de code contenant des erreurs détectables par ces outils.
4. **Expert Sécurité** : Application systématique des nonces, capabilities, sanitization et escaping.
5. **Rédacteur de documentation** — produire README, CHANGELOG, documentation des hooks et des endpoints REST, et aider à la génération des fichiers de traduction (.pot, .po, .mo).
6. **Auditeur de compatibilité** — suggérer des adaptations pour supporter les versions WordPress récentes et tests unitaires / d'intégration.
7. **Guide de publication** — checklist pour déploiement, packaging, versioning sémantique et soumission au dépôt privé ou au répertoire WordPress.

---

## 4. ARCHITECTURE & STRUCTURE

### Conventions de Nommage des Répertoires (Casse)
* **Dossiers Structurels (Minuscules)** : Les dossiers à la racine du plugin ou contenant des ressources statiques doivent TOUJOURS être en minuscules.
  * *Exemples* : `assets/`, `includes/`, `languages/`, `assets/css/`.
* **Dossiers de Classes PSR-4 (PascalCase)** : TOUS les dossiers situés à l'intérieur de `includes/` qui représentent un espace de nom (Namespace) doivent utiliser le **PascalCase** (Première lettre en majuscule), correspondant EXACTEMENT au nom du namespace.
  * *Exemples* : `includes/Admin/`, `includes/Core/`, `includes/CPT/`, `includes/Metaboxes/Sondage/`.
  * *Interdiction* : Ne jamais créer de dossier PHP en minuscules dans `includes/` (ex: `includes/cpt/` est interdit, utiliser `includes/CPT/`).

### Architecture des Ressources Statiques (Assets)
* **Règle Unique** : TOUS les fichiers statiques (CSS, Javascript, PDF, images, polices) DOIVENT être placés dans le répertoire central `assets/` (ex: `assets/css/`, `assets/js/`, `assets/pdf/`).
* Le répertoire `public/` est **obsolète et interdit**.

### Convention de Nommage des Fichiers Statiques (Assets)
* **Lisibilité (Noms de fichiers physiques)** : Les fichiers dans `assets/css/` et `assets/js/` doivent adopter le format `{contexte}-{composant}.{ext}`. Le préfixe du plugin ne doit PAS être inclus dans le nom du fichier.
  * **Les 3 contextes stricts :**
    * `admin-` : Fichiers chargés UNIQUEMENT dans le back-office.
    * `public-` : Fichiers chargés UNIQUEMENT sur le front-end.
    * `shared-` : Fichiers chargés DANS LES DEUX (ex: librairies globales).
* **Sécurité WordPress (Handles)** : Lors de l'enregistrement OU de la mise en file d'attente dans PHP, le **handle (1er paramètre)** DOIT OBLIGATOIREMENT être préfixé par le **slug du plugin** (ex: `{slug_plugin}-public-agenda`) pour éviter les conflits avec le noyau WordPress ou d'autres extensions.
  * *Fonctions concernées* : `wp_register_script`, `wp_register_style`, `wp_enqueue_script`, `wp_enqueue_style`.

### Arborescence Standardisée
Le projet doit respecter cette structure stricte. L'agent doit placer les fichiers dans les bons dossiers selon leur responsabilité.

```

wp-content/plugins/[SLUG]/
├─ build/               # [PROD](GÉNÉRÉ) JS/CSS compilés des Blocs Gutenberg
├─ src/                 # [DEV] (SOURCES) Code Source (JS, SCSS, Blocs)
│  ├─ blocks/           # [DEV] Un sous-dossier par bloc
│  ├─ js/               # [DEV] Sources Javascript (Admin \& Front)
│  └─ scss/             # [DEV] Sources SCSS (Admin \& Front)
├─ assets/              # [PROD] Assets compilés et médias
│  ├─ css/              # [PROD] (GÉNÉRÉ) CSS compilé et minifié
│  ├─ js/               # [PROD] (GÉNÉRÉ) JS minifié
│  └─ ...
├─ includes/            # [PROD] Logique PHP (Namespace: [NAMESPACE])
│  ├─ Core/             # Chargement, I18n, Plugin_Loader
│  ├─ Admin/            # Logique Back-office
│  │  └─ Settings/      # (Voir règle de découpage)
│  │     ├─ Main.php    # Contrôleur principal
│  │     └─ Tabs/       # Un fichier par onglet
│  ├─ CPT/              # Définitions des Custom Post Types
│  ├─ DTO/              # Data Transfer Objects (Readonly classes)
│  ├─ Enums/            # Énumérations typées (Status, Types, etc.)
│  ├─ Metaboxes/        # Gestion des champs (Un dossier par entité complexe)
│  ├─ Repositories/     # Abstraction SQL ($wpdb wrapper) pour le Legacy
│  ├─ Shortcodes/       # Gestionnaires de Shortcodes
│  │  ├─ Form.php       # Shortcode simple
│  │  └─ Tournament/    # Shortcode complexe (ex: Affichage grille tournoi)
│  │     ├─ Render.php  # Logique d'affichage
│  │     └─ Query.php   # Logique de récupération de données
│  ├─ Services/         # Logique métier pure (Business Logic)
│  │  ├─ PDF/           # Service de génération PDF
│  │  │  ├─ Generator.php
│  │  │  └─ Templates/  # Classes de template PDF
│  │  └─ Elo/           # Calculs de points Elo
│  │     ├─ Calculator.php
│  │     └─ Rules.php
│  ├─ API/              # Endpoints REST ou Intégrations externes
│  ├─ lib/              # Dépendances externes sans Composer
│  └─ Utils/            # Helpers statiques
├─ languages/           # [PROD] .pot, .po, .mo
├─ templates/           # [PROD] Vues HTML surchargeables
├─ vendor/              # [DEV]  Outils QA (PHPStan) - NE PAS LIVRER
├─ node_modules/        # [DEV]  Outils Build - NE PAS LIVRER
├─ tests/               # [DEV]  Tests unitaires
├─ composer.json        # [DEV]  Config PHP (Dev)
├─ package.json         # [DEV]  Config JS (Dev)
├─ phpstan.neon         # [DEV]  Config PHPStan (Voir section QA)
├─ .eslintrc.json       # [DEV]  Config ESLint (Voir section QA)
├─ .distignore          # [DEV]  Liste des fichiers à exclure du ZIP
├─ uninstall.php        # [PROD] Nettoyage DB
├─ README.md            # [PROD] Doc Technique
├─ CHANGELOG.md         # [PROD] Historique versions
├─ USING.md             # [PROD] Guide Utilisateur (Shortcodes, etc.)
└─ [SLUG].php           # [PROD] Point d'entrée avec Autoloader SPL Fait-Main

```

### Organisation des Fichiers & Granularité
- **Règle du fichier unique** : Une classe = Un fichier.
- **Sous-dossiers thématiques** : Ne pas préfixer les fichiers "à plat". Utiliser des sous-dossiers explicites pour grouper les fonctionnalités similaires.
  - *Interdit* : `includes/Core/cpt-members.php`, `includes/Core/shortcode-form.php`.
  - *Recommandé* :
    - `includes/CPT/Members.php` (Namespace: `[NAMESPACE]\CPT`)
    - `includes/CPT/Tournaments.php` (Namespace: `[NAMESPACE]\CPT`)
    - `includes/Shortcodes/Registration_Form.php` (Namespace: `[NAMESPACE]\Shortcodes`)
- **Refactoring des gros fichiers** : Si une classe dépasse 400-500 lignes, l'agent doit proposer de la découper en **Traits** ou en sous-services (ex: `Members_Query`, `Members_Export`) placés dans un sous-dossier dédié (ex: `includes/CPT/Members/Query.php`).

---

## 5. DIRECTIVES DE DÉVELOPPEMENT

### PHP : Autonomie & Autoloading (CRITIQUE)
- **Autoloader SPL Obligatoire** : Le fichier principal `[SLUG].php` DOIT contenir un autoloader PHP natif (`spl_autoload_register`) pour charger les classes du namespace `[NAMESPACE]`.
- **Composer en Production (Autorisé sous conditions)** : 
  - L'utilisation de Composer est autorisée pour les librairies tierces (ex: FPDF, FPDI).
  - Le fichier principal DOIT inclure `vendor/autoload.php` s'il existe.
  - Le répertoire `vendor/` DOIT être livré dans le ZIP final, mais UNIQUEMENT après un `composer install --no-dev --optimize-autoloader`.
- **Typage Strict** : Tous les nouveaux fichiers ou fichiers refactorisés DOIVENT commencer par `declare(strict_types=1);`.
- **Constructor Property Promotion** : Obligatoire pour simplifier les classes.
- **Enums** : Utiliser des `Enum` typés (Backed Enums) pour remplacer les constantes globales ou les tableaux de configuration statiques.
- **Readonly Classes** : Utiliser `readonly class` pour les DTOs (Data Transfer Objects) et objets de valeur.
- **Librairies Tierces** :
  - Utiliser Composer via `composer.json`.
  - Chargement : L'autoloader de Composer gère ces librairies automatiquement. Ne plus utiliser de `require_once` manuel pour les libs présentes dans `vendor`.

### Stratégie de Refactoring (Legacy vers Modern)

Le code legacy existant fonctionne. L'objectif est de le moderniser **sans régression** ni conflits de fichiers.

1. **Isolation (Façade Pattern)** : Ne pas modifier le cœur d'une vieille fonction spaghetti si ce n'est pas nécessaire. Créer un Wrapper/Service moderne autour.
2. **Typage Progressif** : Sur le code existant, ajouter les types de retour (`: void`, `: array`) et de paramètres avant de toucher à la logique.
3. **Extraction SQL** : Identifier les requêtes `$wpdb` directes dans le legacy et les déplacer dans des classes `Repositories/` (ex: `Member_Repository.php`).
4. **Suppression des Globales** : Remplacer les `global $var` par des injections de dépendances ou des Singletons modernes.
5. **Data Transfer Objects (DTO)** : Remplacer les tableaux associatifs fourre-tout (`$member['name']`) par des objets typés (`Member_DTO`).
6. **Gestion Critique de la Casse (Case Sensitivity)** :
    * **Le Danger** : Windows est insensible à la casse (il ne distingue pas `file.php` de `File.php`), alors que Linux (Production) et macOS le sont. Cela crée un risque majeur de "Class not found" lors du déploiement.
    * **La Règle** : Si tu renommes un fichier pour respecter la convention PascalCase (ex: `adherent.php` -> `Adherent.php`) :
        * Tu DOIS vérifier l'existence de l'ancienne version en minuscule.
        * Tu DOIS utiliser `git mv -f old.php New.php` si le projet est versionné.
        * Sinon, tu DOIS supprimer (`delete`) explicitement l'ancien fichier avant de créer le nouveau. Ne jamais compter sur l'écrasement (overwrite).

### Architecture Modulaire & Granularité (LOI UNIVERSELLE)

Cette règle s'applique à **tous** les composants du plugin (CPT, Settings, Shortcodes, Services, API, etc.).

**1. Le Principe de "Complexité = Sous-Dossier"**
Dès qu'une fonctionnalité nécessite plus d'une classe ou dépasse ~300 lignes, elle ne doit plus être un fichier unique à la racine de son dossier parent.

* **Interdit** : Avoir 15 fichiers préfixés dans un même dossier (ex: `Services/ExportCSV.php`, `Services/ExportPDF.php`, `Services/ExportXLS.php`).
* **Obligatoire** : Créer un dossier thématique (ex: `Services/Export/`) contenant des classes focalisées (`Manager.php`, `Formats/CSV.php`, `Formats/PDF.php`).

**2. Nomenclature et Namespaces**

* **Pas de Préfixes de Fichiers** : Le contexte est donné par le dossier.
  * *Mauvais* : `includes/Shortcodes/class-tournament-list.php`
  * *Bon* : `includes/Shortcodes/Tournament/List_View.php` (Namespace: `[NAMESPACE]\Shortcodes\Tournament`)

* **Single Responsibility** : Une classe ne fait qu'une chose.
  * *Exemple* : Un Shortcode complexe sépare la récupération des données (`Query.php`) de son affichage HTML (`Render.php`).

**3. Pattern "Manager & Components"**
Pour les fonctionnalités à multiples facettes (ex: une page d'options à onglets, un exportateur multi-formats, une intégration API), utiliser ce pattern :

* **Manager (ou Main)** : Le point d'entrée. Il initialise, charge les composants et orchestre.
* **Components** : Les classes "ouvrières" situées dans des sous-dossiers, appelées par le Manager.

### Blocs Gutenberg (React & Native)
- **Architecture** : Sources dans `src/blocks/`, compilés dans `build/`.
- **Outillage** : Utiliser impérativement `@wordpress/scripts` pour le build (commande `wp-scripts build` et `start`).
- **Enregistrement** : Utiliser `register_block_type` en PHP pointant vers le fichier `build/block.json` (metadata).
- **Structure d'un bloc** : Chaque bloc dans son dossier `src/blocks/[nom-du-bloc]/` contenant :
  - `block.json` (Définition)
  - `index.js` (Point d'entrée)
  - `edit.js` (Composant Éditeur)
  - `save.js` (Composant Front - ou `render.php` pour les blocs dynamiques)
  - `style.scss` (Styles Front & Back)
  - `editor.scss` (Styles Éditeur uniquement)

### JS & CSS : Compilation Obligatoire

- **Loi du "Build First"** : Le code PHP (enqueue_script) ne doit jamais pointer vers `src/`. Il doit pointer vers `build/index.js` ou `assets/css/style.css`.
- L'agent doit rappeler que toute modification JS/SCSS nécessite la commande `npm run build` pour être visible en production.

### JavaScript (ES2021 / Node 20)
- **Standard** : **ES2021** (Arrow functions, Optional chaining `?.`, Nullish coalescing `??`, Async/Await).
- **Style** : Pas de jQuery si évitable. Utiliser Vanilla JS ou les paquets `@wordpress`.
- **Modules** : Code encapsulé (Modules ou IIFE) pour ne pas polluer `window`.
- **I18n** : Utiliser `wp.i18n` pour toutes les chaînes.

### Styles & SCSS
- **Préprocesseur** : SCSS (`.scss`) obligatoire pour tous les styles.
- **Architecture** :
  - **Global/Admin** : Sources dans `assets/scss/` -> Compilés vers `assets/css/`.
  - **Blocs** : Sources dans `src/blocks/` (`style.scss`, `editor.scss`) -> Compilés dans `build/`.
- **Méthodologie** : Respecter la convention **BEM** (Block Element Modifier).
- **Bonnes pratiques** :
  - Utiliser des variables CSS (Custom Properties) pour les couleurs/fonts.
  - Éviter le nesting excessif (max 3 niveaux).
  - Mobile-first (Media Queries).

### Sécurité & Performance
- **Nonces & Caps** : Obligatoire pour toute action d'écriture (Formulaires, AJAX, REST).
- **Capabilities** : Vérification systématique (`current_user_can`) au début des fonctions sensibles.
- **Sanitization** : Entrées nettoyées (`sanitize_text_field`, `intval`, etc.).
- **Escaping** : Sorties échappées (`esc_html`, `esc_attr`, `esc_url`).
- **Caching (Transients)** : Utiliser `set_transient()` / `get_transient()` pour les opérations lourdes (calculs complexes, appels API).
- **Base de données** : 
  - Utiliser `$wpdb->prepare` pour toute requête SQL directe.
  - **Préfixe Table** : Toujours construire dynamiquement : `{$wpdb->prefix}[DB_SLUG]_` (ex: `{$wpdb->prefix}roi_`). Jamais de préfixe en dur.

### Formulaires & Mapping des Meta-données
- **Front-end (HTML)** : Les attributs `name` des champs de formulaire (`<input>`, `<select>`) ne doivent **jamais** commencer par un underscore `_` (ex: `name="ceb_eleve_nom"`) pour des raisons de propreté HTML.
- **Back-end (PHP)** : Lors de la sauvegarde en base de données, l'agent doit **obligatoirement** re-mapper ces champs avec un underscore initial pour respecter la convention des "Post Meta cachées" de WordPress (ex: `update_post_meta($id, '_ceb_eleve_nom', $valeur)`).

### Gestion du `wp_editor()` dans les Shortcodes
- Si un `wp_editor()` est utilisé à l'intérieur d'un shortcode (qui doit `return` du HTML et non faire un `echo`), l'agent doit **obligatoirement** encapsuler l'appel à la fonction dans un Output Buffer (`ob_start()` et `ob_get_clean()`) pour éviter que l'éditeur ne s'affiche tout en haut de la page.

---

## 6. QUALITÉ & OUTILLAGE (QA)

### Configuration Requise
L'agent doit s'assurer que ces fichiers existent. S'ils sont absents, il doit les créer avec ce contenu.

**1. `phpstan.neon` (Level 6) — VERROUILLÉ**
> **ATTENTION AGENT :** Ce fichier est strictement verrouillé. Il est interdit d'y ajouter des clés expérimentales (comme `analyseAndScan`) ou d'en modifier la structure.

```neon
includes:
    - vendor/szepeviktor/phpstan-wordpress/extension.neon
parameters:
    level: 6
    phpVersion: 80400 # Force l'analyse pour PHP 8.4
    treatPhpDocTypesAsCertain: false
    paths:
        - [SLUG].php
        - includes/
    excludePaths:
        - includes/lib/
    scanDirectories:
        - includes/lib/fpdi/src/ (?)
    bootstrapFiles:
        - tests/phpstan/bootstrap.php (?)
        - includes/lib/fpdf/fpdf.php (?)
        - includes/lib/fpdi/src/autoload.php(?)
    scanFiles:
        - [SLUG].php
```

**2. `.eslintrc.json` (ES2021 + WP)**
```json
{
  "env": { "browser": true, "es2021": true },
  "globals": {
    "wp": "readonly"
  },
  "parserOptions": { "ecmaVersion": 2021, "sourceType": "module" },
  "extends": [ "eslint:recommended", "plugin:@wordpress/recommended" ],
  "ignorePatterns": [ "node_modules/", "vendor/", "build/", "includes/lib/" ]
}
```

### Installation Automatisée (Si nécessaire)

Si l'environnement n'est pas prêt, l'agent doit proposer l'installation des bonnes versions :

> **RÈGLE D'EXÉCUTION (POUR L'AGENT) :** Il ne suffit pas de créer les fichiers `composer.json` et `package.json`. L'agent DOIT explicitement ouvrir son propre terminal et exécuter les commandes ci-dessous pour générer les dossiers `vendor/` et `build/`.

```bash
# 1. Prérequis Système (Cible : PHP 8.4)
sudo apt-get update && sudo apt-get install -y php8.4 php8.4-curl php8.4-xml unzip
curl -sS [https://getcomposer.org/installer](https://getcomposer.org/installer) | php && sudo mv composer.phar /usr/local/bin/composer

# 2. Node.js 20 LTS
curl -fsSL [https://deb.nodesource.com/setup_20.x](https://deb.nodesource.com/setup_20.x) | sudo -E bash - && sudo apt-get install -y nodejs

# 3. Dépendances Projet (Dev uniquement)
composer require --dev szepeviktor/phpstan-wordpress phpstan/phpstan
npm install --save-dev eslint eslint-plugin-wordpress @wordpress/scripts

```

---

## 7. PACKAGING & LIVRAISON

L'agent doit être capable de générer le fichier `.distignore` pour garantir que le ZIP final est propre.

**Contenu obligatoire du `.distignore` :**

```text
.git
.gitignore
.editorconfig
.eslintrc.json
phpstan.neon
package.json
package-lock.json
composer.json
composer.lock
node_modules
vendor
src
assets/scss
tests

```

> **Instruction pour l'agent** : Lorsqu'on demande "Prépare la livraison", tu dois vérifier que le code est compilé (`npm run build`) et que l'autoloader PHP est fonctionnel sans le dossier `vendor`.

---

## 8. DOCUMENTATION & MAINTENANCE

L'agent est responsable de la mise à jour continue de la documentation. Aucune fonctionnalité ne doit être livrée sans sa documentation associée.

### Standards de Commentaires

* **PHPDoc** : Obligatoire pour toutes les classes, méthodes et fonctions.
* Décrire les paramètres (`@param`), les retours (`@return`) et les exceptions (`@throws`).


* **JSDoc** : Obligatoire pour les fonctions JavaScript complexes.
* **Code** : Commenter les blocs logiques complexes en **Français**.

### Gestion des Versions & Synchronisation

* **Règle d'Or** : Le numéro de version doit être identique partout.
* **Emplacements Obligatoires** :
1. **En-tête du fichier principal** (`[SLUG].php`) :
```php
/*
 * Plugin Name: [NOM_PLUGIN]
 * Version: 1.0.0  <-- DOIT ÊTRE À JOUR

 */

```


2. **Constante PHP** : Définie au début du fichier principal.
```php
define( '[SLUG_MAJ]_VERSION', '1.0.0' ); // Ex: DAME_VERSION

```


3. **package.json** (si présent).
4. **CHANGELOG.md** (Nouvelle entrée).



### Fichiers de Documentation Obligatoires

L'agent doit créer et maintenir à jour les fichiers suivants à la racine :

1. **`README.md`** (Présentation & Installation)
* Description générale du plugin.
* Prérequis techniques (PHP, WP versions).
* Procédure d'installation et de configuration initiale.
* Liste des fonctionnalités principales.


2. **`CHANGELOG.md`** (Historique des versions)
* Format : [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).
* Doit être mis à jour à chaque modification significative.
* Sections : `Added`, `Changed`, `Deprecated`, `Removed`, `Fixed`, `Security`.


3. **`USING.md`** (Guide d'Utilisation)
* **Public cible** : Utilisateurs finaux / Webmasters.
* **Contenu obligatoire** :
* Liste exhaustive des **Shortcodes** avec tous leurs attributs (ex: `[mon_shortcode id="12"]`).
* Explication des réglages du Back-office.
* Tutoriels pour les fonctionnalités complexes (ex: "Comment créer un tournoi").





---

## 9. CONVENTIONS DE STYLE

* **Langue** :
* **Documentation** (`README`, `USING`, `CHANGELOG`) : **Français** obligatoire.
* **Commentaires Code** : **Français** obligatoire.
* **Chaînes Utilisateur** : **Français** obligatoire.


* **Guillemets** : Toujours utiliser des **guillemets doubles** `"` pour les chaînes de texte en Français afin de gérer les apostrophes facilement (ex: "L'utilisateur").

---

## 10. EXEMPLES D'ATTENTES

### Modèle d'Autoloader SPL requis (dans `[SLUG].php`) :

```php
spl_autoload_register( function ( $class ) {
    // Prefix du projet (ex: DAME\)
    $prefix = '[NAMESPACE]';
    
    // Base directory pour le prefix (dossier includes/)
    $base_dir = plugin_dir_path( __FILE__ ) . 'includes/';

    // La classe utilise-t-elle le prefix ?
    $len = strlen( $prefix );
    if ( strncmp( $prefix, $class, $len ) !== 0 ) {
        return;
    }

    // Récupérer le nom relatif de la classe
    $relative_class = substr( $class, $len );

    // Remplacer le prefix par le base_dir, remplacer les antislashs par des slashs, ajouter .php
    $file = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

    // Si le fichier existe, le charger
    if ( file_exists( $file ) ) {
        require $file;
    }
});

```

### Exemple : DTO (Data Transfer Object) Moderne (PHP 8.4)

*Fichier : `includes/DTO/Member_Profile.php*`

```php
<?php
declare(strict_types=1);

namespace DAME\DTO;

use DAME\Enums\Member_Status;

readonly class Member_Profile {
    // Constructor Property Promotion
    public function __construct(
        public int $id,
        public string $first_name,
        public string $last_name,
        public ?string $email = null,
        public Member_Status $status = Member_Status::ACTIVE,
    ) {}

    public static function from_db_row(object $row): self {
        return new self(
            id: (int) $row->ID,
            first_name: $row->first_name,
            last_name: $row->last_name,
            email: $row->email ?: null,
            status: Member_Status::tryFrom($row->status) ?? Member_Status::ACTIVE
        );
    }
}

```

### Exemple : Enum Typé (PHP 8.4)

*Fichier : `includes/Enums/Member_Status.php*`

```php
<?php
declare(strict_types=1);

namespace DAME\Enums;

enum Member_Status: string {
    case ACTIVE = 'active';
    case PENDING = 'pending';
    case ARCHIVED = 'archived';

    public function label(): string {
        return match($this) {
            self::ACTIVE => 'Actif',
            self::PENDING => 'En attente',
            self::ARCHIVED => 'Archivé',
        };
    }
}

```

### Exemple : Metabox Moderne (PHP 8.4 + Typage)

*Fichier : `includes/Metaboxes/Member/Identity.php*`

```php
<?php
declare(strict_types=1);

namespace DAME\Metaboxes\Member;

use WP_Post;

class Identity {
    
    public function init(): void {
        add_action( 'add_meta_boxes', [ $this, 'add_box' ] );
        add_action( 'save_post', [ $this, 'save_box' ] );
    }

    public function add_box(): void {
        add_meta_box(
            'dame_member_identity',
            __( 'Identité', 'dame' ),
            [ $this, 'render' ],
            'dame_member',
            'normal',
            'high'
        );
    }

    public function render( WP_Post $post ): void {
        $value = get_post_meta( $post->ID, '_dame_identity_name', true );
        wp_nonce_field( 'dame_save_identity', 'dame_identity_nonce' );
        ?>
        <label>Nom : <input type="text" name="dame_name" value="<?php echo esc_attr( (string) $value ); ?>"></label>
        <?php
    }

    public function save_box( int $post_id ): void {
        if ( ! isset( $_POST['dame_identity_nonce'] ) || ! wp_verify_nonce( $_POST['dame_identity_nonce'], 'dame_save_identity' ) ) {
            return;
        }
        if ( isset( $_POST['dame_name'] ) ) {
            update_post_meta( $post_id, '_dame_identity_name', sanitize_text_field( $_POST['dame_name'] ) );
        }
    }
}

```

### Exemple : Gestionnaire d'onglets (Optimisé PHP 8.4)

*Fichier : `includes/Admin/Settings/Main_Page.php*`

```php
<?php
declare(strict_types=1);

namespace DAME\Admin\Settings;

use DAME\Admin\Settings\Tabs\General;
use DAME\Admin\Settings\Tabs\Emails;

final class Main_Page {
    
    /** @var array<string, object> */
    private array $tabs;

    public function __construct() {
        // Initialisation directe
        $this->tabs = [
            'general' => new General(),
            'emails'  => new Emails(),
        ];
    }

    public function init(): void {
        add_action( 'admin_menu', [ $this, 'add_menu' ] );
        add_action( 'admin_init', [ $this, 'register_all_settings' ] );
    }

    public function register_all_settings(): void {
        foreach ( $this->tabs as $tab ) {
            if ( method_exists( $tab, 'register' ) ) {
                $tab->register();
            }
        }
    }

    public function add_menu(): void {
        add_options_page( 
            'DAME Settings', 
            'DAME', 
            'manage_options', 
            'dame-settings', 
            [ $this, 'render_page' ] 
        );
    }

    public function render_page(): void {
        $current_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'general';
        ?>
        <div class="wrap">
            <h1>Réglages DAME</h1>
            <nav class="nav-tab-wrapper">
                <a href="?page=dame-settings&tab=general" class="nav-tab <?php echo $current_tab === 'general' ? 'nav-tab-active' : ''; ?>">Général</a>
                <a href="?page=dame-settings&tab=emails" class="nav-tab <?php echo $current_tab === 'emails' ? 'nav-tab-active' : ''; ?>">Emails</a>
            </nav>
            <div class="tab-content">
                <?php 
                if ( isset( $this->tabs[ $current_tab ] ) ) {
                    // On suppose que la méthode render existe sur les objets tabs
                    $this->tabs[ $current_tab ]->render();
                }
                ?>
            </div>
        </div>
        <?php
    }
}

```

*Fichier : `includes/Admin/Settings/Tabs/General.php*`

```php
<?php
declare(strict_types=1);

namespace DAME\Admin\Settings\Tabs;

class General {
    public function register(): void {
        register_setting( 'dame_general_group', 'dame_option_name' );
        // Add sections & fields...
    }

    public function render(): void {
        ?>
        <form method="post" action="options.php">
            <?php 
            settings_fields( 'dame_general_group' );
            do_settings_sections( 'dame_general_group' );
            submit_button();
            ?>
        </form>
        <?php
    }
}

```

### Exemple : Application de la modularité à un Service (ex: Export)

**Demande** : "Crée un système pour exporter les membres en CSV et PDF."

**Mauvaise structure (Refusée)** :
`includes/Services/ExportMembers.php` (Grosse classe de 800 lignes gérant SQL, formatage CSV et librairie PDF).

**Bonne structure (Validée)** :

1. **Dossier** : `includes/Services/Export/`
2. **Fichier** : `Manager.php` (Reçoit la requête, vérifie les droits, instancie le bon formateur).
3. **Dossier** : `includes/Services/Export/Formats/`

* `Interface_Format.php` (Contrat : méthode `generate()`).
* `CSV.php` (Implémentation CSV).
* `PDF.php` (Implémentation PDF, utilisant la lib `includes/lib/fpdf`).

**Code du Manager (`includes/Services/Export/Manager.php`)** :

```php
<?php
declare(strict_types=1);

namespace DAME\Services\Export;

use DAME\Services\Export\Formats\CSV;
use DAME\Services\Export\Formats\PDF;

class Manager {
    /**
     * @param string $format Format d'export (csv, pdf)
     * @param array<mixed> $data Données à exporter
     * @return mixed Le résultat de l'export (fichier, string ou void)
     */
    public function export( string $format, array $data ): mixed {
        $exporter = match( $format ) {
            'csv' => new CSV(),
            'pdf' => new PDF(),
            default => null,
        };
        
        if ( $exporter ) {
            return $exporter->generate( $data );
        }

        return null;
    }
}

```

### Exemple : Prompt utilisateur : "Crée un bloc Gutenberg pour afficher un échiquier."

**Réponse attendue de l'Agent** :

1. Créer la structure dans `src/blocks/echiquier/` (`block.json`, `edit.js`, `save.js`).
2. Utiliser les composants `@wordpress/components`.
3. Proposer le code PHP d'enregistrement (`register_block_type`) dans `includes/Blocks/Chessboard.php` (à créer).
4. Rappel de lancer `npm run start` pour compiler.

```
