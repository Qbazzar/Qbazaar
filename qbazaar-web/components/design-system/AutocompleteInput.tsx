'use client';

import { useEffect, useId, useMemo, useState, type ComponentProps, type KeyboardEvent } from 'react';

import { cn } from '@/lib/utils';

import { dropdownPanel } from './dropdown-panel';

export interface AutocompleteSuggestion {
  value: string;
  label: string;
}

type NativeInputProps = Omit<ComponentProps<'input'>, 'value' | 'onChange' | 'role' | 'list'>;

export interface AutocompleteInputProps extends NativeInputProps {
  value: string;
  onValueChange: (text: string) => void;
  /** Every suggestion; the list shows those whose label contains the typed text. */
  suggestions: AutocompleteSuggestion[];
  /** Classes of the suggestion card, e.g. a cap on its height. */
  panelClassName?: string;
}

/** Labels containing the text, ignoring case and outer spaces; all of them for empty text. */
export function matchSuggestions(suggestions: AutocompleteSuggestion[], text: string): AutocompleteSuggestion[] {
  const wanted = text.trim().toLocaleLowerCase();
  if (!wanted) return suggestions;
  return suggestions.filter(({ label }) => label.toLocaleLowerCase().includes(wanted));
}

/**
 * A text field whose suggestions open in the design's dropdown card instead
 * of the browser's datalist popup (an editable combobox with list
 * autocomplete). The card is placed under the nearest positioned ancestor,
 * so the caller wraps the field in one.
 */
export function AutocompleteInput({
  value,
  onValueChange,
  suggestions,
  panelClassName,
  className,
  onKeyDown,
  onBlur,
  ...inputProps
}: AutocompleteInputProps) {
  const listId = useId();
  const [open, setOpen] = useState(false);
  const [activeIndex, setActiveIndex] = useState(-1);

  const matches = useMemo(() => matchSuggestions(suggestions, value), [suggestions, value]);
  const shown = open && matches.length > 0;
  const optionId = (index: number) => `${listId}-${index}`;

  useEffect(() => {
    if (!shown || activeIndex < 0) return;
    document.getElementById(`${listId}-${activeIndex}`)?.scrollIntoView?.({ block: 'nearest' });
  }, [shown, activeIndex, listId]);

  const close = () => {
    setOpen(false);
    setActiveIndex(-1);
  };

  const pick = (suggestion: AutocompleteSuggestion) => {
    onValueChange(suggestion.label);
    close();
  };

  const move = (step: number) => {
    if (!shown) {
      setOpen(true);
      setActiveIndex(step > 0 ? 0 : matches.length - 1);
      return;
    }
    setActiveIndex((index) => Math.min(matches.length - 1, Math.max(0, index + step)));
  };

  const handleKeyDown = (event: KeyboardEvent<HTMLInputElement>) => {
    onKeyDown?.(event);
    if (event.defaultPrevented) return;
    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
      event.preventDefault();
      move(event.key === 'ArrowDown' ? 1 : -1);
    } else if (event.key === 'Enter' && shown && activeIndex >= 0) {
      // Picks the highlighted place instead of submitting the form.
      event.preventDefault();
      pick(matches[activeIndex]);
    } else if (event.key === 'Escape' && shown) {
      event.preventDefault();
      close();
    } else if (event.key === 'Tab') {
      close();
    }
  };

  return (
    <>
      <input
        {...inputProps}
        role="combobox"
        aria-autocomplete="list"
        aria-expanded={shown}
        aria-controls={shown ? listId : undefined}
        aria-activedescendant={shown && activeIndex >= 0 ? optionId(activeIndex) : undefined}
        autoComplete="off"
        value={value}
        onChange={(event) => {
          onValueChange(event.target.value);
          setOpen(true);
          setActiveIndex(-1);
        }}
        onClick={() => setOpen(true)}
        onKeyDown={handleKeyDown}
        onBlur={(event) => {
          close();
          onBlur?.(event);
        }}
        className={className}
      />
      {shown ? (
        <ul
          id={listId}
          role="listbox"
          aria-label={inputProps['aria-label']}
          // Pressing in the card keeps the focus in the field, so its blur does not close the card before the click.
          onMouseDown={(event) => event.preventDefault()}
          className={cn(dropdownPanel.card, dropdownPanel.placement, 'overflow-y-auto overscroll-contain', panelClassName)}
        >
          {matches.map((suggestion, index) => (
            <li
              key={suggestion.value}
              id={optionId(index)}
              role="option"
              aria-selected={index === activeIndex}
              className={cn(dropdownPanel.row, index === activeIndex && dropdownPanel.activeRow)}
              onMouseEnter={() => setActiveIndex(index)}
              onClick={() => pick(suggestion)}
            >
              {suggestion.label}
            </li>
          ))}
        </ul>
      ) : null}
    </>
  );
}
