import { fireEvent, render, screen, within } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

import type { CategoryNode } from '@/lib/api/types';
import { t } from '@/lib/i18n/messages';

import { CategoryPicker } from './CategoryPicker';

const node = (id: string, name: string, children: CategoryNode[] = []) =>
  ({ id, name: { ar: name, en: name }, children, custom_fields: null }) as unknown as CategoryNode;

const TREE = [
  node('vehicles', 'Vehicles', [node('cars', 'Cars'), node('boats', 'Boats')]),
  node('fashion', 'Fashion', [node('bags', 'Bags')]),
];

function open(value: string | null = null) {
  const onSelect = vi.fn();
  render(<CategoryPicker open onOpenChange={vi.fn()} tree={TREE} value={value} onSelect={onSelect} />);
  return { onSelect, dialog: screen.getByRole('dialog') };
}

describe('CategoryPicker', () => {
  it('lists the top categories and waits for a subcategory before "Add"', () => {
    const { dialog } = open();

    expect(within(dialog).getByText(t('post_ad.picker.title'))).toBeInTheDocument();
    expect(within(dialog).getByRole('button', { name: 'Vehicles' })).toHaveAttribute('aria-pressed', 'false');
    expect(within(dialog).getByText(t('post_ad.picker.choose_first'))).toBeInTheDocument();
    expect(within(dialog).getByRole('button', { name: t('post_ad.picker.add') })).toBeDisabled();
  });

  it('opens the subcategories of the chosen category and adds the chosen leaf', () => {
    const { dialog, onSelect } = open();

    fireEvent.click(within(dialog).getByRole('button', { name: 'Vehicles' }));
    expect(within(dialog).getByRole('button', { name: 'Vehicles' })).toHaveAttribute('aria-pressed', 'true');
    expect(within(dialog).getByRole('button', { name: t('post_ad.picker.add') })).toBeDisabled();

    fireEvent.click(within(dialog).getByRole('button', { name: 'Boats' }));
    fireEvent.click(within(dialog).getByRole('button', { name: t('post_ad.picker.add') }));

    expect(onSelect).toHaveBeenCalledWith('boats');
  });

  it('starts from the current category', () => {
    const { dialog, onSelect } = open('bags');

    expect(within(dialog).getByRole('button', { name: 'Fashion' })).toHaveAttribute('aria-pressed', 'true');
    expect(within(dialog).getByRole('button', { name: 'Bags' })).toHaveAttribute('aria-pressed', 'true');
    fireEvent.click(within(dialog).getByRole('button', { name: t('post_ad.picker.add') }));

    expect(onSelect).toHaveBeenCalledWith('bags');
  });

  it('on phones, moves focus into the level it opens and back to the parent', () => {
    const matchMedia = vi.spyOn(window, 'matchMedia').mockReturnValue({ matches: true } as MediaQueryList);
    const { dialog } = open();

    fireEvent.click(within(dialog).getByRole('button', { name: 'Vehicles' }));
    expect(within(dialog).getByRole('button', { name: 'Cars' })).toHaveFocus();

    fireEvent.click(within(dialog).getByRole('button', { name: t('post_ad.picker.back') }));
    expect(within(dialog).getByRole('button', { name: 'Vehicles' })).toHaveFocus();
    matchMedia.mockRestore();
  });

  it('switching category clears the old subcategory', () => {
    const { dialog } = open('cars');

    fireEvent.click(within(dialog).getByRole('button', { name: 'Fashion' }));

    expect(within(dialog).queryByRole('button', { name: 'Cars' })).not.toBeInTheDocument();
    expect(within(dialog).getByRole('button', { name: t('post_ad.picker.add') })).toBeDisabled();
  });
});
