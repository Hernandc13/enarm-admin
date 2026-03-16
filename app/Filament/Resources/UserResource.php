<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationLabel = 'Usuarios App';
    protected static ?string $modelLabel = 'Usuario';
    protected static ?string $pluralModelLabel = 'Usuarios App';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Datos del usuario')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Nombre(s)')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('last_name')
                        ->label('Apellidos')
                        ->maxLength(255),

                    Forms\Components\TextInput::make('email')
                        ->label('Correo')
                        ->email()
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(255),
                ]),

            Forms\Components\Section::make('Información adicional')
                ->columns(2)
                ->visible(function (?User $record) {
                    if ($record === null) {
                        return true;
                    }

                    return $record->isManualAppUser();
                })
                ->schema([
                    Forms\Components\TextInput::make('origin_university')
                        ->label('Universidad de origen')
                        ->maxLength(255),

                    Forms\Components\TextInput::make('origin_municipality')
                        ->label('Municipio de procedencia')
                        ->maxLength(255),

                    Forms\Components\TextInput::make('desired_specialty')
                        ->label('Especialidad deseada')
                        ->maxLength(255),

                    Forms\Components\TextInput::make('whatsapp_number')
                        ->label('Número de WhatsApp')
                        ->tel()
                        ->maxLength(30)
                        ->helperText('Ej: +52 33 1234 5678')
                        ->regex('/^\+?[0-9\s\-\(\)]{7,30}$/'),
                ]),

            Forms\Components\Section::make('Acceso de la cuenta')
                ->description('Define cómo se asignará la contraseña y si deseas enviar los accesos al correo del usuario.')
                ->visible(fn (string $operation): bool => $operation === 'create')
                ->columns(2)
                ->schema([
                    Forms\Components\Radio::make('auto_password')
                        ->label('Tipo de contraseña')
                        ->options([
                            1 => 'Generar contraseña automáticamente',
                            0 => 'Capturar contraseña manualmente',
                        ])
                        ->default(1)
                        ->live()
                        ->required()
                        ->columnSpan(1),

                    Forms\Components\Toggle::make('send_welcome')
                        ->label('¿Enviar accesos al correo del usuario?')
                        ->default(true)
                        ->helperText('Si activas esta opción, se enviará al correo el email y la contraseña final asignada.')
                        ->columnSpan(1),

                    Forms\Components\TextInput::make('password_plain')
                        ->label('Contraseña manual')
                        ->password()
                        ->revealable()
                        ->minLength(8)
                        ->maxLength(255)
                        ->placeholder('Mínimo 8 caracteres')
                        ->visible(fn (Forms\Get $get): bool => (string) $get('auto_password') === '0')
                        ->required(fn (Forms\Get $get): bool => (string) $get('auto_password') === '0')
                        ->same('password_plain_confirmation')
                        ->helperText('Esta será la contraseña que se guardará y, si activaste el envío, también la que se mandará por correo.')
                        ->columnSpan(1),

                    Forms\Components\TextInput::make('password_plain_confirmation')
                        ->label('Confirmar contraseña')
                        ->password()
                        ->revealable()
                        ->minLength(8)
                        ->maxLength(255)
                        ->placeholder('Repite la contraseña')
                        ->visible(fn (Forms\Get $get): bool => (string) $get('auto_password') === '0')
                        ->required(fn (Forms\Get $get): bool => (string) $get('auto_password') === '0')
                        ->columnSpan(1),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('last_name')
                    ->label('Apellidos')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('email')
                    ->label('Correo')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\IconColumn::make('has_app_access')
                    ->label('Acceso App')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('origin_university')
                    ->label('Universidad de origen')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('origin_municipality')
                    ->label('Municipio de procedencia')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('desired_specialty')
                    ->label('Especialidad deseada')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('whatsapp_number')
                    ->label('Número de WhatsApp')
                    ->searchable()
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label('Editar'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('grantAccessSelected')
                        ->label('Dar acceso a app')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Dar acceso a usuarios seleccionados')
                        ->modalDescription('Se habilitará el acceso solo a los seleccionados que estén sin acceso.')
                        ->action(function (Collection $records): void {
                            try {
                                $targets = $records
                                    ->filter(fn (User $u) => ! (bool) $u->is_admin)
                                    ->filter(fn (User $u) => ! (bool) $u->is_from_moodle)
                                    ->filter(fn (User $u) => empty($u->moodle_user_id))
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

                                Notification::make()
                                    ->title('Acceso otorgado')
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
                        }),

                    BulkAction::make('revokeAccessSelected')
                        ->label('Quitar acceso a app')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Quitar acceso a usuarios seleccionados')
                        ->modalDescription('Se deshabilitará el acceso solo a los seleccionados que estén con acceso.')
                        ->action(function (Collection $records): void {
                            try {
                                $targets = $records
                                    ->filter(fn (User $u) => ! (bool) $u->is_admin)
                                    ->filter(fn (User $u) => ! (bool) $u->is_from_moodle)
                                    ->filter(fn (User $u) => empty($u->moodle_user_id))
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
                        ->modalDescription('Esto eliminará definitivamente a los usuarios seleccionados, solo manual/excel.')
                        ->action(function (Collection $records): void {
                            try {
                                $targets = $records
                                    ->filter(fn (User $u) => ! (bool) $u->is_admin)
                                    ->filter(fn (User $u) => ! (bool) $u->is_from_moodle)
                                    ->filter(fn (User $u) => empty($u->moodle_user_id));

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
                ])
                    ->label('Abrir acciones')
                    ->icon('heroicon-o-ellipsis-vertical'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit'   => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}