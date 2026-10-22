// Behaviour scenarios: the same steps run in lockstep on the reference and the target, and every
// observation is compared (existence, text, attributes, styles, a screenshot crop, motion, routes).
const { openSide, closeSide } = require('./sides');
const { UnitComparer } = require('./unit');
const { recordSequence, compareSequences } = require('./motion');
const { selectPages } = require('./pages');
const { withKeptCopy } = require('./matching');

// The prototype ignores Escape; the target closes dialogs, drawers and panels on it (keyboard access, accepted).
const ESCAPE_RULE = 'escape-key';
const KEPT_COPY_RULE = 'copy-5';

const SETTLE_MS = 400;
const STEP_TIMEOUT_MS = 6000;
const NAVIGATION_SETTLE_MS = 2000;
// The reference draws some icons as text characters (a modal's ✕, a menu row's ›); the target draws them as SVGs,
// which the icon checks compare. Copy is compared without them.
const TEXT_GLYPHS = /[✕✖×›‹»«→←✓✔▾▸]/g;
const TEXT_CAP = 300;

/**
 * The copy an observation compares: glyph icons dropped, data masked, spaces collapsed. `textMask` is a regex source
 * (matches read as "…") or a function from text to text.
 */
function copyOf(text, mask) {
  const unglyphed = String(text || '').replace(TEXT_GLYPHS, ' ');
  const masked = typeof mask === 'function' ? mask(unglyphed) : mask ? unglyphed.replace(new RegExp(mask, 'g'), '…') : unglyphed;
  return masked.replace(/\s+/g, ' ').trim().slice(0, TEXT_CAP);
}

const OBSERVED_ATTRIBUTES = ['aria-expanded', 'aria-selected', 'aria-checked', 'aria-pressed', 'aria-current', 'aria-hidden', 'disabled', 'checked', 'open', 'hidden'];

// Playwright errors carry a call log; the first lines say why a click was refused.
const compact = (message) => message.split(String.fromCharCode(10)).map((l) => l.trim()).join(" ").slice(0, 420);

const RECHECK_PAUSE_MS = 900;
const NAVIGATED = /Execution context was destroyed|navigation/i;

const SIDE_KEY = { reference: 'ref', target: 'target' };
const describe = (step) => `${step.type}${step.selector ? ` ${JSON.stringify(step.selector)}` : ''}${step.key ? ` ${step.key}` : ''}`;

function locate(page, step, key) {
  return page.locator(step.selector[key]).filter({ visible: true }).first();
}

// The reference opens a file chooser from a click; the target keeps a file input on the page.
async function chooseFile(side, step, key) {
  const file = { name: 'photo.png', mimeType: 'image/png', buffer: step.file() };
  if (side.name === 'target') return side.page.locator(step.selector.target).first().setInputFiles(file);
  const [chooser] = await Promise.all([side.page.waitForEvent('filechooser', { timeout: STEP_TIMEOUT_MS }), locate(side.page, step, key).click({ timeout: STEP_TIMEOUT_MS })]);
  return chooser.setFiles(file);
}

async function runStep(side, step) {
  const key = SIDE_KEY[side.name];
  const { page } = side;
  switch (step.type) {
    case 'click': await locate(page, step, key).click({ timeout: STEP_TIMEOUT_MS, ...step.options }); break;
    case 'hover': await locate(page, step, key).hover({ timeout: STEP_TIMEOUT_MS }); break;
    case 'type': await locate(page, step, key).click({ timeout: STEP_TIMEOUT_MS }); await page.keyboard.type(step.text, { delay: 40 }); break;
    case 'clickAt': await page.mouse.click(step.x, step.y); break;
    case 'press': await page.keyboard.press(step.key); break;
    case 'upload': await page.locator(step.selector[key]).first().setInputFiles({ name: 'photo.png', mimeType: 'image/png', buffer: step.file() }); break;
    case 'chooseFile': await chooseFile(side, step, key); break;
    case 'scrollTo': await locate(page, step, key).scrollIntoViewIfNeeded({ timeout: STEP_TIMEOUT_MS }); break;
    case 'wait': await page.waitForTimeout(step.ms); return;
    default: throw new Error(`unknown step ${step.type}`);
  }
  await page.waitForTimeout(SETTLE_MS);
}

