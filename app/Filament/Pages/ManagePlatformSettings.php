<?php

namespace App\Filament\Pages;

use App\Services\PlatformSettingsService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class ManagePlatformSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $navigationLabel = 'إعدادات المنصة';

    protected static ?string $title = 'إعدادات المنصة';

    protected static string|\UnitEnum|null $navigationGroup = 'الإعدادات';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.manage-platform-settings';

    public string $contactNotificationEmails = '';

    public function mount(PlatformSettingsService $platformSettings): void
    {
        $emails = $platformSettings->contactNotificationEmails();
        $this->contactNotificationEmails = implode("\n", $emails);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('حفظ')
                ->icon('heroicon-o-check')
                ->action('saveSettings'),
        ];
    }

    public function saveSettings(PlatformSettingsService $platformSettings): void
    {
        $platformSettings->setContactNotificationEmails($this->contactNotificationEmails);

        Notification::make()
            ->title('تم حفظ الإعدادات')
            ->success()
            ->send();
    }
}
