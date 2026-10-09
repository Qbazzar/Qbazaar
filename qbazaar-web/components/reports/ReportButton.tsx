'use client';

/**
 * "Report" trigger for an ad or a user: a ghost button that opens
 * `ReportDialog`, which owns the form. It shows from the first render, so
 * nothing shifts once the session is known; guests are sent to login and
 * come back. After a report it reads "Reported!" (190:9777) for the visit.
 */
import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { TriangleAlert } from 'lucide-react';

import { Button } from '@/components/design-system/Button';
import { useAuth } from '@/hooks/useAuth';
import { t } from '@/lib/i18n/messages';
import { currentLocationPath } from '@/lib/navigation/safe-return-to';
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
  const router = useRouter();
  const { isAuthenticated, isHydrated } = useAuth();
  const [open, setOpen] = useState(false);
  const [reported, setReported] = useState(false);

  const onClick = () => {
    if (reported || !isHydrated) return;
    if (!isAuthenticated) {
      router.push(`/login?from=${encodeURIComponent(currentLocationPath())}`);
      return;
    }
    setOpen(true);
  };

  return (
    <>
      <Button
        variant="ghost"
        size="sm"
        onClick={onClick}
        aria-disabled={reported || undefined}
        className={cn(
          'font-normal text-qb-ink-subtle hover:text-qb-ink',
          // No warning token yet: the design's amber "Reported!" takes the brand colour.
          reported && 'text-qb-brand aria-disabled:opacity-100',
          className,
        )}
      >
        <TriangleAlert aria-hidden />
        {reported ? t('reports.reported') : (label ?? t('reports.title'))}
      </Button>

      <ReportDialog
        open={open}
        onOpenChange={setOpen}
        target_type={target_type}
        target_id={target_id}
        onReported={() => setReported(true)}
      />
    </>
  );
}
