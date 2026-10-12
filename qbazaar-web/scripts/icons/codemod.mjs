// Moves lucide-react imports onto the design's icon registry (@/components/design-system/icons).
//
//   node scripts/icons/codemod.mjs <files or folders...>            dry run: prints what would change
//   node scripts/icons/codemod.mjs --write <files or folders...>    rewrites the files
//   node scripts/icons/codemod.mjs --json report.json <paths...>    also writes the per-file report
//
// For every lucide import it picks the reference glyph from lucide-map.json (`files` overrides
// first, then `glyphs`, then the lucide `fallbacks`), renames the uses, rewrites the imports and
// checks each explicit strokeWidth on a swapped icon: a value the design uses for that glyph
// (registry.json `strokes`) stays, any other value is dropped so the glyph's own stroke shows.
// Names it cannot map stay on lucide and are listed, as are tests that assert lucide class names.
import { readFileSync, readdirSync, statSync, writeFileSync } from 'node:fs';
import { dirname, extname, join, relative, sep } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

import ts from 'typescript';

const HERE = dirname(fileURLToPath(import.meta.url));
const WEB = join(HERE, '..', '..');

export function loadConfig() {
  return {
    map: JSON.parse(readFileSync(join(HERE, 'lucide-map.json'), 'utf8')),
    registry: JSON.parse(readFileSync(join(HERE, 'registry.json'), 'utf8')).glyphs,
  };
}

/** HeartIcon and LucideHeart are lucide aliases of Heart. */
function baseName(name) {
  return name.replace(/^Lucide(?=[A-Z])/, '').replace(/(?<=.)Icon$/, '');
}

/** What a lucide export becomes in this file, or null when nothing maps it. */
function resolve(imported, filePath, { map, registry }) {
  if (map.types[imported]) return { kind: 'type', exportName: map.types[imported] };
  const overrides = map.files[filePath] ?? {};
  for (const name of [imported, baseName(imported)]) {
    const glyph = overrides[name] ?? map.glyphs[name] ?? map.fallbacks[name]?.lucide;
    if (glyph && name !== '$comment') {
      const entry = registry[glyph];
      if (!entry) throw new Error(`${glyph} (for ${imported}) is not in registry.json`);
      return { kind: 'glyph', glyph, exportName: entry.export, strokes: entry.strokes, fallback: entry.source === 'fallback' };
    }
  }
  return null;
}

function lineOf(sourceFile, position) {
  return sourceFile.getLineAndCharacterOfPosition(position).line + 1;
}

/** Identifier uses that name the binding (not property names, attribute names or import specifiers). */
function bindingUse(node) {
  const parent = node.parent;
  if (ts.isImportSpecifier(parent) || ts.isExportSpecifier(parent)) return null;
  if ((ts.isPropertyAccessExpression(parent) || ts.isQualifiedName(parent)) && parent.name === node) return null;
  if ((ts.isPropertyAssignment(parent) || ts.isPropertySignature(parent) || ts.isMethodDeclaration(parent)) && parent.name === node) return null;
  if (ts.isJsxAttribute(parent) || ts.isBindingElement(parent) && parent.propertyName === node) return null;
  if (ts.isShorthandPropertyAssignment(parent)) return 'shorthand';
  return 'reference';
}

function literalNumber(attribute) {
  const initializer = attribute.initializer;
  if (!initializer) return null;
  if (ts.isStringLiteral(initializer)) return Number(initializer.text);
  if (ts.isJsxExpression(initializer) && initializer.expression && ts.isNumericLiteral(initializer.expression)) return Number(initializer.expression.text);
  return null;
}

function attributeNamed(element, name) {
  return element.attributes.properties.find((property) => ts.isJsxAttribute(property) && property.name.getText() === name);
}

