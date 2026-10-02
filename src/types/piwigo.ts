/**
 * Types pour l'intégration Piwigo (Albums & Images distantes).
 */

export interface PiwigoDerivative {
	url: string;
	width?: number;
	height?: number;
}

export interface PiwigoDerivatives {
	square?: PiwigoDerivative;
	thumb?: PiwigoDerivative;
	small?: PiwigoDerivative;
	medium?: PiwigoDerivative;
	large?: PiwigoDerivative;
	xlarge?: PiwigoDerivative;
	xxlarge?: PiwigoDerivative;
	[ key: string ]: PiwigoDerivative | undefined;
}

export interface PiwigoImage {
	id: number;
	name?: string;
	file?: string;
	element_url?: string;
	derivatives?: PiwigoDerivatives;
	width?: number;
	height?: number;
}

export interface PiwigoAlbum {
	id: number | string;
	name: string;
	description?: string;
	nb_images?: number;
}

export interface PiwigoImportResponse {
	attachment_id: number;
	url: string;
	alt?: string;
	title?: string;
}
