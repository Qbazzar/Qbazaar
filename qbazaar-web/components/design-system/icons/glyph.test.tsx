import { createElement, createRef } from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { render, screen } from '@testing-library/react';
import { Heart as LucideHeart, type LucideIcon } from 'lucide-react';
import { describe, expect, it } from 'vitest';

import { createGlyph, isRegistryGlyph } from './glyph';
import { AddAdsPinIcon, BellOutlineIcon, MenuIcon } from './glyphs';

const Sample = createGlyph('SampleIcon', {
  name: 'sample',
  attributes: { viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', strokeWidth: '1.8' },
  nodes: [['g', { transform: 'scale(2)' }, [['path', { d: 'M1 1h4' }]]], ['circle', { cx: '12', cy: '12', r: '3' }]],
});

describe('createGlyph', () => {
  it('draws the stored elements with the stored stroke', () => {
    const { container } = render(<Sample />);
    const svg = container.querySelector('svg')!;

    expect(svg).toHaveAttribute('stroke-width', '1.8');
    expect(svg).toHaveAttribute('width', '24');
    expect(svg.querySelector('g > path')).toHaveAttribute('d', 'M1 1h4');
    expect(svg.querySelector(':scope > circle')).toHaveAttribute('r', '3');
  });

  it('is hidden from assistive tech unless it is named', () => {
    const { container } = render(<Sample />);
    expect(container.querySelector('svg')).toHaveAttribute('aria-hidden', 'true');

    render(<Sample role="img" aria-label="Favourites" />);
    expect(screen.getByRole('img', { name: 'Favourites' })).not.toHaveAttribute('aria-hidden');
  });

  it('takes a size, a class, a stroke override and a ref like a lucide icon', () => {
    const ref = createRef<SVGSVGElement>();
    const { container } = render(<Sample ref={ref} size={18} strokeWidth={1.5} className="text-qb-brand" />);
    const svg = container.querySelector('svg')!;

    expect(svg).toHaveAttribute('width', '18');
    expect(svg).toHaveAttribute('height', '18');
    expect(svg).toHaveAttribute('stroke-width', '1.5');
    expect(svg).toHaveClass('text-qb-brand');
    expect(ref.current).toBe(svg);
  });

  it('renders on the server (no hooks, no client boundary)', () => {
    expect(renderToStaticMarkup(<AddAdsPinIcon />)).toContain('stroke-linecap="round"');
  });

  it('tells registry glyphs from lucide icons', () => {
    expect(isRegistryGlyph(AddAdsPinIcon)).toBe(true);
    expect(isRegistryGlyph(LucideHeart)).toBe(false);
    expect(AddAdsPinIcon.displayName).toBe('AddAdsPinIcon');
  });

  it('fits a prop still typed LucideIcon, so streams can move their files in any order', () => {
    const legacyProp: LucideIcon = AddAdsPinIcon;
    const { container } = render(createElement(legacyProp, { size: 20 }));

    expect(container.querySelector('svg')).toHaveAttribute('stroke-width', '1.7');
  });
});

describe('outer colour', () => {
  it('follows the text colour where the reference fixed one (filled bars of the burger)', () => {
    const { container } = render(<MenuIcon />);
    expect(container.querySelector('svg')).toHaveAttribute('fill', 'currentColor');
  });

  it('keeps the inner paints of composed drawings', () => {
    const { container } = render(<BellOutlineIcon />);
    expect(container.querySelector('g')).toHaveAttribute('transform', 'scale(0.5427)');
    expect(container.querySelector('path')).toHaveAttribute('stroke', 'none');
  });
});
