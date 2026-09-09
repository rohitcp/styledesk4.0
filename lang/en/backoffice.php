<?php

declare(strict_types=1);

return [

    'title' => 'StyleDesk Backoffice',
    'subtitle' => 'Platform administration',

    'nav' => [
        'label' => 'Backoffice sections',
        'dashboard' => 'Dashboard',
        'clients' => 'Clients',
        'plans' => 'Plans',
        'billing' => 'Payment & Billing',
        'settings' => 'App Settings',
        'profile' => 'My Profile',
        'logout' => 'Log out',
    ],

    'roles' => [
        'super-owner' => 'Super Owner',
        'admin' => 'Admin',
        'billing-admin' => 'Billing Admin',
        'support-admin' => 'Support Admin',
        'read-only' => 'Read Only Admin',
    ],

    /*
    | Sign-in.
    |
    | Every refusal here says the same thing on purpose. A message that
    | distinguishes "no such administrator" from "wrong password" is a staff
    | list handed out one guess at a time.
    */
    'auth' => [
        'restricted' => 'Restricted to StyleDesk administrators.',

        'email_title' => 'Sign in',
        'email_intro' => 'Enter your address and we will send a one-time code.',
        'email_label' => 'Email address',
        'send_code' => 'Send code',

        'code_title' => 'Check your email',
        'code_intro' => 'If :email belongs to an administrator, a code is on its way. It is valid for a short time.',
        'code_label' => 'Verification code',
        'verify_code' => 'Verify code',
        'resend_code' => 'Resend code',
        'change_email' => 'Use a different address',
        'code_sent' => 'If that address belongs to an administrator, a code has been sent.',
        'code_wrong' => 'That code is not right, or it has expired. Ask for a new one.',
        'too_many_codes' => 'Too many codes have been asked for. Wait a few minutes and try again.',

        'login_title' => 'Enter your password',
        'login_intro' => 'Your address is verified. One more step.',
        'password_label' => 'Password',
        'remember' => 'Remember me',
        'forgot' => 'Forgot password?',
        'sign_in' => 'Log in',
        'refused' => 'Those details were not accepted.',
        'throttled' => 'Too many attempts. Try again in :seconds seconds.',

        'timed_out' => 'You were signed out after a period of inactivity.',
        'disabled' => 'This administrator account is no longer active.',

        'forgot_title' => 'Reset your password',
        'forgot_intro' => 'We will email a link if the address belongs to an administrator.',
        'send_reset' => 'Send reset link',
        'reset_sent' => 'If that address belongs to an administrator, a reset link has been sent.',
        'back_to_sign_in' => 'Back to sign in',

        'reset_title' => 'Choose a new password',
        'new_password' => 'New password',
        'confirm_password' => 'Confirm password',
        'reset_submit' => 'Save password',
        'reset_done' => 'Your password has been changed. Sign in with it.',
    ],

    'email' => [
        'code_subject' => 'Your StyleDesk Backoffice sign-in code',
        'code_headline' => 'Your sign-in code',
        'code_preheader' => 'A one-time code for the StyleDesk Backoffice.',
        'code_greeting' => 'Hello :name,',
        'code_intro' => 'Use this code to continue signing in to the Backoffice.',
        'code_expiry' => 'The code stops working after :minutes minutes.',
        'code_unexpected' => 'If you did not ask to sign in, somebody else has your address. Tell the team and change your password.',

        'reset_subject' => 'Reset your StyleDesk Backoffice password',
        'reset_headline' => 'Reset your password',
        'reset_preheader' => 'A link to choose a new Backoffice password.',
        'reset_greeting' => 'Hello :name,',
        'reset_intro' => 'Somebody asked to reset the password on your StyleDesk Backoffice account. Use the button below to choose a new one.',
        'reset_cta' => 'Choose a new password',
        'reset_expiry' => 'The link stops working after :minutes minutes, and can only be used once.',
        'reset_fallback' => 'If the button does not work, copy and paste this address into your browser:',
        'reset_unexpected' => 'If you did not ask for this, you can ignore this email — your password stays as it is. If it keeps happening, somebody has your address: tell the team.',
    ],

    'clients' => [
        'title' => 'Clients',
        'intro' => 'Every business subscribed to StyleDesk.',

        'stats' => [
            'total' => 'Businesses',
            'active' => 'Active',
            'trialing' => 'On trial',
            'new_this_month' => 'New this month',
            'services' => 'Services',
            'users' => 'Users',
            'bookings' => 'Bookings',
        ],

        'search_label' => 'Search',
        'search_placeholder' => 'Business, slug, email or owner',
        'status' => 'Status',
        'any' => 'Any',
        'apply' => 'Apply',
        'clear' => 'Clear',

        'col' => [
            'business' => 'Business',
            'status' => 'Status',
            'owner' => 'Owner',
            'plan' => 'Plan',
            'locations' => 'Locations',
            'staff' => 'Staff',
            'clients' => 'Clients',
            'joined' => 'Joined',
        ],

        'showing' => 'Showing :first–:last of :total',

        'breadcrumb' => 'Breadcrumb',
        'joined_on' => 'Joined :date',
        'contact' => 'Contact',
        'regional' => 'Regional',
        'business_email' => 'Business email',
        'business_phone' => 'Business phone',
        'website' => 'Website',
        'country' => 'Country',
        'currency' => 'Currency',
        'timezone' => 'Time zone',
        'language' => 'Default language',
        'identifier' => 'Tenant ID',
        'owner_name' => 'Name',
        'owner_email' => 'Email',
        'owner_phone' => 'Phone',
        'subscription' => 'Subscription',
        'trial_started' => 'Trial started',
        'trial_ends' => 'Trial ends',
        'location_name' => 'Location',
        'location_where' => 'Where',
        'primary' => 'Primary',
        'last_seen' => 'Last signed in',
        'never' => 'Never',
        'no_locations' => 'No locations yet.',

        'statuses' => [
            'active' => 'Active',
            'trial' => 'Trial',
            'past_due' => 'Past due',
            'disabled' => 'Disabled',
            'cancelled' => 'Cancelled',
        ],

        'reasons' => [
            'non_payment' => 'Non-payment / Overdue invoice',
            'payment_failed' => 'Payment failed repeatedly',
            'subscription_cancelled' => 'Subscription cancelled',
            'trial_expired' => 'Trial expired',
            'chargeback' => 'Chargeback / Payment dispute',
            'tos_violation' => 'Terms of Service violation',
            'fraud' => 'Fraud / Suspicious activity',
            'client_requested' => 'Client requested account closure',
            'business_closed' => 'Business permanently closed',
            'duplicate_account' => 'Duplicate account',
            'compliance' => 'Compliance issue',
            'administrative' => 'Administrative suspension',
            'other' => 'Other',
        ],

        'choose_reason' => 'Choose a reason',
        'note' => 'Note',
        'optional' => '(optional)',
        'note_placeholder' => 'Invoice #INV-2048 has remained unpaid for more than 45 days. Multiple payment reminders were sent.',
        'note_internal' => 'Internal only. The client never sees this.',
        'note_required' => 'A note is required when the reason is Other.',
        'enable_note_placeholder' => 'Why this account is being restored.',
        'cancel' => 'Cancel',
        'by' => 'By',
        'activity' => 'Backoffice activity',
        'no_activity' => 'Nothing recorded for this client yet.',
        'disable_confirm_title' => 'Disable this client?',
        'disable_confirm_body' => 'Disabling the client will prevent users belonging to :name from accessing StyleDesk. Nothing is deleted, and the account can be enabled again.',
        'enable_confirm_title' => 'Enable this client?',
        'enable_confirm_body' => 'Users belonging to :name will be able to sign in again.',
        'disable_action' => 'Disable client',
        'disable_reason' => 'Reason',
        'disabled_done' => ':name has been disabled. Nobody there can sign in.',
        'enable_action' => 'Enable client',
        'enabled_done' => ':name has been enabled. Everybody there can sign in again.',
        'disabled_banner' => 'This business is disabled — nobody there can sign in.',
        'disabled_detail' => 'Disabled by :who on :when.',
        'no_users' => 'No users yet.',

        'usage' => 'Usage',
        'account' => 'Account',
        'business_name' => 'Business name',
        'primary_contact' => 'Primary contact',
        'last_activity' => 'Last activity',

        'quick_action' => 'Quick Action',
        'quick_action_placeholder' => 'Select Quick Action',
        'quick_action_search' => 'Search actions',
        'quick_action_none' => 'No action matches that.',
        'copied_url' => 'Client URL copied.',
        'copied_id' => 'Client ID copied.',
        'copy_failed' => 'Nothing was copied — your browser refused access to the clipboard.',

        'action_groups' => [
            'go' => 'Go to',
            'copy' => 'Copy',
            'tell' => 'Send',
            'account' => 'Account',
        ],

        'actions' => [
            'open_app' => 'Open client application',
            'view_profile' => 'View client profile',
            'view_services' => 'View services',
            'view_team' => 'View team members',
            'view_email_logs' => 'View email logs',
            'copy_url' => 'Copy client URL',
            'copy_id' => 'Copy client ID',
            'send_email' => 'Send email',
            'resend_welcome' => 'Resend welcome email',
            'send_password_reset' => 'Send password reset',
            'activate' => 'Activate client',
            'deactivate' => 'Deactivate client',
        ],

        'reset_sent' => 'A password reset link has been emailed to :email.',
        'reset_failed' => 'No reset link was sent. One may have been sent very recently — wait a few minutes and try again.',
        'reset_no_owner' => 'This business has no owner, so there is nobody to send a reset link to.',

        'none' => '—',
        'no_owner' => 'No owner',
        'no_plan' => 'No plan',
        'trial_days' => ':days days left',

        'empty' => 'No business has signed up yet.',
        'no_matches' => 'No business matches those filters.',
    ],

    'tabs' => [
        'label' => 'Client sections',
        'overview' => 'Overview',
        'services' => 'Services',
        'email_log' => 'Email Log',
        'sms_log' => 'SMS Log',
        'team' => 'Team Members',
        'subscription' => 'Subscription',
        'coming_soon' => 'Coming soon',
    ],

    'table' => [
        'search' => 'Search',
        'per_page' => 'Rows',
        'no_matches' => 'Nothing matches those filters.',
    ],

    'services' => [
        'search_placeholder' => 'Service, description or category',
        'empty' => 'This business has not added any services yet.',
        'no_price' => 'Not priced',
        'all_locations' => 'All locations',

        'col' => [
            'name' => 'Service',
            'category' => 'Category',
            'duration' => 'Duration',
            'price' => 'Price',
            'location' => 'Location',
            'status' => 'Status',
            'created' => 'Created',
        ],

        'statuses' => [
            'active' => 'Active',
            'inactive' => 'Inactive',
        ],
    ],

    'emails' => [
        'search_placeholder' => 'Recipient, subject or type',
        'empty' => 'This business has not sent any email yet.',
        'manual' => 'Manual message',
        'automatic' => 'Automatic',
        'view_details' => 'View details',
        'hide_details' => 'Hide details',

        'col' => [
            'sent' => 'Date / time',
            'recipient' => 'Recipient',
            'type' => 'Type',
            'subject' => 'Subject',
            'status' => 'Status',
            'sent_by' => 'Sent by',
            'provider' => 'Provider',
            'action' => 'Action',
        ],

        'statuses' => [
            'queued' => 'Queued',
            'sent' => 'Sent',
            'delivered' => 'Delivered',
            'failed' => 'Failed',
        ],

        'detail' => [
            'sender' => 'From',
            'queued' => 'Queued at',
            'sent' => 'Sent at',
            'booking' => 'Booking',
            'failure' => 'Why it failed',
            'message' => 'Message',
        ],
    ],

    'team' => [
        'search_placeholder' => 'Name, email or job title',
        'empty' => 'This business has not added any team members yet.',
        'no_account' => 'No account yet',

        'col' => [
            'name' => 'Name',
            'email' => 'Email',
            'role' => 'Role',
            'location' => 'Location',
            'status' => 'Status',
            'last_login' => 'Last login',
            'created' => 'Created',
        ],
    ],

    'sms' => [
        'title' => 'SMS Log',
        'intro' => 'SMS communication history, delivery status and message activity for this client.',
        'items' => [
            'history' => 'Every message sent, with its recipient',
            'delivery' => 'Delivery and failure status from the provider',
            'activity' => 'Which part of the product sent each message',
            'spend' => 'Message volume and spend against the plan',
        ],
    ],

    'subscription' => [
        'title' => 'Subscription',
        'intro' => 'Plan, billing cycle and payment details for this client. The plan and trial dates already known are on the Overview tab.',
        'items' => [
            'plan' => 'Current plan',
            'cycle' => 'Billing cycle',
            'status' => 'Subscription status',
            'renewal' => 'Renewal date',
            'limits' => 'Usage limits',
            'method' => 'Payment method',
            'history' => 'Billing history',
            'change' => 'Upgrade, downgrade or cancel',
        ],
    ],

    'dashboard' => [
        'greeting' => 'Welcome, :name',
        'intro' => 'Platform administration for StyleDesk.',
        'recent_activity' => 'Recent administrator activity',
        'no_activity' => 'Nothing recorded yet.',
    ],

    'profile' => [
        'title' => 'My Profile',
        'details' => 'Your details',
        'name' => 'Name',
        'role' => 'Role',
        'password' => 'Password',
        'password_hint' => 'Your current password is asked for because this screen is the one an unattended desk gets used on.',
        'current_password' => 'Current password',
        'change_password' => 'Change password',
        'saved' => 'Your details have been saved.',
        'password_saved' => 'Your password has been changed.',
        'wrong_password' => 'That is not your current password.',
    ],

    'soon' => [
        'title' => 'Coming in the next phase',
        'clients' => 'Every business subscribed to StyleDesk, with its plan, billing state and usage.',
        'plans' => 'The subscription plans StyleDesk sells, their prices, limits and features.',
        'billing' => 'Subscription invoices, transactions, failed payments and refunds.',
        'settings' => 'Platform settings, administrators and the audit log.',
    ],

    /*
    | The audit log.
    |
    | Keys hold no dots: Laravel walks a dotted key one segment at a time and
    | can never reach a key that itself contains a dot, so an action like
    | `auth.login` is looked up as `auth_login`.
    */
    'audit' => [
        'unknown_actor' => 'Unknown',
        'actions' => [
            'auth_login' => 'Administrator signed in',
            'auth_logout' => 'Administrator signed out',
            'auth_login_failed' => 'Failed sign-in',
            'auth_code_sent' => 'Verification code sent',
            'auth_code_requested_unknown' => 'Verification code asked for by an unknown address',
            'auth_code_verified' => 'Verification code accepted',
            'auth_code_failed' => 'Verification code refused',
            'auth_password_reset_requested' => 'Password reset requested',
            'auth_password_reset' => 'Password reset',
            'admin_profile_updated' => 'Administrator profile updated',
            'admin_password_changed' => 'Administrator password changed',
            'client_disabled' => 'Client disabled',
            'client_enabled' => 'Client enabled',
            'client_password_reset_sent' => 'Password reset sent to the client owner',
        ],
    ],
];
