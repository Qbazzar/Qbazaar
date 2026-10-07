import { CircleCheck } from 'lucide-react';
import { toast } from 'sonner';

import { Icon } from '@/components/design-system/Icon';

/**
 * The mint "saved" toast of 324:13009 and 638:36172. Styled per toast because
 * the shared Toaster draws sonner's own look, which also turns dark with the
 * system theme. Phones keep sonner's full-width placement; from 601 px the
 * toast is the design's 426 px, centred over the 356 px toaster.
 */
export function showSaved(message: string): void {
  toast.success(message, {
    unstyled: true,
    richColors: false,
    closeButton: false,
    icon: <Icon icon={CircleCheck} className="size-[30px] text-qb-brand" />,
    classNames: {
      toast: [
        'flex items-center gap-2 rounded-[14px] border border-qb-success bg-qb-success-soft px-4 py-[11px]',
        'font-qb text-qb-body-sm leading-tight text-qb-success shadow-qb-card',
        'qb-tablet:-mx-[35px] qb-tablet:w-[426px] qb-tablet:py-4 qb-tablet:text-qb-h5',
      ].join(' '),
      icon: 'flex shrink-0',
    },
  });
}
