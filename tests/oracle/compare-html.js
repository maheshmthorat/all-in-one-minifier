// Differential test: the optimised page must produce the same DOM as the original (parse5).
//
// Canonical form (applied to both documents):
// - comments removed, adjacent text nodes merged;
// - text inside pre/textarea/code/listing/plaintext/xmp/title/noscript/script/style,
//   SVG <text>, and elements with inline white-space:pre* is compared exactly;
//   other text has whitespace runs collapsed to one space (rendering-equivalent);
// - whitespace-only text directly inside <html>/<head>, and at the start/end of <body>, is dropped;
// - <style> content is compared after css-tree canonicalisation, JSON scripts by value;
// - <script> elements with type="aiom/delayed" are compared with their original type;
// - stylesheet/script URLs are replaced by a placeholder (the plugin intentionally points
//   them to minified copies; the files themselves are verified by compare-css/compare-js),
//   and the capture parameter aiom_mode=on|off is normalised.
// Usage: node compare-html.js <dir with N.orig.html / N.min.html / N.name>
'use strict';
const parse5 = require('parse5');
const csstree = require('css-tree');
const fs = require('fs');
const path = require('path');

const EXACT = new Set(['pre', 'textarea', 'code', 'listing', 'plaintext', 'xmp', 'title', 'noscript', 'script', 'style', 'iframe', 'noembed', 'noframes']);
const JSON_TYPES = new Set(['application/ld+json', 'application/json', 'importmap', 'speculationrules']);

