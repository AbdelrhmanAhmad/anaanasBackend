<?php

namespace App\Rules;

use App\Services\ForbiddenWords\ForbiddenWordsService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class NoForbiddenWords implements ValidationRule
{
    public function __construct(
        private readonly ?string $requestLocale = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || trim($value) === '') {
            return;
        }

        $service = app(ForbiddenWordsService::class);
        $locale = $this->requestLocale ?? app()->getLocale();

        if ($service->findMatch($value, $locale) !== null) {
            $fail($service->messageForLocale($locale));
        }
    }
}
