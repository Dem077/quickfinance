<?php

namespace App\Filament\Admin\Resources\UserResource\Pages;

use App\Filament\Admin\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function beforeSave(): void
    {
        // if (($this->data['is_hod'] ?? false) == true) {
        //     $duplicate = \App\Models\User::where('hod_of', $this->data['department_id'])
        //         ->where('id', '<>', $this->record->id)
        //         ->exists();
    
        //     if ($duplicate) {
        //         \Filament\Notifications\Notification::make()
        //             ->title('HOD already assigned for this department')
        //             ->danger()
        //             ->send();
    
        //         throw \Illuminate\Validation\ValidationException::withMessages([
        //             'is_hod' => 'HOD already assigned for this department.',
        //         ]);
        //     }
        // }
    }

    protected function afterSave(): void
    {
        // if ($this->record->is_hod == true) {
        //     $this->record->update([
        //         'hod_of' => $this->record->department_id,
        //     ]);
        // }
        // else {
        //     $this->record->update([
        //         'hod_of' => null,
        //     ]);
        // }
    }
}
