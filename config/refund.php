<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Automated Refund Processing
    |--------------------------------------------------------------------------
    |
    | Refunds are settled by hand by default. Turning this on lets the queue
    | worker settle QRIS refunds through the Midtrans Refund API instead, and
    | leaves every other channel on the manual path as a fallback. It moves
    | real money, so it is opt-in per environment.
    |
    */

    'automation_enabled' => (bool) env('REFUND_AUTOMATION_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Cancellation Batch Size
    |--------------------------------------------------------------------------
    |
    | Cancelling an event can open thousands of refunds at once. The queue job
    | settles this many orders per run and then queues the next slice, so no
    | single run becomes one huge transaction that a worker timeout kills.
    |
    */

    'cancellation_slice' => (int) env('REFUND_CANCELLATION_SLICE', 250),

];
