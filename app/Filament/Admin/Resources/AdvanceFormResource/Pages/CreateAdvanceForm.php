<?php

namespace App\Filament\Admin\Resources\AdvanceFormResource\Pages;

use App\Filament\Admin\Resources\AdvanceFormResource;
use App\Models\AdvanceForm;
use Filament\Resources\Pages\CreateRecord;

class CreateAdvanceForm extends CreateRecord
{
    protected static string $resource = AdvanceFormResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $count = 1157 + 1;

        do {
            $request_number = sprintf('LADV/PROC/%04d', $count);
            $exists = AdvanceForm::where('request_number', $request_number)->exists();
            if ($exists) {
                $count++;
            }
        } while ($exists);

        $data['request_number'] = $request_number;

        return $data;
    }
}
