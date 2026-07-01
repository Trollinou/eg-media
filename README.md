# EG Media Manager

**EG Media Manager** est un plugin WordPress propriétaire conçu pour optimiser, redimensionner et gérer automatiquement les images de votre bibliothèque de médias à l'aide de l'extension PHP **Imagick**.

---

## 📋 Prérequis Techniques

Pour fonctionner de manière optimale, le plugin nécessite la configuration serveur suivante :
- **WordPress** : version 6.0 ou supérieure (recommandé : 6.9.1+)
- **PHP** : version **8.4** ou supérieure (avec typage strict activé)
- **Extension PHP Imagick** : installée et active sur le serveur (requise pour le traitement d'images)

---

## ✨ Fonctionnalités Principales

### 1. Optimisation Automatique lors du Téléversement
Dès qu'une image au format **JPEG**, **PNG** ou **WebP** est ajoutée à la bibliothèque de médias, le plugin la traite automatiquement :
- **Redressement automatique** : Ajustement de l'orientation de l'image (Exif Auto-orient).
- **Redimensionnement intelligent** : Redimensionnement proportionnel selon une largeur maximale personnalisable (ex. 2000px).
- **Amélioration du piqué (Unsharp Mask)** : Application d'un filtre pour conserver la netteté des images après leur redimensionnement.
- **Compression optimisée** : Réglage fin de la qualité pour les formats JPEG et WebP, et niveau de compression réglable pour le format PNG.
- **Chrominance 4:2:0** : Réduction du sous-échantillonnage de la chrominance pour optimiser la taille du fichier.
- **Mode Progressif (Interlace)** : Génération d'images progressives pour un affichage web plus rapide.

### 2. Optimisation en Masse de l'Existant (Bulk Optimization)
- **Traitement par lots (AJAX)** : Optimisez toutes vos images existantes directement depuis le tableau de bord sans surcharge serveur ni interruption.
- **Suivi en temps réel** : Barre de progression dynamique indiquant le nombre d'images restantes à traiter.

### 3. Gestion de Galeries Personnalisées (Taxonomie)
- **Taxonomie "Galeries"** : Gestion des galeries via la taxonomie personnalisée `eg_media_gallery` rattachée aux pièces jointes.
- **Édition Rapide** : Assignation et création rapide de galerie directement depuis les détails d'un média dans la bibliothèque de médias.
- **Téléversement Bulk** : Sélection ou création rapide d'une galerie cible directement au-dessus de la zone de drag-and-drop lors de l'importation de fichiers, appliquant automatiquement la galerie à toutes les images téléversées.
- **Image de Référence** : Définition simplifiée d'une image de référence pour chaque galerie. Un indicateur d'étoile dorée met en valeur l'image de référence sur sa vignette (mode Grille) et à la fois sur sa miniature à gauche et à côté de sa galerie (mode Liste).
- **Conservation du Filtre entre Vues** : Maintien et sélection automatique de la galerie filtrée active lors de la transition bidirectionnelle entre la vue Liste et la vue Grille.
- **Filtrage Avancé** : Filtrage par galerie dans la bibliothèque de médias (Backbone/AJAX) en mode Liste et Grille (avec une option "Sans affectation" pour isoler les images orphelines).
- **Actions Groupées** : Option d'action groupée "Associer à une galerie" disponible dans le mode liste pour traiter plusieurs médias simultanément.

### 4. Bloc Gutenberg dynamique "Visionneuse de Galerie"
- **Choix de mise en page** : Sélection directe dans l'Inspecteur entre le mode *Visionneuse (Diaporama)* classique et le mode *Grille justifiée* (Justified Grid) où les images conservent leur aspect ratio et s'alignent sur la même hauteur par ligne.
- **Chargement progressif ("Charger plus")** : Pour le mode Grille justifiée, affichage initial d'un lot d'images (réglable de 10 à 100) avec un bouton de chargement progressif. Les images suivantes sont injectées instantanément en JavaScript à partir des données pré-chargées au format JSON.
- **Lightbox / Plein Écran Immersif** : Clic sur une miniature de la grille justifiée ou sur l'image principale de la visionneuse pour l'ouvrir en plein écran natif (HTML5 API) de façon fluide.
- **Rendu Premium 90/10 (Mode Visionneuse)** : Affichage responsive avec 90% de la hauteur pour l'image principale active et 10% pour une bande de miniatures rectangulaires (ratio 3:2 avec coins arrondis).
- **Sélection de la Résolution** : Choix de la résolution d'image (`Taille originale`, `Grande`, `Moyenne`, `Miniature`) directement dans l'Inspector Control Gutenberg pour optimiser le temps de chargement et économiser la bande passante.
- **Supports de Styles WordPress** : Prise en charge native des réglages WordPress d'espacements (marges internes `padding` et externes `margin`), de bordures (arrondi `border-radius`) et d'ombres (`box-shadow`) configurables dans l'onglet "Styles" du bloc.
- **Intégration Gutenberg Standard** : Correction de la sélection du bloc et activation de la barre d'outils flottante standard (pour gérer l'alignement, le glisser-déposer, etc.) grâce à l'implémentation de `useBlockProps`.
- **Aperçu d'Édition Réaliste** : Un espace réservé (placeholder) premium en mode sombre qui affiche de façon dynamique la mise en page sélectionnée, le nom de la galerie sélectionnée, sa résolution active, le tri, et les réglages de diaporama/lots d'images.
- **Vanilla JS ES2021** : Script sans dépendance externe gérant le calcul du ratio d'aspect naturel des miniatures pour éviter les déformations (supports portraits/paysages), et élimination des superpositions d'images par régénération de noeuds DOM sous Safari.
- **Logique Circulaire** : Défilement infini des miniatures et navigation circulaire fluide (le clic "Précédent" sur la première image redirige automatiquement vers la dernière).
- **Navigation par Clavier** : Utilisation des flèches directionnelles Gauche et Droite du clavier pour afficher de manière intuitive l'image précédente ou suivante (active en mode visionneuse classique ou en mode plein écran).
- **Défilement à la Roulette** : Possibilité de faire défiler la piste de miniatures de gauche à droite à l'aide de la roulette de la souris ou du défilement trackpad sans modifier la sélection de l'image active (avec gestion fluide de la transition pour éviter les saccades).
- **Diaporama Intelligent** : Diaporama automatique (uniquement pour le mode Visionneuse) avec configuration du tempo (de 1s à 10s), avec mise en pause automatique au survol de la souris (`mouseenter` / `mouseleave`) uniquement en dehors du mode plein écran (lecture continue en plein écran).
- **Tri Avancé** : Possibilité de trier les images de la galerie par nom de fichier ou par date de prise de vue (EXIF avec fallback sur la date de dépôt WordPress), dans le sens ascendant ou descendant.

