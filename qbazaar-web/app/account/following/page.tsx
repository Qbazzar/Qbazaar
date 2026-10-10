'use client';

import { ConnectionsView } from '@/components/users/ConnectionsView';

/** Accounts the signed-in user follows (users.html, "Following" tab). Auth gated by `account/layout`. */
export default function FollowingPage() {
  return <ConnectionsView kind="following" />;
}
