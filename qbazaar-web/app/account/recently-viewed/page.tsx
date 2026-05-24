'use client';

/**
 * FE-7.x — Recently viewed index.
 *
 * Auth-gated. Lists every ad the user has viewed (paginated). The "Clear all"
 * button opens a confirmation dialog and fires the clear mutation; on success
 * the query invalidates and the page falls back to the empty state.
 */
import { useState } from 'react';
import {
  ChevronLeft,
  ChevronRight,
  ClockIcon,
  Loader2Icon,
  Trash2Icon,
} from 'lucide-react';
import { toast } from 'sonner';

import { Button } from '@/components/ui/button';
import {
  Dialog,
  DialogClose,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from '@/components/ui/dialog';
import { AdGrid } from '@/components/ads/AdGrid';
import {
  useClearRecentlyViewedMutation,
  useRecentlyViewedQuery,
} from '@/lib/queries/recently-viewed';
import { t } from '@/lib/i18n/messages';
import { ApiClientError } from '@/lib/api/auth';

const PER_PAGE = 24;

export default function RecentlyViewedPage() {
  const [page, setPage] = useState(1);
  const [confirmOpen, setConfirmOpen] = useState(false);
  const { data, isLoading, isError, error } = useRecentlyViewedQuery({
    page,
    per_page: PER_PAGE,
  });
  const clearMutation = useClearRecentlyViewedMutation();

  const lastPage = data?.meta.last_page ?? 1;
  const hasItems = (data?.data.length ?? 0) > 0;

  const handleClear = () => {
    clearMutation.mutate(undefined, {
      onSuccess: () => {
        toast.success(t('recently_viewed.cleared', 'تم مسح السجل'));
        setConfirmOpen(false);
        setPage(1);
      },
      onError: (err) => {
        toast.error(
          err instanceof ApiClientError ? err.message : t('common.error'),
        );
      },
    });
  };

  return (
    <div className="space-y-6">
      <header className="space-y-2">
        <p className="text-coral text-xs font-bold uppercase tracking-[0.18em]">
          {t('account.nav.recently_viewed', 'آخر ما شاهدت')}
        </p>
        <div className="flex flex-wrap items-end justify-between gap-3">
          <h1 className="font-display text-ink-900 text-3xl md:text-4xl">
            {t('recently_viewed.title', 'آخر ما شاهدت')}
          </h1>
          {hasItems ? (
            <Dialog open={confirmOpen} onOpenChange={setConfirmOpen}>
              <DialogTrigger
                render={
                  <Button
                    type="button"
                    variant="destructive"
                    size="default"
                    className="rounded-full"
                  />
                }
              >
                <Trash2Icon className="size-3.5" aria-hidden="true" />
                {t('recently_viewed.clear', 'مسح الكل')}
              </DialogTrigger>
              <DialogContent>
                <DialogHeader>
                  <DialogTitle>
                    {t('recently_viewed.clear_confirm.title', 'مسح السجل؟')}
                  </DialogTitle>
                  <DialogDescription>
                    {t(
                      'recently_viewed.clear_confirm.body',
                      'سيتم حذف جميع الإعلانات التي شاهدتها مؤخراً. لا يمكن التراجع عن هذا الإجراء.',
                    )}
                  </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                  <DialogClose
                    render={
                      <Button
                        variant="outline"
                        size="default"
                        className="rounded-full"
                      >
                        {t('recently_viewed.clear_confirm.cancel', 'إلغاء')}
                      </Button>
                    }
                  />
                  <Button
                    type="button"
                    variant="destructive"
                    size="default"
                    disabled={clearMutation.isPending}
                    onClick={handleClear}
                    className="rounded-full"
                  >
                    {clearMutation.isPending ? (
                      <>
                        <Loader2Icon
                          className="size-3.5 animate-spin"
                          aria-hidden="true"
                        />
                        {t('recently_viewed.clear_confirm.confirm', 'مسح')}
                      </>
                    ) : (
                      t('recently_viewed.clear_confirm.confirm', 'مسح')
                    )}
                  </Button>
                </DialogFooter>
              </DialogContent>
            </Dialog>
          ) : null}
        </div>
      </header>

      {isLoading ? (
        <div className="flex justify-center py-12" role="status">
          <Loader2Icon
            className="text-muted-foreground size-6 animate-spin"
            aria-hidden="true"
          />
        </div>
      ) : isError ? (
        <p className="text-destructive py-12 text-center text-sm">
          {error instanceof ApiClientError
            ? error.message
            : t('common.error', 'حدث خطأ، حاول مرة أخرى')}
        </p>
      ) : !hasItems ? (
        <EmptyState />
      ) : (
        <>
          <AdGrid ads={data!.data} />
          {lastPage > 1 ? (
            <nav className="mt-8 flex items-center justify-between">
              <Button
                type="button"
                variant="outline"
                onClick={() => setPage((p) => Math.max(1, p - 1))}
                disabled={page <= 1}
              >
                <ChevronRight className="size-4" />
                {t('ads.list.prev', 'السابق')}
              </Button>
              <span className="text-ink-500 text-sm">
                {t(
                  'ads.list.page_of',
                  { current: String(page), total: String(lastPage) },
                  `${page} / ${lastPage}`,
                )}
              </span>
              <Button
                type="button"
                variant="outline"
                onClick={() => setPage((p) => Math.min(lastPage, p + 1))}
                disabled={page >= lastPage}
              >
                {t('ads.list.next', 'التالي')}
                <ChevronLeft className="size-4" />
              </Button>
            </nav>
          ) : null}
        </>
      )}
    </div>
  );
}

function EmptyState() {
  return (
    <div className="border-ink-200 bg-card flex flex-col items-center gap-3 rounded-2xl border border-dashed px-6 py-12 text-center">
      <div className="bg-cream-200 text-ink-700 grid size-14 place-items-center rounded-full">
        <ClockIcon className="size-6" aria-hidden="true" />
      </div>
      <h2 className="font-display text-ink-900 text-xl">
        {t('recently_viewed.empty', 'لا يوجد سجل مشاهدة بعد')}
      </h2>
      <p className="text-ink-500 max-w-sm text-sm">
        {t(
          'recently_viewed.empty_body',
          'ستظهر الإعلانات التي تتصفحها هنا حتى تعود إليها بسهولة.',
        )}
      </p>
    </div>
  );
}
