// Functions that run INSIDE the page via page.evaluate. They must stay self-contained:
// Playwright serialises their source, so they cannot close over module variables.

function collectTexts() {
  const norm = (t) => (t || '').trim().replace(/\s+/g, ' ');
  // A closed sheet or drawer stays in the page, pinned and moved out of the viewport (the reference's filter sheet
  // sits translated below the screen); what it holds is not on screen until it opens.
  const pinnedOffscreen = (el, r) => {
    for (let node = el; node && node !== document.body; node = node.parentElement) {
      if (getComputedStyle(node).position === 'fixed') return r.top >= innerHeight || r.bottom <= 0 || r.left >= innerWidth || r.right <= 0;
    }
    return false;
  };
  const visible = (el) => {
    const r = el.getBoundingClientRect();
    if (!r.width || !r.height || r.bottom + scrollY <= 0 || r.right <= 0 || r.left >= innerWidth) return false;
    const s = getComputedStyle(el);
    if (s.clip === 'rect(0px, 0px, 0px, 0px)' || s.clipPath === 'inset(50%)') return false;
    return s.visibility !== 'hidden' && s.display !== 'none' && Number(s.opacity) !== 0 && !pinnedOffscreen(el, r);
  };
  const INTERACTIVE = 'button,a,[role=button],[role=tab],[role=menuitem],[role=option],summary,label';
  const rows = [];
  const pageHeight = Math.max(document.body.scrollHeight, 1);
  const seen = new Set();
  const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
  while (walker.nextNode() && rows.length < 700) {
    const text = norm(walker.currentNode.textContent);
    const el = walker.currentNode.parentElement;
    if (text.length < 2 || text.length > 60 || !el || ['SCRIPT', 'STYLE', 'NOSCRIPT'].includes(el.tagName) || !visible(el)) continue;
    if (seen.has(el)) continue;
    seen.add(el);
    const r = el.getBoundingClientRect();
    const id = String(rows.length);
    el.setAttribute('data-pgt', id);
    const ancestor = el.closest(INTERACTIVE);
    const interactive = ancestor && !ancestor.hasAttribute('data-pgi') ? ancestor : null;
    if (interactive) interactive.setAttribute('data-pgi', id);
    rows.push({
      id, text, key: text.toLowerCase(), tag: el.tagName.toLowerCase(), interactive: Boolean(interactive), inComponent: Boolean(el.closest('[data-pgc]')),
      chrome: Boolean(el.closest('header, footer, aside, nav')),
      relTop: (r.top + scrollY) / pageHeight, relLeft: (r.left + scrollX) / innerWidth,
    });
  }
  for (const el of document.querySelectorAll('input[placeholder],textarea[placeholder]')) {
    const text = norm(el.placeholder);
    if (text.length < 2 || !visible(el)) continue;
    const id = String(rows.length);
    el.setAttribute('data-pgt', id);
    el.setAttribute('data-pgi', id);
    const r = el.getBoundingClientRect();
    rows.push({
      id, text, key: text.toLowerCase(), tag: 'placeholder', interactive: true, inComponent: Boolean(el.closest('[data-pgc]')),
      relTop: (r.top + scrollY) / pageHeight, relLeft: (r.left + scrollX) / innerWidth,
    });
  }
  return rows;
}

