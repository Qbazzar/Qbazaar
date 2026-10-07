/**
 * Wraps user text (an ad title, a name) in Unicode first-strong isolates, so
 * an English title in an Arabic sentence, or the other way round, keeps its
 * own direction inside plain strings such as labels and breadcrumbs.
 */
export function isolate(text: string): string {
  return `\u2068${text}\u2069`;
}
