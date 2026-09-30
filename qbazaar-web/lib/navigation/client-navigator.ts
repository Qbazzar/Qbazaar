/**
 * Lets non-React code (axios interceptors) navigate through the Next.js
 * router. A soft navigation keeps in-memory state such as the post-ad
 * wizard draft, which a full `window.location` reload would throw away.
 */
type Navigate = (href: string) => void;

let navigate: Navigate | null = null;

export function registerClientNavigator(fn: Navigate): () => void {
  navigate = fn;
  return () => {
    if (navigate === fn) navigate = null;
  };
}

export function navigateClient(href: string): void {
  if (navigate) {
    navigate(href);
    return;
  }
  if (typeof window !== 'undefined') window.location.assign(href);
}
