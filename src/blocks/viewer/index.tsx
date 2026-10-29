/**
 * Point d'entrée pour le bloc Visionneuse de Galerie et les intégrations Piwigo.
 */

import React, { useState, useEffect, Fragment } from 'react';
import { registerBlockType } from '@wordpress/blocks';
import { addFilter } from '@wordpress/hooks';
import { createHigherOrderComponent } from '@wordpress/compose';
import { Button, PanelBody } from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { InspectorControls } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';

import Edit from './edit';
import PiwigoPickerModal from './components/PiwigoPickerModal';
import metadata from './block.json';
import './style.scss';
import './editor.scss';

import type { PiwigoImportResponse } from '../../types/piwigo';

registerBlockType( metadata.name, {
	...metadata,
	edit: Edit as any,
	save: () => null, // Dynamic block: render in PHP
} as any );

interface BaseComponentProps {
	name?: string;
	clientId?: string;
	attributes?: Record<string, unknown>;
	setAttributes?: ( _attrs: Record<string, unknown> ) => void;
	[ key: string ]: unknown;
}

const PiwigoFeaturedImageWrapper = createHigherOrderComponent(
	( OriginalComponent: React.ComponentType<BaseComponentProps> ) => {
		return ( props: BaseComponentProps ) => {
			const [ isModalOpen, setIsModalOpen ] = useState<boolean>( false );

			const postId = useSelect( ( select: any ) => {
				return select( 'core/editor' )?.getCurrentPostId() as number | undefined;
			}, [] );

			const { editPost } = useDispatch( 'core/editor' ) as {
				editPost?: ( _data: Record<string, unknown> ) => void;
			};

			if ( ! postId ) {
				return <OriginalComponent { ...props } />;
			}

			return (
				<Fragment>
					<OriginalComponent { ...props } />

					<Button
						variant="secondary"
						onClick={ () => setIsModalOpen( true ) }
						className="eg-piwigo-trigger-btn eg-piwigo-trigger-btn--featured"
					>
						{ __( 'Set Piwigo Featured Image', 'eg-media' ) as string }
					</Button>

					{ isModalOpen && (
						<PiwigoPickerModal
							postId={ postId }
							endpoint="/eg-media/v1/piwigo/import-featured-image"
							loadingLabel={ __( "Téléchargement et association de l'image…", 'eg-media' ) as string }
							onClose={ () => setIsModalOpen( false ) }
							onSuccess={ ( response: PiwigoImportResponse ) => {
								if ( response && response.attachment_id && editPost ) {
									editPost( { featured_media: response.attachment_id } );
								}
							} }
						/>
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

			const postId = useSelect( ( select: any ) => {
				return ( select( 'core/editor' )?.getCurrentPostId() as number | undefined ) || 0;
			}, [] );

			const currentUrl = ( props.attributes as { url?: string } | undefined )?.url;

			// Injecter le bouton "Sélectionner depuis Piwigo" dans le placeholder du bloc Image.
			useEffect( () => {
				if ( ! currentUrl && props.clientId ) {
					const timer = setTimeout( () => {
						const iframe = document.querySelector(
							'iframe[name="editor-canvas"]'
						) as HTMLIFrameElement | null;
						const doc = iframe?.contentDocument || document;
						const blockContainer =
							doc.getElementById( `block-${ props.clientId }` ) ||
							doc.querySelector( `[data-block="${ props.clientId }"]` );
						if ( blockContainer ) {
							const fieldset = blockContainer.querySelector(
								'.components-placeholder__fieldset'
							);
							if ( fieldset && ! fieldset.querySelector( '.eg-piwigo-placeholder-button' ) ) {
								const btn = document.createElement( 'button' );
								btn.className =
									'components-button is-secondary is-next-40px-default-size block-editor-media-placeholder__button eg-piwigo-placeholder-button';
								btn.type = 'button';
								btn.innerText = __( 'Sélectionner depuis Piwigo', 'eg-media' ) as string;
								btn.onclick = ( e ) => {
									e.preventDefault();
									e.stopPropagation();
									setIsModalOpen( true );
								};
								fieldset.appendChild( btn );
							}
						}
					}, 50 );
					return () => clearTimeout( timer );
				}
			}, [ currentUrl, props.clientId ] );

			return (
				<Fragment>
					<OriginalComponent { ...props } />

					<InspectorControls>
						<PanelBody title={ __( 'Intégration Piwigo', 'eg-media' ) as string } initialOpen={ true }>
							<Button
								variant="secondary"
								onClick={ () => setIsModalOpen( true ) }
								className="eg-piwigo-trigger-btn"
							>
								{ __( 'Sélectionner depuis Piwigo', 'eg-media' ) as string }
							</Button>
						</PanelBody>
					</InspectorControls>

					{ isModalOpen && (
						<PiwigoPickerModal
							postId={ postId }
							endpoint="/eg-media/v1/piwigo/import-image"
							loadingLabel={ __( "Téléchargement et insertion de l'image…", 'eg-media' ) as string }
							onClose={ () => setIsModalOpen( false ) }
							onSuccess={ ( response: PiwigoImportResponse ) => {
								if ( response && response.attachment_id && response.url && props.setAttributes ) {
									props.setAttributes( {
										id: response.attachment_id,
										url: response.url,
										alt: response.alt || '',
									} );
								}
							} }
						/>
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