// A page that is still settling (the reference renders its template after load) can refuse the first try. A
// refused click never happened, so trying once more after a pause cannot press anything twice.
const RETRY_PAUSE_MS = 800;
async function runStepWithRetry(side, step) {
  try {
    await runStep(side, step);
  } catch (error) {
    await side.page.waitForTimeout(RETRY_PAUSE_MS);
    await runStep(side, step);
  }
}

// Marks the first visible match so later reads and crops address the same element.
async function mark(side, step, markName) {
  const selector = step.selector[SIDE_KEY[side.name]];
  await side.page.evaluate((name) => document.querySelectorAll(`[data-pgs="${name}"]`).forEach((e) => e.removeAttribute('data-pgs')), markName);
  // Locators understand Playwright's text selectors (:has-text, :text-is) as well as plain CSS.
  return side.page.locator(selector).evaluateAll((all, { markName, attrs, anywhere, props }) => {
    const visible = (el) => { const r = el.getBoundingClientRect(); const s = getComputedStyle(el); const pinned = (() => { for (let n = el; n; n = n.parentElement) if (getComputedStyle(n).position === 'fixed') return true; return false; })();
      const withinX = r.right > 0 && r.left < innerWidth;
      const withinY = pinned ? r.bottom > 0 && r.top < innerHeight : r.bottom + scrollY > 0 && r.top + scrollY < Math.max(document.body.scrollHeight, document.documentElement.scrollHeight);
      const onScreen = withinX && withinY; return anywhere || (r.width > 0 && r.height > 0 && onScreen && s.visibility !== 'hidden' && s.display !== 'none' && Number(s.opacity) > 0.01); };
    const nodes = all.filter(visible);
    if (!nodes.length) return { count: 0 };
    nodes[0].setAttribute('data-pgs', markName);
    const attributes = Object.fromEntries(attrs.filter((a) => nodes[0].hasAttribute(a)).map((a) => [a, nodes[0].getAttribute(a)]));
    if (nodes[0].getAttribute('aria-disabled') === 'true' || nodes[0].disabled) attributes.disabled = 'true';
    const properties = Object.fromEntries(props.map((p) => [p, String(nodes[0][p])]));
    // Text kept for screen readers only (a dialog's hidden title, a fieldset's legend) is not copy anyone sees.
    const hidden = (el) => { const s = getComputedStyle(el); const r = el.getBoundingClientRect();
      return (s.position === 'absolute' && (s.clip === 'rect(0px, 0px, 0px, 0px)' || s.clipPath === 'inset(50%)')) || (r.width <= 1 && r.height <= 1 && s.overflow === 'hidden'); };
    const hiddenOnes = [...nodes[0].querySelectorAll('*')].filter(hidden);
    const outermost = hiddenOnes.filter((el) => !hiddenOnes.some((other) => other !== el && other.contains(el)));
    const shownText = outermost.reduce((text, el) => (el.innerText ? text.replace(el.innerText, ' ') : text), nodes[0].innerText);
    return { count: nodes.length, properties, text: shownText.trim().replace(/\s+/g, ' ').slice(0, 1200), attributes, focused: document.activeElement === nodes[0] || nodes[0].contains(document.activeElement) };
  }, { markName, attrs: OBSERVED_ATTRIBUTES, anywhere: Boolean(step.what.anywhere), props: step.what.props || [] }).catch(() => ({ count: 0 }));
}

class ScenarioRun {
  constructor(env, definition, viewport, sides) {
    this.env = env;
    this.definition = definition;
    this.sides = sides;
    this.base = { page: definition.page, viewport: viewport.width, locale: env.config.locale };
    this.comparer = new UnitComparer({ ref: sides.reference.probe, target: sides.target.probe, findings: env.findings, base: this.base, nameIcon: env.nameIcon });
    this.comparer.stateLabel = `scenario:${definition.id}`;
  }

  emit(extra) {
    return this.env.findings.add({ ...this.base, state: `scenario:${this.definition.id}`, ...extra });
  }

  accept(rule, extra) {
    this.env.findings.accept(rule, { ...this.base, ...extra });
  }

