import { registerBlockType } from '@wordpress/blocks';
import Edit from './edit';
import metadata from './block.json';
import './style.scss';
import './editor.scss';

import { addFilter } from '@wordpress/hooks';
import { createHigherOrderComponent } from '@wordpress/compose';
import { Button, Modal, SelectControl, Spinner } from '@wordpress/components';
import { useState, useEffect, Fragment } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';

registerBlockType( metadata.name, {
	edit: Edit,
	save: () => null, // Dynamic block: render in PHP
} );

const PiwigoFeaturedImageWrapper = createHigherOrderComponent( ( OriginalComponent ) => {
	return ( props ) => {
		const [ isModalOpen, setIsModalOpen ] = useState( false );
		const [ albums, setAlbums ] = useState( [] );
		const [ selectedAlbum, setSelectedAlbum ] = useState( '' );
		const [ images, setImages ] = useState( [] );
		const [ isLoadingAlbums, setIsLoadingAlbums ] = useState( false );
		const [ isLoadingImages, setIsLoadingImages ] = useState( false );
		const [ isImporting, setIsImporting ] = useState( false );

		// Récupérer le ID du post en cours
		const postId = useSelect( ( select ) => {
			return select( 'core/editor' ).getCurrentPostId();
		}, [] );

		// Récupérer l'action de mise à jour du post
		const { editPost } = useDispatch( 'core/editor' );

		// Charger les albums Piwigo au clic sur le bouton d'ouverture
		const openModal = () => {
			setIsModalOpen( true );
			setIsLoadingAlbums( true );
			apiFetch( { path: '/eg-media/v1/piwigo/albums' } )
				.then( ( data ) => {
					setAlbums( data || [] );
					setIsLoadingAlbums( false );
				} )
				.catch( () => {
					setAlbums( [] );
					setIsLoadingAlbums( false );
				} );
		};

		// Charger les photos lorsque l'album change
		useEffect( () => {
			if ( selectedAlbum ) {
				setIsLoadingImages( true );
				apiFetch( { path: `/eg-media/v1/piwigo/album-images?id=${selectedAlbum}` } )
					.then( ( data ) => {
						setImages( data || [] );
						setIsLoadingImages( false );
					} )
					.catch( () => {
						setImages( [] );
						setIsLoadingImages( false );
					} );
			} else {
				setImages( [] );
			}
		}, [ selectedAlbum ] );

		// Importer la photo choisie
		const importImage = ( imageId ) => {
			setIsImporting( true );
			apiFetch( {
				path: '/eg-media/v1/piwigo/import-featured-image',
				method: 'POST',
				data: {
					post_id: postId,
					piwigo_image_id: imageId,
				},
			} )
				.then( ( response ) => {
					if ( response && response.attachment_id ) {
						// Mettre à jour l'image mise en avant dans l'éditeur de WordPress
						editPost( { featured_media: response.attachment_id } );
					}
					setIsImporting( false );
					setIsModalOpen( false );
				} )
				.catch( () => {
					alert( __( "Erreur lors de l'importation de l'image Piwigo.", 'eg-media' ) );
					setIsImporting( false );
				} );
		};

		// Options de sélection des albums
		const albumOptions = [
			{ label: __( 'Sélectionnez un album…', 'eg-media' ), value: '' },
			...albums.map( ( a ) => ( { label: a.name, value: a.id } ) ),
		];

		// Ne pas afficher si on n'a pas de post ID
		if ( ! postId ) {
			return <OriginalComponent { ...props } />;
		}

		return (
			<Fragment>
				<OriginalComponent { ...props } />
				
				<Button
					variant="secondary"
					onClick={ openModal }
					style={ {
						width: '100%',
						justifyContent: 'center',
						marginTop: '10px',
					} }
				>
					{ __( 'Set Piwigo Featured Image', 'eg-media' ) }
				</Button>

				{ isModalOpen && (
					<Modal
						title={ __( 'Sélectionner une image Piwigo', 'eg-media' ) }
						onRequestClose={ () => setIsModalOpen( false ) }
						style={ { maxWidth: '800px', width: '90%' } }
					>
						<div className="eg-piwigo-featured-image-modal" style={ { padding: '10px 0' } }>
							{ isLoadingAlbums ? (
								<Spinner />
							) : (
								<SelectControl
									label={ __( 'Album Piwigo', 'eg-media' ) }
									value={ selectedAlbum }
									options={ albumOptions }
									onChange={ setSelectedAlbum }
								/>
							) }

							{ isLoadingImages && <Spinner /> }

							{ ! isLoadingImages && images.length > 0 && (
								<div
									className="eg-piwigo-images-grid"
									style={ {
										display: 'grid',
										gridTemplateColumns: 'repeat(auto-fill, minmax(120px, 1fr))',
										gap: '12px',
										marginTop: '20px',
										maxHeight: '400px',
										overflowY: 'auto',
									} }
								>
									{ images.map( ( img ) => {
										// Trouver l'URL de thumbnail de l'image
										const deriv = img.derivatives || {};
										const thumbUrl = deriv.thumb?.url || deriv.square?.url || img.element_url || '';
										return (
											<div
												key={ img.id }
												className="eg-piwigo-image-item"
												style={ {
													border: '1px solid #ccd0d4',
													borderRadius: '4px',
													padding: '4px',
													cursor: 'pointer',
													textAlign: 'center',
													background: '#f6f7f7',
												    display: 'flex',
												    flexDirection: 'column',
												    alignItems: 'center',
												    justifyContent: 'center',
												} }
												onClick={ () => ! isImporting && importImage( img.id ) }
											>
												<img
													src={ thumbUrl }
													alt={ img.name || img.file }
													style={ {
														maxWidth: '100%',
														maxHeight: '100px',
														objectFit: 'cover',
													} }
												/>
												<span
													style={ {
														fontSize: '11px',
														display: 'block',
														marginTop: '4px',
														textOverflow: 'ellipsis',
														overflow: 'hidden',
														whiteSpace: 'nowrap',
														width: '100%',
													} }
												>
													{ img.name || img.file }
												</span>
											</div>
										);
									} ) }
								</div>
							) }

							{ isImporting && (
								<div
									style={ {
										position: 'absolute',
										top: 0,
										left: 0,
										right: 0,
										bottom: 0,
										background: 'rgba(255, 255, 255, 0.8)',
										display: 'flex',
										flexDirection: 'column',
										alignItems: 'center',
										justifyContent: 'center',
										zIndex: 10000,
									} }
								>
									<Spinner />
									<p style={ { marginTop: '10px', fontWeight: 'bold' } }>
										{ __( 'Téléchargement et association de l\'image...', 'eg-media' ) }
									</p>
								</div>
							)}
						</div>
					</Modal>
				) }
			</Fragment>
		);
	};
}, 'withPiwigoFeaturedImage' );

addFilter(
	'editor.PostFeaturedImage',
	'eg-media/piwigo-featured-image-button',
	PiwigoFeaturedImageWrapper
);
