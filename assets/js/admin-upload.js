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
                if (library) {
                    if (typeof library.doEscapedQuery === 'function') {
                        library.doEscapedQuery();
                    } else if (library.props && typeof library.props.trigger === 'function') {
                        library.props.trigger('change');
                    }
                }
            }
        }
    }

    /**
     * Recharge la liste des galeries depuis le serveur et met à jour les sélecteurs HTML.
     */
    function refreshGallerySelectors() {
        if (typeof jQuery === 'undefined') {
            return;
        }

        jQuery.post(ajaxurl, {
            action: 'eg_media_get_galleries'
        }, function(response) {
            if (response.success && Array.isArray(response.data)) {
                const galleries = response.data;

                // Mettre à jour la variable globale pour Backbone
                if (window.egMediaUploadData) {
                    window.egMediaUploadData.galleries = galleries;
                }

                // 1. Mettre à jour le sélecteur d'upload par défaut
                const uploadSelect = document.getElementById('eg_media_target_gallery');
                if (uploadSelect) {
                    const currentVal = uploadSelect.value;
                    const newGalleryInput = document.getElementById('eg_media_new_target_gallery');
                    let targetVal = currentVal;

                    // Si on vient de créer une nouvelle galerie avec du texte
                    if (newGalleryInput && newGalleryInput.value.trim() !== '') {
                        const createdName = newGalleryInput.value.trim().toLowerCase();
                        // Trouver l'ID de la galerie créée correspondante
                        const match = galleries.find(function(g) {
                            return g.name.toLowerCase() === createdName;
                        });
                        if (match) {
                            targetVal = String(match.term_id);
                        }
                        // Vider le champ de saisie
                        newGalleryInput.value = '';
                    }

                    // Reconstruire les options
                    let optionsHtml = '<option value="">— Aucune galerie par défaut —</option>';
                    galleries.forEach(function(gallery) {
                        optionsHtml += `<option value="${gallery.term_id}">${gallery.name}</option>`;
                    });
                    uploadSelect.innerHTML = optionsHtml;
                    uploadSelect.value = targetVal;
                }

                // 2. Mettre à jour le sélecteur Bulk Assign (mode liste)
                const bulkSelects = document.querySelectorAll('.eg-media-bulk-gallery-select');
                bulkSelects.forEach(function(select) {
                    const currentVal = select.value;
                    let optionsHtml = '<option value="">— Choisir une galerie —</option>';
                    optionsHtml += '<option value="orphan">— Sans affectation —</option>';
                    galleries.forEach(function(gallery) {
                        optionsHtml += `<option value="${gallery.term_id}">${gallery.name}</option>`;
                    });
                    select.innerHTML = optionsHtml;
                    select.value = currentVal;
                });
            }
        });
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
                    setTimeout(function () {
                        refreshMediaLibrary();
                        refreshGallerySelectors();
                    }, 500);
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
                    setTimeout(function () {
                        refreshMediaLibrary();
                        refreshGallerySelectors();
                    }, 500);
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
                        setTimeout(function () {
                            refreshMediaLibrary();
                            refreshGallerySelectors();
                        }, 500);
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

                    if (wp.media.view.Label) {
                        this.toolbar.set('egMediaGalleryFilterLabel', new wp.media.view.Label({
                            value:      'Filtrer par galerie',
                            attributes: {
                                'for':  'media-attachment-eg-media-gallery-filter'
                            },
                            priority:   -51
                        }).render());
                    }

                    if (this.collection && this.collection.props) {
                        const urlParams = new URLSearchParams(window.location.search);
                        let filterVal = urlParams.get('eg_media_gallery_filter');
                        
                        // Fallback pour eg_media_gallery (slug)
                        if (!filterVal && urlParams.has('eg_media_gallery')) {
                            const slug = urlParams.get('eg_media_gallery');
                            if (window.egMediaUploadData && window.egMediaUploadData.galleries) {
                                const matched = window.egMediaUploadData.galleries.find(function (g) {
                                    return g.slug === slug || String(g.term_id) === slug;
                                });
                                if (matched) {
                                    filterVal = matched.term_id;
                                }
                            }
                        }

                        if (filterVal) {
                            const parsedInt = parseInt(filterVal, 10);
                            const finalVal = !isNaN(parsedInt) && String(parsedInt) === String(filterVal) ? parsedInt : filterVal;
                            this.collection.props.set('eg_media_gallery_filter', finalVal);
                        } else if (window.location.pathname.indexOf('upload.php') !== -1) {
                            if (this.collection.props.get('eg_media_gallery_filter') === undefined) {
                                this.collection.props.set('eg_media_gallery_filter', 'orphan');
                            }
                        }

                        // Mettre à jour dynamiquement le lien du bouton de vue Liste (.view-list) quand la galerie change
                        const updateListLink = function (value) {
                            const listBtn = document.querySelector('.view-list');
                            if (listBtn) {
                                let href = listBtn.getAttribute('href') || 'upload.php?mode=list';
                                const parts = href.split('?');
                                const baseUrl = parts[0];
                                const params = new URLSearchParams(parts[1] || '');
                                if (value && value !== 'all') {
                                    params.set('eg_media_gallery_filter', value);
                                } else {
                                    params.delete('eg_media_gallery_filter');
                                }
                                params.set('mode', 'list');
                                listBtn.setAttribute('href', baseUrl + '?' + params.toString());
                            }
                        };

                        this.collection.props.on('change:eg_media_gallery_filter', function (model, value) {
                            updateListLink(value);
                        });

                        // Initialiser le lien au chargement
                        updateListLink(this.collection.props.get('eg_media_gallery_filter'));
                    }

                    this.toolbar.set('egMediaGalleryFilter', new wp.media.view.AttachmentFilters.EGMediaGallery({
                        controller: this.controller,
                        model:      this.collection.props,
                        priority:   -50
                    }).render());
                }
            });

            // Surcharge de wp.media.view.Attachment et wp.media.view.Attachment.Library pour ajouter la classe CSS et le badge étoile sur l'image de référence
            if (wp.media.view.Attachment) {
                const OriginalAttachment = wp.media.view.Attachment;
                wp.media.view.Attachment = OriginalAttachment.extend({
                    className: function () {
                        let classes = '';
                        if (typeof OriginalAttachment.prototype.className === 'function') {
                            classes = OriginalAttachment.prototype.className.apply(this, arguments);
                        } else if (typeof OriginalAttachment.prototype.className === 'string') {
                            classes = OriginalAttachment.prototype.className;
                        }
                        if (this.model.get('eg_media_is_reference')) {
                            classes += ' eg-media-is-reference';
                        }
                        return classes;
                    },
                    render: function () {
                        if (typeof OriginalAttachment.prototype.render === 'function') {
                            OriginalAttachment.prototype.render.apply(this, arguments);
                        }
                        if (this.model.get('eg_media_is_reference')) {
                            const galleryName = this.model.get('eg_media_reference_gallery_name') || '';
                            const starHtml = `<div class="eg-media-star-badge" title="Image de référence de la galerie : ${_.escape(galleryName)}">★</div>`;
                            this.$el.append(starHtml);
                        }
                        return this;
                    }
                });

                if (wp.media.view.Attachment.Library) {
                    const OriginalAttachmentLibrary = wp.media.view.Attachment.Library;
                    wp.media.view.Attachment.Library = OriginalAttachmentLibrary.extend({
                        className: function () {
                            let classes = '';
                            if (typeof OriginalAttachmentLibrary.prototype.className === 'function') {
                                classes = OriginalAttachmentLibrary.prototype.className.apply(this, arguments);
                            } else if (typeof OriginalAttachmentLibrary.prototype.className === 'string') {
                                classes = OriginalAttachmentLibrary.prototype.className;
                            }
                            if (this.model.get('eg_media_is_reference')) {
                                classes += ' eg-media-is-reference';
                            }
                            return classes;
                        },
                        render: function () {
                            if (typeof OriginalAttachmentLibrary.prototype.render === 'function') {
                                OriginalAttachmentLibrary.prototype.render.apply(this, arguments);
                            }
                            if (this.model.get('eg_media_is_reference')) {
                                const galleryName = this.model.get('eg_media_reference_gallery_name') || '';
                                const starHtml = `<div class="eg-media-star-badge" title="Image de référence de la galerie : ${_.escape(galleryName)}">★</div>`;
                                this.$el.append(starHtml);
                            }
                            return this;
                        }
                    });
                }
            }
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

        // Conserver le filtre de galerie dans l'URL lors du changement entre les vues Liste et Grille
        function initViewSwitchFilterPreservation() {
            const listSelect = document.getElementById('eg_media_gallery_filter');
            if (listSelect) {
                const updateGridLink = function () {
                    const val = listSelect.value;
                    const gridBtn = document.querySelector('.view-grid');
                    if (gridBtn) {
                        let href = gridBtn.getAttribute('href') || 'upload.php?mode=grid';
                        const parts = href.split('?');
                        const baseUrl = parts[0];
                        const params = new URLSearchParams(parts[1] || '');
                        if (val) {
                            params.set('eg_media_gallery_filter', val);
                        } else {
                            params.delete('eg_media_gallery_filter');
                        }
                        params.set('mode', 'grid');
                        gridBtn.setAttribute('href', baseUrl + '?' + params.toString());
                    }
                };

                listSelect.addEventListener('change', updateGridLink);
                updateGridLink(); // Exécuter une fois au chargement
            }
        }

        initViewSwitchFilterPreservation();
        initBulkActionsListMode();
    });
})();
