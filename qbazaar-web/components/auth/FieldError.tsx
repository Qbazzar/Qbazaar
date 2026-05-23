'use client';

import { translateMaybeKey } from '@/lib/i18n/messages';

/**
 * Render a single form field's error message.
 * Translates well-known Zod keys, falls back to the raw string for runtime errors.
 */
export function FieldError({
  id,
  message,
}: {
  id?: string;
  message?: string;
}) {
  if (!message) return null;
  return (
    <p id={id} role="alert" className="text-destructive text-xs leading-snug">
      {translateMaybeKey(message)}
    </p>
  );
}
