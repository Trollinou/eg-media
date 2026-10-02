/**
 * Point d'entrée pour le bloc Visionneuse de Galerie et les intégrations Piwigo.
 */

import React, { useState, useEffect, Fragment } from 'react';
import { registerBlockType } from '@wordpress/blocks';
import { addFilter } from '@wordpress/hooks';
import { createHigherOrderComponent } from '@wordpress/compose';
import { Button, Modal, PanelBody, SelectControl, Spinner } from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { InspectorControls } from '@wordpress/block-editor';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';

import Edit from './edit';
import metadata from './block.json';
import './style.scss';
import './editor.scss';

import type { PiwigoAlbum, PiwigoImage, PiwigoImportResponse } from '../../types/piwigo';

registerBlockType( metadata.name, {
	...metadata,
	edit: Edit as any,
	save: () => null, // Dynamic block: render in PHP
} as any );

interface BaseComponentProps {
	name?: string;
	clientId?: string;
	attributes?: Record<string, unknown>;
	setAttributes?: ( attrs: Record<string, unknown> ) => void;
	[ key: string ]: unknown;
}

const PiwigoFeaturedImageWrapper = createHigherOrderComponent(
	( OriginalComponent: React.ComponentType<BaseComponentProps> ) => {
		return ( props: BaseComponentProps ) => {
			const [ isModalOpen, setIsModalOpen ] = useState<boolean>( false );
			const [ albums, setAlbums ] = useState<PiwigoAlbum[]>( [] );
			const [ selectedAlbum, setSelectedAlbum ] = useState<string>( '' );
			const [ images, setImages ] = useState<PiwigoImage[]>( [] );
			const [ isLoadingAlbums, setIsLoadingAlbums ] = useState<boolean>( false );
			const [ isLoadingImages, setIsLoadingImages ] = useState<boolean>( false );
			const [ isImporting, setIsImporting ] = useState<boolean>( false );

			// Récupérer le ID du post en cours
			const postId = useSelect( ( select: any ) => {
				return select( 'core/editor' )?.getCurrentPostId() as number | undefined;
			}, [] );

			// Récupérer l'action de mise à jour du post
			const { editPost } = useDispatch( 'core/editor' ) as { editPost?: ( data: Record<string, unknown> ) => void };

			// Charger les albums Piwigo au clic sur le bouton d'ouverture
			const openModal = () => {
				setIsModalOpen( true );
				setIsLoadingAlbums( true );
				apiFetch<PiwigoAlbum[]>( { path: '/eg-media/v1/piwigo/albums' } )
					.then( ( data: PiwigoAlbum[] ) => {
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
					apiFetch<PiwigoImage[]>( { path: `/eg-media/v1/piwigo/album-images?id=${ selectedAlbum }` } )
						.then( ( data: PiwigoImage[] ) => {
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
			const importImage = ( imageId: number ) => {
				setIsImporting( true );
				const album = albums.find( ( a: PiwigoAlbum ) => String( a.id ) === String( selectedAlbum ) );
				const albumName = album ? album.name : '';
				apiFetch<PiwigoImportResponse>( {
					path: '/eg-media/v1/piwigo/import-featured-image',
					method: 'POST',
					data: {
						post_id: postId,
						piwigo_image_id: imageId,
						album_name: albumName,
					},
				} )
					.then( ( response: PiwigoImportResponse ) => {
						if ( response && response.attachment_id && editPost ) {
							// Mettre à jour l'image mise en avant dans l'éditeur de WordPress
							editPost( { featured_media: response.attachment_id } );
						}
						setIsImporting( false );
						setIsModalOpen( false );
					} )
					.catch( () => {
						alert( __( "Erreur lors de l'importation de l'image Piwigo.", 'eg-media' ) as string );
						setIsImporting( false );
					} );
			};

			// Options de sélection des albums
			const albumOptions = [
				{ label: __( 'Sélectionnez un album…', 'eg-media' ) as string, value: '' },
				...albums.map( ( a: PiwigoAlbum ) => ( { label: a.name, value: String( a.id ) } ) ),
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
						{ __( 'Set Piwigo Featured Image', 'eg-media' ) as string }
					</Button>

					{ isModalOpen && (
						<Modal
							title={ __( 'Sélectionner une image Piwigo', 'eg-media' ) as string }
							onRequestClose={ () => setIsModalOpen( false ) }
							style={ { maxWidth: '800px', width: '90%' } }
						>
							<div className="eg-piwigo-featured-image-modal" style={ { padding: '10px 0' } }>
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
										{ images.map( ( img: PiwigoImage ) => {
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
														alt={ img.name || img.file || '' }
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
											{ __( 'Téléchargement et association de l\'image...', 'eg-media' ) as string }
										</p>
									</div>
								) }
							</div>
						</Modal>
					) }
				</Fragment>
			);
		};
	},
	'withPiwigoFeaturedImage'
);

addFilter(
	'editor.PostFeaturedImage',
	'eg-media/piwigo-featured-image-button',
	PiwigoFeaturedImageWrapper
);

const PiwigoBlockImageWrapper = createHigherOrderComponent(
	( OriginalComponent: React.ComponentType<BaseComponentProps> ) => {
		return ( props: BaseComponentProps ) => {
			if ( props.name !== 'core/image' ) {
				return <OriginalComponent { ...props } />;
			}

			const [ isModalOpen, setIsModalOpen ] = useState<boolean>( false );
			const [ albums, setAlbums ] = useState<PiwigoAlbum[]>( [] );
			const [ selectedAlbum, setSelectedAlbum ] = useState<string>( '' );
			const [ images, setImages ] = useState<PiwigoImage[]>( [] );
			const [ isLoadingAlbums, setIsLoadingAlbums ] = useState<boolean>( false );
			const [ isLoadingImages, setIsLoadingImages ] = useState<boolean>( false );
			const [ isImporting, setIsImporting ] = useState<boolean>( false );

			const postId = useSelect( ( select: any ) => {
				return ( select( 'core/editor' )?.getCurrentPostId() as number | undefined ) || 0;
			}, [] );

			const openModal = () => {
				setIsModalOpen( true );
				setIsLoadingAlbums( true );
				apiFetch<PiwigoAlbum[]>( { path: '/eg-media/v1/piwigo/albums' } )
					.then( ( data: PiwigoAlbum[] ) => {
						setAlbums( data || [] );
						setIsLoadingAlbums( false );
					} )
					.catch( () => {
						setAlbums( [] );
						setIsLoadingAlbums( false );
					} );
			};

			useEffect( () => {
				if ( selectedAlbum ) {
					setIsLoadingImages( true );
					apiFetch<PiwigoImage[]>( { path: `/eg-media/v1/piwigo/album-images?id=${ selectedAlbum }` } )
						.then( ( data: PiwigoImage[] ) => {
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

			// Injecter le bouton "Sélectionner depuis Piwigo" dans le placeholder du bloc Image
			useEffect( () => {
				const currentAttrs = props.attributes as { url?: string } | undefined;
				if ( ! currentAttrs?.url && props.clientId ) {
					const timer = setTimeout( () => {
						const iframe = document.querySelector( 'iframe[name="editor-canvas"]' ) as HTMLIFrameElement | null;
						const doc = iframe?.contentDocument || document;
						const blockContainer = doc.getElementById( `block-${ props.clientId }` ) ||
						                      doc.querySelector( `[data-block="${ props.clientId }"]` );
						if ( blockContainer ) {
							const fieldset = blockContainer.querySelector( '.components-placeholder__fieldset' );
							if ( fieldset && ! fieldset.querySelector( '.eg-piwigo-placeholder-button' ) ) {
								const btn = document.createElement( 'button' );
								btn.className = 'components-button is-secondary is-next-40px-default-size block-editor-media-placeholder__button eg-piwigo-placeholder-button';
								btn.type = 'button';
								btn.innerText = __( 'Sélectionner depuis Piwigo', 'eg-media' ) as string;
								btn.onclick = ( e ) => {
									e.preventDefault();
									e.stopPropagation();
									openModal();
								};
								fieldset.appendChild( btn );
							}
						}
					}, 50 );
					return () => clearTimeout( timer );
				}
			} );

			const importImage = ( imageId: number ) => {
				setIsImporting( true );
				const album = albums.find( ( a: PiwigoAlbum ) => String( a.id ) === String( selectedAlbum ) );
				const albumName = album ? album.name : '';
				apiFetch<PiwigoImportResponse>( {
					path: '/eg-media/v1/piwigo/import-image',
					method: 'POST',
					data: {
						post_id: postId,
						piwigo_image_id: imageId,
						album_name: albumName,
					},
				} )
					.then( ( response: PiwigoImportResponse ) => {
						if ( response && response.attachment_id && response.url && props.setAttributes ) {
							props.setAttributes( {
								id: response.attachment_id,
								url: response.url,
								alt: response.alt || '',
							} );
						}
						setIsImporting( false );
						setIsModalOpen( false );
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
				<Fragment>
					<OriginalComponent { ...props } />
					
					<InspectorControls>
						<PanelBody title={ __( 'Intégration Piwigo', 'eg-media' ) as string } initialOpen={ true }>
							<Button
								variant="secondary"
								onClick={ openModal }
								style={ {
									width: '100%',
									justifyContent: 'center',
								} }
							>
								{ __( 'Sélectionner depuis Piwigo', 'eg-media' ) as string }
							</Button>
						</PanelBody>
					</InspectorControls>

					{ isModalOpen && (
						<Modal
							title={ __( 'Sélectionner une image Piwigo', 'eg-media' ) as string }
							onRequestClose={ () => setIsModalOpen( false ) }
							style={ { maxWidth: '800px', width: '90%' } }
						>
							<div className="eg-piwigo-image-select-modal" style={ { padding: '10px 0' } }>
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
										{ images.map( ( img: PiwigoImage ) => {
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
														alt={ img.name || img.file || '' }
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
											{ __( 'Téléchargement et insertion de l\'image...', 'eg-media' ) as string }
										</p>
									</div>
								) }
							</div>
						</Modal>
					) }
				</Fragment>
			);
		};
	},
	'withPiwigoImageSelect'
);

addFilter(
	'editor.BlockEdit',
	'eg-media/piwigo-image-select',
	PiwigoBlockImageWrapper
);
