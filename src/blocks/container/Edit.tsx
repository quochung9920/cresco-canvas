import type { BlockEditProps } from '@wordpress/blocks';
import {
	InnerBlocks,
	InspectorControls,
	useBlockProps,
} from '@wordpress/block-editor';
import {
	Button,
	ColorPalette,
	PanelBody,
	RangeControl,
	SelectControl,
} from '@wordpress/components';
import { Fragment, useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

import {
	PREVIEW_DEVICE_EVENT,
	readPreviewDevice,
	type PreviewDevice,
	type PreviewDeviceEventDetail,
} from '../../shared/previewDevices';
import {
	resetResponsiveDevice,
	resolveContainerLayout,
	updateResponsiveOverride,
} from './responsive';
import { styleFromAttributes } from './styles';
import type {
	ContainerAttributes,
	ResolvedContainerLayout,
	ResponsivePreviewDevice,
} from './types';

const SIDES = [
	{ key: 'paddingTop', label: __( 'Padding top', 'cresco-canvas' ) },
	{ key: 'paddingRight', label: __( 'Padding right', 'cresco-canvas' ) },
	{ key: 'paddingBottom', label: __( 'Padding bottom', 'cresco-canvas' ) },
	{ key: 'paddingLeft', label: __( 'Padding left', 'cresco-canvas' ) },
] as const;

const DEVICE_LABELS: Record< PreviewDevice, string > = {
	'4k': __( '4K', 'cresco-canvas' ),
	desktop: __( 'Desktop', 'cresco-canvas' ),
	laptop: __( 'Laptop', 'cresco-canvas' ),
	tablet: __( 'Tablet', 'cresco-canvas' ),
	mobile: __( 'Mobile', 'cresco-canvas' ),
};

export function Edit( {
	attributes,
	setAttributes,
}: BlockEditProps< ContainerAttributes > ) {
	const [ device, setDevice ] = useState< PreviewDevice >( readPreviewDevice );
	const resolved = resolveContainerLayout( attributes, device );
	const blockProps = useBlockProps( {
		className: 'cc-container',
		style: styleFromAttributes( attributes ),
	} );

	useEffect( () => {
		const onDeviceChange = ( event: Event ) => {
			const detail = ( event as CustomEvent< PreviewDeviceEventDetail > )
				.detail;
			if ( detail?.device ) {
				setDevice( detail.device );
			}
		};

		window.addEventListener( PREVIEW_DEVICE_EVENT, onDeviceChange );
		return () =>
			window.removeEventListener( PREVIEW_DEVICE_EVENT, onDeviceChange );
	}, [] );

	function updateLayout< Key extends keyof ResolvedContainerLayout >(
		key: Key,
		value: ResolvedContainerLayout[ Key ]
	) {
		if ( device === 'desktop' ) {
			setAttributes( { [ key ]: value } );
			return;
		}

		setAttributes( {
			responsive: updateResponsiveOverride(
				attributes.responsive,
				device,
				key,
				value
			),
		} );
	}

	function resetCurrentDevice() {
		if ( device === 'desktop' ) {
			return;
		}
		setAttributes( {
			responsive: resetResponsiveDevice(
				attributes.responsive,
				device as ResponsivePreviewDevice
			),
		} );
	}

	const hasCurrentOverride =
		device !== 'desktop' && Boolean( attributes.responsive?.[ device ] );

	return (
		<Fragment>
			<InspectorControls>
				<PanelBody
					initialOpen
					title={ sprintf(
						/* translators: %s: responsive preview device name. */
						__( 'Layout · %s', 'cresco-canvas' ),
						DEVICE_LABELS[ device ]
					) }
				>
					<p className="cc-responsive-context">
						{ device === 'desktop'
							? __(
									'Desktop values are the base inherited by every smaller device.',
									'cresco-canvas'
							  )
							: __(
									'Only changed values are stored for this device; all other values inherit automatically.',
									'cresco-canvas'
							  ) }
					</p>
					<SelectControl
						label={ __( 'Layout mode', 'cresco-canvas' ) }
						onChange={ ( layoutMode: string ) =>
							updateLayout(
								'layoutMode',
								layoutMode as ResolvedContainerLayout[ 'layoutMode' ]
							)
						}
						options={ [
							{ label: __( 'Flex', 'cresco-canvas' ), value: 'flex' },
							{ label: __( 'Grid', 'cresco-canvas' ), value: 'grid' },
							{ label: __( 'Block', 'cresco-canvas' ), value: 'block' },
						] }
						value={ resolved.layoutMode }
					/>
					{ resolved.layoutMode === 'flex' && (
						<>
							<SelectControl
								label={ __( 'Direction', 'cresco-canvas' ) }
								onChange={ ( direction: string ) =>
									updateLayout(
										'direction',
										direction as ResolvedContainerLayout[ 'direction' ]
									)
								}
								options={ [
									{ label: __( 'Column', 'cresco-canvas' ), value: 'column' },
									{ label: __( 'Row', 'cresco-canvas' ), value: 'row' },
								] }
								value={ resolved.direction }
							/>
							<SelectControl
								label={ __( 'Wrapping', 'cresco-canvas' ) }
								onChange={ ( wrap: string ) =>
									updateLayout(
										'wrap',
										wrap as ResolvedContainerLayout[ 'wrap' ]
									)
								}
								options={ [
									{ label: __( 'No wrap', 'cresco-canvas' ), value: 'nowrap' },
									{ label: __( 'Wrap', 'cresco-canvas' ), value: 'wrap' },
								] }
								value={ resolved.wrap }
							/>
						</>
					) }
					{ resolved.layoutMode === 'grid' && (
						<RangeControl
							label={ __( 'Columns', 'cresco-canvas' ) }
							max={ 12 }
							min={ 1 }
							onChange={ ( columns: number | undefined ) =>
								updateLayout( 'columns', columns ?? 3 )
							}
							value={ resolved.columns }
						/>
					) }
					{ resolved.layoutMode !== 'block' && (
						<>
							<SelectControl
								label={ __( 'Justification', 'cresco-canvas' ) }
								onChange={ ( justify: string ) =>
									updateLayout(
										'justify',
										justify as ResolvedContainerLayout[ 'justify' ]
									)
								}
								options={ [
									{ label: __( 'Start', 'cresco-canvas' ), value: 'flex-start' },
									{ label: __( 'Center', 'cresco-canvas' ), value: 'center' },
									{ label: __( 'End', 'cresco-canvas' ), value: 'flex-end' },
									{ label: __( 'Space between', 'cresco-canvas' ), value: 'space-between' },
								] }
								value={ resolved.justify }
							/>
							<SelectControl
								label={ __( 'Alignment', 'cresco-canvas' ) }
								onChange={ ( align: string ) =>
									updateLayout(
										'align',
										align as ResolvedContainerLayout[ 'align' ]
									)
								}
								options={ [
									{ label: __( 'Stretch', 'cresco-canvas' ), value: 'stretch' },
									{ label: __( 'Start', 'cresco-canvas' ), value: 'flex-start' },
									{ label: __( 'Center', 'cresco-canvas' ), value: 'center' },
									{ label: __( 'End', 'cresco-canvas' ), value: 'flex-end' },
								] }
								value={ resolved.align }
							/>
							<RangeControl
								label={ __( 'Gap', 'cresco-canvas' ) }
								max={ 240 }
								min={ 0 }
								onChange={ ( gap: number | undefined ) =>
									updateLayout( 'gap', gap ?? 0 )
								}
								value={ resolved.gap }
							/>
						</>
					) }
					<RangeControl
						label={ __( 'Maximum width', 'cresco-canvas' ) }
						max={ 3840 }
						min={ 320 }
						onChange={ ( maxWidth: number | undefined ) =>
							updateLayout( 'maxWidth', maxWidth ?? 1200 )
						}
						value={ resolved.maxWidth }
					/>
					{ device !== 'desktop' && (
						<Button
							disabled={ ! hasCurrentOverride }
							onClick={ resetCurrentDevice }
							variant="secondary"
						>
							{ __( 'Reset device overrides', 'cresco-canvas' ) }
						</Button>
					) }
				</PanelBody>
				<PanelBody
					initialOpen={ false }
					title={ sprintf(
						/* translators: %s: responsive preview device name. */
						__( 'Spacing · %s', 'cresco-canvas' ),
						DEVICE_LABELS[ device ]
					) }
				>
					{ SIDES.map( ( side ) => (
						<RangeControl
							key={ side.key }
							label={ side.label }
							max={ 400 }
							min={ 0 }
							onChange={ ( value: number | undefined ) =>
								updateLayout( side.key, value ?? 0 )
							}
							value={ resolved[ side.key ] }
						/>
					) ) }
				</PanelBody>
				<PanelBody
					initialOpen={ false }
					title={ __( 'Style', 'cresco-canvas' ) }
				>
					<p>{ __( 'Background color', 'cresco-canvas' ) }</p>
					<ColorPalette
						onChange={ ( background: string | undefined ) =>
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
