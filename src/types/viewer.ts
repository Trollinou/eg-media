/**
 * Types pour les attributs et la configuration du bloc Visionneuse de Galerie.
 */

export type GallerySource = 'local' | 'piwigo';
export type ViewerLayout = 'viewer' | 'justified';
export type ImageResolution = 'full' | 'large' | 'medium' | 'thumbnail';
export type SortByField = 'date' | 'name';
export type SortOrder = 'ASC' | 'DESC';

export interface ViewerAttributes {
	galleryId?: number;
	gallerySource?: GallerySource;
	resolution?: ImageResolution;
	sortBy?: SortByField;
	sortOrder?: SortOrder;
	slideshow?: boolean;
	tempo?: number;
	layout?: ViewerLayout;
	imagesPerPage?: number;
}
