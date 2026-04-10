<?php

namespace App\Filament\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;
use Illuminate\Support\Facades\Hash;

class NotificationCenter extends Page
{
    protected static ?string $title = 'Centro de Notificaciones';

    protected static ?string $navigationLabel = 'Notificaciones';

    protected static ?string $slug = 'notificaciones';

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedBell;

    protected static string | UnitEnum | null $navigationGroup = 'Administracion';

    protected string $view = 'filament.pages.notification-center';

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('limpiarHistorial')
                ->label('Limpiar Historial')
                ->icon(Heroicon::OutlinedTrash)
                ->color('danger')
                ->form([
                    TextInput::make('password')
                        ->label('Clave de inicio de sesion')
                        ->password()
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $user = auth()->user();

                    if (! $user) {
                        return;
                    }

                    $password = (string) ($data['password'] ?? '');

                    if (! Hash::check($password, (string) $user->password)) {
                        Notification::make()
                            ->title('Clave incorrecta')
                            ->body('No se pudo limpiar el historial porque la clave es invalida.')
                            ->danger()
                            ->send();

                        return;
                    }

                    $user->notifications()->delete();

                    Notification::make()
                        ->title('Historial limpiado')
                        ->body('Se eliminaron tus notificaciones guardadas.')
                        ->success()
                        ->send();
                }),
        ];
    }

    public function getViewData(): array
    {
        $user = auth()->user();

        return [
            'total' => (int) ($user?->notifications()->count() ?? 0),
            'unread' => (int) ($user?->unreadNotifications()->count() ?? 0),
            'latest' => $user?->notifications()->latest()->take(20)->get() ?? collect(),
        ];
    }
}
