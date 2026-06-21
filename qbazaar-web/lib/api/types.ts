/**
 * Shared TypeScript types mirroring qbazaar-contracts/openapi/v1.yaml.
 *
 * These shapes are 1:1 with the OpenAPI schemas and should change ONLY when
 * the contract changes. The Prism mock and the eventual Laravel backend both
 * promise these envelopes.
 */

// ── Auth domain ─────────────────────────────────────────────────────────────
export type AccountType = 'private' | 'business';

export type UserStatus =
  | 'active'
  | 'suspended'
  | 'deactivated'
  | 'pending_deletion';

export type Language = 'ar' | 'en';

export interface User {
  id: string;
  full_name: string;
  email: string;
  phone: string;
  account_type: AccountType;
  status: UserStatus;
  email_verified: boolean;
  phone_verified: boolean;
  language: Language;
  avatar_url: string | null;
  avatar_thumb_url?: string | null;
  avatar_medium_url?: string | null;
  created_at: string;
}

export interface Token {
  access_token: string;
  refresh_token: string;
  token_type: 'Bearer';
  expires_in: number;
}

// ── Request bodies ──────────────────────────────────────────────────────────
export interface RegisterRequest {
  full_name: string;
  email: string;
  phone: string;
  password: string;
  account_type: AccountType;
  language?: Language;
  accepted_terms: true;
}

export interface LoginRequest {
  identifier: string;
  password: string;
}

export interface RefreshRequest {
  refresh_token: string;
}

// ── Envelopes ──────────────────────────────────────────────────────────────
export interface SuccessEnvelope<T> {
  success: true;
  data: T;
}

export interface ApiError {
  code: string;
  message_key: string;
  message: string;
  details?: Record<string, string[]> | null;
  request_id?: string;
}

export interface ErrorEnvelope {
  success: false;
  error: ApiError;
}

export type AuthResponseData = {
  user: User;
  tokens: Token;
};

export type AuthResponseEnvelope = SuccessEnvelope<AuthResponseData>;

// ── OTP / password-reset / email-verification (Wave 2) ─────────────────────
export interface OtpSendRequest {
  phone: string;
}

export interface OtpVerifyRequest {
  phone: string;
  code: string;
}

export interface OtpSendResponseData {
  sent_to: string;
  expires_in: number;
  can_resend_in: number;
}

export type OtpSendResponseEnvelope = SuccessEnvelope<OtpSendResponseData>;

export interface OtpVerifyResponseData {
  phone_verified: true;
}

export interface ForgotPasswordRequest {
  email: string;
}

export interface ResetPasswordRequest {
  email: string;
  token: string;
  password: string;
  password_confirmation: string;
}

export interface VerifyEmailQuery {
  signature?: string;
  expires?: string;
}

// Stable list of error codes the UI switches on (see error-codes.md).
export const AuthErrorCode = {
  InvalidCredentials: 'AUTH_001',
  AccountSuspended: 'AUTH_002',
  PhoneNotVerified: 'AUTH_003',
  OtpExpired: 'AUTH_004',
  OtpInvalid: 'AUTH_005',
  AuthRateLimited: 'AUTH_006',
  EmailExists: 'AUTH_007',
  PhoneExists: 'AUTH_008',
  TokenExpired: 'AUTH_009',
  TokenInvalid: 'AUTH_010',
  ValidationFailed: 'VALIDATION_FAILED',
  RateLimited: 'RATE_LIMIT_EXCEEDED',
} as const;

export type AuthErrorCodeValue =
  (typeof AuthErrorCode)[keyof typeof AuthErrorCode];

// ── Account / Users domain (Sprint 2) ──────────────────────────────────────
// Shapes are aligned with the upcoming OpenAPI schemas — the backend agent
// will land the contract update in this same wave.

/**
 * Counters surfaced on the authenticated account dashboard.
 * Backend: `GET /account/summary`.
 */
export interface AccountSummary {
  ads_count: number;
  drafts_count: number;
  conversations_count: number;
  unread_notifications_count: number;
}

/**
 * The full editable profile for the currently signed-in user.
 * Backend: `GET /account/profile`.
 */
export interface AccountProfile extends User {
  bio: string | null;
}

