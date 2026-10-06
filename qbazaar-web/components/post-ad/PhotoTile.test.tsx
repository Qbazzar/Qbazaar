import type { ReactNode } from 'react';
import { fireEvent, render, screen } from '@testing-library/react';
import { DndContext } from '@dnd-kit/core';
import { SortableContext } from '@dnd-kit/sortable';
import { describe, expect, it, vi } from 'vitest';

import { t } from '@/lib/i18n/messages';
import type { PhotoItem } from '@/lib/post-ad/photos';

import { PhotoTile } from './PhotoTile';

function photo(changes: Partial<PhotoItem>): PhotoItem {
  return { key: 'p1', status: 'uploaded', previewUrl: 'blob:p1', ownsPreviewUrl: true, progress: 100, ...changes };
}

function renderTile(item: PhotoItem, position = 2) {
  const handlers = { onRemove: vi.fn(), onRetry: vi.fn(), onMakeCover: vi.fn() };
  const wrap = (children: ReactNode) => (
    <DndContext>
      <SortableContext items={[item.key]}>
        <ul>{children}</ul>
      </SortableContext>
    </DndContext>
  );
  render(wrap(<PhotoTile photo={item} position={position} total={3} {...handlers} />));
  return handlers;
}

describe('PhotoTile', () => {
  it('names the photo, and removes it or makes it the cover', () => {
    const handlers = renderTile(photo({}));

    expect(screen.getByRole('img', { name: t('post_ad.photos.photo_label', { n: 2, total: 3 }) })).toBeInTheDocument();
    fireEvent.click(screen.getByRole('button', { name: t('post_ad.photos.remove', { n: 2 }) }));
    fireEvent.click(screen.getByRole('button', { name: t('post_ad.photos.make_cover', { n: 2 }) }));

    expect(handlers.onRemove).toHaveBeenCalledOnce();
    expect(handlers.onMakeCover).toHaveBeenCalledOnce();
  });

  it('labels the first photo as the cover', () => {
    renderTile(photo({}), 1);

    expect(screen.getByText(t('post_ad.photos.cover'))).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: t('post_ad.photos.make_cover', { n: 1 }) })).not.toBeInTheDocument();
  });

  it('shows the upload progress of this photo', () => {
    renderTile(photo({ status: 'uploading', progress: 42 }));

    const bar = screen.getByRole('progressbar', { name: t('post_ad.photos.uploading_label', { n: 2 }) });
    expect(bar).toHaveAttribute('aria-valuenow', '42');
  });

  it('offers a retry when the upload failed', () => {
    const handlers = renderTile(photo({ status: 'failed', failure: 'upload', errorCode: 'NETWORK_ERROR', progress: 0 }));

    expect(screen.getByText(t('post_ad.photos.failed_network'))).toBeInTheDocument();
    fireEvent.click(screen.getByRole('button', { name: t('post_ad.photos.retry', { n: 2 }) }));

    expect(handlers.onRetry).toHaveBeenCalledOnce();
  });

  it('has no retry for a photo the browser could not read', () => {
    renderTile(photo({ status: 'failed', failure: 'process', progress: 0 }));

    expect(screen.getByText(t('post_ad.photos.failed_process'))).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: t('post_ad.photos.retry', { n: 2 }) })).not.toBeInTheDocument();
  });
});
