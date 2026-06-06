<?php

namespace App\Filament\Resources\AccountVerificationRequests;

use App\Filament\Resources\AccountVerificationRequests\Pages\ManageAccountVerificationRequests;
use App\Models\AccountVerificationRequest;
use App\Services\AccountVerificationService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class AccountVerificationRequestResource extends Resource
{
    protected static ?string $model = AccountVerificationRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?string $navigationLabel = 'طلبات توثيق الحساب';

    protected static ?string $modelLabel = 'طلب توثيق';

    protected static ?string $pluralModelLabel = 'طلبات توثيق الحساب';

    protected static string|\UnitEnum|null $navigationGroup = 'الإشراف';

    protected static ?int $navigationSort = 15;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Textarea::make('admin_notes')
                ->label('ملاحظات الإدارة')
                ->rows(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('id')
                    ->label('#')
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('المستخدم')
                    ->searchable(['users.name', 'users.email', 'users.mobile'])
                    ->description(fn (AccountVerificationRequest $record) => '#'.$record->user_id),
                TextColumn::make('user.email')
                    ->label('البريد')
                    ->toggleable(),
                TextColumn::make('user.mobile')
                    ->label('الجوال')
                    ->toggleable(),
                TextColumn::make('message')
                    ->label('رسالة المستخدم')
                    ->limit(60)
                    ->wrap()
                    ->placeholder('—'),
                TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        AccountVerificationRequest::STATUS_PENDING => 'warning',
                        AccountVerificationRequest::STATUS_APPROVED => 'success',
                        AccountVerificationRequest::STATUS_REJECTED => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        AccountVerificationRequest::STATUS_PENDING => 'قيد المراجعة',
                        AccountVerificationRequest::STATUS_APPROVED => 'مقبول',
                        AccountVerificationRequest::STATUS_REJECTED => 'مرفوض',
                        default => $state,
                    }),
                TextColumn::make('created_at')
                    ->label('تاريخ الطلب')
                    ->dateTime()
                    ->since()
                    ->sortable(),
                TextColumn::make('reviewed_at')
                    ->label('تاريخ المراجعة')
                    ->dateTime()
                    ->since()
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('الحالة')
                    ->options([
                        AccountVerificationRequest::STATUS_PENDING => 'قيد المراجعة',
                        AccountVerificationRequest::STATUS_APPROVED => 'مقبول',
                        AccountVerificationRequest::STATUS_REJECTED => 'مرفوض',
                    ]),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label('موافقة')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (AccountVerificationRequest $record) => $record->status === AccountVerificationRequest::STATUS_PENDING)
                    ->form([
                        Textarea::make('admin_notes')
                            ->label('ملاحظات (اختياري)')
                            ->rows(2),
                    ])
                    ->action(function (AccountVerificationRequest $record, array $data) {
                        app(AccountVerificationService::class)->approve(
                            $record,
                            (int) Auth::id(),
                            $data['admin_notes'] ?? null,
                        );
                    })
                    ->requiresConfirmation()
                    ->modalHeading('تأكيد توثيق الحساب')
                    ->modalDescription('سيصبح المستخدم قادراً على النشر بدون قيود الانتظار.'),
                Action::make('reject')
                    ->label('رفض')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (AccountVerificationRequest $record) => $record->status === AccountVerificationRequest::STATUS_PENDING)
                    ->form([
                        Textarea::make('admin_notes')
                            ->label('سبب الرفض (اختياري)')
                            ->rows(2),
                    ])
                    ->action(function (AccountVerificationRequest $record, array $data) {
                        app(AccountVerificationService::class)->reject(
                            $record,
                            (int) Auth::id(),
                            $data['admin_notes'] ?? null,
                        );
                    })
                    ->requiresConfirmation(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageAccountVerificationRequests::route('/'),
        ];
    }
}
