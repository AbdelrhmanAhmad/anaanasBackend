<?php

namespace App\Filament\Resources\ContactRequests;

use App\Filament\Resources\ContactRequests\Pages\ManageContactRequests;
use App\Models\ContactRequest;
use App\Services\ContactRequestService;
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

class ContactRequestResource extends Resource
{
    protected static ?string $model = ContactRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static ?string $navigationLabel = 'طلبات تواصل معنا';

    protected static ?string $modelLabel = 'طلب تواصل';

    protected static ?string $pluralModelLabel = 'طلبات تواصل معنا';

    protected static string|\UnitEnum|null $navigationGroup = 'الإشراف';

    protected static ?int $navigationSort = 12;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Textarea::make('admin_notes')
                ->label('ملاحظات الإدارة')
                ->rows(4)
                ->columnSpanFull(),
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
                TextColumn::make('name')
                    ->label('الاسم')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('البريد')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('subject')
                    ->label('الموضوع')
                    ->searchable()
                    ->limit(40)
                    ->wrap(),
                TextColumn::make('message')
                    ->label('الرسالة')
                    ->limit(60)
                    ->wrap()
                    ->toggleable(),
                TextColumn::make('user.name')
                    ->label('المستخدم')
                    ->placeholder('زائر')
                    ->toggleable(),
                TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        ContactRequest::STATUS_PENDING => 'warning',
                        ContactRequest::STATUS_IN_PROGRESS => 'info',
                        ContactRequest::STATUS_RESOLVED => 'success',
                        ContactRequest::STATUS_CLOSED => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        ContactRequest::STATUS_PENDING => 'جديد',
                        ContactRequest::STATUS_IN_PROGRESS => 'قيد المعالجة',
                        ContactRequest::STATUS_RESOLVED => 'تم الحل',
                        ContactRequest::STATUS_CLOSED => 'مغلق',
                        default => $state,
                    }),
                TextColumn::make('created_at')
                    ->label('تاريخ الإرسال')
                    ->dateTime()
                    ->since()
                    ->sortable(),
                TextColumn::make('handled_at')
                    ->label('آخر تحديث')
                    ->dateTime()
                    ->since()
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('الحالة')
                    ->options([
                        ContactRequest::STATUS_PENDING => 'جديد',
                        ContactRequest::STATUS_IN_PROGRESS => 'قيد المعالجة',
                        ContactRequest::STATUS_RESOLVED => 'تم الحل',
                        ContactRequest::STATUS_CLOSED => 'مغلق',
                    ]),
            ])
            ->recordActions([
                Action::make('in_progress')
                    ->label('قيد المعالجة')
                    ->icon('heroicon-o-arrow-path')
                    ->color('info')
                    ->visible(fn (ContactRequest $record) => in_array($record->status, [
                        ContactRequest::STATUS_PENDING,
                        ContactRequest::STATUS_CLOSED,
                        ContactRequest::STATUS_RESOLVED,
                    ], true))
                    ->form([
                        Textarea::make('admin_notes')
                            ->label('ملاحظات (اختياري)')
                            ->rows(2),
                    ])
                    ->action(function (ContactRequest $record, array $data) {
                        app(ContactRequestService::class)->updateStatus(
                            $record,
                            ContactRequest::STATUS_IN_PROGRESS,
                            (int) Auth::id(),
                            $data['admin_notes'] ?? null,
                        );
                    }),
                Action::make('resolved')
                    ->label('تم الحل')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (ContactRequest $record) => $record->status !== ContactRequest::STATUS_RESOLVED)
                    ->form([
                        Textarea::make('admin_notes')
                            ->label('ملاحظات (اختياري)')
                            ->rows(2),
                    ])
                    ->action(function (ContactRequest $record, array $data) {
                        app(ContactRequestService::class)->updateStatus(
                            $record,
                            ContactRequest::STATUS_RESOLVED,
                            (int) Auth::id(),
                            $data['admin_notes'] ?? null,
                        );
                    }),
                Action::make('closed')
                    ->label('إغلاق')
                    ->icon('heroicon-o-x-circle')
                    ->color('gray')
                    ->visible(fn (ContactRequest $record) => $record->status !== ContactRequest::STATUS_CLOSED)
                    ->requiresConfirmation()
                    ->form([
                        Textarea::make('admin_notes')
                            ->label('سبب الإغلاق (اختياري)')
                            ->rows(2),
                    ])
                    ->action(function (ContactRequest $record, array $data) {
                        app(ContactRequestService::class)->updateStatus(
                            $record,
                            ContactRequest::STATUS_CLOSED,
                            (int) Auth::id(),
                            $data['admin_notes'] ?? null,
                        );
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageContactRequests::route('/'),
        ];
    }
}
