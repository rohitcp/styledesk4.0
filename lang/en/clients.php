<?php

declare(strict_types=1);

/*
| App Settings → Clients.
|
| The catalogue's own labels — field names, statuses, panels — live in
| config/clients.php next to the values they describe, and are surfaced through
| this file only where a language needs its own wording.
*/

return [
    'title' => 'Clients',
    'intro' => 'What a client record holds, how it behaves, and what your team sees when they open one.',
    'saved' => 'Client settings updated successfully.',
    'scope_note' => 'These are global settings. Client profiles, history and day-to-day management belong to the Clients module.',
    'pending_note' => 'Settings that describe booking screens and client profiles are stored now and take effect as those screens arrive.',

    'sections' => 'Sections on this page',
    'behavioral' => [
        'title' => 'Behavioural tags',
        'intro' => 'Tags StyleDesk works out for itself from booking, attendance, spending and engagement — as opposed to the ones your team puts on by hand.',
        'pending' => 'These are counted from bookings, attendance and spending. Nothing is applied to a client until those parts of StyleDesk arrive; what you choose here is which ones will be.',
        'note' => 'Behavioural tags are defined by StyleDesk and cannot be renamed or deleted: their names are what reports and future automation refer to. Switching one off stops it being applied and leaves it on the clients who already carry it.',
        'updated' => 'Behavioural tags updated',
        'search' => 'Search behavioural tags…',
        'no_matches' => 'No tag matches that.',
        'none_yet' => 'None yet. These are worked out from bookings, attendance and spending as those arrive.',
        'manage' => 'Manage behavioural tags',
        'other' => 'Other',
        'activated' => '“:label” will be applied',
        'deactivated' => '“:label” will no longer be applied',
    ],

    'expand_all' => 'Expand all',
    'collapse_all' => 'Collapse all',
    'unsaved_warning' => 'You have unsaved changes in this section. Discard them?',
    'preview' => [
        'fields' => 'Fields on a client record',
        'required' => '(required)',
        'optional' => '(optional)',
        'more' => '+:count more',
    ],

    'summary' => [
        'fields' => '{1} 1 field on a client record|[2,*] :count fields on a client record',
        'preferences' => '{0} No preferences|{1} 1 preference|[2,*] :count preferences',
        'tags' => '{0} no tags|{1} 1 tag|[2,*] :count tags',
        'behavioral' => '{0} None of the :total behavioural tags applied|[1,*] :count of :total behavioural tags applied',
        'notes_on' => '{0} Notes on, one per client|[1,*] Notes on, several per client',
        'notes_off' => 'Notes off',
        'panels' => '{0} Nothing shown while booking|{1} 1 panel while booking|[2,*] :count panels while booking',
        'duplicates_off' => 'No duplicate warning',
        'status' => 'Inactive clients bookable: :inactive · Archived in search: :archived',
        'consent_on' => 'Consent recorded when given',
        'consent_off' => 'Consent not recorded',
    ],

    'cards' => [
        'records' => 'Client records',
        'records_hint' => 'Profile fields, what is required, how names are written, and the Client ID.',
        'lists' => 'Preferences & tags',
        'lists_hint' => 'The structured preferences and labels your team can put on a client.',
        'notes' => 'Notes',
        'notes_hint' => 'Client notes, who may write them and where they appear.',
        'booking' => 'Booking behaviour',
        'booking_hint' => 'What the booking screen shows about the client it has selected.',
        'duplicates' => 'Duplicate detection',
        'duplicates_hint' => 'How a possible duplicate is spotted, and what happens when one is.',
        'communication' => 'Communication',
        'communication_hint' => 'The ways a client can be contacted, and what they can consent to.',
        'status' => 'Status & archiving',
        'status_hint' => 'Active, inactive and archived clients, and where each one appears.',
        'privacy' => 'Privacy & consent',
        'privacy_hint' => 'What is recorded when a client gives or withdraws marketing consent.',
    ],

    'defaults' => [
        'title' => 'Defaults for a new client',
        'status' => 'Status',
        'location' => 'Preferred location',
        'staff' => 'Preferred staff member',
        'communication' => 'Communication method',
        'marketing' => 'Marketing consent',
        'none' => 'None',
    ],

    'fields' => [
        'title' => 'Profile fields',
        'hint' => 'Which fields a client record carries, and which of them must be filled in. Drag to reorder.',
        'field' => 'Field',
        'enabled' => 'On',
        'required' => 'Required',
        'locked' => 'Always on',
        'locked_hint' => 'A client record with no name is not a record anyone can use.',
        'required_disabled' => 'A field that is switched off cannot be required.',
        'contact_advice' => 'A mobile number or an email address is what makes a duplicate findable. Requiring at least one is worth considering.',
    ],

    'name_format' => 'How client names are written',
    'name_format_hint' => 'Used everywhere a client is named, so one client cannot read as two people on two screens.',
    'name_preview' => 'For example',

    'client_id' => 'Client ID',
    'client_id_hint' => 'Given automatically, unique to your business, and searchable. It cannot be edited.',
    'client_id_example' => 'For example :example',

    'preferences' => [
        'title' => 'Client preferences',
        'enabled' => 'Enable client preferences',
        'multiple' => 'Allow more than one preference per client',
        'add' => 'Add preference',
        'placeholder' => 'Sensitive scalp',
        'empty' => 'No preferences yet.',
        'deactivate_note' => 'Deactivating keeps it on the clients who already have it; it just stops being offered.',
    ],

    'tags' => [
        'name' => 'Tag name',
        'deleted' => 'Tag deleted',
        'delete_confirm' => 'Delete the tag “:label”? No client is using it.',
        'in_use' => '“:label” is on :count clients, so it cannot be deleted. Deactivate it instead — the clients keep it and nobody can choose it again.',
        'title' => 'Client tags',
        'enabled' => 'Enable client tags',
        'add' => 'Add tag',
        'placeholder' => 'VIP',
        'colour' => 'Colour',
        'empty' => 'No tags yet.',
        'multiple_note' => 'A client can carry several tags.',
    ],

    'notes' => [
        'enabled' => 'Enable client notes',
        'multiple' => 'Allow more than one note per client',
        'in_booking' => 'Show notes while booking',
        'important_on_profile' => 'Show important notes at the top of the profile',
        'allow_important' => 'Let staff mark a note as important',
        'staff_can_edit' => 'Let staff edit their own notes',
        'admin_can_delete' => 'Let the owner and admins delete notes',
    ],

    'duplicates' => [
        'rules' => 'Treat a client as a possible duplicate when they share',
        'warning' => 'Warn before creating a possible duplicate',
        'show_matches' => 'Show the matching clients, so the existing one can be opened',
        'no_merge' => 'Records are never merged automatically. Two people can share a phone, and a wrongly merged history is not something a receptionist can unpick.',
    ],

    'search' => 'Find a client by',
    'creation' => 'Clients can be created from',
    'creation_unavailable' => 'Not available yet',
    'booking_panels' => 'Show on the booking screen',
    'history_panels' => 'Show on the client profile',

    'status' => [
        'allow_booking_inactive' => 'Allow inactive clients to be booked',
        'archived_in_search' => 'Include archived clients in booking search',
        'explainer' => 'Active clients appear normally. Inactive clients appear with a badge. Archived clients stay out of booking search but keep their appointments, transactions and history.',
    ],

    'communication' => [
        'methods' => 'Ways a client can be contacted',
        'marketing' => 'Marketing a client can consent to',
        'stored_separately' => 'Each is stored on its own, so a client who agrees to reminders has not agreed to marketing.',
    ],

    'consent' => [
        'record' => 'Record marketing consent',
        'record_date' => 'Record when it was given',
        'record_captured_by' => 'Record who captured it',
        'client_can_opt_out' => 'Let clients opt out',
        'show_on_profile' => 'Show consent status on the client profile',
        'later' => 'Digital consent forms, treatment and photo consent, privacy acknowledgements and electronic signatures arrive in a later release.',
    ],

    'deactivate' => 'Deactivate',
    'activate' => 'Activate',

    'preference_added' => 'Preference added.',
    'preference_activated' => 'Preference activated.',
    'preference_deactivated' => 'Preference deactivated. Clients who already have it keep it.',
    'tag_added' => 'Tag added.',
    'tag_saved' => 'Tag updated.',
    'tag_activated' => 'Tag activated.',
    'tag_deactivated' => 'Tag deactivated. Clients who already have it keep it.',
    'order_saved' => 'Order saved.',

    'validation' => [
        'status_required' => 'Choose a default status.',
        'name_format_required' => 'Choose how client names are written.',
        'location_invalid' => 'Choose one of your own locations.',
        'staff_invalid' => 'Choose one of your own staff members.',
        'preference_exists' => 'You already have a preference with that name.',
        'tag_exists' => 'You already have a tag with that name.',
    ],

    /*
     * The Clients module's own landing page — not the settings screen.
     *
     * Kept in this file because both talk about the same thing, and a reader
     * of one is the reader of the other.
     */
    /*
     * The Clients module: the listing, the empty state, the form and the
     * profile. Its settings are the keys above.
     */
    'module' => [
        'title' => 'Clients',
        'intro' => 'Manage client profiles, preferences, contact details, notes, and booking history.',

        'add' => 'Add client',
        'add_first' => 'Add your first client',
        'add_title' => 'Add client',
        'edit_title' => 'Edit client',
        'view' => 'View client',
        'create_booking' => 'Create booking',
        'archive' => 'Archive client',
        'restore' => 'Restore client',

        'choose_birth_date' => 'Choose a date of birth',
        'created' => 'Client added successfully',
        'saved' => 'Client updated successfully.',
        'archived' => ':name has been archived. Their appointments and history are unchanged.',
        'restored' => ':name is active again.',
        'correct_fields' => 'Please correct the highlighted fields and try again.',

        /*
         * The empty state, per §Empty State — deliberately not an empty
         * table. Search, filters, pagination and "0 results" are all absent
         * until a business has at least one client: a search box over nothing
         * is a control that can only ever fail.
         */
        'empty_title' => 'Start building your client list',
        'empty_body' => 'Add your clients to keep their contact information, preferences, booking history, notes, and preferred staff in one place.',
        'video_title' => 'How clients work in StyleDesk',
        'video_body' => 'What the Clients module does, how to add someone, and how their preferences, preferred staff, notes and booking history are used the next time they book.',
        'video_pending' => 'The explainer video is on its way.',

        'search_placeholder' => 'Search clients',
        'filters' => [
            'location_short' => 'Location',
            'staff_short' => 'Team',
            'tag_short' => 'Tag',
            'active' => 'Active filters',
            'status' => 'Status',
            'all_statuses' => 'All statuses',
            'location' => 'Preferred location',
            'all_locations' => 'All locations',
            'staff' => 'Preferred staff',
            'all_staff' => 'All staff',
            'tag' => 'Tag',
            'all_tags' => 'All tags',
        ],

        'columns' => [
            'client' => 'Client',
            'mobile' => 'Mobile',
            'email' => 'Email',
            'staff' => 'Preferred staff',
            'location' => 'Preferred location',
            'last_visit' => 'Last visit',
            'next_booking' => 'Next booking',
            'status' => 'Status',
            'actions' => 'Actions',
        ],

        'never_visited' => 'No visits yet',
        'nothing_booked' => 'Nothing booked',
        'all_archived' => 'Every client is archived.',
        'all_archived_hint' => 'Archived clients stay out of this list and out of booking search, and keep everything already on their record.',
        'view_archived' => 'View archived clients',
        'stats' => [
            'total' => 'Total clients',
            'new_this_month' => 'New this month',
            'upcoming' => 'Upcoming bookings',
            'inactive' => 'Inactive clients',
            'total_note' => 'All client records',
            'inactive_note' => 'Not currently booking',
            'vs_last_month' => 'vs last month',
            'awaiting_bookings' => 'Once bookings arrive',
        ],
        'results' => [
            'zero' => 'No clients found',
            'one' => '1 client found',
            'many' => ':count clients found',
            'empty' => 'No clients match your search or filters.',
            'clear' => 'Clear filters',
        ],
        'showing' => 'Showing :from–:to of :total clients',
        'no_matches' => 'No clients match your search.',
        'no_matches_hint' => 'Try a different word, or clear the filters.',
        'actions_for' => 'Actions for :name',

        'archive_confirm' => 'Archive :name? They stay out of booking search and keep every appointment, transaction and note already on their record.',
        'restore_confirm' => 'Make :name active again? They will appear in booking search.',

        /*
         * Possible duplicates, per §7. A warning that names who it found —
         * "this might be a duplicate" with nothing to look at leaves the
         * receptionist no way to decide.
         */
        'duplicates_title' => 'This might be someone you already have',
        'duplicates_body' => 'These clients share a detail with the one you are adding. Open one to check, or confirm they are a different person.',
        'duplicates_confirm' => 'Add anyway — this is a different person',

        'cards' => [
            'about' => 'About',
            'contact' => 'Contact',
            'booking' => 'Booking',
            'preferences' => 'Preferences & tags',
            'communication' => 'Communication & consent',
            'notes' => 'Notes',
        ],

        'consent_given' => 'Given :when',
        'consent_by' => 'Recorded by :name',
        'no_consent' => 'No marketing consent recorded',
        'no_preferences' => 'None recorded',

        /**
         * Phone numbers and email addresses on a client record.
         *
         * "Primary" names what a row is rather than what clicking it does,
         * because the control beside it is a radio: the reader is choosing
         * among the numbers, not performing an action on one.
         */
        'contacts' => [
            'primary' => 'Primary',
            'secondary' => 'Secondary',
            'priority' => 'Priority',
            'add_phone' => 'Add another number',
            'add_email' => 'Add another email',
            'remove' => 'Delete',
            'remove_phone_title' => 'Remove this number?',
            'remove_phone_body' => ':contact will be removed from this client when you save.',
            'remove_email_title' => 'Remove this address?',
            'remove_email_body' => ':contact will be removed from this client when you save.',
            'phone_number' => 'Phone number',
            'email_address' => 'Email address',
            'phone_type' => 'Phone type',
            'email_type' => 'Email type',
            'country_code' => 'Country code',
            'other_numbers' => 'Other numbers',
            'other_emails' => 'Other addresses',
        ],

        /**
         * The client workspace.
         *
         * Empty states say what is true today rather than "no data": a client
         * with no appointments has none because bookings have not shipped, and
         * a page that says so is one nobody has to investigate.
         */
        'workspace' => [
            'back' => 'Back to clients',
            'dob' => 'DOB: :date',
            'client_since' => 'Client since :date',
            'summary' => [
                'last_visit' => 'Last visit',
                'next_booking' => 'Next booking',
                'total_visits' => 'Total visits',
                'lifetime_spend' => 'Lifetime spend',
                'awaiting_bookings' => 'Counts appear once bookings arrive.',
            ],
            'tabs' => [
                'activity' => 'Activity',
                'bookings' => 'Bookings',
                'notes' => 'Notes',
                'files' => 'Files',
            ],
            'quick' => [
                'create_booking' => 'Create booking',
                'add_note' => 'Add note',
                'send_message' => 'Send message',
            ],
            'identity' => [
                'preferred_name' => 'Goes by :name',
                'edit_client' => 'Edit client',
                'more_actions' => 'More actions',
                'mark_inactive' => 'Mark inactive',
                'mark_active' => 'Mark active',
            ],
            'contact' => [
                'title' => 'Contact',
                'phone' => 'Phone',
                'email' => 'Email',
                'address' => 'Address',
                'view_all_numbers' => 'View all :count numbers',
                'view_all_emails' => 'View all :count addresses',
                'copy' => 'Copy',
                'copied' => 'Copied',
                'none_recorded' => 'None recorded',
                'hidden' => 'You do not have access to this client\'s contact details.',
                'email_short' => 'Email',
                'sms_short' => 'SMS',
                'no_email' => 'No email address on file',
                'no_phone' => 'No phone number on file',
                'send_email' => 'Send email',
                'send_sms' => 'Send SMS',
                'call' => 'Call',
            ],
            'preferences' => [
                'contact_title' => 'Contact preferences',
                'contactable' => 'Contactable by',
                'marketing' => 'Marketing',
                'opted_in' => 'Opted in',
                'opted_out' => 'Opted out',
                'title' => 'Preferences',
                'view_all' => 'View all :count preferences',
                'none' => 'No preferences recorded yet.',
            ],
            'preferred' => [
                'title' => 'Preferred',
                'staff' => 'Staff',
                'location' => 'Location',
                'none' => 'No preference',
            ],
            'important' => [
                'title' => 'Private note',
                'none' => 'Nothing flagged as important.',
            ],
            'profile_note' => 'Profile note',
            'tags' => [
                'title' => 'Client tags',
                'intro' => 'Organise clients using tags for booking, service, marketing and relationship management.',
                'none' => 'No client tags assigned',
                'manage' => 'Manage client tags',
                'search' => 'Search client tags…',
                'no_matches' => 'No tag matches that.',
                'updated' => 'Client tags updated successfully.',
            ],
            'activity' => [
                'created' => 'Client added',
                'created_meta' => 'Reference :ref',
                'consent' => 'Marketing consent recorded',
                'note' => 'Note added',
                'important_note' => 'Important note added',
                'archived' => 'Client archived',
                'title' => 'Activity',
                'search' => 'Search client activity…',
                'filters' => [
                    'all' => 'All',
                    'bookings' => 'Bookings',
                    'notes' => 'Notes',
                    'messages' => 'Messages',
                    'payments' => 'Payments',
                    'changes' => 'Changes',
                ],
                'none' => 'Nothing to show yet.',
                'no_matches' => 'No activity matches that.',
                'pending_modules' => 'Bookings, messages and payments join this timeline as those parts of StyleDesk arrive.',
            ],
            'bookings' => [
                'upcoming' => 'Upcoming',
                'previous' => 'Previous visits',
                'none_upcoming' => 'No upcoming appointments.',
                'none_previous' => 'No visits yet.',
                'coming' => 'Appointment history appears here once bookings arrive.',
                'hidden' => 'You do not have access to this client\'s appointment history.',
                'next_appointment' => 'Next appointment',
                'last_booking' => 'Last booking',
                'view_booking' => 'View booking',
                'reschedule' => 'Reschedule',
                'book_same_again' => 'Book the same again',
            ],
            'notes' => [
                'title' => 'Notes',
                'add' => 'Add note',
                'placeholder' => 'What should the next person know?',
                'important' => 'Mark as important',
                'important_badge' => 'Important',
                'save' => 'Save note',
                'cancel' => 'Cancel',
                'edit' => 'Edit',
                'delete' => 'Delete',
                'delete_confirm' => 'Delete this note? It is removed from the client\'s record for everyone.',
                'delete_tip' => 'Delete note',
                'delete_title' => 'Delete note?',
                'none' => 'No notes on this client yet.',
                'hidden' => 'You do not have access to this client\'s notes.',
                'added' => 'Note added',
                'updated' => 'Note updated',
                'deleted' => 'Note deleted',
                'body_required' => 'Write the note before saving it.',
                'unknown_author' => 'A former team member',
                'yours' => 'You',
                'important_hint' => 'Shown on the profile itself, so it is read before the appointment rather than during it.',
                'private' => 'Mark as private',
                'private_hint' => 'Only you, the owner, admins and the people you name below can read it.',
                'private_badge' => 'Private note',
                'access' => 'Access',
                'access_for' => 'Access for',
                'access_hint' => 'Owners and admins can always read private notes. Anyone else needs naming here.',
                'access_search' => 'Search team members…',
                'access_no_matches' => 'Nobody matches that.',
                'access_placeholder' => 'Search and select team members…',
                'manage_access' => 'Manage access',
                'column_user' => 'Team member',
                'column_role' => 'Role',
                'column_access' => 'Access',
                'access_admins' => 'Owners and admins always have access to private notes, so they do not need choosing here.',
                'picker_title' => 'Select people for this private note',
                'assign' => 'Assign',
                'selected' => '{0} Nobody selected|{1} 1 selected|[2,*] :count selected',
                'access_summary' => '{1} 1 person can read this|[2,*] :count people can read this',
                'remove_access_title' => 'Remove private access settings?',
                'remove_access' => 'Turning privacy off clears the people you chose.',
                'expand' => 'Expand note',
                'restore' => 'Restore note',
                'access_clear' => 'Clear all',
                'access_none' => 'Nobody selected yet. Owners and admins can still read it.',
                'toolbar' => 'Formatting',
                'bold' => 'Bold',
                'italic' => 'Italic',
                'underline' => 'Underline',
                'heading' => 'Heading',
                'bullet_list' => 'Bulleted list',
                'ordered_list' => 'Numbered list',
                'link' => 'Link',
                'link_url' => 'Link address',
                'image' => 'Insert image',
                'clear_formatting' => 'Clear formatting',
                'undo' => 'Undo',
                'redo' => 'Redo',
                'editor_label' => 'Note',
                'image_uploading' => 'Uploading the image…',
                'image_failed' => 'That image could not be uploaded.',
                'image_too_large' => 'That image is larger than 5 MB.',
                'image_type' => 'Images must be JPG, PNG or WEBP.',
                'discard_title' => 'Discard unsaved note?',
                'discard_body' => 'Your changes will be lost.',
                'keep_editing' => 'Keep editing',
                'view' => 'View',
                'hide' => 'Hide',
                'no_access' => 'You do not have access to this private note.',
                'count' => '{0} No notes yet|{1} 1 note|[2,*] :count notes',
                'discard_confirm' => 'Discard this note? What you have written is not saved.',
                'discard' => 'Discard',
                'added_success' => 'Note added successfully.',
            ],
            'files' => [
                'title' => 'Files',
                'coming' => 'Reference photos, consent forms and documents attach here once file storage arrives.',
            ],
            'insights' => [
                'title' => 'Client insights',
                'coming' => 'Booking patterns appear here once this client has visits.',
            ],
        ],
        'validation' => [
            'phone_repeated' => 'This number is already on this client.',
            'email_repeated' => 'This address is already on this client.',
            'mobile_required' => 'A phone number is required.',
            'email_required' => 'An email address is required.',
            'first_name_required' => 'First name is required.',
            'email_invalid' => 'Enter a valid email address.',
            'dob_past' => 'A date of birth must be in the past.',
        ],
    ],
];
