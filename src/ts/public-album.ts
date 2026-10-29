/**
 * Script front-end pour la fonctionnalité d'Albums de EG Media Manager.
 *
 * ES2022 TypeScript en mode strict.
 */

document.addEventListener( 'DOMContentLoaded', () => {
	const cards = document.querySelectorAll<HTMLElement>( '.eg-album__card' );

	const closeOverlay = ( overlay: HTMLElement ): void => {
		overlay.classList.remove( 'is-active' );
		setTimeout( () => {
			if ( ! overlay.classList.contains( 'is-active' ) ) {
				overlay.style.display = 'none';
			}
		}, 350 );
	};

	cards.forEach( ( card: HTMLElement ) => {
		card.addEventListener( 'click', () => {
			const targetId = card.dataset.targetViewer;
			if ( ! targetId ) {
				return;
			}

			const overlay = document.getElementById( `eg-viewer-overlay-${ targetId }` );
			if ( ! overlay ) {
				return;
			}

			// Ouvrir le modal
			overlay.style.display = 'flex';
			requestAnimationFrame( () => {
				overlay.classList.add( 'is-active' );
			} );

			// Déclencher le recalcul de la grille justified si nécessaire
			window.dispatchEvent( new Event( 'resize' ) );
		} );
	} );

	// Écouter les évènements de fermeture sur tous les overlays d'albums
	const overlays = document.querySelectorAll<HTMLElement>( '.eg-album__overlay' );

	overlays.forEach( ( overlay: HTMLElement ) => {
		const closeBtn = overlay.querySelector<HTMLElement>( '.eg-album__overlay-close' );

		if ( closeBtn ) {
			closeBtn.addEventListener( 'click', ( e: MouseEvent ) => {
				e.stopPropagation();
				closeOverlay( overlay );
			} );
		}

		// Fermer au clic en dehors du contenu
		overlay.addEventListener( 'click', ( e: MouseEvent ) => {
			if ( e.target === overlay ) {
				closeOverlay( overlay );
			}
		} );
	} );

	// Écouteur global unique sur Escape pour fermer l'overlay actif
	if ( overlays.length > 0 ) {
		document.addEventListener( 'keydown', ( e: KeyboardEvent ) => {
			if ( e.key === 'Escape' && ! document.fullscreenElement ) {
				const activeOverlay = document.querySelector<HTMLElement>( '.eg-album__overlay.is-active' );
				if ( activeOverlay ) {
					closeOverlay( activeOverlay );
				}
			}
		} );
	}
} );
