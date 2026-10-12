// A tiny vocabulary for scenarios. Every selector is { ref, target } (or one string for both sides),
// and every step runs identically on the reference and on the target.
const pair = (sel) => (typeof sel === 'string' ? { ref: sel, target: sel } : sel);

const click = (selector, options = {}) => ({ type: 'click', selector: pair(selector), options });
const hover = (selector) => ({ type: 'hover', selector: pair(selector) });
const type = (selector, text) => ({ type: 'type', selector: pair(selector), text });
const press = (key) => ({ type: 'press', key });
/** Clicks a page coordinate: closes things by clicking outside them. */
const clickAt = (x, y) => ({ type: 'clickAt', x, y });
/** Gives a file input a generated PNG, so photo flows can run without a file on disk. */
const upload = (selector) => ({ type: 'upload', selector: pair(selector), file: samplePng });
/** Picks a generated image: the reference clicks `ref` to open its chooser, the target sets `target` (a file input). */
const chooseFile = (selector) => ({ type: 'chooseFile', selector: pair(selector), file: samplePng });
const wait = (ms) => ({ type: 'wait', ms });
const scrollTo = (selector) => ({ type: 'scrollTo', selector: pair(selector) });

/** Compares what is on screen at this point: existence, text, attributes, styles, crop. */
const observe = (name, selector, what = {}) => ({ type: 'observe', name, selector: pair(selector), what: { exists: true, text: true, ...what } });

/** Records the motion of an element while `trigger` runs, then compares duration and easing. */
const motion = (name, selector, trigger, durationMs = 700) => ({ type: 'motion', name, selector: pair(selector), trigger, durationMs });

/** Compares the URL path reached so far with the route the reference page maps to. */
const expectRoute = (name, referencePage) => ({ type: 'route', name, referencePage });

/** Fails when the target left the page it was opened on. */
const stay = (name) => ({ type: 'stay', name });

function samplePng() {
  const { PNG } = require('pngjs');
  const png = new PNG({ width: 400, height: 300 });
  for (let i = 0; i < png.data.length; i += 4) {
    const x = (i / 4) % 400;
    png.data.set([x % 256, (i / 4 / 400) % 256, 128, 255], i);
  }
  return PNG.sync.write(png);
}

function scenario(definition) {
  const defaults = { viewports: [1440, 390], writes: false, login: false, steps: [] };
  return { ...defaults, ...definition };
}

module.exports = { scenario, click, clickAt, hover, type, upload, chooseFile, press, wait, scrollTo, observe, motion, expectRoute, stay };
