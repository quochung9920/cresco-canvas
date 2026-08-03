export interface ContainerAttributes extends Record< string, unknown > {
	align: 'center' | 'flex-end' | 'flex-start' | 'stretch';
	background: string;
	direction: 'column' | 'row';
	gap: number;
	justify: 'center' | 'flex-end' | 'flex-start' | 'space-between';
	layoutMode: 'block' | 'flex' | 'grid';
	maxWidth: number;
	paddingBottom: number;
	paddingLeft: number;
	paddingRight: number;
	paddingTop: number;
}
