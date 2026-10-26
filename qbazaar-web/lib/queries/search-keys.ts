import type { SearchQueryParams } from '@/lib/api/types';

export const searchKeys = {
  all: ['search'] as const,
  results: () => [...searchKeys.all, 'results'] as const,
  result: (params: SearchQueryParams) =>
    [...searchKeys.results(), params] as const,
  suggestions: () => [...searchKeys.all, 'suggestions'] as const,
  suggestion: (q: string) => [...searchKeys.suggestions(), q] as const,
  saved: () => [...searchKeys.all, 'saved'] as const,
};
