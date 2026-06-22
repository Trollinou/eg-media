# Changelog

Toutes les modifications notables de ce projet seront consignées dans ce fichier.

Le format est basé sur [Keep a Changelog](https://keepachangelog.com/fr/1.0.0/)
et ce projet respecte le [Versionnage Sémantique](https://semver.org/lang/fr/).

## [1.0.2] - 2026-06-22

### Added
- Implémentation du filtrage par galerie dans la liste de médias (avec l'option "Sans affectation" pour isoler les images orphelines).
- Implémentation de la sélection d'une galerie par défaut lors des téléversements groupés (Bulk) au-dessus de la zone de drag-and-drop.
- Script JavaScript en Vanilla JS pour intercepter les requêtes d'envoi et associer les fichiers importés à la galerie ciblée.

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
