/**
 * Script d'administration pour intercepter le téléversement et ajouter la galerie ciblée.
 */
document.addEventListener('DOMContentLoaded', function () {
    const gallerySelect = document.getElementById('eg_media_target_gallery');
    if (!gallerySelect) {
        return;
    }

    /**
     * Met à jour les paramètres d'envoi du uploader WordPress.
     */
    function updateUploaderParams() {
        const val = gallerySelect.value;
        if (typeof wp !== 'undefined' && wp.Uploader && wp.Uploader.defaults) {
            wp.Uploader.defaults.multipart_params = wp.Uploader.defaults.multipart_params || {};
            wp.Uploader.defaults.multipart_params.eg_media_target_gallery = val;
        }
    }

    // Écouter les changements sur le sélecteur.
    gallerySelect.addEventListener('change', updateUploaderParams);

    // Première initialisation.
    updateUploaderParams();

    // Surcharge de wp.Uploader pour intercepter toutes les instanciations (Gutenberg, page Ajouter, bibliothèque, etc.)
    if (typeof wp !== 'undefined' && wp.Uploader) {
        const OriginalUploader = wp.Uploader;
        wp.Uploader = function (options) {
            const val = gallerySelect.value;
            options.multipart_params = options.multipart_params || {};
            options.multipart_params.eg_media_target_gallery = val;

            const instance = new OriginalUploader(options);

            if (instance.uploader) {
                instance.uploader.bind('BeforeUpload', function (up) {
                    const currentVal = gallerySelect.value;
                    up.settings.multipart_params = up.settings.multipart_params || {};
                    up.settings.multipart_params.eg_media_target_gallery = currentVal;
                });
            }

            return instance;
        };
        // Conserver les propriétés statiques
        Object.assign(wp.Uploader, OriginalUploader);
    }
});