/** The swapped glyph an element draws: <HeartIcon .../> or <Icon icon={HeartIcon} .../>. */
function drawnGlyph(element, swaps) {
  const tag = element.tagName.getText();
  if (swaps.has(tag)) return swaps.get(tag);
  if (tag !== 'Icon') return null;
  const icon = attributeNamed(element, 'icon');
  const expression = icon?.initializer && ts.isJsxExpression(icon.initializer) ? icon.initializer.expression : null;
  return expression && ts.isIdentifier(expression) ? (swaps.get(expression.text) ?? null) : null;
}

function importText(specifiers, moduleName) {
  const values = specifiers.filter((s) => !s.type).map((s) => s.text).sort();
  const types = specifiers.filter((s) => s.type).map((s) => `type ${s.text}`).sort();
  return `import { ${[...values, ...types].join(', ')} } from '${moduleName}';`;
}

function specifierText(spec) {
  const imported = (spec.propertyName ?? spec.name).text;
  const text = imported === spec.name.text ? imported : `${imported} as ${spec.name.text}`;
  return { text, type: spec.isTypeOnly };
}

/** Removes a whole statement with its line break. */
function statementRemoval(source, statement) {
  let end = statement.end;
  if (source[end] === '\r') end += 1;
  if (source[end] === '\n') end += 1;
  return { start: statement.getStart(), end, text: '' };
}

/**
 * Rewrites one file. `filePath` is relative to qbazaar-web with forward slashes (the key of
 * lucide-map.json `files`). Returns the new source and what changed.
 */
