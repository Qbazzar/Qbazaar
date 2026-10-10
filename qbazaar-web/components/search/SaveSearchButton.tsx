'use client';

/**
 * "Save Search" pill and its dialog. Signed-out visitors get a link to the
 * login page instead of a request that would fail; until the auth store
 * hydrates the button renders but is disabled, so the layout does not jump.
 * A search already among the visitor's saved searches shows the green "Saved"
 * state (256:5238), which links to them.
 */
import { useState } from 'react';
import Link from 'next/link';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { toast } from 'sonner';
import { Search } from 'lucide-react';

import { fieldErrorText } from '@/components/auth/FieldError';
import { Button } from '@/components/design-system/Button';
import { Field } from '@/components/design-system/Field';
import { focusRing } from '@/components/design-system/focus-ring';
import { Icon } from '@/components/design-system/Icon';
import { Input } from '@/components/design-system/Input';
import { Modal } from '@/components/design-system/Modal';
import { t } from '@/lib/i18n/messages';
import '@/components/catalog/catalog-tokens.css';
import { headingFont, toolbarPill } from '@/components/catalog/layout';
import { cn } from '@/lib/utils';
import { useAuth } from '@/hooks/useAuth';
import { useSaveSearchMutation, useSavedSearchesQuery } from '@/lib/queries/search';
import { ApiClientError } from '@/lib/api/auth';
import type { SearchQueryParams } from '@/lib/api/types';

import { isSameSearch } from './search-params';

/** `SaveSearchRequest.name` in the contract. */
const NAME_MAX_LENGTH = 60;

const schema = z.object({
  name: z.string().trim().min(1, 'search.save_search.name_required').max(NAME_MAX_LENGTH, 'search.save_search.name_max'),
});

type FormValues = z.infer<typeof schema>;

const TRIGGER = {
  /** Beside the page title on desktop (69:467). */
  header: { pill: 'h-14 px-5 text-qb-h5 [&_svg]:size-6', face: 'gap-3', label: '' },
  /** Toolbar of tablets (text pill, 544:38513) and phones (icon only, 623:30012). */
  toolbar: {
    pill: 'h-10 w-11 justify-center qb-tablet:h-11 qb-tablet:w-auto qb-tablet:px-4 qb-tablet:text-qb-body [&_svg]:size-5',
    face: 'qb-tablet:gap-2',
    label: 'sr-only qb-tablet:not-sr-only',
  },
} as const;

interface SaveSearchButtonProps {
  params: SearchQueryParams;
  variant?: keyof typeof TRIGGER;
  className?: string;
}

export function SaveSearchButton({ params, variant = 'header', className }: SaveSearchButtonProps) {
  const { isAuthenticated, isHydrated } = useAuth();
  const [open, setOpen] = useState(false);
  const mutation = useSaveSearchMutation();
  const form = useForm<FormValues>({ resolver: zodResolver(schema), defaultValues: { name: '' } });
  const savedSearches = useSavedSearchesQuery(isHydrated && isAuthenticated);
  const [justSaved, setJustSaved] = useState<SearchQueryParams | null>(null);
  const saved = [...(savedSearches.data?.map((search) => search.query_params) ?? []), ...(justSaved ? [justSaved] : [])].some((savedParams) =>
    isSameSearch(savedParams, params),
  );

  const saveLabel = t('catalog.save_search', 'احفظ البحث');
  const savedLabel = t('search.save_search.saved', 'تم الحفظ');
  const trigger = TRIGGER[variant];
  const triggerClass = cn(
    toolbarPill,
    'shrink-0 cursor-pointer font-qb transition-[background-color,box-shadow,translate] duration-200 motion-reduce:transition-none',
    'hover:-translate-y-[3px] hover:bg-qb-hover hover:shadow-qb-hover motion-reduce:hover:translate-y-0 disabled:pointer-events-none disabled:opacity-50',
    saved && 'border-(--color-qb-saved) bg-(--color-qb-saved-soft) text-(--color-qb-saved) hover:bg-(--color-qb-saved-soft)',
    trigger.pill,
    focusRing,
    className,
  );
  const face = (label: string, hidden = false) => (
    <span aria-hidden={hidden || undefined} className={cn('col-start-1 row-start-1 inline-flex items-center', trigger.face, hidden && 'invisible')}>
      <Icon icon={Search} className={cn('shrink-0', saved ? 'text-(--color-qb-search-icon)' : 'text-qb-ink-body')} />
      <span className={trigger.label}>{label}</span>
    </span>
  );
  // The other state's face, hidden, keeps the pill as wide when it turns "Saved" (256:5238).
  const content = (
    <span className="grid justify-items-center">
      {face(saved ? savedLabel : saveLabel)}
      {face(saved ? saveLabel : savedLabel, true)}
    </span>
  );

  if (saved) {
    return (
      <Link href="/account/saved-searches" aria-label={t('search.save_search.saved_link', 'تم الحفظ، افتح عمليات البحث المحفوظة')} className={triggerClass}>
        {content}
      </Link>
    );
  }

  if (isHydrated && !isAuthenticated) {
    return (
      <Link href="/login" className={triggerClass}>
        {content}
      </Link>
    );
  }

  const onSubmit = form.handleSubmit((values) => {
    mutation.mutate(
      { name: values.name, query_params: params },
      {
        onSuccess: () => {
          toast.success(t('search.save_search.success_toast', 'تم حفظ البحث'));
          form.reset();
          setOpen(false);
          setJustSaved(params);
        },
        onError: (err) => toast.error(saveErrorMessage(err)),
      },
    );
  });

  const nameError = form.formState.errors.name?.message;

  return (
    <Modal
      open={open}
      onOpenChange={setOpen}
      showCloseButton
      className={headingFont}
      title={t('search.save_search.dialog_title', 'احفظ هذا البحث')}
      description={t('search.save_search.dialog_subtitle', 'سنحفظ الفلاتر الحالية لتعيد تشغيلها متى شئت.')}
      trigger={
        <button type="button" disabled={!isHydrated} className={triggerClass}>
          {content}
        </button>
      }
    >
      <form onSubmit={onSubmit} noValidate className="flex flex-col gap-6">
        <Field
          label={t('search.save_search.name_label', 'اسم البحث')}
          error={fieldErrorText(nameError)}
          required
        >
          {(control) => (
            <Input
              {...control}
              autoComplete="off"
              maxLength={NAME_MAX_LENGTH}
              placeholder={t('search.save_search.name_placeholder')}
              {...form.register('name')}
            />
          )}
        </Field>
        <div className="flex flex-col-reverse gap-3 qb-tablet:flex-row qb-tablet:justify-end">
          <Button type="button" variant="outline" onClick={() => setOpen(false)}>
            {t('search.save_search.cancel', 'إلغاء')}
          </Button>
          <Button type="submit" disabled={mutation.isPending}>
            {mutation.isPending ? t('search.save_search.saving', 'جاري الحفظ…') : t('search.save_search.save', 'حفظ')}
          </Button>
        </div>
      </form>
    </Modal>
  );
}

/** Messages are keyed by the contract code, e.g. `SEARCH_004` (saved-search limit). */
function saveErrorMessage(err: unknown): string {
  const byCode = err instanceof ApiClientError ? t(`search.errors.${err.code.toLowerCase()}`, '') : '';
  return byCode || t('search.errors.save_failed', 'تعذّر حفظ البحث');
}