  async step(step) {
    if (step.type === 'observe') return this.observe(step);
    if (step.type === 'motion') return this.motion(step);
    if (step.type === 'route') return this.route(step);
    if (step.type === 'stay') return this.stay(step);
    // What is observed right after Escape is where the target may close and the prototype stay open.
    this.afterEscape = step.type === 'press' && step.key === 'Escape';
    for (const side of [this.sides.reference, this.sides.target]) {
      try {
        await runStepWithRetry(side, step);
      } catch (error) {
        const onTarget = side.name === 'target';
        this.emit({ component: `scenario:${this.definition.id}`, category: 'behaviour', severity: onTarget ? 'high' : 'low',
          property: 'step-failed', ref: onTarget ? 'step works on the reference' : describe(step), target: onTarget ? `cannot ${describe(step)}` : 'not run',
          note: onTarget ? `target: ${compact(error.message)}` : `the reference itself failed this step (${compact(error.message)}), fix the scenario selector` });
        return false;
      }
    }
    return true;
  }

  // An element can still be sliding in (a drawer under a busy CPU), and a click can start a client-side navigation
  // that replaces the page under a read. Both settle: the observation looks once more after a pause.
  async observe(step) {
    try {
      return await this.observeOnce(step, { recheck: true });
    } catch (error) {
      if (!NAVIGATED.test(error.message)) throw error;
      await this.settle();
      return this.observeOnce(step, { recheck: false });
    }
  }

  async settle() {
    await Promise.all([this.sides.reference, this.sides.target].map((side) => side.page.waitForLoadState('load').catch(() => {})));
    await this.sides.target.page.waitForTimeout(RECHECK_PAUSE_MS);
  }

  async observeOnce(step, { recheck }) {
    const markName = `${this.definition.id}-${step.name}`;
    const markBoth = async () => [await mark(this.sides.reference, step, markName), await mark(this.sides.target, step, markName)];
    let [ref, target] = await markBoth();
    if (recheck && Boolean(ref.count) !== Boolean(target.count)) {
      await this.settle();
      [ref, target] = await markBoth();
    }
    const component = `scenario:${this.definition.id}:${step.name}`;
    if (this.env.config.verbose) console.log(`  observe ${step.name}: reference ${ref.count}, target ${target.count}`);
    if (!ref.count && !target.count) {
      // Absent on both sides is a legitimate "closed" check, but it is also what a broken selector looks like.
      this.env.warnings.push({ what: `scenario ${this.definition.id} @${this.base.viewport}`, reason: `observation "${step.name}" matched nothing on either side` });
      return true;
    }
    if (!ref.count || !target.count) {
      const exists = { component, property: 'exists', ref: ref.count ? 'visible' : 'not visible', target: target.count ? 'visible' : 'not visible' };
      if (this.afterEscape && ref.count) { this.accept(ESCAPE_RULE, exists); return true; }
      this.emit({ ...exists, category: 'behaviour', severity: 'high', note: step.selector.ref + ' vs ' + step.selector.target });
      return true;
    }
    this.compareFacts(component, step, ref, target);
    const unit = { dedupeKey: JSON.stringify(step.selector), name: component, refSel: `[data-pgs="${markName}"]`, tgtSel: `[data-pgs="${markName}"]`, states: false, icons: step.what.icons !== false, mask: step.what.mask, fixedSize: step.what.fixedSize };
    if (step.what.styles !== false) await this.comparer.compare(unit);
    if (step.what.crop) await this.comparer.pixelDiff(unit, this.env.outDir);
    return true;
  }

  // A copy difference that is only an accepted one: the observation's own rule (what.accepted.text reduces both copies
  // to what the rule leaves to compare, the language list's heading), or the owner's kept strings (reference-text.json).
  acceptedCopyRule(step, refText, refCopy, targetCopy) {
    const accepted = step.what.accepted;
    if (accepted && accepted.text && accepted.text(refCopy) === accepted.text(targetCopy)) return accepted.rule;
    return copyOf(withKeptCopy(refText, { id: this.definition.page }), step.what.textMask) === targetCopy ? KEPT_COPY_RULE : null;
  }

