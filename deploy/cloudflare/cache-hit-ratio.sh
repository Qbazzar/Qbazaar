#!/usr/bin/env bash
# QBazaar — edge cache hit ratio for one host over the last 24 hours
# (OPS-18.18: "image cache hit ratio reported").
#
# Runs anywhere with curl and jq; it never touches the server. Needs a
# Cloudflare API token with Zone > Analytics > Read on the zone, passed in the
# environment only:
#   CF_API_TOKEN=... CF_ZONE_ID=... bash deploy/cloudflare/cache-hit-ratio.sh            # cdn.qbazaar.qa
#   CF_API_TOKEN=... CF_ZONE_ID=... bash deploy/cloudflare/cache-hit-ratio.sh qbazaar.qa
#
# Counts every request by its cache status (hit, miss, expired, dynamic …).
# For media on cdn. a healthy ratio is above 90% once traffic has warmed the
# edge; a low one usually means a missing cache rule or cache-busting query
# strings. Free plans keep about a day of this data.

set -euo pipefail

HOST="${1:-cdn.qbazaar.qa}"
: "${CF_API_TOKEN:?set CF_API_TOKEN (Zone Analytics Read)}"
: "${CF_ZONE_ID:?set CF_ZONE_ID (dashboard > Overview > Zone ID)}"
command -v jq > /dev/null || { echo "needs jq" >&2; exit 1; }

UNTIL="$(date -u +%Y-%m-%dT%H:%M:%SZ)"
SINCE="$(date -u -d '-24 hours' +%Y-%m-%dT%H:%M:%SZ)"

QUERY='query ($zone: string, $host: string, $since: Time, $until: Time) {
  viewer {
    zones(filter: { zoneTag: $zone }) {
      httpRequestsAdaptiveGroups(
        limit: 50
        filter: { clientRequestHTTPHost: $host, datetime_geq: $since, datetime_lt: $until }
      ) {
        count
        dimensions { cacheStatus }
      }
    }
  }
}'

PAYLOAD="$(jq -n --arg q "$QUERY" --arg zone "$CF_ZONE_ID" --arg host "$HOST" --arg since "$SINCE" --arg until "$UNTIL" \
    '{ query: $q, variables: { zone: $zone, host: $host, since: $since, until: $until } }')"

# The token goes through stdin, never onto the command line.
RESPONSE="$(curl -fsS https://api.cloudflare.com/client/v4/graphql \
    -H @- -H 'Content-Type: application/json' --data "$PAYLOAD" <<< "Authorization: Bearer $CF_API_TOKEN")"

if [[ "$(jq '.errors | length' <<< "$RESPONSE")" != 0 ]]; then
    jq -r '.errors[].message' <<< "$RESPONSE" >&2
    exit 1
fi

jq -r --arg host "$HOST" '
  [.data.viewer.zones[0].httpRequestsAdaptiveGroups[] | { status: .dimensions.cacheStatus, count }] as $rows
  | ($rows | map(.count) | add // 0) as $total
  | ($rows | map(select(.status == "hit" or .status == "stale" or .status == "revalidated" or .status == "updating")) | map(.count) | add // 0) as $hits
  | "host: \($host), last 24 h",
    ($rows | sort_by(-.count)[] | "  \(.status): \(.count)"),
    "requests: \($total), served from the edge: \($hits)",
    (if $total > 0 then "hit ratio: \(($hits * 1000 / $total | floor) / 10)%" else "hit ratio: n/a (no traffic)" end)
' <<< "$RESPONSE"
