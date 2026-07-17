<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Low stock threshold
    |--------------------------------------------------------------------------
    |
    | Variants with available quantity greater than zero but less than or equal
    | to this value are flagged as low stock in the admin panel.
    |
    */
    'low_stock_threshold' => (int) env('LOW_STOCK_THRESHOLD', 5),

];