export interface UpdateProfileRequest {
  full_name: string;
  language: Language;
  bio?: string | null;
}

export interface ChangePasswordRequest {
  current_password: string;
  new_password: string;
  password_confirmation: string;
}

/**
 * Privacy toggles persisted in a JSON column on `users`.
 * Backend: `GET, PUT /account/privacy-settings`.
 */
export interface PrivacySettings {
  show_phone: boolean;
  show_email: boolean;
  allow_chat: boolean;
  indexed_by_search: boolean;
}

export interface VerificationStatus {
  email_verified: boolean;
  phone_verified: boolean;
  business_verified: boolean;
  kyc_verified: boolean;
}

/**
 * One row in the active-sessions list.
 * Backend: `GET /account/sessions`.
 */
export interface UserSession {
  id: string;
  device_label: string | null;
  ip_address: string | null;
  user_agent: string | null;
  last_used_at: string;
  created_at: string;
  is_current: boolean;
}

export interface BlockedUser {
  id: string;
  full_name: string;
  avatar_url: string | null;
  blocked_at: string;
}

export interface PublicUserProfile {
  id: string;
  full_name: string;
  avatar_url: string | null;
  account_type: AccountType;
  email_verified: boolean;
  phone_verified: boolean;
  ads_count: number;
  joined_at: string;
  bio: string | null;
}

/**
 * Minimal ad shape used by the public profile's "Ads" tab.
 * The real Ad schema lands in Sprint 4 — this is intentionally narrow.
 */
export interface PublicUserAd {
  id: string;
  title: string;
  price: number;
  currency: string;
  thumbnail_url: string | null;
  created_at: string;
}

export interface PaginationMeta {
  current_page: number;
  per_page: number;
  total: number;
  last_page: number;
}

export interface PaginatedEnvelope<T> {
  success: true;
  data: T[];
  meta: PaginationMeta;
}

export const UserErrorCode = {
  UserNotFound: 'USER_001',
  PasswordIncorrect: 'USER_002',
  AlreadyBlocked: 'USER_003',
  CannotBlockSelf: 'USER_004',
  PrivacyDenied: 'USER_005',
  // Wave 2 — account-lifecycle codes
  DeactivationPasswordRequired: 'USER_006',
  DeletionPasswordRequired: 'USER_007',
  DataExportPending: 'USER_008',
  AvatarInvalidImage: 'USER_009',
  AvatarTooLarge: 'USER_010',
} as const;

export type UserErrorCodeValue =
  (typeof UserErrorCode)[keyof typeof UserErrorCode];

// ── Account lifecycle / Avatar (Sprint 2 Wave 2) ───────────────────────────

/**
 * Response from `POST /account/data-export-request`.
 * The actual export file is delivered out-of-band via email — the API only
 * confirms the job has been queued.
 */
export interface DataExportResponse {
  status: 'queued' | 'processing' | 'ready';
  requested_at: string;
  estimated_ready_in_minutes?: number | null;
}

export interface DeactivateAccountRequest {
  password: string;
  reason?: string | null;
}

export interface DeleteAccountRequest {
  password: string;
  reason?: string | null;
}

/**
 * Response from `POST /uploads/avatar`. The backend returns three URLs:
 *
 * - `avatar_url`        full-resolution image (cropped 1:1 by the client)
 * - `avatar_thumb_url`  ~64px thumbnail used in lists + sidebar
 * - `avatar_medium_url` ~256px medium used on profile cards
 */
export interface AvatarUploadResponse {
  avatar_url: string;
  avatar_thumb_url: string;
  avatar_medium_url: string;
}

// ── Reference data (Sprint 3) ──────────────────────────────────────────────
// Categories + locations are read-only public data loaded once and cached
// aggressively (the contract advertises 1h/24h cache headers). The shapes
// below mirror `qbazaar-contracts/openapi/v1.yaml` for Sprint 3.

/**
 * Bilingual string used everywhere reference data needs both Arabic and
 * English labels (categories, filters, fields, locations).
 */
export type LocalizedString = { ar: string; en: string };

/**
 * One row of `categories`. `custom_fields` and `custom_filters` are surfaced
 * inline on the category detail/listing pages — they're nullable because the
 * leaf-vs-parent distinction is enforced by the backend, not the schema.
 */
