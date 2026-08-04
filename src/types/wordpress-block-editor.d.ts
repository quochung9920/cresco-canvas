declare module '@wordpress/block-editor' {
	type ComponentType< Props = Record< string, never > > =
		import('react').ComponentType< Props >;
	type HTMLAttributes< Element > = import('react').HTMLAttributes< Element >;
	type ReactNode = import('react').ReactNode;

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

	export const InnerBlocks: ComponentType< InnerBlocksProps > & {
		ButtonBlockAppender: ComponentType;
		Content: ComponentType;
	};
	export const InspectorControls: ComponentType< InspectorControlsProps >;
	export const useBlockProps: UseBlockProps;
}
