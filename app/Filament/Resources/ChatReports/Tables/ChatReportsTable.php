<?php

namespace App\Filament\Resources\ChatReports\Tables;

use App\Models\ChatReport;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ChatReportsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('_id')
                    ->label('#')
                    ->limit(8)
                    ->toggleable(isToggledHiddenByDefault: true),

                BadgeColumn::make('status')
                    ->label('الحالة')
                    ->colors([
                        'warning' => ChatReport::STATUS_PENDING,
                        'info' => ChatReport::STATUS_REVIEWED,
                        'gray' => ChatReport::STATUS_DISMISSED,
                        'danger' => ChatReport::STATUS_ACTION_TAKEN,
                    ])
                    ->sortable(),

                BadgeColumn::make('category')
                    ->label('التصنيف')
                    ->colors([
                        'warning' => 'spam',
                        'danger' => 'harassment',
                        'rose' => 'scam',
                        'gray' => 'other',
                        'amber' => 'inappropriate',
                    ]),

                TextColumn::make('reporter_id')
                    ->label('من')
                    ->formatStateUsing(function ($state) {
                        $u = \App\Models\User::find((int) $state);
                        return $u ? ($u->name . ' (#' . $u->id . ')') : ('#' . $state);
                    })
                    ->searchable(),

                TextColumn::make('reported_user_id')
                    ->label('على')
                    ->formatStateUsing(function ($state) {
                        $u = \App\Models\User::find((int) $state);
                        return $u ? ($u->name . ' (#' . $u->id . ')') : ('#' . $state);
                    }),

                TextColumn::make('post_id')
                    ->label('الإعلان')
                    ->formatStateUsing(function ($state) {
                        $p = \App\Models\Post::find((int) $state);
                        return $p ? ('#' . $p->id . ' — ' . \Illuminate\Support\Str::limit($p->title, 40)) : ('#' . $state);
                    }),

                TextColumn::make('reason')
                    ->label('السبب')
                    ->limit(60)
                    ->wrap(),

                TextColumn::make('created_at')
                    ->label('وقت البلاغ')
                    ->dateTime()
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('الحالة')
                    ->options([
                        ChatReport::STATUS_PENDING => 'قيد الانتظار',
                        ChatReport::STATUS_REVIEWED => 'تمّت المراجعة',
                        ChatReport::STATUS_DISMISSED => 'مرفوض',
                        ChatReport::STATUS_ACTION_TAKEN => 'إجراء متّخذ',
                    ]),
                SelectFilter::make('category')
                    ->label('التصنيف')
                    ->options(array_combine(ChatReport::categories(), ChatReport::categories())),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
