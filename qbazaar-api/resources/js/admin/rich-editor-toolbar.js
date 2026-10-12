// Toolbar definition: which buttons exist, how they act and when they look active.
const svg = (d) => `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${d}</svg>`;
const text = (t, cls = '') => `<span class="qb-rte-glyph ${cls}" aria-hidden="true">${t}</span>`;

const lines = (widths, side) =>
    widths.map((w, i) => {
        const y = 6 + i * 4;
        const x1 = side === 'end' ? 20 - w : side === 'center' ? 12 - w / 2 : 4;
        return `<path d="M${x1} ${y}h${w}"/>`;
    }).join('');

const ICONS = {
    undo: svg('<path d="M9 14 4 9l5-5"/><path d="M4 9h10a6 6 0 0 1 0 12h-3"/>'),
    redo: svg('<path d="m15 14 5-5-5-5"/><path d="M20 9H10a6 6 0 0 0 0 12h3"/>'),
    paragraph: text('¶'),
    h2: text('H2'),
    h3: text('H3'),
    bold: text('B', 'qb-rte-b'),
    italic: text('I', 'qb-rte-i'),
    underline: text('U', 'qb-rte-u'),
    strike: text('S', 'qb-rte-s'),
    bulletList: svg('<path d="M9 6h11M9 12h11M9 18h11"/><path d="M4.5 6h.01M4.5 12h.01M4.5 18h.01"/>'),
    orderedList: svg('<path d="M10 6h10M10 12h10M10 18h10"/><path d="M4 5l1-1v4M4 14h2l-2 3h2"/>'),
    blockquote: svg('<path d="M7 7h4v4c0 3-1.5 4.5-4 5M15 7h4v4c0 3-1.5 4.5-4 5"/>'),
    hr: svg('<path d="M4 12h16"/>'),
    link: svg('<path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1"/><path d="M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1"/>'),
    clear: svg('<path d="M5 5h14M12 5v4M9 19l3-10M15 19l-1.5-5M4 20 20 4"/>'),
    source: svg('<path d="m8 8-4 4 4 4M16 8l4 4-4 4M13.5 5l-3 14"/>'),
    alignStart: svg(lines([16, 10, 14, 8], 'start')),
    alignEnd: svg(lines([16, 10, 14, 8], 'end')),
    alignCenter: svg(lines([16, 10, 14, 8], 'center')),
    alignJustify: svg(lines([16, 16, 16, 16], 'start')),
};

// Groups are separated visually; `run` executes the command, `active` drives aria-pressed.
export const GROUPS = [
    [
        { id: 'undo', run: (c) => c.undo(), can: (e) => e.can().undo() },
        { id: 'redo', run: (c) => c.redo(), can: (e) => e.can().redo() },
    ],
    [
        { id: 'paragraph', run: (c) => c.setParagraph(), active: (e) => e.isActive('paragraph') },
        { id: 'h2', run: (c) => c.toggleHeading({ level: 2 }), active: (e) => e.isActive('heading', { level: 2 }) },
        { id: 'h3', run: (c) => c.toggleHeading({ level: 3 }), active: (e) => e.isActive('heading', { level: 3 }) },
    ],
    [
        { id: 'bold', run: (c) => c.toggleBold(), active: (e) => e.isActive('bold') },
        { id: 'italic', run: (c) => c.toggleItalic(), active: (e) => e.isActive('italic') },
        { id: 'underline', run: (c) => c.toggleUnderline(), active: (e) => e.isActive('underline') },
        { id: 'strike', run: (c) => c.toggleStrike(), active: (e) => e.isActive('strike') },
    ],
    [
        { id: 'bulletList', run: (c) => c.toggleBulletList(), active: (e) => e.isActive('bulletList') },
        { id: 'orderedList', run: (c) => c.toggleOrderedList(), active: (e) => e.isActive('orderedList') },
        { id: 'blockquote', run: (c) => c.toggleBlockquote(), active: (e) => e.isActive('blockquote') },
        { id: 'hr', run: (c) => c.setHorizontalRule() },
    ],
    [
        { id: 'alignStart', run: (c) => c.setTextAlign('start'), active: (e) => e.isActive({ textAlign: 'start' }) },
        { id: 'alignCenter', run: (c) => c.setTextAlign('center'), active: (e) => e.isActive({ textAlign: 'center' }) },
        { id: 'alignEnd', run: (c) => c.setTextAlign('end'), active: (e) => e.isActive({ textAlign: 'end' }) },
        { id: 'alignJustify', run: (c) => c.setTextAlign('justify'), active: (e) => e.isActive({ textAlign: 'justify' }) },
    ],
    [
        { id: 'link', special: 'link', active: (e) => e.isActive('link') },
        { id: 'clear', run: (c) => c.unsetAllMarks().clearNodes().unsetTextAlign() },
        { id: 'source', special: 'source' },
    ],
];

/** Start/end icons are drawn left/right; swap them under RTL so "start" always points at the reading edge. */
export function iconFor(id, isRtl) {
    if (isRtl && id === 'alignStart') return ICONS.alignEnd;
    if (isRtl && id === 'alignEnd') return ICONS.alignStart;
    return ICONS[id];
}
