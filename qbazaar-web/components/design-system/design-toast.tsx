import { Check } from 'lucide-react';
import { toast } from 'sonner';

/** One at a time: a new confirmation replaces the one on screen, as in the reference. */
const DESIGN_TOAST_ID = 'qb-design-toast';
const DESIGN_TOAST_MS = 3200;

/**
 * The mint confirmation toast of the design (401:13703, `.qb-toast-design`
 * of chat.js and cropper.js): centred 92 px from the top (84 on phones),
 * up to 574 px wide (92vw on phones), with no border and no close button.
 *
 * It rides on the shared sonner Toaster, so the top is measured from the
 * Toaster's own offset and the toast keeps sonner's queue, motion and live
 * region.
 */
export function showDesignToast(message: string): void {
  toast.custom(
    () => (
      <div
        className={[
          'pointer-events-auto mx-auto flex min-h-[52px] w-full items-center gap-3 rounded-[14px] bg-qb-toast-mint px-[18px] py-3',
          'font-qb text-qb-body-sm leading-normal font-medium text-qb-toast-mint-ink shadow-qb-toast',
          'qb-tablet:min-h-[62px] qb-tablet:w-fit qb-tablet:px-11 qb-tablet:py-3.5 qb-tablet:text-qb-h5',
        ].join(' ')}
      >
        <Check aria-hidden="true" className="size-[22px] shrink-0" strokeWidth={2} />
        <span>{message}</span>
      </div>
    ),
    {
      id: DESIGN_TOAST_ID,
      duration: DESIGN_TOAST_MS,
      unstyled: true,
      closeButton: false,
      className: [
        'pointer-events-none top-[calc(84px_-_var(--mobile-offset-top))]!',
        'qb-tablet:inset-x-[calc((var(--width)_-_min(574px,92vw))_/_2)] qb-tablet:top-[calc(92px_-_var(--offset-top))]!',
      ].join(' '),
    },
  );
}
