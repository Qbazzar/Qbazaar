'use client';

/**
 * FE-2.13 + FE-2.14 — Public user profile.
 *
 * Two tabs:
 *   1. Ads — paginated via `GET /users/{id}/ads`. Backend returns an empty
 *      list for now; we render a friendly empty state with the user's name.
 *   2. About — bio + account type. Lightweight today; richer signals (city,
 *      ratings, etc.) land in later sprints.
 *
 * "Send message" + "Report" are placeholders (Sprint 8 / Sprint 10). "Block"
 * uses the shared `BlockUserButton` component.
 */
import { use, useState } from 'react';
import { useRouter, useSearchParams } from 'next/navigation';
import { useQuery } from '@tanstack/react-query';
import { toast } from 'sonner';
import {
  Loader2Icon,
  MailIcon,
  AlertCircleIcon,
  CheckCircle2Icon,
} from 'lucide-react';

import {
  Avatar,
  AvatarFallback,
  AvatarImage,
} from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
  Tabs,
  TabsList,
  TabsTrigger,
  TabsContent,
} from '@/components/ui/tabs';
import { BlockUserButton } from '@/components/users/BlockUserButton';
import { ReportButton } from '@/components/reports/ReportButton';
import { t } from '@/lib/i18n/messages';
import { formatMonthYear } from '@/lib/utils';
import { getPublicProfile, getUserAds } from '@/lib/api/users';
import { ApiClientError } from '@/lib/api/auth';
import { useAuth } from '@/hooks/useAuth';
import type { PublicUserProfile } from '@/lib/api/types';

interface PageProps {
  params: Promise<{ id: string }>;
}

export default function PublicProfilePage({ params }: PageProps) {
  const { id } = use(params);
  const router = useRouter();
  const search = useSearchParams();
  const { user: authedUser } = useAuth();
  const initialTab = search.get('tab') === 'about' ? 'about' : 'ads';
  const [tab, setTab] = useState<'ads' | 'about'>(initialTab);
  const [blocked, setBlocked] = useState(false);

  const profileQuery = useQuery({
    queryKey: ['users', id, 'public-profile'],
    queryFn: () => getPublicProfile(id),
    retry: (failureCount, err) => {
      // Don't keep retrying 404s — the user simply doesn't exist.
      if (err instanceof ApiClientError && err.status === 404) return false;
      return failureCount < 2;
    },
  });

  if (profileQuery.isLoading) {
    return (
      <div
        className="flex min-h-svh items-center justify-center"
        role="status"
        aria-live="polite"
      >
        <Loader2Icon
          className="text-muted-foreground size-6 animate-spin"
          aria-hidden
        />
      </div>
    );
  }

  if (
    profileQuery.error ||
    !profileQuery.data
  ) {
    return (
      <div className="bg-cream-50 flex min-h-svh items-center justify-center px-4 py-10">
        <div className="bg-card ring-foreground/10 max-w-md rounded-2xl p-8 text-center ring-1">
          <span className="bg-coral/10 text-coral mx-auto mb-3 inline-flex size-10 items-center justify-center rounded-full">
            <AlertCircleIcon className="size-5" aria-hidden />
          </span>
          <h1 className="font-display text-2xl tracking-tight">
            {t('users.profile.not_found_title')}
          </h1>
          <p className="text-muted-foreground mt-2 text-sm">
            {t('users.profile.not_found_body')}
          </p>
          <Button
            className="mt-6 rounded-full"
            onClick={() => router.push('/')}
          >
            {t('common.retry')}
          </Button>
        </div>
      </div>
    );
  }

  const profile = profileQuery.data;
  const isSelf = authedUser?.id === profile.id;

  return (
    <main>
      <div className="container" style={{ paddingTop: 24, paddingBottom: 48 }}>
        <PublicProfileHeader
          profile={profile}
          isSelf={isSelf}
          blocked={blocked}
          onBlocked={() => setBlocked(true)}
          onPlaceholderMessage={() =>
            toast.info(t('users.profile.send_message_soon'))
          }
        />

        <Tabs
          value={tab}
          onValueChange={(value: string) => setTab(value as 'ads' | 'about')}
        >
          <TabsList variant="line" className="tab-bar">
            <TabsTrigger value="ads" className="tab-bar__btn">
              {t('users.profile.tabs.ads')}
            </TabsTrigger>
            <TabsTrigger value="about" className="tab-bar__btn">
              {t('users.profile.tabs.about')}
            </TabsTrigger>
          </TabsList>

          <TabsContent value="ads">
            <UserAdsTab userId={profile.id} userName={profile.full_name} />
          </TabsContent>

          <TabsContent value="about">
            <AboutTab profile={profile} />
          </TabsContent>
        </Tabs>
      </div>
    </main>
  );
}

