import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const emblaApi = {
  on: vi.fn(),
  off: vi.fn(),
  selectedScrollSnap: vi.fn(() => 0),
  scrollPrev: vi.fn(),
  scrollNext: vi.fn(),
};
emblaApi.on.mockReturnValue(emblaApi);
emblaApi.off.mockReturnValue(emblaApi);
vi.mock('embla-carousel-react', () => ({ default: () => [vi.fn(), emblaApi] }));

import { setClientLocale } from '@/lib/i18n/locale';
import type { Media } from '@/lib/api/types';

import { AdGallery } from './AdGallery';

function media(id: number): Media {
  const url = `https://cdn.example/${id}.jpg`;
  return {
    id: String(id),
    collection: 'images',
    url,
    sizes: { thumbnail: url, medium: url, large: url, original_webp: url },
    blurhash: null,
    width: 960,
    height: 720,
    order: id,
    size_bytes: 1000,
  };
}

beforeEach(() => {
  setClientLocale('en');
  vi.clearAllMocks();
  emblaApi.on.mockReturnValue(emblaApi);
  emblaApi.off.mockReturnValue(emblaApi);
});

describe('AdGallery', () => {
  it('says so when the ad has no photos', () => {
    render(<AdGallery images={[]} alt="BMW M3" />);

    expect(screen.getByText('No image')).toBeInTheDocument();
  });

  it('labels the slider and each photo', () => {
    render(<AdGallery images={[media(1), media(2), media(3)]} alt="BMW M3" />);

    expect(screen.getByRole('region', { name: 'Photos of BMW M3' })).toBeInTheDocument();
    expect(screen.getByRole('group', { name: 'Photo 1 of 3' })).toBeInTheDocument();
    expect(screen.getByRole('img', { name: 'BMW M3 — 1' })).toBeInTheDocument();
  });

  it('cycles with both arrows: "Previous" on the first photo goes to the last one', async () => {
    render(<AdGallery images={[media(1), media(2)]} alt="BMW M3" />);
    const previous = screen.getByRole('button', { name: 'Previous' });

    expect(previous).not.toHaveAttribute('aria-disabled');
    await userEvent.click(previous);
    expect(emblaApi.scrollPrev).toHaveBeenCalledOnce();

    await userEvent.click(screen.getByRole('button', { name: 'Next' }));
    expect(emblaApi.scrollNext).toHaveBeenCalledOnce();
  });

  it('announces the photo that is showing', () => {
    render(<AdGallery images={[media(1), media(2), media(3)]} alt="BMW M3" />);

    expect(screen.getByText('Photo 1 of 3', { selector: '[aria-live="polite"]' })).toBeInTheDocument();
  });

  it('has no arrows for a single photo', () => {
    render(<AdGallery images={[media(1)]} alt="BMW M3" />);

    expect(screen.queryByRole('button', { name: 'Next' })).toBeNull();
  });

  it('shows the favourite button on the photo', () => {
    render(<AdGallery images={[media(1)]} alt="BMW M3" favorite={<button type="button">Save</button>} />);

    expect(screen.getByRole('button', { name: 'Save' })).toBeInTheDocument();
  });

  it('leaves the photo itself inert, as the reference does', () => {
    render(<AdGallery images={[media(1), media(2)]} alt="BMW M3" />);

    expect(screen.queryByRole('button', { name: 'Open fullscreen' })).toBeNull();
    expect(screen.getAllByRole('button')).toHaveLength(2);
  });
});