function readUnit({ selector, descendants, hop }) {
  const root = document.querySelector(selector);
  if (!root) return null;
  const lineHeights = new Map();
  const norm = (t) => (t || '').trim().replace(/\s+/g, ' ');
  const quad = (s, parts) => parts.map((p) => s.getPropertyValue(p)).join(' ');

  const lineHeightPx = (s) => {
    if (s.lineHeight !== 'normal') return s.lineHeight;
    const key = [s.fontFamily, s.fontSize, s.fontWeight, s.fontStyle].join('|');
    if (!lineHeights.has(key)) {
      const probe = document.createElement('div');
      probe.style.cssText = `position:absolute;visibility:hidden;white-space:nowrap;line-height:normal;padding:0;border:0;margin:0;font-family:${s.fontFamily};font-size:${s.fontSize};font-weight:${s.fontWeight};font-style:${s.fontStyle}`;
      probe.textContent = 'Hg';
      document.body.appendChild(probe);
      lineHeights.set(key, `${Math.round(probe.getBoundingClientRect().height * 100) / 100}px`);
      probe.remove();
    }
    return lineHeights.get(key);
  };

  // Modern colour spaces (oklab, color-mix results) are rewritten as rgb() so values compare by what is drawn.
  const swatch = document.createElement('canvas').getContext('2d', { willReadFrequently: true });
  const toRgb = (token) => {
    swatch.clearRect(0, 0, 1, 1);
    swatch.fillStyle = '#000';
    swatch.fillStyle = token;
    swatch.fillRect(0, 0, 1, 1);
    const [r, g, b, a] = swatch.getImageData(0, 0, 1, 1).data;
    return a === 255 ? `rgb(${r}, ${g}, ${b})` : `rgba(${r}, ${g}, ${b}, ${Math.round((a / 255) * 100) / 100})`;
  };
  const MODERN = /(?:oklab|oklch|lab|lch|color)\([^()]*\)/g;
  const canon = (v) => (/(?:oklab|oklch|lab|lch|color)\(/.test(v) ? v.replace(MODERN, toRgb) : v);

  // The box as laid out, before transforms (its own or an ancestor's): a tilted pill's bounding rect is taller than
  // the pill itself. The rect keeps its sub-pixel precision whenever no transform changed the size.
  const layoutSize = (el) => {
    const r = el.getBoundingClientRect();
    if (typeof el.offsetWidth !== 'number') return { width: r.width, height: r.height };
    const transformed = Math.abs(r.width - el.offsetWidth) > 1 || Math.abs(r.height - el.offsetHeight) > 1;
    return transformed ? { width: el.offsetWidth, height: el.offsetHeight } : { width: r.width, height: r.height };
  };

  // Percent and pill radii (9999px) both resolve to what is drawn: at most half the shorter side.
  const radiusPx = (el, s, corners) => {
    const r = layoutSize(el);
    const cap = Math.min(r.width, r.height) / 2;
    return corners.map((c) => {
      const v = s.getPropertyValue(c).split(' ')[0];
      const px = v.endsWith('%') ? (parseFloat(v) / 100) * r.width : parseFloat(v);
      return `${Math.round(Math.min(px || 0, cap) * 100) / 100}px`;
    }).join(' ');
  };

  // The space an element keeps around itself in its parent's flow: its margin plus the flex or grid gap that
  // follows it (below it, or on its inline-end side). A margin on one side and a parent gap on the other draw
  // the same space, so margins are compared through this value (compare.js).
  const inFlow = (el) => {
    const s = getComputedStyle(el);
    const r = el.getBoundingClientRect();
    return s.display !== 'none' && s.position !== 'absolute' && s.position !== 'fixed' && r.width > 0 && r.height > 0;
  };
  const flowMargin = (el, s) => {
    const margin = [s.marginTop, s.marginRight, s.marginBottom, s.marginLeft].map((v) => parseFloat(v) || 0);
    const parent = el.parentElement;
    const ps = parent ? getComputedStyle(parent) : null;
    if (!ps || !/flex|grid/.test(ps.display) || el.nodeType !== 1 || !inFlow(el)) return margin.map((v) => `${v}px`).join(' ');
    const [rowGap, columnGap] = [parseFloat(ps.rowGap) || 0, parseFloat(ps.columnGap) || 0];
    const r = el.getBoundingClientRect();
    const rtl = ps.direction === 'rtl';
    const siblings = [...parent.children].filter((c) => c !== el && inFlow(c)).map((c) => c.getBoundingClientRect());
    const sameRow = (o) => o.top < r.bottom - 0.5 && o.bottom > r.top + 0.5;
    if (rowGap && siblings.some((o) => o.top >= r.bottom - 0.5)) margin[2] += rowGap;
    if (columnGap && siblings.some((o) => sameRow(o) && (rtl ? o.right <= r.left + 0.5 : o.left >= r.right - 0.5))) margin[rtl ? 3 : 1] += columnGap;
    return margin.map((v) => `${Math.round(v * 100) / 100}px`).join(' ');
  };

  const styles = (el, pseudo) => {
    const s = getComputedStyle(el, pseudo || null);
    const g = (p) => canon(s.getPropertyValue(p));
    return {
      color: g('color'), 'background-color': g('background-color'), 'background-image': g('background-image'),
      'border-width': quad(s, ['border-top-width', 'border-right-width', 'border-bottom-width', 'border-left-width']),
      'border-style': quad(s, ['border-top-style', 'border-right-style', 'border-bottom-style', 'border-left-style']),
      'border-color': canon(quad(s, ['border-top-color', 'border-right-color', 'border-bottom-color', 'border-left-color'])),
      'border-radius': radiusPx(el, s, ['border-top-left-radius', 'border-top-right-radius', 'border-bottom-right-radius', 'border-bottom-left-radius']),
      'box-shadow': g('box-shadow'), 'outline-width': g('outline-width'), 'outline-style': g('outline-style'),
      'outline-color': g('outline-color'), 'outline-offset': g('outline-offset'),
      opacity: g('opacity'), transform: g('transform'), filter: g('filter'), cursor: g('cursor'),
      'font-family': g('font-family'), 'font-size': g('font-size'), 'font-weight': g('font-weight'), 'font-style': g('font-style'),
      'line-height': lineHeightPx(s), 'letter-spacing': g('letter-spacing') === 'normal' ? '0px' : g('letter-spacing'),
      'text-transform': g('text-transform'), 'text-decoration-line': g('text-decoration-line'),
      padding: quad(s, ['padding-top', 'padding-right', 'padding-bottom', 'padding-left']),
      margin: quad(s, ['margin-top', 'margin-right', 'margin-bottom', 'margin-left']),
      'margin-flow': pseudo ? quad(s, ['margin-top', 'margin-right', 'margin-bottom', 'margin-left']) : flowMargin(el, s),
      gap: `${g('row-gap')} ${g('column-gap')}`, width: g('width'), height: g('height'),
      display: g('display'), visibility: g('visibility'), 'z-index': g('z-index'),
      'transition-property': g('transition-property'), 'transition-duration': g('transition-duration'),
      'transition-timing-function': g('transition-timing-function'), 'transition-delay': g('transition-delay'),
      'animation-name': g('animation-name'), 'animation-duration': g('animation-duration'),
      'animation-timing-function': g('animation-timing-function'), 'animation-iteration-count': g('animation-iteration-count'),
      'animation-delay': g('animation-delay'),
    };
  };

  const rectOf = (el) => {
    const r = el.getBoundingClientRect();
    return { x: r.left + scrollX, y: r.top + scrollY, w: r.width, h: r.height };
  };

  // Checkboxes, radios and the like draw no text, so their font and colour are not compared.
  const TEXTLESS_INPUTS = ['checkbox', 'radio', 'range', 'color', 'file', 'hidden', 'image'];
  const isToggle = (el) => el.tagName === 'INPUT' && ['checkbox', 'radio'].includes(el.type);
  const drawsValue = (el) => el.tagName === 'TEXTAREA' || el.tagName === 'SELECT' || (el.tagName === 'INPUT' && !TEXTLESS_INPUTS.includes(el.type));
  const hasOwnText = (el) => norm([...el.childNodes].filter((n) => n.nodeType === 3).map((n) => n.textContent).join(' ')).length > 0 || drawsValue(el);
  // A label inside an unstyled native button inherits the browser's control font, so it counts as the control.
  const nativeKind = (el) => (isToggle(el) ? 'toggle' : el.closest('button, input, select, textarea') ? 'control' : null);

  // The box that owns a text. Both sides carry text in elements that draw no box of their own: the prototype's
  // template engine wraps every interpolated value in <span class="sc-interp">, the target wraps labels in spans.
  // Climbing through such carriers to the parent that holds exactly the same text reaches the element whose margin,
  // padding, border, background and cursor a person sees (a footer heading, a tile label, a city pill, a select's
  // box). The climb stops at a control, at the unit's own root (already compared as itself) and at the body.
  // unit.js boxView reads the box properties from there, on each side; text properties stay on the text element.
  const BOX_PROPS = ['background-color', 'background-image', 'border-width', 'border-style', 'border-color', 'border-radius', 'box-shadow',
    'padding', 'margin', 'margin-flow', 'gap', 'cursor', 'width', 'height', 'display', 'outline-width', 'outline-style', 'outline-color', 'outline-offset',
    'transition-property', 'transition-duration', 'transition-timing-function', 'transition-delay'];
  const CONTROL = 'a, button, summary, label, select, input, textarea, [role=button], [role=tab], [role=link], [role=menuitem], [role=option], [role=combobox], [role=switch], [role=checkbox], [role=radio]';
  const paintsBox = (s) => s.backgroundColor !== 'rgba(0, 0, 0, 0)' || s.backgroundImage !== 'none' || s.boxShadow !== 'none'
    || [s.borderTopWidth, s.borderRightWidth, s.borderBottomWidth, s.borderLeftWidth].some((w) => parseFloat(w) > 0);
  const spaceless = (s) => [s.paddingTop, s.paddingRight, s.paddingBottom, s.paddingLeft, s.marginTop, s.marginRight, s.marginBottom, s.marginLeft]
    .every((v) => parseFloat(v) === 0);
  const carrier = (el) => !el.matches(CONTROL) && !paintsBox(getComputedStyle(el)) && spaceless(getComputedStyle(el));
  // What a person reads in an element: an invisible sizer that repeats the label does not make its parent say more.
  const shownText = (el) => norm(typeof el.innerText === 'string' ? el.innerText : el.textContent);
  const ownerBox = (el, stopAt) => {
    const text = shownText(el);
    if (!text) return el;
    let node = el;
    while (carrier(node)) {
      const parent = node.parentElement;
      if (!parent || parent === document.body || parent === stopAt || shownText(parent) !== text) break;
      node = parent;
    }
    return node;
  };
  // The item that keeps the box's place in its row or column: a wrapper around the box that adds only a margin (the
  // target's <li> around a city pill link carries the pill's stagger) holds the margins the reference sets on the box.
  const padless = (s) => [s.paddingTop, s.paddingRight, s.paddingBottom, s.paddingLeft].every((v) => parseFloat(v) === 0);
  const flowItemOf = (owner, text, stopAt) => {
    let node = owner;
    for (let parent = node.parentElement; parent && parent !== document.body && parent !== stopAt && shownText(parent) === text
      && !parent.matches(CONTROL) && !paintsBox(getComputedStyle(parent)) && padless(getComputedStyle(parent)); parent = parent.parentElement) node = parent;
    return node;
  };
  const MARGIN_PROPS = ['margin', 'margin-flow'];
  const boxView = (el, stopAt) => {
    const owner = ownerBox(el, stopAt);
    const item = shownText(el) ? flowItemOf(owner, shownText(el), stopAt) : owner;
    if (owner === el && item === el) return {};
    const [ownerStyles, itemStyles] = [styles(owner), item === owner ? null : styles(item)];
    const pick = (p) => [p, itemStyles && MARGIN_PROPS.includes(p) ? itemStyles[p] : ownerStyles[p]];
    return { box: { tag: owner.tagName.toLowerCase(), rect: rectOf(owner), styles: Object.fromEntries(BOX_PROPS.map(pick)) } };
  };
  // Hidden on purpose: a sizer span kept invisible to reserve a combobox's width is not what a person reads.
  const hidden = (el) => getComputedStyle(el).visibility === 'hidden';
  // A root that draws no text of its own but holds one visible text element (a combobox around its label) draws its
  // text with that element's typography.
  const TEXT_PROPS = ['color', 'font-family', 'font-size', 'font-weight', 'font-style', 'line-height', 'letter-spacing', 'text-transform', 'text-decoration-line'];
  const ownTextOf = (el) => norm([...el.childNodes].filter((n) => n.nodeType === 3).map((n) => n.textContent).join(' '));
  const textCarrierOf = (el) => {
    if (hasOwnText(el)) return null;
    const writers = [...el.querySelectorAll('*')].filter((n) => !n.closest('svg') && ownTextOf(n) && !hidden(n) && n.getBoundingClientRect().width > 0);
    return writers.length === 1 ? writers[0] : null;
  };
  const textView = (el) => {
    const writer = textCarrierOf(el);
    if (!writer) return {};
    const writerStyles = styles(writer);
    return { textCarrier: { tag: writer.tagName.toLowerCase(), styles: Object.fromEntries(TEXT_PROPS.map((p) => [p, writerStyles[p]])) } };
  };
  // A language flag: the target draws SVG flags (60x40) and the reference's auth pages without auth.js keep a static
  // 15px rect in the pill; both are the owner-accepted flag difference, not a glyph to match.
  const LANGUAGE = '.qb-lang, [aria-label^="language" i], [aria-haspopup][aria-label*="language" i]';
  const isFlag = (svg) => {
    if (!svg.closest(LANGUAGE)) return false;
    const vb = svg.viewBox && svg.viewBox.baseVal;
    if (vb && vb.width && Math.abs(vb.width / vb.height - 1.5) < 0.01) return 'flag';
    const shapes = [...svg.children];
    return shapes.length === 1 && shapes[0].tagName.toLowerCase() === 'rect' ? 'static' : false;
  };
  const inputType = (el) => (el.tagName === 'INPUT' ? el.type : el.tagName.toLowerCase());
  // The field an input stands for, as a person reads it: its label, else its accessible name or placeholder.
  const fieldOf = (el) => norm((el.labels && el.labels[0] && el.labels[0].innerText) || el.getAttribute('aria-label') || el.placeholder || el.name || '')
    .toLowerCase().slice(0, 40);
  const templateWrapper = (el) => el.classList.contains('sc-interp');
  const describe = (el, key, kind) => ({ key, kind, tag: el.tagName.toLowerCase(), native: nativeKind(el), templateWrapper: templateWrapper(el), rect: rectOf(el), styles: styles(el), ownText: hasOwnText(el), ...boxView(el, root) });
  const out = { rect: rectOf(root), styles: styles(root), ownText: hasOwnText(root), tag: root.tagName.toLowerCase(), native: nativeKind(root), templateWrapper: templateWrapper(root), text: norm(root.textContent).slice(0, 80), descendants: [], ...(hop ? boxView(root, null) : {}), ...textView(root) };
  if (!descendants) return out;

  const counters = {};
  const nth = (kind) => (counters[kind] = (counters[kind] || 0) + 1) - 1;
  const all = [...root.querySelectorAll('*')].slice(0, 500);
  for (const el of all) {
    if (out.descendants.length >= 80) break;
    const tag = el.tagName.toLowerCase();
    if (el.closest('svg') && tag !== 'svg') continue;
    const r = el.getBoundingClientRect();
    if (!r.width || !r.height || hidden(el)) continue;
    if (tag === 'svg') { out.descendants.push({ ...describe(el, `svg#${nth('svg')}`, 'icon'), flag: isFlag(el) }); continue; }
    if (tag === 'input' || tag === 'textarea') {
      const i = nth('input');
      out.descendants.push({ ...describe(el, `input#${i}`, 'input'), inputType: inputType(el), field: fieldOf(el) });
      if (el.placeholder) out.descendants.push({ key: `placeholder#${i}`, kind: 'placeholder', of: `input#${i}`, rect: rectOf(el), styles: styles(el, '::placeholder'), ownText: true });
      continue;
    }
    if (tag === 'img') { out.descendants.push(describe(el, `img#${nth('img')}`, 'image')); continue; }
    const own = [...el.childNodes].filter((n) => n.nodeType === 3).map((n) => n.textContent).join(' ');
    const text = norm(own);
    if (text.length >= 2) {
      const key = `t:${text.toLowerCase().slice(0, 60)}`;
      out.descendants.push(describe(el, `${key}#${nth(key)}`, 'text'));
    }
  }
  return out;
}

// Rasterises each svg of a unit to a 48x48 ink mask so glyphs can be compared across sides. `scope` is a selector,
// or { selector, extra } where `extra` names more roots whose icons belong to the unit (the reference's phone header
// bar is pinned outside <header>). Hidden svgs (a desktop icon set kept in the DOM at phone widths) are not counted.
async function rasteriseIcons(scope) {
  const { selector, extra } = typeof scope === 'string' ? { selector: scope } : scope;
  const root = document.querySelector(selector);
  if (!root) return [];
  const roots = [root, ...(extra ? [...document.querySelectorAll(extra)].filter((n) => !root.contains(n)) : [])];
  const shown = (s) => { const r = s.getBoundingClientRect(); return r.width > 0 && r.height > 0 && getComputedStyle(s).visibility !== 'hidden'; };
  const svgs = roots.flatMap((r) => [...r.querySelectorAll('svg')])
    .filter((s) => !s.parentElement.closest('svg') && shown(s)).slice(0, 40);
  // A language flag (the target's 60x40 SVG flags, the reference's static rect in the auth pill) is the accepted flag
  // difference, compared by the language scenarios, not as a glyph.
  const LANGUAGE = '.qb-lang, [aria-label^="language" i], [aria-haspopup][aria-label*="language" i]';
  const isFlag = (svg) => {
    if (!svg.closest(LANGUAGE)) return false;
    const vb = svg.viewBox && svg.viewBox.baseVal;
    if (vb && vb.width && Math.abs(vb.width / vb.height - 1.5) < 0.01) return 'flag';
    const shapes = [...svg.children];
    return shapes.length === 1 && shapes[0].tagName.toLowerCase() === 'rect' ? 'static' : false;
  };
  const canvas = document.createElement('canvas');
  canvas.width = 48; canvas.height = 48;
  const ctx = canvas.getContext('2d', { willReadFrequently: true });
  const inked = (v) => (v && v !== 'none' && !/rgba\(\s*\d+,\s*\d+,\s*\d+,\s*0\)/.test(v) ? '#000' : 'none');
  const results = [];
  for (let index = 0; index < svgs.length; index++) {
    const svg = svgs[index];
    const clone = svg.cloneNode(true);
    const live = [svg, ...svg.querySelectorAll('*')];
    const copy = [clone, ...clone.querySelectorAll('*')];
    live.forEach((el, i) => {
      const s = getComputedStyle(el);
      copy[i].setAttribute('style', `fill:${inked(s.fill)};stroke:${inked(s.stroke)};stroke-width:${s.strokeWidth};stroke-linecap:${s.strokeLinecap};stroke-linejoin:${s.strokeLinejoin};fill-rule:${s.fillRule};opacity:1`);
    });
    const vb = svg.viewBox && svg.viewBox.baseVal && svg.viewBox.baseVal.width ? svg.viewBox.baseVal : null;
    const rect = svg.getBoundingClientRect();
    if (!clone.getAttribute('viewBox')) clone.setAttribute('viewBox', `0 0 ${rect.width} ${rect.height}`);
    clone.setAttribute('width', '48');
    clone.setAttribute('height', '48');
    clone.setAttribute('xmlns', 'http://www.w3.org/2000/svg');
    let bits = null;
    try {
      const img = new Image();
      img.src = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(new XMLSerializer().serializeToString(clone));
      await img.decode();
      ctx.clearRect(0, 0, 48, 48);
      ctx.drawImage(img, 0, 0, 48, 48);
      const px = ctx.getImageData(0, 0, 48, 48).data;
      bits = '';
      for (let i = 3; i < px.length; i += 4) bits += px[i] > 60 ? '1' : '0';
    } catch (e) { bits = null; }
    const stroked = svg.querySelector('[stroke]:not([stroke=none])') || svg;
    const style = getComputedStyle(stroked);
    const strokeUser = style.stroke !== 'none' ? parseFloat(style.strokeWidth) || 0 : 0;
    const scale = vb ? rect.width / vb.width : 1;
    const toRgb = (token) => { ctx.clearRect(0, 0, 1, 1); ctx.fillStyle = '#000'; ctx.fillStyle = token; ctx.fillRect(0, 0, 1, 1); const [r, g, b] = ctx.getImageData(0, 0, 1, 1).data; return `rgb(${r}, ${g}, ${b})`; };
    const rawPaint = style.stroke !== 'none' && strokeUser ? style.stroke : getComputedStyle(svg.querySelector('[fill]:not([fill=none])') || svg).fill;
    const paint = /^(oklab|oklch|lab|lch|color)\(/.test(rawPaint) ? toRgb(rawPaint) : rawPaint;
    results.push({
      index, bits, ink: paint, width: rect.width, height: rect.height, strokeUser, flag: isFlag(svg),
      stroke: Math.round(strokeUser * scale * 100) / 100,
      viewBox: vb ? `${vb.width}x${vb.height}` : null,
      d: [...svg.querySelectorAll('path')].map((p) => p.getAttribute('d')).join(' ').slice(0, 4000),
    });
  }
  return results;
}

// Icons drawn with CSS instead of an svg: a control with no text, svg or image whose inner boxes paint the glyph (the
// reference's phone burger is three bars drawn by a span and its ::before/::after). Counted with the svgs so a burger
// drawn in CSS on one side and as an svg on the other is the same number of icons.
function countDrawnIcons(scope) {
  const { selector, extra } = typeof scope === 'string' ? { selector: scope } : scope;
  const root = document.querySelector(selector);
  if (!root) return 0;
  const roots = [root, ...(extra ? [...document.querySelectorAll(extra)].filter((n) => !root.contains(n)) : [])];
  const CONTROL = 'a, button, [role=button]';
  const shown = (n) => { const r = n.getBoundingClientRect(); return r.width > 0 && r.height > 0 && getComputedStyle(n).visibility !== 'hidden'; };
  const paints = (s) => s.backgroundColor !== 'rgba(0, 0, 0, 0)' || [s.borderTopWidth, s.borderBottomWidth].some((w) => parseFloat(w) > 0);
  const drawn = (control) => !(control.innerText || '').trim() && !control.querySelector('svg, img') && shown(control)
    && [...control.querySelectorAll('*')].some((n) => shown(n) && n.getBoundingClientRect().width <= 32
      && (paints(getComputedStyle(n)) || paints(getComputedStyle(n, '::before'))));
  const controls = roots.flatMap((r) => [...(r.matches(CONTROL) ? [r] : []), ...r.querySelectorAll(CONTROL)]);
  return controls.filter(drawn).length;
}

function clearMarks(attrs) {
  for (const attr of attrs) document.querySelectorAll(`[${attr}]`).forEach((el) => el.removeAttribute(attr));
}

function markAncestors({ selector, id }) {
  const el = document.querySelector(selector);
  if (!el) return false;
  document.querySelectorAll('[data-pga]').forEach((n) => n.removeAttribute('data-pga'));
  // Hover rules rarely reach further than a card or a menu, so four ancestors are forced.
  let node = el;
  for (let depth = 0; node && node !== document.documentElement && depth < 5; depth++, node = node.parentElement) node.setAttribute('data-pga', id);
  return true;
}

module.exports = { collectTexts, readUnit, rasteriseIcons, countDrawnIcons, clearMarks, markAncestors };
