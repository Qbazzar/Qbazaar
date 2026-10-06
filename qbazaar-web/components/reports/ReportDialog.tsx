'use client';

/**
 * Dialog form for submitting a polymorphic report.
 *
 * Uses RHF + Zod for client-side validation; the backend remains the source
 * of truth. The two soft-error codes (`REPORT_002` duplicate, `REPORT_001` self)
 * are handled in the mutation hook — this component only needs to close the
 * dialog on settle.
 */
import { useEffect } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { toast } from 'sonner';
import { Loader2Icon } from 'lucide-react';
import { z } from 'zod';

import {
  Dialog,
  DialogClose,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { FieldError } from '@/components/auth/FieldError';
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

interface Props {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  target_type: ReportTarget;
  target_id: string;
}

export function ReportDialog({
  open,
  onOpenChange,
  target_type,
  target_id,
}: Props) {
  const mutation = useSubmitReportMutation();

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
    mutation.mutate(
      {
        target_type,
        target_id,
        category: values.category,
        description: values.description,
      },
      {
        onSuccess: () => {
          toast.success(t('reports.success_toast', 'تم إرسال البلاغ'));
          onOpenChange(false);
        },
        onError: (err) => {
          // Soft errors (duplicate / self) close the dialog — the mutation
          // hook surfaces its own toast. Hard errors stay inside the form.
          if (err.code === 'REPORT_002' || err.code === 'REPORT_001') {
            onOpenChange(false);
          }
        },
      },
    );
  });

  const categoryError = form.formState.errors.category?.message;
  const descriptionError = form.formState.errors.description?.message;
  const selected = form.watch('category');

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>
            {t('reports.dialog.title', 'الإبلاغ عن محتوى مخالف')}
          </DialogTitle>
          <DialogDescription>
            {t('reports.dialog.description')}
          </DialogDescription>
        </DialogHeader>

        <form onSubmit={onSubmit} noValidate className="space-y-4">
          {/* Category radios */}
          <fieldset className="space-y-2" aria-invalid={Boolean(categoryError)}>
            <legend className="text-ink-900 text-sm font-medium">
              {t('reports.category_label', 'سبب الإبلاغ')}
            </legend>
            <div role="radiogroup" className="space-y-1.5">
              {REPORT_CATEGORIES.map((cat) => {
                const checked = selected === cat;
                return (
                  <label
                    key={cat}
                    className={cn(
                      'border-ink-200 hover:bg-cream-200/60 flex cursor-pointer items-start gap-3 rounded-xl border p-3 text-sm transition-colors',
                      checked && 'border-coral/40 bg-coral/5',
                    )}
                  >
                    <input
                      type="radio"
                      value={cat}
                      checked={checked}
                      className="text-coral focus:ring-coral mt-0.5 size-4 cursor-pointer"
                      {...form.register('category')}
                    />
                    <span className="flex flex-col">
                      <span className="text-ink-900 font-medium">
                        {t(`reports.categories.${cat}.label`)}
                      </span>
                      <span className="text-ink-500 mt-0.5 text-xs">
                        {t(`reports.categories.${cat}.hint`)}
                      </span>
                    </span>
                  </label>
                );
              })}
            </div>
            <FieldError id="category-error" message={categoryError} />
          </fieldset>

          {/* Description */}
          <div className="space-y-1.5">
            <Label htmlFor="report-description">
              {t('reports.description_label', 'تفاصيل إضافية')}
            </Label>
            <Textarea
              id="report-description"
              rows={3}
              maxLength={1000}
              placeholder={t('reports.description_placeholder')}
              aria-invalid={Boolean(descriptionError)}
              {...form.register('description')}
            />
            <FieldError id="description-error" message={descriptionError} />
          </div>

          <DialogFooter>
            <DialogClose
              render={
                <Button
                  type="button"
                  variant="outline"
                  size="default"
                  className="rounded-full"
                  disabled={mutation.isPending}
                >
                  {t('reports.cancel', 'إلغاء')}
                </Button>
              }
            />
            <Button
              type="submit"
              variant="default"
              size="default"
              disabled={mutation.isPending}
              className="bg-coral hover:bg-coral/90 rounded-full text-white"
            >
              {mutation.isPending ? (
                <Loader2Icon className="size-3.5 animate-spin" aria-hidden />
              ) : null}
              {t('reports.submit', 'إرسال البلاغ')}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}
