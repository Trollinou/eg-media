/**
 * Types pour les galeries locales WordPress.
 */

export interface GalleryImage {
	id: number;
	index: number;
	thumbSrc: string;
	fullSrc: string;
	alt: string;
	width: number;
	height: number;
	filename?: string;
	date?: string;
}

export interface GalleryTerm {
	id: number;
	name: string;
	slug: string;
	count?: number;
}
