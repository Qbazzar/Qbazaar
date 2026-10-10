// Pixel crops: screenshot the same component on both sides and diff them with pixelmatch.
const fs = require('fs');
const path = require('path');
const { PNG } = require('pngjs');
const pixelmatch = require('pixelmatch');

const PIXEL_THRESHOLD = 0.1;
const MASK_COLOUR = '#FF00FF';

function padTo(png, width, height) {
  if (png.width === width && png.height === height) return png;
  const out = new PNG({ width, height });
  out.data.fill(255);
  PNG.bitblt(png, out, 0, 0, png.width, png.height, 0, 0);
  return out;
}

/** Diffs two PNG buffers; returns the mismatch ratio and writes a diff image. */
function diffImages(refBuffer, targetBuffer, diffPath) {
  const a = PNG.sync.read(refBuffer);
  const b = PNG.sync.read(targetBuffer);
  const width = Math.max(a.width, b.width);
  const height = Math.max(a.height, b.height);
  const left = padTo(a, width, height);
  const right = padTo(b, width, height);
  const diff = new PNG({ width, height });
  const mismatched = pixelmatch(left.data, right.data, diff.data, width, height, { threshold: PIXEL_THRESHOLD });
  fs.mkdirSync(path.dirname(diffPath), { recursive: true });
  fs.writeFileSync(diffPath, PNG.sync.write(diff));
  return { ratio: mismatched / (width * height), sameSize: a.width === b.width && a.height === b.height, size: { ref: [a.width, a.height], target: [b.width, b.height] } };
}

async function cropOf(page, selector, maskSelectors = []) {
  const locator = page.locator(selector).first();
  const masks = ['img', 'video', 'canvas', 'iframe', ...maskSelectors].map((s) => page.locator(`${selector} ${s}`));
  await locator.scrollIntoViewIfNeeded().catch(() => {});
  return locator.screenshot({ mask: masks, maskColor: MASK_COLOUR, animations: 'disabled', caret: 'hide' }).catch(() => null);
}

module.exports = { diffImages, cropOf };
