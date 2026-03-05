<?php

namespace App\Filament\Pages;

use App\Mail\MoodleAccessMail;
use App\Models\Setting;
use App\Models\User;
use App\Services\MoodleService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\BulkActionsPosition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class MoodleUsers extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationLabel = 'Usuarios Moodle';
    protected static ?string $title = 'Usuarios Moodle';
    protected static ?string $navigationGroup = 'Integraciones';

    protected static string $view = 'filament.pages.moodle-users';

    protected function getTableQuery(): Builder
    {
        return User::query()
            ->where('is_admin', false)
            ->where('is_from_moodle', true);
    }

    protected function getDefaultTableSortColumn(): ?string
    {
        return 'id';
    }

    protected function getDefaultTableSortDirection(): ?string
    {
        return 'desc';
    }

    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('fullname')
                ->label('Nombre completo')
                ->state(fn (User $record) => trim(($record->name ?? '') . ' ' . ($record->last_name ?? '')))
                ->searchable(query: function (Builder $query, string $search): Builder {
                    return $query->where(function (Builder $q) use ($search) {
                        $q->where('name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
                })
                ->sortable(),

            TextColumn::make('email')
                ->label('Correo')
                ->searchable()
                ->sortable(),

            BadgeColumn::make('has_app_access')
                ->label('Estado')
                ->formatStateUsing(fn ($state) => (bool) $state ? 'Con acceso' : 'Sin acceso')
                ->color(fn ($state) => (bool) $state ? 'success' : 'gray')
                ->sortable(),

            TextColumn::make('synced_at')
                ->label('Sincronización')
                ->dateTime('Y-m-d H:i')
                ->sortable(),

            TextColumn::make('granted_at')
                ->label('Acceso otorgado')
                ->dateTime('Y-m-d H:i')
                ->toggleable(isToggledHiddenByDefault: true)
                ->sortable(),

            TextColumn::make('revoked_at')
                ->label('Acceso cancelado')
                ->dateTime('Y-m-d H:i')
                ->toggleable(isToggledHiddenByDefault: true)
                ->sortable(),
        ];
    }

    /**
     * Esto evita que aparezca arriba/abajo (si tu instalación lo estuviera mostrando 2 veces por posición).
     */
    protected function getTableBulkActionsPosition(): BulkActionsPosition
    {
        return BulkActionsPosition::Top;
    }

    /**
     * NO BulkActionGroup: Filament ya renderiza el botón "Abrir acciones" automáticamente.
     */
    protected function getTableBulkActions(): array
    {
        return [
            BulkAction::make('grantAccessSelected')
                ->label('Dar acceso a app')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Dar acceso a usuarios seleccionados')
                ->modalDescription('Se habilitará el acceso SOLO a los seleccionados que estén "Sin acceso".')
                ->form([
                    Forms\Components\Toggle::make('send_email')
                        ->label('Enviar correo de bienvenida')
                        ->default(true),
                ])
                ->action(function (Collection $records, array $data): void {
                    try {
                        $sendEmail = (bool) ($data['send_email'] ?? true);

                        $targets = $records
                            ->filter(fn (User $u) => (bool) $u->is_from_moodle && ! (bool) $u->is_admin)
                            ->filter(fn (User $u) => ! (bool) $u->has_app_access);

                        if ($targets->isEmpty()) {
                            Notification::make()
                                ->title('Nada que actualizar')
                                ->body('Los seleccionados ya tienen acceso o no aplican.')
                                ->warning()
                                ->send();
                            return;
                        }

                        $ids = $targets->pluck('id')->all();

                        $updated = User::query()
                            ->whereIn('id', $ids)
                            ->update([
                                'has_app_access' => true,
                                'granted_at'     => now(),
                                'revoked_at'     => null,
                            ]);

                        $sent = 0;

                        if ($sendEmail) {
                            $logoUrl = Setting::get('email_logo_url', 'https://i.imgur.com/WHdjsG4.png');

                            User::query()
                                ->whereIn('id', $ids)
                                ->orderBy('id')
                                ->chunk(100, function ($chunk) use (&$sent, $logoUrl) {
                                    foreach ($chunk as $user) {
                                        Mail::to($user->email)->send(new MoodleAccessMail([
                                            'name'       => trim(($user->name ?? '') . ' ' . ($user->last_name ?? '')),
                                            'email'      => (string) $user->email,
                                            'app_url'    => config('app.url'),
                                            'moodle_url' => config('services.moodle.url'),
                                            'logo_url'   => $logoUrl,
                                        ]));
                                        $sent++;
                                    }
                                });
                        }

                        Notification::make()
                            ->title('Acceso otorgado')
                            ->body("Usuarios actualizados: {$updated}" . ($sendEmail ? "\nCorreos enviados: {$sent}" : ''))
                            ->success()
                            ->send();

                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('No se pudo otorgar acceso')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            BulkAction::make('revokeAccessSelected')
                ->label('Quitar acceso a app')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Quitar acceso a usuarios seleccionados')
                ->modalDescription('Se deshabilitará el acceso SOLO a los seleccionados que estén "Con acceso".')
                ->action(function (Collection $records): void {
                    try {
                        $targets = $records
                            ->filter(fn (User $u) => (bool) $u->is_from_moodle && ! (bool) $u->is_admin)
                            ->filter(fn (User $u) => (bool) $u->has_app_access);

                        if ($targets->isEmpty()) {
                            Notification::make()
                                ->title('Nada que actualizar')
                                ->body('Los seleccionados ya están sin acceso o no aplican.')
                                ->warning()
                                ->send();
                            return;
                        }

                        $ids = $targets->pluck('id')->all();

                        $updated = User::query()
                            ->whereIn('id', $ids)
                            ->update([
                                'has_app_access' => false,
                                'revoked_at'     => now(),
                            ]);

                        Notification::make()
                            ->title('Acceso revocado')
                            ->body("Usuarios actualizados: {$updated}")
                            ->success()
                            ->send();

                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('No se pudo revocar acceso')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            BulkAction::make('deleteSelected')
                ->label('Eliminar seleccionados')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Eliminar usuarios seleccionados')
                ->modalDescription('Esto elimina SOLO en el panel a los usuarios Moodle seleccionados. Perderán acceso a la app.')
                ->action(function (Collection $records): void {
                    try {
                        $targets = $records
                            ->filter(fn (User $u) => (bool) $u->is_from_moodle && ! (bool) $u->is_admin);

                        if ($targets->isEmpty()) {
                            Notification::make()
                                ->title('Nada que eliminar')
                                ->body('Los seleccionados no aplican para eliminación.')
                                ->warning()
                                ->send();
                            return;
                        }

                        $ids = $targets->pluck('id')->all();

                        $deleted = User::query()
                            ->whereIn('id', $ids)
                            ->delete();

                        Notification::make()
                            ->title('Usuarios eliminados')
                            ->body("Usuarios eliminados: {$deleted}")
                            ->success()
                            ->send();

                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('No se pudo eliminar')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }

    protected function getTableActions(): array
    {
        return [];
    }

    /**
     * ✅ HEADER ACTIONS: aquí agrupamos "Dar acceso a todos" + "Quitar acceso a todos"
     */
    protected function getTableHeaderActions(): array
{
    return [
        Action::make('syncMoodle')
            ->label('Sincronizar Moodle')
            ->icon('heroicon-o-arrow-path')
            ->color('primary')
            ->requiresConfirmation()
            ->modalHeading('Sincronizar usuarios desde Moodle')
            ->modalDescription('Esto actualiza/crea usuarios en la tabla users. No otorga acceso automáticamente.')
            ->action(function (): void {
                $this->syncFromMoodle();
            }),

        // ✅ Botón tipo "pill" que abre menú (como tu screenshot)
        ActionGroup::make([
            Action::make('grantAccessAllMoodle')
                ->label('Dar acceso a todos')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Dar acceso a TODOS los usuarios Moodle')
                ->modalDescription('Se habilitará el acceso SOLO a usuarios Moodle que estén "Sin acceso".')
                ->form([
                    Forms\Components\Toggle::make('send_email')
                        ->label('Enviar correo de bienvenida')
                        ->default(true),
                ])
                ->action(function (array $data): void {
                    try {
                        $sendEmail = (bool) ($data['send_email'] ?? true);

                        $q = User::query()
                            ->where('is_admin', false)
                            ->where('is_from_moodle', true)
                            ->where('has_app_access', false);

                        $ids = $sendEmail ? (clone $q)->pluck('id')->all() : [];

                        $updated = (clone $q)->update([
                            'has_app_access' => true,
                            'granted_at'     => now(),
                            'revoked_at'     => null,
                        ]);

                        $sent = 0;

                        if ($sendEmail && ! empty($ids)) {
                            $logoUrl = Setting::get('email_logo_url', 'https://i.imgur.com/WHdjsG4.png');

                            User::query()
                                ->whereIn('id', $ids)
                                ->orderBy('id')
                                ->chunk(200, function ($chunk) use (&$sent, $logoUrl) {
                                    foreach ($chunk as $user) {
                                        Mail::to($user->email)->send(new MoodleAccessMail([
                                            'name'       => trim(($user->name ?? '') . ' ' . ($user->last_name ?? '')),
                                            'email'      => (string) $user->email,
                                            'app_url'    => config('app.url'),
                                            'moodle_url' => config('services.moodle.url'),
                                            'logo_url'   => $logoUrl,
                                        ]));
                                        $sent++;
                                    }
                                });
                        }

                        Notification::make()
                            ->title('Acceso otorgado')
                            ->body("Usuarios actualizados: {$updated}" . ($sendEmail ? "\nCorreos enviados: {$sent}" : ''))
                            ->success()
                            ->send();

                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('No se pudo otorgar acceso')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Action::make('revokeAccessAllMoodle')
                ->label('Quitar acceso a todos')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Quitar acceso a TODOS los usuarios Moodle')
                ->modalDescription('Se deshabilitará el acceso SOLO a usuarios Moodle que estén "Con acceso".')
                ->action(function (): void {
                    try {
                        $updated = User::query()
                            ->where('is_admin', false)
                            ->where('is_from_moodle', true)
                            ->where('has_app_access', true)
                            ->update([
                                'has_app_access' => false,
                                'revoked_at'     => now(),
                            ]);

                        Notification::make()
                            ->title('Acceso revocado')
                            ->body("Usuarios actualizados: {$updated}")
                            ->success()
                            ->send();

                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('No se pudo revocar acceso')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ])
            ->label('Accesos')
            ->icon('heroicon-o-user-group')
            ->color('gray')
            ->button()
            // Opcional (si tu versión lo soporta):
            // ->outlined()
            // ->size('sm')
        ,
    ];
}


    private function syncFromMoodle(): void
    {
        try {
            /** @var MoodleService $moodle */
            $moodle = app(MoodleService::class);
            $users = $moodle->listUsers();

            $created = 0;
            $updated = 0;

            foreach ($users as $u) {
                $moodleId = (int) ($u['id'] ?? 0);
                $email    = strtolower(trim((string) ($u['email'] ?? '')));
                $first    = trim((string) ($u['firstname'] ?? ''));
                $last     = trim((string) ($u['lastname'] ?? ''));

                if ($moodleId <= 0) {
                    continue;
                }

                $record = User::query()->where('moodle_user_id', $moodleId)->first();
                if (! $record && $email !== '') {
                    $record = User::query()->where('email', $email)->first();
                }

                if (! $record) {
                    $record = new User();
                    $record->password       = bcrypt(Str::random(32));
                    $record->has_app_access = false;
                    $record->granted_at     = null;
                    $record->revoked_at     = null;
                    $record->is_admin       = false;
                    $created++;
                } else {
                    $updated++;
                }

                $record->moodle_user_id = $moodleId;
                $record->is_from_moodle = true;

                if ($email !== '') {
                    $record->email = $email;
                }
                if ($first !== '') {
                    $record->name = $first;
                }

                $record->last_name = $last;
                $record->synced_at = now();
                $record->save();
            }

            Notification::make()
                ->title('Sincronización completada')
                ->body("Nuevos: {$created} · Actualizados: {$updated} · Total recibido: " . count($users))
                ->success()
                ->send();

        } catch (\Throwable $e) {
            Notification::make()
                ->title('Error al sincronizar Moodle')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
