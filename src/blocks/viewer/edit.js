import { __ } from '@wordpress/i18n';
import { useSelect } from '@wordpress/data';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import {
	PanelBody,
	SelectControl,
	ToggleControl,
	RangeControl,
	Placeholder,
} from '@wordpress/components';

export default function Edit( { attributes, setAttributes } ) {
	const {
		galleryId,
		sortBy,
		sortOrder,
		slideshow,
		tempo,
		resolution,
		layout,
		imagesPerPage,
	} = attributes;
	const blockProps = useBlockProps( {
		className: 'eg-viewer-editor-wrapper',
	} );

	// Récupérer la liste des galeries via la taxonomie eg_media_gallery
	const galleries = useSelect( ( select ) => {
		return select( 'core' ).getEntityRecords(
			'taxonomy',
			'eg_media_gallery',
			{
				per_page: -1,
			}
		);
	}, [] );

	// Construire les options pour le SelectControl des galeries
	const galleryOptions = [
		{ label: __( 'Sélectionnez une galerie…', 'eg-media' ), value: '' },
	];

	if ( galleries ) {
		galleries.forEach( ( gallery ) => {
			galleryOptions.push( {
				label: gallery.name,
				value: gallery.id,
			} );
		} );
	}

	// Trouver le nom de la galerie sélectionnée pour l'affichage
	const selectedGallery = galleries
		? galleries.find( ( g ) => g.id === parseInt( galleryId, 10 ) )
		: null;

	const handleGalleryChange = ( value ) => {
		setAttributes( {
			galleryId: value ? parseInt( value, 10 ) : undefined,
		} );
	};

	return (
		<>
			<InspectorControls>
				<PanelBody
					title={ __( 'Réglages de la Visionneuse', 'eg-media' ) }
					initialOpen={ true }
				>
					<SelectControl
						label={ __( 'Galerie', 'eg-media' ) }
						value={ galleryId || '' }
						options={ galleryOptions }
						onChange={ handleGalleryChange }
					/>
					<SelectControl
						label={ __( 'Mise en page', 'eg-media' ) }
						value={ layout || 'viewer' }
						options={ [
							{
								label: __(
									'Visionneuse (Diaporama)',
									'eg-media'
								),
								value: 'viewer',
							},
							{
								label: __( 'Grille justifiée', 'eg-media' ),
								value: 'justified',
							},
						] }
						onChange={ ( value ) =>
							setAttributes( { layout: value } )
						}
					/>
					<SelectControl
						label={ __( 'Résolution', 'eg-media' ) }
						value={ resolution || 'full' }
						options={ [
							{
								label: __(
									'Taille originale (Full)',
									'eg-media'
								),
								value: 'full',
							},
							{
								label: __( 'Grande (Large)', 'eg-media' ),
								value: 'large',
							},
							{
								label: __( 'Moyenne (Medium)', 'eg-media' ),
								value: 'medium',
							},
							{
								label: __(
									'Miniature (Thumbnail)',
									'eg-media'
								),
								value: 'thumbnail',
							},
						] }
						onChange={ ( value ) =>
							setAttributes( { resolution: value } )
						}
					/>
					<SelectControl
						label={ __( 'Trier par', 'eg-media' ) }
						value={ sortBy }
						options={ [
							{
								label: __( 'Date de prise de vue', 'eg-media' ),
								value: 'date',
							},
							{
								label: __( 'Nom de fichier', 'eg-media' ),
								value: 'name',
							},
						] }
						onChange={ ( value ) =>
							setAttributes( { sortBy: value } )
						}
					/>
					<SelectControl
						label={ __( 'Ordre', 'eg-media' ) }
						value={ sortOrder }
						options={ [
							{
								label: __(
									'Descendant (Z-A / Nouveau en premier)',
									'eg-media'
								),
								value: 'DESC',
							},
							{
								label: __(
									'Ascendant (A-Z / Ancien en premier)',
									'eg-media'
								),
								value: 'ASC',
							},
						] }
						onChange={ ( value ) =>
							setAttributes( { sortOrder: value } )
						}
					/>
					{ layout !== 'justified' && (
						<>
							<ToggleControl
								label={ __(
									'Activer le Diaporama',
									'eg-media'
								) }
								checked={ slideshow }
								onChange={ ( value ) =>
									setAttributes( { slideshow: value } )
								}
							/>
							{ slideshow && (
								<RangeControl
									label={ __( 'Tempo (ms)', 'eg-media' ) }
									value={ tempo }
									onChange={ ( value ) =>
										setAttributes( { tempo: value } )
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
							label={ __( 'Images par lot', 'eg-media' ) }
							value={ imagesPerPage || 30 }
							onChange={ ( value ) =>
								setAttributes( { imagesPerPage: value } )
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
						label={ __( 'Visionneuse de Galerie', 'eg-media' ) }
						instructions={ __(
							'Veuillez sélectionner une galerie dans la barre latérale des réglages.',
							'eg-media'
						) }
					/>
				) : (
					<div className="eg-viewer-placeholder">
						<div className="eg-viewer-placeholder__icon">
							<span className="dashicons dashicons-images-alt2"></span>
						</div>
						<div className="eg-viewer-placeholder__content">
							<h3>
								{ __( 'Visionneuse de Galerie', 'eg-media' ) }
							</h3>
							<p>
								<strong>
									{ __( 'Galerie active :', 'eg-media' ) }
								</strong>{ ' ' }
								{ selectedGallery
									? selectedGallery.name
									: `ID: ${ galleryId }` }
							</p>
							<div className="eg-viewer-placeholder__meta">
								<span>
									<strong>Mise en page :</strong>{ ' ' }
									{ layout === 'justified'
										? 'Grille justifiée'
										: 'Visionneuse' }
								</span>
								<span>
									<strong>Résolution :</strong>{ ' ' }
									{ resolution || 'full' }
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
										{ imagesPerPage || 30 }
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
