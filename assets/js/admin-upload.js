/**
 * Script d'administration pour intercepter le téléversement et ajouter la galerie ciblée.
 */
document.addEventListener('DOMContentLoaded', function () {

    // Écouter les changements sur le sélecteur de galerie par délégation d'événement (utile si chargé dynamiquement).
    document.addEventListener('change', function (event) {
        if (event.target && event.target.id === 'eg_media_target_gallery') {
            if (typeof wp !== 'undefined' && wp.Uploader && wp.Uploader.defaults) {
                wp.Uploader.defaults.multipart_params = wp.Uploader.defaults.multipart_params || {};
                wp.Uploader.defaults.multipart_params.eg_media_target_gallery = event.target.value;
            }
        }
    });

    // Initialisation au chargement pour le sélecteur statique s'il existe déjà.
    const initialSelect = document.getElementById('eg_media_target_gallery');
    if (initialSelect && typeof wp !== 'undefined' && wp.Uploader && wp.Uploader.defaults) {
        wp.Uploader.defaults.multipart_params = wp.Uploader.defaults.multipart_params || {};
        wp.Uploader.defaults.multipart_params.eg_media_target_gallery = initialSelect.value;
    }

    // Surcharge de wp.Uploader pour intercepter toutes les instanciations (Gutenberg, page Ajouter, bibliothèque, etc.)
    if (typeof wp !== 'undefined' && wp.Uploader) {
        const OriginalUploader = wp.Uploader;
        wp.Uploader = function (options) {
            const gallerySelect = document.getElementById('eg_media_target_gallery');
            const val = gallerySelect ? gallerySelect.value : '';
            options.multipart_params = options.multipart_params || {};
            options.multipart_params.eg_media_target_gallery = val;

            const instance = new OriginalUploader(options);

            if (instance.uploader) {
                instance.uploader.bind('BeforeUpload', function (up) {
                    const latestSelect = document.getElementById('eg_media_target_gallery');
                    const currentVal = latestSelect ? latestSelect.value : '';
                    up.settings.multipart_params = up.settings.multipart_params || {};
                    up.settings.multipart_params.eg_media_target_gallery = currentVal;
                });
            }

            return instance;
        };
        // Conserver les propriétés statiques
        Object.assign(wp.Uploader, OriginalUploader);
    }

    // Filtre pour la vue Grille de la bibliothèque de médias (Backbone)
    if (typeof wp !== 'undefined' && wp.media && wp.media.view && wp.media.view.AttachmentFilters) {
        // Définition de notre composant de filtre personnalisé
        wp.media.view.AttachmentFilters.EGMediaGallery = wp.media.view.AttachmentFilters.extend({
            id: 'media-attachment-eg-media-gallery-filter',
            createFilters: function () {
                const filters = {};

                filters['all'] = {
                    text: 'Toutes les galeries',
                    props: {
                        eg_media_gallery_filter: ''
                    },
                    priority: 10
                };

                filters['orphan'] = {
                    text: '— Sans affectation —',
                    props: {
                        eg_media_gallery_filter: 'orphan'
                    },
                    priority: 20
                };

                if (window.egMediaUploadData && window.egMediaUploadData.galleries) {
                    window.egMediaUploadData.galleries.forEach(function (gallery) {
                        filters[gallery.term_id] = {
                            text: gallery.name,
                            props: {
                                eg_media_gallery_filter: gallery.term_id
                            },
                            priority: 30
                        };
                    });
                }

                this.filters = filters;
            }
        });

        // Surcharge de la zone de filtres (AttachmentsBrowser) pour insérer notre filtre
        const OriginalAttachmentsBrowser = wp.media.view.AttachmentsBrowser;
        wp.media.view.AttachmentsBrowser = OriginalAttachmentsBrowser.extend({
            createToolbar: function () {
                OriginalAttachmentsBrowser.prototype.createToolbar.apply(this, arguments);

                this.toolbar.set('egMediaGalleryFilter', new wp.media.view.AttachmentFilters.EGMediaGallery({
                    controller: this.controller,
                    model:      this.collection.props,
                    priority:   -80
                }).render());
            }
        });
    }
});
