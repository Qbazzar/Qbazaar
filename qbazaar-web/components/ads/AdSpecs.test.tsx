import { render, screen, within } from '@testing-library/react';
import { beforeEach, describe, expect, it } from 'vitest';

import { setClientLocale } from '@/lib/i18n/locale';
import type { Ad, Category } from '@/lib/api/types';

import { AdSpecs } from './AdSpecs';

const category = {
  custom_fields: [
    { key: 'make', label: { ar: 'الماركة', en: 'Make' }, type: 'select', required: true, options: ['BMW'] },
    { key: 'sunroof', label: { ar: 'فتحة سقف', en: 'Sunroof' }, type: 'boolean', required: false, options: null },
  ],
} as unknown as Category;

function ad(custom_fields: Record<string, unknown> | null, condition: Ad['condition'] = null) {
  return { condition, custom_fields: custom_fields as Ad['custom_fields'], category };
}

beforeEach(() => setClientLocale('en'));

describe('AdSpecs', () => {
  it('shows the technical data as terms and values', () => {
    render(<AdSpecs ad={ad({ make: 'BMW' }, 'like_new')} locale="en" />);
    const panel = screen.getByRole('region', { name: 'Technical Data' });

    expect(within(panel).getByText('Condition').tagName).toBe('DT');
    expect(within(panel).getByText('Like new').tagName).toBe('DD');
    expect(within(panel).getByText('BMW')).toBeInTheDocument();
  });

  it('lists the features that are set in their own panel', () => {
    render(<AdSpecs ad={ad({ sunroof: true })} locale="en" />);

    const features = screen.getByRole('region', { name: 'Features & Extras' });
    expect(within(features).getAllByRole('listitem').map((item) => item.textContent)).toEqual(['Sunroof']);
    expect(screen.queryByRole('region', { name: 'Technical Data' })).toBeNull();
  });

  it('renders nothing for an ad without specs', () => {
    const { container } = render(<AdSpecs ad={ad(null)} locale="en" />);

    expect(container).toBeEmptyDOMElement();
  });
});
