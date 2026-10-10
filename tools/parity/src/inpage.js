// Functions that run INSIDE the page via page.evaluate. They must stay self-contained:
// Playwright serialises their source, so they cannot close over module variables.

function collectTexts() {
  const norm = (t) => (t || '').trim().replace(/\s+/g, ' ');
  const visible = (el) => {
    const r = el.getBoundingClientRect();
    if (!r.width || !r.height || r.bottom + scrollY <= 0 || r.right <= 0 || r.left >= innerWidth) return false;
    const s = getComputedStyle(el);
    if (s.clip === 'rect(0px, 0px, 0px, 0px)' || s.clipPath === 'inset(50%)') return false;
    return s.visibility !== 'hidden' && s.display !== 'none' && Number(s.opacity) !== 0;
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

function readUnit({ selector, descendants }) {
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

  // Percent and pill radii (9999px) both resolve to what is drawn: at most half the shorter side.
  const radiusPx = (el, s, corners) => {
    const r = el.getBoundingClientRect();
    const cap = Math.min(r.width, r.height) / 2;
    return corners.map((c) => {
      const v = s.getPropertyValue(c).split(' ')[0];
      const px = v.endsWith('%') ? (parseFloat(v) / 100) * r.width : parseFloat(v);
      return `${Math.round(Math.min(px || 0, cap) * 100) / 100}px`;
    }).join(' ');
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

  const hasOwnText = (el) => norm([...el.childNodes].filter((n) => n.nodeType === 3).map((n) => n.textContent).join(' ')).length > 0 || ['INPUT', 'TEXTAREA', 'SELECT'].includes(el.tagName);
  const describe = (el, key, kind) => ({ key, kind, rect: rectOf(el), styles: styles(el), ownText: hasOwnText(el) });
  const out = { rect: rectOf(root), styles: styles(root), ownText: hasOwnText(root), tag: root.tagName.toLowerCase(), text: norm(root.textContent).slice(0, 80), descendants: [] };
  if (!descendants) return out;

  const counters = {};
  const nth = (kind) => (counters[kind] = (counters[kind] || 0) + 1) - 1;
  const all = [...root.querySelectorAll('*')].slice(0, 500);
  for (const el of all) {
    if (out.descendants.length >= 80) break;
    const tag = el.tagName.toLowerCase();
    if (el.closest('svg') && tag !== 'svg') continue;
    const r = el.getBoundingClientRect();
    if (!r.width || !r.height) continue;
    if (tag === 'svg') { out.descendants.push(describe(el, `svg#${nth('svg')}`, 'icon')); continue; }
    if (tag === 'input' || tag === 'textarea') {
      const i = nth('input');
      out.descendants.push(describe(el, `input#${i}`, 'input'));
      if (el.placeholder) out.descendants.push({ key: `placeholder#${i}`, kind: 'placeholder', rect: rectOf(el), styles: styles(el, '::placeholder'), ownText: true });
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

// Rasterises each svg of a unit to a 48x48 ink mask so glyphs can be compared across sides.
async function rasteriseIcons(selector) {
  const root = document.querySelector(selector);
  if (!root) return [];
  const svgs = [...root.querySelectorAll('svg')]
    .filter((s) => !s.parentElement.closest('svg') && s.getBoundingClientRect().width > 0).slice(0, 40);
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
      index, bits, ink: paint, width: rect.width, height: rect.height, strokeUser,
      stroke: Math.round(strokeUser * scale * 100) / 100,
      viewBox: vb ? `${vb.width}x${vb.height}` : null,
      d: [...svg.querySelectorAll('path')].map((p) => p.getAttribute('d')).join(' ').slice(0, 4000),
    });
  }
  return results;
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

module.exports = { collectTexts, readUnit, rasteriseIcons, clearMarks, markAncestors };
