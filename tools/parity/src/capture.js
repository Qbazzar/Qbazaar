// State capture through the Chrome DevTools Protocol: forced pseudo states, rendered fonts,
// stylesheet text (for keyframes and source lines) and in-page unit reads.
const inpage = require('./inpage');

const STATE_CLASSES = { hover: ['hover'], 'focus-visible': ['focus', 'focus-visible'], active: ['active', 'hover'] };
const SOURCE_PROPERTY = {
  padding: /^padding(-|$)/, margin: /^margin(-|$)/, 'border-radius': /^border(-\w+)*-radius$|^border-radius$/,
  'border-width': /^border(-\w+)?(-width)?$/, 'border-color': /^border(-\w+)?(-color)?$/,
  gap: /^(row-|column-)?gap$/, 'font-size': /^font(-size)?$/, 'line-height': /^(font|line-height)$/,
};

class Probe {
  constructor(page, cdp, side, base) {
    this.page = page;
    this.cdp = cdp;
    this.side = side;
    this.base = base;
    this.sheets = new Map();
  }

  static async attach(context, page, side, base) {
    const cdp = await context.newCDPSession(page);
    const probe = new Probe(page, cdp, side, base);
    cdp.on('CSS.styleSheetAdded', ({ header }) => probe.sheets.set(header.styleSheetId, header));
    await cdp.send('DOM.enable');
    await cdp.send('CSS.enable');
    return probe;
  }

  async rootNode() {
    const { root } = await this.cdp.send('DOM.getDocument', { depth: 0 });
    return root.nodeId;
  }

  async nodeIds(selector) {
    const { nodeIds } = await this.cdp.send('DOM.querySelectorAll', { nodeId: await this.rootNode(), selector });
    return nodeIds;
  }

  read(selector, options = {}) {
    return this.page.evaluate(inpage.readUnit, { selector, descendants: Boolean(options.descendants) });
  }

  async withState(selector, state, readFn) {
    const marker = `s${Date.now()}`;
    if (!(await this.page.evaluate(inpage.markAncestors, { selector, id: marker }))) return null;
    const target = await this.nodeIds(selector);
    const ancestors = state === 'focus-visible' ? [] : await this.nodeIds(`[data-pga="${marker}"]`);
    const ids = [...new Set([...ancestors, ...target])];
    for (const nodeId of ids) {
      await this.cdp.send('CSS.forcePseudoState', { nodeId, forcedPseudoClasses: STATE_CLASSES[state] }).catch(() => {});
    }
    try {
      await this.page.waitForTimeout(await this.settleTime(selector));
      return await readFn();
    } finally {
      for (const nodeId of ids) await this.cdp.send('CSS.forcePseudoState', { nodeId, forcedPseudoClasses: [] }).catch(() => {});
      await this.page.evaluate(inpage.clearMarks, ['data-pga']);
    }
  }

  // Wait for the longest declared transition, so the forced state is read after it settles.
  async settleTime(selector) {
    const ms = await this.page.evaluate((sel) => {
      const el = document.querySelector(sel);
      if (!el) return 0;
      const nodes = [el, ...el.querySelectorAll('*')].slice(0, 120);
      const toMs = (v) => Math.max(...v.split(',').map((x) => parseFloat(x) * (x.includes('ms') ? 1 : 1000)), 0);
      return Math.max(...nodes.map((n) => { const s = getComputedStyle(n); return toMs(s.transitionDuration) + toMs(s.transitionDelay); }), 0);
    }, selector);
    return Math.min(ms, 1200) + 80;
  }

  async renderedFonts(selector) {
    const [nodeId] = await this.nodeIds(selector);
    if (!nodeId) return null;
    const res = await this.cdp.send('CSS.getPlatformFontsForNode', { nodeId }).catch(() => null);
    if (!res || !res.fonts.length) return null;
    return res.fonts.map((f) => f.familyName).sort().join(' + ');
  }

