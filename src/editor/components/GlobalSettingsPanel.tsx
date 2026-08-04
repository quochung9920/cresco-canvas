import {
	Button,
	PanelBody,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import type { ChangeEvent } from 'react';

import type { GlobalSettings } from '../types';

interface Props {
	onChange: ( settings: GlobalSettings ) => void;
	onSave: () => void;
	saving: boolean;
	settings: GlobalSettings;
}

interface ColorFieldProps {
	field: 'background' | 'primary' | 'text';
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

export function GlobalSettingsPanel( {
	onChange,
	onSave,
	saving,
	settings,
}: Props ) {
	return (
		<>
			<PanelBody
				initialOpen={ false }
				title={ __( 'Global design', 'cresco-canvas' ) }
			>
				<div className="cc-settings-grid">
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
						label={ __( 'Page background', 'cresco-canvas' ) }
						onChange={ onChange }
						settings={ settings }
					/>
					<TextControl
						label={ __( 'Global radius', 'cresco-canvas' ) }
						max={ 80 }
						min={ 0 }
						onChange={ ( value ) =>
							onChange( {
								...settings,
								radius: Number( value ),
							} )
						}
						type="number"
						value={ settings.radius }
					/>
					<TextControl
						label={ __( 'Font family stack', 'cresco-canvas' ) }
						onChange={ ( fontFamily ) =>
							onChange( { ...settings, fontFamily } )
						}
						value={ settings.fontFamily }
					/>
					<Button
						disabled={ saving }
						isBusy={ saving }
						onClick={ onSave }
						variant="primary"
					>
						{ saving
							? __( 'Saving…', 'cresco-canvas' )
							: __( 'Save global design', 'cresco-canvas' ) }
					</Button>
				</div>
			</PanelBody>
			<PanelBody
				initialOpen={ false }
				title={ __( 'Data and uninstall', 'cresco-canvas' ) }
			>
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
			</PanelBody>
		</>
	);
}
