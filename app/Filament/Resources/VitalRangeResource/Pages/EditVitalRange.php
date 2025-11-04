<?php

namespace App\Filament\Resources\VitalRangeResource\Pages;

use App\Filament\Resources\VitalRangeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Forms;

class EditVitalRange extends EditRecord
{
    protected static string $resource = VitalRangeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    public function form(Forms\Form $form): Forms\Form
    {
        return parent::form($form)
            ->modify('parameter', function (Forms\Components\Select $component) {
                return $component->rules([
                    'required',
                    function ($get) {
                        return function (string $attribute, $value, \Closure $fail) {
                            $exists = \App\Models\VitalRange::where('parameter', $value)
                                ->where('id', '!=', $this->record->id)
                                ->exists();
                            if ($exists) {
                                $fail('A vital range with this parameter already exists.');
                            }
                        };
                    },
                ]);
            });
    }
}