  async sourceOf(selector, property) {
    const pattern = SOURCE_PROPERTY[property] || new RegExp(`^${property}$`);
    const [nodeId] = await this.nodeIds(selector);
    if (!nodeId) return null;
    const matched = await this.cdp.send('CSS.getMatchedStylesForNode', { nodeId }).catch(() => null);
    if (!matched) return null;
    const rules = [...(matched.matchedCSSRules || [])].reverse().map((m) => m.rule);
    for (const rule of rules) {
      const hit = rule.style.cssProperties.some((p) => pattern.test(p.name) && !p.disabled);
      if (hit && rule.style.range) return this.locate(rule.style.styleSheetId, rule.style.range.startLine, rule.selectorList.text);
    }
    const inline = matched.inlineStyle && matched.inlineStyle.cssProperties.some((p) => pattern.test(p.name));
    return inline ? 'inline style (set by a reference script)' : null;
  }

  locate(sheetId, line, selectorText) {
    const header = this.sheets.get(sheetId);
    const url = header ? header.sourceURL || this.page.url() : this.page.url();
    const relative = url.startsWith(this.base) ? url.slice(this.base.length) : url;
    return `${relative}:${line + 1 + (header && !header.sourceURL ? header.startLine : 0)} (${selectorText})`;
  }

  async keyframes() {
    const found = [];
    for (const [id, header] of this.sheets) {
      const { text } = await this.cdp.send('CSS.getStyleSheetText', { styleSheetId: id }).catch(() => ({ text: '' }));
      found.push(...parseKeyframes(text, header.sourceURL ? header.sourceURL.replace(this.base, '') : 'inline', header.startLine || 0));
    }
    return found;
  }

  // Which keyframes run on the page, how often, on what, and whether one runs on a page-sized wrapper (a screen fade).
  usedAnimations() {
    return this.page.evaluate(() => {
      const uses = new Map();
      const describe = (el) => {
        const label = el.getAttribute('data-screen-label');
        if (label) return `${el.tagName.toLowerCase()}[data-screen-label="${label}"]`;
        const cls = [...el.classList].slice(0, 2).join('.');
        return el.tagName.toLowerCase() + (cls ? `.${cls}` : '');
      };
      for (const el of document.querySelectorAll('*')) {
        for (const raw of getComputedStyle(el).animationName.split(',')) {
          const name = raw.trim();
          if (name === 'none') continue;
          const r = el.getBoundingClientRect();
          const use = uses.get(name) || { name, count: 0, sample: describe(el), pageLevel: false };
          use.count++;
          use.pageLevel = use.pageLevel || (r.width >= innerWidth * 0.9 && r.height >= innerHeight * 0.5);
          uses.set(name, use);
        }
      }
      return [...uses.values()];
    });
  }
}

function parseKeyframes(text, file, lineOffset) {
  const out = [];
  const re = /@(?:-webkit-)?keyframes\s+([\w-]+)\s*\{/g;
  let m;
  while ((m = re.exec(text))) {
    let depth = 1;
    let i = re.lastIndex;
    while (i < text.length && depth) { depth += text[i] === '{' ? 1 : text[i] === '}' ? -1 : 0; i++; }
    const line = text.slice(0, m.index).split('\n').length + lineOffset;
    out.push({ name: m[1], steps: normaliseSteps(text.slice(re.lastIndex, i - 1)), source: `${file}:${line}` });
  }
  return out;
}

function normaliseSteps(body) {
  const steps = {};
  const re = /([^{}]+)\{([^}]*)\}/g;
  let m;
  while ((m = re.exec(body))) {
    const decls = m[2].split(';').map((d) => d.trim().toLowerCase().replace(/\s+/g, ' ')).filter(Boolean).sort().join(';');
    for (const sel of m[1].split(',')) steps[sel.trim().replace('from', '0%').replace('to', '100%')] = decls;
  }
  return steps;
}

module.exports = { Probe, parseKeyframes };
