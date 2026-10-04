# EG Media Manager

**EG Media Manager** est un plugin WordPress propriétaire de nouvelle génération conçu pour optimiser, redimensionner et gérer automatiquement les images de votre bibliothèque de médias à l'aide de l'extension PHP **Imagick** (avec fallback GD).

---

## 📋 Prérequis Techniques & Stack

Pour fonctionner de manière optimale, le plugin repose sur les technologies les plus récentes :
- **WordPress** : version **7.1** ou supérieure
- **PHP** : version **8.4** ou supérieure (avec `strict_types=1` et Enums typés)
- **TypeScript** : **6.0.3** en mode strict (`strict: true`)
- **Interactivity API** : framework réactif natif WordPress 7.1 (`@wordpress/interactivity`)
- **Block Bindings API** : intégration native pour le Full Site Editing (FSE)
- **Extension PHP Imagick** : installée et active sur le serveur (recommandée, avec fallback GD)

---

## ✨ Fonctionnalités Principales

### 1. Optimisation Automatique lors du Téléversement (AVIF, WebP, JPEG, PNG)
Dès qu'une image au format **AVIF**, **WebP**, **JPEG** ou **PNG** est ajoutée à la bibliothèque de médias, le plugin la traite automatiquement :
- **Support des Formats Modernes** : Traitement et compression native des formats AVIF et WebP.
- **Redressement automatique** : Ajustement automatique de l'orientation de l'image (Exif Auto-orient).
- **Redimensionnement intelligent** : Redimensionnement proportionnel selon une largeur maximale personnalisable (ex. 2000px).
- **Amélioration du piqué (Unsharp Mask)** : Application d'un filtre pour conserver la netteté des images après leur redimensionnement.
- **Compression optimisée** : Réglage fin de la qualité pour les formats AVIF, WebP et JPEG, et niveau de compression réglable pour le PNG.
- **Chrominance 4:2:0** : Réduction du sous-échantillonnage de la chrominance pour optimiser la taille du fichier.
- **Mode Progressif (Interlace)** : Génération d'images progressives pour un affichage web instantané.

### 2. Optimisation en Masse de l'Existant (Bulk Optimization)
- **Traitement par lots (AJAX)** : Optimisez toutes vos images existantes directement depuis le tableau de bord sans surcharge serveur ni interruption.
- **Suivi en temps réel** : Barre de progression dynamique indiquant le nombre d'images restantes à traiter.

### 3. Gestion de Galeries Personnalisées (Taxonomie)
- **Taxonomie "Galeries"** : Gestion des galeries via la taxonomie personnalisée `eg_media_gallery` rattachée aux pièces jointes.
- **Édition Rapide** : Assignation et création rapide de galerie directement depuis les détails d'un média.
- **Téléversement Bulk** : Sélection ou création rapide d'une galerie cible directement au-dessus de la zone de glisser-déposer.
- **Image de Référence** : Définition simplifiée d'une image de référence pour chaque galerie (étoile dorée sur sa vignette).
- **Conservation du Filtre entre Vues** : Maintien et sélection automatique de la galerie filtrée active lors de la transition entre la vue Liste et la vue Grille.
- **Actions Groupées** : Option d'action groupée "Associer à une galerie" disponible dans le mode liste.

### 4. Bloc Gutenberg dynamique "Visionneuse de Galerie" (Interactivity API)
- **Architecture Interactivity API** : Conçu sur le store natif `@wordpress/interactivity` pour une réactivité front-end ultra-légère et sans dépendance lourde.
- **Choix de mise en page** : Mode *Visionneuse (Diaporama)* classique ou mode *Grille justifiée* (Justified Grid).
- **Chargement progressif ("Charger plus")** : Rendu dynamique des lots d'images suivants en un clic.
- **Lightbox / Plein Écran Immersif** : Plein écran natif (HTML5 Fullscreen API) fluide avec navigation clavier (Flèches gauche/droite, Echap).
- **Rendu Premium 90/10** : 90% de la hauteur pour l'image principale et 10% pour une bande de miniatures rectangulaires centrées.
- **Supports de Styles WordPress** : Marges internes `padding`, externes `margin`, coins arrondis `border-radius` et ombres `box-shadow`.
- **HTML API** : Utilisation de `WP_HTML_Tag_Processor` pour injecter automatiquement `fetchpriority="high"` sur l'image principale et `loading="lazy"` sur les miniatures.

### 5. Block Bindings API (Full Site Editing)
- Sources de données connectables directement dans l'éditeur de blocs et templates FSE :
  - `eg-media/gallery-data` : Nom, description, nombre d'images, URL de l'image de référence et lien de la galerie.
  - `eg-media/album-data` : Titre, description, URL de couverture et lien de l'album.
  - `eg-media/media-metadata` : Crédit photo, localisation, légende et statut du média.

### 6. Intégration Piwigo
- **Connexion API distante** : Liaison sécurisée avec une instance Piwigo.
- **Mise en cache Transients** : Mise en cache locale avec invalidation automatique et par album (`clear_album_cache`).
- **Importation en un clic** : Définition d'images mises en avant ou intégration dans le bloc Image natif depuis Piwigo.

### 7. Albums & CPT
- Regroupement de galeries locales et distantes au sein d'albums (`eg_media_album`).
- Rendu en grille moderne responsive avec modal overlay interactif.
- Ordonnancement global des galeries (tri manuel ou alphabétique) et tri individuel des photos par galerie (date de prise de vue croissante/décroissante, alphabétique A-Z/Z-A).

---

## 🛠️ Développement & Qualité (QA)

```bash
# Vérification des types TypeScript
npm run type-check

# Compilation des assets (Production)
npm run build

# Analyse statique PHP (Niveau 8 strict)
vendor/bin/phpstan analyze --debug --memory-limit=2G

# Exécution des tests unitaires PHPUnit
vendor/bin/phpunit

# Création de l'archive ZIP autonome
npm run package
```
