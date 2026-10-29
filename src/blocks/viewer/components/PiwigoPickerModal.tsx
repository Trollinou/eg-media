/**
 * Modale mutualisée de sélection et d'importation d'images depuis Piwigo.
 */

import React, { useState, useEffect } from 'react';
import { Modal, SelectControl, Spinner } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';

import type { PiwigoAlbum, PiwigoImage, PiwigoImportResponse } from '../../../types/piwigo';

export interface PiwigoPickerModalProps {
	postId: number;
	endpoint: '/eg-media/v1/piwigo/import-featured-image' | '/eg-media/v1/piwigo/import-image';
	loadingLabel: string;
	onClose: () => void;
	onSuccess: ( _response: PiwigoImportResponse ) => void;
}

export default function PiwigoPickerModal( {
	postId,
	endpoint,
	loadingLabel,
	onClose,
	onSuccess,
}: PiwigoPickerModalProps ): React.ReactElement {
	const [ albums, setAlbums ] = useState<PiwigoAlbum[]>( [] );
	const [ selectedAlbum, setSelectedAlbum ] = useState<string>( '' );
	const [ images, setImages ] = useState<PiwigoImage[]>( [] );
	const [ isLoadingAlbums, setIsLoadingAlbums ] = useState<boolean>( true );
	const [ isLoadingImages, setIsLoadingImages ] = useState<boolean>( false );
	const [ isImporting, setIsImporting ] = useState<boolean>( false );

	// Charger les albums Piwigo à l'ouverture de la modale.
	useEffect( () => {
		let isMounted = true;
		setIsLoadingAlbums( true );
		apiFetch<PiwigoAlbum[]>( { path: '/eg-media/v1/piwigo/albums' } )
			.then( ( data: PiwigoAlbum[] ) => {
				if ( isMounted ) {
					setAlbums( data || [] );
					setIsLoadingAlbums( false );
				}
			} )
			.catch( () => {
				if ( isMounted ) {
					setAlbums( [] );
					setIsLoadingAlbums( false );
				}
			} );

		return () => {
			isMounted = false;
		};
	}, [] );

	// Charger les photos lorsque l'album sélectionné change.
	useEffect( () => {
		let isMounted = true;
		if ( selectedAlbum ) {
			setIsLoadingImages( true );
			apiFetch<PiwigoImage[]>( { path: `/eg-media/v1/piwigo/album-images?id=${ selectedAlbum }` } )
				.then( ( data: PiwigoImage[] ) => {
					if ( isMounted ) {
						setImages( data || [] );
						setIsLoadingImages( false );
					}
				} )
				.catch( () => {
					if ( isMounted ) {
						setImages( [] );
						setIsLoadingImages( false );
					}
				} );
		} else {
			setImages( [] );
		}

		return () => {
			isMounted = false;
		};
	}, [ selectedAlbum ] );

	const importImage = ( imageId: number ): void => {
		if ( isImporting ) {
			return;
		}
		setIsImporting( true );
		const album = albums.find( ( a: PiwigoAlbum ) => String( a.id ) === String( selectedAlbum ) );
		const albumName = album ? album.name : '';

		apiFetch<PiwigoImportResponse>( {
			path: endpoint,
			method: 'POST',
			data: {
				post_id: postId,
				piwigo_image_id: imageId,
				album_name: albumName,
			},
		} )
			.then( ( response: PiwigoImportResponse ) => {
				setIsImporting( false );
				onSuccess( response );
				onClose();
			} )
			.catch( () => {
				alert( __( "Erreur lors de l'importation de l'image Piwigo.", 'eg-media' ) as string );
				setIsImporting( false );
			} );
	};

	const albumOptions = [
		{ label: __( 'Sélectionnez un album…', 'eg-media' ) as string, value: '' },
		...albums.map( ( a: PiwigoAlbum ) => ( { label: a.name, value: String( a.id ) } ) ),
	];

	return (
		<Modal
			title={ __( 'Sélectionner une image Piwigo', 'eg-media' ) as string }
			onRequestClose={ onClose }
			className="eg-piwigo-modal"
		>
			<div className="eg-piwigo-modal__body">
				{ isLoadingAlbums ? (
					<Spinner />
				) : (
					<SelectControl
						label={ __( 'Album Piwigo', 'eg-media' ) as string }
						value={ selectedAlbum }
						options={ albumOptions }
						onChange={ setSelectedAlbum }
					/>
				) }

				{ isLoadingImages && <Spinner /> }

				{ ! isLoadingImages && images.length > 0 && (
					<div className="eg-piwigo-modal__grid">
						{ images.map( ( img: PiwigoImage ) => {
							const deriv = img.derivatives || {};
							const thumbUrl = deriv.thumb?.url || deriv.square?.url || img.element_url || '';
							return (
								<button
									type="button"
									key={ img.id }
									className="eg-piwigo-modal__item"
									onClick={ () => importImage( img.id ) }
								>
									<img
										src={ thumbUrl }
										alt={ img.name || img.file || '' }
										className="eg-piwigo-modal__item-thumb"
										loading="lazy"
									/>
									<span className="eg-piwigo-modal__item-label">
										{ img.name || img.file }
									</span>
								</button>
							);
						} ) }
					</div>
				) }

				{ isImporting && (
					<div className="eg-piwigo-modal__loading-overlay">
						<Spinner />
						<p className="eg-piwigo-modal__loading-text">{ loadingLabel }</p>
					</div>
				) }
			</div>
		</Modal>
	);
}

