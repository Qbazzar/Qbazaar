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
  deleteAd: vi.fn(),
}));
vi.mock('@/lib/api/account', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/lib/api/account')>()),
  getAccountSummary: vi.fn(),
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
vi.mock('@/lib/api/promotions', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/lib/api/promotions')>()),
  listPromotionOffers: vi.fn(),
}));
vi.mock('@/lib/images/prepare-photo', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/lib/images/prepare-photo')>()),
  preparePhoto: vi.fn(async () => ({ blob: new Blob(['jpeg'], { type: 'image/jpeg' }), width: 2048, height: 1536 })),
}));

import { toast } from 'sonner';
import { getAccountSummary } from '@/lib/api/account';
import { uploadAdImages } from '@/lib/api/ad-images';
import { createAd, deleteAd, publishAd, updateAd } from '@/lib/api/ads';
import { getCategoryTree } from '@/lib/api/categories';
import { getQatarLocations } from '@/lib/api/locations';
import { listPromotionOffers } from '@/lib/api/promotions';
import type { AccountSummary, Ad, CategoryNode, Location, Media, User } from '@/lib/api/types';
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
  fireEvent.click(screen.getByRole('combobox', { name: t('post_ad.location.area') }));
  const doha = screen.getByRole('treeitem', { name: 'الدوحة' }).firstElementChild!;
  fireEvent.pointerDown(doha, { pointerType: 'touch' });
  fireEvent.click(doha);
  fireEvent.click(screen.getByRole('treeitem', { name: 'الخليج الغربي' }));
}

beforeEach(() => {
  vi.clearAllMocks();
  usePostAdStore.setState({ session: null, view: 'form', ad: null, errors: {}, photos: [] });
  vi.mocked(getCategoryTree).mockResolvedValue(TREE);
  vi.mocked(getQatarLocations).mockResolvedValue(CITIES);
  vi.mocked(getAccountSummary).mockResolvedValue({ ads_by_status: { active: 3 } } as AccountSummary);
  vi.mocked(uploadAdImages).mockResolvedValue([MEDIA]);
  vi.mocked(listPromotionOffers).mockResolvedValue([
    { type: 'highlight', price: '15.00', currency: 'QAR', duration_days: 7 },
    { type: 'push_up', price: '10.00', currency: 'QAR', duration_days: 3 },
  ]);
});

