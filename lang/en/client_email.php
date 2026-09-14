<?php

declare(strict_types=1);

return [

    'title' => 'Email',
    'intro' => 'Send email to your clients from StyleDesk.',

    'settings' => [
        'enable' => 'Enable Client Email',
        'enable_hint' => 'When this is off, Send Email is hidden from client profiles. Email history stays where it is.',
        'enabled' => 'Enabled',
        'disabled' => 'Disabled',

        'default_method' => 'Default Sending Method',
        'default_hint' => 'One provider sends your client email. You can change it at any time.',
        'active' => 'ACTIVE',
        'coming_soon' => 'Coming soon',
        'use_this' => 'Use this',

        'sender' => 'Sender',
        'sender_hint' => 'How your emails are signed, whichever method sends them.',
        'sender_name' => 'Sender Name',
        'sender_name_hint' => 'The name your clients see. Defaults to your business name.',
        'from_address' => 'Sender email (From address)',
        'from_address_hint' => 'Sent through StyleDesk, so the From address is StyleDesk’s own. Your sender name above is what clients see, and replies go to the address below.',
        'from_address_gmail' => 'Your connected Gmail account. Clients see it as the sender and replies land in that inbox.',
        'reply_to' => 'Reply-To Email',
        'reply_to_hint' => 'Where a client’s reply goes. Without one, replies reach nobody.',
        'reply_to_gmail' => 'Not used while Gmail is sending — replies go straight to your connected inbox.',
        'preview' => 'Your clients will see',

        'send_test' => 'Send Test Email',
        'test_hint' => 'Sent to your own address, so you can see exactly what a client gets.',
        'test_sent' => 'Test email sent to :email.',
        'test_failed' => 'The test could not be sent. :reason',
        'test_subject' => 'Test email from StyleDesk',
        'test_body' => "This is a test email from :name.\n\nIf you can read this, your client email is set up and working. Nothing has been sent to any of your clients.",

        'saved' => 'Your email settings have been saved.',
        'save' => 'Save',
    ],

    'providers' => [
        'styledesk' => [
            'name' => 'StyleDesk Email',
            'description' => 'Send emails directly through StyleDesk without connecting an external email account.',
        ],
        'gmail' => [
            'name' => 'Connect Gmail',
            'description' => 'Connect your business Gmail or Google Workspace account and send emails from your existing business email address.',
        ],
    ],

    'connection' => [
        'connected' => 'Connected',
        'disconnected' => 'Disconnected',
        'needs_attention' => 'Needs Attention',
    ],

    'send' => [
        'action' => 'Send Email',
        'title' => 'Send Email',
        'to' => 'To',
        'from' => 'From',
        'reply_to' => 'Reply-to',
        'new_message' => 'New message',
        'minimise' => 'Minimise',
        'expand' => 'Expand',
        'discard' => 'Discard',
        'discard_title' => 'Discard this draft?',
        'discard_body' => 'This message has not been sent. Discarding it cannot be undone.',
        'keep_draft' => 'Keep draft',
        'unresolved' => 'This wording still has placeholders in it. Choose the related booking below to fill them in, or edit them out by hand.',
        'search_templates' => 'Search templates…',
        /* What the client actually sees in their inbox. Said plainly on the
           settings screen too, because an owner who expected their own address
           should find out here rather than from a client. */
        'from_via' => ':name via StyleDesk',
        'template' => 'Email Template',
        'no_template' => 'No template',
        'related_booking' => 'Related Booking',
        'no_booking' => 'None',
        'subject' => 'Subject',
        'message' => 'Message',
        'cancel' => 'Cancel',
        'submit' => 'Send Email',
        'sending' => 'Sending…',
        'sent' => 'Email sent to :name',
    ],

    'statuses' => [
        'queued' => 'Queued',
        'sent' => 'Sent',
        'delivered' => 'Delivered',
        'failed' => 'Failed',
    ],

    'history' => [
        'title' => 'Email History',
        'empty' => 'No email has been sent to this client yet.',
        'sent_by' => 'Sent by :name',
        'system' => 'StyleDesk System',
        'view' => 'View',
    ],

    'errors' => [
        'disabled' => 'Client email is switched off for this business. Turn it on in App Settings → Email.',
        'no_provider' => 'No sending method is set up. Choose one in App Settings → Email.',
        'no_address' => 'This client has no email address on file.',
        'failed' => 'The email could not be sent. It is on the client’s record as failed, and you can try again.',
        'gmail_not_connected' => 'No Gmail account is connected. Connect one in App Settings → Email.',
        'reconnect_gmail' => 'Reconnect Gmail',
    ],

    /*
    | The templates a message can start from.
    |
    | Scaffolding, not envelopes: the drawer drops one in, the sender edits it,
    | and what is stored is what was actually sent.
    */
    'templates' => [
        'appointment_follow_up' => [
            'name' => 'Appointment Follow-up',
            'subject' => 'Thank you for visiting {{business_name}}',
            'body' => "Hello {{client_first_name}},\n\nThank you for coming in on {{booking_date}}. We hope you are happy with your {{service_name}}.\n\nIf there is anything at all you would like adjusted, just reply to this email and we will look after it.\n\nThank you,\n{{business_name}}",
        ],
        'appointment_information' => [
            'name' => 'Appointment Information',
            'subject' => 'Your appointment at {{business_name}}',
            'body' => "Hello {{client_first_name}},\n\nThis is a note about your appointment on {{booking_date}} at {{booking_time}} with {{staff_name}}.\n\nBooking reference: {{booking_reference}}\n\nIf you need to change anything, reply to this email or give us a call.\n\nThank you,\n{{business_name}}",
        ],
        'payment_reminder' => [
            'name' => 'Payment Reminder',
            'subject' => 'A reminder about your balance at {{business_name}}',
            'body' => "Hello {{client_first_name}},\n\nThis is a friendly reminder that your outstanding balance is {{balance_due}}.\n\nYou can settle it on your next visit, or reply to this email and we will send you a payment link.\n\nThank you,\n{{business_name}}",
        ],
        'outstanding_balance' => [
            'name' => 'Outstanding Balance',
            'subject' => 'Outstanding balance for your visit on {{booking_date}}',
            'body' => "Hello {{client_first_name}},\n\nYour visit on {{booking_date}} has an outstanding balance of {{balance_due}}.\n\nBooking reference: {{booking_reference}}\n\nIf you believe this is a mistake, please reply and we will check it straight away.\n\nThank you,\n{{business_name}}",
        ],
        'thank_you' => [
            'name' => 'Thank You',
            'subject' => 'Thank you from {{business_name}}',
            'body' => "Hello {{client_first_name}},\n\nThank you for choosing {{business_name}}. It was a pleasure looking after you.\n\nWe look forward to seeing you again soon.\n\nThank you,\n{{business_name}}",
        ],
        'service_follow_up' => [
            'name' => 'Service Follow-up',
            'subject' => 'How is your {{service_name}}?',
            'body' => "Hello {{client_first_name}},\n\nIt has been a little while since your {{service_name}} with {{staff_name}}. We wanted to check how you are getting on with it.\n\nIf you would like a touch-up or have any questions, just reply to this email.\n\nThank you,\n{{business_name}}",
        ],
        'membership_information' => [
            'name' => 'Membership Information',
            'subject' => 'Membership at {{business_name}}',
            'body' => "Hello {{client_first_name}},\n\nWe thought you might like to know about our membership options, which give regular clients better rates and priority booking.\n\nReply to this email and we will send you the details.\n\nThank you,\n{{business_name}}",
        ],
        'general_message' => [
            'name' => 'General Message',
            'subject' => 'A message from {{business_name}}',
            'body' => "Hello {{client_first_name}},\n\n\nThank you,\n{{business_name}}",
        ],
    ],

    'gmail' => [
        'connect' => 'Connect Gmail',
        'reconnect' => 'Reconnect',
        'disconnect' => 'Disconnect',
        'connected' => 'Gmail connected. Your client email now sends from :email.',
        'connected_on' => 'Connected :date',
        'disconnected' => 'Gmail has been disconnected. Client email now sends through StyleDesk Email.',
        'failed' => 'Gmail could not be connected. :reason',
        'state_mismatch' => 'That connection attempt could not be verified. Please try again.',
        'no_code' => 'Google did not send anything back to connect with.',
        'no_refresh_token' => 'Google did not issue a lasting permission for this account.',
        'reconnect_needed' => 'The Gmail connection has stopped working and needs to be connected again.',
        'unknown_error' => 'Google did not say why.',
        'replies_note' => 'Clients reply straight to this inbox. Replies are not brought into StyleDesk.',
    ],

    'variables' => [
        'title' => 'You can use',
        'hint' => 'These are replaced when the template is loaded.',
    ],
];
