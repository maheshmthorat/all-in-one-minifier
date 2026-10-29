// Differential test: every N.min.css must produce the same css-tree AST as N.orig.css.
// Comparison uses css-tree's canonical generator; raw values (custom properties,
// unparsed preludes) are compared with whitespace runs collapsed, since CSS
// tokenization treats any whitespace run as a single whitespace token.
// Usage: node compare-css.js <dir>
'use strict';
const csstree = require('css-tree');
const fs = require('fs');
const path = require('path');

function canon(src) {
	const errors = [];
	const ast = csstree.parse(src, {
		parseCustomProperty: false,
		onParseError: (e) => errors.push(e.message),
	});
	csstree.walk(ast, function (node) {
		if (node.type === 'Raw') {
			node.value = node.value.replace(/\/\*[\s\S]*?\*\//g, '').replace(/\s+/g, ' ').trim();
			// Outside custom property values (which are exposed verbatim to JS), whitespace
			// next to "," "(" ")" is never significant, including in values css-tree could
			// not parse (IE filters, var() fallbacks, newer functions).
			if (!(this.declaration && this.declaration.property.startsWith('--') && !this.function)) {
				node.value = node.value.replace(/\s*([,)])\s*/g, '$1').replace(/\(\s+/g, '(');
			}
		}
		if (node.type === 'Comment') node.value = '';
	});
	// Empty declarations (";;") are ignored by CSS parsers.
	return { css: csstree.generate(ast).replace(/;{2,}/g, ';').replace(/;}/g, '}'), errors: errors.length };
}

const dir = process.argv[2];
let equal = 0, different = 0;
for (const f of fs.readdirSync(dir).filter((f) => f.endsWith('.orig.css'))) {
	const n = f.slice(0, -'.orig.css'.length);
	const name = fs.readFileSync(path.join(dir, n + '.name'), 'utf8');
	const a = canon(fs.readFileSync(path.join(dir, f), 'utf8'));
	const b = canon(fs.readFileSync(path.join(dir, n + '.min.css'), 'utf8'));
	if (a.css === b.css) { equal++; continue; }
	different++;
	let i = 0;
	while (i < a.css.length && a.css[i] === b.css[i]) i++;
	console.log('DIFFERENT', name, `(parse errors orig=${a.errors} min=${b.errors})`);
	console.log('   orig:', JSON.stringify(a.css.slice(Math.max(0, i - 60), i + 60)));
	console.log('   min :', JSON.stringify(b.css.slice(Math.max(0, i - 60), i + 60)));
}
console.log(`CSS AST check: ${equal} equal, ${different} different`);
process.exit(different ? 1 : 0);
