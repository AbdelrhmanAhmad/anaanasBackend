<?php

namespace App\Filament\Support;

use App\Models\Post;
use App\Models\PostData;

class PostDataInfolistFormatter
{
    public static function locale(): string
    {
        $loc = app()->getLocale();

        return in_array($loc, ['ar', 'en'], true) ? $loc : 'ar';
    }

    public static function pickLocalized(mixed $name, ?string $locale = null): string
    {
        $locale ??= self::locale();

        if (is_string($name)) {
            $trimmed = trim($name);

            return $trimmed !== '' ? $trimmed : '—';
        }

        if (! is_array($name)) {
            return '—';
        }

        foreach ([$locale, 'ar', 'en'] as $key) {
            if (! empty($name[$key]) && is_string($name[$key])) {
                return trim($name[$key]);
            }
        }

        foreach ($name as $value) {
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return '—';
    }

    public static function fetchDocument(Post $post): ?array
    {
        try {
            $doc = PostData::query()->where('post_id', (int) $post->id)->first();
            if (! $doc) {
                return null;
            }

            $arr = $doc->toArray();
            unset($arr['_id']);

            return $arr;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, string>
     */
    public static function attributesKeyValue(Post $post): array
    {
        $doc = self::fetchDocument($post);
        if (! $doc) {
            return [];
        }

        $rows = $doc['attributes_and_options'] ?? [];
        if (! is_array($rows) || $rows === []) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $attr = $row['attribute'] ?? null;
            if (! is_array($attr)) {
                continue;
            }

            $label = self::pickLocalized($attr['name'] ?? null);
            if ($label === '—' && isset($attr['id'])) {
                $label = __('Attribute').' #'.$attr['id'];
            }

            $value = self::formatRowValue($row);
            $out[$label] = $value !== '' && $value !== '—' ? $value : '—';
        }

        return $out;
    }

    /**
     * @return array<string, string>
     */
    public static function metadataKeyValue(Post $post): array
    {
        $doc = self::fetchDocument($post);
        if (! $doc) {
            return [];
        }

        $out = [];

        $scalarKeys = [
            'post_id' => __('Post ID'),
            'user_id' => __('User ID'),
            'section_id' => __('Section ID'),
            'category_id' => __('Category ID'),
            'country_id' => __('Country ID'),
            'city_id' => __('City ID'),
        ];

        foreach ($scalarKeys as $key => $label) {
            if (array_key_exists($key, $doc) && $doc[$key] !== null && $doc[$key] !== '') {
                $out[$label] = (string) $doc[$key];
            }
        }

        $user = $doc['user'] ?? null;
        if (is_array($user)) {
            if (! empty($user['name'])) {
                $out[__('Publisher')] = (string) $user['name'];
            }
            if (! empty($user['mobile'])) {
                $out[__('Mobile')] = (string) $user['mobile'];
            }
            if (! empty($user['email'])) {
                $out[__('Email')] = (string) $user['email'];
            }
        }

        foreach (['created_at', 'updated_at'] as $key) {
            if (empty($doc[$key])) {
                continue;
            }
            $out[__($key === 'created_at' ? 'Created at' : 'Updated at')] = is_string($doc[$key])
                ? $doc[$key]
                : json_encode($doc[$key], JSON_UNESCAPED_UNICODE);
        }

        $rawAttributes = $doc['attributes'] ?? null;
        if (is_array($rawAttributes) && $rawAttributes !== []) {
            $out[__('Raw attribute IDs')] = json_encode(
                $rawAttributes,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            );
        }

        return $out;
    }

    protected static function formatRowValue(array $row): string
    {
        if (! empty($row['options']) && is_array($row['options'])) {
            $parts = [];
            foreach ($row['options'] as $opt) {
                if (! is_array($opt)) {
                    continue;
                }
                $label = self::pickLocalized($opt['name'] ?? null);
                if ($label !== '—') {
                    $parts[] = $label;
                }
            }

            return implode('، ', $parts);
        }

        $opt = $row['option'] ?? null;
        if (is_array($opt)) {
            return self::pickLocalized($opt['name'] ?? null);
        }

        return '—';
    }
}
