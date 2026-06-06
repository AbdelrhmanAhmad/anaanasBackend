<?php

namespace App\Services;

use Illuminate\Http\Request;

class ContactFormGuardService
{
    public function isSuspicious(Request $request): bool
    {
        $honeypotField = (string) config('contact.honeypot.field', 'company_website');
        if ($request->filled($honeypotField)) {
            return true;
        }

        $timestampField = (string) config('contact.honeypot.timestamp_field', '_form_ts');
        $submittedAt = (int) $request->input($timestampField, 0);
        $minSeconds = max(1, (int) config('contact.honeypot.min_seconds', 3));

        if ($submittedAt <= 0) {
            return true;
        }

        $elapsed = time() - $submittedAt;

        return $elapsed < $minSeconds;
    }
}
