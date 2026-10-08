import { existsSync, readFileSync } from 'node:fs';
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
