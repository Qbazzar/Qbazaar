'use client';

/**
 * The report form of product.html at every width: "Report this ad", one
 * reason from a short list of radio rows, then Cancel and "Submit Report".
 * RHF + Zod check it first; the API stays the source of truth. The soft
 * errors (`REPORT_002` already reported, `REPORT_001` yourself) are toasted
 * by the mutation hook and close the form.
 */
import { useEffect, useId } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { toast } from 'sonner';
import { Loader2Icon } from 'lucide-react';
import { z } from 'zod';

import { FieldError } from '@/components/auth/FieldError';
import { CardDialog } from '@/components/design-system/CardDialog';
import { focusRing } from '@/components/design-system/focus-ring';
import { useSubmitReportMutation } from '@/lib/queries/reports';
import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';
import type { ReportCategory, ReportTarget } from '@/lib/api/types';

/**
 * The reference's five ad reasons, in its order, on the API's categories
 * ("Prohibited item" is the API's `inappropriate`). Accounts get the
 * reasons that apply to a person; `other` stays out because the API wants
 * a description with it and the reference form has none.
 */
const REASONS: Record<'ad' | 'user', readonly ReportCategory[]> = {
  ad: ['fraud', 'inappropriate', 'wrong_category', 'duplicate', 'offensive'],
  user: ['fraud', 'spam', 'offensive', 'inappropriate'],
};

const reportSchema = z.object({
  category: z.enum(['spam', 'fraud', 'inappropriate', 'offensive', 'duplicate', 'wrong_category', 'other'], {
    message: 'reports.errors.category_required',
  }),
});

type ReportFormInput = z.input<typeof reportSchema>;
type ReportFormOutput = z.output<typeof reportSchema>;

/** An 18 px ring (#cbcbcb), a brand ring around a 9 px brand dot once chosen. */
const radio = [
  'size-[18px] shrink-0 cursor-pointer appearance-none rounded-full border-[1.5px] border-qb-radio bg-qb-surface',
  'checked:border-qb-brand checked:bg-qb-brand checked:shadow-[inset_0_0_0_3px_var(--color-qb-surface)]',
  focusRing,
].join(' ');

// The reference leaves these at the browser's default button size (13.33 px).
const footerButton = cn('h-[43px] flex-1 cursor-pointer rounded-qb-md text-[13.33px] font-semibold transition-colors', focusRing);

interface ReportDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  target_type: ReportTarget;
  target_id: string;
  /** The target now has a report on file: just sent, or sent before. */
  onReported: () => void;
}

export function ReportDialog({ open, onOpenChange, target_type, target_id, onReported }: ReportDialogProps) {
  const mutation = useSubmitReportMutation();
  const errorId = useId();
  const audience = target_type === 'ad' ? 'ad' : 'user';

  const form = useForm<ReportFormInput, unknown, ReportFormOutput>({
    resolver: zodResolver(reportSchema),
    defaultValues: { category: undefined },
    mode: 'onSubmit',
  });

  // Reset the form whenever the dialog closes so the next open is clean.
  useEffect(() => {
    if (!open) form.reset();
  }, [open, form]);

  const onSubmit = form.handleSubmit(({ category }) => {
    if (mutation.isPending) return;
    mutation.mutate(
      { target_type, target_id, category, description: undefined },
      {
        onSuccess: () => {
          toast.success(t('reports.success_toast'));
          onReported();
          onOpenChange(false);
        },
        onError: (err) => {
          if (err.code === 'REPORT_002') onReported();
          if (err.code === 'REPORT_002' || err.code === 'REPORT_001') onOpenChange(false);
        },
      },
    );
  });

  const categoryError = form.formState.errors.category?.message;

  return (
    <CardDialog
      open={open}
      onOpenChange={onOpenChange}
      title={t(`reports.dialog.title_${audience}`)}
      description={t(`reports.dialog.subtitle_${audience}`)}
      widthClassName="max-w-[480px]"
    >
      <form onSubmit={onSubmit} noValidate className="mt-[18px]">
        <fieldset aria-describedby={categoryError ? errorId : undefined}>
          <legend className="sr-only">{t('reports.dialog.heading')}</legend>
          <div className="flex flex-col gap-0.5">
            {REASONS[audience].map((category) => (
              <label
                key={category}
                className="flex cursor-pointer items-center gap-3 border-b border-qb-line px-1 py-[13px] text-qb-caption text-qb-ink-feature"
              >
                <input type="radio" value={category} className={radio} {...form.register('category')} />
                {t(audience === 'ad' ? `reports.ad_reasons.${category}` : `reports.categories.${category}.label`)}
              </label>
            ))}
          </div>
          <FieldError id={errorId} message={categoryError} />
        </fieldset>

        <div className="mt-5 flex gap-3">
          <button
            type="button"
            onClick={() => onOpenChange(false)}
            className={cn(footerButton, 'border border-qb-line bg-qb-surface text-qb-ink-body')}
          >
            {t('common.cancel')}
          </button>
          <button
            type="submit"
            aria-disabled={mutation.isPending || undefined}
            className={cn(footerButton, 'inline-flex items-center justify-center gap-2 bg-qb-brand text-qb-on-brand hover:bg-qb-brand-hover')}
          >
            {mutation.isPending ? <Loader2Icon className="size-4 animate-spin" aria-hidden /> : null}
            {t('reports.submit')}
          </button>
        </div>
      </form>
    </CardDialog>
  );
}
