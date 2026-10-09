'use client';

/**
 * Following / Followers — users.html. Two full-width tabs (links to
 * `/account/following` and `/account/followers`) over a grid of user cards.
 * On "Following" the "✓ Following" button unfollows and the card leaves the
 * list; on "Followers" the button follows back and toggles. Each tab has
 * its own empty state.
 */
import Link from 'next/link';
import { Check, Loader2Icon, UserPlus } from 'lucide-react';
import { useQueryClient, type InfiniteData } from '@tanstack/react-query';
import { toast } from 'sonner';

import { Avatar } from '@/components/design-system/Avatar';
import { Breadcrumb } from '@/components/design-system/Breadcrumb';
import { focusRing } from '@/components/design-system/focus-ring';
import { Icon } from '@/components/design-system/Icon';
import { useAuth } from '@/hooks/useAuth';
import { ApiClientError } from '@/lib/api/auth';
import type { CursorPage } from '@/lib/api/commerce-types';
import type { FollowListKind, FollowListUser } from '@/lib/api/follows';
import { formatNumber } from '@/lib/i18n/format';
import { getLocale } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';
import { tPlural } from '@/lib/i18n/plural';
import { followKeys, useFollowListQuery, useSetListedFollowState } from '@/lib/queries/follows';
import { useFollowMutation, usePublicProfileQuery, userKeys } from '@/lib/queries/users';
import { cn } from '@/lib/utils';

const TABS: readonly { kind: FollowListKind; href: string }[] = [
  { kind: 'following', href: '/account/following' },
  { kind: 'followers', href: '/account/followers' },
];

export function ConnectionsView({ kind }: { kind: FollowListKind }) {
  const { user } = useAuth();
  const { data: me } = usePublicProfileQuery(user?.id ?? '');
  const list = useFollowListQuery(kind);
  const rows = list.data?.pages.flatMap((page) => page.data) ?? [];
  const counts: Record<FollowListKind, number | undefined> = { following: me?.following_count, followers: me?.followers_count };

  return (
    <div className="mx-auto w-full max-w-[1080px] px-qb-gutter py-[clamp(20px,4vw,40px)] font-qb text-qb-ink">
      <Breadcrumb items={[{ label: t('home.breadcrumb'), href: '/' }, { label: t('users.connections.title') }]} className="mb-3.5" />
      <h1 className="mb-[22px] text-[clamp(26px,3.5vw,34px)] font-semibold tracking-normal text-qb-ink">{t('users.connections.title')}</h1>

      <nav aria-label={t('users.connections.title')} className="mb-7 flex border-b border-qb-line">
        {TABS.map((tab) => {
          const active = tab.kind === kind;
          const count = counts[tab.kind];
          return (
            <Link
              key={tab.kind}
              href={tab.href}
              aria-current={active ? 'page' : undefined}
              className={cn(
                '-mb-px flex-1 border-b-[3px] p-[15px] text-center text-qb-body transition-colors',
                active ? 'border-qb-brand font-semibold text-qb-brand' : 'border-transparent font-medium text-qb-ink-secondary hover:text-qb-brand',
                focusRing,
              )}
            >
              {t(`users.connections.tabs.${tab.kind}`)}
              {count !== undefined ? <span className="font-normal text-qb-ink-subtle"> {formatNumber(count, getLocale())}</span> : null}
            </Link>
          );
        })}
      </nav>

      {list.isPending ? (
        <div aria-busy="true" className="[display:grid] grid-cols-[repeat(auto-fill,minmax(300px,1fr))] gap-[18px]">
          <span className="sr-only">{t('common.loading')}</span>
          {Array.from({ length: 6 }, (_, index) => (
            <div key={index} aria-hidden="true" className="h-[142px] animate-pulse rounded-qb-xl bg-qb-fill motion-reduce:animate-none" />
          ))}
        </div>
      ) : list.isError ? (
        <p role="alert" className="text-qb-caption text-qb-danger">
          {t('common.error')}
        </p>
      ) : rows.length === 0 ? (
        <ConnectionsEmpty kind={kind} />
      ) : (
        <>
          <ul className="[display:grid] grid-cols-[repeat(auto-fill,minmax(300px,1fr))] gap-[18px]">
            {rows.map((row) => (
              <li key={row.id}>
                <ConnectionCard row={row} kind={kind} />
              </li>
            ))}
          </ul>
          {list.hasNextPage ? (
            <div className="mt-6 flex justify-center">
              <button
                type="button"
                onClick={() => void list.fetchNextPage()}
                aria-busy={list.isFetchingNextPage || undefined}
                className={cn(
                  'inline-flex cursor-pointer items-center gap-2 rounded-qb-lg bg-qb-brand-soft px-[30px] py-3.5 text-qb-body-sm font-semibold text-qb-brand',
                  focusRing,
                )}
              >
                {list.isFetchingNextPage ? <Loader2Icon className="size-4 animate-spin" aria-hidden /> : null}
                {t('users.connections.load_more')}
              </button>
            </div>
          ) : null}
        </>
      )}
    </div>
  );
}

