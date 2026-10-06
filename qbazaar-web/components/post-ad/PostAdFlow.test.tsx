import type { ImgHTMLAttributes } from 'react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { configure, fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const router = vi.hoisted(() => ({ push: vi.fn(), replace: vi.fn() }));

// The whole form renders on every step here, which a busy CI runner takes its time over.
const FLOW_TIMEOUT = 30_000;
configure({ asyncUtilTimeout: 5_000 });

vi.mock('next/navigation', () => ({ useRouter: () => router, usePathname: () => '/post-ad' }));
vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn(), info: vi.fn() } }));
vi.mock('next/image', () => ({
  default: ({ fill: _fill, sizes: _sizes, ...props }: ImgHTMLAttributes<HTMLImageElement> & { fill?: boolean }) => <img {...props} />,
}));
vi.mock('@/lib/api/ads', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/lib/api/ads')>()),
  createAd: vi.fn(),
  updateAd: vi.fn(),
  publishAd: vi.fn(),
  getMyAds: vi.fn(),
}));
vi.mock('@/lib/api/categories', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/lib/api/categories')>()),
  getCategoryTree: vi.fn(),
}));
vi.mock('@/lib/api/locations', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/lib/api/locations')>()),
  getQatarLocations: vi.fn(),
}));
vi.mock('@/lib/api/ad-images', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/lib/api/ad-images')>()),
  uploadAdImages: vi.fn(),
  reorderAdImages: vi.fn(),
  deleteMedia: vi.fn(),
}));
vi.mock('@/lib/images/prepare-photo', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/lib/images/prepare-photo')>()),
  preparePhoto: vi.fn(async () => ({ blob: new Blob(['jpeg'], { type: 'image/jpeg' }), width: 2048, height: 1536 })),
}));

import { toast } from 'sonner';
import { uploadAdImages } from '@/lib/api/ad-images';
import { createAd, getMyAds, publishAd, updateAd } from '@/lib/api/ads';
import { getCategoryTree } from '@/lib/api/categories';
import { getQatarLocations } from '@/lib/api/locations';
import type { Ad, CategoryNode, Location, Media, User } from '@/lib/api/types';
import { t } from '@/lib/i18n/messages';
import { usePostAdStore } from '@/store/post-ad';

import { PostAdFlow } from './PostAdFlow';

const USER = {
  id: 'u1',
  full_name: 'Sara Al-Mansoori',
  account_type: 'private',
  phone_verified: true,
  avatar_url: null,
  created_at: '2016-01-08T10:00:00Z',
} as User;

const TREE = [
  {
    id: 'vehicles',
    name: { ar: 'مركبات', en: 'Vehicles' },
    custom_fields: null,
    children: [{ id: 'cars', name: { ar: 'سيارات', en: 'Cars' }, custom_fields: [], children: [] }],
  },
] as unknown as CategoryNode[];

const CITIES = [
  {
    id: 'doha',
    name: { ar: 'الدوحة', en: 'Doha' },
    children: [{ id: 'west-bay', name: { ar: 'الخليج الغربي', en: 'West Bay' }, children: [] }],
  },
] as unknown as Location[];

function ad(changes: Partial<Ad> = {}): Ad {
  return {
    id: 'ad-1',
    user_id: 'u1',
    status: 'draft',
    title: 'Toyota Land Cruiser 2021',
    description: 'One owner, full service history, no accidents.',
    category_id: 'cars',
    location_id: 'west-bay',
    price: 185000,
    price_type: 'fixed',
    condition: null,
    ad_type: 'offering',
    shipping: 'pickup_only',
    shipping_fee: null,
    quantity: 1,
    postal_code: null,
    street: null,
    show_full_address: false,
    custom_fields: {},
    views_count: 0,
    favorites_count: 0,
    created_at: '2026-10-06T10:00:00Z',
    images: [],
    ...changes,
  } as Ad;
}

const MEDIA = {
  id: 501,
  url: 'https://api.qbazaar.test/media/501/original',
  sizes: { thumbnail: 'https://cdn.qbazaar.test/501-thumb.jpg', medium: '', large: '', original_webp: '' },
} as unknown as Media;

