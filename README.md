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

### 3. Tableau de Bord d'Administration
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
3. Activez le plugin depuis l'interface d'administration de WordPress (**Extensions > Extensions installées**).

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
  - `includes/Admin/` : Gestion du tableau de bord (Configuration et Statistiques).
  - `includes/Services/` : Services de traitement d'images (`Processor` et `BulkProcessor`).
  - `includes/Enums/` : Énumérations typées PHP 8.4.
- **Sécurité renforcée** : Utilisation systématique de Nonces, de vérifications de rôles (`current_user_can`) et d'échappement des données à l'affichage (`esc_html`, `esc_attr`, `esc_url`).