describe('PostAdFlow', { timeout: FLOW_TIMEOUT }, () => {
  it('gives every element of the form its own id, so labels and focus find the right control', async () => {
    renderFlow();
    await screen.findByRole('combobox', { name: t('post_ad.location.area') });
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
    await screen.findByRole('combobox', { name: t('post_ad.location.area') });

    await fillRequiredFields();
    fireEvent.click(screen.getByRole('button', { name: t('post_ad.actions.save_draft') }));

    await waitFor(() => expect(toast.success).toHaveBeenCalledWith(t('post_ad.toast.draft_saved'), expect.anything()));
    expect(createAd).toHaveBeenCalledWith(
      expect.objectContaining({ category_id: 'cars', location_id: 'west-bay', price: 185000, title: 'Toyota Land Cruiser 2021' }),
    );

    const file = new File([new Uint8Array(2000)], 'car.jpg', { type: 'image/jpeg' });
    fireEvent.change(screen.getByLabelText(t('post_ad.basic.images')), { target: { files: [file] } });
    await waitFor(() => expect(uploadAdImages).toHaveBeenCalledWith('ad-1', [expect.any(Blob)], expect.objectContaining({ onProgress: expect.any(Function) })));

    // Highlight starts ticked, as on add-ads.html; the seller swaps it for Push up.
    const highlight = await screen.findByRole('checkbox', { name: new RegExp(`^${t('orders.promotion.types.highlight')}`) });
    expect(highlight).toBeChecked();
    fireEvent.click(highlight);
    fireEvent.click(screen.getByRole('checkbox', { name: new RegExp(`^${t('orders.promotion.types.push_up')}`) }));
    fireEvent.click(screen.getByRole('button', { name: t('post_ad.actions.add_ads') }));
    const confirm = await screen.findByRole('button', { name: t('post_ad.actions.confirm_publish') });
    // The promotion ticked on the form stays ticked in the publish step's table.
    const featured = screen.getByRole('group', { name: t('post_ad.promote.featured') });
    expect(within(featured).getByRole('checkbox', { name: new RegExp(`^${t('orders.promotion.types.push_up')}`) })).toBeChecked();
    expect(within(featured).getByRole('checkbox', { name: new RegExp(`^${t('orders.promotion.types.highlight')}`) })).not.toBeChecked();
    // Nothing changed since the draft was saved, so there was nothing to send.
    expect(updateAd).not.toHaveBeenCalled();

    fireEvent.click(confirm);
    expect(await screen.findByText(t('post_ad.publish.terms_required'))).toBeInTheDocument();
    expect(publishAd).not.toHaveBeenCalled();

    fireEvent.click(screen.getByRole('checkbox', { name: new RegExp(t('post_ad.publish.terms_link')) }));
    fireEvent.click(confirm);

    expect(await screen.findByRole('heading', { name: t('post_ad.review.title') })).toBeInTheDocument();
    expect(publishAd).toHaveBeenCalledWith('ad-1', { acceptedTerms: true, idempotencyKey: expect.any(String) });
    // An ad in review can't be promoted yet, so the chosen promotion points to Promotions.
    expect(screen.getByRole('heading', { name: t('post_ad.review.promotions_title') })).toBeInTheDocument();
    expect(screen.getByText(t('post_ad.review.promotions_pending'))).toBeInTheDocument();
    expect(screen.getByRole('link', { name: t('post_ad.review.go_promotions') })).toHaveAttribute('href', '/account/promotions');
  });

  it('opens the preview from a saved draft and goes back to editing', async () => {
    vi.mocked(createAd).mockResolvedValue(ad());
    renderFlow();
    await screen.findByRole('combobox', { name: t('post_ad.location.area') });
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
    await screen.findByRole('combobox', { name: t('post_ad.location.area') });

    fireEvent.change(screen.getByLabelText(t('post_ad.basic.ad_title')), { target: { value: 'Toyota Land Cruiser 2021 GXR' } });
    fireEvent.click(screen.getByRole('button', { name: t('post_ad.actions.preview') }));

    expect(await screen.findByRole('heading', { level: 1, name: 'Toyota Land Cruiser 2021 GXR' })).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: t('post_ad.actions.publish') })).not.toBeInTheDocument();
    expect(updateAd).not.toHaveBeenCalled();
  });

  it('saves only the price of a live ad, which keeps it live, and returns to My Ads', async () => {
    // Stored by the mobile app without custom fields: the form must not send its own rebuilt bag.
    const live = ad({ status: 'active', images: [MEDIA], custom_fields: null as unknown as Ad['custom_fields'] });
    vi.mocked(updateAd).mockResolvedValue({ ...live, price: 179000 });
    renderFlow(live);
    await screen.findByRole('combobox', { name: t('post_ad.location.area') });

    expect(screen.queryByRole('button', { name: t('post_ad.actions.save_draft') })).not.toBeInTheDocument();
    fireEvent.change(screen.getByRole('textbox', { name: t('post_ad.price.price') }), { target: { value: '179000' } });
    fireEvent.click(screen.getByRole('button', { name: t('post_ad.actions.save_changes') }));

    await waitFor(() => expect(router.push).toHaveBeenCalledWith('/account/ads'));
    expect(updateAd).toHaveBeenCalledWith('ad-1', { price: 179000 });
    expect(toast.success).toHaveBeenCalledWith(t('post_ad.toast.changes_saved'), expect.anything());
  });

  it('tells the seller a live ad is back in review when its title changed', async () => {
    const live = ad({ status: 'active', images: [MEDIA] });
    vi.mocked(updateAd).mockResolvedValue({ ...live, title: 'Toyota Land Cruiser 2021 GXR', status: 'pending' });
    renderFlow(live);
    await screen.findByRole('combobox', { name: t('post_ad.location.area') });

    fireEvent.change(screen.getByLabelText(t('post_ad.basic.ad_title')), { target: { value: 'Toyota Land Cruiser 2021 GXR' } });
    fireEvent.click(screen.getByRole('button', { name: t('post_ad.actions.save_changes') }));

    await waitFor(() => expect(router.push).toHaveBeenCalledWith('/account/ads'));
    expect(updateAd).toHaveBeenCalledWith('ad-1', { title: 'Toyota Land Cruiser 2021 GXR' });
    expect(toast.success).toHaveBeenCalledWith(t('post_ad.toast.changes_in_review'), expect.anything());
  });

  it('holds the new photos of a live ad until Save Changes', async () => {
    const live = ad({ status: 'active', images: [MEDIA] });
    renderFlow(live);
    await screen.findByRole('combobox', { name: t('post_ad.location.area') });

    const file = new File([new Uint8Array(2000)], 'car.jpg', { type: 'image/jpeg' });
    fireEvent.change(screen.getByLabelText(t('post_ad.basic.images')), { target: { files: [file] } });
    await waitFor(() => expect(usePostAdStore.getState().photos[1]?.status).toBe('ready'));
    expect(uploadAdImages).not.toHaveBeenCalled();

    fireEvent.click(screen.getByRole('button', { name: t('post_ad.actions.save_changes') }));

    await waitFor(() => expect(router.push).toHaveBeenCalledWith('/account/ads'));
    expect(uploadAdImages).toHaveBeenCalledWith('ad-1', [expect.any(Blob)], expect.anything());
    expect(updateAd).not.toHaveBeenCalled();
    // The API sends a live ad back to review when a photo is added.
    expect(toast.success).toHaveBeenCalledWith(t('post_ad.toast.changes_in_review'), expect.anything());
  });

  it('deletes the draft from the publish step', async () => {
    vi.mocked(deleteAd).mockResolvedValue(undefined);
    renderFlow(ad({ images: [MEDIA] }));
    await screen.findByRole('combobox', { name: t('post_ad.location.area') });

    fireEvent.click(screen.getByRole('button', { name: t('post_ad.actions.add_ads') }));
    await screen.findByRole('heading', { name: t('post_ad.your_ad.title') });
    // The text row from the tablet up and the phone's icon button; CSS shows one of them.
    const deleteButtons = screen.getAllByRole('button', { name: t('common.delete') });
    expect(deleteButtons).toHaveLength(2);
    fireEvent.click(deleteButtons[1]);
    const dialog = await screen.findByRole('dialog');
    fireEvent.click(within(dialog).getByRole('button', { name: t('common.delete') }));

    await waitFor(() => expect(router.push).toHaveBeenCalledWith('/account/ads'));
    expect(deleteAd).toHaveBeenCalledWith('ad-1');
  });

  it('waits for the categories, and offers a retry when they fail to load', async () => {
    vi.mocked(getCategoryTree).mockRejectedValueOnce(new Error('offline'));
    renderFlow();

    fireEvent.click(await screen.findByRole('button', { name: t('common.retry') }));

    expect(await screen.findByRole('heading', { name: t('post_ad.basic.title') })).toBeInTheDocument();
    expect(getCategoryTree).toHaveBeenCalledTimes(2);
  });
});
