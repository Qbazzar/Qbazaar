/**
 * The signed-in user's follow lists (`GET /account/following`,
 * `GET /account/followers`): cursor pages, newest follow first. Each row is
 * the other account plus whether the viewer follows it.
 */
import { api } from './client';
import type { CursorPage } from './commerce-types';
import { query, unwrapBody } from './request';
import type { AccountType } from './types';

export type FollowListKind = 'following' | 'followers';

export interface FollowListUser {
  id: string;
  full_name: string;
  avatar_url: string | null;
  account_type: AccountType;
  followers_count: number;
  /** Whether the viewer follows this account. */
  is_following: boolean;
  followed_at: string | null;
}

export function listFollows(kind: FollowListKind, cursor?: string | null): Promise<CursorPage<FollowListUser>> {
  return unwrapBody(api.get<CursorPage<FollowListUser>>(`/api/v1/account/${kind}`, { params: query({ cursor }) }));
}
