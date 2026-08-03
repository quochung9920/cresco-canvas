import type { BlockEditProps } from '@wordpress/blocks';
import {
	InnerBlocks,
	InspectorControls,
	useBlockProps,
} from '@wordpress/block-editor';
import {
	ColorPalette,
	PanelBody,
	RangeControl,
	SelectControl,
} from '@wordpress/components';
import { Fragment } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import { styleFromAttributes } from './styles';
import type { ContainerAttributes } from './types';

const SIDES = [
	{ key: 'paddingTop', label: __( 'Padding top', 'cresco-canvas' ) },
	{ key: 'paddingRight', label: __( 'Padding right', 'cresco-canvas' ) },
	{ key: 'paddingBottom', label: __( 'Padding bottom', 'cresco-canvas' ) },
	{ key: 'paddingLeft', label: __( 'Padding left', 'cresco-canvas' ) },
] as const;

export function Edit( {
	attributes,
	setAttributes,
}: BlockEditProps< ContainerAttributes > ) {
	const blockProps = useBlockProps( {
		className: 'cc-container',
		style: styleFromAttributes( attributes ),
	} );

	return (
		<Fragment>
			<InspectorControls>
				<PanelBody
					initialOpen
					title={ __( 'Layout', 'cresco-canvas' ) }
				>
					<SelectControl
						label={ __( 'Layout mode', 'cresco-canvas' ) }
						onChange={ ( layoutMode ) =>
							setAttributes( {
								layoutMode:
									layoutMode as ContainerAttributes[ 'layoutMode' ],
							} )
						}
						options={ [
							{
								label: __( 'Flex', 'cresco-canvas' ),
								value: 'flex',
							},
							{
								label: __( 'Grid', 'cresco-canvas' ),
								value: 'grid',
							},
							{
								label: __( 'Block', 'cresco-canvas' ),
								value: 'block',
							},
						] }
						value={ attributes.layoutMode }
					/>
					{ attributes.layoutMode === 'flex' && (
						<SelectControl
							label={ __( 'Direction', 'cresco-canvas' ) }
							onChange={ ( direction ) =>
								setAttributes( {
									direction:
										direction as ContainerAttributes[ 'direction' ],
								} )
							}
							options={ [
								{
									label: __( 'Column', 'cresco-canvas' ),
									value: 'column',
								},
								{
									label: __( 'Row', 'cresco-canvas' ),
									value: 'row',
								},
							] }
							value={ attributes.direction }
						/>
					) }
					{ attributes.layoutMode !== 'block' && (
						<>
							<SelectControl
								label={ __( 'Justification', 'cresco-canvas' ) }
								onChange={ ( justify ) =>
									setAttributes( {
										justify:
											justify as ContainerAttributes[ 'justify' ],
									} )
								}
								options={ [
									{
										label: __( 'Start', 'cresco-canvas' ),
										value: 'flex-start',
									},
									{
										label: __( 'Center', 'cresco-canvas' ),
										value: 'center',
									},
									{
										label: __( 'End', 'cresco-canvas' ),
										value: 'flex-end',
									},
									{
										label: __(
											'Space between',
											'cresco-canvas'
										),
										value: 'space-between',
									},
								] }
								value={ attributes.justify }
							/>
							<SelectControl
								label={ __( 'Alignment', 'cresco-canvas' ) }
								onChange={ ( align ) =>
									setAttributes( {
										align: align as ContainerAttributes[ 'align' ],
									} )
								}
								options={ [
									{
										label: __( 'Stretch', 'cresco-canvas' ),
										value: 'stretch',
									},
									{
										label: __( 'Start', 'cresco-canvas' ),
										value: 'flex-start',
									},
									{
										label: __( 'Center', 'cresco-canvas' ),
										value: 'center',
									},
									{
										label: __( 'End', 'cresco-canvas' ),
										value: 'flex-end',
									},
								] }
								value={ attributes.align }
							/>
							<RangeControl
								label={ __( 'Gap', 'cresco-canvas' ) }
								max={ 160 }
								min={ 0 }
								onChange={ ( gap ) =>
									setAttributes( { gap: gap ?? 0 } )
								}
								value={ attributes.gap }
							/>
						</>
					) }
					<RangeControl
						label={ __( 'Maximum width', 'cresco-canvas' ) }
						max={ 2560 }
						min={ 320 }
						onChange={ ( maxWidth ) =>
							setAttributes( { maxWidth: maxWidth ?? 1200 } )
						}
						value={ attributes.maxWidth }
					/>
				</PanelBody>
				<PanelBody
					initialOpen={ false }
					title={ __( 'Spacing', 'cresco-canvas' ) }
				>
					{ SIDES.map( ( side ) => (
						<RangeControl
							key={ side.key }
							label={ side.label }
							max={ 240 }
							min={ 0 }
							onChange={ ( value ) =>
								setAttributes( { [ side.key ]: value ?? 0 } )
							}
							value={ attributes[ side.key ] }
						/>
					) ) }
				</PanelBody>
				<PanelBody
					initialOpen={ false }
					title={ __( 'Style', 'cresco-canvas' ) }
				>
					<p>{ __( 'Background', 'cresco-canvas' ) }</p>
					<ColorPalette
						onChange={ ( background ) =>
							setAttributes( { background: background || '' } )
						}
						value={ attributes.background }
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<InnerBlocks
					renderAppender={ InnerBlocks.ButtonBlockAppender }
				/>
			</div>
		</Fragment>
	);
}
