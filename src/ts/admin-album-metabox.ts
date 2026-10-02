/**
 * Script d'administration pour la gestion de la composition des albums (Metabox).
 *
 * ES2022 TypeScript en mode strict.
 */

interface AlbumItem {
	type: 'local' | 'piwigo';
	id: number;
	name: string;
}

document.addEventListener( 'DOMContentLoaded', () => {
	const container = document.getElementById( 'eg_media_album_items_container' ) as HTMLElement | null;
	const input = document.getElementById( 'eg_media_album_items_input' ) as HTMLInputElement | null;
	const sortSelect = document.getElementById( 'eg_media_album_sort' ) as HTMLSelectElement | null;
	const sortDesc = document.getElementById( 'eg_media_sort_desc' ) as HTMLElement | null;
	const btnAddLocal = document.getElementById( 'eg_media_btn_add_local' ) as HTMLButtonElement | null;
	const btnAddPiwigo = document.getElementById( 'eg_media_btn_add_piwigo' ) as HTMLButtonElement | null;
	const selectLocal = document.getElementById( 'eg_media_add_local_gallery' ) as HTMLSelectElement | null;
	const selectPiwigo = document.getElementById( 'eg_media_add_piwigo_album' ) as HTMLSelectElement | null;

	if ( ! container || ! input || ! sortSelect || ! sortDesc ) {
		return;
	}

	let items: AlbumItem[] = [];
	try {
		const val = input.value;
		if ( val ) {
			items = JSON.parse( val ) as AlbumItem[];
		}
	} catch ( e ) {
		items = [];
	}

	if ( ! Array.isArray( items ) ) {
		items = [];
	}

	let dragSrcEl: HTMLElement | null = null;

	const handleDragStart = function( this: HTMLElement, e: DragEvent ): void {
		dragSrcEl = this;
		this.classList.add( 'dragging' );
		if ( e.dataTransfer ) {
			e.dataTransfer.effectAllowed = 'move';
			e.dataTransfer.setData( 'text/plain', this.dataset.index || '0' );
		}
	};

	const handleDragOver = ( e: DragEvent ): boolean => {
		if ( e.preventDefault ) {
			e.preventDefault();
		}
		if ( e.dataTransfer ) {
			e.dataTransfer.dropEffect = 'move';
		}
		return false;
	};

	const handleDrop = function( this: HTMLElement, e: DragEvent ): boolean {
		e.stopPropagation();
		e.preventDefault();
		if ( dragSrcEl !== this && e.dataTransfer ) {
			const fromIndex = parseInt( e.dataTransfer.getData( 'text/plain' ), 10 );
			const toIndex = parseInt( this.dataset.index || '0', 10 );

			const temp = items[ fromIndex ];
			items.splice( fromIndex, 1 );
			items.splice( toIndex, 0, temp );

			saveAndRender();
		}
		return false;
	};

	const handleDragEnd = function( this: HTMLElement ): void {
		this.classList.remove( 'dragging' );
		const draggingItems = container.querySelectorAll( '.eg-album-metabox__item, .eg-album-item' );
		draggingItems.forEach( ( el ) => el.classList.remove( 'dragging' ) );
	};

	const renderItems = (): void => {
		container.innerHTML = '';
		if ( items.length === 0 ) {
			container.innerHTML = '<div class="eg-album-metabox__empty">Aucune galerie associée.</div>';
			return;
		}

		items.forEach( ( item: AlbumItem, index: number ) => {
			const div = document.createElement( 'div' );
			div.className = 'eg-album-metabox__item eg-album-item';
			div.setAttribute( 'draggable', sortSelect.value === 'manual' ? 'true' : 'false' );
			div.dataset.index = String( index );

			const contentSpan = document.createElement( 'span' );
			contentSpan.className = 'eg-album-metabox__item-title eg-album-item-title';
			contentSpan.textContent = item.name + ' ';

			const typeSpan = document.createElement( 'span' );
			typeSpan.className = 'eg-album-metabox__item-type eg-album-item-type';
			typeSpan.textContent = item.type === 'local' ? 'Locale' : 'Piwigo';

			const textWrap = document.createElement( 'div' );
			textWrap.appendChild( contentSpan );
			textWrap.appendChild( typeSpan );

			const removeLink = document.createElement( 'button' );
			removeLink.type = 'button';
			removeLink.className = 'eg-album-metabox__item-remove eg-album-item-remove button-link button-link-delete';
			removeLink.textContent = 'Retirer';
			removeLink.addEventListener( 'click', () => {
				items.splice( index, 1 );
				saveAndRender();
			} );

			div.appendChild( textWrap );
			div.appendChild( removeLink );

			if ( sortSelect.value === 'manual' ) {
				div.addEventListener( 'dragstart', handleDragStart );
				div.addEventListener( 'dragover', handleDragOver );
				div.addEventListener( 'drop', handleDrop );
				div.addEventListener( 'dragend', handleDragEnd );
			}

			container.appendChild( div );
		} );
	};

	const saveAndRender = (): void => {
		input.value = JSON.stringify( items );
		renderItems();
	};

	// Rendu initial
	renderItems();

	sortSelect.addEventListener( 'change', () => {
		const val = sortSelect.value;
		if ( val === 'manual' ) {
			sortDesc.textContent = "Faites glisser les éléments pour réorganiser l'ordre d'affichage.";
		} else {
			sortDesc.textContent = "Le tri automatique est activé. L'ordre ci-dessous n'a pas d'influence.";
		}
		renderItems();
	} );

	if ( btnAddLocal && selectLocal ) {
		btnAddLocal.addEventListener( 'click', () => {
			const option = selectLocal.options[ selectLocal.selectedIndex ];
			if ( ! option || ! option.value ) {
				return;
			}

			const id = parseInt( option.value, 10 );
			const name = option.getAttribute( 'data-name' ) || option.textContent || '';

			if ( items.some( ( item ) => item.type === 'local' && item.id === id ) ) {
				alert( "Cette galerie locale est déjà présente dans l'album." );
				return;
			}

			items.push( { type: 'local', id, name } );
			saveAndRender();
			selectLocal.value = '';
		} );
	}

	if ( btnAddPiwigo && selectPiwigo ) {
		btnAddPiwigo.addEventListener( 'click', () => {
			const option = selectPiwigo.options[ selectPiwigo.selectedIndex ];
			if ( ! option || ! option.value ) {
				return;
			}

			const id = parseInt( option.value, 10 );
			const name = option.getAttribute( 'data-name' ) || option.textContent || '';

			if ( items.some( ( item ) => item.type === 'piwigo' && item.id === id ) ) {
				alert( "Cet album Piwigo est déjà présent dans l'album." );
				return;
			}

			items.push( { type: 'piwigo', id, name } );
			saveAndRender();
			selectPiwigo.value = '';
		} );
	}
} );
