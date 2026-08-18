/**
 * Executable contract for the canonical Studio size control.
 *
 * The React DOM ownership gate can only string-match the runtime. This gate
 * evaluates the real parser/serializer helpers straight out of the shipped
 * source, so the round-trip a user actually experiences -- pick a mode, save a
 * CSS string, reload, get the same mode back -- is asserted rather than assumed.
 */
import fs from 'node:fs';
import vm from 'node:vm';

const file = 'runtime-src/build/studio-dimension-controls.js';
const src = fs.readFileSync(file, 'utf8').replace(/\r\n/g, '\n');

function slice(from, to) {
	const start = src.indexOf(from);
	const end = src.indexOf(to);
	if (start < 0 || end < 0 || end <= start) {
		throw new Error(`[size-modes] Cannot locate the helper block between "${from}" and "${to}". The runtime layout changed.`);
	}
	return src.slice(start, end);
}

const helpers =
	slice('  var UNITS =', '  function inspectorPanel()') +
	slice('  function optionList(', '  function DimensionControl(');

const context = { window: {}, document: {}, root: null };
vm.createContext(context);
vm.runInContext(
	`${helpers}\nthis.API = { inferMode, parseSimple, serial, effective, owns, optionList };`,
	context
);
const { inferMode, serial, effective, owns, optionList } = context.API;

const failures = [];
const check = (label, got, want) => {
	if (JSON.stringify(got) !== JSON.stringify(want)) {
		failures.push(`${label}: expected ${JSON.stringify(want)}, got ${JSON.stringify(got)}`);
	}
};

// A stored CSS string must reopen in the mode that produced it.
const parseCases = [
	['width', '120px', 'px'],
	['width', '50%', '%'],
	['width', '2rem', 'rem'],
	['width', '10vw', 'vw'],
	['width', '100%', '100%'],
	['width', 'auto', 'auto'],
	['width', 'fit-content', 'fit-content'],
	['width', 'min-content', 'min-content'],
	['width', 'max-content', 'max-content'],
	['maxWidth', 'none', 'none'],
	['height', '400px', 'px'],
	['fontSize', '16px', 'px'],
	['fontSize', '1rem', 'rem'],
	['lineHeight', '1.2', 'unitless'],
	['lineHeight', 'normal', 'normal'],
	['lineHeight', '1.5em', 'em'],
	['letterSpacing', '0.05em', 'em'],
	// Anything the property's own unit list does not offer stays editable as Custom
	// instead of being silently rewritten.
	['letterSpacing', '2vw', 'custom'],
	['width', 'clamp(20rem, 50vw, 70rem)', 'custom'],
	['width', 'calc(100% - 32px)', 'custom'],
	['width', 'var(--content-width)', 'custom'],
	['width', '{spacing.lg}', 'custom'],
	// An empty value opens on the property's natural default unit.
	['width', '', 'px'],
	['lineHeight', '', 'unitless'],
];
for (const [key, value, mode] of parseCases) {
	check(`inferMode(${key}, ${JSON.stringify(value)})`, inferMode(key, value), mode);
}

// Serializing is the exact inverse, and never invents a unit for an empty value.
check('serial(120,px)', serial('120', 'px'), '120px');
check('serial(50,%)', serial('50', '%'), '50%');
check('serial(1.4,unitless)', serial('1.4', 'unitless'), '1.4');
check('serial(empty)', serial('', 'px'), '');

// Every size control offers Custom CSS as an escape hatch.
for (const key of ['width', 'height', 'minWidth', 'maxWidth', 'fontSize', 'lineHeight', 'borderRadius', 'marginTop']) {
	const modes = optionList(key).map((option) => option.value);
	if (!modes.includes('custom')) failures.push(`optionList(${key}) is missing the custom mode.`);
	if (modes.length < 2) failures.push(`optionList(${key}) must offer more than one mode.`);
}
// Units a property cannot store must not be offered for it.
check('letterSpacing units', optionList('letterSpacing').map((o) => o.value).filter((v) => ['vw', 'vh', 'ch'].includes(v)), []);

// Responsive inheritance is desktop-first, and an override is distinguishable
// from an inherited value at every device.
const node = {
	style: { width: '120px', marginTop: '8px' },
	responsive: { desktop: { width: '110px' }, tablet: { width: '100%' } },
	states: { hover: { width: '220px' } },
};
check('wide reads base', effective(node, 'wide', 'normal').width, '120px');
check('desktop override', effective(node, 'desktop', 'normal').width, '110px');
check('tablet override', effective(node, 'tablet', 'normal').width, '100%');
check('mobile inherits tablet', effective(node, 'mobile', 'normal').width, '100%');
check('hover state wins', effective(node, 'wide', 'hover').width, '220px');
check('unrelated key still inherited', effective(node, 'mobile', 'normal').marginTop, '8px');
check('tablet owns width', owns(node, 'width', 'tablet', 'normal'), true);
check('mobile does not own width', owns(node, 'width', 'mobile', 'normal'), false);
check('hover owns width', owns(node, 'width', 'wide', 'hover'), true);
check('mobile does not own marginTop', owns(node, 'marginTop', 'mobile', 'normal'), false);

if (failures.length) {
	process.stderr.write(`[size-modes] ${failures.length} contract failure(s):\n  ${failures.join('\n  ')}\n`);
	process.exit(1);
}

process.stdout.write(
	`[size-modes] Size control contract verified: ${parseCases.length} parse cases, serializer inverse, Custom CSS availability, per-property unit limits, and responsive override/inheritance.\n`
);
