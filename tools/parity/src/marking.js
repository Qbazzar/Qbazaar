// Marks the first visible matches of each component selector on a page. Selectors go through
// Playwright, so text selectors (:has-text, :text-is) work next to plain CSS.
const MAX_REPEATED = 3;

async function markComponents(page, components) {
  const found = [];
  for (const comp of components) {
    const indexes = await page.locator(comp.selector).evaluateAll((nodes, { name, max }) => {
      const visible = (el) => {
        const r = el.getBoundingClientRect();
        const s = getComputedStyle(el);
        return r.width > 0 && r.height > 0 && s.visibility !== 'hidden' && s.display !== 'none';
      };
      return nodes.filter(visible).slice(0, max).map((el, i) => { el.setAttribute('data-pgc', `${name}~${i}`); return i; });
    }, { name: comp.name, max: comp.all ? MAX_REPEATED : 1 }).catch(() => []);
    for (const index of indexes) found.push({ id: `${comp.name}~${index}`, name: comp.name, index });
  }
  return found;
}

module.exports = { markComponents };
