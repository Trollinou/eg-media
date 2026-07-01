/**
 * Script front-end pour la fonctionnalité d'Albums de EG Media Manager.
 *
 * ES2021 Vanilla JS.
 */

document.addEventListener( 'DOMContentLoaded', () => {
	const cards = document.querySelectorAll( '.eg-album__card' );

	cards.forEach( ( card ) => {
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
			// (Certains navigateurs ont besoin d'un évènement de resize pour ajuster le flex-basis)
			window.dispatchEvent( new Event( 'resize' ) );
		} );
	} );

	// Écouter les évènements de fermeture sur tous les overlays d'albums
	const overlays = document.querySelectorAll( '.eg-album__overlay' );

	overlays.forEach( ( overlay ) => {
		const closeBtn = overlay.querySelector( '.eg-album__overlay-close' );

		const closeModal = () => {
			overlay.classList.remove( 'is-active' );
			// Attendre la fin de la transition d'opacité avant de masquer
			setTimeout( () => {
				overlay.style.display = 'none';
			}, 350 );
			document.body.style.overflow = '';
		};

		if ( closeBtn ) {
			closeBtn.addEventListener( 'click', ( e ) => {
				e.stopPropagation();
				closeModal();
			} );
		}

		// Fermer au clic en dehors du contenu
		overlay.addEventListener( 'click', ( e ) => {
			if ( e.target === overlay ) {
				closeModal();
			}
		} );

		// Échappe pour fermer
		document.addEventListener( 'keydown', ( e ) => {
			if ( e.key === 'Escape' && overlay.classList.contains( 'is-active' ) ) {
				closeModal();
			}
		} );
	} );
} );
