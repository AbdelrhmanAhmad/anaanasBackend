<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">
            إشعارات الإدارة بالبريد
        </x-slot>

        <x-slot name="description">
            أدخل عناوين البريد الإلكتروني التي تستقبل إشعاراً عند كل طلب تواصل جديد أو إعلان جديد بانتظار المراجعة (سطر لكل بريد، أو مفصولة بفاصلة).
        </x-slot>

        <div class="space-y-2">
            <label for="contactNotificationEmails" class="text-sm font-medium text-gray-950 dark:text-white">
                قائمة البريد
            </label>
            <textarea
                id="contactNotificationEmails"
                wire:model="contactNotificationEmails"
                rows="6"
                class="fi-input block w-full rounded-lg border-none bg-white px-3 py-2 text-sm text-gray-950 shadow-sm ring-1 ring-gray-950/10 transition duration-75 placeholder:text-gray-400 focus:ring-2 focus:ring-primary-600 dark:bg-white/5 dark:text-white dark:ring-white/10 dark:placeholder:text-gray-500 dark:focus:ring-primary-500"
                placeholder="support@example.com&#10;admin@example.com"
            ></textarea>
        </div>
    </x-filament::section>
</x-filament-panels::page>
