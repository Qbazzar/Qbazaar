import type { ListAdsParams, MyAdsParams } from '@/lib/api/ads';
export const adKeys = {
  all: ['ads'] as const,
  lists: () => [...adKeys.all, 'list'] as const,
  list: (params: ListAdsParams) => [...adKeys.lists(), params] as const,
  details: () => [...adKeys.all, 'detail'] as const,
  detail: (id: string) => [...adKeys.details(), id] as const,
  myLists: () => [...adKeys.all, 'mine'] as const,
  myList: (params: MyAdsParams) => [...adKeys.myLists(), params] as const,
  similar: (id: string) => [...adKeys.all, 'similar', id] as const,
  featured: () => [...adKeys.all, 'featured'] as const,
};
