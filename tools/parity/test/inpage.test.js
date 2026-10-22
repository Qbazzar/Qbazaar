// In-page readers (src/inpage.js, the sweep's twin finder) against small fixtures in a real Chrome. Skipped when
// Chrome cannot start (the gate itself needs it, so a machine that runs the gate runs these too).
const test = require('node:test');
const assert = require('node:assert/strict');
const inpage = require('../src/inpage');
const { findTwin } = require('../src/sweep');
const { ownerView } = require('../src/unit');
const { diffStyles } = require('../src/compare');

let browser = null;
let page = null;

test.before(async () => {
  try {
    const { chromium } = require('playwright');
    browser = await chromium.launch({ channel: 'chrome' });
    page = await browser.newPage({ viewport: { width: 744, height: 900 } });
  } catch (error) {
    browser = null;
  }
});

test.after(async () => { if (browser) await browser.close(); });

const html = (body) => `<!doctype html><html><head><style>body{margin:0;font:16px/24px Arial}</style></head><body>${body}</body></html>`;
const needsChrome = (t) => { if (!page) t.skip('Chrome is not available'); return Boolean(page); };
const textOf = (unit, key) => unit.descendants.find((d) => d.key.startsWith(key));

test('the box that owns a text: the prototype\'s template wrapper climbs to its heading, the target\'s own heading stays', async (t) => {
  if (!needsChrome(t)) return;
  await page.setContent(html(`
    <footer id="ref"><div class="qb-facc"><h3 style="margin:0;padding:16px 2px;display:flex;cursor:pointer;font-size:16px;font-weight:600"><span class="sc-interp">Information</span></h3></div></footer>
    <footer id="tgt"><details><summary style="padding:16px 2px;display:flex;cursor:pointer;font-size:16px;font-weight:600">Information</summary></details></footer>`));
  const ref = await page.evaluate(inpage.readUnit, { selector: '#ref', descendants: true });
  const tgt = await page.evaluate(inpage.readUnit, { selector: '#tgt', descendants: true });
  const [r, s] = [textOf(ref, 't:information'), textOf(tgt, 't:information')];
  assert.equal(r.box.tag, 'h3');
  assert.equal(r.box.styles.padding, '16px 2px 16px 2px');
  // The target's summary owns its box; the closed details around it holds the same text and adds nothing.
  assert.equal(s.box.tag, 'summary');
  const [rv, sv] = ownerView(r, s);
  assert.deepEqual(diffStyles(rv.styles, sv.styles, { kind: 'text', ownText: true }), []);
});

test('the climb stops at a box, at a control, and where the parent says more', async (t) => {
  if (!needsChrome(t)) return;
  await page.setContent(html(`
    <div id="a"><p style="margin:0 0 24px"><span class="sc-interp">Settings</span></p></div>
    <div id="b"><button style="padding:4px 8px"><span class="sc-interp">Save</span></button></div>
    <div id="c"><p style="margin:0 0 24px"><span class="sc-interp">Total</span> <b>12</b></p></div>`));
  const a = await page.evaluate(inpage.readUnit, { selector: '#a', descendants: true });
  assert.equal(textOf(a, 't:settings').box.styles.margin, '0px 0px 24px 0px');
  const b = await page.evaluate(inpage.readUnit, { selector: '#b', descendants: true });
  assert.equal(textOf(b, 't:save').box.tag, 'button');
  const c = await page.evaluate(inpage.readUnit, { selector: '#c', descendants: true });
  assert.equal(textOf(c, 't:total').box, undefined);
  // A plain text unit (its own element is the root) climbs only when asked to (text units pass hop).
  await page.setContent(html('<h2 style="margin:0 0 20px"><span id="t" class="sc-interp">Information</span></h2>'));
  assert.equal((await page.evaluate(inpage.readUnit, { selector: '#t', descendants: false })).box, undefined);
  assert.equal((await page.evaluate(inpage.readUnit, { selector: '#t', descendants: false, hop: true })).box.styles.margin, '0px 0px 20px 0px');
});

