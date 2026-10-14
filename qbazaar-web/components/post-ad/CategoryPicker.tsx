'use client';

import { useEffect, useRef, useState, type CSSProperties } from 'react';
import { Dialog } from '@base-ui/react/dialog';

import { Button } from '@/components/design-system/Button';
import { focusRing } from '@/components/design-system/focus-ring';
import { Icon } from '@/components/design-system/Icon';
import { ArrowLeftIcon } from '@/components/design-system/icons';
import { modalBackdrop } from '@/components/design-system/Modal';
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

/** Below the 601 px tablet breakpoint, where the picker shows one level at a time. */
const PHONE_QUERY = '(max-width: 600.98px)';

/**
 * "Select Category" (add-ads.html, 335:7242): one column per level side by side from 601 px,
 * one level at a time with a back button on phones (639:36814, 640:37592). The r18 card with the
 * reference's deep shadow fades in as its dialogs do (qbFade .2s). Only a leaf category can carry
 * an ad, so "Add" waits for one.
 */
export function CategoryPicker({ open, onOpenChange, tree, value, onSelect }: CategoryPickerProps) {
  return (
    <Dialog.Root open={open} onOpenChange={onOpenChange}>
      <Dialog.Portal>
        <Dialog.Backdrop className={modalBackdrop} />
        <Dialog.Popup
          className={cn(
            'fixed top-1/2 left-1/2 z-50 flex max-h-[calc(100dvh-40px)] w-[calc(100%-40px)] max-w-[760px] -translate-1/2 flex-col overflow-hidden',
            'rounded-[18px] bg-qb-surface font-qb text-qb-ink shadow-qb-modal',
            'animate-qb-dialog-enter motion-reduce:animate-none',
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
  const columns = useRef<(HTMLDivElement | null)[]>([]);
  const levelChanged = useRef(false);

  // On phones the column that held focus is hidden now, so focus moves into the one shown.
  useEffect(() => {
    if (!levelChanged.current) return;
    levelChanged.current = false;
    if (!window.matchMedia(PHONE_QUERY).matches) return;
    const column = columns.current[phoneLevel];
    const option = column?.querySelector<HTMLElement>('button[aria-pressed="true"]') ?? column?.querySelector<HTMLElement>('button');
    option?.focus();
  }, [phoneLevel]);

  const nodes = findPath(tree, path[path.length - 1] ?? null);
  const levels: (readonly CategoryNode[])[] = [tree];
  for (const node of nodes) {
    if (!isLeaf(node)) levels.push(node.children);
  }
  // Two columns at least, as on the design: the second one waits for a category.
  const desktopLevels = levels.length > 1 ? levels : [...levels, []];
  const chosen = nodes[nodes.length - 1];
  const leaf = chosen && isLeaf(chosen) ? chosen : null;

  function showLevel(level: number) {
    levelChanged.current = true;
    setPhoneLevel(level);
  }

  function choose(level: number, node: CategoryNode) {
    setPath([...path.slice(0, level), node.id]);
    if (!isLeaf(node)) showLevel(level + 1);
  }

  const phoneParent = phoneLevel > 0 ? nodes[phoneLevel - 1] : null;

  return (
    <>
      <div className="flex items-center gap-3 border-b border-qb-line px-5 py-4 qb-tablet:px-[26px] qb-tablet:py-[22px]">
        {phoneParent ? (
          <button
            type="button"
            onClick={() => showLevel(phoneLevel - 1)}
            aria-label={t('post_ad.picker.back')}
            className={cn('-ms-2 inline-flex size-10 items-center justify-center rounded-qb-md hover:bg-qb-fill qb-tablet:hidden', focusRing)}
          >
            <Icon icon={ArrowLeftIcon} size="lg" flipInRtl />
          </button>
        ) : null}
        <Dialog.Title className="min-w-0 flex-1 truncate text-qb-h3 font-medium tracking-normal text-(--color-qb-ink-heading)">
          <span className={cn(phoneParent && 'max-qb-tablet:hidden')}>{t('post_ad.picker.title')}</span>
          {phoneParent ? <span className="qb-tablet:hidden">{localized(phoneParent.name)}</span> : null}
        </Dialog.Title>
        {/* The reference closes with a 22 px "✕" character, not an icon. */}
        <Dialog.Close aria-label={t('ui.close')} className={cn('cursor-pointer rounded-qb-xs text-[22px] leading-none text-qb-ink-subtle', focusRing)}>
          <span aria-hidden="true">✕</span>
        </Dialog.Close>
      </div>

      <div
        className="[display:grid] min-h-0 flex-1 qb-tablet:min-h-[340px] qb-tablet:grid-cols-[repeat(var(--columns),minmax(0,1fr))]"
        style={{ '--columns': desktopLevels.length } as CSSProperties}
      >
        {desktopLevels.map((options, level) => (
          <PickerColumn
            key={level}
            ref={(element) => {
              columns.current[level] = element;
            }}
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
          className="h-auto px-[34px] py-[11px] text-qb-body-sm leading-[1.15]"
        >
          {t('post_ad.picker.add')}
        </Button>
      </div>
    </>
  );
}

function PickerColumn({
  ref,
  level,
  options,
  selectedId,
  onChoose,
  hiddenOnPhone,
}: {
  ref: (element: HTMLDivElement | null) => void;
  level: number;
  options: readonly CategoryNode[];
  selectedId: string | null;
  onChoose: (node: CategoryNode) => void;
  hiddenOnPhone: boolean;
}) {
  const headingId = `post-ad-picker-level-${level}`;
  return (
    <div
      ref={ref}
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
        <ul aria-labelledby={headingId} className="flex flex-col">
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
                    'mb-0.5 flex w-full cursor-pointer items-center justify-between gap-2 rounded-qb-sm px-3 py-[11px] text-start text-qb-caption',
                    focusRing,
                    selected ? 'bg-qb-brand text-qb-on-brand' : 'bg-qb-surface text-qb-ink-body',
                  )}
                >
                  <span>{localized(node.name)}</span>
                  {/* A "›" character on the reference; it points the other way in Arabic. */}
                  {parent ? (
                    <span aria-hidden="true" className="inline-block rtl:-scale-x-100">
                      ›
                    </span>
                  ) : null}
                </button>
              </li>
            );
          })}
        </ul>
      )}
    </div>
  );
}
