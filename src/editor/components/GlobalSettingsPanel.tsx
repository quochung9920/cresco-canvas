import {
	Button,
	SelectControl,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import type { ChangeEvent } from 'react';

import type { GlobalSettings } from '../types';

interface Props {
	onChange: ( settings: GlobalSettings ) => void;
	onSave: () => void;
	settings: GlobalSettings;
}

interface ColorFieldProps {
	field: 'primary' | 'text' | 'background';
	label: string;
	onChange: ( settings: GlobalSettings ) => void;
	settings: GlobalSettings;
}

function ColorField( { field, label, onChange, settings }: ColorFieldProps ) {
	const inputId = `cc-color-${ field }`;

	return (
		<label className="cc-color-field" htmlFor={ inputId }>
			{ label }
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

export function GlobalSettingsPanel( { onChange, onSave, settings }: Props ) {
	return (
		<>
			<div className="cc-panel-header">
				{ __( 'Global Settings', 'cresco-canvas' ) }
			</div>
			<div className="cc-panel-body cc-settings-grid">
				<ColorField
					field="primary"
					label={ __( 'Primary color', 'cresco-canvas' ) }
					onChange={ onChange }
					settings={ settings }
				/>
				<ColorField
					field="text"
					label={ __( 'Text color', 'cresco-canvas' ) }
					onChange={ onChange }
					settings={ settings }
				/>
				<ColorField
					field="background"
					label={ __( 'Background color', 'cresco-canvas' ) }
					onChange={ onChange }
					settings={ settings }
				/>
				<TextControl
					label={ __( 'Boxed maximum width', 'cresco-canvas' ) }
					min={ 960 }
					onChange={ ( value ) =>
						onChange( {
							...settings,
							containerMax: Number( value ),
						} )
					}
					type="number"
					value={ settings.containerMax }
				/>
				<TextControl
					label={ __( 'Content maximum width', 'cresco-canvas' ) }
					min={ 640 }
					onChange={ ( value ) =>
						onChange( { ...settings, contentMax: Number( value ) } )
					}
					type="number"
					value={ settings.contentMax }
				/>
				<TextControl
					label={ __( 'Global radius', 'cresco-canvas' ) }
					min={ 0 }
					onChange={ ( value ) =>
						onChange( { ...settings, radius: Number( value ) } )
					}
					type="number"
					value={ settings.radius }
				/>
				<SelectControl
					label={ __( 'Default Page editor', 'cresco-canvas' ) }
					onChange={ ( value ) =>
						onChange( {
							...settings,
							editorPreference:
								value as GlobalSettings[ 'editorPreference' ],
						} )
					}
					options={ [
						{
							label: __(
								'Remember last choice',
								'cresco-canvas'
							),
							value: 'remember',
						},
						{
							label: __( 'Cresco Canvas', 'cresco-canvas' ),
							value: 'canvas',
						},
						{
							label: __( 'WordPress Editor', 'cresco-canvas' ),
							value: 'wordpress',
						},
					] }
					value={ settings.editorPreference }
				/>
				<ToggleControl
					checked={ settings.removeDataOnUninstall }
					help={ __(
						'Page content is never deleted. This removes only Cresco settings and metadata during uninstall.',
						'cresco-canvas'
					) }
					label={ __(
						'Remove plugin data on uninstall',
						'cresco-canvas'
					) }
					onChange={ ( value ) =>
						onChange( {
							...settings,
							removeDataOnUninstall: value,
						} )
					}
				/>
				<Button onClick={ onSave } variant="primary">
					{ __( 'Save Global Settings', 'cresco-canvas' ) }
				</Button>
			</div>
		</>
	);
}