test('a wrapper that only adds a margin keeps the box\'s place: its margin is the box\'s', async (t) => {
  if (!needsChrome(t)) return;
  await page.setContent(html(`
    <div id="ref" style="display:flex;gap:12px"><span class="qb-btn" style="margin-top:8px;padding:10px 20px;background:#fff;border:1px solid #eee"><span class="sc-interp">Lusail</span></span><span class="qb-btn" style="padding:10px 20px;background:#fff">Doha</span></div>
    <ul id="tgt" style="display:flex;gap:12px;list-style:none;margin:0;padding:0"><li style="margin-top:8px"><a href="#" style="display:inline-block;padding:10px 20px;background:#fff;border:1px solid #eee">Lusail</a></li><li><a href="#" style="display:inline-block;padding:10px 20px;background:#fff">Doha</a></li></ul>`));
  const ref = textOf(await page.evaluate(inpage.readUnit, { selector: '#ref', descendants: true }), 't:lusail');
  const tgt = textOf(await page.evaluate(inpage.readUnit, { selector: '#tgt', descendants: true }), 't:lusail');
  assert.equal(ref.box.styles.margin, '8px 0px 0px 0px');
  assert.equal(tgt.box.tag, 'a');
  assert.equal(tgt.box.styles.margin, '8px 0px 0px 0px');
  assert.equal(tgt.box.styles.padding, '10px 20px 10px 20px');
});

test('a label spaced by its own margin and one spaced by the form\'s gap leave the same space', async (t) => {
  if (!needsChrome(t)) return;
  const form = (gap) => `
    <form id="ref" style="padding:20px"><input style="display:block;height:40px"><label style="display:block;margin:18px 0 8px">Email</label><input style="display:block;height:40px"></form>
    <form id="tgt" style="padding:20px;display:flex;flex-direction:column;gap:${gap}px"><input style="height:40px"><div style="display:flex;flex-direction:column;gap:8px"><label>Email</label><input style="height:40px"></div></form>`;
  const read = async () => ['#ref', '#tgt'].map(async (s) => textOf(await page.evaluate(inpage.readUnit, { selector: s, descendants: true }), 't:email'));
  await page.setContent(html(form(18)));
  const [r, s] = await Promise.all(await read());
  assert.equal(r.styles['margin-space'], '18px 8px');
  assert.equal(s.styles['margin-space'], '18px 8px');
  assert.deepEqual(diffStyles(r.styles, s.styles, { kind: 'text', ownText: true }).map((d) => d.property), []);
  // The form really spaces the label less: the margin difference is reported.
  await page.setContent(html(form(12)));
  const [r2, s2] = await Promise.all(await read());
  assert.deepEqual(diffStyles(r2.styles, s2.styles, { kind: 'text', ownText: true }).map((d) => d.property), ['margin']);
});

test('an invisible sizer is not a text: the combobox reads its visible label', async (t) => {
  if (!needsChrome(t)) return;
  await page.setContent(html(`
    <form id="hero"><div id="cb" role="combobox" style="display:grid;padding:10px 14px;color:#212121;font-size:16px">
      <span style="grid-area:1/1;color:#bdbdbd;font-size:20px">Choose Category</span>
      <span style="grid-area:1/1;visibility:hidden">Choose Category</span>
    </div></form>`));
  const form = await page.evaluate(inpage.readUnit, { selector: '#hero', descendants: true });
  const labels = form.descendants.filter((d) => d.key.startsWith('t:choose category'));
  assert.equal(labels.length, 1);
  assert.equal(labels[0].styles.color, 'rgb(189, 189, 189)');
  // The visible label's own box is the combobox (the sizer does not make the combobox say more).
  assert.equal(labels[0].box.tag, 'div');
  assert.equal(labels[0].box.styles.padding, '10px 14px 10px 14px');
  // The combobox read as a unit of its own writes its text through that label.
  const unit = await page.evaluate(inpage.readUnit, { selector: '#cb', descendants: true });
  assert.deepEqual(unit.textCarrier.styles.color, 'rgb(189, 189, 189)');
  assert.equal(unit.textCarrier.styles['font-size'], '20px');
});

