import { CircleCheck } from 'lucide-react';
import { toast } from 'sonner';

import { Icon } from '@/components/design-system/Icon';

import '@/styles/design-tokens-sell.css';

/**
 * The mint "saved" toast of 324:13009 and 638:36172. Styled per toast because
 * the shared Toaster draws sonner's own look, which also turns dark with the
 * system theme. Phones keep sonner's full-width placement; from 601 px the
 * toast is the design's 426 px, centred over the 356 px toaster. The margin
 * moves it from the toaster's offset to just under the header, as designed
 * (top 79 px; 84 px on phones, polish.css).
 */
export function showSaved(message: string): void {
  toast.success(message, {
    unstyled: true,
    richColors: false,
    closeButton: false,
    icon: <Icon icon={CircleCheck} className="size-[30px] text-qb-brand" />,
    classNames: {
      toast: [
        'mt-[68px] flex items-center gap-2 rounded-[14px] border border-(--color-qb-mint) bg-(--color-qb-mint-soft) px-4 py-2.5',
        'font-qb text-qb-body-sm leading-tight font-normal text-(--color-qb-mint) shadow-qb-hover',
        'qb-tablet:-mx-[35px] qb-tablet:mt-[55px] qb-tablet:w-[426px] qb-tablet:py-[15px] qb-tablet:text-qb-h5',
      ].join(' '),
      icon: 'flex shrink-0',
    },
  });
}
