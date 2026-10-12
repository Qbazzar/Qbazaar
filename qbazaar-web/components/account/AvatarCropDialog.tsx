'use client';

import { Dialog } from '@base-ui/react/dialog';
import Cropper, { type Area } from 'react-easy-crop';

import { focusRing } from '@/components/design-system/focus-ring';
import { LoaderCircleIcon } from '@/components/design-system/icons';
import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';

export interface AvatarCropDialogProps {
  /** The picked photo as a data URL; the dialog is open while it is set. */
  image: string | null;
  crop: { x: number; y: number };
  zoom: number;
  uploading: boolean;
  /** False until the cropper reports the first crop area. */
  canSave: boolean;
  onCropChange: (crop: { x: number; y: number }) => void;
  onZoomChange: (zoom: number) => void;
  onCropComplete: (area: Area, areaPixels: Area) => void;
  onSave: () => void;
  onClose: () => void;
}

/** `.qb-crop-btns` buttons of cropper.js: r10, 13 px padding, 600 14 px. */
const cropButton =
  'inline-flex min-w-0 basis-0 cursor-pointer items-center justify-center gap-2 rounded-qb-md p-[13px] text-qb-caption font-semibold disabled:cursor-progress disabled:opacity-60';

/**
 * "Update Profile Photo" of cropper.js (`.qb-crop`): a 430 px r20 card with
 * the 300 px viewport, the 260 px circle cut out of a white veil with an
 * orange dashed ring, the "Zoom" slider, then "Cancel" and the wider
 * "Save Photo". Focus is trapped; Escape and a click on the dimmed page
 * close it while nothing is uploading.
 */
export function AvatarCropDialog({
  image,
  crop,
  zoom,
  uploading,
  canSave,
  onCropChange,
  onZoomChange,
  onCropComplete,
  onSave,
  onClose,
}: AvatarCropDialogProps) {
  return (
    <Dialog.Root
      open={image !== null}
      onOpenChange={(open) => {
        if (!open && !uploading) onClose();
      }}
    >
      <Dialog.Portal>
        <Dialog.Backdrop className="fixed inset-0 z-50 bg-qb-chat-overlay" />
        <Dialog.Popup
          className={cn(
            'fixed start-1/2 top-1/2 z-50 max-h-[calc(100dvh-32px)] w-[min(430px,94vw)] -translate-x-1/2 -translate-y-1/2 overflow-y-auto rtl:translate-x-1/2',
            'rounded-[20px] bg-qb-surface px-[26px] pt-[26px] pb-[22px] font-qb text-qb-ink shadow-qb-chat-dialog',
            focusRing,
          )}
        >
          <Dialog.Title className="mb-1 text-center text-qb-h5 font-semibold tracking-normal">
            {t('account.avatar.crop_title')}
          </Dialog.Title>
          <Dialog.Description className="mb-4 text-center text-qb-label text-qb-ink-dialog">
            {t('account.avatar.crop_subtitle')}
          </Dialog.Description>

          <div className="relative mx-auto size-[300px] max-w-full overflow-hidden rounded-[14px] bg-qb-fill-strong">
            {image ? (
              <Cropper
                image={image}
                crop={crop}
                zoom={zoom}
                aspect={1}
                cropShape="round"
                cropSize={{ width: 260, height: 260 }}
                showGrid={false}
                onCropChange={onCropChange}
                onZoomChange={onZoomChange}
                onCropComplete={onCropComplete}
                classes={{ cropAreaClassName: 'border-2! border-dashed! border-qb-brand/85! text-qb-surface/75!' }}
              />
            ) : null}
          </div>

          <label className="mx-1.5 mt-[18px] mb-1 flex items-center gap-3 text-qb-label font-medium text-qb-menu-item">
            {t('account.avatar.crop_zoom_label')}
            <input
              type="range"
              min={1}
              max={3}
              step={0.01}
              value={zoom}
              onChange={(event) => onZoomChange(Number(event.target.value))}
              disabled={uploading}
              className={cn('min-w-0 flex-1 accent-qb-brand', focusRing)}
            />
          </label>

          <div className="mt-4 flex gap-3">
            <button
              type="button"
              onClick={onClose}
              disabled={uploading}
              className={cn(cropButton, 'flex-1 border border-qb-line bg-qb-surface text-qb-icon-close', focusRing)}
            >
              {t('account.avatar.crop_cancel')}
            </button>
            <button
              type="button"
              onClick={onSave}
              disabled={uploading || !canSave}
              className={cn(
                cropButton,
                'flex-[1.4] bg-qb-brand text-qb-on-brand transition-[background] duration-[180ms] ease-[ease] hover:bg-qb-brand-hover',
                focusRing,
              )}
            >
              {uploading ? (
                <>
                  <LoaderCircleIcon aria-hidden="true" className="size-4 animate-spin" />
                  {t('account.avatar.uploading')}
                </>
              ) : (
                t('account.avatar.crop_confirm')
              )}
            </button>
          </div>
        </Dialog.Popup>
      </Dialog.Portal>
    </Dialog.Root>
  );
}
