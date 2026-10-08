import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const emblaApi = {
  on: vi.fn(),
  off: vi.fn(),
  selectedScrollSnap: vi.fn(() => 0),
  scrollPrev: vi.fn(),
  scrollNext: vi.fn(),
  scrollTo: vi.fn(),
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

  it('moves with the arrows; at the first photo "Previous" does nothing but keeps its focus', async () => {
    render(<AdGallery images={[media(1), media(2)]} alt="BMW M3" />);
    const previous = screen.getByRole('button', { name: 'Previous' });

    expect(previous).toHaveAttribute('aria-disabled', 'true');
    expect(previous).toBeEnabled();
    await userEvent.click(previous);
    expect(emblaApi.scrollPrev).not.toHaveBeenCalled();

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

  it('opens the photo full screen and closes it again', async () => {
    render(<AdGallery images={[media(1), media(2)]} alt="BMW M3" />);

    await userEvent.click(screen.getAllByRole('button', { name: 'Open fullscreen' })[0]);
    expect(await screen.findByRole('dialog')).toBeInTheDocument();

    await userEvent.click(screen.getByRole('button', { name: 'Close' }));
    expect(emblaApi.scrollTo).toHaveBeenCalledWith(0, true);
  });
});
