<?php

declare(strict_types=1);

/*
| Customer reviews and feedback.
|
| Two audiences in one file, kept apart by their top-level keys: everything
| under `page` and `email` is read by a client who has never heard of
| StyleDesk, and everything else is read by the business. The client-facing
| wording is short on purpose — §33 is the whole design brief, and every extra
| sentence between the link and the star costs a review.
*/

return [

    'title' => 'Reviews & Feedback',

    'stars' => '{1} 1 star|[2,*] :count stars',

    /* What the stars mean, for the label beside the one being chosen. */
    'rating_labels' => [
        1 => 'Very poor',
        2 => 'Poor',
        3 => 'Average',
        4 => 'Good',
        5 => 'Excellent',
    ],

    /* Where the business has got to with a piece of feedback. */
    'statuses' => [
        'new' => 'New',
        'reviewing' => 'Reviewing',
        'contacted' => 'Customer contacted',
        'resolved' => 'Resolved',
        'closed' => 'Closed',
        'no_action' => 'No action required',
    ],

    /* Where the asking has got to, on the booking's own panel. */
    'request_statuses' => [
        'not_requested' => 'Not requested',
        'scheduled' => 'Scheduled',
        'sent' => 'Sent',
        'completed' => 'Completed',
    ],

    /* ---------------------------------------------------------- the page -- */

    'page' => [
        'title' => 'How was your visit?',
        'question' => 'How was your experience?',
        'question_hint' => 'Tap a star. That is all we need.',

        'comment_label' => 'Tell us about your experience',
        'comment_optional' => 'Optional',
        'comment_placeholder' => 'Tell us what you liked or what we could improve.',

        'recommend_label' => 'Would you recommend us to a friend?',
        'recommend' => [
            'yes' => 'Yes',
            'maybe' => 'Maybe',
            'no' => 'No',
        ],

        /* Shown once a low rating is chosen. Different words, because the
           question is a different question — §14. */
        'sorry_title' => 'We are sorry your experience did not meet expectations.',
        'sorry_hint' => 'Please tell us how we can improve.',
        'contact_label' => 'I would like someone from the business to contact me.',

        'submit' => 'Submit',
        'submit_negative' => 'Send feedback',
        'choose_rating' => 'Choose a rating first.',

        'thanks_title' => 'Thank you for your feedback!',
        'thanks_positive' => 'We are happy you enjoyed your visit. Would you like to share your experience with others?',
        'thanks_negative' => 'Thank you for telling us. We will use this to put things right.',
        'thanks_contact' => 'Somebody from the business will be in touch.',
        'google_cta' => 'Review us on Google',
        'done' => 'Done',

        'already' => 'Thank you. Your feedback has already been submitted.',
    ],

    /* --------------------------------------------------------- the email -- */

    'email' => [
        'subject' => 'How was your visit to :business?',
        'preview' => 'It takes one tap.',
        'headline' => 'How was your visit?',
        'intro' => 'Hi :name — we would love to hear about your experience at :business.',
        'rate' => 'Rate your experience',
        'cta' => 'Leave feedback',
    ],

    /* ------------------------------------------------------ the settings -- */

    'settings' => [
        'title' => 'Reviews & Feedback',
        'intro' => 'Ask clients how their visit went after a completed appointment, catch the unhappy ones before they go public, and point the happy ones at your listing.',

        'enable' => 'Enable customer reviews',
        'enable_hint' => 'When this is off nothing is sent. Reviews already given stay on the client record and in your reports.',
        'disabled_note' => 'Switch this on to choose when clients are asked, how they are asked, and where the happy ones are sent afterwards.',

        'timing' => 'When to ask',
        'timing_hint' => 'Measured from the moment the appointment is completed. An hour is usually right: long enough that the client has left, soon enough that they remember it.',
        'delays' => [
            'immediate' => 'Immediately',
            '1h' => '1 hour after completion',
            '3h' => '3 hours after completion',
            '6h' => '6 hours after completion',
            'next_day' => 'The next day',
        ],

        'channel' => 'How to ask',
        'channel_hint' => 'A client with no address on file is not asked. Text messaging is not available yet.',
        'channels' => [
            'email' => 'Email',
            'sms' => 'SMS',
            'both' => 'SMS + Email',
        ],
        'coming_soon' => 'Coming soon',

        'google' => 'Google reviews',
        'google_enable' => 'Offer a Google review to happy clients',
        'google_hint' => 'Shown only to clients who leave 4 or 5 stars. Clients who leave fewer are asked how you could improve instead, and are never sent to a public listing.',
        'google_urls' => 'Review links by location',
        'google_urls_hint' => 'Each branch has its own Google listing. A branch with no link simply does not offer the button.',
        'google_url_placeholder' => 'https://g.page/r/…',
        'no_locations' => 'Add a location before setting Google review links.',

        'saved' => 'Review settings saved.',
    ],

];
