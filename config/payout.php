<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Platform Fee
    |--------------------------------------------------------------------------
    |
    | Percentage of gross revenue taken as platform commission, applied on
    | each payout. Configurable via the PLATFORM_FEE_RATE environment
    | variable (e.g. 0.05 = 5%).
    |
    */

    'platform_fee_rate' => (float) env('PLATFORM_FEE_RATE', 0.05),
];