test('icons: hidden svgs are left out, extra roots and CSS-drawn glyphs count, flags are named', async (t) => {
  if (!needsChrome(t)) return;
  const glyph = '<svg width="21" height="21" viewBox="0 0 24 24"><path d="M3 3h18v18H3z" stroke="#333" fill="none"/></svg>';
  await page.setContent(html(`
    <header id="h" style="height:88px"><button style="display:none">${glyph}</button><button aria-label="Language">${glyph}<svg width="20" height="14" viewBox="0 0 60 40"><rect width="60" height="40" fill="#012169"/><path d="M0 0l60 40" stroke="#fff"/></svg></button></header>
    <button id="bell" style="position:fixed;top:14px;right:60px;width:44px;height:44px">${glyph}</button>
    <button id="burger" style="position:fixed;top:14px;right:8px;width:44px;height:44px"><span style="display:block;width:20px;height:2px;background:#333"></span></button>
    <button class="qb-lang" id="pill"><svg width="15" height="15" viewBox="0 0 24 24"><rect width="24" height="24" fill="#364153"/></svg>EN</button>`));
  const own = await page.evaluate(inpage.rasteriseIcons, '#h');
  assert.deepEqual(own.map((i) => i.flag), [false, 'flag']);
  const withBar = await page.evaluate(inpage.rasteriseIcons, { selector: '#h', extra: '#bell, #burger' });
  assert.equal(withBar.filter((i) => !i.flag).length, 2);
  assert.equal(await page.evaluate(inpage.countDrawnIcons, { selector: '#h', extra: '#bell, #burger' }), 1);
  assert.equal(await page.evaluate(inpage.countDrawnIcons, '#h'), 0);
  assert.deepEqual((await page.evaluate(inpage.rasteriseIcons, '#pill')).map((i) => i.flag), ['static']);
});

test('a closed sheet pinned below the screen holds no text a person reads', async (t) => {
  if (!needsChrome(t)) return;
  await page.setContent(html(`
    <main><h1>Cars</h1><button>Filter</button></main>
    <div style="position:fixed;left:0;right:0;top:0;transform:translateY(120vh)"><button>Apply Filter</button></div>
    <div style="position:fixed;left:0;right:0;bottom:0"><button>Show Results</button></div>`));
  const keys = (await page.evaluate(inpage.collectTexts)).map((r) => r.key);
  assert.ok(keys.includes('filter'));
  assert.ok(keys.includes('show results'));
  assert.ok(!keys.includes('apply filter'));
});

test('sweep twins: a field wrapper pairs with the box around the same field, a page chip with one in its state', async (t) => {
  if (!needsChrome(t)) return;
  await page.setContent(html(`
    <main>
      <label id="search" style="display:flex;border:1px solid #ededed;border-radius:12px;padding:12px 14px"><svg width="18" height="18"></svg><input placeholder="Search" style="border:0"></label>
      <nav aria-label="Pagination"><a href="#" aria-current="page" style="background:rgb(243,128,87)">1</a><a href="#" style="background:#fff">2</a><a href="#" aria-label="Next page" style="background:#fff">›</a></nav>
    </main>`));
  const base = { text: '', label: '', landmark: 'main', ordinal: 0, centre: null, clickable: 'a, button, [role=button], [role=link], label, input', toggle: false };
  const wrapper = await page.evaluate(findTwin, { ...base, roleSelector: 'input, textarea, select', kind: 'input', field: { placeholder: 'search', label: '' } });
  assert.match(wrapper, /^<label id="search"/);
  const chip = await page.evaluate(findTwin, { ...base, text: '1', kind: 'pagination', selected: false });
  assert.match(chip, />2<\/a>$/);
  // A reference chip in the current state keeps the current target chip.
  assert.match(await page.evaluate(findTwin, { ...base, text: '1', kind: 'pagination', selected: true }), /aria-current="page"/);
  // Without a field the bare input is still found by its role and order, as before.
  assert.match(await page.evaluate(findTwin, { ...base, roleSelector: 'input, textarea, select', kind: 'input' }), /^<input/);
});
