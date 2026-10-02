/**
 * Composant d'édition pour le bloc Visionneuse de Galerie.
 */

import React, { useState, useEffect } from 'react';
import { __ } from '@wordpress/i18n';
import { useSelect } from '@wordpress/data';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import apiFetch from '@wordpress/api-fetch';
import {
	PanelBody,
	SelectControl,
	ToggleControl,
	RangeControl,
	Placeholder,
} from '@wordpress/components';
import type {
	ViewerAttributes,
	GallerySource,
	ViewerLayout,
	ImageResolution,
	SortByField,
	SortOrder,
} from '../../types/viewer';
import type { GalleryTerm } from '../../types/gallery';
import type { PiwigoAlbum } from '../../types/piwigo';

interface EditProps {
	attributes: ViewerAttributes;
	setAttributes: ( attributes: Partial<ViewerAttributes> ) => void;
}

export default function Edit( { attributes, setAttributes }: EditProps ): React.ReactElement {
	const {
		galleryId,
		gallerySource = 'local',
		sortBy = 'date',
		sortOrder = 'DESC',
		slideshow = false,
		tempo = 3000,
		resolution = 'full',
		layout = 'viewer',
		imagesPerPage = 30,
	} = attributes;

	const blockProps = useBlockProps( {
		className: 'eg-viewer-editor-wrapper',
	} );

	const [ piwigoAlbums, setPiwigoAlbums ] = useState<PiwigoAlbum[]>( [] );
	const [ isPiwigoLoading, setIsPiwigoLoading ] = useState<boolean>( false );

	// Récupérer la liste des galeries locales via la taxonomie eg_media_gallery
	const galleries = useSelect( ( select: any ) => {
		return select( 'core' )?.getEntityRecords(
			'taxonomy',
			'eg_media_gallery',
			{
				per_page: -1,
			}
		) as GalleryTerm[] | null | undefined;
	}, [] );

	// Charger les albums Piwigo si la source est Piwigo
	useEffect( () => {
		if ( gallerySource === 'piwigo' ) {
			setIsPiwigoLoading( true );
			apiFetch<PiwigoAlbum[]>( { path: '/eg-media/v1/piwigo/albums' } )
				.then( ( data: PiwigoAlbum[] ) => {
					setPiwigoAlbums( data || [] );
					setIsPiwigoLoading( false );
				} )
				.catch( () => {
					setPiwigoAlbums( [] );
					setIsPiwigoLoading( false );
				} );
		}
	}, [ gallerySource ] );

	// Construire les options pour le SelectControl des galeries
	const galleryOptions = [
		{ label: __( 'Sélectionnez une galerie…', 'eg-media' ) as string, value: '' },
	];

	if ( gallerySource === 'piwigo' ) {
		piwigoAlbums.forEach( ( album: PiwigoAlbum ) => {
			galleryOptions.push( {
				label: album.name,
				value: String( album.id ),
			} );
		} );
	} else if ( galleries && Array.isArray( galleries ) ) {
		galleries.forEach( ( gallery: GalleryTerm ) => {
			galleryOptions.push( {
				label: gallery.name,
				value: String( gallery.id ),
			} );
		} );
	}

	// Trouver le nom de la galerie sélectionnée pour l'affichage
	let selectedGalleryName = '';
	if ( galleryId ) {
		if ( gallerySource === 'piwigo' ) {
			const found = piwigoAlbums.find( ( a: PiwigoAlbum ) => String( a.id ) === String( galleryId ) );
			selectedGalleryName = found ? found.name : `ID: ${ galleryId }`;
		} else if ( galleries && Array.isArray( galleries ) ) {
			const found = galleries.find( ( g: GalleryTerm ) => g.id === galleryId );
			selectedGalleryName = found ? found.name : `ID: ${ galleryId }`;
		}
	}

	const handleGalleryChange = ( value: string ) => {
		setAttributes( {
			galleryId: value ? parseInt( value, 10 ) : undefined,
		} );
	};

	return (
		<>
			<InspectorControls>
				<PanelBody
					title={ __( 'Réglages de la Visionneuse', 'eg-media' ) as string }
					initialOpen={ true }
				>
					<SelectControl
						label={ __( 'Source', 'eg-media' ) as string }
						value={ gallerySource }
						options={ [
							{ label: __( 'Galerie locale (WordPress)', 'eg-media' ) as string, value: 'local' },
							{ label: __( 'Album distant (Piwigo)', 'eg-media' ) as string, value: 'piwigo' },
						] }
						onChange={ ( value: string ) => {
							setAttributes( {
								gallerySource: value as GallerySource,
								galleryId: undefined, // Reset selection
							} );
						} }
					/>
					<SelectControl
						label={ ( gallerySource === 'piwigo' ? __( 'Album Piwigo', 'eg-media' ) : __( 'Galerie', 'eg-media' ) ) as string }
						value={ galleryId ? String( galleryId ) : '' }
						options={ galleryOptions }
						onChange={ handleGalleryChange }
						help={ isPiwigoLoading ? ( __( 'Chargement des albums Piwigo...', 'eg-media' ) as string ) : undefined }
					/>
					<SelectControl
						label={ __( 'Mise en page', 'eg-media' ) as string }
						value={ layout }
						options={ [
							{
								label: __(
									'Visionneuse (Diaporama)',
									'eg-media'
								) as string,
								value: 'viewer',
							},
							{
								label: __( 'Grille justifiée', 'eg-media' ) as string,
								value: 'justified',
							},
						] }
						onChange={ ( value: string ) =>
							setAttributes( { layout: value as ViewerLayout } )
						}
					/>
					<SelectControl
						label={ __( 'Résolution', 'eg-media' ) as string }
						value={ resolution }
						options={ [
							{
								label: __(
									'Taille originale (Full)',
									'eg-media'
								) as string,
								value: 'full',
							},
							{
								label: __( 'Grande (Large)', 'eg-media' ) as string,
								value: 'large',
							},
							{
								label: __( 'Moyenne (Medium)', 'eg-media' ) as string,
								value: 'medium',
							},
							{
								label: __(
									'Miniature (Thumbnail)',
									'eg-media'
								) as string,
								value: 'thumbnail',
							},
						] }
						onChange={ ( value: string ) =>
							setAttributes( { resolution: value as ImageResolution } )
						}
					/>
					<SelectControl
						label={ __( 'Trier par', 'eg-media' ) as string }
						value={ sortBy }
						options={ [
							{
								label: __( 'Date de prise de vue', 'eg-media' ) as string,
								value: 'date',
							},
							{
								label: __( 'Nom de fichier', 'eg-media' ) as string,
								value: 'name',
							},
						] }
						onChange={ ( value: string ) =>
							setAttributes( { sortBy: value as SortByField } )
						}
					/>
					<SelectControl
						label={ __( 'Ordre', 'eg-media' ) as string }
						value={ sortOrder }
						options={ [
							{
								label: __(
									'Descendant (Z-A / Nouveau en premier)',
									'eg-media'
								) as string,
								value: 'DESC',
							},
							{
								label: __(
									'Ascendant (A-Z / Ancien en premier)',
									'eg-media'
								) as string,
								value: 'ASC',
							},
						] }
						onChange={ ( value: string ) =>
							setAttributes( { sortOrder: value as SortOrder } )
						}
					/>
					{ layout !== 'justified' && (
						<>
							<ToggleControl
								label={ __(
									'Activer le Diaporama',
									'eg-media'
								) as string }
								checked={ slideshow }
								onChange={ ( value: boolean ) =>
									setAttributes( { slideshow: value } )
								}
							/>
							{ slideshow && (
								<RangeControl
									label={ __( 'Tempo (ms)', 'eg-media' ) as string }
									value={ tempo }
									onChange={ ( value: number | undefined ) =>
										setAttributes( { tempo: value || 3000 } )
									}
									min={ 1000 }
									max={ 10000 }
									step={ 500 }
								/>
							) }
						</>
					) }
					{ layout === 'justified' && (
						<RangeControl
							label={ __( 'Images par lot', 'eg-media' ) as string }
							value={ imagesPerPage }
							onChange={ ( value: number | undefined ) =>
								setAttributes( { imagesPerPage: value || 30 } )
							}
							min={ 10 }
							max={ 100 }
							step={ 5 }
						/>
					) }
				</PanelBody>
			</InspectorControls>

			<div { ...blockProps }>
				{ ! galleryId ? (
					<Placeholder
						icon="images-alt"
						label={ __( 'Visionneuse de Galerie', 'eg-media' ) as string }
						instructions={ __(
							'Veuillez sélectionner une galerie/un album dans la barre latérale des réglages.',
							'eg-media'
						) as string }
					/>
				) : (
					<div className="eg-viewer-placeholder">
						<div className="eg-viewer-placeholder__icon">
							<span className="dashicons dashicons-images-alt2"></span>
						</div>
						<div className="eg-viewer-placeholder__content">
							<h3>
								{ __( 'Visionneuse de Galerie', 'eg-media' ) as string }
							</h3>
							<p>
								<strong>
									{ gallerySource === 'piwigo'
										? ( __( 'Album Piwigo active :', 'eg-media' ) as string )
										: ( __( 'Galerie active :', 'eg-media' ) as string ) }
								</strong>{ ' ' }
								{ selectedGalleryName }
							</p>
							<div className="eg-viewer-placeholder__meta">
								<span>
									<strong>Source :</strong>{ ' ' }
									{ gallerySource === 'piwigo' ? 'Piwigo' : 'Locale (WordPress)' }
								</span>
								<span>
									<strong>Mise en page :</strong>{ ' ' }
									{ layout === 'justified'
										? 'Grille justifiée'
										: 'Visionneuse' }
								</span>
								<span>
									<strong>Résolution :</strong>{ ' ' }
									{ resolution }
								</span>
								<span>
									<strong>Tri :</strong>{ ' ' }
									{ sortBy === 'date' ? 'Date' : 'Nom' } (
									{ sortOrder })
								</span>
								{ layout !== 'justified' && (
									<span>
										<strong>Diaporama :</strong>{ ' ' }
										{ slideshow
											? `Oui (${ tempo }ms)`
											: 'Non' }
									</span>
								) }
								{ layout === 'justified' && (
									<span>
										<strong>Images par lot :</strong>{ ' ' }
										{ imagesPerPage }
									</span>
								) }
							</div>
						</div>
					</div>
				) }
			</div>
		</>
	);
}
