<?php

namespace App\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RemindSignatureIfMissing
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user && blank($user->signature) && session()->pull('remind_signature', false)) {
            $profileUrl = Filament::getCurrentPanel()?->getUrl().'/profile';

            Notification::make()
                ->title('Signature required')
                ->body('Please add your signature on your profile page so it can appear on PDF documents.')
                ->warning()
                ->persistent()
                ->actions([
                    Action::make('go_to_profile')
                        ->label('Go to Profile')
                        ->button()
                        ->url($profileUrl),
                ])
                ->send();
        }

        return $next($request);
    }
}
