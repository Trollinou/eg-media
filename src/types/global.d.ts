/**
 * Déclarations globales pour les assets et modules tiers.
 */

declare module '*.scss' {
	const content: Record<string, string>;
	export default content;
}

declare module '*.json' {
	const value: Record<string, any>;
	export default value;
}

declare module '@wordpress/interactivity' {
	export function store(
		namespace: string,
		storeDefinition: {
			state?: Record<string, any>;
			actions?: Record<string, ( ...args: any[] ) => void>;
			callbacks?: Record<string, ( ...args: any[] ) => void>;
		}
	): any;
	export function getContext<T = Record<string, any>>(): T;
	export function getElement(): { ref: HTMLElement; attributes: Record<string, any> };
}

interface Window {
	egMediaUploadData?: {
		nonce?: string;
		galleries?: Array<{ term_id: number | string; name: string; slug?: string }>;
		[ key: string ]: any;
	};
	wp?: any;
	uploader?: any;
	_?: any;
}

declare const ajaxurl: string;
declare const jQuery: any;
declare const wp: any;
declare const uploader: any;
declare const _: any;
