// The reference icon library (reference-icons/): used to name the glyph a reference icon is, so a
// finding can say "add-ads-pin" instead of "svg#0".
const fs = require('fs');
const path = require('path');
const inpage = require('./inpage');
const { shapeScore } = require('./icons');

const LIBRARY_DIR = path.join(__dirname, '..', 'reference-icons');
const NAMING_THRESHOLD = 0.95;

function loadSvgFiles() {
  if (!fs.existsSync(LIBRARY_DIR)) return [];
  return fs.readdirSync(LIBRARY_DIR).filter((f) => f.endsWith('.svg')).map((file) => ({ name: file.replace(/\.svg$/, ''), svg: fs.readFileSync(path.join(LIBRARY_DIR, file), 'utf8') }));
}

/** Rasterises every library icon once; returns a function that names a rasterised reference icon. */
async function loadIconNamer(browser) {
  const files = loadSvgFiles();
  if (!files.length) return () => null;
  const page = await browser.newPage();
  await page.setContent(`<body style="color:#000">${files.map((f) => `<div class="lib">${f.svg}</div>`).join('')}</body>`);
  const library = [];
  for (let i = 0; i < files.length; i++) {
    const [raster] = await page.evaluate(inpage.rasteriseIcons, `.lib:nth-child(${i + 1})`);
    if (raster) library.push({ name: files[i].name, bits: raster.bits });
  }
  await page.close();
  return (bits) => {
    const scored = library.map((l) => ({ name: l.name, score: shapeScore(bits, l.bits) || 0 })).sort((a, b) => b.score - a.score)[0];
    return scored && scored.score >= NAMING_THRESHOLD ? scored.name : null;
  };
}

module.exports = { loadIconNamer };
