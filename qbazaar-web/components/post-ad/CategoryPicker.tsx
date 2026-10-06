'use client';

import { useState, type CSSProperties } from 'react';
import { Dialog } from '@base-ui/react/dialog';
import { ArrowLeft, ChevronRight, X } from 'lucide-react';

import { Button } from '@/components/design-system/Button';
import { Icon } from '@/components/design-system/Icon';
import { focusRing } from '@/components/design-system/focus-ring';
import type { CategoryNode } from '@/lib/api/types';
import { localized } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';
import { findPath, isLeaf } from '@/lib/post-ad/tree';
import { cn } from '@/lib/utils';

export interface CategoryPickerProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  tree: readonly CategoryNode[];
  value: string | null;
  onSelect: (categoryId: string) => void;
}

const COLUMN_TITLES = ['post_ad.picker.category', 'post_ad.picker.subcategory', 'post_ad.picker.items'];

/**
 * "Select Category" (335:7242): one column per level side by side from 601 px,
 * one level at a time with a back button on phones (639:36814, 640:37592).
 * Only a leaf category can carry an ad, so "Add" waits for one.
 */
export function CategoryPicker({ open, onOpenChange, tree, value, onSelect }: CategoryPickerProps) {
  return (
    <Dialog.Root open={open} onOpenChange={onOpenChange}>
      <Dialog.Portal>
        <Dialog.Backdrop className="fixed inset-0 z-50 bg-qb-overlay transition-opacity duration-200 data-starting-style:opacity-0 data-ending-style:opacity-0 motion-reduce:transition-none" />
        <Dialog.Popup
          className={cn(
            'fixed inset-x-4 top-1/2 z-50 mx-auto flex max-h-[calc(100dvh-32px)] max-w-[760px] -translate-y-1/2 flex-col overflow-hidden',
            'rounded-qb-xl bg-qb-surface font-qb text-qb-ink shadow-qb-popover',
            'transition-[opacity,scale] duration-200 data-starting-style:scale-95 data-starting-style:opacity-0 data-ending-style:opacity-0 motion-reduce:transition-none',
          )}
        >
          {/* Remounted on every open so it starts from the current value. */}
          {open ? <PickerBody tree={tree} value={value} onSelect={onSelect} /> : null}
        </Dialog.Popup>
      </Dialog.Portal>
    </Dialog.Root>
  );
}