// ── Header ────────────────────────────────────────────────────────────────
function PublicProfileHeader({
  profile,
  isSelf,
  blocked,
  onBlocked,
  onPlaceholderMessage,
}: {
  profile: PublicUserProfile;
  isSelf: boolean;
  blocked: boolean;
  onBlocked: () => void;
  onPlaceholderMessage: () => void;
}) {
  return (
    <header className="profile-head">
      <div className="profile-head__avatar">
        {profile.avatar_url ? (
          <Avatar size="lg" className="size-24">
            <AvatarImage src={profile.avatar_url} alt={profile.full_name} />
            <AvatarFallback>{initials(profile.full_name)}</AvatarFallback>
          </Avatar>
        ) : (
          initials(profile.full_name) || 'Q'
        )}
      </div>

      <div className="profile-head__info">
        <div className="profile-head__name">{profile.full_name}</div>
        <div className="profile-head__meta">
          <span>
            {t('users.profile.joined', {
              date: formatMonthYear(profile.joined_at),
            })}
          </span>
        </div>

        <div className="profile-head__pills">
          <Badge variant="outline" className="border-coral/30 text-coral">
            {t(`users.profile.account_type.${profile.account_type}`)}
          </Badge>
          {profile.email_verified ? (
            <Badge className="bg-sage/15 text-sage border-sage/30 border">
              <CheckCircle2Icon className="size-3" aria-hidden />
              {t('account.verification.channels.email')}
            </Badge>
          ) : null}
          {profile.phone_verified ? (
            <Badge className="bg-sage/15 text-sage border-sage/30 border">
              <CheckCircle2Icon className="size-3" aria-hidden />
              {t('account.verification.channels.phone')}
            </Badge>
          ) : null}
          <Badge variant="outline">
            {t('users.profile.ads_count', { count: profile.ads_count })}
          </Badge>
        </div>
      </div>

      {!isSelf ? (
        <div className="profile-head__actions">
          <Button
            type="button"
            size="default"
            className="rounded-full px-4"
            onClick={onPlaceholderMessage}
          >
            <MailIcon className="size-4" aria-hidden />
            {t('users.profile.send_message')}
          </Button>
          {blocked ? (
            <Badge variant="destructive">{t('users.block.success')}</Badge>
          ) : (
            <BlockUserButton
              userId={profile.id}
              userName={profile.full_name}
              size="default"
              onBlocked={onBlocked}
            />
          )}
          <ReportButton target_type="user" target_id={profile.id} />
        </div>
      ) : null}
    </header>
  );
}

// ── Tabs ───────────────────────────────────────────────────────────────────
function UserAdsTab({
  userId,
  userName,
}: {
  userId: string;
  userName: string;
}) {
  const adsQuery = useQuery({
    queryKey: ['users', userId, 'ads'],
    queryFn: () => getUserAds(userId, { page: 1, per_page: 20 }),
  });

  if (adsQuery.isLoading) {
    return (
      <div className="flex justify-center py-10" role="status">
        <Loader2Icon
          className="text-muted-foreground size-5 animate-spin"
          aria-hidden
        />
      </div>
    );
  }

  if (adsQuery.error) {
    return (
      <p className="text-destructive text-sm" role="alert">
        {t('auth.errors.network')}
      </p>
    );
  }

  const ads = adsQuery.data?.data ?? [];

  if (ads.length === 0) {
    return (
      <div className="bg-card ring-foreground/10 rounded-2xl p-10 text-center ring-1">
        <p className="font-display text-ink-900 text-xl">
          {t('users.profile.ads_empty_title')}
        </p>
        <p className="text-muted-foreground mx-auto mt-1 max-w-sm text-sm">
          {t('users.profile.ads_empty_body', { name: userName })}
        </p>
      </div>
    );
  }

  // Real ad cards land in Sprint 4. This minimal preview gives the UI
  // something to render once the backend starts returning data.
  return (
    <ul className="grid gap-3 sm:grid-cols-2">
      {ads.map((ad) => (
        <li
          key={ad.id}
          className="bg-card ring-foreground/10 rounded-2xl p-4 ring-1"
        >
          <p className="text-ink-900 truncate text-sm font-semibold">
            {ad.title}
          </p>
          <p className="text-terracotta font-display mt-1 text-xl">
            {ad.price.toLocaleString()} {ad.currency}
          </p>
        </li>
      ))}
    </ul>
  );
}

function AboutTab({ profile }: { profile: PublicUserProfile }) {
  return (
    <div className="bg-card ring-foreground/10 rounded-2xl p-6 ring-1 sm:p-8">
      {profile.bio?.trim() ? (
        <p className="text-ink-700 text-sm leading-relaxed whitespace-pre-line">
          {profile.bio}
        </p>
      ) : (
        <p className="text-muted-foreground text-sm">
          {t('users.profile.no_bio')}
        </p>
      )}
    </div>
  );
}

function initials(fullName: string): string {
  return fullName
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase() ?? '')
    .join('');
}
