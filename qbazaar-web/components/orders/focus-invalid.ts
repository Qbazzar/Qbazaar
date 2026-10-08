/** After a failed submit, moves focus to the first field marked invalid so its error is read out. */
export function focusFirstInvalid(container: ParentNode | null): void {
  if (!container) return;
  requestAnimationFrame(() => {
    container.querySelector<HTMLElement>('[aria-invalid="true"]')?.focus();
  });
}

/** The same inside the open dialog, for forms that live in a confirmation. */
export function focusFirstInvalidInDialog(): void {
  focusFirstInvalid(document.querySelector('[role="dialog"]'));
}
