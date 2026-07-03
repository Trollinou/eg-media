# Guide d'Utilisation - EG Media Manager

Ce guide détaille comment utiliser les différentes fonctionnalités de EG Media Manager pour insérer des galeries et des albums dans votre site WordPress.

---

## 1. Visionneuse de Galerie (Bloc Gutenberg)

Pour insérer une galerie d'images directement à l'aide de l'éditeur de blocs Gutenberg :
1. Ajoutez le bloc **Visionneuse de Galerie** (`eg-media/viewer`).
2. Dans le panneau latéral de droite, configurez les options suivantes :
   - **Source** : Sélectionnez **Galerie locale** (liée à la taxonomie `eg_media_gallery`) ou **Album distant Piwigo**.
   - **Galerie** : Choisissez la galerie à afficher dans le menu déroulant.
   - **Mise en page (Layout)** : 
     - **Visionneuse (Viewer)** : Un carrousel classique d'images avec miniature active et défilement.
     - **Grille justifiée (Justified Grid)** : Grille intelligente où les images conservent leur proportion d'origine et s'alignent automatiquement par ligne, avec option de chargement progressif.
   - **Tri** : Triez les photos par nom ou par date, en ordre ascendant ou descendant.
   - **Diaporama** : Activez le diaporama automatique (disponible uniquement en mode Visionneuse).
   - **Images par page** : Le nombre d'images affichées initialement en mode Grille justifiée (les autres seront chargées en cliquant sur "Charger plus").

---

## 2. Albums (Regroupement de Galeries)

La fonctionnalité d'Albums vous permet de regrouper plusieurs galeries (qu'elles soient locales ou Piwigo) sur une seule et même page, classées par thématique, avec un affichage esthétique sous forme de cartes d'entête.

### Créer un Album
1. Dans le menu de votre administration WordPress, allez dans **Albums** -> **Ajouter un nouveau**.
2. Saisissez le titre de votre album (ex: "Saison 2025-2026").
3. Dans la zone **Contenu et Organisation de l'Album** :
   - **Mode de Tri** : Choisissez entre *Tri manuel (Glisser-Déposer)* ou *Tri automatique par ordre alphabétique*.
   - **Ajouter des éléments** : Utilisez les listes déroulantes pour ajouter des galeries locales ou des albums Piwigo.
   - Si vous avez choisi le *Tri manuel*, vous pouvez ordonner vos galeries en les faisant glisser verticalement.
4. Cliquez sur **Publier** ou **Mettre à jour**.
5. Copiez le code court généré en haut de la metabox (ex: `[eg_media_album id="123"]`).

### Insérer un Album sur votre site
Pour afficher l'album sur votre site, il vous suffit de coller le code court copié dans le contenu d'un article ou d'une page :

```text
[eg_media_album id="VOTRE_ID_ALBUM"]
```

**Comportement visuel** :
- L'album s'affiche sous forme d'une grille moderne à 2 colonnes (sur tablette et desktop) et 1 colonne (sur mobile).
- Chaque galerie est représentée par une carte comprenant sa photo de couverture et son titre.
- En cliquant sur une carte, un calque modal (overlay) noir translucide s'affiche sur tout l'écran avec le titre de la galerie et ses photos disposées dans une **Grille justifiée**.
- En cliquant sur l'une des photos de la grille, la visionneuse se lance en mode plein écran (lightbox) avec les contrôles de navigation par flèches ou roulette de la souris.

---

## 3. Insertion d'Images Piwigo dans vos Pages (Bloc Image Natif)

Vous pouvez insérer n'est-ce qu'une seule image provenant de Piwigo dans le contenu de votre page en utilisant le bloc **Image** standard de WordPress :
1. Insérez un bloc **Image** classique dans votre page ou article.
2. Dans la barre latérale droite de réglages du bloc, localisez l'onglet **"Intégration Piwigo"**.
3. Cliquez sur le bouton **"Sélectionner depuis Piwigo"**.
4. Dans la fenêtre modale, sélectionnez votre album Piwigo puis cliquez sur la photo de votre choix.
5. L'image est automatiquement téléchargée en arrière-plan et insérée dans votre bloc.

### Rangement et Classement Automatique
Pour éviter d'encombrer votre médiathèque en vrac, chaque image importée de Piwigo (que ce soit via le bloc Image ou comme Image mise en avant) est **automatiquement classée** dans une galerie locale nommée **`Piwigo - [Nom de l'album]`**. Vous pouvez ensuite filtrer vos images par galerie ou réutiliser ces imports organisés dans d'autres contenus.
