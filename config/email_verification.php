<?php

return [

    'code_length' => 6,
    'expires_minutes' => (int) env('EMAIL_VERIFICATION_EXPIRES_MINUTES', 15),
    'max_attempts' => (int) env('EMAIL_VERIFICATION_MAX_ATTEMPTS', 5),
    'resend_per_hour' => (int) env('EMAIL_VERIFICATION_RESEND_PER_HOUR', 5),
    'verify_per_hour' => (int) env('EMAIL_VERIFICATION_VERIFY_PER_HOUR', 20),

];
