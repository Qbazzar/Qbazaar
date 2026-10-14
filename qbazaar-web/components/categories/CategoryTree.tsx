'use client';

/**
 * Recursive accordion-style category explorer.
 *
 * Renders each parent as an expandable row using the native <details> element
 * for accessibility + zero-JS toggle. Leaves are plain links. The component
 * is intentionally simple: it's used as a sidebar / picker UI, not the
 * primary discovery surface.
 */
import Link from 'next/link';
import { ChevronLeftIcon } from '@/components/design-system/icons';
import { DynamicIcon } from '@/components/ui/dynamic-icon';
import { localized, getLocale } from '@/lib/i18n/locale';
import { cn } from '@/lib/utils';
import type { CategoryNode } from '@/lib/api/types';

interface Props {
  nodes: CategoryNode[];
  activeSlug?: string | null;
  className?: string;
}

interface NodeProps {
  node: CategoryNode;
  depth: number;
  activeSlug?: string | null;
}

function TreeNode({ node, depth, activeSlug }: NodeProps) {
  const locale = getLocale();
  const hasChildren = node.children.length > 0;
  const isActive = activeSlug === node.slug;
  const rowClass = cn(
    'flex items-center gap-2 rounded-md px-2 py-1.5 text-sm transition-colors',
    isActive
      ? 'bg-coral/10 text-terracotta font-medium'
      : 'text-ink-700 hover:bg-cream-200',
  );

  if (!hasChildren) {
    return (
      <li>
        <Link
          href={`/c/${node.slug}`}
          className={rowClass}
          style={{ paddingInlineStart: `${depth * 12 + 8}px` }}
        >
          <DynamicIcon name={node.icon} className="size-4 shrink-0" />
          <span className="truncate">{localized(node.name, locale)}</span>
        </Link>
      </li>
    );
  }

  return (
    <li>
      <details className="group" open={depth === 0}>
        <summary
          className={cn(
            rowClass,
            'cursor-pointer list-none [&::-webkit-details-marker]:hidden',
          )}
          style={{ paddingInlineStart: `${depth * 12 + 8}px` }}
        >
          <ChevronLeftIcon className="size-4 shrink-0 transition-transform group-open:-rotate-90 rtl:rotate-180 rtl:group-open:rotate-90" />
          <DynamicIcon name={node.icon} className="size-4 shrink-0" />
          <Link
            href={`/c/${node.slug}`}
            className="flex-1 truncate hover:underline"
            onClick={(e) => e.stopPropagation()}
          >
            {localized(node.name, locale)}
          </Link>
        </summary>
        <ul className="mt-1 space-y-0.5">
          {node.children.map((child) => (
            <TreeNode
              key={child.id}
              node={child}
              depth={depth + 1}
              activeSlug={activeSlug}
            />
          ))}
        </ul>
      </details>
    </li>
  );
}

export function CategoryTree({ nodes, activeSlug, className }: Props) {
  return (
    <ul className={cn('space-y-0.5', className)}>
      {nodes.map((node) => (
        <TreeNode
          key={node.id}
          node={node}
          depth={0}
          activeSlug={activeSlug ?? null}
        />
      ))}
    </ul>
  );
}
