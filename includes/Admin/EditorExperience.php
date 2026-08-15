<?php
/**
 * Retired Editor Experience v2 compatibility service.
 *
 * Cresco Studio is now the only admin editor presentation. The class is kept
 * for binary/backward compatibility with integrations that reference it, but
 * it no longer registers browser assets or mounts a second editor layer.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class EditorExperience {
	const VERSION = '2.0.0';

	/**
	 * Intentionally no-op.
	 *
	 * Historical Editor Experience assets depended on the standalone editor and
	 * could be reintroduced through WordPress dependency resolution after Studio
	 * claimed ownership. Keeping registration empty guarantees a single runtime.
	 */
	public function register() {
		return;
	}
}
