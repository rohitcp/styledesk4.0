<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Forms & Waivers
|--------------------------------------------------------------------------
|
| The module's vocabulary, in one place. Every list a form is validated
| against is here rather than as literals in a controller or an enum column
| in the database — the same arrangement config/loyalty.php and
| config/membership.php use, and for the same reason: the list ships with the
| code, so a second copy in a migration is one that needs a migration to
| correct.
|
| The reader never sees these keys. Labels resolve through each locale's
| forms.php lang file,
| so a key here without a translation there is a screen showing 'every_30'.
|
*/

return [

    /*
    | What kind of thing a form is.
    |
    | Not enforcement — a waiver and a questionnaire are the same machinery —
    | but it is how a business finds one among forty, and how the client
    | profile groups what it shows.
    */
    'types' => [
        'intake',
        'consultation',
        'consent',
        'medical',
        'waiver',
        /* A treatment record and a photo release are their own kinds rather
           than "custom": both are asked for by name, and a business that has
           to file them as Custom cannot find them again by type. */
        'treatment',
        'photo_consent',
        'policy',
        'aftercare',
        'custom',
    ],

    /*
    | A form's life.
    |
    |   draft     being built, never assigned to anybody
    |   active    live, and the only status that is ever assigned
    |   inactive  paused, keeping its questions and its submissions
    |   archived  out of the way; submissions against it survive untouched
    */
    'statuses' => ['draft', 'active', 'inactive', 'archived'],

    /*
    | The categories a business starts with.
    |
    | Seeded as rows the business then owns — renameable, reorderable and
    | disableable — rather than read from here at runtime. A tattoo studio
    | and a med spa do not file forms the same way.
    */
    'default_categories' => [
        'intake',
        'consultation',
        'consent',
        'medical',
        'treatment',
        'waiver',
        'policy',
        'aftercare',
        'general',
    ],

    /*
    | How long a completed form counts for.
    |
    | `days` is what the expiry date is computed from at completion. Null on
    | `once` and `never` because they do not expire, and on
    | `every_appointment` because that one is answered per booking rather
    | than by a date, and on `custom` because the business supplies the
    | number.
    */
    'validity' => [
        'once' => ['days' => null],
        'every_appointment' => ['days' => null],
        'every_30_days' => ['days' => 30],
        'every_90_days' => ['days' => 90],
        'every_6_months' => ['days' => 183],
        'every_12_months' => ['days' => 365],
        'custom' => ['days' => null],
        'never' => ['days' => null],
    ],

    /*
    | How the questions are presented.
    |
    |   classic  several questions a screen — the front desk and a tablet
    |   focused  one question at a time — a client on a phone
    */
    'layouts' => ['classic', 'focused'],

    /*
    | Where a submission is on its line.
    |
    | Ordered as it progresses, which is the order the client profile's
    | filters read in. `signed` sits past `completed` rather than beside it:
    | a form requiring a signature is not finished until there is one.
    */
    'submission_statuses' => [
        'not_sent',
        'sent',
        'viewed',
        'in_progress',
        'completed',
        'signed',
        'expired',
        'declined',
        'cancelled',
    ],

    /*
    | Who filled it in, and where.
    |
    | What tells a waiver signed on the salon's own tablet from one the
    | client completed at home the night before.
    */
    'sources' => ['client_link', 'front_desk', 'check_in', 'staff', 'kiosk'],

    /*
    | How a signature was given. Both are typed into the same column; this
    | records which, because "drawn" and "typed" are not equally strong
    | evidence and a business reading an old waiver should be told which it
    | is looking at.
    */
    'signature_methods' => ['draw', 'type'],

    /*
    | What a question can be.
    |
    | Grouped the way the builder's left panel reads, and each type declares
    | what it needs rather than the builder carrying a switch statement per
    | capability:
    |
    |   options      the business supplies a list of answers
    |   placeholder  an empty-field example can be set
    |   content      static text rather than a question — no answer, no
    |                required, and never mapped to a client field
    |   breaks       ends the page, so the renderer starts a new one
    |
    | Adding a type here is what puts it in the builder. The renderer reads
    | the same list, so a type that is not here cannot be saved either.
    */
    'field_types' => [
        'basic' => [
            'text' => ['placeholder' => true],
            'long_text' => ['placeholder' => true],
            'email' => ['placeholder' => true],
            'phone' => ['placeholder' => true],
            'number' => ['placeholder' => true],
            'date' => [],
            /* Its own type rather than a date with a label, because it is the
               one date a form asks for that has a client field to map to and
               a sensible range of its own. */
            'date_of_birth' => [],
            'time' => [],
        ],

        'choice' => [
            'yes_no' => [],
            'dropdown' => ['options' => true, 'placeholder' => true],
            'checkbox' => ['options' => true],
            /* One answer from a visible list. Kept under its original key:
               forms already saved say `multiple_choice`, and renaming the key
               would empty their answers to read the new one. */
            'multiple_choice' => ['options' => true],
            'multi_select' => ['options' => true, 'placeholder' => true],
        ],

        'waiver' => [
            'consent' => [],
            'initials' => [],
            'signature' => [],
            /*
             * Signed and named in one question.
             *
             * A drawn signature on its own is hard to read back — the whole
             * point of a waiver is being able to say WHO signed it, and a
             * squiggle is not a name. This asks for both, and the two land in
             * the columns `form_submissions` already keeps for them:
             * `signature` and `signed_name`.
             */
            'signature_name' => [],
            /* Consent with the wording attached: the client is agreeing to
               something specific, and the something is the field's own text
               rather than a heading above it. */
            'terms' => ['content' => true, 'accepts' => true],
        ],

        'layout' => [
            'heading' => ['content' => true],
            'paragraph' => ['content' => true],
            'divider' => ['content' => true],
            'section' => ['content' => true],
            'page_break' => ['content' => true, 'breaks' => true],
        ],

        'advanced' => [
            'file_upload' => [],
            /*
             * Two sets of photographs, one question.
             *
             * A treatment is documented before and after, and the pair is
             * the record — so it is one component the business drops in
             * rather than two upload fields they have to remember to label
             * consistently. The two sides are stored separately underneath.
             */
            'before_after' => ['uploads' => true],
            'rating' => ['scale' => true],
            'scale' => ['scale' => true],
            /* Carries a value the client never sees and never answers — a
               campaign name, a source. No label on the page, so nothing to
               make required. */
            'hidden' => ['hidden' => true],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Where a form's public link lives
    |--------------------------------------------------------------------------
    |
    | `subdomain` is the business's own booking host — acme.styledesk.app/form/…
    | — which is what a client should see: the form is the salon's, not
    | StyleDesk's.
    |
    | `central` puts it on the application's own domain instead. It exists
    | because a tenant subdomain has to be SERVED: wildcard DNS and a vhost
    | that answers for it. On a development machine that is often not true,
    | and a form link nobody can open is no use for testing the thing it links
    | to. Both addresses answer either way; this only decides which one the
    | settings screen hands out.
    |
    */
    'public_link_host' => env('FORMS_PUBLIC_LINK_HOST', 'subdomain'),

    /*
    |--------------------------------------------------------------------------
    | What a form may be sent
    |--------------------------------------------------------------------------
    |
    | The authority on this is App\Services\Storage\FileValidator: it checks
    | the extension AND the file's real MIME type against an allowlist, and
    | anything this offered that it refuses would be a setting a business
    | could switch on and a client could not get past.
    |
    | HEIC is deliberately absent. Every iPhone photograph is one, so it
    | belongs here — but the validator refuses it on both checks today, and
    | browsers cannot display it either, so adding it means extending the
    | allowlist and transcoding on the way in. Offering it before that work is
    | done would fail at the worst moment: a client, on their phone, trying to
    | finish a form.
    |
    | `max_kb` matches the validator's own image limit. FormsSettingsTest
    | asserts the two agree, so the day one moves the other is not left
    | promising a size the disk will reject.
    |
    */
    'uploads' => [
        'types' => ['jpg', 'jpeg', 'png', 'webp', 'gif', 'pdf'],
        'default_types' => ['jpg', 'jpeg', 'png', 'webp'],
        'max_kb' => 5120,
        /* Ten a side, which is what the client files screen already allows
           for a before-and-after record. */
        'default_max_files' => 10,
        'max_files' => 20,
    ],

    /*
    | Where a question's help text sits.
    |
    | Below by default: a client reads the question, then the field, and an
    | instruction between the two is read before it is needed. Above is for
    | the cases where it has to be read first — "include anything that may
    | affect your treatment" belongs before the box, not after it.
    */
    'help_positions' => ['above', 'below'],

    /*
    | How a question's own answers are laid out.
    |
    | A property of the question, not of the form: "Yes / No" reads well on
    | one line and six massage types read better in two columns, and both can
    | sit in the same form. It only ever moves the answers around — the row
    | the question sits in is a separate decision.
    */
    'option_layouts' => ['single', 'two'],

    /*
    | How a date is written.
    |
    | Not translated, and deliberately: a format pattern is a pattern rather
    | than prose, and "MM/DD/YYYY" means the same thing to a reader in every
    | language StyleDesk ships. The lang rule says as much.
    |
    | A month and a day with no year is how a business asks for a birthday it
    | only wants to send a card on.
    */
    'date_formats' => [
        'mm/dd/yyyy' => ['pattern' => 'MM/DD/YYYY', 'parts' => ['month', 'day', 'year']],
        'dd/mm/yyyy' => ['pattern' => 'DD/MM/YYYY', 'parts' => ['day', 'month', 'year']],
        'mm/dd' => ['pattern' => 'MM/DD', 'parts' => ['month', 'day']],
        'dd/mm' => ['pattern' => 'DD/MM', 'parts' => ['day', 'month']],
    ],

    /*
    |--------------------------------------------------------------------------
    | How a form looks, and how its questions are arranged
    |--------------------------------------------------------------------------
    |
    | The vocabulary the builder offers and the public renderer will read, in
    | one place so the two cannot disagree about what "spacious" means. The
    | numbers live here rather than in either of them for the same reason.
    |
    | Two levels, and the distinction is the point: the form-level answers are
    | the business's house style, and a row may override the ones that are
    | about arrangement. A global setting that forced every row to match would
    | make "first name beside last name, medical history on its own" —
    | which is what an intake form actually looks like — impossible.
    |
    */
    'theme' => [
        /* The reading width of the form. `null` is the full width of
           whatever it is rendered in. */
        'widths' => [
            'narrow' => ['px' => 480],
            'standard' => ['px' => 680],
            'wide' => ['px' => 880],
            'full' => ['px' => null],
        ],

        'alignments' => ['left', 'center'],

        /* Where the buttons sit, which is not the same question as where the
           form's content is set: a left-aligned form with a full-width
           submit button underneath is a perfectly ordinary thing to want. */
        'button_alignments' => ['left', 'center', 'right'],

        /* Above reads better on a phone and is the default everywhere.
           Beside is for a desk form somebody fills in at speed. */
        'label_positions' => ['above', 'left'],

        'field_styles' => [
            'compact' => ['height' => 36, 'text' => 13],
            'default' => ['height' => 44, 'text' => 14],
            'comfortable' => ['height' => 52, 'text' => 15],
        ],

        'radii' => [
            'small' => ['px' => 4],
            'medium' => ['px' => 8],
            'large' => ['px' => 14],
        ],

        /* The business's own palette unless somebody deliberately says
           otherwise: a form is the salon writing to its client. */
        'backgrounds' => ['business', 'custom'],

        'button_styles' => ['business', 'rounded', 'full'],

        /* The gap between one row and the next. `custom` takes a number of
           pixels from the business instead. */
        'row_spacings' => [
            'compact' => ['px' => 8],
            'normal' => ['px' => 16],
            'comfortable' => ['px' => 24],
            'spacious' => ['px' => 36],
            'custom' => ['px' => null],
        ],

        /* How many questions a row holds. Two is the most a form can be read
           down on a phone, which is where most of them are filled in. */
        'row_layouts' => ['single', 'two'],

        /* Only the even split is offered for now; the other two are here
           because the column widths they describe are what the renderer will
           read, and a row saved with one must not be refused later. */
        'column_splits' => [
            '50_50' => [50, 50],
            '40_60' => [40, 60],
            '60_40' => [60, 40],
        ],

        'defaults' => [
            'width' => 'standard',
            'alignment' => 'left',
            'label_position' => 'above',
            'field_style' => 'default',
            'radius' => 'medium',
            'background' => 'business',
            'background_color' => null,
            'button_style' => 'business',
            'row_spacing' => 'normal',
            'row_spacing_px' => null,
            /* What a NEW row starts as. Only a default: the layout belongs to
               each row, and this is the answer one takes before anybody has
               said otherwise. */
            'default_layout' => 'single',

            /*
             * The wording on the buttons.
             *
             * Null means "whatever StyleDesk calls it", which is the only
             * answer that stays translated: a business that types "Submit"
             * here has written English into a form its Spanish clients will
             * read.
             */
            'submit_label' => null,
            'cancel_label' => null,

            /* A way out. Off by default — a client who opened an intake link
               is there to finish it, and a Cancel button beside Submit is an
               invitation not to. */
            'show_cancel' => false,

            'button_alignment' => 'left',

            /*
             * Keep what the client has typed.
             *
             * A twenty-question medical history is not something anybody
             * wants to type twice, and a phone that rings mid-form is the
             * ordinary case rather than the unlucky one.
             */
            'save_progress' => false,
        ],
    ],

    'limits' => [
        'name' => 120,
        'internal_description' => 255,
        'category_name' => 60,
        /* A year and a bit, so "every 12 months" can be expressed as a
           custom period without the field accepting a century. */
        'validity_days' => 400,
        /* A form somebody can actually finish. Two hundred questions is not
           an intake form, and an unbounded schema is an unbounded json
           column. */
        'fields' => 200,
        'rows' => 200,
        /* A row holds one question or two. More than that is a table, and a
           table is not something a client fills in on a phone. */
        'row_fields' => 2,
        'field_label' => 255,
        'field_options' => 50,
        'field_help' => 500,
        'field_default' => 255,
        'field_error' => 255,
        /* The longest a business may hold a client to. Long enough for a
           medical history, short enough that the column has an end. */
        'field_max_length' => 5000,
    ],
];
