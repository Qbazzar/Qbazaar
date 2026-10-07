interface TreeNode {
  slug: string;
  children: TreeNode[];
}

/**
 * Search facets count ads under the category or place they are filed in, a
 * leaf; a parent row shows the sum over its subtree. Undefined without facets.
 */
export function subtreeCount(node: TreeNode, counts: Record<string, number> | null | undefined): number | undefined {
  if (!counts) return undefined;
  const own = counts[node.slug] ?? 0;
  return node.children.reduce((sum, child) => sum + (subtreeCount(child, counts) ?? 0), own);
}
