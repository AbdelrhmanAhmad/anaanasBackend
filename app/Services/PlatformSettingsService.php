<?php

namespace App\Services;

use App\Models\PlatformSetting;

class PlatformSettingsService
{
    public const KEY_CONTACT_NOTIFICATION_EMAILS = 'contact_notification_emails';

    public function get(string $key, mixed $default = null): mixed
    {
        $row = PlatformSetting::query()->where('key', $key)->first();

        return $row?->value ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        PlatformSetting::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value],
        );
    }

    /**
     * @return list<string>
     */
    public function contactNotificationEmails(): array
    {
        $raw = $this->get(self::KEY_CONTACT_NOTIFICATION_EMAILS, []);

        if (is_string($raw)) {
            $raw = preg_split('/[\s,;]+/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        }

        if (! is_array($raw)) {
            return [];
        }

        $emails = [];
        foreach ($raw as $entry) {
            $email = trim((string) $entry);
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $emails[] = $email;
            }
        }

        return array_values(array_unique($emails));
    }

    /**
     * @param  list<string>|string  $emails
     */
    public function setContactNotificationEmails(array|string $emails): void
    {
        if (is_string($emails)) {
            $emails = preg_split('/[\s,;]+/', $emails, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        }

        $normalized = [];
        foreach ($emails as $entry) {
            $email = trim((string) $entry);
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $normalized[] = $email;
            }
        }

        $this->set(self::KEY_CONTACT_NOTIFICATION_EMAILS, array_values(array_unique($normalized)));
    }
}
