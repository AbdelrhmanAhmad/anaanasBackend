<?php

namespace App\Services\ForbiddenWords;

final class ForbiddenTextNormalizer
{
    private const AR_DIACRITICS = '/[\x{0610}-\x{061A}\x{064B}-\x{065F}\x{0670}\x{06D6}-\x{06DC}\x{06DF}-\x{06E4}\x{06E7}\x{06E8}\x{06EA}-\x{06ED}]/u';

    /** @var array<string, string> */
    private const LEET_MAP = [
        '0' => 'o',
        '1' => 'i',
        '3' => 'e',
        '4' => 'a',
        '5' => 's',
        '7' => 't',
        '8' => 'b',
        '@' => 'a',
        '$' => 's',
        '€' => 'e',
    ];

    public function normalize(?string $text): string
    {
        if ($text === null || $text === '') {
            return '';
        }

        $text = mb_strtolower(trim($text));
        $text = preg_replace(self::AR_DIACRITICS, '', $text) ?? $text;
        $text = str_replace('ـ', '', $text);
        $text = str_replace(['أ', 'إ', 'آ', 'ٱ', 'ؤ', 'ئ'], ['ا', 'ا', 'ا', 'ا', 'و', 'ي'], $text);
        $text = str_replace(['ة', 'ى'], ['ه', 'ي'], $text);
        $text = str_replace(array_keys(self::LEET_MAP), array_values(self::LEET_MAP), $text);
        $text = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    /** نسخة مدمجة بدون مسافات/رموز — لتجاوز "ح ش ي ش" */
    public function compact(?string $text): string
    {
        $normalized = $this->normalize($text);

        return preg_replace('/[\s\p{P}\p{S}]+/u', '', $normalized) ?? '';
    }

    public function collapseRepeats(string $text): string
    {
        return preg_replace('/(.)\1{2,}/u', '$1$1', $text) ?? $text;
    }
}