function renderFlow(editing?: Ad) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  render(
    <QueryClientProvider client={client}>
      <PostAdFlow user={USER} ad={editing} breadcrumb={[{ label: 'Home', href: '/' }, { label: 'Add Ads' }]} />
    </QueryClientProvider>,
  );
}

async function fillRequiredFields() {
  fireEvent.change(screen.getByLabelText(t('post_ad.basic.ad_title')), { target: { value: 'Toyota Land Cruiser 2021' } });
  fireEvent.click(screen.getByRole('button', { name: new RegExp(t('post_ad.basic.category_select')) }));
  const dialog = await screen.findByRole('dialog');
  fireEvent.click(within(dialog).getByRole('button', { name: 'مركبات' }));
  fireEvent.click(within(dialog).getByRole('button', { name: 'سيارات' }));
  fireEvent.click(within(dialog).getByRole('button', { name: t('post_ad.picker.add') }));
  fireEvent.change(screen.getByLabelText(t('post_ad.basic.description')), {
    target: { value: 'One owner, full service history, no accidents.' },
  });
  fireEvent.change(screen.getByRole('textbox', { name: t('post_ad.price.price') }), { target: { value: '١٨٥٠٠٠' } });
  fireEvent.change(screen.getByLabelText(t('post_ad.location.area')), { target: { value: 'west-bay' } });
}

beforeEach(() => {
  vi.clearAllMocks();
  usePostAdStore.setState({ session: null, view: 'form', ad: null, errors: {}, photos: [] });
  vi.mocked(getCategoryTree).mockResolvedValue(TREE);
  vi.mocked(getQatarLocations).mockResolvedValue(CITIES);
  vi.mocked(getMyAds).mockResolvedValue({ data: [], meta: { current_page: 1, last_page: 1, per_page: 20, total: 3 }, links: { first: null, last: null, prev: null, next: null } });
  vi.mocked(uploadAdImages).mockResolvedValue([MEDIA]);
});

