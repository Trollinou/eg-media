/**
 * Script d'administration pour intercepter le téléversement et ajouter la galerie ciblée.
 */
(function () {
    /**
     * Force le rechargement de la bibliothèque de médias Backbone pour afficher les nouveaux éléments filtrés.
     */
    function refreshMediaLibrary() {
        if (typeof wp !== 'undefined' && wp.media && wp.media.frame) {
            const state = wp.media.frame.state();
            if (state) {
                const library = state.get('library');
                if (library && library.props && typeof library.props.trigger === 'function') {
                    library.props.trigger('change');
                }
            }
        }
    }

    // Surcharge immédiate de wp.Uploader pour intercepter toutes les instanciations futures.
    if (typeof wp !== 'undefined' && wp.Uploader) {
        const OriginalUploader = wp.Uploader;
        wp.Uploader = function (options) {
            const gallerySelect = document.getElementById('eg_media_target_gallery');
            const newGalleryInput = document.getElementById('eg_media_new_target_gallery');
            
            const val = gallerySelect ? gallerySelect.value : '';
            const newVal = newGalleryInput ? newGalleryInput.value : '';

            options.multipart_params = options.multipart_params || {};
            options.multipart_params.eg_media_target_gallery = val;
            options.multipart_params.eg_media_new_target_gallery = newVal;

            const instance = new OriginalUploader(options);

            if (instance.uploader) {
                instance.uploader.bind('BeforeUpload', function (up) {
                    const latestSelect = document.getElementById('eg_media_target_gallery');
                    const latestNewInput = document.getElementById('eg_media_new_target_gallery');
                    
                    const currentVal = latestSelect ? latestSelect.value : '';
                    const currentNewVal = latestNewInput ? latestNewInput.value : '';

                    up.settings.multipart_params = up.settings.multipart_params || {};
                    up.settings.multipart_params.eg_media_target_gallery = currentVal;
                    up.settings.multipart_params.eg_media_new_target_gallery = currentNewVal;
                });

                // Rafraîchir la vue de la bibliothèque une fois les uploads terminés
                instance.uploader.bind('UploadComplete', function () {
                    setTimeout(refreshMediaLibrary, 500);
                });
            }

            return instance;
        };
        Object.assign(wp.Uploader, OriginalUploader);
    }

    document.addEventListener('DOMContentLoaded', function () {

        /**
         * Déplace l'interface utilisateur du sélecteur de galerie au-dessus de la zone de drag-and-drop.
         */
        function moveSelectorToTop() {
            const container = document.querySelector('.eg-media-upload-gallery-container');
            const dragDrop = document.getElementById('drag-drop-area');
            if (container && dragDrop && dragDrop.parentNode) {
                if (dragDrop.previousElementSibling !== container) {
                    dragDrop.parentNode.insertBefore(container, dragDrop);
                }
            }
        }

        /**
         * Met à jour les paramètres d'envoi de tous les uploaders actifs et par défaut.
         */
        function updateAllUploaders() {
            const targetSelect = document.getElementById('eg_media_target_gallery');
            const targetNewInput = document.getElementById('eg_media_new_target_gallery');
            if (!targetSelect && !targetNewInput) {
                return;
            }
            const val = targetSelect ? targetSelect.value : '';
            const newVal = targetNewInput ? targetNewInput.value : '';

            // 1. Defaults de wp.Uploader
            if (typeof wp !== 'undefined' && wp.Uploader && wp.Uploader.defaults) {
                wp.Uploader.defaults.multipart_params = wp.Uploader.defaults.multipart_params || {};
                wp.Uploader.defaults.multipart_params.eg_media_target_gallery = val;
                wp.Uploader.defaults.multipart_params.eg_media_new_target_gallery = newVal;
            }

            // 2. Instance globale "uploader" (media-new.php)
            if (typeof uploader !== 'undefined' && uploader.settings) {
                uploader.settings.multipart_params = uploader.settings.multipart_params || {};
                uploader.settings.multipart_params.eg_media_target_gallery = val;
                uploader.settings.multipart_params.eg_media_new_target_gallery = newVal;
            }

            // 3. Uploader Backbone (wp.media.uploader)
            if (typeof wp !== 'undefined' && wp.media && wp.media.uploader && wp.media.uploader.uploader) {
                const wpUp = wp.media.uploader.uploader;
                if (wpUp.settings) {
                    wpUp.settings.multipart_params = wpUp.settings.multipart_params || {};
                    wpUp.settings.multipart_params.eg_media_target_gallery = val;
                    wpUp.settings.multipart_params.eg_media_new_target_gallery = newVal;
                }
            }
        }

        /**
         * Lie notre logique BeforeUpload à l'instance globale Plupload de la page de téléversement.
         */
        function bindToGlobalUploader() {
            // 1. Uploader global (media-new.php)
            if (typeof uploader !== 'undefined' && uploader.bind && !uploader._egMediaBound) {
                uploader.bind('BeforeUpload', function (up) {
                    const targetSelect = document.getElementById('eg_media_target_gallery');
                    const targetNewInput = document.getElementById('eg_media_new_target_gallery');
                    
                    const val = targetSelect ? targetSelect.value : '';
                    const newVal = targetNewInput ? targetNewInput.value : '';

                    up.settings.multipart_params = up.settings.multipart_params || {};
                    up.settings.multipart_params.eg_media_target_gallery = val;
                    up.settings.multipart_params.eg_media_new_target_gallery = newVal;
                });

                // Rafraîchir la vue lors de la complétion pour l'uploader de page
                uploader.bind('UploadComplete', function () {
                    setTimeout(refreshMediaLibrary, 500);
                });

                uploader._egMediaBound = true;
            }

            // 2. Uploader Backbone (wp.media.uploader.uploader)
            if (typeof wp !== 'undefined' && wp.media && wp.media.uploader && wp.media.uploader.uploader) {
                const wpUp = wp.media.uploader.uploader;
                if (wpUp.bind && !wpUp._egMediaBound) {
                    wpUp.bind('BeforeUpload', function (up) {
                        const targetSelect = document.getElementById('eg_media_target_gallery');
                        const targetNewInput = document.getElementById('eg_media_new_target_gallery');
                        
                        const val = targetSelect ? targetSelect.value : '';
                        const newVal = targetNewInput ? targetNewInput.value : '';

                        up.settings.multipart_params = up.settings.multipart_params || {};
                        up.settings.multipart_params.eg_media_target_gallery = val;
                        up.settings.multipart_params.eg_media_new_target_gallery = newVal;
                    });

                    wpUp.bind('UploadComplete', function () {
                        setTimeout(refreshMediaLibrary, 500);
                    });

                    wpUp._egMediaBound = true;
                }
            }
        }

        // Écouter les changements sur le sélecteur de galerie (vide la création si sélectionné).
        document.addEventListener('change', function (event) {
            if (event.target && event.target.id === 'eg_media_target_gallery') {
                const newGalleryInput = document.getElementById('eg_media_new_target_gallery');
                if (newGalleryInput && event.target.value !== '') {
                    newGalleryInput.value = '';
                }
                updateAllUploaders();
            }
        });

        // Écouter les saisies sur le champ de création de galerie (vide la sélection si saisi).
        document.addEventListener('input', function (event) {
            if (event.target && event.target.id === 'eg_media_new_target_gallery') {
                const selectField = document.getElementById('eg_media_target_gallery');
                if (selectField && event.target.value !== '') {
                    selectField.value = '';
                }
                updateAllUploaders();
            }
        });

        // Liaison de sécurité sur les gestes utilisateur pour s'assurer que le sélecteur est bien déplacé et synchronisé.
        const triggerElements = ['dragover', 'mouseenter', 'click'];
        triggerElements.forEach(function (evtName) {
            document.addEventListener(evtName, function () {
                moveSelectorToTop();
                bindToGlobalUploader();
                updateAllUploaders();
            }, { passive: true });
        });

        // Exécution initiale
        moveSelectorToTop();
        bindToGlobalUploader();
        updateAllUploaders();

        // Filtre pour la vue Grille de la bibliothèque de médias (Backbone)
        if (typeof wp !== 'undefined' && wp.media && wp.media.view && wp.media.view.AttachmentFilters) {
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

            const OriginalAttachmentsBrowser = wp.media.view.AttachmentsBrowser;
            wp.media.view.AttachmentsBrowser = OriginalAttachmentsBrowser.extend({
                createToolbar: function () {
                    OriginalAttachmentsBrowser.prototype.createToolbar.apply(this, arguments);

                    this.toolbar.set('egMediaGalleryFilter', new wp.media.view.AttachmentFilters.EGMediaGallery({
                        controller: this.controller,
                        model:      this.collection.props,
                        priority:   -50
                    }).render());
                }
            });
        }

        // Action groupée pour la vue liste (Bulk Assign)
        function initBulkActionsListMode() {
            const bulkSelectors = document.querySelectorAll('select[name="action"], select[name="action2"]');
            if (bulkSelectors.length === 0) {
                return;
            }

            const translate = (typeof wp !== 'undefined' && wp.i18n && wp.i18n.__) ? wp.i18n.__ : function (text) { return text; };

            let optionsHtml = '<option value="">' + translate('— Choisir une galerie —', 'eg-media') + '</option>';
            optionsHtml += '<option value="orphan">' + translate('— Sans affectation —', 'eg-media') + '</option>';
            if (window.egMediaUploadData && window.egMediaUploadData.galleries) {
                window.egMediaUploadData.galleries.forEach(function (gallery) {
                    optionsHtml += '<option value="' + gallery.term_id + '">' + gallery.name + '</option>';
                });
            }

            bulkSelectors.forEach(function (selector) {
                const container = document.createElement('span');
                container.className = 'eg-media-bulk-assign-fields';
                container.style.display = 'none';
                container.style.verticalAlign = 'middle';
                container.style.marginLeft = '8px';
                container.style.marginRight = '8px';

                container.innerHTML = `
                    <select name="eg_media_bulk_gallery" class="eg-media-bulk-gallery-select" style="vertical-align: middle; height: 30px;">
                        ${optionsHtml}
                    </select>
                    <span style="font-size: 13px; color: #646970; margin: 0 4px; vertical-align: middle;">ou</span>
                    <input type="text" 
                           name="eg_media_bulk_new_gallery" 
                           class="eg-media-bulk-new-gallery-input" 
                           placeholder="${translate('Nouvelle galerie...', 'eg-media')}" 
                           style="vertical-align: middle; height: 30px; line-height: 30px; font-size: 13px; padding: 0 8px;" />
                `;

                selector.parentNode.insertBefore(container, selector.nextSibling);

                selector.addEventListener('change', function () {
                    if (this.value === 'eg_media_bulk_assign') {
                        container.style.display = 'inline-block';
                    } else {
                        container.style.display = 'none';
                    }
                });
            });

            // Synchroniser les valeurs entre les deux blocs (haut et bas)
            const selects = document.querySelectorAll('.eg-media-bulk-gallery-select');
            const inputs = document.querySelectorAll('.eg-media-bulk-new-gallery-input');

            selects.forEach(function (select) {
                select.addEventListener('change', function () {
                    const currentVal = this.value;
                    // Mettre à jour l'autre select
                    selects.forEach(function (s) {
                        if (s !== select) {
                            s.value = currentVal;
                        }
                    });
                    // Vider les inputs de texte de création
                    if (currentVal !== '') {
                        inputs.forEach(function (i) {
                            i.value = '';
                        });
                    }
                });
            });

            inputs.forEach(function (input) {
                input.addEventListener('input', function () {
                    const currentVal = this.value;
                    // Mettre à jour l'autre input
                    inputs.forEach(function (i) {
                        if (i !== input) {
                            i.value = currentVal;
                        }
                    });
                    // Vider les selects
                    if (currentVal !== '') {
                        selects.forEach(function (s) {
                            s.value = '';
                        });
                    }
                });
            });
        }

        initBulkActionsListMode();
    });
})();
