// Decides whether the visual editor can hold a piece of HTML without losing
// anything. Both sides are reduced to a canonical string so harmless
// differences (attribute order, <b> vs <strong>, whitespace between blocks,
// the <p> TipTap puts inside list items and table cells) do not count.

const RENAMED = { b: 'strong', i: 'em' };
const ALIGN = /^\s*text-align\s*:\s*(start|end|left|right|center|justify)\s*;?\s*$/i;
// Dropped on both sides: layout helpers the editor adds that the server strips anyway.
const IGNORED_TAGS = new Set(['colgroup', 'col']);
const STRUCTURAL = new Set(['body', 'ul', 'ol', 'table', 'thead', 'tbody', 'tr', 'blockquote']);
const UNWRAP_SOLE_P = new Set(['li', 'td', 'th']);

function canonicalAttrs(el) {
    return [...el.attributes]
        .filter((a) => !a.name.startsWith('data-'))
        .filter((a) => !(el.localName === 'table' && a.name === 'style'))
        .map((a) => {
            const align = a.name === 'style' ? ALIGN.exec(a.value) : null;
            return `${a.name}="${align ? `text-align: ${align[1].toLowerCase()}` : a.value}"`;
        })
        .sort()
        .join(' ');
}

function canonicalChildren(node) {
    let kids = [...node.childNodes].filter((n) => !(n.nodeType === Node.TEXT_NODE && n.textContent.trim() === '' && n.textContent.includes('\n')));
    const only = kids.length === 1 ? kids[0] : null;
    if (only && UNWRAP_SOLE_P.has(node.localName) && only.localName === 'p' && only.attributes.length === 0) kids = [...only.childNodes];
    return kids.map(canonicalNode).join('');
}

function canonicalNode(node) {
    if (node.nodeType === Node.TEXT_NODE) return node.textContent.replace(/\s+/g, ' ');
    if (node.nodeType !== Node.ELEMENT_NODE) return '';
    const tag = RENAMED[node.localName] || node.localName;
    if (IGNORED_TAGS.has(tag)) return '';
    const attrs = canonicalAttrs(node);
    const inner = canonicalChildren(node);
    return `<${tag}${attrs ? ` ${attrs}` : ''}>${inner}</${tag}>`;
}

function canonical(html) {
    const body = new DOMParser().parseFromString(`<body>${html}</body>`, 'text/html').body;
    return canonicalChildren(body)
        .replace(/<(p|h[1-6])><\/\1>$/, '')
        .trim();
}

/** True when `html` and what the editor serialised for it mean the same thing. */
export function roundTrips(html, editorHtml, editorIsEmpty) {
    return canonical(html) === (editorIsEmpty ? '' : canonical(editorHtml));
}
