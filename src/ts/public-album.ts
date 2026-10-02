/**
 * Script front-end pour la fonctionnalité d'Albums de EG Media Manager.
 *
 * ES2022 TypeScript en mode strict.
 */

document.addEventListener( 'DOMContentLoaded', () => {
	const cards = document.querySelectorAll<HTMLElement>( '.eg-album__card' );

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
			overlay.classList.add( 'is-active' );
			overlay.style.display = 'flex';
			document.body.style.overflow = 'hidden';

			// Déclencher le recalcul de la grille justified si nécessaire
			window.dispatchEvent( new Event( 'resize' ) );
		} );
	} );

	// Écouter les évènements de fermeture sur tous les overlays d'albums
	const overlays = document.querySelectorAll<HTMLElement>( '.eg-album__overlay' );

	overlays.forEach( ( overlay: HTMLElement ) => {
		const closeBtn = overlay.querySelector<HTMLElement>( '.eg-album__overlay-close' );

		const closeModal = (): void => {
			overlay.classList.remove( 'is-active' );
			// Attendre la fin de la transition d'opacité avant de masquer
			setTimeout( () => {
				overlay.style.display = 'none';
			}, 350 );
			document.body.style.overflow = '';
		};

		if ( closeBtn ) {
			closeBtn.addEventListener( 'click', ( e: MouseEvent ) => {
				e.stopPropagation();
				closeModal();
			} );
		}

		// Fermer au clic en dehors du contenu
		overlay.addEventListener( 'click', ( e: MouseEvent ) => {
			if ( e.target === overlay ) {
				closeModal();
			}
		} );

		// Échappe pour fermer
		document.addEventListener( 'keydown', ( e: KeyboardEvent ) => {
			if ( e.key === 'Escape' && overlay.classList.contains( 'is-active' ) ) {
				closeModal();
			}
		} );
	} );
} );
