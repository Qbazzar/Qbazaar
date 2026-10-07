'use client';

import type { ReactNode } from 'react';

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
    <p id={id} role="alert" className="text-qb-caption text-qb-danger">
      {translateMaybeKey(message)}
    </p>
  );
}

/** Error for the design-system `Field`: translated, and announced when it appears. */
export function announcedError(message?: string): ReactNode {
  return message ? <span role="alert">{translateMaybeKey(message)}</span> : undefined;
}
