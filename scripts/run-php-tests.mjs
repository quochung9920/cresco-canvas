import { access } from 'node:fs/promises';
import path from 'node:path';
import process from 'node:process';
import { spawnSync } from 'node:child_process';

const phpBinary = process.env.CRESCO_PHP_BINARY || ( process.platform === 'win32' ? 'C:\\xampp\\php\\php.exe' : 'php' );
const phpunit = path.join( 'vendor', 'phpunit', 'phpunit', 'phpunit' );

try {
	await access( phpunit );
} catch {
	process.stderr.write( 'PHPUnit is not installed. Run composer install first.\n' );
	process.exit( 1 );
}

const result = spawnSync(
	phpBinary,
	[ phpunit, '-c', 'phpunit.xml.dist', ...process.argv.slice( 2 ) ],
	{ stdio: 'inherit' }
);

if ( result.error ) {
	process.stderr.write( `Unable to run PHP at ${ phpBinary }: ${ result.error.message }\n` );
	process.exit( 1 );
}
process.exit( result.status ?? 1 );
