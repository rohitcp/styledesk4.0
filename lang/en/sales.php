<?php

declare(strict_types=1);

return [

    'title' => 'Sales',
    'intro' => 'View earnings, payments, outstanding balances, refunds, tips, and transaction activity.',

    'period' => 'Date range',
    'periods' => [
        'today' => 'Today',
        'yesterday' => 'Yesterday',
        'week' => 'This week',
        'month' => 'This month',
        'custom' => 'Custom range',
    ],
    'from' => 'From',
    'to' => 'To',
    'apply' => 'Apply',

    'widgets' => [
        'total_sales' => 'Total Sales',
        'collected' => 'Payments Collected',
        'outstanding' => 'Outstanding Balance',
        'refunds' => 'Refunds',
        'tips' => 'Tips Collected',
        'transactions' => 'Transactions',
    ],

    'notes' => [
        'total_sales' => 'By appointment date',
        'collected' => 'By payment date',
        'outstanding' => 'Still owed',
        'refunds' => 'By payment date',
        'tips' => 'By payment date',
        'transactions' => 'Payments taken',
    ],

    'vs_previous' => 'vs previous period',
    'show_these' => 'Show these →',

    /*
    | The two halves are counted differently and the page says so. A deposit
    | taken in August for a September appointment is September's sale and
    | August's payment; a reader who does not know that will find the totals
    | do not reconcile and conclude the figures are wrong.
    */
    'counting_note' => 'Sales and outstanding balances are counted by appointment date. Payments, refunds and tips are counted by when the money moved.',

    'transactions' => 'Transactions',
    'search_placeholder' => 'Transaction, booking, client, email or phone',
    'actions_for' => 'Actions for :name',
    'showing' => 'Showing',
    'results' => [
        'zero' => 'No transactions',
        'one' => '1 transaction',
        'many' => ':count transactions',
        'clear' => 'Clear filters',
    ],
    'empty' => 'No money has moved in this period.',
    'walk_in' => 'Walk-in',

    'columns' => [
        'reference' => 'Transaction',
        'at' => 'Date & time',
        'booking' => 'Booking',
        'client' => 'Client',
        'services' => 'Service',
        'staff' => 'Staff',
        'location' => 'Location',
        'total' => 'Booking total',
        /* This payment, as against what the booking has taken in total — two
           different questions, and the table answers both. */
        'amount' => 'Paid',
        'balance' => 'Balance',
        'method' => 'Method',
        'status' => 'Status',
    ],

    'drawer' => [
        'at_a_glance' => 'At a glance',
        'nothing_owed' => 'Nothing owed',
        'next_appointment' => 'Next appointment',
        'total_visits' => 'Total visits',
        'lifetime_spend' => 'Lifetime spend',
        'last_visit' => 'Last visit',
        'tags' => 'Tags',
        'account' => 'Account',
        'bookings' => 'Bookings',
        'spend' => 'Total paid',
        'owed' => 'Still owed',
        'transaction' => 'Transaction',
        'booking' => 'Booking',
        'amount' => 'This payment',
        'tip' => 'Tip',
        'paid_total' => 'Paid on this booking',
        'recorded_by' => 'Taken by',
        'online' => 'Online',
        'processor_reference' => 'Processor reference',
        'view_client' => 'View Full Details',
        'view_receipt' => 'View Full Receipt',
        'download' => 'Download PDF',
    ],

    'actions' => [
        'view_booking' => 'View booking',
        'open_booking' => 'Open booking page',
        'view_client' => 'View client',
        'view_receipt' => 'View receipt',
    ],
];
