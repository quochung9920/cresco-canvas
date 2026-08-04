import type { PreviewDevice } from '../../shared/previewDevices';

export type ContainerAlignment =
	| 'center'
	| 'flex-end'
	| 'flex-start'
	| 'stretch';
export type ContainerDirection = 'column' | 'row';
export type ContainerJustification =
	| 'center'
	| 'flex-end'
	| 'flex-start'
	| 'space-between';
export type ContainerLayoutMode = 'block' | 'flex' | 'grid';
export type ContainerWrap = 'nowrap' | 'wrap';
export type ResponsivePreviewDevice = Exclude< PreviewDevice, 'desktop' >;

export interface ResolvedContainerLayout {
	align: ContainerAlignment;
	columns: number;
	direction: ContainerDirection;
	gap: number;
	justify: ContainerJustification;
	layoutMode: ContainerLayoutMode;
	maxWidth: number;
	paddingBottom: number;
	paddingLeft: number;
	paddingRight: number;
	paddingTop: number;
	wrap: ContainerWrap;
}

export type ContainerResponsiveOverrides = Partial<
	Record< ResponsivePreviewDevice, Partial< ResolvedContainerLayout > >
>;

export interface ContainerAttributes extends Record< string, unknown > {
	align: ContainerAlignment;
	background: string;
	columns?: number;
	direction: ContainerDirection;
	gap: number;
	justify: ContainerJustification;
	layoutMode: ContainerLayoutMode;
	maxWidth: number;
	paddingBottom: number;
	paddingLeft: number;
	paddingRight: number;
	paddingTop: number;
	responsive?: ContainerResponsiveOverrides;
	wrap?: ContainerWrap;
}
