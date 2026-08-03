import { createBlock, type Block } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';

export function createElementBlock( name: string ): Block {
	if ( name === 'core/buttons' ) {
		return createBlock( 'core/buttons', {}, [
			createBlock( 'core/button', {
				text: __( 'Button', 'cresco-canvas' ),
			} ),
		] );
	}

	if ( name === 'cresco/container' ) {
		return createBlock( name, {}, [
			createBlock( 'core/paragraph', {
				content: __( 'Start building here…', 'cresco-canvas' ),
			} ),
		] );
	}

	return createBlock( name );
}
