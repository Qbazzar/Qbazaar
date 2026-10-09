// Rebuilds every raster/vector brand icon from the Q mark inside public/brand/qb-logo.svg.
// Run: node scripts/generate-brand-icons.mjs   (outputs are committed; the script is not part of the build)
import { readFileSync, writeFileSync } from 'node:fs';
import sharp from 'sharp';

const MARK_GROUPS = [13, 25]; // zero-based slice of the <g> layers that draw the Q + awning
const MARK = { width: 41, height: 48 };
const BACKGROUND = '#ffffff';

const layers = readFileSync('public/brand/qb-logo.svg', 'utf8')
  .split('\n')
  .filter((line) => line.startsWith('<g '))
  .slice(...MARK_GROUPS)
  .join('');

/** A square icon: the mark centred at `markHeight` (fraction of the side) on the brand background. */
function iconSvg({ size, markHeight, radius = 0 }) {
  const scale = (size * markHeight) / MARK.height;
  const x = (size - MARK.width * scale) / 2;
  const y = (size - MARK.height * scale) / 2;
  return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${size} ${size}" width="${size}" height="${size}">`
    + `<rect width="${size}" height="${size}" rx="${radius}" fill="${BACKGROUND}"/>`
    + `<g transform="translate(${x} ${y}) scale(${scale})">${layers}</g></svg>`;
}

const png = (svg, size) => sharp(Buffer.from(svg), { density: 384 }).resize(size, size).png().toBuffer();

/** ICO container holding PNG frames (supported by every current browser). */
function ico(frames) {
  const header = Buffer.alloc(6 + frames.length * 16);
  header.writeUInt16LE(1, 2);
  header.writeUInt16LE(frames.length, 4);
  let offset = header.length;
  frames.forEach(({ size, data }, i) => {
    const at = 6 + i * 16;
    header.writeUInt8(size, at);
    header.writeUInt8(size, at + 1);
    header.writeUInt16LE(1, at + 4);
    header.writeUInt16LE(32, at + 6);
    header.writeUInt32LE(data.length, at + 8);
    header.writeUInt32LE(offset, at + 12);
    offset += data.length;
  });
  return Buffer.concat([header, ...frames.map((f) => f.data)]);
}

const tab = iconSvg({ size: 256, markHeight: 0.9, radius: 56 });
writeFileSync('app/icon.svg', iconSvg({ size: 64, markHeight: 0.86, radius: 14 }));
writeFileSync('app/apple-icon.png', await png(iconSvg({ size: 180, markHeight: 0.78 }), 180));
writeFileSync('public/icons/icon-192.png', await png(iconSvg({ size: 512, markHeight: 0.86, radius: 112 }), 192));
writeFileSync('public/icons/icon-512.png', await png(iconSvg({ size: 512, markHeight: 0.86, radius: 112 }), 512));
// Maskable: full-bleed, mark inside the 80% safe circle.
writeFileSync('public/icons/icon-maskable-512.png', await png(iconSvg({ size: 512, markHeight: 0.58 }), 512));
const frames = await Promise.all([16, 32, 48].map(async (size) => ({ size, data: await png(tab, size) })));
writeFileSync('app/favicon.ico', ico(frames));
