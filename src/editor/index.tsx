import { registerPlugin } from '@wordpress/plugins';

import '../styles/admin.scss';
import { SettingsSidebar } from './components/SettingsSidebar';

registerPlugin( 'cresco-canvas', {
	icon: 'layout',
	render: SettingsSidebar,
} );
