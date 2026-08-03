import { registerBlockType, type BlockConfiguration } from '@wordpress/blocks';

import metadata from '../../../blocks/container/block.json';

import { Edit } from './Edit';
import { save } from './save';
import type { ContainerAttributes } from './types';

registerBlockType< ContainerAttributes >(
	metadata as unknown as BlockConfiguration< ContainerAttributes >,
	{
		edit: Edit,
		save,
	}
);
