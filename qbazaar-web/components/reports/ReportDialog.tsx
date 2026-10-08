'use client';

/**
 * The report form: one reason from the list and optional details. A centred
 * dialog from 601 px (369:17437, 532:26357) and a bottom sheet on phones
 * (604:34196). RHF + Zod check it first; the API stays the source of truth.
 * The soft errors (`REPORT_002` already reported, `REPORT_001` yourself) are
 * toasted by the mutation hook and close the form.
 */
import { useEffect, useId } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { toast } from 'sonner';
import { Loader2Icon } from 'lucide-react';
import { z } from 'zod';

import { FieldError, announcedError } from '@/components/auth/FieldError';
import { Button } from '@/components/design-system/Button';
import { Field } from '@/components/design-system/Field';
import { Textarea } from '@/components/design-system/Input';
import { Modal, Sheet } from '@/components/design-system/Modal';
import { useSubmitReportMutation } from '@/lib/queries/reports';
import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';
import type { ReportCategory, ReportTarget } from '@/lib/api/types';

const REPORT_CATEGORIES: readonly ReportCategory[] = [
  'spam',
  'fraud',
  'inappropriate',
  'offensive',
  'duplicate',
  'wrong_category',
  'other',
] as const;

const reportSchema = z.object({
  category: z.enum([...REPORT_CATEGORIES] as [ReportCategory, ...ReportCategory[]], {
    message: 'reports.errors.category_required',
  }),
  description: z
    .string()
    .trim()
    .max(1000, 'reports.errors.description_max')
    .optional()
    .transform((v) => (v && v.length > 0 ? v : undefined)),
});

type ReportFormInput = z.input<typeof reportSchema>;
type ReportFormOutput = z.output<typeof reportSchema>;

/**
 * The reference's radio: a brand ring around a brand dot when chosen. Unchosen
 * it is an empty circle in the phone sheet and a dark dot in the dialog.
 */
const radio = [
  'size-[18px] shrink-0 cursor-pointer appearance-none rounded-full border border-qb-line-strong bg-qb-surface transition-colors motion-reduce:transition-none',
  'checked:border-[1.5px] checked:border-qb-brand checked:bg-qb-brand checked:shadow-[inset_0_0_0_3px_var(--color-qb-surface)]',
  'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-solid focus-visible:outline-qb-brand-active',
].join(' ');

interface ReportDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  /** The phone layout: a bottom sheet with the radios on the end side. */
  asSheet: boolean;
  target_type: ReportTarget;
  target_id: string;
  /** The target now has a report on file: just sent, or sent before. */
  onReported: () => void;
}

export function ReportDialog({ open, onOpenChange, asSheet, target_type, target_id, onReported }: ReportDialogProps) {
  const mutation = useSubmitReportMutation();
  const errorId = useId();

  const form = useForm<ReportFormInput, unknown, ReportFormOutput>({
    resolver: zodResolver(reportSchema),
    defaultValues: { category: undefined, description: '' },
    mode: 'onSubmit',
  });

  // Reset form whenever the dialog closes so the next open is clean.
  useEffect(() => {
    if (!open) form.reset();
  }, [open, form]);

  const onSubmit = form.handleSubmit((values) => {
    if (mutation.isPending) return;
    mutation.mutate(
      { target_type, target_id, category: values.category, description: values.description },
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
  const descriptionError = form.formState.errors.description?.message;
  const Shell = asSheet ? Sheet : Modal;

  return (
    <Shell
      open={open}
      onOpenChange={onOpenChange}
      title={t('reports.title')}
      showCloseButton
      className={asSheet ? undefined : 'qb-tablet:p-8'}
    >
      <form onSubmit={onSubmit} noValidate>
        {asSheet ? null : <hr className="-mx-6 mt-2 mb-6 border-qb-line qb-tablet:-mx-8" />}
        <fieldset aria-describedby={categoryError ? errorId : undefined}>
          <legend
            className={
              asSheet
                ? 'text-qb-caption text-qb-ink-subtle'
                : 'text-qb-body-lg font-semibold tracking-normal text-qb-ink'
            }
          >
            {t('reports.dialog.heading')}
          </legend>
          <div className={cn('flex flex-col', asSheet ? 'mt-3' : 'mt-3.5')}>
            {REPORT_CATEGORIES.map((category) => (
              <label
                key={category}
                className={cn(
                  'flex cursor-pointer items-center',
                  asSheet ? 'min-h-[43px] flex-row-reverse justify-between gap-4' : 'min-h-12 gap-3.5',
                )}
              >
                <input
                  type="radio"
                  value={category}
                  className={cn(radio, !asSheet && 'not-checked:border-qb-line not-checked:bg-qb-icon')}
                  {...form.register('category')}
                />
                <span className="text-qb-body text-qb-ink-body">{t(`reports.categories.${category}.label`)}</span>
              </label>
            ))}
          </div>
          <FieldError id={errorId} message={categoryError} />
        </fieldset>

        <Field label={t('reports.description_label')} error={announcedError(descriptionError)} className="mt-4">
          {(control) => (
            <Textarea
              {...control}
              rows={3}
              maxLength={1000}
              placeholder={t('reports.description_placeholder')}
              className="min-h-[96px]"
              {...form.register('description')}
            />
          )}
        </Field>

        <Button
          type="submit"
          fullWidth
          aria-disabled={mutation.isPending || undefined}
          className="mt-6 h-[46px] rounded-qb-xl text-qb-body focus-visible:outline-solid qb-tablet:h-12"
        >
          {mutation.isPending ? <Loader2Icon className="animate-spin" aria-hidden /> : null}
          {t('reports.submit')}
        </Button>
      </form>
    </Shell>
  );
}
