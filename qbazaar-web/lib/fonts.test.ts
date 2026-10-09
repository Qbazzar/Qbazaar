import { existsSync, readdirSync, readFileSync } from 'node:fs';
import { join } from 'node:path';
import { describe, expect, it } from 'vitest';

const fontsDir = join(__dirname, '..', 'app', 'fonts');
const fontsTs = readFileSync(join(fontsDir, 'index.ts'), 'utf8');
const tokensCss = readFileSync(join(__dirname, '..', 'styles', 'design-tokens.css'), 'utf8');

interface FontCall {
  name: string;
  declaredFamily: string | null;
  hasFallback: boolean;
  files: string[];
}

const calls: FontCall[] = [...fontsTs.matchAll(/const (\w+) = localFont\(\{([\s\S]*?)\n\}\);/g)].map(([, name, body]) => ({
  name,
  declaredFamily: body.match(/prop: 'font-family', value: '([^']+)'/)?.[1] ?? null,
  hasFallback: !body.includes('adjustFontFallback: false'),
  files: [...body.matchAll(/'\.\/([^']+\.woff2)'/g)].map((match) => match[1]),
}));

/** Families as next/font names them: the const, unless the call declares another face's name. */
const families = new Set(calls.filter((call) => !call.declaredFamily).map((call) => call.name));
const fallbacks = new Set(calls.filter((call) => !call.declaredFamily && call.hasFallback).map((call) => `${call.name} Fallback`));

const qbStacks = [...tokensCss.matchAll(/--(font-qb[\w-]*): ([^;]+);/g)].map(([, token, stack]) => ({
  token,
  names: [...stack.matchAll(/"([^"]+)"/g)].map((match) => match[1]),
}));

describe('app/fonts', () => {
  it('ships every font file it loads', () => {
    for (const file of calls.flatMap((call) => call.files)) {
      expect(existsSync(join(fontsDir, file)), file).toBe(true);
    }
  });

  it('adds extra weights and subsets to an existing family', () => {
    for (const call of calls.filter((c) => c.declaredFamily)) {
      expect(families, `${call.name} joins ${call.declaredFamily}`).toContain(call.declaredFamily);
    }
  });

  it('declares every family and fallback the font-qb stacks name', () => {
    expect(qbStacks.map((stack) => stack.token)).toEqual(
      expect.arrayContaining(['font-qb', 'font-qb-label', 'font-qb-script', 'font-qb-script-compact', 'font-qb-brand']),
    );
    for (const { token, names } of qbStacks) {
      for (const name of names) {
        expect(families.has(name) || fallbacks.has(name), `${token}: "${name}"`).toBe(true);
      }
    }
  });

  it('keeps the old Cairo, DM Sans and Instrument Serif faces out of the app', () => {
    const globalsCss = readFileSync(join(__dirname, '..', 'app', 'globals.css'), 'utf8');
    expect(`${fontsTs}
${globalsCss}`).not.toMatch(/cairo|dm-?sans|instrument/i);
  });
});

describe('Arabic typography', () => {
  const arabicBlock = tokensCss.match(/:root:lang\(ar\) \{([^}]*)\}/)?.[1] ?? '';
  const arabicStacks = [...arabicBlock.matchAll(/--(font-qb[\w-]*): ([^;]+);/g)].map(([, token, stack]) => ({
    token,
    names: [...stack.matchAll(/"([^"]+)"/g)].map((match) => match[1]),
  }));

  it('restacks every Latin-first font-qb token with Noto Kufi Arabic first on Arabic pages', () => {
    const latinFirst = [...new Set(qbStacks.map(({ token }) => token))].filter((token) => token !== 'font-qb-brand');
    expect(arabicStacks.map(({ token }) => token).sort()).toEqual([...latinFirst].sort());
    for (const { token, names } of arabicStacks) {
      expect(names[0], token).toBe('notoKufiArabic');
      expect(names.length, token).toBeGreaterThan(2);
    }
  });

  it('loads Noto Kufi Arabic as one preloaded variable file covering weights 400-700', () => {
    const noto = fontsTs.match(/const notoKufiArabic = localFont\(\{([\s\S]*?)\n\}\);/)?.[1] ?? '';
    expect(noto).toContain("'./noto-kufi-arabic-variable.woff2'");
    expect(noto).toContain("weight: '100 900'");
    expect(noto).not.toContain('preload: false');
    expect(noto).not.toContain('adjustFontFallback: false');
    expect(existsSync(join(fontsDir, 'OFL-NotoKufiArabic.txt'))).toBe(true);
  });

  it('no longer ships IBM Plex Sans Arabic', () => {
    expect(readdirSync(fontsDir).filter((file) => /plex/i.test(file))).toEqual([]);
    expect(`${fontsTs}\n${tokensCss}`).not.toMatch(/plex/i);
  });

  it('keeps the Latin faces behind it so Latin text and digits do not change', () => {
    for (const { token, names } of arabicStacks) {
      const latin = qbStacks.find((stack) => stack.token === token)?.names[0];
      expect(names, token).toContain(latin);
    }
  });

  it('keeps Latin letter-spacing off Arabic text', () => {
    expect(tokensCss).toMatch(/:lang\(ar\) :not\(:lang\(en\)\) \{\s*letter-spacing: normal;/);
  });
});
