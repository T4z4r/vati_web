<?php

return [
    'max_kilobytes' => (int) env('SIGNATURE_MAX_KILOBYTES', 2048),
    'max_pixels' => (int) env('SIGNATURE_MAX_PIXELS', 4000000),
];
