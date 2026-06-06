<?php

return [
    /** Minimum minutes between consecutive ad creations per user. */
    'interval_minutes' => (int) env('POST_RATE_LIMIT_INTERVAL_MINUTES', 5),

    /** Maximum ads a user may create within the rolling hour window. */
    'hourly_max' => (int) env('POST_RATE_LIMIT_HOURLY_MAX', 5),

    /** Rolling window length in minutes for the hourly cap. */
    'hourly_window_minutes' => (int) env('POST_RATE_LIMIT_HOURLY_WINDOW_MINUTES', 60),
];
