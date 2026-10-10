'use client';

import { useState } from 'react';
import { useMutation } from '@tanstack/react-query';
import { toast } from 'sonner';
import { Loader2 } from 'lucide-react';

import { Button } from '@/components/design-system/Button';
import { requestDataExport } from '@/lib/api/account';
import { t } from '@/lib/i18n/messages';

import { SettingsRow } from './SettingsPanel';
import { apiErrorMessage } from './api-error-message';

/**
 * "Export my data" (`POST /account/data-export-request`): the archive is
 * prepared in the background and its link arrives by email, so the row only
 * confirms the request.
 */
export function ExportDataRow() {
  const [queued, setQueued] = useState(false);
  const mutation = useMutation({
    mutationFn: requestDataExport,
    onSuccess: () => setQueued(true),
    onError: (err) => toast.error(apiErrorMessage(err)),
  });

  return (
    <SettingsRow
      value={t('account.data.export.title')}
      description={
        queued ? (
          <span role="status">
            <span className="block font-semibold text-qb-success">{t('account.data.export.queued_title')}</span>
            {t('account.data.export.queued_body')}
          </span>
        ) : (
          t('account.data.export.body')
        )
      }
      action={
        queued ? (
          <Button
            variant="outline"
            size="sm"
            onClick={() => {
              setQueued(false);
              mutation.reset();
            }}
          >
            {t('account.data.export.request_again')}
          </Button>
        ) : (
          <Button variant="outline" size="sm" onClick={() => mutation.mutate()} disabled={mutation.isPending}>
            {mutation.isPending ? (
              <>
                <Loader2 className="animate-spin" aria-hidden="true" />
                {t('account.data.export.submitting')}
              </>
            ) : (
              t('account.data.export.submit')
            )}
          </Button>
        )
      }
    />
  );
}
