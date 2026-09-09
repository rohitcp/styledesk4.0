<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Reason codes
|--------------------------------------------------------------------------
|
| Why something happened, chosen from a list rather than typed.
|
| "Why do we lose bookings" is a question of counting, and forty spellings of
| "client changed their mind" answer none of it. So every place StyleDesk asks
| for a reason asks it the same way: pick from a list the business maintains.
|
| Reference data, not code. A tenth reason type is a block added here — the
| table, the settings screen and the picker all read this file, so nothing has
| to be redesigned to make room for "Chargeback" or "Product Return".
|
| Each type carries:
|   `label`     what the type is called on the settings screen
|   `intro`     one line saying where the list is used
|   `extra`     an optional second question asked alongside the reason, where
|               one answer is not enough — a no-show has a reason AND somebody
|               it is down to, and folding them into one list produces a
|               menu that is half cause and half blame
|   `reasons`   the defaults every business starts with
|
| A reason ending in "Other" always asks for a written explanation; see
| `App\Models\ReasonCode::seedDefaultsFor()`, which sets `requires_details` on
| it rather than every list having to remember to.
*/

return [

    /*
    | The reason whose selection demands a sentence. Matched by key so a
    | business renaming it to "Something else" does not switch the
    | requirement off.
    */
    'details_key' => 'other',

    'types' => [

        'booking-cancellation' => [
            'label' => 'Booking cancellation',
            'intro' => 'Why an appointment was called off.',
            'reasons' => [
                'Client Requested Cancellation',
                'Client No Longer Available',
                'Client Scheduling Conflict',
                'Client Illness',
                'Client Emergency',
                'Staff Unavailable',
                'Staff Illness',
                'Staff Emergency',
                'Business Closed',
                'Service Unavailable',
                'Resource Unavailable',
                'Location Unavailable',
                'Duplicate Booking',
                'Booking Created by Mistake',
                'Booking Details Incorrect',
                'Client Did Not Confirm',
                'Client No Show',
                'Payment Issue',
                'Deposit Not Received',
                'Weather / Emergency',
                'Technical Issue',
                'Other',
            ],
        ],

        'booking-reschedule' => [
            'label' => 'Booking reschedule',
            'intro' => 'Why an appointment moved.',
            'reasons' => [
                'Client Requested Reschedule',
                'Client Scheduling Conflict',
                'Client Running Late',
                'Client Illness',
                'Client Emergency',
                'Staff Requested Reschedule',
                'Staff Unavailable',
                'Staff Running Late',
                'Staff Illness',
                'Staff Emergency',
                'Business Scheduling Conflict',
                'Service Unavailable',
                'Resource Unavailable',
                'Location Changed',
                'Appointment Duration Changed',
                'Booking Created at Wrong Time',
                'Booking Created on Wrong Date',
                'Weather / Emergency',
                'Technical Issue',
                'Other',
            ],
        ],

        'no-show' => [
            'label' => 'No show',
            'intro' => 'Why somebody did not arrive.',
            /* Whose fault it was is a different question from what happened,
               and the reports that decide fees need both. */
            'extra' => [
                'key' => 'responsibility',
                'label' => 'Responsibility',
                'options' => ['client' => 'Client', 'business' => 'Business', 'system' => 'System', 'unknown' => 'Unknown'],
            ],
            'reasons' => [
                'Client Did Not Arrive',
                'Client Forgot Appointment',
                'Client Unable to Attend',
                'Client Emergency',
                'Client Illness',
                'Client Did Not Receive Reminder',
                'Client Could Not Contact Business',
                'Client Arrived Too Late',
                'Incorrect Appointment Date',
                'Incorrect Appointment Time',
                'Booking Created by Mistake',
                'Duplicate Booking',
                'Communication Issue',
                'Unknown Reason',
                'Other',
            ],
        ],

        'refund' => [
            'label' => 'Refund',
            'intro' => 'Why money went back out.',
            'extra' => [
                'key' => 'scope',
                'label' => 'Refund scope',
                'options' => ['full' => 'Full refund', 'partial' => 'Partial refund'],
            ],
            'reasons' => [
                'Client Requested Refund',
                'Service Not Completed',
                'Service Quality Issue',
                'Client Dissatisfied',
                'Incorrect Service Charged',
                'Incorrect Amount Charged',
                'Duplicate Charge',
                'Accidental Charge',
                'Deposit Refund',
                'Booking Cancelled',
                'Business Cancelled Appointment',
                'Staff Unavailable',
                'Product Returned',
                'Product Defective',
                'Gift Card Refund',
                'Package Refund',
                'Membership Refund',
                'Promotional Adjustment',
                'Payment Processing Error',
                'Fraudulent Transaction',
                'Manager Courtesy Refund',
                'Other',
            ],
        ],

        'payment-adjustment' => [
            'label' => 'Payment adjustment',
            'intro' => 'Why a bill was changed after it was written.',
            'extra' => [
                'key' => 'direction',
                'label' => 'Direction',
                'options' => ['increase' => 'Increase', 'decrease' => 'Decrease', 'none' => 'No monetary change'],
            ],
            'reasons' => [
                'Incorrect Amount Charged',
                'Incorrect Service Price',
                'Discount Applied',
                'Discount Removed',
                'Coupon Applied',
                'Coupon Removed',
                'Tax Correction',
                'Tip Correction',
                'Gratuity Adjustment',
                'Deposit Adjustment',
                'Balance Correction',
                'Payment Method Correction',
                'Partial Payment Adjustment',
                'Manual Price Override',
                'Package Credit Applied',
                'Membership Credit Applied',
                'Gift Card Adjustment',
                'Processing Fee Adjustment',
                'Transaction Fee Adjustment',
                'Rounding Adjustment',
                'Manager Courtesy Adjustment',
                'Administrative Correction',
                'Other',
            ],
        ],

        'client-status-change' => [
            'label' => 'Client status change',
            'intro' => 'Why a client moved between active, inactive and the rest.',
            'reasons' => [
                'New Client',
                'Client Activated',
                'Client Reactivated',
                'Client Inactive',
                'Client Requested Deactivation',
                'Client Moved Away',
                'Client No Longer Uses Services',
                'Duplicate Client Record',
                'Client Record Merged',
                'Client Deceased',
                'Client Banned',
                'Client Suspended',
                'Payment Issue',
                'Repeated No Shows',
                'Repeated Late Cancellations',
                'Policy Violation',
                'Staff Safety Concern',
                'Client Requested Account Closure',
                'Administrative Change',
                'Other',
            ],
        ],

        'staff-schedule-change' => [
            'label' => 'Staff schedule change',
            'intro' => 'Why a rota changed — shifts, hours, leave and cover.',
            'reasons' => [
                'Staff Requested Change',
                'Manager Requested Change',
                'Staff Unavailable',
                'Staff Illness',
                'Staff Emergency',
                'Personal Leave',
                'Vacation',
                'Training',
                'Team Meeting',
                'Business Requirement',
                'Shift Coverage',
                'Shift Swap',
                'Schedule Conflict',
                'Reduced Hours',
                'Extended Hours',
                'Overtime',
                'Location Change',
                'Temporary Location Assignment',
                'Business Closed',
                'Holiday Schedule',
                'Weather / Emergency',
                'Resource Availability',
                'Booking Demand Adjustment',
                'Administrative Correction',
                'Other',
            ],
        ],

        'booking-declined' => [
            'label' => 'Booking declined',
            'intro' => 'Why a requested appointment was turned down.',
            'reasons' => [
                'Requested Time Unavailable',
                'Staff Unavailable',
                'Service Provider Unavailable',
                'Service Unavailable',
                'Resource Unavailable',
                'Location Unavailable',
                'Insufficient Appointment Time',
                'Outside Business Hours',
                'Outside Staff Working Hours',
                'Client Does Not Meet Service Requirements',
                'Client Contraindication',
                'Age Restriction',
                'Consultation Required',
                'Deposit Required',
                'Payment Issue',
                'Outstanding Balance',
                'Client Account Restricted',
                'Client Blocked',
                'Repeated No Shows',
                'Repeated Late Cancellations',
                'Booking Request Incomplete',
                'Invalid Booking Information',
                'Duplicate Booking Request',
                'Business Unable to Accommodate',
                'Other',
            ],
        ],

        'service-cancellation' => [
            'label' => 'Service cancellation',
            'intro' => 'Why a service stopped being offered.',
            'reasons' => [
                'Service Discontinued',
                'Service Temporarily Unavailable',
                'Low Demand',
                'Seasonal Service Ended',
                'Staff No Longer Available',
                'Qualified Staff Unavailable',
                'Equipment Unavailable',
                'Equipment Removed',
                'Resource Unavailable',
                'Location No Longer Offers Service',
                'Product / Supply Unavailable',
                'Vendor Issue',
                'Safety Concern',
                'Regulatory Requirement',
                'Pricing Change',
                'Service Replaced',
                'Service Merged with Another Service',
                'Duplicate Service',
                'Service Created by Mistake',
                'Business Decision',
                'Rebranding / Service Menu Update',
                'Administrative Cleanup',
                'Other',
            ],
        ],
    ],
];
