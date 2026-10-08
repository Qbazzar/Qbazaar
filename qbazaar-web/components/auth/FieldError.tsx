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

/** Translated error text for the design-system `Field`, which announces it and links it to the control. */
export function fieldErrorText(message?: string): ReactNode {
  return message ? translateMaybeKey(message) : undefined;
}
