import { BlockInspector } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';

import type { GlobalSettings } from '../types';
import { GlobalSettingsPanel } from './GlobalSettingsPanel';

interface Props {
	globalSettings: GlobalSettings | null;
	onGlobalChange: ( settings: GlobalSettings ) => void;
	onGlobalSave: () => void;
	settingsOpen: boolean;
}

export function InspectorPanel( {
	globalSettings,
	onGlobalChange,
	onGlobalSave,
	settingsOpen,
}: Props ) {
	return (
		<aside
			aria-label={ __( 'Block settings', 'cresco-canvas' ) }
			className="cc-panel cc-panel-right"
		>
			{ settingsOpen && globalSettings ? (
				<GlobalSettingsPanel
					onChange={ onGlobalChange }
					onSave={ onGlobalSave }
					settings={ globalSettings }
				/>
			) : (
				<>
					<div className="cc-panel-header">
						{ __( 'Block Settings', 'cresco-canvas' ) }
					</div>
					<div className="cc-panel-body">
						<BlockInspector />
					</div>
				</>
			) }
		</aside>
	);
}
