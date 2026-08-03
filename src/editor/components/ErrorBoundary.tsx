import { Component } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import type { ErrorInfo, ReactNode } from 'react';

interface Props {
	children: ReactNode;
	nativeEditUrl: string;
	safeModeUrl: string;
}

interface State {
	hasError: boolean;
}

export class ErrorBoundary extends Component< Props, State > {
	public override state: State = { hasError: false };

	public static getDerivedStateFromError(): State {
		return { hasError: true };
	}

	public override componentDidCatch( error: Error, info: ErrorInfo ): void {
		// The recovery UI is intentional. Diagnostics with redaction arrive in 1.0.
		void error;
		void info;
	}

	public override render(): ReactNode {
		if ( ! this.state.hasError ) {
			return this.props.children;
		}

		return (
			<div className="cc-recovery" role="alert">
				<h1>
					{ __(
						'Cresco Canvas could not finish loading',
						'cresco-canvas'
					) }
				</h1>
				<p>
					{ __(
						'Your Page was not changed. Use Safe Mode or the WordPress Editor to recover.',
						'cresco-canvas'
					) }
				</p>
				<div className="cc-recovery-actions">
					{ this.props.safeModeUrl && (
						<a className="button" href={ this.props.safeModeUrl }>
							{ __( 'Open Safe Mode', 'cresco-canvas' ) }
						</a>
					) }
					{ this.props.nativeEditUrl && (
						<a
							className="button button-primary"
							href={ this.props.nativeEditUrl }
						>
							{ __( 'Open WordPress Editor', 'cresco-canvas' ) }
						</a>
					) }
				</div>
			</div>
		);
	}
}