export interface Category {
  id: string;
  parent_id: string | null;
  slug: string;
  name: LocalizedString;
  description: LocalizedString | null;
  /** Lucide-react icon name, e.g. 'Car', 'Home', 'Smartphone'. */
  icon: string | null;
  order: number;
  is_active: boolean;
  custom_fields: CategoryField[] | null;
  custom_filters: CategoryFilter[] | null;
  /** Cached count surfaced by the API; zero until Sprint 5 ships ads. */
  ads_count: number;
  created_at: string;
  updated_at: string;
}

/**
 * A category plus its nested children — the shape returned by
 * `GET /api/v1/categories/tree`. Used for the explorer + breadcrumb.
 */
export interface CategoryNode extends Category {
  children: CategoryNode[];
}

export type CategoryFilterType = 'select' | 'range' | 'boolean';

export interface CategoryFilter {
  key: string;
  label: LocalizedString;
  type: CategoryFilterType;
  options: string[] | null;
}

export type CategoryFieldType =
  | 'text'
  | 'number'
  | 'select'
  | 'boolean'
  | 'date';

export interface CategoryField {
  key: string;
  label: LocalizedString;
  type: CategoryFieldType;
  required: boolean;
  options: string[] | null;
}

export interface CategoryStats {
  ads_count: number;
  sub_ads_count: number;
}

export type LocationType = 'city' | 'district' | 'area';

/**
 * One node in the Qatar location tree. Children are inlined so the whole
 * tree fits in a single payload (it's small + extremely cacheable).
 */
export interface Location {
  id: string;
  parent_id: string | null;
  slug: string;
  name: LocalizedString;
  type: LocationType;
  lat: number | null;
  lng: number | null;
  children: Location[];
}

export type ReferenceErrorCode = 'CATEGORY_NOT_FOUND' | 'LOCATION_NOT_FOUND';

// ── Ads / Media (Sprint 4 + 5) ─────────────────────────────────────────────
// Wave A covers the public listing surface plus owner CRUD. Search, favourites
// and messaging hooks are deliberately deferred to later sprints — these
// shapes mirror `qbazaar-contracts/openapi/v1.yaml` (BE-4.x / BE-5.x).

export type PriceType = 'fixed' | 'negotiable' | 'free' | 'contact';
export type AdCondition = 'new' | 'like_new' | 'used';
export type AdStatus =
  | 'draft'
  | 'pending'
  | 'active'
  | 'sold'
  | 'expired'
  | 'rejected'
  | 'blocked';

/**
 * The three responsive variants the backend pre-renders for every uploaded
 * ad image, plus the canonical WebP fallback. URLs are absolute.
 */
export interface MediaSizes {
  thumbnail: string;
  medium: string;
  large: string;
  original_webp: string;
}

/**
 * One image (or attachment) tied to an ad. `blurhash` is the compact preview
 * the UI decodes client-side while the full image loads. `order` is the
 * gallery sort key and is mutated by the reorder endpoint.
 */
export interface Media {
  id: string;
  collection: string;
  url: string;
  sizes: MediaSizes;
  blurhash: string | null;
  width: number | null;
  height: number | null;
  order: number;
  size_bytes: number;
}

/**
 * Lean user shape embedded on the ad detail payload. The full `PublicUserProfile`
 * lives on the dedicated user-profile endpoint — this is the slice the ad-detail
 * sidebar needs.
 */
export interface PublicUser {
  id: string;
  full_name: string;
  avatar_url: string | null;
  avatar_thumb_url?: string | null;
  account_type: AccountType;
  email_verified: boolean;
  phone_verified: boolean;
  joined_at: string;
  ads_count?: number;
}

export interface Ad {
  id: string;
  user_id: string;
  category_id: string;
  location_id: string;
  latitude: number | null;
  longitude: number | null;
  title: string;
  description: string;
  price: number | null;
  price_type: PriceType;
  currency: 'QAR';
  condition: AdCondition | null;
  status: AdStatus;
  /** Free-form bag keyed by `CategoryField.key` — values arrive verbatim. */
  custom_fields: Record<string, unknown>;
  views_count: number;
  favorites_count: number;
  published_at: string | null;
  expires_at: string | null;
  created_at: string;
  updated_at: string;

