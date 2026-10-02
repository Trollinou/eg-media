/**
 * Script d'administration TypeScript pour intercepter le téléversement et ajouter la galerie ciblée.
 *
 * ES2022 TypeScript en mode strict.
 */

( () => {
	/**
	 * Force le rechargement de la bibliothèque de médias Backbone pour afficher les nouveaux éléments filtrés.
	 */
	function refreshMediaLibrary(): void {
		if ( typeof window.wp !== 'undefined' && window.wp.media && window.wp.media.frame ) {
			const state = window.wp.media.frame.state();
			if ( state ) {
				const library = state.get( 'library' );
				if ( library ) {
					if ( typeof library.doEscapedQuery === 'function' ) {
						library.doEscapedQuery();
					} else if (
						library.props &&
						typeof library.props.trigger === 'function'
					) {
						library.props.trigger( 'change' );
					}
				}
			}
		}
	}

	/**
	 * Synchronise le filtre de la médiathèque Backbone avec la galerie cible sélectionnée ou saisie.
	 */
	function syncLibraryFilterWithTarget(): void {
		const targetSelect = document.getElementById( 'eg_media_target_gallery' ) as HTMLSelectElement | null;
		const targetNewInput = document.getElementById( 'eg_media_new_target_gallery' ) as HTMLInputElement | null;

		let filterVal: string | number = 'orphan';

		if ( targetNewInput && targetNewInput.value.trim() !== '' ) {
			filterVal = '';
		} else if ( targetSelect && targetSelect.value !== '' ) {
			filterVal = parseInt( targetSelect.value, 10 );
		}

		if ( typeof window.wp !== 'undefined' && window.wp.media && window.wp.media.frame ) {
			const state = window.wp.media.frame.state();
			if ( state ) {
				const library = state.get( 'library' );
				if ( library && library.props ) {
					if ( library.props.get( 'eg_media_gallery_filter' ) !== filterVal ) {
						library.props.set( 'eg_media_gallery_filter', filterVal );
					}
				}
			}
		}
	}

	/**
	 * Recharge la liste des galeries depuis le serveur et met à jour les sélecteurs HTML.
	 */
	function refreshGallerySelectors(): void {
		if ( typeof jQuery === 'undefined' ) {
			return;
		}

		jQuery.post(
			ajaxurl,
			{
				action: 'eg_media_get_galleries',
				nonce: window.egMediaUploadData ? window.egMediaUploadData.nonce : '',
			},
			( response: { success: boolean; data?: any[] } ) => {
				if ( response.success && Array.isArray( response.data ) ) {
					const galleries = response.data;

					if ( window.egMediaUploadData ) {
						window.egMediaUploadData.galleries = galleries;
					}

					const uploadSelect = document.getElementById( 'eg_media_target_gallery' ) as HTMLSelectElement | null;
					if ( uploadSelect ) {
						const currentVal = uploadSelect.value;
						const newGalleryInput = document.getElementById( 'eg_media_new_target_gallery' ) as HTMLInputElement | null;
						let targetVal = currentVal;

						if ( newGalleryInput && newGalleryInput.value.trim() !== '' ) {
							const createdName = newGalleryInput.value.trim().toLowerCase();
							const match = galleries.find( ( g ) => g.name.toLowerCase() === createdName );
							if ( match ) {
								targetVal = String( match.term_id );
							}
							newGalleryInput.value = '';
						}

						let optionsHtml = '<option value="">— Aucune galerie par défaut —</option>';
						galleries.forEach( ( gallery ) => {
							optionsHtml += `<option value="${ gallery.term_id }">${ gallery.name }</option>`;
						} );
						uploadSelect.innerHTML = optionsHtml;
						uploadSelect.value = targetVal;
					}

					const bulkSelects = document.querySelectorAll<HTMLSelectElement>( '.eg-media-bulk-gallery-select' );
					bulkSelects.forEach( ( select ) => {
						const currentVal = select.value;
						let optionsHtml = '<option value="">— Choisir une galerie —</option>';
						optionsHtml += '<option value="orphan">— Sans affectation —</option>';
						galleries.forEach( ( gallery ) => {
							optionsHtml += `<option value="${ gallery.term_id }">${ gallery.name }</option>`;
						} );
						select.innerHTML = optionsHtml;
						select.value = currentVal;
					} );

					syncLibraryFilterWithTarget();
				}
			}
		);
	}

	if ( typeof window.wp !== 'undefined' && window.wp.Uploader ) {
		const OriginalUploader = window.wp.Uploader;
		window.wp.Uploader = function ( options: any ) {
			const gallerySelect = document.getElementById( 'eg_media_target_gallery' ) as HTMLSelectElement | null;
			const newGalleryInput = document.getElementById( 'eg_media_new_target_gallery' ) as HTMLInputElement | null;

			const val = gallerySelect ? gallerySelect.value : '';
			const newVal = newGalleryInput ? newGalleryInput.value : '';

			options.multipart_params = options.multipart_params || {};
			options.multipart_params.eg_media_target_gallery = val;
			options.multipart_params.eg_media_new_target_gallery = newVal;

			const instance = new OriginalUploader( options );

			if ( instance.uploader ) {
				instance.uploader.bind( 'BeforeUpload', ( up: any ) => {
					const latestSelect = document.getElementById( 'eg_media_target_gallery' ) as HTMLSelectElement | null;
					const latestNewInput = document.getElementById( 'eg_media_new_target_gallery' ) as HTMLInputElement | null;

					const currentVal = latestSelect ? latestSelect.value : '';
					const currentNewVal = latestNewInput ? latestNewInput.value : '';

					up.settings.multipart_params = up.settings.multipart_params || {};
					up.settings.multipart_params.eg_media_target_gallery = currentVal;
					up.settings.multipart_params.eg_media_new_target_gallery = currentNewVal;

					syncLibraryFilterWithTarget();
				} );

				instance.uploader.bind( 'UploadComplete', () => {
					setTimeout( () => {
						refreshMediaLibrary();
						refreshGallerySelectors();
					}, 500 );
				} );
			}

			return instance;
		};
		Object.assign( window.wp.Uploader, OriginalUploader );
	}

	document.addEventListener( 'DOMContentLoaded', () => {
		function moveSelectorToTop(): void {
			const container = document.querySelector<HTMLElement>( '.eg-media-upload-gallery-container' );
			const dragDrop = document.getElementById( 'drag-drop-area' );
			if ( container && dragDrop && dragDrop.parentNode ) {
				if ( dragDrop.previousElementSibling !== container ) {
					dragDrop.parentNode.insertBefore( container, dragDrop );
				}
			}
		}

		function updateAllUploaders(): void {
			const targetSelect = document.getElementById( 'eg_media_target_gallery' ) as HTMLSelectElement | null;
			const targetNewInput = document.getElementById( 'eg_media_new_target_gallery' ) as HTMLInputElement | null;
			if ( ! targetSelect && ! targetNewInput ) {
				return;
			}
			const val = targetSelect ? targetSelect.value : '';
			const newVal = targetNewInput ? targetNewInput.value : '';

			if ( typeof window.wp !== 'undefined' && window.wp.Uploader && window.wp.Uploader.defaults ) {
				window.wp.Uploader.defaults.multipart_params = window.wp.Uploader.defaults.multipart_params || {};
				window.wp.Uploader.defaults.multipart_params.eg_media_target_gallery = val;
				window.wp.Uploader.defaults.multipart_params.eg_media_new_target_gallery = newVal;
			}

			if ( typeof window.uploader !== 'undefined' && window.uploader.settings ) {
				window.uploader.settings.multipart_params = window.uploader.settings.multipart_params || {};
				window.uploader.settings.multipart_params.eg_media_target_gallery = val;
				window.uploader.settings.multipart_params.eg_media_new_target_gallery = newVal;
			}

			if ( typeof window.wp !== 'undefined' && window.wp.media && window.wp.media.uploader && window.wp.media.uploader.uploader ) {
				const wpUp = window.wp.media.uploader.uploader;
				if ( wpUp.settings ) {
					wpUp.settings.multipart_params = wpUp.settings.multipart_params || {};
					wpUp.settings.multipart_params.eg_media_target_gallery = val;
					wpUp.settings.multipart_params.eg_media_new_target_gallery = newVal;
				}
			}
		}

		function bindToGlobalUploader(): void {
			if ( typeof window.uploader !== 'undefined' && window.uploader.bind && ! window.uploader._egMediaBound ) {
				window.uploader.bind( 'BeforeUpload', ( up: any ) => {
					const targetSelect = document.getElementById( 'eg_media_target_gallery' ) as HTMLSelectElement | null;
					const targetNewInput = document.getElementById( 'eg_media_new_target_gallery' ) as HTMLInputElement | null;

					const val = targetSelect ? targetSelect.value : '';
					const newVal = targetNewInput ? targetNewInput.value : '';

					up.settings.multipart_params = up.settings.multipart_params || {};
					up.settings.multipart_params.eg_media_target_gallery = val;
					up.settings.multipart_params.eg_media_new_target_gallery = newVal;

					syncLibraryFilterWithTarget();
				} );

				window.uploader.bind( 'UploadComplete', () => {
					setTimeout( () => {
						refreshMediaLibrary();
						refreshGallerySelectors();
					}, 500 );
				} );

				window.uploader._egMediaBound = true;
			}

			if ( typeof window.wp !== 'undefined' && window.wp.media && window.wp.media.uploader && window.wp.media.uploader.uploader ) {
				const wpUp = window.wp.media.uploader.uploader;
				if ( wpUp.bind && ! wpUp._egMediaBound ) {
					wpUp.bind( 'BeforeUpload', ( up: any ) => {
						const targetSelect = document.getElementById( 'eg_media_target_gallery' ) as HTMLSelectElement | null;
						const targetNewInput = document.getElementById( 'eg_media_new_target_gallery' ) as HTMLInputElement | null;

						const val = targetSelect ? targetSelect.value : '';
						const newVal = targetNewInput ? targetNewInput.value : '';

						up.settings.multipart_params = up.settings.multipart_params || {};
						up.settings.multipart_params.eg_media_target_gallery = val;
						up.settings.multipart_params.eg_media_new_target_gallery = newVal;

						syncLibraryFilterWithTarget();
					} );

					wpUp.bind( 'UploadComplete', () => {
						setTimeout( () => {
							refreshMediaLibrary();
							refreshGallerySelectors();
						}, 500 );
					} );

					wpUp._egMediaBound = true;
				}
			}
		}

		document.addEventListener( 'change', ( event: Event ) => {
			const target = event.target as HTMLElement | null;
			if ( target && target.id === 'eg_media_target_gallery' ) {
				const newGalleryInput = document.getElementById( 'eg_media_new_target_gallery' ) as HTMLInputElement | null;
				if ( newGalleryInput && ( target as HTMLSelectElement ).value !== '' ) {
					newGalleryInput.value = '';
				}
				updateAllUploaders();
				syncLibraryFilterWithTarget();
			}
		} );

		document.addEventListener( 'input', ( event: Event ) => {
			const target = event.target as HTMLElement | null;
			if ( target && target.id === 'eg_media_new_target_gallery' ) {
				const selectField = document.getElementById( 'eg_media_target_gallery' ) as HTMLSelectElement | null;
				if ( selectField && ( target as HTMLInputElement ).value !== '' ) {
					selectField.value = '';
				}
				updateAllUploaders();
				syncLibraryFilterWithTarget();
			}
		} );

		const triggerElements = [ 'dragover', 'mouseenter', 'click' ];
		triggerElements.forEach( ( evtName ) => {
			document.addEventListener(
				evtName,
				() => {
					moveSelectorToTop();
					bindToGlobalUploader();
					updateAllUploaders();
					syncLibraryFilterWithTarget();
				},
				{ passive: true }
			);
		} );

		moveSelectorToTop();
		bindToGlobalUploader();
		updateAllUploaders();

		if ( typeof window.wp !== 'undefined' && window.wp.media && window.wp.media.view && window.wp.media.view.AttachmentFilters ) {
			window.wp.media.view.AttachmentFilters.EGMediaGallery = window.wp.media.view.AttachmentFilters.extend( {
				id: 'media-attachment-eg-media-gallery-filter',
				createFilters() {
					const filters: Record<string, any> = {};

					filters.all = {
						text: 'Toutes les galeries',
						props: {
							eg_media_gallery_filter: '',
						},
						priority: 10,
					};

					filters.orphan = {
						text: '— Sans affectation —',
						props: {
							eg_media_gallery_filter: 'orphan',
						},
						priority: 20,
					};

					if ( window.egMediaUploadData && window.egMediaUploadData.galleries ) {
						window.egMediaUploadData.galleries.forEach( ( gallery ) => {
							filters[ gallery.term_id ] = {
								text: gallery.name,
								props: {
									eg_media_gallery_filter: gallery.term_id,
								},
								priority: 30,
							};
						} );
					}

					this.filters = filters;
				},
			} );

			const OriginalAttachmentsBrowser = window.wp.media.view.AttachmentsBrowser;
			window.wp.media.view.AttachmentsBrowser = OriginalAttachmentsBrowser.extend( {
				createToolbar() {
					OriginalAttachmentsBrowser.prototype.createToolbar.apply( this, arguments );

					if ( window.wp.media.view.Label ) {
						this.toolbar.set(
							'egMediaGalleryFilterLabel',
							new window.wp.media.view.Label( {
								value: 'Filtrer par galerie',
								attributes: {
									for: 'media-attachment-eg-media-gallery-filter',
								},
								priority: -51,
							} ).render()
						);
					}

					if ( this.collection && this.collection.props ) {
						const urlParams = new URLSearchParams( window.location.search );
						let filterVal = urlParams.get( 'eg_media_gallery_filter' );

						if ( ! filterVal && urlParams.has( 'eg_media_gallery' ) ) {
							const slug = urlParams.get( 'eg_media_gallery' );
							if ( window.egMediaUploadData && window.egMediaUploadData.galleries ) {
								const matched = window.egMediaUploadData.galleries.find(
									( g ) => g.slug === slug || String( g.term_id ) === slug
								);
								if ( matched ) {
									filterVal = String( matched.term_id );
								}
							}
						}

						if ( filterVal ) {
							const parsedInt = parseInt( filterVal, 10 );
							const finalVal = ! isNaN( parsedInt ) && String( parsedInt ) === String( filterVal ) ? parsedInt : filterVal;
							this.collection.props.set( 'eg_media_gallery_filter', finalVal );
						} else if ( window.location.pathname.indexOf( 'upload.php' ) !== -1 ) {
							if ( this.collection.props.get( 'eg_media_gallery_filter' ) === undefined ) {
								this.collection.props.set( 'eg_media_gallery_filter', 'orphan' );
							}
						}

						const updateListLink = ( value: any ): void => {
							const listBtn = document.querySelector( '.view-list' );
							if ( listBtn ) {
								const href = listBtn.getAttribute( 'href' ) || 'upload.php?mode=list';
								const parts = href.split( '?' );
								const baseUrl = parts[ 0 ];
								const params = new URLSearchParams( parts[ 1 ] || '' );
								if ( value && value !== 'all' ) {
									params.set( 'eg_media_gallery_filter', value );
								} else {
									params.delete( 'eg_media_gallery_filter' );
								}
								params.set( 'mode', 'list' );
								listBtn.setAttribute( 'href', baseUrl + '?' + params.toString() );
							}
						};

						this.collection.props.on( 'change:eg_media_gallery_filter', ( _model: any, value: any ) => {
							updateListLink( value );

							const targetSelect = document.getElementById( 'eg_media_target_gallery' ) as HTMLSelectElement | null;
							if ( targetSelect ) {
								const targetVal = value === 'orphan' || ! value ? '' : String( value );
								if ( targetSelect.value !== targetVal ) {
									targetSelect.value = targetVal;
									const targetNewInput = document.getElementById( 'eg_media_new_target_gallery' ) as HTMLInputElement | null;
									if ( targetNewInput ) {
										targetNewInput.value = '';
									}
									updateAllUploaders();
								}
							}
						} );

						updateListLink( this.collection.props.get( 'eg_media_gallery_filter' ) );
					}

					this.toolbar.set(
						'egMediaGalleryFilter',
						new window.wp.media.view.AttachmentFilters.EGMediaGallery( {
							controller: this.controller,
							model: this.collection.props,
							priority: -50,
						} ).render()
					);
				},
			} );

			if ( window.wp.media.view.Attachment ) {
				const OriginalAttachment = window.wp.media.view.Attachment;
				window.wp.media.view.Attachment = OriginalAttachment.extend( {
					className() {
						let classes = '';
						if ( typeof OriginalAttachment.prototype.className === 'function' ) {
							classes = OriginalAttachment.prototype.className.apply( this, arguments );
						} else if ( typeof OriginalAttachment.prototype.className === 'string' ) {
							classes = OriginalAttachment.prototype.className;
						}
						if ( this.model.get( 'eg_media_is_reference' ) ) {
							classes += ' eg-media-is-reference';
						}
						return classes;
					},
					render() {
						if ( typeof OriginalAttachment.prototype.render === 'function' ) {
							OriginalAttachment.prototype.render.apply( this, arguments );
						}
						if ( this.model.get( 'eg_media_is_reference' ) ) {
							const galleryName = this.model.get( 'eg_media_reference_gallery_name' ) || '';
							const starHtml = `<div class="eg-media-star-badge" title="Image de référence de la galerie : ${ window._.escape(
								galleryName
							) }">★</div>`;
							this.$el.append( starHtml );
						}
						return this;
					},
				} );

				if ( window.wp.media.view.Attachment.Library ) {
					const OriginalAttachmentLibrary = window.wp.media.view.Attachment.Library;
					window.wp.media.view.Attachment.Library = OriginalAttachmentLibrary.extend( {
						className() {
							let classes = '';
							if ( typeof OriginalAttachmentLibrary.prototype.className === 'function' ) {
								classes = OriginalAttachmentLibrary.prototype.className.apply( this, arguments );
							} else if ( typeof OriginalAttachmentLibrary.prototype.className === 'string' ) {
								classes = OriginalAttachmentLibrary.prototype.className;
							}
							if ( this.model.get( 'eg_media_is_reference' ) ) {
								classes += ' eg-media-is-reference';
							}
							return classes;
						},
						render() {
							if ( typeof OriginalAttachmentLibrary.prototype.render === 'function' ) {
								OriginalAttachmentLibrary.prototype.render.apply( this, arguments );
							}
							if ( this.model.get( 'eg_media_is_reference' ) ) {
								const galleryName = this.model.get( 'eg_media_reference_gallery_name' ) || '';
								const starHtml = `<div class="eg-media-star-badge" title="Image de référence de la galerie : ${ window._.escape(
									galleryName
								) }">★</div>`;
								this.$el.append( starHtml );
							}
							return this;
						},
					} );
				}
			}
		}

		function initBulkActionsListMode(): void {
			const bulkSelectors = document.querySelectorAll<HTMLSelectElement>( 'select[name="action"], select[name="action2"]' );
			if ( bulkSelectors.length === 0 ) {
				return;
			}

			const translate = typeof window.wp !== 'undefined' && window.wp.i18n && window.wp.i18n.__ ? window.wp.i18n.__ : ( text: string ) => text;

			let optionsHtml = '<option value="">' + translate( '— Choisir une galerie —', 'eg-media' ) + '</option>';
			optionsHtml += '<option value="orphan">' + translate( '— Sans affectation —', 'eg-media' ) + '</option>';
			if ( window.egMediaUploadData && window.egMediaUploadData.galleries ) {
				window.egMediaUploadData.galleries.forEach( ( gallery ) => {
					optionsHtml += '<option value="' + gallery.term_id + '">' + gallery.name + '</option>';
				} );
			}

			bulkSelectors.forEach( ( selector ) => {
				const container = document.createElement( 'span' );
				container.className = 'eg-media-bulk-assign-fields';
				container.style.display = 'none';
				container.style.verticalAlign = 'middle';
				container.style.marginLeft = '8px';
				container.style.marginRight = '8px';

				container.innerHTML = `
                    <select name="eg_media_bulk_gallery" class="eg-media-bulk-gallery-select" style="vertical-align: middle; height: 30px;">
                        ${ optionsHtml }
                    </select>
                    <span style="font-size: 13px; color: #646970; margin: 0 4px; vertical-align: middle;">ou</span>
                    <input type="text" 
                           name="eg_media_bulk_new_gallery" 
                           class="eg-media-bulk-new-gallery-input" 
                           placeholder="${ translate( 'Nouvelle galerie...', 'eg-media' ) }" 
                           style="vertical-align: middle; height: 30px; line-height: 30px; font-size: 13px; padding: 0 8px;" />
                `;

				if ( selector.parentNode ) {
					selector.parentNode.insertBefore( container, selector.nextSibling );
				}

				selector.addEventListener( 'change', function ( this: HTMLSelectElement ) {
					if ( this.value === 'eg_media_bulk_assign' ) {
						container.style.display = 'inline-block';
					} else {
						container.style.display = 'none';
					}
				} );
			} );

			const selects = document.querySelectorAll<HTMLSelectElement>( '.eg-media-bulk-gallery-select' );
			const inputs = document.querySelectorAll<HTMLInputElement>( '.eg-media-bulk-new-gallery-input' );

			selects.forEach( ( select ) => {
				select.addEventListener( 'change', function ( this: HTMLSelectElement ) {
					const currentVal = this.value;
					selects.forEach( ( s ) => {
						if ( s !== select ) {
							s.value = currentVal;
						}
					} );
					if ( currentVal !== '' ) {
						inputs.forEach( ( i ) => {
							i.value = '';
						} );
					}
				} );
			} );

			inputs.forEach( ( input ) => {
				input.addEventListener( 'input', function ( this: HTMLInputElement ) {
					const currentVal = this.value;
					inputs.forEach( ( i ) => {
						if ( i !== input ) {
							i.value = currentVal;
						}
					} );
					if ( currentVal !== '' ) {
						selects.forEach( ( s ) => {
							s.value = '';
						} );
					}
				} );
			} );
		}

		function initViewSwitchFilterPreservation(): void {
			const listSelect = document.getElementById( 'eg_media_gallery_filter' ) as HTMLSelectElement | null;
			if ( listSelect ) {
				const updateGridLink = (): void => {
					const val = listSelect.value;
					const gridBtn = document.querySelector( '.view-grid' );
					if ( gridBtn ) {
						const href = gridBtn.getAttribute( 'href' ) || 'upload.php?mode=grid';
						const parts = href.split( '?' );
						const baseUrl = parts[ 0 ];
						const params = new URLSearchParams( parts[ 1 ] || '' );
						if ( val ) {
							params.set( 'eg_media_gallery_filter', val );
						} else {
							params.delete( 'eg_media_gallery_filter' );
						}
						params.set( 'mode', 'grid' );
						gridBtn.setAttribute( 'href', baseUrl + '?' + params.toString() );
					}
				};

				listSelect.addEventListener( 'change', updateGridLink );
				updateGridLink();
			}
		}

		initViewSwitchFilterPreservation();
		initBulkActionsListMode();
	} );
} )();