describe('PostAdFlow', { timeout: FLOW_TIMEOUT }, () => {
  it('gives every element of the form its own id, so labels and focus find the right control', async () => {
    renderFlow();
    await screen.findByRole('option', { name: 'الخليج الغربي' });
    expect(screen.getByRole('textbox', { name: t('post_ad.price.price') })).toHaveAttribute('id', 'post-ad-price');

    fireEvent.click(screen.getByRole('button', { name: new RegExp(t('post_ad.basic.category_select')) }));
    await screen.findByRole('dialog');
    const ids = [...document.querySelectorAll('[id]')].map((element) => element.id);
    expect(ids.filter((id, index) => ids.indexOf(id) !== index)).toEqual([]);
  });

  it('shows what is missing and focuses the first field instead of saving', async () => {
    renderFlow();
    await screen.findByRole('heading', { name: t('post_ad.basic.title') });

    fireEvent.click(screen.getByRole('button', { name: t('post_ad.actions.save_draft') }));

    const title = screen.getByLabelText(t('post_ad.basic.ad_title'));
    expect(await screen.findByText(t('post_ad.errors.title_required'))).toBeInTheDocument();
    expect(title).toHaveAttribute('aria-invalid', 'true');
    await waitFor(() => expect(title).toHaveFocus());
    expect(createAd).not.toHaveBeenCalled();
  });

  it('saves a draft, uploads its photos, then publishes it for review', async () => {
    vi.mocked(createAd).mockResolvedValue(ad());
    vi.mocked(updateAd).mockResolvedValue(ad());
    vi.mocked(publishAd).mockResolvedValue(ad({ status: 'pending' }));
    renderFlow();
    await screen.findByRole('heading', { name: t('post_ad.basic.title') });
    await screen.findByRole('option', { name: 'الخليج الغربي' });

    await fillRequiredFields();
    fireEvent.click(screen.getByRole('button', { name: t('post_ad.actions.save_draft') }));

    await waitFor(() => expect(toast.success).toHaveBeenCalledWith(t('post_ad.toast.draft_saved')));
    expect(createAd).toHaveBeenCalledWith(
      expect.objectContaining({ category_id: 'cars', location_id: 'west-bay', price: 185000, title: 'Toyota Land Cruiser 2021' }),
    );

    const file = new File([new Uint8Array(2000)], 'car.jpg', { type: 'image/jpeg' });
    fireEvent.change(screen.getByLabelText(t('post_ad.basic.images')), { target: { files: [file] } });
    await waitFor(() => expect(uploadAdImages).toHaveBeenCalledWith('ad-1', [expect.any(Blob)], expect.objectContaining({ onProgress: expect.any(Function) })));

    fireEvent.click(screen.getByRole('button', { name: t('post_ad.actions.add_ads') }));
    const confirm = await screen.findByRole('button', { name: t('post_ad.actions.confirm_publish') });
    expect(updateAd).toHaveBeenCalledWith('ad-1', expect.objectContaining({ title: 'Toyota Land Cruiser 2021' }));

    fireEvent.click(confirm);
    expect(await screen.findByText(t('post_ad.publish.terms_required'))).toBeInTheDocument();
    expect(publishAd).not.toHaveBeenCalled();

    fireEvent.click(screen.getByRole('checkbox', { name: new RegExp(t('post_ad.publish.terms_link')) }));
    fireEvent.click(confirm);

    expect(await screen.findByRole('heading', { name: t('post_ad.review.title') })).toBeInTheDocument();
    expect(publishAd).toHaveBeenCalledWith('ad-1', { acceptedTerms: true, idempotencyKey: expect.any(String) });
  });

  it('opens the preview from a saved draft and goes back to editing', async () => {
    vi.mocked(createAd).mockResolvedValue(ad());
    renderFlow();
    await screen.findByRole('option', { name: 'الخليج الغربي' });
    await fillRequiredFields();

    fireEvent.click(screen.getByRole('button', { name: t('post_ad.actions.preview') }));

    expect(await screen.findByText(t('post_ad.preview.banner'))).toBeInTheDocument();
    expect(screen.getByRole('heading', { level: 1, name: 'Toyota Land Cruiser 2021' })).toBeInTheDocument();
    fireEvent.click(screen.getByRole('button', { name: t('post_ad.actions.edit') }));
    expect(await screen.findByRole('heading', { name: t('post_ad.basic.title') })).toBeInTheDocument();
  });

  it('previews a live ad without saving it, since saving can send it back to review', async () => {
    const live = ad({ status: 'active', images: [MEDIA] });
    renderFlow(live);
    await screen.findByRole('option', { name: 'الخليج الغربي' });

    fireEvent.change(screen.getByLabelText(t('post_ad.basic.ad_title')), { target: { value: 'Toyota Land Cruiser 2021 GXR' } });
    fireEvent.click(screen.getByRole('button', { name: t('post_ad.actions.preview') }));

    expect(await screen.findByRole('heading', { level: 1, name: 'Toyota Land Cruiser 2021 GXR' })).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: t('post_ad.actions.publish') })).not.toBeInTheDocument();
    expect(updateAd).not.toHaveBeenCalled();
  });

  it('saves a live ad in place and returns to My Ads', async () => {
    const live = ad({ status: 'active', images: [MEDIA] });
    vi.mocked(updateAd).mockResolvedValue(live);
    renderFlow(live);
    await screen.findByRole('option', { name: 'الخليج الغربي' });

    expect(screen.queryByRole('button', { name: t('post_ad.actions.save_draft') })).not.toBeInTheDocument();
    fireEvent.change(screen.getByRole('textbox', { name: t('post_ad.price.price') }), { target: { value: '179000' } });
    fireEvent.click(screen.getByRole('button', { name: t('post_ad.actions.save_changes') }));

    await waitFor(() => expect(router.push).toHaveBeenCalledWith('/account/ads'));
    expect(updateAd).toHaveBeenCalledWith('ad-1', expect.objectContaining({ price: 179000 }));
    expect(toast.success).toHaveBeenCalledWith(t('post_ad.toast.changes_in_review'));
  });
});
