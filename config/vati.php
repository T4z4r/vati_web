<?php

return [
    'credit_daily_target' => (int) env('VATI_CREDIT_DAILY_TARGET', 10),

    // Flat interest factors (not percentages), keyed by duration in months.
    // Each factor is the total interest charged over the whole tenure as a
    // factor of the principal (e.g. 0.036 = 3.6% interest for an 8-month loan).
    // Weekly interest/repayment factor amount spreads the same factor evenly
    // across the installments (weekly = principal * factor / number of weeks).
    // Durations without an entry are interest-free.
    'interest_tiers' => [
        6 => 0.0445,
        8 => 0.036,
        10 => 0.0295,
    ],

    // Custom weeks per duration mapping for weekly repayment loans.
    // Overrides the default 4 weeks/month calculation.
    'installment_weeks' => [
        6 => 25,
        8 => 32,
        10 => 40,
        12 => 50,
    ],
];
