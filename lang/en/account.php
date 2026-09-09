<?php

declare(strict_types=1);

/* My Account — the signed-in person's own settings. Deliberately separate
   from the settings language file, which belongs to App Settings and
   configures the business rather than the person. */

return [
    'title' => 'My account',
    'intro' => 'Your own profile, preferences and security. Nothing here changes anything for the rest of the business.',

    'sections' => [
        'profile' => 'My profile',
        'preferences' => 'My preferences',
        'password' => 'Change password',
        'notifications' => 'Notifications',
    ],

    'save' => 'Save changes',
    'cancel' => 'Cancel',
    'reset' => 'Reset to default',
    'reset_confirm' => 'Reset these to the business defaults?',

    'profile' => [
        'title' => 'My profile',
        'intro' => 'How you appear across StyleDesk, and how we reach you.',

        'photo_card' => 'Profile photo',
        'photo_hint' => 'JPG, PNG or WebP, up to 5 MB. Your initials are used until you add one.',
        'photo_upload' => 'Upload photo',
        'photo_replace' => 'Replace photo',
        'photo_remove' => 'Remove',
        'photo_saved' => 'Profile photo updated.',
        'photo_removed' => 'Profile photo removed.',
        'photo_too_large' => 'That image is larger than 5 MB.',
        'photo_wrong_type' => 'Choose a JPG, PNG or WebP image.',
        'photo_pending' => 'Preview — save to keep it.',

        'personal_card' => 'Personal information',
        'first_name' => 'First name',
        'last_name' => 'Last name',
        'display_name' => 'Display name',
        'display_name_hint' => 'Leave blank to use your first and last name.',
        'display_name_placeholder' => 'How your name appears to clients',
        'job_title' => 'Job title',
        'job_title_placeholder' => 'Senior stylist',
        'phone' => 'Mobile number',
        'phone_hint' => 'Used for appointment alerts once SMS is switched on.',
        'saved' => 'Profile updated successfully.',

        'email_card' => 'Email address',
        'email' => 'Email address',
        'email_locked_hint' => 'The address you sign in with. Ask an administrator if it needs to change.',
        'email_hint' => 'You sign in with this address. Changing it needs your password and a confirmation from the new address.',
        'email_current_password' => 'Current password',
        'email_change_cta' => 'Change',
        'email_new' => 'New email address',
        'email_change' => 'Change email address',
        'email_subject' => 'Confirm your new StyleDesk email address',
        'email_pending_title' => 'Confirm your new email address',
        'email_pending_body' => 'We sent a link to :email. Your current address keeps working until you confirm the new one.',
        'email_pending' => 'Check :email for a link to confirm the change.',
        'email_resend' => 'Resend link',
        'email_resent' => 'We sent the confirmation link again.',
        'email_cancel' => 'Cancel change',
        'email_cancelled' => 'Email change cancelled.',
        'email_changed' => 'Email address updated successfully.',
        'email_link_dead' => 'That confirmation link has expired or has already been used. Ask for a new one from My Profile.',
        'email_taken' => 'That email address is already in use.',
        'email_unchanged' => 'That is already your email address.',

        'staff_card' => 'Staff information',
        'staff_intro' => 'Set by an administrator in Staff Management and shown here for reference.',
        'role' => 'Role',
        'no_role' => 'No role assigned',
        'locations' => 'Assigned location',
        'all_locations' => 'All locations',
        'no_location' => 'No location assigned',
        'status' => 'Account status',
        'status_active' => 'Active',
        'status_inactive' => 'Inactive',
        'status_archived' => 'Archived',
        'member_since' => 'Member since',
    ],

    'preferences' => [
        'title' => 'My preferences',
        'intro' => 'How the app reads for you. Each of these follows the business default until you change it here.',

        'language_card' => 'Language',
        'language' => 'Primary language',
        'language_hint' => 'Changes the interface for you only. What your business has typed — service names, client notes — is never translated.',
        'language_default' => 'Use the business language',

        'format_card' => 'Dates and times',
        'date_format' => 'Date format',
        'time_format' => 'Time format',
        'timezone' => 'Time zone',
        'timezone_hint' => 'Leave unset to follow the business time zone.',
        'first_day_of_week' => 'Start of week',
        'use_business' => 'Use the business setting',

        'calendar_card' => 'Calendar',
        'calendar_intro' => 'How calendar screens open for you.',
        'calendar_view' => 'Default view',
        'calendar_views' => [
            'day' => 'Day',
            'week' => 'Week',
            'month' => 'Month',
        ],
        'show_weekends' => 'Show weekends',
        'show_cancelled' => 'Show cancelled appointments',
        'show_resource_color' => 'Show resource colour',
        'show_staff_color' => 'Show staff colour',

        'save' => 'Save preferences',
        'saved' => 'Preferences updated successfully.',
        'reset_action' => 'Reset to default',
        'reset_hint' => 'Clears your personal choices so every setting follows the business again.',
        'reset' => 'Preferences reset to the business defaults.',
    ],

    'password' => [
        'title' => 'Change password',
        'intro' => 'Choose something you do not use anywhere else.',
        'card' => 'Your password',
        'hidden' => 'Your password is hidden',
        'current' => 'Current password',
        'new' => 'New password',
        'confirm' => 'Confirm new password',
        'requirements' => 'Your password needs',
        'save' => 'Change password',
        'saved' => 'Password changed successfully.',
        'current_wrong' => 'That is not your current password.',
        'same_as_current' => 'Choose a password different from your current one.',
        'mismatch' => 'Passwords do not match.',
        'logout_others' => 'Sign out from all other devices',
        'logout_others_hint' => 'This browser stays signed in. Anywhere else you are signed in will need the new password.',
    ],

    'notifications' => [
        'title' => 'Notifications',
        'intro' => 'Which messages reach you, and how. Security alerts are always sent.',
        'saved' => 'Notification preferences updated successfully.',
        'reset' => 'Notification preferences reset to their defaults.',
        'save' => 'Save notification preferences',
        'enable_all' => 'Enable all',
        'disable_all' => 'Disable optional notifications',
        'bulk_hint' => 'Security alerts stay on either way.',
        'always_on' => 'Always on',
        'coming_soon' => 'Coming soon',
        'not_supported' => 'Not available for this notification',

        'channels' => [
            'in_app' => 'In-app',
            'email' => 'Email',
            'sms' => 'SMS',
            'push' => 'Push',
        ],

        'groups' => [
            'appointments' => [
                'label' => 'Appointments',
                'description' => 'What happens to bookings on your calendar.',
            ],
            'clients' => [
                'label' => 'Clients',
                'description' => 'Changes to the people you look after.',
            ],
            'team' => [
                'label' => 'Team',
                'description' => 'Your schedule, your role and what colleagues send you.',
            ],
            'resources' => [
                'label' => 'Resources',
                'description' => 'The rooms, chairs and equipment your work depends on.',
            ],
            'system' => [
                'label' => 'Security and account',
                'description' => 'How you find out that something happened to your account. These are always sent.',
            ],
        ],

        'types' => [
            'booking.created' => 'New appointment created',
            'booking.assigned' => 'Appointment assigned to me',
            'booking.updated' => 'Appointment updated',
            'booking.rescheduled' => 'Appointment rescheduled',
            'booking.cancelled' => 'Appointment cancelled',
            'booking.completed' => 'Appointment completed',
            'booking.no_show' => 'Appointment marked no-show',
            'booking.reminder' => 'Appointment reminder',

            'client.assigned' => 'New client assigned to me',
            'client.updated' => 'Client profile updated',
            'client.note_added' => 'Client note added',
            'client.file_uploaded' => 'Client file uploaded',
            'client.mentioned' => 'A client note mentions me',

            'team.invitation' => 'Staff invitation',
            'team.location_assigned' => 'Staff member assigned to a location',
            'team.hours_changed' => 'Working hours changed',
            'team.schedule_changed' => 'Schedule changed',
            'team.mentioned' => 'Someone mentions me',
            'team.role_changed' => 'Role or permissions changed',

            'resource.assigned' => 'Resource assigned to me',
            'resource.changed' => 'Resource changed',
            'resource.unavailable' => 'Resource becomes unavailable',
            'resource.conflict' => 'Resource scheduling conflict',

            'security.alert' => 'Security alerts',
            'security.password_changed' => 'Password changed',
            'security.email_changed' => 'Email address changed',
            'security.new_login' => 'New sign-in detected',
            'security.account_alert' => 'Account alerts',
        ],
        /*
        | One line each, saying WHEN the message arrives rather than repeating
        | the name. Somebody deciding whether to be interrupted needs the
        | trigger, not a synonym for the title.
        */
        'types_hint' => [
            'booking.created' => 'Someone books an appointment — online, at the desk, or by phone.',
            'booking.assigned' => 'An appointment is put in your name, or moved to you from a colleague.',
            'booking.updated' => 'The services, price or notes on one of your appointments change.',
            'booking.rescheduled' => 'One of your appointments moves to a different day or time.',
            'booking.cancelled' => 'A client or a colleague cancels an appointment of yours.',
            'booking.completed' => 'An appointment of yours is checked out and marked finished.',
            'booking.no_show' => 'A client is recorded as not having turned up.',
            'booking.reminder' => 'Shortly before an appointment of yours is due to start.',

            'client.assigned' => 'A client is made yours to look after.',
            'client.updated' => 'Someone edits the details of a client assigned to you.',
            'client.note_added' => 'A note is added to one of your clients.',
            'client.file_uploaded' => 'A photo or document is added to one of your clients.',
            'client.mentioned' => 'A colleague writes your name in a note on a client.',

            'team.invitation' => 'Someone is invited to join the business, or accepts an invitation.',
            'team.location_assigned' => 'You are moved to a different branch, or a colleague is.',
            'team.hours_changed' => 'Your regular working hours are edited.',
            'team.schedule_changed' => 'A rota covering your shifts is published or changed.',
            'team.mentioned' => 'A colleague writes your name anywhere in StyleDesk.',
            'team.role_changed' => 'Your role changes, or what you are allowed to do does.',

            'resource.assigned' => 'A room, chair or piece of equipment is put in your name.',
            'resource.changed' => 'A resource you use is renamed, moved or has its hours edited.',
            'resource.unavailable' => 'A resource you are booked into is closed for maintenance or repair.',
            'resource.conflict' => 'Two appointments end up needing the same resource at the same time.',

            'security.alert' => 'Something happens to your account that we think you should know about.',
            'security.password_changed' => 'Your password is changed, by you or by anyone else.',
            'security.email_changed' => 'The email address you sign in with is changed.',
            'security.new_login' => 'Your account is signed into from a device or place we have not seen before.',
            'security.account_alert' => 'Your account is locked, suspended or otherwise restricted.',
        ],
    ],
];