function PickerBody({ tree, value, onSelect }: Omit<CategoryPickerProps, 'open' | 'onOpenChange'>) {
  const initialPath = findPath(tree, value).map((node) => node.id);
  const [path, setPath] = useState<string[]>(initialPath);
  // The column shown on phones: the one holding the current choice.
  const [phoneLevel, setPhoneLevel] = useState(Math.max(0, initialPath.length - 1));

  const nodes = findPath(tree, path[path.length - 1] ?? null);
  const columns: (readonly CategoryNode[])[] = [tree];
  for (const node of nodes) {
    if (!isLeaf(node)) columns.push(node.children);
  }
  // Two columns at least, as on the design: the second one waits for a category.
  const desktopColumns = columns.length > 1 ? columns : [...columns, []];
  const chosen = nodes[nodes.length - 1];
  const leaf = chosen && isLeaf(chosen) ? chosen : null;

  function choose(level: number, node: CategoryNode) {
    setPath([...path.slice(0, level), node.id]);
    if (!isLeaf(node)) setPhoneLevel(level + 1);
  }

  const phoneParent = phoneLevel > 0 ? nodes[phoneLevel - 1] : null;

  return (
    <>
      <div className="flex items-center gap-3 border-b border-qb-line px-5 py-4 qb-tablet:px-[26px] qb-tablet:py-[22px]">
        {phoneParent ? (
          <button
            type="button"
            onClick={() => setPhoneLevel(phoneLevel - 1)}
            aria-label={t('post_ad.picker.back')}
            className={cn('-ms-2 inline-flex size-10 items-center justify-center rounded-qb-md hover:bg-qb-fill qb-tablet:hidden', focusRing)}
          >
            <Icon icon={ArrowLeft} size="lg" flipInRtl />
          </button>
        ) : null}
        <Dialog.Title className="min-w-0 flex-1 truncate text-qb-h5 font-semibold tracking-normal">
          <span className={cn(phoneParent && 'max-qb-tablet:hidden')}>{t('post_ad.picker.title')}</span>
          {phoneParent ? <span className="qb-tablet:hidden">{localized(phoneParent.name)}</span> : null}
        </Dialog.Title>
        <Dialog.Close
          aria-label={t('ui.close')}
          className={cn('-me-2 inline-flex size-10 items-center justify-center rounded-qb-md text-qb-ink-subtle hover:bg-qb-fill', focusRing)}
        >
          <Icon icon={X} size="lg" />
        </Dialog.Close>
      </div>

      <div
        className="grid min-h-0 flex-1 qb-tablet:min-h-[340px] qb-tablet:grid-cols-[repeat(var(--columns),minmax(0,1fr))]"
        style={{ '--columns': desktopColumns.length } as CSSProperties}
      >
        {desktopColumns.map((options, level) => (
          <PickerColumn
            key={level}
            level={level}
            options={options}
            selectedId={path[level] ?? null}
            onChoose={(node) => choose(level, node)}
            hiddenOnPhone={level !== phoneLevel}
          />
        ))}
      </div>

      <div className="flex justify-end border-t border-qb-line px-5 py-4 qb-tablet:px-[26px] qb-tablet:py-[18px]">
        <Button
          size="sm"
          disabled={!leaf}
          onClick={() => leaf && onSelect(leaf.id)}
          className="h-auto px-[34px] py-[11px] text-qb-body-sm"
        >
          {t('post_ad.picker.add')}
        </Button>
      </div>
    </>
  );
}

function PickerColumn({
  level,
  options,
  selectedId,
  onChoose,
  hiddenOnPhone,
}: {
  level: number;
  options: readonly CategoryNode[];
  selectedId: string | null;
  onChoose: (node: CategoryNode) => void;
  hiddenOnPhone: boolean;
}) {
  const headingId = `post-ad-picker-level-${level}`;
  return (
    <div
      className={cn(
        'min-h-0 overflow-y-auto p-3 qb-tablet:max-h-[56vh] qb-tablet:border-e qb-tablet:border-qb-line qb-tablet:last:border-e-0',
        hiddenOnPhone && 'max-qb-tablet:hidden',
      )}
    >
      <h3
        id={headingId}
        className="flex items-center gap-[7px] px-2.5 pt-1.5 pb-2.5 text-qb-label font-medium tracking-normal text-qb-brand"
      >
        <span aria-hidden="true" className="h-3.5 w-1 rounded-qb-xs bg-qb-brand" />
        {t(COLUMN_TITLES[Math.min(level, COLUMN_TITLES.length - 1)])}
      </h3>
      {options.length === 0 ? (
        <p className="px-3 py-2 text-qb-caption text-qb-ink-subtle">{t('post_ad.picker.choose_first')}</p>
      ) : (
        <ul aria-labelledby={headingId} className="flex flex-col gap-0.5">
          {options.map((node) => {
            const selected = node.id === selectedId;
            const parent = !isLeaf(node);
            return (
              <li key={node.id}>
                <button
                  type="button"
                  aria-pressed={selected}
                  onClick={() => onChoose(node)}
                  className={cn(
                    'flex w-full items-center justify-between gap-2 rounded-qb-sm px-3 py-[11px] text-start text-qb-caption transition-colors',
                    focusRing,
                    selected ? 'bg-qb-brand text-white' : 'text-qb-ink-body hover:bg-qb-hover',
                  )}
                >
                  <span>{localized(node.name)}</span>
                  {parent ? <Icon icon={ChevronRight} size="sm" flipInRtl /> : null}
                </button>
              </li>
            );
          })}
        </ul>
      )}
    </div>
  );
}
