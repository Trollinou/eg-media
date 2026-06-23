# Changelog

Toutes les modifications notables de ce projet seront consignées dans ce fichier.

Le format est basé sur [Keep a Changelog](https://keepachangelog.com/fr/1.0.0/)
et ce projet respecte le [Versionnage Sémantique](https://semver.org/lang/fr/).

## [1.0.5] - 2026-06-23

### Added
- Implémentation d'un fallback d'optimisation d'image complet et robuste en GD PHP native lorsque Imagick n'est pas activé. Le traitement inclut désormais le redressement automatique par données EXIF, le redimensionnement bilinéaire, l'accentuation de la netteté (Unsharp Mask) par matrice de convolution 3x3, l'activation du mode progressif/entrelacé, ainsi que le contrôle et la conversion fine de la compression par type de fichier (JPEG, WebP, et PNG échelonné sur 0-9).

### Changed
- Configuration par défaut du filtre de galerie sur "Sans affectation" (`orphan`) lors du chargement initial de la bibliothèque de médias (`upload.php`) sans paramètres de filtrage pour limiter l'affichage initial.
- Refonte responsive de la visionneuse de galerie (`.eg-viewer`) : suppression de la hauteur fixe de 600px au profit d'un `aspect-ratio` fluide (4/3 par défaut sur mobile, 16/9 à partir de 768px de largeur), hauteur dynamique `height: auto` et limite de hauteur `max-height: 85vh`.
- Optimisation Safari de la zone image principale (`.eg-viewer__main`) : intégration d'un positionnement absolu sur l'image (`.eg-viewer__main-image`) avec `object-fit: contain` (Safari Hack), et ajustement de la transition d'opacité à `0.15s` pour concorder avec le setTimeout de 150ms du JS.
- Amélioration du bandeau de miniatures : application d'une hauteur fluide et bornée (`flex: 0 0 clamp(60px, 12%, 80px)`), retrait du ratio d'aspect fixe des miniatures (`aspect-ratio: 3/2`) au profit des calculs dynamiques de taille injectés par JavaScript, et activation de `will-change: transform` sur la piste de défilement (`.eg-viewer__track`).

### Fixed
- Correction d'un conflit d'ombre portée sur la visionneuse : suppression des ombres portées CSS/SCSS codées en dur sur `.eg-viewer` et `.eg-viewer-placeholder` afin de laisser le contrôle complet aux réglages d'ombres standards de l'éditeur WordPress (Gutenberg).
- Sélection automatique de la galerie dans le menu déroulant de la bibliothèque de médias lors de la redirection depuis l'écran Médias/Galeries (gestion du paramètre de requête natif `eg_media_gallery`).



## [1.0.4] - 2026-06-22

### Added
- Implémentation d'une action AJAX sécurisée `eg_media_get_galleries` pour l'actualisation dynamique du sélecteur de galeries.
- Mise à jour automatique des listes déroulantes de galeries à la fin des téléversements groupés (`UploadComplete`) avec pré-sélection automatique de la galerie créée à la volée.
- Implémentation du mode plein écran sur la visionneuse (toggled au clic sur la photo principale, avec bouton de fermeture "X" dans le coin supérieur droit).
- Ajout d'un contrôle de sélection de la résolution d'image (`Taille originale`, `Grande`, `Moyenne`, `Miniature`) dans l'inspecteur Gutenberg du bloc Visionneuse.
- Intégration des supports natifs WordPress pour les espacements (marges internes/externes), les bordures (arrondi de coins) et les ombres sur le bloc Visionneuse.

### Changed
- Augmentation de la limite de mémoire PHP pour le traitement d'images à `512M` via le filtre WordPress `image_memory_limit` afin d'éviter les crashs lors d'imports groupés.
- Ajout d'un fallback d'optimisation robuste utilisant `WP_Image_Editor` natif de WordPress si l'extension `Imagick` n'est pas présente sur le serveur.
- Correction d'un bug Backbone JS (`TypeError`) lors du rafraîchissement de la bibliothèque de médias en utilisant `library.doEscapedQuery()`.
- Harmonisation visuelle de la visionneuse avec le thème (vignettes rectangulaires avec `aspect-ratio: 3/2` et `border-radius: 4px`, couleurs d'accentuation calquées sur la palette du thème).
- Correction du bug visuel de superposition/empilement d'images (notamment sous Safari/WebKit) en détruisant et recréant l'élément image du DOM à chaque changement, éliminant ainsi les caches GPU.
- Ajustement du diaporama pour désactiver la mise en pause automatique au survol de la souris lorsque la visionneuse est en plein écran.
- Amélioration de l'ergonomie d'édition dans Gutenberg : correction de la sélection du bloc et affichage de la barre d'outils flottante standard grâce à `useBlockProps`.
- Refonte visuelle de l'aperçu du bloc en mode édition : affichage d'un espace réservé (placeholder) premium en mode sombre (hauteur réelle de 600px), indiquant le nom de la galerie active et sa résolution choisie.

## [1.0.3] - 2026-06-22

### Added
- Implémentation du bloc Gutenberg dynamique "Visionneuse de Galerie" (`eg-media/viewer`) permettant d'afficher les galeries avec une disposition 90% / 10% (image principale / miniatures circulaires).
- Contrôles dans l'Inspector Gutenberg pour le tri avancé (par Nom ou Date EXIF, avec fallback sur la date de dépôt), l'ordre (ASC/DESC), et la vitesse du diaporama.
- Script front-end interactif en Vanilla JS (ES2021) gérant le calcul automatique des ratios d'aspect des miniatures, la navigation en boucle infinie (circulaire), le diaporama automatique et l'effet hover pour suspendre temporairement le défilement.
- Intégration de `@wordpress/scripts` pour la compilation et le packaging des blocs Gutenberg.

### Changed
- Mise à jour des dépendances npm pour le développement vers les dernières versions (`@wordpress/scripts` ^32.4.0, `adm-zip` ^0.5.17, `minimatch` ^10.2.5).

## [1.0.2] - 2026-06-22

### Added
- Implémentation du filtrage par galerie dans la liste de médias (avec l'option "Sans affectation" pour isoler les images orphelines) en mode liste et en mode grille (Backbone / requêtes AJAX `query-attachments`).
- Implémentation de la sélection d'une galerie par défaut ou de la création rapide d'une nouvelle galerie directement lors des téléversements groupés (Bulk) au-dessus de la zone de drag-and-drop.
- Script JavaScript en Vanilla JS pour intercepter les requêtes d'envoi et associer les fichiers importés à la galerie ciblée en surchargeant globalement le comportement de `wp.Uploader`.
- Rafraîchissement automatique de la bibliothèque de médias Backbone à la fin des téléversements pour assurer l'affichage immédiat des images importées avec le filtre de galerie actif.
- Implémentation d'une action groupée native `"Associer à une galerie"` en mode liste (avec sélection de galerie existante ou création rapide d'une nouvelle galerie depuis le formulaire global).
- Support du filtrage de galerie dans le modal "Image mise en avant" (ou autres boutons d'upload d'images) sur les écrans d'édition d'articles et pages.

### Changed
- Amélioration de l'ergonomie en déplaçant dynamiquement le sélecteur de galerie au-dessus de la zone de drag-and-drop.
- Remplacement du droit `edit_post` par `upload_files` pour permettre l'assignation de taxonomie lors de la création initiale de l'attachement via le hook `add_attachment` (même si Imagick n'est pas installé sur l'environnement de développement).
- Chargement étendu du script d'administration aux pages de création/édition d'articles (`post.php` et `post-new.php`).
- Amélioration du positionnement et de l'alignement des filtres (type, date, galerie) dans la modale de médias en utilisant CSS Grid pour afficher les labels au-dessus des sélecteurs de manière harmonieuse sur une seule ligne.


## [1.0.1] - 2026-06-21

### Added
- Ajout de la taxonomie personnalisée `eg_media_gallery` ("Galeries") pour regrouper les images.
- Ajout d'une interface de sélection et de création de galerie rapide directement dans le volet d'édition des pièces jointes (médias).
- Gestion de la sauvegarde automatique des relations de taxonomie lors de l'enregistrement des détails d'un média.

### Fixed
- Correction d'un conflit avec l'interface de taxonomie par défaut de WordPress qui remplaçait le sélecteur HTML personnalisé par un champ texte contenant l'identifiant (ID) numérique de la galerie. Renommage de la clé de formulaire en `eg_media_gallery_select`.
- Résolution du problème d'affichage du compteur de médias (qui restait bloqué à 0 dans l'administration des Galeries) en utilisant le callback de mise à jour du compteur de taxonomie générique (`_update_generic_term_count`), adapté aux pièces jointes (statut `inherit`).

## [1.0.0] - 2026-06-21

### Added
- Initialisation de la structure du plugin `eg-media` conforme aux directives PHP 8.4.
- Implémentation de l'optimisation des images JPEG lors de l'envoi via `Imagick`.
- Intégration d'un tableau de bord d'administration avec statistiques et statut du serveur.
- Support de l'optimisation en masse (Bulk Optimization) pour les médias existants via AJAX en arrière-plan.
