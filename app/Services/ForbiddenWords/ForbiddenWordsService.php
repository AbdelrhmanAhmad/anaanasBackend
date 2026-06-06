<?php

namespace App\Services\ForbiddenWords;

use App\Models\ForbiddenWord;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class ForbiddenWordsService
{
    public function __construct(
        private readonly ForbiddenTextNormalizer $normalizer,
    ) {}

    /**
     * @return array{category: string}|null
     */
    public function findMatch(?string $text, ?string $requestLocale = null): ?array
    {
        if ($text === null || trim($text) === '') {
            return null;
        }

        $normalizedHaystack = $this->normalizer->normalize($text);
        $compactHaystack = $this->normalizer->compact($text);
        $collapsedHaystack = $this->normalizer->collapseRepeats($normalizedHaystack);
        $collapsedCompact = $this->normalizer->collapseRepeats($compactHaystack);

        if ($normalizedHaystack === '' && $compactHaystack === '') {
            return null;
        }

        $minCompact = (int) config('forbidden_words.min_compact_length', 4);

        foreach ($this->activeEntries() as $entry) {
            if ($this->matchesWord($entry, $normalizedHaystack, $collapsedHaystack, $compactHaystack, $collapsedCompact, $minCompact)) {
                return ['category' => $entry->category];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array<string, string>
     */
    public function scanFields(array $fields, ?string $requestLocale = null): array
    {
        $errors = [];

        foreach ($fields as $attribute => $value) {
            if (! is_string($value) || trim($value) === '') {
                continue;
            }

            if ($this->findMatch($value, $requestLocale) !== null) {
                $errors[$attribute] = $this->messageForLocale($requestLocale);
            }
        }

        return $errors;
    }

    public function messageForLocale(?string $requestLocale = null): string
    {
        $locale = $requestLocale ?? app()->getLocale();
        if (! in_array($locale, ['ar', 'en'], true)) {
            $locale = 'en';
        }

        return $locale === 'ar'
            ? 'يحتوي النص على كلمات غير مسموح بنشرها وفق سياسة المنصة.'
            : 'This text contains words that are not allowed per platform policy.';
    }

    public function flushCache(): void
    {
        Cache::forget(config('forbidden_words.cache_key', 'forbidden_words.active.v3'));
    }

    /**
     * @return Collection<int, ForbiddenWord>
     */
    private function activeEntries(): Collection
    {
        return Cache::remember(
            config('forbidden_words.cache_key', 'forbidden_words.active.v3'),
            (int) config('forbidden_words.cache_ttl', 3600),
            fn () => ForbiddenWord::query()
                ->where('is_active', true)
                ->orderByRaw('CHAR_LENGTH(word) DESC')
                ->get()
        );
    }

    private function matchesWord(
        ForbiddenWord $entry,
        string $normalizedHaystack,
        string $collapsedHaystack,
        string $compactHaystack,
        string $collapsedCompact,
        int $minCompact,
    ): bool {
        $needle = $this->normalizer->normalize($entry->word);
        if ($needle === '') {
            return false;
        }

        $parts = preg_split('/\s+/u', $needle, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($parts === []) {
            return false;
        }

        foreach ($parts as $part) {
            if (! $this->partMatches($part, $normalizedHaystack, $collapsedHaystack, $compactHaystack, $collapsedCompact, $minCompact)) {
                return false;
            }
        }

        return true;
    }

    private function partMatches(
        string $part,
        string $normalizedHaystack,
        string $collapsedHaystack,
        string $compactHaystack,
        string $collapsedCompact,
        int $minCompact,
    ): bool {
        if ($this->containsWholeToken($normalizedHaystack, $part)) {
            return true;
        }

        if ($this->containsWholeToken($collapsedHaystack, $this->normalizer->collapseRepeats($part))) {
            return true;
        }

        $compactPart = $this->normalizer->compact($part);
        if (mb_strlen($compactPart) >= $minCompact) {
            if (str_contains($compactHaystack, $compactPart)) {
                return true;
            }
            if (str_contains($collapsedCompact, $this->normalizer->collapseRepeats($compactPart))) {
                return true;
            }
        }

        return false;
    }

    private function containsWholeToken(string $haystack, string $needle): bool
    {
        if ($needle === '' || $haystack === '') {
            return false;
        }

        $pattern = '/(?<![\p{L}\p{N}])' . preg_quote($needle, '/') . '(?![\p{L}\p{N}])/u';

        return (bool) preg_match($pattern, $haystack);
    }
}
