import type { CategoryField, CategoryNode } from '@/lib/api/types';

interface TreeNode<T> {
  id: string;
  children: T[];
}

/** Nodes from the root down to `id`, or an empty list when it isn't in the tree. */
export function findPath<T extends TreeNode<T>>(nodes: readonly T[], id: string | null): T[] {
  if (!id) return [];
  for (const node of nodes) {
    if (node.id === id) return [node];
    const below = findPath(node.children, id);
    if (below.length > 0) return [node, ...below];
  }
  return [];
}

export function isLeaf(node: { children: readonly unknown[] }): boolean {
  return node.children.length === 0;
}

/** The custom fields of the chosen category; only leaf categories carry ads. */
export function fieldsOf(tree: readonly CategoryNode[], categoryId: string | null): CategoryField[] {
  const path = findPath(tree, categoryId);
  const node = path[path.length - 1];
  return node && isLeaf(node) ? (node.custom_fields ?? []) : [];
}