export function transformSource(original, filePath, config = loadConfig()) {
  const { map } = config;
  // Work on LF and give the file its own line endings back (Windows checkouts use CRLF).
  const crlf = original.includes('\r\n');
  const source = crlf ? original.replace(/\r\n/g, '\n') : original;
  const kind = extname(filePath) === '.tsx' ? ts.ScriptKind.TSX : ts.ScriptKind.TS;
  const sourceFile = ts.createSourceFile(filePath, source, ts.ScriptTarget.Latest, true, kind);
  const report = { file: filePath, swaps: [], strokeWidths: [], unmapped: [], notes: [] };

  const lucideImports = sourceFile.statements.filter((s) => ts.isImportDeclaration(s) && s.moduleSpecifier.text === 'lucide-react');
  if (!lucideImports.length) return { output: original, report };

  const swaps = new Map(); // local lucide name -> { newLocal, glyph, strokes, exportName, type }
  const keep = new Map(); // import declaration -> specifiers still on lucide
  for (const declaration of lucideImports) {
    const clause = declaration.importClause;
    const bindings = clause?.namedBindings;
    if (!bindings || !ts.isNamedImports(bindings)) {
      report.notes.push(`line ${lineOf(sourceFile, declaration.getStart())}: non-named lucide import, move it by hand`);
      keep.set(declaration, null);
      continue;
    }
    const kept = [];
    for (const spec of bindings.elements) {
      const imported = (spec.propertyName ?? spec.name).text;
      const target = resolve(imported, filePath, config);
      if (!target) {
        kept.push(spec);
        report.unmapped.push(imported);
        continue;
      }
      swaps.set(spec.name.text, { ...target, type: spec.isTypeOnly || clause.isTypeOnly || target.kind === 'type', imported });
    }
    keep.set(declaration, kept);
  }
  if (!swaps.size) return { output: original, report };

  // A swapped name that already names something else in the file gets an alias.
  const otherNames = new Set();
  const collect = (node) => {
    if (ts.isImportDeclaration(node) && lucideImports.includes(node)) return;
    if (ts.isIdentifier(node) && !swaps.has(node.text)) otherNames.add(node.text);
    ts.forEachChild(node, collect);
  };
  collect(sourceFile);
  for (const [local, swap] of swaps) {
    swap.newLocal = otherNames.has(swap.exportName) ? `Qb${swap.exportName}` : swap.exportName;
    report.swaps.push({ from: swap.imported, to: swap.newLocal === swap.exportName ? swap.exportName : `${swap.exportName} as ${swap.newLocal}`, glyph: swap.glyph ?? null, fallback: Boolean(swap.fallback), local });
  }

  const edits = [];
  const glyphSwaps = new Map([...swaps].filter(([, swap]) => swap.kind === 'glyph').map(([local, swap]) => [local, swap]));
  const visit = (node) => {
    if (ts.isImportDeclaration(node) && lucideImports.includes(node)) return;
    if (ts.isIdentifier(node) && swaps.has(node.text)) {
      const use = bindingUse(node);
      const { newLocal } = swaps.get(node.text);
      if (use === 'shorthand') edits.push({ start: node.getStart(), end: node.end, text: `${node.text}: ${newLocal}` });
      else if (use === 'reference' && newLocal !== node.text) edits.push({ start: node.getStart(), end: node.end, text: newLocal });
    }
    if (ts.isJsxSelfClosingElement(node) || ts.isJsxOpeningElement(node)) {
      const swap = drawnGlyph(node, glyphSwaps);
      const strokeWidth = swap ? attributeNamed(node, 'strokeWidth') : null;
      if (strokeWidth) {
        const value = literalNumber(strokeWidth);
        const line = lineOf(sourceFile, strokeWidth.getStart());
        if (value !== null && !swap.strokes.includes(value)) {
          edits.push({ start: strokeWidth.getFullStart(), end: strokeWidth.end, text: '' });
          const drawn = swap.strokes.length ? `the design draws ${swap.glyph} at ${swap.strokes.join('/')}` : `${swap.glyph} is filled, it has no stroke`;
          report.strokeWidths.push({ line, glyph: swap.glyph, value, action: `dropped (${drawn})` });
        } else {
          report.strokeWidths.push({ line, glyph: swap.glyph, value: value ?? strokeWidth.initializer?.getText(), action: value === null ? 'kept (not a literal, check it)' : 'kept (a stroke the design uses)' });
        }
      }
    }
    ts.forEachChild(node, visit);
  };
  visit(sourceFile);

  // Imports: drop or trim the lucide ones, add (or extend) the registry import.
  const added = [...swaps.values()].map((swap) => ({ text: swap.newLocal === swap.exportName ? swap.exportName : `${swap.exportName} as ${swap.newLocal}`, type: swap.type }));
  const unique = [...new Map(added.map((spec) => [spec.text, spec])).values()].map((spec) => ({
    ...spec,
    type: added.filter((other) => other.text === spec.text).every((other) => other.type),
  }));
  const existing = sourceFile.statements.find((s) => ts.isImportDeclaration(s) && s.moduleSpecifier.text === map.importPath && s.importClause?.namedBindings && ts.isNamedImports(s.importClause.namedBindings) && !s.importClause.isTypeOnly);
  for (const [declaration, kept] of keep) {
    if (kept === null) continue;
    if (kept.length) edits.push({ start: declaration.getStart(), end: declaration.end, text: importText(kept.map(specifierText), 'lucide-react') });
    else edits.push(statementRemoval(source, declaration));
  }
  if (existing) {
    const current = existing.importClause.namedBindings.elements.map(specifierText);
    const merged = [...current, ...unique.filter((spec) => !current.some((c) => c.text === spec.text))];
    edits.push({ start: existing.getStart(), end: existing.end, text: importText(merged, map.importPath) });
  } else {
    // Among the @/ imports in order; else a group of its own after the packages; else where lucide was.
    const imports = sourceFile.statements.filter((s) => ts.isImportDeclaration(s));
    const aliasImports = imports.filter((s) => s.moduleSpecifier.text.startsWith('@/'));
    const removedLucide = (s) => lucideImports.includes(s) && !keep.get(s)?.length;
    const packageImports = imports.filter((s) => !/^(\.{1,2}\/|@\/)|^\.{1,2}$/.test(s.moduleSpecifier.text) && !removedLucide(s));
    const after = aliasImports.find((s) => s.moduleSpecifier.text.localeCompare(map.importPath) > 0);
    const text = importText(unique, map.importPath);
    if (after) edits.push({ start: after.getStart(), end: after.getStart(), text: `${text}\n` });
    else if (aliasImports.length) edits.push({ start: aliasImports.at(-1).end, end: aliasImports.at(-1).end, text: `\n${text}` });
    else if (packageImports.length) edits.push({ start: packageImports.at(-1).end, end: packageImports.at(-1).end, text: `\n\n${text}` });
    else {
      const removal = edits.findIndex((edit) => edit.start === lucideImports[0].getStart());
      if (removal >= 0) edits[removal] = { start: lucideImports[0].getStart(), end: lucideImports[0].end, text };
      else edits.push({ start: lucideImports[0].end, end: lucideImports[0].end, text: `\n${text}` });
    }
  }

  if (/lucide-(?!react['"])[a-z]/.test(source)) report.notes.push('asserts or styles lucide class names (lucide-*): registry glyphs carry no such class');
  if (/import\(\s*['"]lucide-react['"]\s*\)/.test(source)) report.notes.push('loads lucide-react dynamically: move by hand');

  let output = source;
  for (const edit of edits.sort((a, b) => b.start - a.start || b.end - a.end)) {
    output = output.slice(0, edit.start) + edit.text + output.slice(edit.end);
  }
  // A removed import can leave two blank lines in a row in the import block.
  output = output.replace(/^((?:[^\n]*\n)*?)(?=(?:export |function |const |interface |type |class ))/, (header) => header.replace(/\n{3,}/g, '\n\n'));
  return { output: crlf ? output.replace(/\n/g, '\r\n') : output, report };
}

function* sourceFiles(path) {
  const stats = statSync(path);
  if (stats.isFile()) {
    if (/\.(ts|tsx)$/.test(path)) yield path;
    return;
  }
  for (const entry of readdirSync(path)) {
    if (entry === 'node_modules' || entry.startsWith('.')) continue;
    yield* sourceFiles(join(path, entry));
  }
}

function main(argv) {
  let write = false;
  let jsonFile = null;
  const paths = [];
  for (let i = 0; i < argv.length; i += 1) {
    if (argv[i] === '--write') write = true;
    else if (argv[i] === '--json') jsonFile = argv[(i += 1)];
    else paths.push(argv[i]);
  }
  if (!paths.length) {
    console.error('usage: node scripts/icons/codemod.mjs [--write] [--json report.json] <files or folders...>');
    process.exit(2);
  }
  const config = loadConfig();
  const reports = [];
  for (const path of paths) {
    for (const file of sourceFiles(path)) {
      const key = relative(WEB, file).split(sep).join('/');
      const source = readFileSync(file, 'utf8');
      const { output, report } = transformSource(source, key, config);
      if (!report.swaps.length && !report.unmapped.length && !report.notes.length) continue;
      reports.push(report);
      if (write && output !== source) writeFileSync(file, output);
      const swaps = report.swaps.map((s) => `${s.from}->${s.to}${s.fallback ? ' (fallback)' : ''}`).join(', ');
      console.log(`${write ? 'rewrote' : 'would rewrite'} ${key}: ${swaps}`);
      for (const stroke of report.strokeWidths) console.log(`  line ${stroke.line}: strokeWidth ${stroke.value} on ${stroke.glyph} ${stroke.action}`);
      if (report.unmapped.length) console.log(`  stays on lucide: ${report.unmapped.join(', ')}`);
      for (const note of report.notes) console.log(`  note: ${note}`);
    }
  }
  if (jsonFile) writeFileSync(jsonFile, `${JSON.stringify(reports, null, 2)}\n`);
  console.log(`${reports.length} file(s) ${write ? 'rewritten' : 'to rewrite'}`);
}

if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) main(process.argv.slice(2));
