<?php

return [
    'credit_daily_target' => (int) env('VATI_CREDIT_DAILY_TARGET', 10),

    // Full-tenure reducing-balance interest tiers, keyed by duration in months.
    // Each rate is the total interest charged over the whole tenure (e.g. 21%
    // over 6 months), accrued monthly on the outstanding principal. Durations
    // without an entry are interest-free.
    'interest_tiers' => [
        6 => 21.0,
        8 => 28.0,
        10 => 32.0,
    ],
];
