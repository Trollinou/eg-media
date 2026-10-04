/**
 * Script front-end Interactivity API pour le bloc Visionneuse de Galerie (WordPress 7.1).
 *
 * ES2022+ TypeScript en mode strict avec @wordpress/interactivity store.
 */

import { store, getContext, getElement } from '@wordpress/interactivity';
import type { ViewerContext } from '../../types/interactivity';
import type { GalleryImage } from '../../types/gallery';

let slideshowTimer: ReturnType<typeof setInterval> | null = null;
let wheelTimer: ReturnType<typeof setTimeout> | null = null;

store( 'eg-media/viewer', {
	state: {
		/**
		 * Source de l'image actuellement sélectionnée.
		 */
		get currentImageSrc(): string {
			const ctx = getContext<ViewerContext>();
			const img = ctx.images[ ctx.currentIndex ];
			return img ? img.fullSrc : '';
		},

		/**
		 * Texte alternatif de l'image active.
		 */
		get currentImageAlt(): string {
			const ctx = getContext<ViewerContext>();
			const img = ctx.images[ ctx.currentIndex ];
			return img ? img.alt : '';
		},

		/**
		 * Vérifie si la miniature courante est active.
		 */
		get isThumbnailActive(): boolean {
			const ctx = getContext<ViewerContext>();
			const { attributes } = getElement();
			const index = parseInt( attributes[ 'data-index' ] || '-1', 10 );
			return index === ctx.currentIndex;
		},

		/**
		 * Indique si toutes les images de la grille justifiée sont chargées.
		 */
		get hasLoadedAllImages(): boolean {
			const ctx = getContext<ViewerContext>();
			return ctx.loadedImagesCount >= ctx.images.length;
		},
	},

	actions: {
		/**
		 * Sélectionne une image spécifique par index.
		 */
		selectImage( event?: MouseEvent ): void {
			const ctx = getContext<ViewerContext>();
			const { attributes } = getElement();
			const targetIndex = event
				? parseInt( ( event.currentTarget as HTMLElement )?.dataset?.index || attributes[ 'data-index' ] || '0', 10 )
				: parseInt( attributes[ 'data-index' ] || '0', 10 );

			if ( targetIndex >= 0 && targetIndex < ctx.images.length ) {
				ctx.currentIndex = targetIndex;
			}
		},

		/**
		 * Clic sur une miniature de la grille justifiée : active l'image et ouvre le plein écran.
		 */
		selectJustifiedImage( event: MouseEvent ): void {
			const ctx = getContext<ViewerContext>();
			const target = event.target as HTMLElement | null;
			const item = target?.closest<HTMLElement>( '.eg-viewer__justified-item' );
			if ( item ) {
				const index = parseInt( item.dataset.index || '0', 10 );
				if ( index >= 0 && index < ctx.images.length ) {
					ctx.currentIndex = index;
					const { ref } = getElement();
					const viewer = ref.closest<HTMLElement>( '.eg-viewer' ) || ref;
					if ( ! document.fullscreenElement && viewer.requestFullscreen ) {
						viewer.requestFullscreen();
					}
				}
			}
		},

		/**
		 * Passe à l'image suivante (boucle circulaire).
		 */
		nextImage(): void {
			const ctx = getContext<ViewerContext>();
			if ( ctx.images.length === 0 ) {
				return;
			}
			ctx.currentIndex = ( ctx.currentIndex + 1 ) % ctx.images.length;
		},

		/**
		 * Revient à l'image précédente (boucle circulaire).
		 */
		prevImage(): void {
			const ctx = getContext<ViewerContext>();
			if ( ctx.images.length === 0 ) {
				return;
			}
			ctx.currentIndex = ( ctx.currentIndex - 1 + ctx.images.length ) % ctx.images.length;
		},

		/**
		 * Bascule le mode plein écran natif (Fullscreen API).
		 */
		toggleFullscreen(): void {
			const { ref } = getElement();
			const viewer = ref.closest<HTMLElement>( '.eg-viewer' ) || ref;

			if ( ! document.fullscreenElement ) {
				if ( viewer.requestFullscreen ) {
					viewer.requestFullscreen();
				}
			} else if ( document.exitFullscreen ) {
				document.exitFullscreen();
			}
		},

		/**
		 * Ferme le mode plein écran.
		 */
		closeFullscreen( event: MouseEvent ): void {
			event.stopPropagation();
			if ( document.fullscreenElement && document.exitFullscreen ) {
				document.exitFullscreen();
			}
		},

		/**
		 * Synchronise l'état plein écran réactif avec l'événement document.fullscreenchange.
		 */
		handleFullscreenChange(): void {
			const ctx = getContext<ViewerContext>();
			const { ref } = getElement();
			const viewer = ref.closest<HTMLElement>( '.eg-viewer' ) || ref;
			ctx.isFullscreen = document.fullscreenElement === viewer;
		},

		/**
		 * Gestion des touches du clavier (Flèche gauche / Flèche droite / Echap).
		 */
		handleKeyDown( event: KeyboardEvent ): void {
			const ctx = getContext<ViewerContext>();
			const { ref } = getElement();
			const viewer = ref.closest<HTMLElement>( '.eg-viewer' ) || ref;

			const isFull = document.fullscreenElement === viewer;
			const isViewerMode = ctx.layout === 'viewer';

			if ( ! isViewerMode && ! isFull ) {
				return;
			}

			if ( document.fullscreenElement && document.fullscreenElement !== viewer ) {
				return;
			}

			if ( event.key === 'ArrowRight' ) {
				event.preventDefault();
				ctx.currentIndex = ( ctx.currentIndex + 1 ) % ctx.images.length;
			} else if ( event.key === 'ArrowLeft' ) {
				event.preventDefault();
				ctx.currentIndex = ( ctx.currentIndex - 1 + ctx.images.length ) % ctx.images.length;
			}
		},

		/**
		 * Charge un lot supplémentaire d'images dans la grille justifiée.
		 */
		loadMore(): void {
			const ctx = getContext<ViewerContext>();
			const nextCount = Math.min( ctx.images.length, ctx.loadedImagesCount + ctx.limit );
			
			const { ref } = getElement();
			const viewer = ref.closest<HTMLElement>( '.eg-viewer' ) || ref;
			const grid = viewer.querySelector<HTMLElement>( '.eg-viewer__justified-grid' );

			if ( grid ) {
				const nextBatch = ctx.images.slice( ctx.loadedImagesCount, nextCount );
				nextBatch.forEach( ( img: GalleryImage ) => {
					const aspect = img.width / ( img.height || 150 );
					const flexBasis = aspect * 150;
					const itemDiv = document.createElement( 'div' );
					itemDiv.className = 'eg-viewer__justified-item';
					itemDiv.dataset.index = String( img.index );
					itemDiv.style.flexGrow = String( aspect );
					itemDiv.style.flexBasis = `${ flexBasis }px`;

					const imgEl = document.createElement( 'img' );
					imgEl.src = img.thumbSrc;
					imgEl.alt = img.alt;
					imgEl.loading = 'lazy';

					itemDiv.appendChild( imgEl );
					grid.appendChild( itemDiv );
				} );
			}

			ctx.loadedImagesCount = nextCount;
		},

		/**
		 * Défilement horizontal de la piste de miniatures à la molette.
		 */
		onWheel( event: WheelEvent ): void {
			event.preventDefault();
			const { ref } = getElement();
			const track = ref.querySelector<HTMLElement>( '.eg-viewer__track' );
			if ( ! track ) {
				return;
			}

			track.style.transition = 'none';

			if ( wheelTimer ) {
				clearTimeout( wheelTimer );
			}

			const viewportWidth = ref.clientWidth;
			const maxScroll = -( track.scrollWidth - viewportWidth );
			const delta = event.deltaY || event.deltaX;

			const currentTransform = track.style.transform;
			const match = currentTransform.match( /translateX\(([-\d.]+)px\)/ );
			let offset = match ? parseFloat( match[ 1 ] ) : 0;

			offset -= delta * 1.2;

			if ( offset > 0 ) {
				offset = 0;
			} else if ( offset < maxScroll && maxScroll < 0 ) {
				offset = maxScroll;
			}

			track.style.transform = `translateX(${ offset }px)`;

			wheelTimer = setTimeout( () => {
				track.style.transition = '';
			}, 150 );
		},
	},

	callbacks: {
		/**
		 * Initialise les dimensions des miniatures, le diaporama automatique et le suivi réactif.
		 */
		init(): void {
			const ctx = getContext<ViewerContext>();
			const { ref } = getElement();
			const viewer = ref.closest<HTMLElement>( '.eg-viewer' ) || ref;

			const track = viewer.querySelector<HTMLElement>( '.eg-viewer__track' );
			const thumbnailsContainer = viewer.querySelector<HTMLElement>( '.eg-viewer__thumbnails' );
			const thumbnails = viewer.querySelectorAll<HTMLElement>( '.eg-viewer__thumbnail' );

			if ( track && thumbnails.length > 0 ) {
				const trackHeight = track.clientHeight || 50;
				thumbnails.forEach( ( thumb: HTMLElement ) => {
					const naturalWidth = parseFloat( thumb.dataset.width || '150' ) || 150;
					const naturalHeight = parseFloat( thumb.dataset.height || '150' ) || 150;
					const ratio = naturalWidth / naturalHeight;
					thumb.style.width = `${ trackHeight * ratio }px`;
					thumb.style.height = `${ trackHeight }px`;
				} );

				// Centrage de la miniature active dans la piste
				if ( thumbnailsContainer && thumbnails[ ctx.currentIndex ] ) {
					const activeThumb = thumbnails[ ctx.currentIndex ];
					const viewportWidth = thumbnailsContainer.clientWidth;
					let trackOffset = -( activeThumb.offsetLeft - viewportWidth / 2 + activeThumb.offsetWidth / 2 );
					const maxScroll = -( track.scrollWidth - viewportWidth );
					if ( trackOffset > 0 ) {
						trackOffset = 0;
					} else if ( trackOffset < maxScroll && maxScroll < 0 ) {
						trackOffset = maxScroll;
					}
					track.style.transform = `translateX(${ trackOffset }px)`;
				}
			}

			// Gestion du diaporama
			if ( ctx.slideshow && ctx.layout !== 'justified' ) {
				if ( slideshowTimer ) {
					clearInterval( slideshowTimer );
				}
				slideshowTimer = setInterval( () => {
					if ( ! ctx.isFullscreen && viewer.matches( ':hover' ) ) {
						return; // Pause sur hover hors plein écran
					}
					ctx.currentIndex = ( ctx.currentIndex + 1 ) % ( ctx.images.length || 1 );
				}, ctx.tempo || 3000 );
			}
		},
	},
} );
