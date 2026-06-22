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

    // Hook sur wp.media si Backbone est chargé.
    if (typeof wp !== 'undefined' && wp.media) {
        if (wp.media.view && wp.media.view.Uploader) {
            const originalInit = wp.media.view.Uploader.prototype.initialize;
            wp.media.view.Uploader.prototype.initialize = function () {
                originalInit.apply(this, arguments);
                updateUploaderParams();
            };
        }
    }
});
