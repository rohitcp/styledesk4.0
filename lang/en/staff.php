<?php

declare(strict_types=1);

/*
| The Staff module: the directory, the profile, and the add/edit form.
|
| Field labels are shared across all three deliberately. "Preferred name"
| naming one thing on a profile and another on the form is the drift a single
| key exists to prevent.
*/

return [
    'title' => 'Staff members',
    'summary' => '{1} :count active member|[2,*] :count active members',
    'summary_pending' => '{1} :count pending invite|[2,*] :count pending invites',

    'add' => 'Add staff member',
    'add_title' => 'Add staff member',
    'add_intro' => 'Their details and role. Working hours, availability and per-service settings are configured once the record exists.',
    'adding' => 'Adding…',
    'edit' => 'Edit',
    'edit_person' => 'Edit :name',
    'edit_title' => 'Edit staff member',
    'view_profile' => 'View profile',

    'created' => ':name was added to your team.',
    'created_invited_to' => ':name was added and an invitation is on its way to :email.',
    'saved_person' => ":name's details were updated.",
    'add_failed' => "We couldn't add that staff member right now. Please try again.",
    'created_invited' => ':name has been added and their invitation is on its way.',
    'saved' => 'Staff member updated successfully.',
    'deleted' => ':name was removed from your team.',
    'save_failed' => "We couldn't save your changes. Please review the information and try again.",
    'correct_fields' => 'Please correct the highlighted fields and try again.',
    'delete_confirm' => 'Remove :name from your team? Their record is deleted and, if they had a login, they lose access to this business.',

    'search_placeholder' => 'Search by name, email, phone or job title',
    'search_label' => 'Search staff',
    'apply_filters' => 'Apply filters',

    'filters' => [
        'role' => 'Role',
        'all_roles' => 'All roles',
        'location' => 'Location',
        'all_locations' => 'All locations',
        'service' => 'Service',
        'all_services' => 'All services',
        'provider_type' => 'Provider type',
        'all_provider_types' => 'All provider types',
        'employment' => 'Employment',
        'all_employment' => 'All employment types',
        'status' => 'Status',
        'all_statuses' => 'All statuses',
        'sort' => 'Sort by',
    ],

    'columns' => [
        'name' => 'Name',
        'role' => 'Role',
        'location' => 'Location',
        'contact' => 'Contact',
        'services' => 'Services',
        'status' => 'Status',
        'last_login' => 'Last login',
        'actions' => 'Actions',
    ],

    'empty_title' => 'No staff match these filters.',
    'empty_hint' => 'Clear the filters, or invite someone from the team step.',
    'never' => 'Never',
    'all_locations' => 'All locations',
    'actions_for' => 'Actions for :name',

    'cards' => [
        'basic' => 'Basic information',
        'contact' => 'Contact information',
        'employment' => 'Role & employment',
        'employment_hint' => 'Role decides what they can do in StyleDesk. Employment type is how the business engages them — the two are independent.',
        'account' => 'Account',
    ],

    'fields' => [
        'first_name' => 'First name',
        'last_name' => 'Last name',
        'middle_name' => 'Middle name',
        'preferred_name' => 'Preferred name',
        'preferred_name_placeholder' => 'What the team and clients call them',
        'pronouns' => 'Pronouns',
        'job_title' => 'Job title',
        'job_title_placeholder' => 'Senior Stylist',
        'employee_ref' => 'Staff ID',
        'avatar' => 'Profile image',
        'avatar_hint' => 'JPG, PNG or WEBP, up to 2 MB. Shown on the booking page and in the team list.',
        'bio' => 'Bio',
        'bio_placeholder' => 'Shown on the booking page if they take appointments.',

        'email' => 'Primary email',
        'email_hint' => 'Also the address any invitation is sent to.',
        'work_email' => 'Work email',
        'phone' => 'Primary phone',
        'phone_type' => 'Phone type',
        'secondary_phone' => 'Secondary phone',
        'emergency_contact_name' => 'Emergency contact',
        'emergency_contact_phone' => 'Emergency phone',
        'emergency_contact_relationship' => 'Relationship',
        'relationship_placeholder' => 'Partner',
        'address' => 'Address',

        'role' => 'Role',
        'role_placeholder' => 'Choose a role',
        'role_hint' => 'You can only assign roles within your own access.',
        'location' => 'Primary location',
        'employment_type' => 'Employment type',
        'provider_type' => 'Provider type',
        'specialities' => 'Specialities',
        'services' => 'Services they provide',
        'services_hint' => 'Anyone assigned services becomes bookable by name.',

        'account_status' => 'Account status',
        'account_status_hint' => 'An inactive member cannot be booked and takes no new appointments.',
        'login_enabled' => 'Allow staff login',
        'login_enabled_hint' => 'They get their own StyleDesk account. Leave off for someone who only needs to appear on the calendar.',
        'send_invitation' => 'Send the invitation now',
        'send_invitation_hint' => 'Emails them a link to set a password and join. You can also send it later.',
        'invitation_message' => 'Message',
        'invitation_message_placeholder' => 'Looking forward to having you on the team.',
    ],

    'not_specified' => 'Not specified',

    'validation' => [
        'first_name_required' => 'First name is required.',
        'last_name_required' => 'Last name is required.',
        'email_required' => 'Primary email is required.',
        'email_invalid' => 'Enter a valid email address.',
        'email_taken' => 'Someone on your team already uses that email address.',
        'role_required' => 'Choose a role for this person.',
        'role_invalid' => 'You can only assign roles within your own access.',
        'location_invalid' => 'Choose one of your own locations.',
        'service_invalid' => 'Choose one of your own services.',
        'role_not_yours' => 'You cannot assign that role.',
        'own_role' => 'You cannot change your own role. Ask another administrator.',
        'avatar_max' => 'The profile image must be 2 MB or smaller.',
        'avatar_mimes' => 'Use a JPG, PNG or WEBP image.',
    ],

    /*
     * The profile screen's fact labels.
     *
     * Separate from `fields` because the profile states what a value is
     * ("Legal name") where the form asks for parts of it ("First name"), and
     * collapsing the two would make one screen ask a question the other
     * answers.
     */
    'profile' => [
        'about' => 'About',
        'legal_name' => 'Legal name',
        'preferred_name' => 'Preferred name',
        'pronouns' => 'Pronouns',
        'employee_ref' => 'Staff ID',

        'contact' => 'Contact',
        'email' => 'Primary email',
        'work_email' => 'Work email',
        'phone' => 'Primary phone',
        'secondary_phone' => 'Secondary phone',
        'address' => 'Address',
        'emergency_contact' => 'Emergency contact',

        'access' => 'Role & access',
        'role' => 'Role',
        'location' => 'Primary location',
        'login' => 'Staff login',
        'account' => 'Account',
        'last_login' => 'Last login',

        'services' => 'Services',
        'assign_services' => 'Assign services',

        'employment' => 'Employment',
        'employment_type' => 'Employment type',
        'provider_type' => 'Provider type',
        'specialities' => 'Specialities',
        'added' => 'Added',

        'invitation' => 'Invitation',
        'sent_to' => 'Sent to',

        'activity' => 'Activity',
        'activity_hint' => 'Administrative changes to this record.',
        'activity_empty' => 'Nothing recorded yet.',

        'bookable' => 'Bookable',
        'no_login' => 'No login',
        'summary_email' => 'Email',
        'summary_phone' => 'Phone',
        'summary_location' => 'Location',
    ],
];
