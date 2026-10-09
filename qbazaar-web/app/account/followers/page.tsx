'use client';

import { ConnectionsView } from '@/components/users/ConnectionsView';

/** Accounts that follow the signed-in user (users.html, "Followers" tab). Auth gated by `account/layout`. */
export default function FollowersPage() {
  return <ConnectionsView kind="followers" />;
}