### 5. Intégration Piwigo (Albums & Images mises en avant)
- **Liaison d'albums** : Possibilité de connecter une instance Piwigo (v16.4.0+) à l'aide d'une clé d'accès personnel (identifiant public et secret séparés).
- **Visionneuse Piwigo** : Option de source "Album distant (Piwigo)" ajoutée au bloc de la visionneuse pour charger et afficher directement des photos stockées sur Piwigo dans le diaporama ou la grille justifiée.
- **Importation d'image mise en avant** : Bouton d'action "Set Piwigo Featured Image" intégré sous le bloc d'image mise en avant natif de l'éditeur d'articles WordPress. Il permet de parcourir un album Piwigo, d'en importer une photo localement dans la bibliothèque de médias en un clic, et de la définir comme image mise en avant du post.
- **Cache Transient** : Persistence des données Piwigo pendant 1 heure via l'API Transients de WordPress pour garantir des performances optimales.

### 6. Albums (Regroupement de Galeries)
- **Custom Post Type Albums** : Regroupement sous forme d'albums de plusieurs galeries locales ou Piwigo.
- **Tri et Ordonnancement Dynamique** : Choix du type de tri (alphabétique ou manuel via glisser-déposer) et prévisualisation directe du shortcode à intégrer.
- **Rendu en Grille Premium** : Affichage public à l'aide d'un shortcode `[eg_media_album id="XX"]` sous forme de cartes d'entête sur 2 colonnes avec des effets visuels raffinés.
- **Ouverture dynamique en Justified Grid** : Clic sur une galerie de l'album pour ouvrir son contenu en grille justifiée au sein d'un modal overlay moderne, avec lightbox plein écran active.

