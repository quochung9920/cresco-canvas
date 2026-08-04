import { Button, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import { DEVICES } from '../constants';
import type { Device, PageRecord } from '../types';

interface Props {
	brand: string;
	device: Device;
	dirty: boolean;
	nativeEditUrl: string;
	notice: string;
	onDeviceChange: ( device: Device ) => void;
	onGlobalSettings: () => void;
	onSave: () => void;
	onTitleChange: ( title: string ) => void;
	page: PageRecord | null;
	pagesUrl: string;
	saving: boolean;
	showGlobalSettings: boolean;
}

export function TopBar( props: Props ) {
	const {
		brand,
		device,
		dirty,
		nativeEditUrl,
		notice,
		onDeviceChange,
		onGlobalSettings,
		onSave,
		onTitleChange,
		page,
		pagesUrl,
		saving,
		showGlobalSettings,
	} = props;

	return (
		<header className="cc-topbar">
			<Button href={ pagesUrl } variant="tertiary">
				{ __( 'Back to Pages', 'cresco-canvas' ) }
			</Button>
			<div className="cc-brand">{ brand }</div>
			{ page && (
				<TextControl
					className="cc-page-title"
					hideLabelFromVision
					label={ __( 'Page title', 'cresco-canvas' ) }
					onChange={ onTitleChange }
					value={ page.title }
				/>
			) }
			<div
				aria-label={ __( 'Preview device', 'cresco-canvas' ) }
				className="cc-device-switcher"
				role="group"
			>
				{ DEVICES.map( ( item ) => (
					<Button
						aria-pressed={ device === item }
						key={ item }
						onClick={ () => onDeviceChange( item ) }
						variant={ device === item ? 'primary' : 'secondary' }
					>
						{ item.toUpperCase() }
					</Button>
				) ) }
			</div>
			<div className="cc-spacer" />
			<span aria-live="polite" className="cc-status">
				{ saving
					? __( 'Saving…', 'cresco-canvas' )
					: notice ||
					  ( dirty
							? __( 'Unsaved changes', 'cresco-canvas' )
							: '' ) }
			</span>
			{ showGlobalSettings && (
				<Button onClick={ onGlobalSettings } variant="secondary">
					{ __( 'Global Settings', 'cresco-canvas' ) }
				</Button>
			) }
			{ page && nativeEditUrl && (
				<Button href={ nativeEditUrl } variant="secondary">
					{ __( 'WordPress Editor', 'cresco-canvas' ) }
				</Button>
			) }
			{ page?.preview && (
				<Button
					href={ page.preview }
					rel="noopener noreferrer"
					target="_blank"
					variant="secondary"
				>
					{ __( 'Preview', 'cresco-canvas' ) }
				</Button>
			) }
			{ page && (
				<Button
					disabled={ ! dirty || saving }
					isBusy={ saving }
					onClick={ onSave }
					variant="primary"
				>
					{ saving
						? __( 'Saving', 'cresco-canvas' )
						: __( 'Save', 'cresco-canvas' ) }
				</Button>
			) }
		</header>
	);
}
