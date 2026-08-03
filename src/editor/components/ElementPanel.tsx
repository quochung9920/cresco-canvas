import { SearchControl } from '@wordpress/components';
import { useMemo, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import { ELEMENTS } from '../constants';

interface Props {
	disabled: boolean;
	onAdd: ( name: string ) => void;
}

export function ElementPanel( { disabled, onAdd }: Props ) {
	const [ search, setSearch ] = useState( '' );
	const elements = useMemo( () => {
		const normalized = search.trim().toLocaleLowerCase();
		return normalized
			? ELEMENTS.filter( ( item ) =>
					`${ item.label } ${ item.category }`
						.toLocaleLowerCase()
						.includes( normalized )
			  )
			: ELEMENTS;
	}, [ search ] );

	return (
		<aside
			aria-label={ __( 'Add blocks', 'cresco-canvas' ) }
			className="cc-panel"
		>
			<div className="cc-panel-header">
				{ __( 'Add', 'cresco-canvas' ) }
			</div>
			<div className="cc-panel-body">
				<SearchControl
					label={ __( 'Search blocks', 'cresco-canvas' ) }
					onChange={ setSearch }
					value={ search }
				/>
				<div className="cc-element-grid">
					{ elements.map( ( item ) => (
						<button
							className="cc-element"
							disabled={ disabled }
							key={ item.name }
							onClick={ () => onAdd( item.name ) }
							type="button"
						>
							<span>{ item.label }</span>
							<small>{ item.category }</small>
						</button>
					) ) }
				</div>
			</div>
		</aside>
	);
}
