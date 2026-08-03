declare module '@wordpress/block-editor' {
	type Block = import('@wordpress/blocks').Block;
	type ComponentType< Props = Record< string, never > > =
		import('react').ComponentType< Props >;
	type HTMLAttributes< Element > = import('react').HTMLAttributes< Element >;
	type ReactNode = import('react').ReactNode;

	interface BlockEditorProviderProps {
		children: ReactNode;
		onChange?: ( blocks: Block[] ) => void;
		onInput?: ( blocks: Block[] ) => void;
		settings?: {
			canLockBlocks?: boolean;
			focusMode?: boolean;
			hasFixedToolbar?: boolean;
			templateLock?: 'all' | 'contentOnly' | 'insert' | false;
		};
		value?: Block[];
	}

	interface BlockCanvasProps {
		children?: ReactNode;
		height?: string;
		styles?: Array< Record< string, unknown > >;
	}

	interface InnerBlocksProps {
		renderAppender?: ComponentType;
	}

	interface InspectorControlsProps {
		children?: ReactNode;
		group?: string;
	}

	interface UseBlockProps {
		(
			props?: HTMLAttributes< HTMLDivElement >
		): HTMLAttributes< HTMLDivElement >;
		save: (
			props?: HTMLAttributes< HTMLDivElement >
		) => HTMLAttributes< HTMLDivElement >;
	}

	export const BlockCanvas: ComponentType< BlockCanvasProps >;
	export const BlockEditorProvider: ComponentType< BlockEditorProviderProps >;
	export const BlockInspector: ComponentType;
	export const InnerBlocks: ComponentType< InnerBlocksProps > & {
		ButtonBlockAppender: ComponentType;
		Content: ComponentType;
	};
	export const InspectorControls: ComponentType< InspectorControlsProps >;
	export const useBlockProps: UseBlockProps;
}
