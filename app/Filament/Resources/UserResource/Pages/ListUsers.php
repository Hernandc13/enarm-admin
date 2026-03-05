<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Imports\UsersImport;
use App\Models\User;
use Filament\Actions;
use Filament\Actions\ActionGroup;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    /**
     *  Solo usuarios Manual/Excel (NO Moodle) y NO admins.
     */
    protected function getTableQuery(): Builder
    {
        return parent::getTableQuery()
            ->where('is_admin', false)
            ->whereNull('moodle_user_id')
            ->where('is_from_moodle', false);
    }

    /**
     *  Header actions agrupadas 
     */
    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Crear Usuario')
                ->icon('heroicon-o-user-plus')
                ->color('primary'),

            // Menú: Accesos
            ActionGroup::make([
                Actions\Action::make('grantAccessAll')
                    ->label('Dar acceso a todos')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Dar acceso a todos los usuarios (Manual/Excel)')
                    ->modalDescription('Se habilitará el acceso únicamente a usuarios que actualmente estén "Sin acceso".')
                    ->action(function (): void {
                        $this->grantAccessAll();
                    }),

                Actions\Action::make('revokeAccessAll')
                    ->label('Quitar acceso a todos')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Quitar acceso a todos los usuarios (Manual/Excel)')
                    ->modalDescription('Se deshabilitará el acceso únicamente a usuarios que actualmente estén "Con acceso".')
                    ->action(function (): void {
                        $this->revokeAccessAll();
                    }),
            ])
                ->label('Accesos')
                ->icon('heroicon-o-user-group')
                ->color('gray')
                ->button(),

            //  Menú: Excel
            ActionGroup::make([
                Actions\Action::make('downloadUsersTemplate')
                    ->label('Descargar formato Excel')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->url(fn () => route('users.template.download'))
                    ->openUrlInNewTab(),

                Actions\Action::make('importExcel')
                    ->label('Importar Excel')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('primary')
                    ->modalHeading('Importar usuarios desde Excel')
                    ->modalDescription(
                        "Regla de contraseña:\n" .
                        "• Si 'contrasena' trae valor, ESA será la contraseña que se enviará al usuario.\n" .
                        "• Si 'contrasena' viene vacía, el sistema generará una contraseña automática."
                    )
                    ->form([
                        Forms\Components\FileUpload::make('file')
                            ->label('Archivo Excel (.xlsx)')
                            ->required()
                            ->disk('local')
                            ->directory('imports')
                            ->preserveFilenames()
                            ->acceptedFileTypes([
                                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                'application/vnd.ms-excel',
                            ]),

                        Forms\Components\Toggle::make('send_welcome')
                            ->label('Enviar mensaje de bienvenida')
                            ->default(true),
                    ])
                    ->action(function (array $data): void {
                        $this->handleImportExcel($data);
                    }),
            ])
                ->label('Excel')
                ->icon('heroicon-o-document-arrow-up')
                ->color('gray')
                ->button(),
        ];
    }

    protected function grantAccessAll(): void
    {
        try {
            $updated = User::query()
                ->where('is_admin', false)
                ->whereNull('moodle_user_id')
                ->where('is_from_moodle', false)
                ->where('has_app_access', false)
                ->update([
                    'has_app_access' => true,
                    'granted_at'     => now(),
                    'revoked_at'     => null,
                ]);

            Notification::make()
                ->title('Acceso otorgado en lote')
                ->body("Usuarios actualizados: {$updated}")
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('No se pudo otorgar acceso')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function revokeAccessAll(): void
    {
        try {
            $updated = User::query()
                ->where('is_admin', false)
                ->whereNull('moodle_user_id')
                ->where('is_from_moodle', false)
                ->where('has_app_access', true)
                ->update([
                    'has_app_access' => false,
                    'revoked_at'     => now(),
                ]);

            Notification::make()
                ->title('Acceso revocado en lote')
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
    }

    protected function handleImportExcel(array $data): void
    {
        try {
            $relativePath = $data['file'];
            $fullPath = Storage::disk('local')->path($relativePath);

            $import = new UsersImport((bool) ($data['send_welcome'] ?? true));
            Excel::import($import, $fullPath);

            Storage::disk('local')->delete($relativePath);

            $lines = [
                "Importados: {$import->imported}",
                "Omitidos (duplicados): {$import->skippedDuplicates}",
                "Con error: {$import->failed}",
            ];

            if (! empty($import->errors)) {
                $details = array_slice($import->errors, 0, 8);
                $lines[] = '';
                $lines[] = 'Detalles (primeros):';
                foreach ($details as $d) {
                    $lines[] = "• {$d}";
                }
                if (count($import->errors) > 8) {
                    $lines[] = '• ...';
                }
            }

            Notification::make()
                ->title('Importación finalizada')
                ->body(implode("\n", $lines))
                ->success()
                ->send();
        } catch (\Throwable $e) {
            if (! empty($data['file'])) {
                Storage::disk('local')->delete($data['file']);
            }

            Notification::make()
                ->title('No se pudo importar el Excel')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
