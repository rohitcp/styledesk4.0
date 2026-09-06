<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Marketing
|--------------------------------------------------------------------------
|
| Email campaigns: what the business writes to its whole client list, as
| opposed to the email module, which is a conversation with one client.
|
*/

return [

    'title' => 'Email marketing',
    'subtitle' => 'Campaigns to your client list',
    'intro' => 'Write to your clients as a group — offers, news and reminders — and see what they did with it.',

    'create' => 'Create email campaign',
    'edit' => 'Edit campaign',
    'saved' => 'Campaign saved',
    'deleted' => 'Campaign deleted',
    'delete' => 'Delete draft',
    'delete_confirm' => 'Delete this draft? It has not been sent to anybody, and this cannot be undone.',
    'save_draft' => 'Save draft',
    'cancel' => 'Cancel',
    'search' => 'Search campaigns…',
    'all_statuses' => 'All statuses',
    'filters_active' => 'Filters',
    'clear' => 'Clear',

    'summary' => [
        'campaigns' => 'Campaigns',
        'sent' => 'Emails sent',
        'delivery_rate' => 'Delivery rate',
        'open_rate' => 'Open rate',
        'click_rate' => 'Click rate',
        'unsubscribed' => 'Unsubscribed',
    ],

    'statuses' => [
        'draft' => 'Draft',
        'scheduled' => 'Scheduled',
        'sending' => 'Sending',
        'sent' => 'Sent',
        'paused' => 'Paused',
        'cancelled' => 'Cancelled',
        'failed' => 'Failed',
    ],

    'table' => [
        'name' => 'Campaign',
        'audience' => 'Audience',
        'recipients' => 'Recipients',
        'scheduled' => 'Scheduled',
        'sent' => 'Sent',
        'delivered' => 'Delivered',
        'opened' => 'Opened',
        'clicked' => 'Clicked',
        'author' => 'Created by',
        'status' => 'Status',
    ],

    /* Step one: what the email is and who it comes from. */
    'details' => [
        'title' => 'Campaign details',
        'intro' => 'What this campaign is called, and what your clients will see in their inbox.',
        'name' => 'Campaign name',
        'name_hint' => 'For your own reference. Your clients never see this.',
        'name_placeholder' => 'September massage promotion',
        'subject' => 'Email subject',
        'subject_placeholder' => 'Save 20% on your next massage',
        'preview_text' => 'Preview text',
        'preview_hint' => 'The line shown after the subject in most inboxes.',
        'preview_placeholder' => 'Book your September appointment today.',
        'from_name' => 'From name',
        'reply_to' => 'Reply-to email',
        'reply_hint' => 'Where replies go. Leave blank to use your business email.',
    ],

    /* Step two: who it goes to. */
    'audience' => [
        'title' => 'Audience',
        'intro' => 'Who this campaign goes to. The rules are applied when it sends, so a list that grows before then grows with it.',
        'scope' => 'Clients',
        'locations' => 'Locations',
        'tags' => 'Tags',
        'staff' => 'Seen by staff member',
        'services' => 'Who had service',
        'lapsed' => 'Has not visited in',
        'visited' => 'Visited within',
        'booking' => 'Upcoming appointment',
        'days' => ':days days',
        'any' => 'Any',
        'has_upcoming' => 'Has one booked',
        'no_upcoming' => 'Has nothing booked',

        'scopes' => [
            'all' => 'All clients',
            'active' => 'Active clients',
        ],

        /*
        | The audience column on the listing, in words.
        |
        | Their own group, because four of these would otherwise collide with
        | the form labels above — "Has not visited in" is a field label and
        | "no visit in 90 days" is a sentence about a saved campaign, and the
        | second silently replaced the first when they shared a key.
        */
        'said' => [
            'locations' => '{1} 1 location|[2,*] :count locations',
            'tags' => '{1} 1 tag|[2,*] :count tags',
            'staff' => '{1} 1 staff member|[2,*] :count staff',
            'services' => '{1} 1 service|[2,*] :count services',
            'lapsed' => 'no visit in :days days',
            'visited' => 'visited in :days days',
            'has_upcoming' => 'has an upcoming booking',
            'no_upcoming' => 'nothing booked',
        ],
    ],

    /*
    | The estimate. Three numbers rather than one, because "1,248 clients"
    | hides the two facts an owner needs before pressing send.
    */
    'estimate' => [
        'title' => 'Estimated recipients',
        'eligible' => 'Eligible',
        'unsubscribed' => 'Unsubscribed',
        'invalid' => 'Invalid email',
        'total' => 'Matched by your rules',
        'counting' => 'Counting…',
        'hint' => 'Counted now. The rules are applied again when the campaign sends, so this can move.',
        'none' => 'Nobody matches these rules yet.',
        'all_unsubscribed' => 'Everybody matching these rules has unsubscribed from marketing email.',
    ],

    /* What is built, and what is not. */
    'next' => [
        'title' => 'Still to come',
        'intro' => 'This campaign can be described and saved. Designing and sending it are the next steps.',
        'design' => 'Design the email',
        'preview' => 'Preview and test',
        'send' => 'Send or schedule',
        'soon' => 'Coming soon',
    ],

    'empty' => 'No campaigns yet.',
    'empty_hint' => 'Create one to write to your clients as a group.',
    'no_matches' => 'No campaign matches that search.',

    'results' => [
        'zero' => 'No campaigns found',
        'one' => '1 campaign found',
        'many' => ':count campaigns found',
    ],
    'showing' => 'Showing :from–:to of :total campaigns',
    'actions_for' => 'Actions for :name',

];
