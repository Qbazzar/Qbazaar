import { useState } from 'react';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vitest';

import { ResultsFocusProvider, useRequestResultsFocus, useResultsFocusTarget } from './results-focus';

interface PageProps {
  ready?: boolean;
  onlyIfFocusLost?: boolean;
  /** Whether "Apply" stays on screen once used, like a search box, or goes away, like a reset button. */
  applyStays?: boolean;
}

function Page({ ready = true, onlyIfFocusLost = false, applyStays = false }: PageProps) {
  const requestResultsFocus = useRequestResultsFocus();
  const ref = useResultsFocusTarget<HTMLHeadingElement>({ ready, onlyIfFocusLost });
  const [applied, setApplied] = useState(false);

  return (
    <>
      {applyStays || !applied ? (
        <button
          type="button"
          onClick={() => {
            requestResultsFocus();
            setApplied(true);
          }}
        >
          Apply
        </button>
      ) : null}
      <h2 ref={ref} tabIndex={-1}>
        Results
      </h2>
    </>
  );
}

const renderPage = (props: PageProps = {}) =>
  render(<Page {...props} />, { wrapper: ({ children }) => <ResultsFocusProvider>{children}</ResultsFocusProvider> });

describe('results focus', () => {
  it('leaves the focus alone without a request', () => {
    renderPage();

    expect(screen.getByRole('heading', { name: 'Results' })).not.toHaveFocus();
  });

  it('hands the focus to the results once they are ready', async () => {
    const user = userEvent.setup();
    const { rerender } = renderPage({ ready: false });

    await user.click(screen.getByRole('button', { name: 'Apply' }));
    expect(screen.getByRole('heading', { name: 'Results' })).not.toHaveFocus();

    rerender(<Page ready />);
    expect(screen.getByRole('heading', { name: 'Results' })).toHaveFocus();
  });

  it('leaves the focus with a control that stays on screen, and takes it once that control is gone', async () => {
    const user = userEvent.setup();
    const { rerender } = renderPage({ onlyIfFocusLost: true, applyStays: true });
    const apply = screen.getByRole('button', { name: 'Apply' });

    await user.click(apply);
    expect(apply).toHaveFocus();

    rerender(<Page onlyIfFocusLost />);
    expect(screen.getByRole('heading', { name: 'Results' })).toHaveFocus();
  });
});
