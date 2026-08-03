import { InnerBlocks, useBlockProps } from '@wordpress/block-editor';
import type { BlockSaveProps } from '@wordpress/blocks';

import { styleFromAttributes } from './styles';
import type { ContainerAttributes } from './types';

export function save( { attributes }: BlockSaveProps< ContainerAttributes > ) {
	const blockProps = useBlockProps.save( {
		className: 'cc-container',
		style: styleFromAttributes( attributes ),
	} );
	return (
		<div { ...blockProps }>
			<InnerBlocks.Content />
		</div>
	);
}