### 7. Tableau de Bord d'Administration
Intégré directement sous le menu **Médias > EG Media Manager**, il propose :
- **Onglet Statistiques** :
  - Indicateur d'état du serveur (vérification de la présence d'Imagick).
  - Nombre total d'images optimisées.
  - Espace disque total économisé (Ko, Mo, Go).
  - Outil d'optimisation en masse.
- **Onglet Configuration** :
  - Personnalisation de la largeur maximale de redimensionnement.
  - Réglage de la qualité de compression JPEG/WebP.
  - Réglage du niveau de compression PNG (Faible, Moyenne, Forte).
  - Options activables/désactivables (Unsharp Mask, Auto-orient, Chrominance 4:2:0, Interlacing).
  - **Zone de danger** : Réinitialisation globale du statut d'optimisation pour permettre de ré-optimiser toutes les images du site.

---

## 🚀 Installation & Configuration

### Installation Manuelle
1. Téléchargez ou clonez le dépôt dans le dossier `wp-content/plugins/eg-media/`.
2. Assurez-vous que l'extension **Imagick** est bien activée sur votre serveur PHP.
3. Installez les dépendances et compilez les blocs Gutenberg :
   ```bash
   npm install
   npm run build
   ```
4. Activez le plugin depuis l'interface d'administration de WordPress (**Extensions > Extensions installées**).

### Configuration Initiale
1. Rendez-vous dans le menu **Médias > EG Media Manager**.
2. Allez sur l'onglet **Configuration** pour ajuster les paramètres selon vos besoins.
3. Si vous avez des images déjà présentes dans votre bibliothèque de médias, retournez sur l'onglet **Statistiques** et cliquez sur **Lancer l'optimisation** pour traiter vos images existantes.

---

## 🛠️ Architecture du Code

Le plugin respecte des standards de développement stricts :
- **Autoloading natif** : Chargement automatique des classes PHP via un autoloader SPL dans `eg-media.php`.
- **Modèle Orienté Objet** : Organisation modulaire sous le namespace `EG_MEDIA` dans le dossier `includes/`.
  - `includes/Core/` : Logique fondamentale du plugin.
  - `includes/Admin/` : Gestion du tableau de bord, des filtres de médias Backbone (Uploads, Filtres, Actions) et de la metabox d'album.
  - `includes/CPT/` : Enregistrement de la taxonomie personnalisée `eg_media_gallery` et du CPT `eg_media_album`.
  - `includes/Blocks/` : Contrôleurs d'enregistrement des blocs Gutenberg dynamiques.
  - `includes/Shortcodes/` : Gestionnaires des codes courts (Shortcodes) publics comme `[eg_media_album]`.
  - `includes/Services/` : Services de traitement d'images et liaisons API (`Processor`, `BulkProcessor` et `Piwigo`).
  - `includes/API/` : Points de terminaison REST API (Intégration Gutenberg/Piwigo).
  - `includes/Enums/` : Énumérations typées PHP 8.4.
- **Sécurité renforcée** : Utilisation systématique de Nonces, de vérifications de rôles (`current_user_can`) et d'échappement des données à l'affichage (`esc_html`, `esc_attr`, `esc_url`).
