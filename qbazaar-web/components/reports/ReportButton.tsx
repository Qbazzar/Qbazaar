'use client';

/**
 * "Report" trigger for an ad or a user: a ghost button that opens
 * `ReportDialog`, which owns the form. Hidden for guests, since only
 * signed-in users can file reports.
 */
import { useState } from 'react';
import { TriangleAlert } from 'lucide-react';

import { Button } from '@/components/design-system/Button';
import { useAuth } from '@/hooks/useAuth';
import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';
import type { ReportTarget } from '@/lib/api/types';

import { ReportDialog } from './ReportDialog';

interface ReportButtonProps {
  target_type: ReportTarget;
  target_id: string;
  /** Defaults to "Report". */
  label?: string;
  className?: string;
}

export function ReportButton({ target_type, target_id, label, className }: ReportButtonProps) {
  const { isAuthenticated, isHydrated } = useAuth();
  const [open, setOpen] = useState(false);

  if (!isHydrated || !isAuthenticated) return null;

  return (
    <>
      <Button
        variant="ghost"
        size="sm"
        onClick={() => setOpen(true)}
        className={cn('font-normal text-qb-ink-subtle hover:text-qb-ink', className)}
      >
        <TriangleAlert aria-hidden />
        {label ?? t('reports.title')}
      </Button>

      <ReportDialog open={open} onOpenChange={setOpen} target_type={target_type} target_id={target_id} />
    </>
  );
}
