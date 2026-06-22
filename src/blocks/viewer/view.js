/**
 * Script front-end pour le bloc Visionneuse de Galerie.
 *
 * ES2021 Vanilla JS.
 */

document.addEventListener( 'DOMContentLoaded', () => {
	const viewers = document.querySelectorAll( '.eg-viewer' );

	viewers.forEach( ( viewer ) => {
		const mainImage = viewer.querySelector( '.eg-viewer__main-image' );
		const track = viewer.querySelector( '.eg-viewer__track' );
		const thumbnailsContainer = viewer.querySelector(
			'.eg-viewer__thumbnails'
		);
		const thumbnails = viewer.querySelectorAll( '.eg-viewer__thumbnail' );

		if (
			! mainImage ||
			! track ||
			! thumbnailsContainer ||
			thumbnails.length === 0
		) {
			return;
		}

		const arrowLeft = viewer.querySelector( '.eg-viewer__arrow--left' );
		const arrowRight = viewer.querySelector( '.eg-viewer__arrow--right' );

		// Vérifier également les flèches si elles existent
		if ( ! arrowLeft || ! arrowRight ) {
			return;
		}

		const isSlideshow = viewer.dataset.slideshow === 'true';
		const tempo = parseInt( viewer.dataset.tempo, 10 ) || 3000;

		let currentIndex = 0;
		let slideshowInterval = null;
		let trackOffset = 0;

		// 1. Calculer la largeur dynamique des miniatures
		const initThumbnailWidths = () => {
			const trackHeight = track.clientHeight || 50; // Hauteur de la piste (10% du conteneur)

			thumbnails.forEach( ( thumb ) => {
				const naturalWidth = parseFloat( thumb.dataset.width ) || 150;
				const naturalHeight = parseFloat( thumb.dataset.height ) || 150;
				const ratio = naturalWidth / naturalHeight;
				const calculatedWidth = trackHeight * ratio;

				thumb.style.width = `${ calculatedWidth }px`;
				thumb.style.height = `${ trackHeight }px`;
			} );
		};

		// Initialisation et adaptation lors du redimensionnement
		initThumbnailWidths();
		window.addEventListener( 'resize', () => {
			initThumbnailWidths();
			updateTrackPosition();
		} );

		// 2. Mettre à jour l'image active et la classe active
		const setActiveImage = ( index ) => {
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
			const newSrc = activeThumb.dataset.fullSrc;
			const imgEl = activeThumb.querySelector( 'img' );
			const newAlt = imgEl ? imgEl.alt : '';

			const mainContainer = viewer.querySelector( '.eg-viewer__main' );
			if ( ! mainContainer ) {
				return;
			}

			const currentImg = mainContainer.querySelector( '.eg-viewer__main-image' );
			if ( currentImg ) {
				currentImg.style.opacity = '0';
			}

			setTimeout( () => {
				// Détruire physiquement l'ancienne image pour forcer Safari à effacer son cache matériel
				if ( currentImg ) {
					currentImg.remove();
				}

				// Nettoyer d'éventuelles images dupliquées résiduelles
				const extraImages = mainContainer.querySelectorAll( 'img' );
				extraImages.forEach( ( img ) => {
					img.remove();
				} );

				// Créer un nouvel élément img vierge
				const newImg = document.createElement( 'img' );
				newImg.className = 'eg-viewer__main-image';
				newImg.style.opacity = '0';
				newImg.style.cursor = document.fullscreenElement === viewer || document.webkitFullscreenElement === viewer ? 'zoom-out' : 'zoom-in';
				
				// Ré-attacher l'écouteur de clic pour le plein écran
				newImg.addEventListener( 'click', toggleFullscreen );

				// Précharger et afficher l'image propre
				const tempImg = new Image();
				tempImg.onload = () => {
					newImg.src = newSrc;
					newImg.alt = newAlt;
					mainContainer.appendChild( newImg );
					// Forcer un reflow pour déclencher l'animation d'opacité
					newImg.offsetHeight;
					newImg.style.opacity = '1';
				};
				tempImg.src = newSrc;
			}, 150 );

			// Mettre à jour les classes actives
			thumbnails.forEach( ( thumb, i ) => {
				if ( i === currentIndex ) {
					thumb.classList.add( 'eg-viewer__thumbnail--active' );
				} else {
					thumb.classList.remove( 'eg-viewer__thumbnail--active' );
				}
			} );

			updateTrackPosition();
		};

		// 3. Déplacement de la piste des miniatures
		const updateTrackPosition = () => {
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

		// Décaler la piste au clic sur une flèche
		const shiftTrack = ( direction ) => {
			if ( direction === 'next' ) {
				setActiveImage( currentIndex + 1 );
			} else {
				setActiveImage( currentIndex - 1 );
			}
		};

		// Événements boutons
		arrowLeft.addEventListener( 'click', () => {
			shiftTrack( 'prev' );
		} );

		arrowRight.addEventListener( 'click', () => {
			shiftTrack( 'next' );
		} );

		// Clic direct sur une miniature
		thumbnails.forEach( ( thumb, index ) => {
			thumb.addEventListener( 'click', () => {
				setActiveImage( index );
			} );
		} );

		// 4. Gestion du diaporama
		const startSlideshow = () => {
			if ( ! isSlideshow || slideshowInterval ) {
				return;
			}
			slideshowInterval = setInterval( () => {
				setActiveImage( currentIndex + 1 );
			}, tempo );
		};

		const stopSlideshow = () => {
			if ( slideshowInterval ) {
				clearInterval( slideshowInterval );
				slideshowInterval = null;
			}
		};

		// 5. Gestion du plein écran (HTML5 Fullscreen API)
		const closeBtn = viewer.querySelector( '.eg-viewer__close' );

		const toggleFullscreen = () => {
			if ( ! document.fullscreenElement && ! document.webkitFullscreenElement ) {
				if ( viewer.requestFullscreen ) {
					viewer.requestFullscreen();
				} else if ( viewer.webkitRequestFullscreen ) {
					viewer.webkitRequestFullscreen();
				} else if ( viewer.msRequestFullscreen ) {
					viewer.msRequestFullscreen();
				}
			} else {
				if ( document.exitFullscreen ) {
					document.exitFullscreen();
				} else if ( document.webkitExitFullscreen ) {
					document.webkitExitFullscreen();
				} else if ( document.msExitFullscreen ) {
					document.msExitFullscreen();
				}
			}
		};

		if ( mainImage ) {
			mainImage.addEventListener( 'click', toggleFullscreen );
			mainImage.style.cursor = 'zoom-in';
		}

		if ( closeBtn ) {
			closeBtn.addEventListener( 'click', ( e ) => {
				e.stopPropagation();
				if ( document.fullscreenElement || document.webkitFullscreenElement ) {
					if ( document.exitFullscreen ) {
						document.exitFullscreen();
					} else if ( document.webkitExitFullscreen ) {
						document.webkitExitFullscreen();
					}
				}
			} );
		}

		const handleFullscreenChange = () => {
			const isFull = document.fullscreenElement === viewer || document.webkitFullscreenElement === viewer;
			const currentImg = viewer.querySelector( '.eg-viewer__main-image' );
			if ( isFull ) {
				viewer.classList.add( 'eg-viewer--fullscreen' );
				if ( currentImg ) {
					currentImg.style.cursor = 'zoom-out';
				}
				// Forcer le redémarrage du diaporama en plein écran (la souris survole forcément l'écran)
				if ( isSlideshow ) {
					startSlideshow();
				}
			} else {
				viewer.classList.remove( 'eg-viewer--fullscreen' );
				if ( currentImg ) {
					currentImg.style.cursor = 'zoom-in';
				}
				// Si on quitte le plein écran, vérifier si la souris survole toujours la visionneuse
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

		document.addEventListener( 'fullscreenchange', handleFullscreenChange );
		document.addEventListener( 'webkitfullscreenchange', handleFullscreenChange );

		if ( isSlideshow ) {
			startSlideshow();

			// Pause au survol uniquement hors plein écran
			viewer.addEventListener( 'mouseenter', () => {
				const isFull = document.fullscreenElement === viewer || document.webkitFullscreenElement === viewer;
				if ( ! isFull ) {
					stopSlideshow();
				}
			} );
			viewer.addEventListener( 'mouseleave', () => {
				const isFull = document.fullscreenElement === viewer || document.webkitFullscreenElement === viewer;
				if ( ! isFull ) {
					startSlideshow();
				}
			} );
		}
	} );
} );
