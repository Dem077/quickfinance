<?php

namespace App\Livewire;

use Filament\Forms;
use Filament\Notifications\Notification;
use Jeffgreco13\FilamentBreezy\Livewire\PersonalInfo;
use Saade\FilamentAutograph\Forms\Components\SignaturePad;

class MyCustomProfileComponent extends PersonalInfo
{
    public array $only = ['name', 'email', 'signature'];

    protected function getProfileFormSchema(): array
    {
        $groupFields = Forms\Components\Group::make([
            $this->getNameComponent(),
            $this->getEmailComponent(),
            $this->getSignatureComponent(),
        ])->columnSpan(2);

        return ($this->hasAvatars)
            ? [filament('filament-breezy')->getAvatarUploadComponent(), $groupFields]
            : [$groupFields];
    }

    protected function getSignatureComponent(): SignaturePad
    {
        return SignaturePad::make('signature')
            ->required();
    }

    protected function sendNotification(): void
    {
        Notification::make()
            ->success()
            ->title('Saved Data!')
            ->send();
    }
}
