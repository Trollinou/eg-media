/**
 * Types pour l'Interactivity API du bloc Visionneuse de Galerie.
 */

import type { GalleryImage } from './gallery';
import type { ViewerLayout } from './viewer';

export interface ViewerContext {
	currentIndex: number;
	isFullscreen: boolean;
	isSlideshowRunning: boolean;
	loadedImagesCount: number;
	images: GalleryImage[];
	layout: ViewerLayout;
	slideshow: boolean;
	tempo: number;
	limit: number;
}
