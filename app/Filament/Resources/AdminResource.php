<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AdminResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AdminResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';
    protected static ?string $navigationLabel = 'Administradores';
    protected static ?string $modelLabel = 'Administrador';
    protected static ?string $pluralModelLabel = 'Administradores';

    /**
     * Solo admins
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('is_admin', true);
    }

    /**
     * Mostrar esta sección solo a usuarios admin
     */
    public static function canViewAny(): bool
    {
        $user = auth()->user();
        return $user && (bool) ($user->is_admin ?? false);
    }

    public static function form(Form $form): Form
    {
        $isCreate = $form->getOperation() === 'create';

        return $form->schema([
            Forms\Components\Section::make('Datos del administrador')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Nombre')
                        ->required()
                        ->maxLength(100),

                    Forms\Components\TextInput::make('last_name')
                        ->label('Apellidos')
                        ->required()
                        ->maxLength(150),

                    Forms\Components\TextInput::make('email')
                        ->label('Correo')
                        ->email()
                        ->required()
                        ->unique(ignoreRecord: true),

                    // ===== SOLO CREATE =====
                    Forms\Components\Toggle::make('auto_password')
                        ->label('Generar contraseña automáticamente')
                        ->default(true)
                        ->live()
                        ->visible($isCreate)
                        ->dehydrated($isCreate),

                    Forms\Components\TextInput::make('password_plain')
                        ->label('Contraseña (se enviará en el correo)')
                        ->password()
                        ->revealable()
                        ->minLength(8)
                        ->visible(fn (Forms\Get $get) => $isCreate && ! $get('auto_password'))
                        ->required(fn (Forms\Get $get) => $isCreate && ! $get('auto_password'))
                        ->dehydrated($isCreate),

                    Forms\Components\Toggle::make('send_welcome')
                        ->label('Enviar mensaje de bienvenida')
                        ->default(true)
                        ->visible($isCreate)
                        ->dehydrated($isCreate),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Nombre')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('last_name')->label('Apellidos')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('email')->label('Correo')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('created_at')->label('Alta')->dateTime('Y-m-d H:i')->sortable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),

            
                Tables\Actions\DeleteAction::make()
                    ->label('Eliminar')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Eliminar administrador')
                    ->modalDescription('Esta acción no se puede deshacer.'),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListAdmins::route('/'),
            'create' => Pages\CreateAdmin::route('/create'),
            'edit'   => Pages\EditAdmin::route('/{record}/edit'),
        ];
    }
}
