import {
	Button,
	Notice,
	PanelBody,
	TextControl,
	TextareaControl,
	ToggleControl,
} from '@wordpress/components';
import { useMemo, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import type { ChangeEvent } from 'react';

import type { GlobalSettings } from '../types';

interface Props {
	onChange: ( settings: GlobalSettings ) => void;
	onReset: () => Promise< void >;
	onSave: () => void;
	saving: boolean;
	settings: GlobalSettings;
}

interface ColorFieldProps {
	field: 'background' | 'muted' | 'primary' | 'text';
	label: string;
	onChange: ( settings: GlobalSettings ) => void;
	settings: GlobalSettings;
}

function ColorField( { field, label, onChange, settings }: ColorFieldProps ) {
	const inputId = `cc-color-${ field }`;

	return (
		<label className="cc-color-field" htmlFor={ inputId }>
			<span>{ label }</span>
			<input
				id={ inputId }
				type="color"
				value={ settings[ field ] }
				onChange={ ( event: ChangeEvent< HTMLInputElement > ) =>
					onChange( { ...settings, [ field ]: event.target.value } )
				}
			/>
		</label>
	);
}

function isImportedSettings( value: unknown ): value is GlobalSettings {
	if ( ! value || typeof value !== 'object' ) {
		return false;
	}

	const candidate = value as Record< string, unknown >;
	return (
		[ 'background', 'fontFamily', 'muted', 'primary', 'text' ].every(
			( key ) => typeof candidate[ key ] === 'string'
		) &&
		[ 'containerMax', 'contentMax', 'radius', 'schemaVersion' ].every(
			( key ) => typeof candidate[ key ] === 'number'
		) &&
		typeof candidate.removeDataOnUninstall === 'boolean'
	);
}

export function GlobalSettingsPanel( {
	onChange,
	onReset,
	onSave,
	saving,
	settings,
}: Props ) {
	const exported = useMemo(
		() => JSON.stringify( settings, null, 2 ),
		[ settings ]
	);
	const [ importValue, setImportValue ] = useState( '' );
	const [ importError, setImportError ] = useState( '' );
	const [ resetting, setResetting ] = useState( false );

	function importSettings() {
		setImportError( '' );
		try {
			const parsed: unknown = JSON.parse( importValue );
			if ( ! isImportedSettings( parsed ) ) {
				throw new Error(
					__( 'The JSON does not contain a complete Cresco design settings object.', 'cresco-canvas' )
				);
			}
			onChange( parsed );
			setImportValue( '' );
		} catch ( error ) {
			setImportError(
				error instanceof Error
					? error.message
					: __( 'The design settings JSON is invalid.', 'cresco-canvas' )
			);
		}
	}

	async function resetSettings() {
		if ( resetting ) {
			return;
		}
		setResetting( true );
		try {
			await onReset();
		} finally {
			setResetting( false );
		}
	}

	return (
		<>
			<PanelBody initialOpen={ false } title={ __( 'Global design', 'cresco-canvas' ) }>
				<div className="cc-settings-grid">
					<ColorField field="primary" label={ __( 'Primary color', 'cresco-canvas' ) } onChange={ onChange } settings={ settings } />
					<ColorField field="text" label={ __( 'Text color', 'cresco-canvas' ) } onChange={ onChange } settings={ settings } />
					<ColorField field="muted" label={ __( 'Muted text color', 'cresco-canvas' ) } onChange={ onChange } settings={ settings } />
					<ColorField field="background" label={ __( 'Page background', 'cresco-canvas' ) } onChange={ onChange } settings={ settings } />
					<TextControl
						label={ __( 'Container maximum width', 'cresco-canvas' ) }
						max={ 2560 }
						min={ 960 }
						onChange={ ( value ) => onChange( { ...settings, containerMax: Number( value ) } ) }
						type="number"
						value={ settings.containerMax }
					/>
					<TextControl
						label={ __( 'Content maximum width', 'cresco-canvas' ) }
						max={ settings.containerMax }
						min={ 640 }
						onChange={ ( value ) => onChange( { ...settings, contentMax: Number( value ) } ) }
						type="number"
						value={ settings.contentMax }
					/>
					<TextControl
						label={ __( 'Global radius', 'cresco-canvas' ) }
						max={ 80 }
						min={ 0 }
						onChange={ ( value ) => onChange( { ...settings, radius: Number( value ) } ) }
						type="number"
						value={ settings.radius }
					/>
					<TextControl
						label={ __( 'Font family stack', 'cresco-canvas' ) }
						onChange={ ( fontFamily ) => onChange( { ...settings, fontFamily } ) }
						value={ settings.fontFamily }
					/>
					<Button disabled={ saving } isBusy={ saving } onClick={ onSave } variant="primary">
						{ saving ? __( 'Saving…', 'cresco-canvas' ) : __( 'Save global design', 'cresco-canvas' ) }
					</Button>
				</div>
			</PanelBody>

			<PanelBody initialOpen={ false } title={ __( 'Import, export, and reset', 'cresco-canvas' ) }>
				<TextareaControl
					readOnly
					help={ __( 'Copy this JSON to move the current design settings to another Cresco installation.', 'cresco-canvas' ) }
					label={ __( 'Export design JSON', 'cresco-canvas' ) }
					rows={ 8 }
					value={ exported }
				/>
				{ importError && <Notice isDismissible onRemove={ () => setImportError( '' ) } status="error">{ importError }</Notice> }
				<TextareaControl
					help={ __( 'Imported values are previewed first. Use Save global design to persist them.', 'cresco-canvas' ) }
					label={ __( 'Import design JSON', 'cresco-canvas' ) }
					onChange={ setImportValue }
					rows={ 8 }
					value={ importValue }
				/>
				<Button disabled={ ! importValue.trim() } onClick={ importSettings } variant="secondary">
					{ __( 'Apply imported design', 'cresco-canvas' ) }
				</Button>
				<Button
					className="cc-reset-design"
					disabled={ resetting }
					isBusy={ resetting }
					onClick={ resetSettings }
					variant="tertiary"
				>
					{ __( 'Reset global design to defaults', 'cresco-canvas' ) }
				</Button>
			</PanelBody>

			<PanelBody initialOpen={ false } title={ __( 'Data and uninstall', 'cresco-canvas' ) }>
				<ToggleControl
					checked={ settings.removeDataOnUninstall }
					help={ __( 'Page content is never deleted. This removes only Cresco settings and metadata during uninstall.', 'cresco-canvas' ) }
					label={ __( 'Remove plugin data on uninstall', 'cresco-canvas' ) }
					onChange={ ( value ) => onChange( { ...settings, removeDataOnUninstall: value } ) }
				/>
			</PanelBody>
		</>
	);
}
