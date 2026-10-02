/**
 * Script d'administration TypeScript pour la gestion de l'optimisation en masse.
 */

interface EgMediaBulkConfig {
	action: string;
	nonce: string;
	ajaxUrl: string;
	unoptimizedCount: string | number;
}

declare const egMediaBulk: EgMediaBulkConfig | undefined;

document.addEventListener( 'DOMContentLoaded', () => {
	const startButton = document.getElementById( 'eg-media-bulk-start' ) as HTMLButtonElement | null;
	const countSpan = document.getElementById( 'eg-media-bulk-count' ) as HTMLElement | null;
	const progressBar = document.getElementById( 'eg-media-bulk-progress' ) as HTMLProgressElement | null;
	const statusDiv = document.getElementById( 'eg-media-bulk-status' ) as HTMLElement | null;

	if (
		! startButton ||
		! countSpan ||
		! progressBar ||
		! statusDiv ||
		typeof egMediaBulk === 'undefined'
	) {
		return;
	}

	const totalToOptimize = parseInt( String( egMediaBulk.unoptimizedCount ), 10 );
	let optimizedSoFar = 0;

	const processBatch = (): void => {
		const formData = new FormData();
		formData.append( 'action', egMediaBulk.action );
		formData.append( 'nonce', egMediaBulk.nonce );

		fetch( egMediaBulk.ajaxUrl, {
			method: 'POST',
			body: formData,
		} )
			.then( ( response: Response ) => {
				if ( ! response.ok ) {
					throw new Error( 'Erreur réseau ou réponse invalide.' );
				}
				return response.json();
			} )
			.then( ( data: { success: boolean; data?: { message?: string; remaining?: string | number; processed?: string | number } } ) => {
				if ( ! data.success ) {
					const message =
						data.data && data.data.message
							? data.data.message
							: 'Une erreur est survenue.';
					throw new Error( message );
				}

				const remaining = parseInt( String( data.data?.remaining || 0 ), 10 );
				const processed = parseInt( String( data.data?.processed || 0 ), 10 );

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
					setTimeout( () => {
						window.location.reload();
					}, 1500 );
				}
			} )
			.catch( ( error: Error ) => {
				statusDiv.style.color = '#dc3232';
				statusDiv.textContent = `Erreur : ${ error.message }`;
				startButton.disabled = false;
			} );
	};

	startButton.addEventListener( 'click', () => {
		startButton.disabled = true;
		progressBar.style.display = 'block';
		progressBar.value = 0;
		progressBar.max = totalToOptimize;
		statusDiv.textContent = "Démarrage de l'optimisation...";

		processBatch();
	} );
} );
