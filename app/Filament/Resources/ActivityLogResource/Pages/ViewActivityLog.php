<?php

namespace App\Filament\Resources\ActivityLogResource\Pages;

use App\Filament\Resources\ActivityLogResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;

class ViewActivityLog extends ViewRecord
{
    protected static string $resource = ActivityLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Logs are read-only
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Log Information')
                    ->schema([
                        TextEntry::make('logged_at')
                            ->label('Logged At')
                            ->dateTime('M j, Y g:i A'),
                        TextEntry::make('log_type')
                            ->label('Log Type')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'activity' => 'primary',
                                'audit' => 'warning',
                                'error' => 'danger',
                                'system' => 'info',
                                default => 'gray',
                            }),
                        TextEntry::make('event')
                            ->label('Event')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'created' => 'success',
                                'updated' => 'info',
                                'deleted' => 'danger',
                                'viewed' => 'gray',
                                'login' => 'success',
                                'logout' => 'gray',
                                default => 'primary',
                            }),
                        TextEntry::make('level')
                            ->label('Level')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'debug' => 'gray',
                                'info' => 'info',
                                'warning' => 'warning',
                                'error' => 'danger',
                                'critical' => 'danger',
                                default => 'gray',
                            }),
                        TextEntry::make('description')
                            ->label('Description')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('User & Context')
                    ->schema([
                        TextEntry::make('user.name')
                            ->label('User')
                            ->default('System'),
                        TextEntry::make('branch.name')
                            ->label('Branch'),
                        TextEntry::make('ip_address')
                            ->label('IP Address'),
                        TextEntry::make('user_agent')
                            ->label('User Agent')
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->collapsible(),

                Section::make('Subject Information')
                    ->schema([
                        TextEntry::make('subject_type')
                            ->label('Subject Type')
                            ->formatStateUsing(fn (?string $state): string => $state ? class_basename($state) : 'N/A'),
                        TextEntry::make('subject_id')
                            ->label('Subject ID'),
                        TextEntry::make('subject.name')
                            ->label('Subject Name')
                            ->default('N/A')
                            ->visible(fn ($record) => $record->subject_type && $record->subject),
                    ])
                    ->columns(2)
                    ->collapsible()
                    ->visible(fn ($record) => $record->subject_type),

                Section::make('Properties')
                    ->schema([
                        TextEntry::make('properties')
                            ->label('Properties')
                            ->formatStateUsing(fn ($state) => $state ? json_encode($state, JSON_PRETTY_PRINT) : 'No properties')
                            ->columnSpanFull()
                            ->monospace()
                            ->placeholder('No properties'),
                    ])
                    ->collapsible()
                    ->collapsed()
                    ->visible(fn ($record) => !empty($record->properties)),

                Section::make('Context')
                    ->schema([
                        TextEntry::make('context')
                            ->label('Context')
                            ->formatStateUsing(fn ($state) => $state ? json_encode($state, JSON_PRETTY_PRINT) : 'No context')
                            ->columnSpanFull()
                            ->monospace()
                            ->placeholder('No context'),
                    ])
                    ->collapsible()
                    ->collapsed()
                    ->visible(fn ($record) => !empty($record->context)),
            ]);
    }
}
