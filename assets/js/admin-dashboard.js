/**
 * Script d'administration pour la gestion de l'optimisation en masse.
 */
document.addEventListener( 'DOMContentLoaded', function () {
	const startButton = document.getElementById( 'eg-media-bulk-start' );
	const countSpan = document.getElementById( 'eg-media-bulk-count' );
	const progressBar = document.getElementById( 'eg-media-bulk-progress' );
	const statusDiv = document.getElementById( 'eg-media-bulk-status' );

	if (
		! startButton ||
		! countSpan ||
		! progressBar ||
		! statusDiv ||
		typeof egMediaBulk === 'undefined'
	) {
		return;
	}

	const totalToOptimize = parseInt( egMediaBulk.unoptimizedCount, 10 );
	let optimizedSoFar = 0;

	startButton.addEventListener( 'click', function () {
		startButton.disabled = true;
		progressBar.style.display = 'block';
		progressBar.value = 0;
		progressBar.max = totalToOptimize;
		statusDiv.textContent = "Démarrage de l'optimisation...";

		processBatch();
	} );

	function processBatch() {
		const formData = new FormData();
		formData.append( 'action', egMediaBulk.action );
		formData.append( 'nonce', egMediaBulk.nonce );

		fetch( egMediaBulk.ajaxUrl, {
			method: 'POST',
			body: formData,
		} )
			.then( ( response ) => {
				if ( ! response.ok ) {
					throw new Error( 'Erreur réseau ou réponse invalide.' );
				}
				return response.json();
			} )
			.then( ( data ) => {
				if ( ! data.success ) {
					const message =
						data.data && data.data.message
							? data.data.message
							: 'Une erreur est survenue.';
					throw new Error( message );
				}

				const remaining = parseInt( data.data.remaining, 10 );
				const processed = parseInt( data.data.processed, 10 );

				optimizedSoFar += processed;
				progressBar.value = optimizedSoFar;
				countSpan.textContent = remaining.toString();

				if ( remaining > 0 && processed > 0 ) {
					statusDiv.textContent = `Optimisation en cours : ${ optimizedSoFar } / ${ totalToOptimize } images traitées...`;
					processBatch();
				} else {
					statusDiv.style.color = '#46b450';
					statusDiv.textContent =
						'Optimisation terminée avec succès ! Rechargement de la page...';
					setTimeout( function () {
						window.location.reload();
					}, 1500 );
				}
			} )
			.catch( ( error ) => {
				statusDiv.style.color = '#dc3232';
				statusDiv.textContent = `Erreur : ${ error.message }`;
				startButton.disabled = false;
			} );
	}
} );
