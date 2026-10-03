<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Admin Panel labels (Sprint 11)
|--------------------------------------------------------------------------
|
| Strings consumed by the Filament admin resources, widgets, and actions.
| Kept in a separate file from `messages.php` so a regenerated public API
| message catalogue never accidentally drops admin keys.
|
| Keep keys short and predictable — the same translation is often reused
| across resources (e.g. `admin.fields.created_at`).
|
*/

return [
    'auth' => [
        'account_inactive' => 'This account is not active. Contact an administrator.',
        'password_change_required' => 'Choose a new password before continuing.',
        'password_reused' => 'The new password must differ from the current one.',
    ],

    'dashboard' => [
        'title' => 'Dashboard',
    ],

    'navigation' => [
        'settings' => 'Settings',
        'users' => 'Users',
        'ads' => 'Ads',
        'taxonomy' => 'Taxonomy',
        'categories' => 'Categories',
        'locations' => 'Locations',
        'moderation' => 'Moderation',
        'reports' => 'Reports',
        'moderation_rules' => 'Moderation rules',
        'activity' => 'Activity log',
        'comms' => 'Communications',
        'conversations' => 'Conversations',
        'messages' => 'Messages',
        'offers' => 'Offers',
        'notifications' => 'Notifications',
        'saved_searches' => 'Saved searches',
        'support_tickets' => 'Support tickets',
        'pages' => 'Pages',
        'help_categories' => 'Help categories',
        'help_articles' => 'Help articles',
        'roles' => 'Roles & permissions',
    ],

    'navigation_groups' => [
        'system' => 'System',
        'marketplace' => 'Marketplace',
        'communications' => 'Communications',
        'moderation' => 'Moderation',
        'taxonomy' => 'Taxonomy',
        'content' => 'Content',
        'audit' => 'Audit',
    ],

    'resources' => [
        'user' => [
            'label' => 'User',
            'plural' => 'Users',
        ],
        'ad' => [
            'label' => 'Ad',
            'plural' => 'Ads',
        ],
        'category' => [
            'label' => 'Category',
            'plural' => 'Categories',
        ],
        'location' => [
            'label' => 'Location',
            'plural' => 'Locations',
        ],
        'report' => [
            'label' => 'Report',
            'plural' => 'Reports',
        ],
        'notification' => [
            'label' => 'Notification',
            'plural' => 'Notifications',
        ],
        'conversation' => [
            'label' => 'Conversation',
            'plural' => 'Conversations',
        ],
        'message' => [
            'label' => 'Message',
            'plural' => 'Messages',
        ],
        'offer' => [
            'label' => 'Offer',
            'plural' => 'Offers',
        ],
        'saved_search' => [
            'label' => 'Saved search',
            'plural' => 'Saved searches',
        ],
        'moderation_rule' => [
            'label' => 'Moderation rule',
            'plural' => 'Moderation rules',
        ],
        'activity' => [
            'label' => 'Activity entry',
            'plural' => 'Activity log',
        ],
        'support_ticket' => [
            'label' => 'Support ticket',
            'plural' => 'Support tickets',
        ],
        'page' => [
            'label' => 'Page',
            'plural' => 'Pages',
        ],
        'help_category' => [
            'label' => 'Help category',
            'plural' => 'Help categories',
        ],
        'help_article' => [
            'label' => 'Help article',
            'plural' => 'Help articles',
        ],
        'role' => [
            'label' => 'Role',
            'plural' => 'Roles & permissions',
        ],
    ],

    'message' => [
        'type' => [
            'text' => 'Text',
            'offer' => 'Offer',
            'system' => 'System',
        ],
    ],

    'offer' => [
        'status' => [
            'pending' => 'Pending',
            'accepted' => 'Accepted',
            'rejected' => 'Rejected',
            'withdrawn' => 'Withdrawn',
            'expired' => 'Expired',
            'countered' => 'Countered',
        ],
    ],

    'report' => [
        'status' => [
            'pending' => 'Pending',
            'reviewed' => 'Reviewed',
            'dismissed' => 'Dismissed',
            'actioned' => 'Actioned',
        ],
        'target' => [
            'ad' => 'Listing',
            'user' => 'User',
            'conversation' => 'Conversation',
            'message' => 'Message',
        ],
    ],

    'location' => [
        'type' => [
            'city' => 'City',
            'district' => 'District',
            'area' => 'Area',
        ],
    ],

    'moderation_rule' => [
        'type' => [
            'banned_word' => 'Banned word',
            'blocked_domain' => 'Blocked domain',
        ],
        'language' => [
            'any' => 'Any',
            'ar' => 'Arabic',
            'en' => 'English',
        ],
    ],

    'support' => [
        'assigned_to_me' => 'Assign to me',
        'reply' => 'Reply',
        'change_status' => 'Change status',
        'category' => [
            'general' => 'General inquiry',
            'billing' => 'Billing',
            'technical' => 'Technical',
            'abuse' => 'Abuse',
            'feedback' => 'Feedback',
            'other' => 'Other',
        ],
        'status' => [
            'open' => 'Open',
            'in_progress' => 'In progress',
            'waiting_user' => 'Waiting on user',
            'resolved' => 'Resolved',
            'closed' => 'Closed',
        ],
        'priority' => [
            'low' => 'Low',
            'normal' => 'Normal',
            'high' => 'High',
            'urgent' => 'Urgent',
        ],
    ],

    'fields' => [
        'id' => 'ID',
        'avatar' => 'Avatar',
        'full_name' => 'Full name',
        'email' => 'Email',
        'phone' => 'Phone',
        'account_type' => 'Account type',
        'status' => 'Status',
        'email_verified' => 'Email verified',
        'phone_verified' => 'Phone verified',
        'language' => 'Language',
        'privacy_settings' => 'Privacy',
        'last_login_at' => 'Last login',
        'created_at' => 'Created',
        'updated_at' => 'Updated',
        'updated' => 'Updated',
        'opened' => 'Opened',
        'sent' => 'Sent',
        'title' => 'Title',
        'title_en' => 'Title (EN)',
        'title_ar' => 'Title (AR)',
        'description' => 'Description',
        'category' => 'Category',
        'location' => 'Location',
        'price' => 'Price',
        'price_type' => 'Price type',
        'currency' => 'Currency',
        'condition' => 'Condition',
        'featured' => 'Featured',
        'views_count' => 'Views',
        'favorites_count' => 'Favorites',
        'published_at' => 'Published',
        'expires_at' => 'Expires',
        'admin_notes' => 'Admin notes',
        'parent' => 'Parent',
        'slug' => 'Slug',
        'order' => 'Order',
        'icon' => 'Icon',
        'is_active' => 'Active',
        'lat' => 'Latitude',
        'lng' => 'Longitude',
        'type' => 'Type',
        'language_scope' => 'Language scope',
        'value' => 'Value',
        'target' => 'Target',
        'target_type' => 'Target type',
        'target_id' => 'Target ID',
        'reporter' => 'Reporter',
        'reviewer' => 'Reviewed by',
        'reviewed_at' => 'Reviewed at',
        'amount' => 'Amount',
        'accepted_at' => 'Accepted at',
        'rejected_at' => 'Rejected at',
        'withdrawn_at' => 'Withdrawn at',
        'last_message_at' => 'Last message',
        'message_count' => 'Messages',
        'buyer' => 'Buyer',
        'seller' => 'Seller',
        'ad' => 'Ad',
        'body' => 'Body',
        'read_at' => 'Read',
        'log_name' => 'Log',
        'subject' => 'Subject',
        'causer' => 'Caused by',
        'event' => 'Event',
        'properties' => 'Properties',
        'name' => 'Name',
        'name_en' => 'Name (EN)',
        'name_ar' => 'Name (AR)',
        'query_params' => 'Query',
        'custom_fields' => 'Custom fields',
        'custom_filters' => 'Custom filters',
        'excerpt' => 'Excerpt',
        'views' => 'Views',
        'articles' => 'Articles',
        'meta_description' => 'Meta description',
        'field_key' => 'Field key',
        'filter_key' => 'Filter key',
        'definition_json' => 'Definition (JSON)',
        'short_summary' => 'Short summary',
        'is_published' => 'Published',
        'priority' => 'Priority',
        'assignee' => 'Assignee',
        'last_reply' => 'Last reply',
        'replies' => 'Replies',
        'reply' => 'Reply',
        'author' => 'Author',
        'is_staff' => 'Staff?',
        'reply_as_staff' => 'Reply as staff',
        'roles' => 'Roles',
        'permissions' => 'Permissions',
        'users' => 'Users',
        'guard_name' => 'Guard',
        'sender' => 'Sender',
    ],

    'actions' => [
        'view' => 'View',
        'edit' => 'Edit',
        'delete' => 'Delete',
        'create' => 'Create',
        'save' => 'Save',
        'cancel' => 'Cancel',
        'ban' => 'Ban',
        'assign_to_me' => 'Assign to me',
        'unban' => 'Unban',
        'suspend' => 'Suspend',
        'reset_password' => 'Reset password',
        'view_as_user' => 'View public profile',
        'view_ads' => "View user's ads",
        'approve' => 'Approve',
        'reject' => 'Reject',
        'force_expire' => 'Force expire',
        'force_delete' => 'Force delete',
        'feature' => 'Toggle featured',
        'dismiss' => 'Dismiss',
        'mark_reviewed' => 'Mark reviewed',
        'mark_actioned' => 'Action taken',
        'bulk_dismiss' => 'Dismiss selected',
        'send_announcement' => 'Send announcement',
        'announcement_sent' => 'Announcement queued to :count recipients.',
        'reset_password_sent' => 'Password-reset email sent.',
        'ad_approved' => 'Ad approved and published.',
        'ad_rejected' => 'Ad rejected.',
        'report_dismissed' => 'Report dismissed.',
        'report_reviewed' => 'Report marked reviewed.',
        'report_actioned' => 'Report marked as actioned.',
        'ban_applied' => 'User banned.',
        'unban_applied' => 'User unbanned.',
        'suspend' => 'Suspend',
        'unsuspend' => 'Reactivate',
        'ad_suspended' => 'Ad suspended.',
        'ad_unsuspended' => 'Ad reactivated.',
    ],

    'price_type' => [
        'fixed' => 'Fixed price',
        'negotiable' => 'Negotiable',
        'free' => 'Free',
        'contact' => 'Contact for price',
    ],

    'condition' => [
        'new' => 'New',
        'like_new' => 'Like new',
        'used' => 'Used',
    ],

    'support_ticket_created' => [
        'title' => 'New support ticket',
        'body' => ':subject',
    ],

    'report_filed' => [
        'title' => 'New report',
        'body' => ':category — reported :target.',
    ],

    'ledger_reconciliation' => [
        'title' => 'Ledger reconciliation failed',
        'body' => 'Accounts out of balance: :accounts. Unbalanced transactions: :transactions. Malformed entries: :entries. Trial balance: :trial_balance. The details are in the application log.',
        'balanced' => 'holds',
        'unbalanced' => 'does not hold',
    ],

    'commission_rates' => [
        'title' => 'Commission by category',
        'intro' => 'A category rate applies to the category and to its sub-categories that have no rate of their own. Without one, the general rate applies.',
        'category' => 'Category',
        'rate' => 'Rate (%)',
        'add' => 'Save rate',
        'remove' => 'Remove',
        'empty' => 'No category rates yet: every category uses the general rate.',
        'saved' => 'Category commission rate saved.',
        'removed' => 'Category commission rate removed.',
    ],

    'ad_review' => [
        'title' => 'New ad awaiting review',
        'body' => ':title:hint',
        'flagged' => ' — auto-flagged: :flags',
        'action' => 'Review ad',
        'auto_check' => 'Automatic check',
        'auto_check_clean' => 'The check found no problems.',
        'auto_check_pending' => 'The automatic check is still running. Refresh in a moment.',
        'auto_check_flags' => [
            'banned_words' => 'Banned words',
            'phone' => 'Phone number in the text',
            'external_link' => 'External link',
            'duplicate_image' => 'An image matches another seller\'s ad',
        ],
        'duplicate_of' => 'Matching ads:',
        'pending_badge' => 'Ads waiting for review',
    ],

    'announcement' => [
        'title_field' => 'Title',
        'body_field' => 'Body',
        'target_field' => 'Audience',
        'target' => [
            'all_users' => 'All users',
            'active_users' => 'Active users only',
            'users_with_active_ads' => 'Users with active ads',
        ],
    ],

    'widgets' => [
        'users' => [
            'total' => 'Total users',
            'active_today' => 'Active today',
            'new_this_week' => 'New this week',
            'active_label' => ':count active accounts',
            'last_24h' => 'Signed in today',
            'since_monday' => 'Since Monday',
        ],
        'ads' => [
            'active_total' => 'Active ads',
            'pending_moderation' => 'Pending moderation',
            'published_today' => 'Published today',
            'live_now' => 'Visible to buyers',
            'awaiting_review' => 'Awaiting review',
            'since_midnight' => 'Since midnight',
        ],
        'reports' => [
            'pending' => 'Pending reports',
            'actioned_week' => 'Actioned this week',
            'dismissed_week' => 'Dismissed this week',
            'awaiting_review' => 'Awaiting moderator',
            'since_monday' => 'Since Monday',
        ],
        'revenue' => [
            'mtd' => 'Revenue MTD',
            'featured_active' => 'Featured ads active',
            'subscriptions' => 'Active subscriptions',
            'coming_sprint_12' => 'Available in Sprint 12',
        ],
        'chart' => [
            'heading' => 'Ads published — last 30 days',
            'ads_published' => 'Ads published',
        ],
        'recent_reports' => [
            'heading' => 'Pending reports',
            'id' => 'ID',
            'target' => 'Target',
            'category' => 'Category',
            'reporter' => 'Reporter',
            'created' => 'Reported',
        ],
    ],

    'tabs' => [
        'pending' => 'Pending',
        'reviewed' => 'Reviewed',
        'dismissed' => 'Dismissed',
        'actioned' => 'Actioned',
        'all' => 'All',
    ],

    'helpers' => [
        'lucide_icon' => 'Lucide icon name (e.g. tag, map-pin, smartphone)',
    ],

    'sections' => [
        'general' => 'General',
        'translations' => 'Translations',
        'content' => 'Content',
        'meta' => 'Meta',
        'taxonomy' => 'Taxonomy',
        'pricing' => 'Pricing',
        'moderation' => 'Moderation',
        'audit' => 'Audit',
        'audience' => 'Audience',
        'assignment' => 'Assignment',
        'verification' => 'Verification',
        'images' => 'Images',
        'seo' => 'SEO',
        'geo' => 'Geo coordinates',
        'reporter' => 'Reporter',
        'target' => 'Target',
        'roles' => 'Roles',
        'permissions' => 'Permissions',
    ],

    'impersonation' => [
        'reason' => 'Impersonation reason',
        'reason_hint' => 'The reason is kept in the audit log. The session ends after :minutes minutes.',
        'submit' => 'Browse as this user',
        'staff_refused' => 'Staff members cannot be impersonated.',
    ],

    'settings' => [
        'title' => 'Platform settings',
        'intro' => 'Changes apply immediately across the platform and are recorded in the activity log.',
        'save' => 'Save settings',
        'saved' => 'Settings saved.',
        'groups' => [
            'commission' => 'Commission',
            'ads' => 'Ads',
            'offers' => 'Offers',
            'orders' => 'Orders and disputes',
        ],
        'fields' => [
            'commission_rate' => [
                'label' => 'Commission rate',
                'help' => 'Percent of the item price QBazaar keeps on every sale, rounded half-up to 0.01 QAR. A category rate below overrides it. Only new orders use a changed rate.',
                'unit' => '%',
            ],
            'commission_debt_ceiling' => [
                'label' => 'Commission debt ceiling',
                'help' => "When a seller's unpaid commission reaches this amount, new sales are blocked until they settle.",
                'unit' => 'QAR',
            ],
            'settlement_deadline_days' => [
                'label' => 'Settlement deadline',
                'help' => 'How many days a seller has to settle commission debt.',
                'unit' => 'days',
            ],
            'ad_expiry_warning_days' => [
                'label' => 'Expiry warning lead time',
                'help' => 'How many days before an ad expires its seller is reminded to renew. Sent once per ad.',
                'unit' => 'days',
            ],
            'ad_max_images' => [
                'label' => 'Images per ad',
                'help' => 'The most photos a seller can attach to one ad.',
                'unit' => 'images',
            ],
            'ad_daily_publish_limit' => [
                'label' => 'Daily publish limit',
                'help' => 'How many ads a seller can submit for review in 24 hours.',
                'unit' => 'ads',
            ],
            'offer_counter_rounds_per_side' => [
                'label' => 'Counter-offer rounds',
                'help' => 'How many times each side (seller first, then buyer) may answer with a counter-offer in one negotiation. Zero turns counter-offers off.',
                'unit' => 'rounds',
            ],
            'order_dispute_window_hours' => [
                'label' => 'Report-a-problem window',
                'help' => 'How many hours after the seller confirms the handover of a cash order the buyer can report a problem and open a dispute.',
                'unit' => 'hours',
            ],
            'escrow_auto_release_days' => [
                'label' => 'Escrow auto-release',
                'help' => 'For orders paid by card or bank transfer: how many days after the handover the held amount goes to the seller on its own when the buyer neither confirms nor disputes. Cash orders are not affected.',
                'unit' => 'days',
            ],
        ],
    ],

    'locales' => [
        'ar' => 'العربية',
        'en' => 'English',
    ],

    'finance' => [
        'settlements' => [
            'approved' => 'Settlement approved and posted to the books.',
            'rejected' => 'Settlement rejected; the seller has been told.',
        ],
        'withdrawals' => [
            'paid' => 'Withdrawal recorded as transferred; the seller has been told.',
            'rejected' => 'Withdrawal rejected; the amount is back in the seller’s wallet.',
        ],
        'disputes' => [
            'resolved' => 'The ruling has been recorded.',
        ],
        'review_requested' => [
            'action' => 'Open the review queue',
            'settlement' => [
                'title' => 'Commission settlement waiting for review',
                'body' => 'A seller sent a bank transfer of :amount QAR to pay their commission. Match it against the bank statement, then approve or reject it.',
            ],
            'withdrawal' => [
                'title' => 'Withdrawal waiting to be transferred',
                'body' => 'A seller asked to withdraw :amount QAR to their bank account.',
            ],
            'dispute' => [
                'title' => 'New order dispute',
                'body' => 'A buyer reported a problem with an order of :amount QAR after the handover.',
            ],
        ],
    ],
];
