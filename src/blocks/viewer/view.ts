/**
 * Script front-end TypeScript pour le bloc Visionneuse de Galerie.
 *
 * ES2022+ TypeScript en mode strict.
 */

import type { GalleryImage } from '../../types/gallery';
import type { ViewerLayout } from '../../types/viewer';

document.addEventListener( 'DOMContentLoaded', () => {
	const viewers = document.querySelectorAll<HTMLElement>( '.eg-viewer' );

	viewers.forEach( ( viewer: HTMLElement ) => {
		const mainImage = viewer.querySelector<HTMLElement>( '.eg-viewer__main-image' );
		const track = viewer.querySelector<HTMLElement>( '.eg-viewer__track' );
		const thumbnailsContainer = viewer.querySelector<HTMLElement>(
			'.eg-viewer__thumbnails'
		);
		const thumbnails = viewer.querySelectorAll<HTMLElement>( '.eg-viewer__thumbnail' );

		if (
			! mainImage ||
			! track ||
			! thumbnailsContainer ||
			thumbnails.length === 0
		) {
			return;
		}

		const arrowLeft = viewer.querySelector<HTMLElement>( '.eg-viewer__arrow--left' );
		const arrowRight = viewer.querySelector<HTMLElement>( '.eg-viewer__arrow--right' );

		if ( ! arrowLeft || ! arrowRight ) {
			return;
		}

		const layout = ( viewer.dataset.layout || 'viewer' ) as ViewerLayout;
		const isSlideshow =
			viewer.dataset.slideshow === 'true' && layout !== 'justified';
		const tempo = parseInt( viewer.dataset.tempo || '3000', 10 ) || 3000;

		let currentIndex = 0;
		let slideshowInterval: ReturnType<typeof setInterval> | null = null;
		let trackOffset = 0;

		// 1. Calculer la largeur dynamique des miniatures
		const initThumbnailWidths = (): void => {
			const trackHeight = track.clientHeight || 50; // Hauteur de la piste (10% du conteneur)

			thumbnails.forEach( ( thumb: HTMLElement ) => {
				const naturalWidth = parseFloat( thumb.dataset.width || '150' ) || 150;
				const naturalHeight = parseFloat( thumb.dataset.height || '150' ) || 150;
				const ratio = naturalWidth / naturalHeight;
				const calculatedWidth = trackHeight * ratio;

				thumb.style.width = `${ calculatedWidth }px`;
				thumb.style.height = `${ trackHeight }px`;
			} );
		};

		// 2. Mettre à jour l'image active et la classe active
		const setActiveImage = ( index: number ): void => {
			// S'assurer que l'index reste dans les limites (boucle circulaire)
			if ( index < 0 ) {
				currentIndex = thumbnails.length - 1;
			} else if ( index >= thumbnails.length ) {
				currentIndex = 0;
			} else {
				currentIndex = index;
			}

			// Changer la source et l'alt de l'image principale avec un effet de fondu
			const activeThumb = thumbnails[ currentIndex ];
			if ( ! activeThumb ) {
				return;
			}

			const newSrc = activeThumb.dataset.fullSrc || '';
			const imgEl = activeThumb.querySelector<HTMLImageElement>( 'img' );
			const newAlt = imgEl ? imgEl.alt : '';

			const mainContainer = viewer.querySelector<HTMLElement>( '.eg-viewer__main' );
			if ( ! mainContainer ) {
				return;
			}

			const currentImg = mainContainer.querySelector<HTMLImageElement>(
				'.eg-viewer__main-image'
			);
			if ( currentImg ) {
				currentImg.style.opacity = '0';
			}

			setTimeout( () => {
				// Détruire physiquement l'ancienne image pour forcer Safari à effacer son cache matériel
				if ( currentImg ) {
					currentImg.remove();
				}

				// Nettoyer d'éventuelles images dupliquées résiduelles
				const extraImages = mainContainer.querySelectorAll<HTMLImageElement>( 'img' );
				extraImages.forEach( ( img: HTMLImageElement ) => {
					img.remove();
				} );

				// Créer un nouvel élément img vierge
				const newImg = document.createElement( 'img' );
				newImg.className = 'eg-viewer__main-image';
				newImg.style.opacity = '0';
				newImg.style.cursor =
					document.fullscreenElement === viewer
						? 'zoom-out'
						: 'zoom-in';

				// Ré-attacher l'écouteur de clic pour le plein écran
				newImg.addEventListener( 'click', toggleFullscreen );

				// Précharger et afficher l'image propre
				const tempImg = new window.Image();
				tempImg.onload = () => {
					newImg.src = newSrc;
					newImg.alt = newAlt;
					mainContainer.appendChild( newImg );
					// Forcer un reflow pour déclencher l'animation d'opacité
					/* eslint-disable-next-line @typescript-eslint/no-unused-expressions */
					newImg.offsetHeight;
					newImg.style.opacity = '1';
				};
				tempImg.src = newSrc;
			}, 150 );

			// Mettre à jour les classes actives
			thumbnails.forEach( ( thumb: HTMLElement, i: number ) => {
				if ( i === currentIndex ) {
					thumb.classList.add( 'eg-viewer__thumbnail--active' );
				} else {
					thumb.classList.remove( 'eg-viewer__thumbnail--active' );
				}
			} );

			updateTrackPosition();
		};

		// 3. Déplacement de la piste des miniatures
		const updateTrackPosition = (): void => {
			const viewportWidth = thumbnailsContainer.clientWidth;
			const activeThumb = thumbnails[ currentIndex ];
			if ( ! activeThumb ) {
				return;
			}

			const activeThumbWidth = activeThumb.offsetWidth;
			const activeThumbOffset = activeThumb.offsetLeft;

			// Calculer le décalage pour centrer la miniature active dans le conteneur
			trackOffset = -(
				activeThumbOffset -
				viewportWidth / 2 +
				activeThumbWidth / 2
			);

			// Limiter le décalage pour ne pas scroller dans le vide
			const maxScroll = -( track.scrollWidth - viewportWidth );
			if ( trackOffset > 0 ) {
				trackOffset = 0;
			} else if ( trackOffset < maxScroll && maxScroll < 0 ) {
				trackOffset = maxScroll;
			}

			track.style.transform = `translateX(${ trackOffset }px)`;
		};

		// Décaler la piste au clic sur une flèche sans changer l'image active
		const shiftTrack = ( direction: 'prev' | 'next' ): void => {
			const viewportWidth = thumbnailsContainer.clientWidth;
			const scrollAmount = viewportWidth * 0.6; // Défilement de 60% de la largeur visible
			const maxScroll = -( track.scrollWidth - viewportWidth );

			if ( direction === 'next' ) {
				trackOffset -= scrollAmount;
			} else {
				trackOffset += scrollAmount;
			}

			// Limiter le décalage pour ne pas scroller dans le vide
			if ( trackOffset > 0 ) {
				trackOffset = 0;
			} else if ( trackOffset < maxScroll && maxScroll < 0 ) {
				trackOffset = maxScroll;
			}

			track.style.transform = `translateX(${ trackOffset }px)`;
		};

		// Événements boutons
		arrowLeft.addEventListener( 'click', () => {
			shiftTrack( 'prev' );
		} );

		arrowRight.addEventListener( 'click', () => {
			shiftTrack( 'next' );
		} );

		// Clic direct sur une miniature
		thumbnails.forEach( ( thumb: HTMLElement, index: number ) => {
			thumb.addEventListener( 'click', () => {
				setActiveImage( index );
			} );
		} );

		// Défilement de la piste des miniatures à la roulette sans changer l'image active
		let wheelTimeout: ReturnType<typeof setTimeout> | null = null;
		thumbnailsContainer.addEventListener(
			'wheel',
			( e: WheelEvent ) => {
				e.preventDefault();

				// Désactiver la transition CSS pour un défilement immédiat et sans tremblement (surtout au trackpad)
				track.style.transition = 'none';

				if ( wheelTimeout ) {
					clearTimeout( wheelTimeout );
				}

				const viewportWidth = thumbnailsContainer.clientWidth;
				const maxScroll = -( track.scrollWidth - viewportWidth );
				const delta = e.deltaY || e.deltaX;

				// Ajuster la vitesse/sensibilité
				trackOffset -= delta * 1.2;

				if ( trackOffset > 0 ) {
					trackOffset = 0;
				} else if ( trackOffset < maxScroll && maxScroll < 0 ) {
					trackOffset = maxScroll;
				} else if ( maxScroll >= 0 ) {
					trackOffset = 0;
				}

				track.style.transform = `translateX(${ trackOffset }px)`;

				// Rétablir la transition CSS par défaut après la fin du défilement
				wheelTimeout = setTimeout( () => {
					track.style.transition = '';
				}, 150 );
			},
			{ passive: false }
		);

		// 3.5 Gestion de la grille justifiée
		if ( layout === 'justified' ) {
			const limit = parseInt( viewer.dataset.limit || '30', 10 ) || 30;
			const imagesJsonStr = viewer.dataset.imagesJson;
			let imagesData: GalleryImage[] = [];
			try {
				if ( imagesJsonStr ) {
					imagesData = JSON.parse( imagesJsonStr ) as GalleryImage[];
				}
			} catch ( e ) {
				// eslint-disable-next-line no-console
				console.error( 'Erreur lors du parsing JSON des images :', e );
			}

			let currentLoadedCount = Math.min( imagesData.length, limit );

			const justifiedGrid = viewer.querySelector<HTMLElement>(
				'.eg-viewer__justified-grid'
			);
			if ( justifiedGrid ) {
				justifiedGrid.addEventListener( 'click', ( e: MouseEvent ) => {
					const target = e.target as HTMLElement | null;
					const item = target?.closest<HTMLElement>(
						'.eg-viewer__justified-item'
					);
					if ( item ) {
						const index = parseInt( item.dataset.index || '0', 10 );
						setActiveImage( index );
						toggleFullscreen();
					}
				} );
			}

			const loadMoreBtn = viewer.querySelector<HTMLButtonElement>(
				'.eg-viewer__load-more-btn'
			);
			if ( loadMoreBtn && justifiedGrid ) {
				loadMoreBtn.addEventListener( 'click', () => {
					const nextBatch = imagesData.slice(
						currentLoadedCount,
						currentLoadedCount + limit
					);
					nextBatch.forEach( ( img: GalleryImage ) => {
						const aspect = img.width / img.height;
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
						justifiedGrid.appendChild( itemDiv );
					} );

					currentLoadedCount += nextBatch.length;

					if ( currentLoadedCount >= imagesData.length ) {
						const container = viewer.querySelector<HTMLElement>(
							'.eg-viewer__load-more-container'
						);
						if ( container ) {
							container.style.display = 'none';
						}
					}
				} );
			}
		}

		// 4. Gestion du diaporama
		const startSlideshow = (): void => {
			if ( ! isSlideshow || slideshowInterval ) {
				return;
			}
			slideshowInterval = setInterval( () => {
				setActiveImage( currentIndex + 1 );
			}, tempo );
		};

		const stopSlideshow = (): void => {
			if ( slideshowInterval ) {
				clearInterval( slideshowInterval );
				slideshowInterval = null;
			}
		};

		// 5. Gestion du plein écran (HTML5 Fullscreen API)
		const closeBtn = viewer.querySelector<HTMLElement>( '.eg-viewer__close' );

		const toggleFullscreen = (): void => {
			if ( ! document.fullscreenElement ) {
				if ( viewer.requestFullscreen ) {
					viewer.requestFullscreen();
				}
			} else if ( document.exitFullscreen ) {
				document.exitFullscreen();
			}
		};

		if ( mainImage ) {
			mainImage.addEventListener( 'click', toggleFullscreen );
			mainImage.style.cursor = 'zoom-in';
		}

		if ( closeBtn ) {
			closeBtn.addEventListener( 'click', ( e: MouseEvent ) => {
				e.stopPropagation();
				if ( document.fullscreenElement ) {
					if ( document.exitFullscreen ) {
						document.exitFullscreen();
					}
				}
			} );
		}

		const handleFullscreenChange = (): void => {
			const isFull = document.fullscreenElement === viewer;
			const currentImg = viewer.querySelector<HTMLImageElement>( '.eg-viewer__main-image' );
			if ( isFull ) {
				viewer.classList.add( 'eg-viewer--fullscreen' );
				if ( currentImg ) {
					currentImg.style.cursor = 'zoom-out';
				}
				if ( isSlideshow ) {
					startSlideshow();
				}
			} else {
				viewer.classList.remove( 'eg-viewer--fullscreen' );
				if ( currentImg ) {
					currentImg.style.cursor = 'zoom-in';
				}
				if ( isSlideshow ) {
					if ( viewer.matches( ':hover' ) ) {
						stopSlideshow();
					} else {
						startSlideshow();
					}
				}
			}
			setTimeout( () => {
				initThumbnailWidths();
				updateTrackPosition();
			}, 100 );
		};

		const handleKeyDown = ( e: KeyboardEvent ): void => {
			const isFull = document.fullscreenElement === viewer;
			const isViewerMode = layout === 'viewer';

			if ( ! isViewerMode && ! isFull ) {
				return;
			}

			if (
				document.fullscreenElement &&
				document.fullscreenElement !== viewer
			) {
				return;
			}

			if ( e.key === 'ArrowRight' ) {
				e.preventDefault();
				setActiveImage( currentIndex + 1 );
			} else if ( e.key === 'ArrowLeft' ) {
				e.preventDefault();
				setActiveImage( currentIndex - 1 );
			}
		};

		document.addEventListener( 'keydown', handleKeyDown );
		document.addEventListener( 'fullscreenchange', handleFullscreenChange );

		if ( isSlideshow ) {
			startSlideshow();

			viewer.addEventListener( 'mouseenter', () => {
				const isFull = document.fullscreenElement === viewer;
				if ( ! isFull ) {
					stopSlideshow();
				}
			} );
			viewer.addEventListener( 'mouseleave', () => {
				const isFull = document.fullscreenElement === viewer;
				if ( ! isFull ) {
					startSlideshow();
				}
			} );
		}

		// Initialisation et adaptation au redimensionnement
		initThumbnailWidths();
		window.addEventListener( 'resize', () => {
			initThumbnailWidths();
			updateTrackPosition();
		} );
	} );
} );
