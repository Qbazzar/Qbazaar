/** The API names notification icons in kebab case ("shield-alert"); lucide exports PascalCase names. */
export function lucideIconName(name: string | null | undefined): string | null {
  if (!name) return null;
  return name.replace(/(^|-)([a-z0-9])/g, (_match, _dash, char: string) => char.toUpperCase());
}