  compareFacts(component, step, ref, target) {
    const [refCopy, targetCopy] = [copyOf(ref.text, step.what.textMask), copyOf(target.text, step.what.textMask)];
    if (step.what.text && step.what.text !== 'skip' && refCopy !== targetCopy) {
      const text = { component, property: 'text', ref: refCopy, target: targetCopy };
      const rule = this.acceptedCopyRule(step, ref.text, refCopy, targetCopy);
      if (rule) this.accept(rule, text); else this.emit({ ...text, category: 'behaviour', severity: 'medium' });
    }
    if (step.what.count && ref.count !== target.count) {
      const count = { component, property: 'count', ref: String(ref.count), target: String(target.count) };
      if (step.what.accepted && step.what.accepted.count) this.accept(step.what.accepted.rule, count);
      else this.emit({ ...count, category: 'behaviour', severity: 'medium' });
    }
    for (const attr of OBSERVED_ATTRIBUTES) {
      const [a, b] = [ref.attributes[attr], target.attributes[attr]];
      // Only attributes the reference sets count: extra ARIA state on the target is an improvement, not a gap.
      if (step.what.attributes && a !== undefined && a !== b) {
        this.emit({ component, category: 'behaviour', severity: 'medium', property: attr, ref: String(a), target: String(b) });
      }
    }
    for (const prop of step.what.props || []) {
      if (ref.properties[prop] !== target.properties[prop]) this.emit({ component, category: 'behaviour', severity: 'high', property: prop, ref: ref.properties[prop], target: target.properties[prop] });
    }
    if (step.what.focus && ref.focused !== target.focused) {
      this.emit({ component, category: 'behaviour', severity: 'medium', property: 'focus', ref: String(ref.focused), target: String(target.focused) });
    }
  }

  async motion(step) {
    const sequences = {};
    for (const side of [this.sides.reference, this.sides.target]) {
      const key = SIDE_KEY[side.name];
      try {
        sequences[side.name] = await recordSequence(side.page, step.selector[key], () => runStep(side, step.trigger), step.durationMs);
      } catch (error) {
        sequences[side.name] = { moved: false, duration: 0, curve: [], error: error.message };
      }
    }
    for (const p of compareSequences(sequences.reference, sequences.target)) {
      this.emit({ component: `scenario:${this.definition.id}:${step.name}`, category: 'motion', severity: 'medium', ...p });
    }
    return true;
  }

  // Pressing a control that only opens UI must not navigate away.
  stay(step) {
    const left = new URL(this.sides.target.page.url()).pathname !== new URL(this.sides.target.landed.url).pathname;
    if (!left) return true;
    this.emit({ component: `scenario:${this.definition.id}:${step.name}`, category: 'behaviour', severity: 'high', property: 'navigates',
      ref: 'stays on the page', target: `navigated to ${new URL(this.sides.target.page.url()).pathname}` });
    return false;
  }

  async route(step) {
    await this.sides.target.page.waitForTimeout(NAVIGATION_SETTLE_MS);
    const expected = selectPages({ pages: null, viewports: [{ width: this.base.viewport }] }).find((p) => p.reference === `${step.referencePage}.html`);
    const reached = new URL(this.sides.target.page.url());
    const wanted = expected ? expected.route.split('?')[0] : null;
    const expectedPath = wanted && wanted.replace(/\/$/, '') !== '' ? wanted : '/';
    if (!expected || reached.pathname.replace(/\/$/, '') === expectedPath.replace(/\/$/, '')) return true;
    this.emit({ component: `scenario:${this.definition.id}:${step.name}`, category: 'flows', severity: 'high', property: 'route', ref: `${step.referencePage}.html -> ${expectedPath}`, target: reached.pathname });
    return true;
  }
}

async function runScenario(env, definition, viewport) {
  const pageDef = selectPages({ pages: [definition.page], viewports: [viewport] })[0];
  const sides = {
    reference: { name: 'reference', ...(await openSide(env, 'reference', pageDef, viewport)) },
    target: { name: 'target', ...(await openSide(env, 'target', { ...pageDef, login: pageDef.login || definition.login, ...(definition.targetRoute ? { route: definition.targetRoute } : {}) }, viewport)) },
  };
  const run = new ScenarioRun(env, definition, viewport, sides);
  try {
    for (const step of definition.steps) if ((await run.step(step)) === false) break;
  } finally {
    await closeSide(env, sides.reference);
    await closeSide(env, sides.target);
  }
}

module.exports = { runScenario, copyOf, ScenarioRun };