  // Included on `GET /ads/{id}` and `POST/PUT` responses.
  user?: PublicUser;
  category?: Category;
  location?: Location;
  /** Ordered by `order` asc — UI should not re-sort. */
  images?: Media[];
}

/**
 * Trimmed shape returned by the public list endpoint — keeps payloads small
 * for the home feed. The full ad is fetched on the detail page.
 */
export interface AdSummary {
  id: string;
  title: string;
  price: number | null;
  price_type: PriceType;
  currency: 'QAR';
  status: AdStatus;
  primary_image: Media | null;
  location_slug: string;
  category_slug: string;
  published_at: string | null;
  created_at: string;
}

export interface CreateAdRequest {
  category_id: string;
  location_id: string;
  latitude?: number | null;
  longitude?: number | null;
  title: string;
  description: string;
  price: number | null;
  price_type: PriceType;
  condition: AdCondition | null;
  custom_fields: Record<string, unknown>;
}

export type UpdateAdRequest = Partial<CreateAdRequest>;

export type AdErrorCode =
  | 'AD_NOT_FOUND'
  | 'AD_NOT_PUBLISHABLE'
  | 'AD_IMAGES_TOO_MANY'
  | 'AD_IMAGE_NOT_FOUND'
  | 'AD_INVALID_STATUS_TRANSITION';

/**
 * Laravel-style paginated envelope used by every list endpoint that exposes
 * cursorless pagination (the ad feed + the owner-scoped `account/ads`).
 */
