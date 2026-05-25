'use client';

/**
 * Hero search bar for the homepage — QBFront `.search-bar` markup.
 *
 * Routes to `/search?q=...&location=...` on submit. Category and distance
 * are placeholder UI for now (the real category browse already lives at
 * `/categories` and on `/search` itself).
 */
import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { t } from '@/lib/i18n/messages';

export function HomeSearchBar() {
  const router = useRouter();
  const [q, setQ] = useState('');
  const [location, setLocation] = useState('');

  const submit = (e: React.FormEvent) => {
    e.preventDefault();
    const params = new URLSearchParams();
    if (q.trim()) params.set('q', q.trim());
    if (location.trim()) params.set('location', location.trim());
    const qs = params.toString();
    router.push(qs ? `/search?${qs}` : '/search');
  };

  return (
    <form className="search-bar" role="search" onSubmit={submit}>
      <div className="search-field">
        <svg
          className="search-field__icon"
          width="16"
          height="16"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          strokeWidth="1.6"
          strokeLinecap="round"
        >
          <circle cx="11" cy="11" r="7" />
          <path d="m20 20-3.5-3.5" />
        </svg>
        <input
          type="search"
          placeholder={t('search.placeholder', 'ابحث')}
          aria-label={t('search.placeholder', 'ابحث')}
          value={q}
          onChange={(e) => setQ(e.target.value)}
        />
      </div>
      <div className="search-field">
        <svg
          className="search-field__icon"
          width="16"
          height="16"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          strokeWidth="1.6"
        >
          <rect x="3" y="3" width="7" height="7" rx="1" />
          <rect x="14" y="3" width="7" height="7" rx="1" />
          <rect x="3" y="14" width="7" height="7" rx="1" />
          <rect x="14" y="14" width="7" height="7" rx="1" />
        </svg>
        <input
          placeholder={t('categories.pick', 'اختر القسم')}
          aria-label={t('categories.pick', 'اختر القسم')}
          readOnly
          onFocus={() => router.push('/categories')}
        />
        <svg
          className="search-field__chevron"
          width="14"
          height="14"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          strokeWidth="1.8"
          strokeLinecap="round"
        >
          <path d="m6 9 6 6 6-6" />
        </svg>
      </div>
      <div className="search-field">
        <svg
          className="search-field__icon"
          width="16"
          height="16"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          strokeWidth="1.6"
          strokeLinecap="round"
          strokeLinejoin="round"
        >
          <path d="M12 22s7-7 7-13a7 7 0 1 0-14 0c0 6 7 13 7 13z" />
          <circle cx="12" cy="9" r="2.5" />
        </svg>
        <input
          placeholder={t('search.location_placeholder', 'الموقع')}
          aria-label={t('search.location_placeholder', 'الموقع')}
          value={location}
          onChange={(e) => setLocation(e.target.value)}
        />
      </div>
      <div className="search-field">
        <input
          placeholder={t('search.distance_placeholder', 'المسافة')}
          aria-label={t('search.distance_placeholder', 'المسافة')}
          readOnly
        />
        <svg
          className="search-field__chevron"
          width="14"
          height="14"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          strokeWidth="1.8"
          strokeLinecap="round"
        >
          <path d="m6 9 6 6 6-6" />
        </svg>
      </div>
      <button type="submit" className="btn btn--primary">
        {t('search.submit', 'بحث')}
      </button>
    </form>
  );
}
