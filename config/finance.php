<?php

return [
    'timezone' => 'Asia/Manila',

    'payment_channels' => [
        'gcash' => [
            'label' => 'GCash',
            'short_label' => 'G',
            'logo' => 'images/mode_of_payments/GCASH.png',
            'account_name' => 'CABEL NURHASAN',
            'accounts' => [
                ['label' => 'GCash number', 'number' => '0927 299 1833'],
            ],
        ],
        'maya' => [
            'label' => 'Maya',
            'short_label' => 'M',
            'logo' => 'images/mode_of_payments/MAYA.png',
            'account_name' => 'CABEL NURHASAN',
            'accounts' => [
                ['label' => 'Account number', 'number' => '0927 299 1833'],
            ],
        ],
        'bdo' => [
            'label' => 'BDO',
            'short_label' => 'B',
            'logo' => 'images/mode_of_payments/BDO.png',
            'swift_code' => 'BNORPHMM',
            'branch' => 'Woodlane Diversion Road – Davao City',
            'accounts' => [
                ['label' => 'Savings account', 'number' => '010478011996', 'name' => 'AL MUNAWWARA ISLAMIC SCHOOL INC.'],
                ['label' => 'Current account', 'number' => '010478008782', 'name' => 'CABEL B. NURHASAN'],
                ['label' => 'Savings account', 'number' => '010470022817', 'name' => 'CABEL NURHASAN'],
                ['label' => 'Savings account', 'number' => '010470099925', 'name' => 'WARDAH D. PINDATON or JAMELLA P. MOHAMAD'],
                ['label' => 'Savings account', 'number' => '010470105712', 'name' => 'JAMELLA P. MOHAMAD or WARDAH D. PINDATON'],
            ],
        ],
    ],

    'payment_support_email' => 'amisfinance2324@gmail.com',

    /*
    |--------------------------------------------------------------------------
    | School Year Installment Configurations
    |--------------------------------------------------------------------------
    |
    | Define the months list and parameters for monthly installments per SY.
    | Offset indicates whether a month falls in the second calendar year.
    | E.g. July (offset 0 -> 2026), March (offset 1 -> 2027)
    |
    */
    'school_years' => [
        '2026-2027' => [
            'installment_months' => [
                ['name' => 'July',      'day' => 15, 'year_offset' => 0],
                ['name' => 'August',    'day' => 15, 'year_offset' => 0],
                ['name' => 'September', 'day' => 15, 'year_offset' => 0],
                ['name' => 'October',   'day' => 15, 'year_offset' => 0],
                ['name' => 'November',  'day' => 15, 'year_offset' => 0],
                ['name' => 'December',  'day' => 15, 'year_offset' => 0],
                ['name' => 'January',   'day' => 15, 'year_offset' => 1],
                ['name' => 'February',  'day' => 15, 'year_offset' => 1],
                ['name' => 'March',     'day' => 15, 'year_offset' => 1],
            ],
        ],
        '2025-2026' => [
            'installment_months' => [
                ['name' => 'July',      'day' => 15, 'year_offset' => 0],
                ['name' => 'August',    'day' => 15, 'year_offset' => 0],
                ['name' => 'September', 'day' => 15, 'year_offset' => 0],
                ['name' => 'October',   'day' => 15, 'year_offset' => 0],
                ['name' => 'November',  'day' => 15, 'year_offset' => 0],
                ['name' => 'December',  'day' => 15, 'year_offset' => 0],
                ['name' => 'January',   'day' => 15, 'year_offset' => 1],
                ['name' => 'February',  'day' => 15, 'year_offset' => 1],
                ['name' => 'March',     'day' => 15, 'year_offset' => 1],
            ],
        ],
    ],
];