/** One account: 46 px peach avatar, name and followers line, and the follow toggle. */
function ConnectionCard({ row, kind }: { row: FollowListUser; kind: FollowListKind }) {
  const queryClient = useQueryClient();
  const { user } = useAuth();
  const mutation = useFollowMutation(row.id);
  const setListedState = useSetListedFollowState();
  const locale = getLocale();

  const toggle = () => {
    if (mutation.isPending) return;
    mutation.mutate(!row.is_following, {
      onSuccess: (state) => {
        toast.success(t(state.following ? 'users.connections.followed' : 'users.connections.unfollowed', { name: row.full_name }));
        if (kind === 'following' && !state.following) {
          // The reference drops an unfollowed account from "Following" at once.
          queryClient.setQueryData<InfiniteData<CursorPage<FollowListUser>>>(followKeys.list('following'), (data) =>
            data ? { ...data, pages: data.pages.map((page) => ({ ...page, data: page.data.filter((item) => item.id !== row.id) })) } : data,
          );
        }
        setListedState(row.id, state.following);
        if (user) void queryClient.invalidateQueries({ queryKey: userKeys.profiles(user.id) });
      },
      onError: (err) => {
        toast.error(err instanceof ApiClientError && err.code === 'FOLLOW_002' ? t('users.follow.errors.blocked') : t('common.error'));
      },
    });
  };

  const following = row.is_following;

  return (
    <article className="flex h-full flex-col rounded-qb-xl border border-qb-line bg-qb-surface p-[18px] transition-shadow hover:shadow-qb-hover">
      <Link href={`/u/${row.id}`} className={cn('mb-4 flex min-w-0 items-start gap-3 rounded-qb-md', focusRing)}>
        <Avatar name={row.full_name} src={row.avatar_url} tone="brand" decorative className="size-[46px] text-qb-body-sm font-medium text-qb-ink-title" />
        <span className="min-w-0 flex-1">
          <span className="block truncate text-qb-body font-medium text-qb-ink-title">
            <bdi>{row.full_name}</bdi>
          </span>
          <span className="mt-[3px] block text-qb-label text-qb-ink-subtle">{tPlural('companies.followers', row.followers_count, locale)}</span>
        </span>
      </Link>
      <button
        type="button"
        onClick={toggle}
        aria-busy={mutation.isPending || undefined}
        className={cn(
          'mt-auto flex h-10 w-full cursor-pointer items-center justify-center gap-[7px] rounded-qb-md border text-qb-caption font-medium transition-colors',
          following ? 'border-qb-line bg-qb-surface text-qb-ink-title' : 'border-qb-brand bg-qb-brand text-qb-on-brand hover:bg-qb-brand-hover',
          focusRing,
        )}
      >
        {mutation.isPending ? <Loader2Icon className="size-4 animate-spin" aria-hidden /> : following ? <Check className="size-[13px]" strokeWidth={2.2} aria-hidden /> : null}
        {following ? t('users.follow.following') : t('users.connections.follow_back')}
        <span className="sr-only"> {row.full_name}</span>
      </button>
    </article>
  );
}

/** "You're not following anyone yet" / "You don't have any followers yet" with the way to the catalogue. */
function ConnectionsEmpty({ kind }: { kind: FollowListKind }) {
  return (
    <section className="rounded-[20px] border border-qb-line bg-qb-surface px-6 py-[clamp(48px,8vw,74px)] text-center">
      <span aria-hidden="true" className="mb-[22px] inline-flex size-[76px] items-center justify-center rounded-full bg-qb-brand-soft text-qb-brand">
        <Icon icon={UserPlus} className="size-[34px]" strokeWidth={1.6} />
      </span>
      <h2 className="mb-2.5 text-qb-h4 font-semibold tracking-normal text-qb-icon">{t(`users.connections.empty.${kind}.title`)}</h2>
      <p className="mx-auto mb-[26px] max-w-[420px] text-qb-body-sm leading-[1.6] text-qb-ink-note">{t(`users.connections.empty.${kind}.body`)}</p>
      <Link
        href="/categories"
        className={cn('inline-flex rounded-qb-md bg-qb-brand px-[30px] py-[13px] text-qb-body-sm font-semibold text-qb-on-brand hover:bg-qb-brand-hover', focusRing)}
      >
        {t(`users.connections.empty.${kind}.cta`)}
      </Link>
    </section>
  );
}
