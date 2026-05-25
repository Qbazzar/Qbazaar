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
    'navigation' => [
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
        'title' => 'Title',
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
        'query_params' => 'Query',
    ],

    'actions' => [
        'view' => 'View',
        'edit' => 'Edit',
        'delete' => 'Delete',
        'create' => 'Create',
        'save' => 'Save',
        'cancel' => 'Cancel',
        'ban' => 'Ban',
        'unban' => 'Unban',
        'suspend' => 'Suspend',
        'reset_password' => 'Reset password',
        'view_as_user' => 'View public profile',
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
];
