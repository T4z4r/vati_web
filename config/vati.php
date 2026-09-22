<?php

return [
    'credit_daily_target' => (int) env('VATI_CREDIT_DAILY_TARGET', 10),

    // Reducing-balance interest factors (not percentages), keyed by duration in
    // months. Each factor is the monthly rate charged on the outstanding
    // principal (e.g. 0.036 = 3.6% per month over 8 months); weekly repayment
    // periods receive a proportional rate. Durations without an entry are
    // interest-free.
    'interest_tiers' => [
        6 => 0.445,
        8 => 0.036,
        10 => 0.0295,
    ],
];
