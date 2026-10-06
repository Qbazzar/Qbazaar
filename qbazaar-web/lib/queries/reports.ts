/**
 * TanStack Query hook for the reports domain (Sprint 10).
 *
 * Reports are write-only from the client — there's no read endpoint surfaced
 * to end users, so we expose just the submit mutation. The mutation handles
 * the two soft-error codes (`REPORT_002` duplicate, `REPORT_001` self) by
 * surfacing localised toasts rather than letting them bubble as red errors.
 */
import { useMutation, type UseMutationResult } from '@tanstack/react-query';
import { toast } from 'sonner';
import { submitReport } from '@/lib/api/reports';
import { ApiClientError } from '@/lib/api/auth';
import { t } from '@/lib/i18n/messages';
import type { MakeReportRequest, Report } from '@/lib/api/types';

export function useSubmitReportMutation(): UseMutationResult<
  Report,
  ApiClientError,
  MakeReportRequest
> {
  return useMutation<Report, ApiClientError, MakeReportRequest>({
    mutationFn: (payload) => submitReport(payload),
    onError: (err) => {
      if (err.code === 'REPORT_002') {
        toast.warning(t('reports.errors.recent_duplicate', 'تم الإبلاغ مسبقاً'));
        return;
      }
      if (err.code === 'REPORT_001') {
        // Silent UX: the dialog closes and the action is a no-op, matching
        // the spec — but we still log so devs notice repeated misuse.
        toast.info(t('reports.errors.self', 'لا يمكنك الإبلاغ عن نفسك'));
        return;
      }
      // Validation errors are surfaced inline by the dialog; everything else
      // gets a generic toast so the user has feedback.
      if (err.code !== 'VALIDATION_FAILED') {
        toast.error(err.message || t('common.error', 'حدث خطأ، حاول مرة أخرى'));
      }
    },
  });
}
