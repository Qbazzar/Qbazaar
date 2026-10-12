import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { describe, expect, it } from 'vitest';

import { loadConfig, transformSource } from './codemod.mjs';

const config = loadConfig();
const run = (source: string, file = 'components/demo/Demo.tsx') => transformSource(source, file, config);

describe('icon codemod', () => {
  it('swaps a lucide import for the reference glyph and renames its uses', () => {
    const { output, report } = run(
      [
        "import Link from 'next/link';",
        "import { MapPinPlus } from 'lucide-react';",
        '',
        "import { Icon } from '@/components/design-system/Icon';",
        "import { cn } from '@/lib/utils';",
        '',
        'export const A = () => <Icon icon={MapPinPlus} />;',
        '',
      ].join('\n'),
    );

    expect(output).toBe(
      [
        "import Link from 'next/link';",
        '',
        "import { Icon } from '@/components/design-system/Icon';",
        "import { AddAdsPinIcon } from '@/components/design-system/icons';",
        "import { cn } from '@/lib/utils';",
        '',
        'export const A = () => <Icon icon={AddAdsPinIcon} />;',
        '',
      ].join('\n'),
    );
    expect(report.swaps).toEqual([{ from: 'MapPinPlus', to: 'AddAdsPinIcon', glyph: 'add-ads-pin', fallback: false, local: 'MapPinPlus' }]);
  });

  it('keeps aliases, object keys and types working', () => {
    const { output } = run(
      [
        "import { Wallet as WalletIcon, Heart, type LucideIcon } from 'lucide-react';",
        "import type { Wallet } from '@/lib/api/commerce-types';",
        '',
        'const ICONS: Record<string, LucideIcon> = { Heart, wallet: WalletIcon };',
        'export function W({ wallet }: { wallet: Wallet }) { return <WalletIcon aria-hidden />; }',
        '',
      ].join('\n'),
    );

    expect(output).toContain("import { HeartIcon, WalletIcon, type IconGlyph } from '@/components/design-system/icons';");
    expect(output).toContain('const ICONS: Record<string, IconGlyph> = { Heart: HeartIcon, wallet: WalletIcon };');
    expect(output).toContain('return <WalletIcon aria-hidden />;');
    expect(output).not.toContain('lucide-react');
  });

  it('drops a stroke width the design never uses for that glyph and keeps one it does', () => {
    const { output, report } = run(
      [
        "import { Heart, Search } from 'lucide-react';",
        '',
        'export const A = () => (',
        '  <>',
        '    <Heart className="size-4" strokeWidth={1.5} />',
        '    <Search strokeWidth={2.5} />',
        '  </>',
        ');',
        '',
      ].join('\n'),
    );

    expect(output).toContain('<HeartIcon className="size-4" strokeWidth={1.5} />');
    expect(output).toContain('<SearchIcon />');
    expect(report.strokeWidths.map((entry: { action: string }) => entry.action.split(' ')[0])).toEqual(['kept', 'dropped']);
  });

  it('uses the per-file choice where the reference draws another glyph in that place', () => {
    const { output } = run("import { Heart } from 'lucide-react';\n\nexport const A = () => <Heart />;\n", 'components/layout/MobileMenuDrawer.tsx');
    expect(output).toContain('<HeartDrawerIcon />');
  });

  it('maps lucide names the reference does not draw to the explicit fallbacks', () => {
    const { output, report } = run("import { Loader2Icon } from 'lucide-react';\n\nexport const A = () => <Loader2Icon className=\"animate-spin\" />;\n");
    expect(output).toContain('<LoaderCircleIcon className="animate-spin" />');
    expect(report.swaps[0].fallback).toBe(true);
  });

  it('leaves names it cannot map on lucide and says so', () => {
    const { output, report } = run("import { Heart, Zap } from 'lucide-react';\n\nexport const A = () => <><Heart /><Zap /></>;\n");
    expect(output).toContain("import { Zap } from 'lucide-react';");
    expect(output).toContain('<HeartIcon /><Zap />');
    expect(report.unmapped).toEqual(['Zap']);
  });

  it('adds a group of its own when the file has no @/ imports, and keeps CRLF line endings', () => {
    const { output } = run(
      ["import { render } from '@testing-library/react';", "import { Heart } from 'lucide-react';", "import { it } from 'vitest';", '', "import { Icon } from './Icon';", ''].join('\r\n'),
    );
    expect(output).toBe(
      [
        "import { render } from '@testing-library/react';",
        "import { it } from 'vitest';",
        '',
        "import { HeartIcon } from '@/components/design-system/icons';",
        '',
        "import { Icon } from './Icon';",
        '',
      ].join('\r\n'),
    );
  });

  it('merges into an existing registry import', () => {
    const { output } = run(
      ["import { Heart } from 'lucide-react';", '', "import { BellIcon } from '@/components/design-system/icons';", '', 'export const A = () => <><BellIcon /><Heart /></>;', ''].join('\n'),
    );
    expect(output).toContain("import { BellIcon, HeartIcon } from '@/components/design-system/icons';");
    expect(output.match(/design-system\/icons/g)).toHaveLength(1);
  });

  it('aliases a glyph whose name is already taken in the file', () => {
    const { output } = run("import { Heart } from 'lucide-react';\n\nconst HeartIcon = 1;\nexport const A = () => <Heart data-n={HeartIcon} />;\n");
    expect(output).toContain("import { HeartIcon as QbHeartIcon } from '@/components/design-system/icons';");
    expect(output).toContain('<QbHeartIcon data-n={HeartIcon} />');
  });
});

describe('lucide-map.json', () => {
  it('maps every lucide export the app imports today (glyph, fallback or type)', () => {
    const web = process.cwd();
    const files = execFileSync('git', ['ls-files', '*.ts', '*.tsx'], { cwd: web, encoding: 'utf8' }).trim().split('\n');
    const unmapped = new Set<string>();
    for (const file of files) {
      const source = readFileSync(join(web, file), 'utf8');
      if (!/['"]lucide-react['"]/.test(source)) continue;
      const { report } = transformSource(source, file, config);
      report.unmapped.forEach((name: string) => unmapped.add(`${name} (${file})`));
    }
    expect([...unmapped]).toEqual([]);
  });
});