function attr(node, name) {
	const a = (node.attrs || []).find((x) => x.name === name);
	return a ? a.value : null;
}
function cssCanon(src) {
	try {
		const ast = csstree.parse(src, { parseCustomProperty: false });
		csstree.walk(ast, function (n) {
			if (n.type === 'Raw') n.value = n.value.replace(/\/\*[\s\S]*?\*\//g, '').replace(/\s+/g, ' ').trim();
		});
		return csstree.generate(ast).replace(/;{2,}/g, ';').replace(/;}/g, '}');
	} catch (e) {
		return 'UNPARSEABLE:' + src;
	}
}
function textOf(node) {
	return (node.childNodes || []).filter((c) => c.nodeName === '#text').map((c) => c.value).join('');
}
// Capture parameter (also URL-encoded, and echoed by WooCommerce's sort form) and
// WooCommerce's per-request uniqid() field ids, Store API nonce timestamps and guest
// cart tokens (JWT) vary between any two requests, optimised or not.
const MODE = /aiom_mode(=|%3D|%253D|"\s+value=")(?:on|off)|(_)[0-9a-f]{13}\b|(Nonce-Timestamp%22%3A|NonceTimestamp: '|Cart-Token%22%3A%22)[\w.-]+/gi;
const norm = (v) => v.replace(MODE, (m, sep, us, ts) => (ts ? ts + 'T' : us ? '_UNIQID' : 'aiom_mode' + sep + 'X'));
function canon(node, exact) {
	if (node.nodeName === '#text') return (exact ? node.value : node.value.replace(/[ \t\n\r\f]+/g, ' ')).replace(MODE, (m, sep, us, ts) => (ts ? ts + 'T' : us ? '_UNIQID' : 'aiom_mode' + sep + 'X'));
	if (node.nodeName === '#comment' || node.nodeName === '#documentType') return null;
	const tag = node.tagName || node.nodeName;
	const style = attr(node, 'style') || '';
	const ex = exact || EXACT.has(tag) || (tag === 'text' && node.namespaceURI && node.namespaceURI.includes('svg')) || /white-space\s*:\s*(pre|break-spaces)/i.test(style);
	const attrs = (node.attrs || []).map((a) => [a.name, ((tag === 'link' && a.name === 'href') || (tag === 'script' && a.name === 'src')) ? 'ASSET' : (a.name === 'value' && attr(node, 'name') === 'aiom_mode' ? 'X' : norm(a.value))]);
	if (tag === 'script') {
		const delayed = attrs.find((a) => a[0] === 'type' && a[1] === 'aiom/delayed');
		let list = attrs.filter((a) => !['data-aiom-delay', 'data-aiom-type'].includes(a[0]));
		if (delayed) {
			const orig = attr(node, 'data-aiom-type');
			list = list.filter((a) => a[0] !== 'type');
			if (orig) list.push(['type', orig]);
		}
		const type = (attr(node, 'type') || '').toLowerCase();
		let content = textOf(node).replace(MODE, (m, sep, us, ts) => (ts ? ts + 'T' : us ? '_UNIQID' : 'aiom_mode' + sep + 'X'));
		if (JSON_TYPES.has(type)) {
			try { content = 'JSON:' + JSON.stringify(JSON.parse(content)); } catch (e) { /* keep raw */ }
		}
		return { tag, attrs: list.sort(), content };
	}
	if (tag === 'style') return { tag, attrs, content: cssCanon(textOf(node)) };
	// WooCommerce shuffles related products on every request.
	if (tag === 'section' && /\brelated\b/.test(attr(node, 'class') || '') && /\bproducts\b/.test(attr(node, 'class') || '')) return { tag, attrs, kids: ['RELATED-PRODUCTS'] };
	const kids = [];
	const source = node.content ? node.content.childNodes : node.childNodes || [];
	for (const c of source) {
		const v = canon(c, ex);
		if (v === null) continue;
		if (typeof v === 'string' && typeof kids[kids.length - 1] === 'string') {
			kids[kids.length - 1] = ex ? kids[kids.length - 1] + v : (kids[kids.length - 1] + v).replace(/ {2,}/g, ' ');
		} else {
			kids.push(v);
		}
	}
	let out = kids;
	if (!ex) {
		if (tag === 'html' || tag === 'head') out = kids.filter((k) => !(typeof k === 'string' && k.trim() === ''));
		if (tag === 'body') {
			while (typeof out[0] === 'string' && out[0].trim() === '') out = out.slice(1);
			while (typeof out[out.length - 1] === 'string' && out[out.length - 1].trim() === '') out = out.slice(0, -1);
		}
	}
	return { tag, attrs, kids: out };
}
function firstDiff(a, b, p) {
	if (JSON.stringify(a) === JSON.stringify(b)) return null;
	if (typeof a !== 'object' || typeof b !== 'object' || !a || !b) return { path: p, a, b };
	if (a.tag !== b.tag) return { path: p + '/tag', a: a.tag, b: b.tag };
	if (JSON.stringify(a.attrs) !== JSON.stringify(b.attrs)) return { path: p + '/' + a.tag + '@attrs', a: a.attrs, b: b.attrs };
	if (a.content !== b.content) return { path: p + '/' + a.tag + '#content', a: String(a.content).slice(0, 200), b: String(b.content).slice(0, 200) };
	const n = Math.max((a.kids || []).length, (b.kids || []).length);
	for (let i = 0; i < n; i++) {
		const d = firstDiff(a.kids[i], b.kids[i], p + '/' + a.tag + '[' + i + ']');
		if (d) return d;
	}
	return { path: p, a: 'children differ', b: '' };
}

const dir = process.argv[2];
let equal = 0, different = 0;
for (const f of fs.readdirSync(dir).filter((x) => x.endsWith('.orig.html'))) {
	const n = f.slice(0, -'.orig.html'.length);
	const name = fs.existsSync(path.join(dir, n + '.name')) ? fs.readFileSync(path.join(dir, n + '.name'), 'utf8') : n;
	const a = canon(parse5.parse(fs.readFileSync(path.join(dir, f), 'utf8')), false);
	const b = canon(parse5.parse(fs.readFileSync(path.join(dir, n + '.min.html'), 'utf8')), false);
	const d = firstDiff(a, b, '');
	if (!d) { equal++; continue; }
	different++;
	console.log('DIFFERENT', name.trim(), d.path);
	console.log('   orig:', JSON.stringify(d.a).slice(0, 300));
	console.log('   min :', JSON.stringify(d.b).slice(0, 300));
}
console.log(`HTML DOM check: ${equal} equal, ${different} different`);
process.exit(different ? 1 : 0);