export interface PaginatedResponse<T> {
  data: T[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
  links: {
    first: string | null;
    last: string | null;
    prev: string | null;
    next: string | null;
  };
}

// ── Search (Sprint 6) ──────────────────────────────────────────────────────
// The search domain reuses `AdSummary` for results — its public shape — and
// adds the faceted-search envelope on top. Saved searches let the user
// snapshot a parameter bag and rerun it from the account area.

export type SortMode = 'latest' | 'oldest' | 'price_asc' | 'price_desc';

/**
 * The full set of query parameters accepted by `GET /api/v1/search`. Each
 * field is optional: when absent the backend treats it as "no filter". Slug
 * + id duplicates exist because the URL is slug-driven but the backend will
 * accept either to keep call-sites flexible.
 */
export interface SearchQueryParams extends Record<string, unknown> {
  q?: string;
  category_id?: string;
  category_slug?: string;
  location_id?: string;
  location_slug?: string;
  price_min?: number;
  price_max?: number;
  condition?: AdCondition;
  price_type?: PriceType;
  sort?: SortMode;
  page?: number;
  per_page?: number;
}

/**
 * Aggregated counts returned alongside the result page so the UI can render
 * "147 in cars" badges next to filter rows. The keys are slugs (categories,
 * locations) or canonical enum values (conditions) — never display strings.
 */
export interface SearchFacets {
  categories: Record<string, number>;
  locations: Record<string, number>;
  price_buckets: { range: string; count: number }[];
  conditions: Record<AdCondition, number>;
}

export interface SearchResponse extends PaginatedResponse<AdSummary> {
  facets: SearchFacets;
}

export interface SearchSuggestion {
  text: string;
}

/**
 * One saved search row owned by the current user. `query_params` is the same
 * bag accepted by `runSearch` — the "Run" action just restores it onto the
 * URL.
 */
export interface SavedSearch {
  id: string;
  name: string;
  query_params: SearchQueryParams;
  created_at: string;
}

export type SearchErrorCode =
  | 'SEARCH_INVALID_PARAMS'
  | 'SAVED_SEARCH_LIMIT'
  | 'SAVED_SEARCH_NOT_FOUND';

// ── Favorites + Recently Viewed (Sprint 7) ─────────────────────────────────
// Favorites are a per-user toggle on an ad. The backend returns the new
// favorited state plus the total favorite count so the UI never has to guess.
// Recently-viewed entries are recorded silently on every ad-detail mount; an
// anonymous client sends a `X-Session-Id` header (UUID stored in localStorage)
// so the backend can stitch the history once the user signs in.

export interface FavoriteToggleResponse {
  favorited: boolean;
  count: number;
}

export interface FavoritedAdSummary extends AdSummary {
  favorited_at: string;
}

export interface RecentlyViewedAdSummary extends AdSummary {
  viewed_at: string;
}

export type FavoritesErrorCode = 'FAVORITE_AD_NOT_FOUND' | 'FAVORITE_FORBIDDEN';
export type RecentlyViewedErrorCode = 'RECENTLY_VIEWED_AD_NOT_FOUND';

// ── Messaging (Sprint 8) ───────────────────────────────────────────────────
// 1:1 buyer↔seller conversations scoped to a single ad. Real-time delivery is
// powered by Reverb (over the Pusher protocol); the shapes below mirror the
// `BE-8.x` contract additions and stay decoupled from the broadcaster.

export type MessageType = 'text' | 'offer' | 'system';

/**
 * Lean row used by the conversations index. The ad slice is kept small —
 * everything an inbox row needs (image, title, price headline) — so the list
 * stays light even with hundreds of conversations.
 */
export interface ConversationListItem {
  id: string;
  ad: {
    id: string;
    title: string;
    primary_image: Media | null;
    price: number | null;
    price_type: PriceType;
    currency: 'QAR';
  };
  other_participant: {
    id: string;
    full_name: string;
    avatar_thumb_url: string | null;
  };
  last_message_preview: string | null;
  last_message_at: string | null;
  unread_count: number;
}

/**
 * Full conversation envelope returned by show/start endpoints. Extends the
 * list item with the buyer/seller ids so the client can decide which side of
 * the chat to render.
 */
export interface Conversation extends ConversationListItem {
  buyer_id: string;
  seller_id: string;
  created_at: string;
}

export interface Message {
  id: string;
  conversation_id: string;
  sender_id: string;
  body: string;
  type: MessageType;
  read_at: string | null;
  created_at: string;
  sender: {
    id: string;
    full_name: string;
    avatar_thumb_url: string | null;
  };
  /**
   * Populated when `type === 'offer'`. The backend bundles the offer envelope
   * onto the message so the chat timeline can render an OfferBubble inline
   * without an extra round-trip.
   */
  offer?: Offer | null;
}

export interface UnreadCountResponse {
  total: number;
}

export type MessagingErrorCode =
  | 'CONVERSATION_NOT_FOUND'
  | 'CONVERSATION_BLOCKED'
  | 'CONVERSATION_OWN_AD'
  | 'MESSAGE_NOT_FOUND';

// ── Offers (Sprint 9) ──────────────────────────────────────────────────────
// Buyer-initiated price offers attached to a conversation. The lifecycle is
// pending → accepted | rejected | withdrawn | expired and the same Echo
// channel (`conversation.{id}`) broadcasts every state change.

export type OfferStatus =
  | 'pending'
  | 'accepted'
  | 'rejected'
  | 'withdrawn'
  | 'expired';

export interface Offer {
  id: string;
  conversation_id: string;
  ad_id: string;
  buyer_id: string;
  seller_id: string;
  message_id: string | null;
  /** Amount in QAR. The server emits this as a string decimal; the API
   *  client coerces it into a number before it reaches consumers. */
  amount: number;
  currency: 'QAR';
  note: string | null;
  status: OfferStatus;
  expires_at: string;
  accepted_at: string | null;
  rejected_at: string | null;
  withdrawn_at: string | null;
  created_at: string;
  /** Convenience flag computed server-side so the UI can decide whether to
   *  show "accept/reject" (seller) or "withdraw" (buyer). */
  viewer_role: 'buyer' | 'seller';
}

export interface CreateOfferRequest {
  amount: number;
  note?: string | null;
}

export type OfferErrorCode =
  | 'OFFER_NOT_FOUND'
  | 'OFFER_ACTIVE_EXISTS'
  | 'OFFER_OWN_AD'
  | 'OFFER_AD_NOT_ACTIVE'
  | 'OFFER_NOT_PENDING'
  | 'OFFER_FORBIDDEN';

// ── Notifications (Sprint 10) ──────────────────────────────────────────────
// The notifications center reuses the standard Laravel notifications table:
// every row carries the polymorphic `type` (FQCN) plus a stable `category`
// slug the UI can switch on. `cta_url` is the link to click when the row is
// pressed; `icon` is a lucide name resolved via DynamicIcon.

export interface Notification {
  id: string;
  type: string;
  category: string;
  title: string;
  body: string;
  cta_url: string | null;
  icon: string | null;
  read_at: string | null;
  created_at: string;
}

export type NotificationErrorCode =
  | 'NOTIFICATION_NOT_FOUND'
  | 'NOTIFICATION_FORBIDDEN';

// ── Reports (Sprint 10) ────────────────────────────────────────────────────
// User-initiated abuse reports against an ad, user, conversation or message.
// `category` is a stable slug rendered as a radio group in ReportDialog. The
// backend rate-limits duplicate reports per target/category.

export type ReportTarget = 'ad' | 'user' | 'conversation' | 'message';

export type ReportCategory =
  | 'spam'
  | 'fraud'
  | 'inappropriate'
  | 'offensive'
  | 'duplicate'
  | 'wrong_category'
  | 'other';

export type ReportStatus = 'pending' | 'reviewed' | 'dismissed' | 'actioned';

export interface Report {
  id: string;
  target_type: ReportTarget;
  target_id: string;
  category: ReportCategory;
  description: string | null;
  status: ReportStatus;
  created_at: string;
}

export interface MakeReportRequest {
  target_type: ReportTarget;
  target_id: string;
  category: ReportCategory;
  description?: string;
}

export type ReportErrorCode =
  | 'REPORT_INVALID_TARGET'
  | 'REPORT_SELF'
  | 'REPORT_RECENT_DUPLICATE';

// ── CMS Pages / Help center / Support (Sprint 12) ──────────────────────────
// Pages are admin-authored static content (about, terms, privacy, contact).
// Help articles are categorised long-form content surfaced through search.
// Support tickets are a lightweight contact-us channel with replies.

/**
 * One row in the public pages index. `body` is intentionally omitted from the
 * list shape — the index endpoint is cheap to render and the body comes back
 * with the detail call.
 */
export interface PageListItem {
  id: string;
  slug: string;
  title: LocalizedString;
  display_order: number;
}

export interface Page extends PageListItem {
  body: LocalizedString;
  meta_description: LocalizedString | null;
  published_at: string | null;
}

export interface HelpCategory {
  id: string;
  slug: string;
  name: LocalizedString;
  description: LocalizedString | null;
  /** Lucide-react icon name, resolved via DynamicIcon. */
  icon: string | null;
  display_order: number;
  articles_count?: number;
}

export interface HelpArticleListItem {
  id: string;
  slug: string;
  title: LocalizedString;
  excerpt: LocalizedString | null;
  display_order: number;
}

export interface HelpArticle extends HelpArticleListItem {
  body: LocalizedString;
  views_count: number;
  category: {
    id: string;
    slug: string;
    name: LocalizedString;
    icon: string | null;
  };
}

export type SupportTicketStatus =
  | 'open'
  | 'in_progress'
  | 'waiting_user'
  | 'resolved'
  | 'closed';

export type SupportTicketCategory =
  | 'general'
  | 'billing'
  | 'technical'
  | 'abuse'
  | 'feedback'
  | 'other';

export type SupportTicketPriority = 'low' | 'normal' | 'high' | 'urgent';

export interface SupportTicketListItem {
  id: string;
  subject: string;
  category: SupportTicketCategory;
  status: SupportTicketStatus;
  priority: SupportTicketPriority;
  last_replied_at: string | null;
  replies_count: number;
  created_at: string;
}

export interface SupportReply {
  id: string;
  author: { id: string; name: string; is_staff: boolean };
  body: string;
  created_at: string;
}

export interface SupportTicket extends SupportTicketListItem {
  body: string;
  email: string | null;
  replies: SupportReply[];
}

export interface MakeSupportTicketRequest {
  subject: string;
  category: SupportTicketCategory;
  body: string;
  email?: string;
}

export type CmsErrorCode = 'PAGE_NOT_FOUND';

export type HelpErrorCode =
  | 'HELP_CATEGORY_NOT_FOUND'
  | 'HELP_ARTICLE_NOT_FOUND';

export type SupportErrorCode =
  | 'TICKET_NOT_FOUND'
  | 'TICKET_FORBIDDEN'
  | 'TICKET_INVALID_TRANSITION';
