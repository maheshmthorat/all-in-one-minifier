// Differential test: every N.min.js must parse to the same AST as N.orig.js (acorn).
// Positions, raw source text and comments are ignored; values are compared.
// Usage: node compare-js.js <dir>
'use strict';
const acorn = require('acorn');
const fs = require('fs');
const path = require('path');

function strip(node) {
	if (Array.isArray(node)) return node.map(strip);
	if (node && typeof node === 'object') {
		const o = {};
		for (const k of Object.keys(node).sort()) {
			if (k === 'start' || k === 'end' || k === 'loc' || k === 'range' || k === 'raw') continue;
			if (k === 'value' && node.type === 'Literal' && node.regex) continue;
			if (k === 'value' && node.type === 'TemplateElement') { o[k] = node.value.cooked; continue; }
			// Empty statements inside statement lists are no-ops.
			if ((k === 'body' || k === 'consequent') && Array.isArray(node[k])) {
				o[k] = strip(node[k].filter((s) => !(s && s.type === 'EmptyStatement')));
				continue;
			}
			o[k] = strip(node[k]);
		}
		return o;
	}
	if (typeof node === 'bigint') return node.toString() + 'n';
	return node;
}
function parse(src) {
	let err;
	for (const sourceType of ['script', 'module']) {
		try {
			return JSON.stringify(strip(acorn.parse(src, { ecmaVersion: 'latest', sourceType, allowHashBang: true })));
		} catch (e) { err = e; }
	}
	return 'PARSE_ERROR ' + err.message;
}

const dir = process.argv[2];
let equal = 0, different = 0, skipped = 0;
for (const f of fs.readdirSync(dir).filter((f) => f.endsWith('.orig.js'))) {
	const n = f.slice(0, -'.orig.js'.length);
	const name = fs.readFileSync(path.join(dir, n + '.name'), 'utf8');
	const a = parse(fs.readFileSync(path.join(dir, f), 'utf8'));
	if (a.startsWith('PARSE_ERROR')) { skipped++; continue; }
	const b = parse(fs.readFileSync(path.join(dir, n + '.min.js'), 'utf8'));
	if (a === b) equal++;
	else { different++; console.log('DIFFERENT', name, b.startsWith('PARSE_ERROR') ? b : ''); }
}
console.log(`JS AST check: ${equal} equal, ${different} different, ${skipped} originals not parseable by acorn`);
process.exit(different ? 1 : 0);
