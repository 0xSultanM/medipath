<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Domain enums → presentation labels
    |--------------------------------------------------------------------------
    | Labels are resolved on the server and passed to React as props so no
    | UI component hardcodes a role / status / specialty string (G6, P10).
    | The values themselves are persisted in DB enums/columns.
    */
    'roles' => [
        'admin' => [
            'label' => 'Admin',
            'portal' => 'Administration',
            'route' => 'admin.dashboard',
            'icon' => '🛡️',
        ],
        'doctor' => [
            'label' => 'Doctor',
            'portal' => 'Doctor workspace',
            'route' => 'doctor.dashboard',
            'icon' => '🩺',
        ],
        'pharmacist' => [
            'label' => 'Pharmacist',
            'portal' => 'Pharmacy',
            'route' => 'pharmacist.dashboard',
            'icon' => '💊',
        ],
        'patient' => [
            'label' => 'Patient',
            'portal' => 'Patient portal',
            'route' => 'patient.dashboard',
            'icon' => '🧑',
        ],
    ],

    'role_values' => ['admin', 'doctor', 'pharmacist', 'patient'],

    'account_statuses' => [
        'pending' => ['label' => 'Pending', 'tone' => 'warning'],
        'approved' => ['label' => 'Approved', 'tone' => 'success'],
        'rejected' => ['label' => 'Rejected', 'tone' => 'danger'],
        'suspended' => ['label' => 'Suspended', 'tone' => 'danger-outline'],
        'archived' => ['label' => 'Archived', 'tone' => 'neutral'],
    ],

    'verification_statuses' => [
        'unverified' => ['label' => 'Unverified', 'tone' => 'warning'],
        'pending' => ['label' => 'Under review', 'tone' => 'info'],
        'verified' => ['label' => 'Verified', 'tone' => 'success'],
        'resubmission' => ['label' => 'Resubmission requested', 'tone' => 'warning'],
        'revoked' => ['label' => 'Verification revoked', 'tone' => 'danger'],
    ],

    'appointment_statuses' => [
        'pending' => ['label' => 'Pending', 'tone' => 'warning', 'active' => true],
        'accepted' => ['label' => 'Accepted', 'tone' => 'primary', 'active' => true],
        'rejected' => ['label' => 'Rejected', 'tone' => 'danger', 'active' => false],
        'cancelled' => ['label' => 'Cancelled', 'tone' => 'neutral', 'active' => false],
        'completed' => ['label' => 'Completed', 'tone' => 'success', 'active' => false],
        'no_show' => ['label' => 'No show', 'tone' => 'neutral', 'active' => false],
    ],

    'prescription_statuses' => [
        'draft' => ['label' => 'Draft', 'tone' => 'neutral'],
        'pending_dispense' => ['label' => 'Pending dispense', 'tone' => 'warning'],
        'dispensed' => ['label' => 'Dispensed', 'tone' => 'success'],
        'cancelled' => ['label' => 'Cancelled', 'tone' => 'neutral'],
    ],

    'payment_statuses' => [
        'pending' => ['label' => 'Payment pending', 'tone' => 'warning'],
        'paid' => ['label' => 'Paid', 'tone' => 'success'],
        'refunded' => ['label' => 'Refunded', 'tone' => 'info'],
    ],

    'payment_methods' => [
        'cash' => 'Cash',
        'card' => 'Card',
        'mobile_banking' => 'Mobile banking',
        'insurance' => 'Insurance',
        'bank_transfer' => 'Bank transfer',
    ],

    'moderation_statuses' => [
        'pending' => ['label' => 'Pending moderation', 'tone' => 'warning'],
        'approved' => ['label' => 'Published', 'tone' => 'success'],
        'rejected' => ['label' => 'Rejected', 'tone' => 'danger'],
    ],

    'stock_statuses' => [
        'healthy' => ['label' => 'Healthy', 'tone' => 'success'],
        'low' => ['label' => 'Low stock', 'tone' => 'warning'],
        'critical' => ['label' => 'Critical stock', 'tone' => 'danger'],
        'out_of_stock' => ['label' => 'Out of stock', 'tone' => 'danger-outline'],
    ],

    'expiry_statuses' => [
        'ok' => ['label' => 'OK', 'tone' => 'success'],
        'upcoming' => ['label' => 'Expiring ≤90 days', 'tone' => 'warning'],
        'critical' => ['label' => 'Expiring ≤30 days', 'tone' => 'danger'],
        'expired' => ['label' => 'Expired', 'tone' => 'neutral-strong'],
    ],

    'interaction_severities' => [
        'minor' => ['label' => 'Minor interaction', 'tone' => 'info'],
        'moderate' => ['label' => 'Moderate interaction', 'tone' => 'warning'],
        'major' => ['label' => 'Major interaction', 'tone' => 'danger'],
        'contraindicated' => ['label' => 'Contraindicated', 'tone' => 'danger'],
    ],

    'allergy_severities' => [
        'mild' => ['label' => 'Mild', 'tone' => 'info'],
        'moderate' => ['label' => 'Moderate', 'tone' => 'warning'],
        'severe' => ['label' => 'Severe', 'tone' => 'danger'],
        'life_threatening' => ['label' => 'Life-threatening', 'tone' => 'emergency'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Currency / locale (seeded into system_settings; G1)
    |--------------------------------------------------------------------------
    */
    'currency' => [
        'code' => env('MEDIPATH_DEFAULT_CURRENCY', 'BDT'),
        // Symbol is resolved at runtime by intl NumberFormatter in the closest
        // native locale (never hardcoded in UI). Falls back to the ISO code.
        'symbol_source' => 'intl',
        'symbol_locales' => [
            'BDT' => 'bn_BD',
            'USD' => 'en_US',
            'EUR' => 'en_IE',
            'GBP' => 'en_GB',
            'INR' => 'en_IN',
            'PKR' => 'en_PK',
            'SAR' => 'ar_SA',
            'AED' => 'ar_AE',
            'JPY' => 'ja_JP',
            'MYR' => 'ms_MY',
            'SGD' => 'en_SG',
        ],
        'fraction_digits' => 2,
    ],

    'locale' => [
        'default' => env('APP_LOCALE', 'en'),
        'supported' => ['en', 'bn'],
        'timezone' => env('APP_TIMEZONE', 'Asia/Dhaka'),
        'date_formats' => [
            'd M Y' => '23 Nov 2026',
            'd/m/Y' => '23/11/2026',
            'Y-m-d' => '2026-11-23',
            'M j, Y' => 'Nov 23, 2026',
        ],
        'date_format' => 'd M Y',
        'time_format' => 'g:i A',
    ],

    'pagination' => [
        'default' => 15,
        'options' => [10, 15, 25, 50],
        'max' => 100,
    ],

    'uploads' => [
        'credential_max_kb' => 5120,
        'credential_mimes' => ['pdf', 'jpg', 'jpeg', 'png'],
        'avatar_max_kb' => 2048,
    ],

    'registration' => [
        'license_authorities' => [
            'Bangladesh Medical & Dental Council',
            'Pharmacy Council of Bangladesh',
            'Bangladesh Nursing & Midwifery Council',
            'Dental Council of Bangladesh',
        ],
        'genders' => ['Prefer not to say', 'Female', 'Male', 'Other'],
        'max_per_hour' => 10,
    ],

    'security' => [
        'login_max_attempts' => 5,
        'login_decay_seconds' => 60,
        'password_min' => 10,
        'reset_token_ttl_minutes' => 30,
        'admin_idle_minutes' => 15,
        'session_idle_minutes' => 120,
    ],

    'demo' => [
        'password' => env('MEDIPATH_DEMO_PASSWORD', 'MediPath#2026'),
    ],
];
