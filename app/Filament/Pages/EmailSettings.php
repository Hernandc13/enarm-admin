<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Services\EmailTemplateService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\HtmlString;

class EmailSettings extends Page implements Forms\Contracts\HasForms
{
    use Forms\Concerns\InteractsWithForms;

    protected static ?string $navigationIcon  = 'heroicon-o-envelope';
    protected static ?string $navigationLabel = 'Correos';
    protected static ?string $navigationGroup = 'Configuración';
    protected static ?string $title           = 'Plantillas de correo';

    protected static string $view = 'filament.pages.email-settings';

    public array $data = [];

    private array $toolbarFull = [
        'bold','italic','underline','strike','link',
        'blockquote','bulletList','orderedList','h2','h3','hr','undo','redo',
    ];

    public function mount(): void
    {
        $this->fillForType('normal');
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Plantilla')
                    ->schema([
                        Forms\Components\Select::make('template_type')
                            ->label('Tipo de plantilla')
                            ->options([
                                'normal' => 'Plantilla usuarios normales',
                                'moodle' => 'Plantilla usuarios de Moodle',
                                'admin'  => 'Plantilla usuarios administradores',
                            ])
                            ->required()
                            ->native(false)
                            ->reactive()
                            ->afterStateUpdated(function ($state) {
                                $this->fillForType((string) $state);
                            }),
                    ]),

                Forms\Components\Grid::make([
                        'default' => 1,
                        'lg'      => 2,
                    ])
                    ->schema([
                        // Left preview
                        Forms\Components\Section::make('Previsualización')
                            ->schema([
                                Forms\Components\Placeholder::make('preview')
                                    ->label('')
                                    ->content(function (callable $get) {
                                        $type = (string) $get('template_type');

                                        $sample = match ($type) {
                                            'moodle' => [
                                                'name' => 'demo user',
                                                'email' => 'demo@gmail.com',
                                                'password' => '',
                                                'app_url' => (string) config('app.url'),
                                                'moodle_url' => (string) config('services.moodle.url'),
                                                'logo_url' => (string) Setting::get('email_logo_url', 'https://i.imgur.com/WHdjsG4.png'),
                                            ],
                                            'admin' => [
                                                'name' => 'demo admin',
                                                'email' => 'admin@gmail.com',
                                                'password' => '2[x01JK_c%h]',
                                                'app_url' => (string) config('app.url'),
                                                'panel_url' => rtrim((string) config('app.url'), '/') . '/admin_enarm',
                                                'logo_url' => (string) Setting::get('email_logo_url', 'https://i.imgur.com/WHdjsG4.png'),
                                            ],
                                            default => [
                                                'name' => 'demo user',
                                                'email' => 'demo@gmail.com',
                                                'password' => '2[x01JK_c%h]',
                                                'app_url' => (string) config('app.url'),
                                                'logo_url' => (string) Setting::get('email_logo_url', 'https://i.imgur.com/WHdjsG4.png'),
                                            ],
                                        };

                                        $keys = $this->keys($type);

                                        $overrides = [
                                            $keys['subject']        => (string) $get('subject'),
                                            $keys['body']           => (string) $get('body'),
                                            $keys['brand_title']    => (string) $get('brand_title'),
                                            $keys['brand_subtitle'] => (string) $get('brand_subtitle'),
                                            $keys['footer_text']    => (string) $get('footer_text'),
                                            $keys['header_color']   => (string) $get('header_color'),
                                            $keys['button_color']   => (string) $get('button_color'),
                                        ];

                                        if ($type === 'admin') {
                                            $overrides[$keys['admin_cta_text']] = (string) $get('admin_cta_text');
                                            $overrides[$keys['admin_cta_url']]  = (string) $get('admin_cta_url');
                                        } else {
                                            $overrides[$keys['ios_text']]     = (string) $get('ios_text');
                                            $overrides[$keys['ios_url']]      = (string) $get('ios_url');
                                            $overrides[$keys['android_text']] = (string) $get('android_text');
                                            $overrides[$keys['android_url']]  = (string) $get('android_url');
                                        }

                                        $service = app(EmailTemplateService::class);
                                        $out = $service->render($type, $sample, $overrides);

                                        $html = (string) $out['html'];
                                        $subject = (string) $get('subject');

                                        $iframe = '
                                            <div style="border:1px solid #e5e7eb; border-radius:14px; overflow:hidden; background:#fff;">
                                              <div style="padding:10px 12px; border-bottom:1px solid #e5e7eb; font-family:Arial; font-size:13px; font-weight:700;">
                                                Asunto: ' . e($subject) . '
                                              </div>
                                              <iframe style="width:100%; height:860px; border:0; display:block;" srcdoc="' . e($html) . '"></iframe>
                                            </div>
                                        ';

                                        return new HtmlString($iframe);
                                    })
                                    ->columnSpanFull(),
                            ])
                            ->columnSpan(1),

                        Forms\Components\Section::make('Diseño')
                            ->schema([
                                Forms\Components\TextInput::make('subject')
                                    ->label('Asunto')
                                    ->required()
                                    ->maxLength(150)
                                    ->live(debounce: 250)
                                    ->columnSpanFull(),

                        
                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\ColorPicker::make('header_color')
                                            ->label('Color del header')
                                            ->default('#990017')
                                            ->live(),

                                        Forms\Components\ColorPicker::make('button_color')
                                            ->label('Color de botones')
                                            ->default('#012e82')
                                            ->live(),
                                    ]),

                                Forms\Components\TextInput::make('brand_title')
                                    ->label('Título del header')
                                    ->required()
                                    ->maxLength(50)
                                    ->live(debounce: 250)
                                    ->columnSpanFull(),

                                Forms\Components\TextInput::make('brand_subtitle')
                                    ->label('Subtítulo del header')
                                    ->required()
                                    ->maxLength(80)
                                    ->live(debounce: 250)
                                    ->columnSpanFull(),

                                Forms\Components\RichEditor::make('body')
                                    ->label('Contenido')
                                    ->required()
                                    ->toolbarButtons($this->toolbarFull)
                                    ->live(debounce: 250)
                                    ->columnSpanFull(),

                                Forms\Components\Group::make()
                                    ->schema([
                                        Forms\Components\Grid::make(2)->schema([
                                            Forms\Components\TextInput::make('ios_text')
                                                ->label('Texto botón iOS')
                                                ->required()
                                                ->maxLength(60)
                                                ->live(debounce: 250),

                                            Forms\Components\TextInput::make('ios_url')
                                                ->label('URL botón iOS')
                                                ->required()
                                                ->live(debounce: 250),
                                        ]),
                                        Forms\Components\Grid::make(2)->schema([
                                            Forms\Components\TextInput::make('android_text')
                                                ->label('Texto botón Android')
                                                ->required()
                                                ->maxLength(60)
                                                ->live(debounce: 250),

                                            Forms\Components\TextInput::make('android_url')
                                                ->label('URL botón Android')
                                                ->required()
                                                ->live(debounce: 250),
                                        ]),
                                    ])
                                    ->visible(fn (callable $get) => (string) $get('template_type') !== 'admin')
                                    ->columnSpanFull(),

                                Forms\Components\Group::make()
                                    ->schema([
                                        Forms\Components\Grid::make(2)->schema([
                                            Forms\Components\TextInput::make('admin_cta_text')
                                                ->label('Texto botón (Admin)')
                                                ->required()
                                                ->maxLength(60)
                                                ->live(debounce: 250),

                                            Forms\Components\TextInput::make('admin_cta_url')
                                                ->label('URL botón (Admin)')
                                                ->required()
                                                ->live(debounce: 250),
                                        ]),
                                    ])
                                    ->visible(fn (callable $get) => (string) $get('template_type') === 'admin')
                                    ->columnSpanFull(),

                                Forms\Components\RichEditor::make('footer_text')
                                    ->label('Texto del footer')
                                    ->required()
                                    ->toolbarButtons($this->toolbarFull)
                                    ->live(debounce: 250)
                                    ->columnSpanFull(),
                            ])
                            ->columnSpan(1),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $state = $this->form->getState();
        $type  = (string) ($state['template_type'] ?? 'normal');
        $keys  = $this->keys($type);

        Setting::set($keys['subject'], (string) ($state['subject'] ?? ''));
        Setting::set($keys['body'], (string) ($state['body'] ?? ''));

        Setting::set($keys['brand_title'], (string) ($state['brand_title'] ?? 'ENARM CCM'));
        Setting::set($keys['brand_subtitle'], (string) ($state['brand_subtitle'] ?? 'Accesos a tu cuenta'));
        Setting::set($keys['footer_text'], (string) ($state['footer_text'] ?? ''));

        Setting::set($keys['header_color'], (string) ($state['header_color'] ?? '#990017'));
        Setting::set($keys['button_color'], (string) ($state['button_color'] ?? '#012e82'));

        if ($type === 'admin') {
            Setting::set($keys['admin_cta_text'], (string) ($state['admin_cta_text'] ?? 'Abrir panel'));
            Setting::set($keys['admin_cta_url'], (string) ($state['admin_cta_url'] ?? ''));
        } else {
            Setting::set($keys['ios_text'], (string) ($state['ios_text'] ?? 'Descarga iOS'));
            Setting::set($keys['ios_url'], (string) ($state['ios_url'] ?? ''));
            Setting::set($keys['android_text'], (string) ($state['android_text'] ?? 'Descarga Android'));
            Setting::set($keys['android_url'], (string) ($state['android_url'] ?? ''));
        }

        Notification::make()->title('Plantilla guardada')->success()->send();
    }

    private function fillForType(string $type): void
    {
        $keys = $this->keys($type);
        $def  = $this->defaults($type);

        $this->form->fill([
            'template_type'   => $type,

            'subject'         => (string) Setting::get($keys['subject'], $def['subject']),
            'body'            => (string) Setting::get($keys['body'], $def['body']),

            'brand_title'     => (string) Setting::get($keys['brand_title'], $def['brand_title']),
            'brand_subtitle'  => (string) Setting::get($keys['brand_subtitle'], $def['brand_subtitle']),
            'footer_text'     => (string) Setting::get($keys['footer_text'], $def['footer_text']),

            'header_color'    => (string) Setting::get($keys['header_color'], $def['header_color']),
            'button_color'    => (string) Setting::get($keys['button_color'], $def['button_color']),

            'ios_text'        => (string) Setting::get($keys['ios_text'], $def['ios_text']),
            'ios_url'         => (string) Setting::get($keys['ios_url'], $def['ios_url']),
            'android_text'    => (string) Setting::get($keys['android_text'], $def['android_text']),
            'android_url'     => (string) Setting::get($keys['android_url'], $def['android_url']),

            'admin_cta_text'  => (string) Setting::get($keys['admin_cta_text'], $def['admin_cta_text']),
            'admin_cta_url'   => (string) Setting::get($keys['admin_cta_url'], $def['admin_cta_url']),
        ]);
    }

    private function keys(string $type): array
    {
        $prefix = match ($type) {
            'moodle' => 'moodle_email',
            'admin'  => 'admin_welcome_email',
            default  => 'welcome_email',
        };

        return [
            'subject'        => "{$prefix}_subject",
            'body'           => "{$prefix}_body",

            'brand_title'    => "{$prefix}_brand_title",
            'brand_subtitle' => "{$prefix}_brand_subtitle",
            'footer_text'    => "{$prefix}_footer_text",

            'header_color'   => "{$prefix}_header_color",
            'button_color'   => "{$prefix}_button_color",

            'ios_text'       => "{$prefix}_ios_text",
            'ios_url'        => "{$prefix}_ios_url",
            'android_text'   => "{$prefix}_android_text",
            'android_url'    => "{$prefix}_android_url",

            'admin_cta_text' => "{$prefix}_cta_text",
            'admin_cta_url'  => "{$prefix}_cta_url",
        ];
    }

    private function defaults(string $type): array
    {
        $common = [
            'brand_title'  => 'ENARM CCM',
            'footer_text'  => 'Si no solicitaste este acceso, ignora este mensaje o contacta al administrador.',
            'header_color' => '#990017',
            'button_color' => '#012e82',
        ];

        return match ($type) {
            'moodle' => $common + [
                'subject'        => 'Acceso a la app - Usa tus credenciales de Moodle',
                'brand_subtitle' => 'Acceso con credenciales Moodle',
                'body'           => '<p>Te damos la bienvenida. A continuación encontrarás tus accesos para iniciar sesión:</p>',
                'ios_text'       => 'Descarga iOS',
                'ios_url'        => '',
                'android_text'   => 'Descarga Android',
                'android_url'    => '',
                'admin_cta_text' => 'Abrir panel',
                'admin_cta_url'  => '',
            ],
            'admin' => $common + [
                'subject'        => 'Acceso de administrador - ENARM CCM',
                'brand_subtitle' => 'Acceso de administrador',
                'body'           => '<p>Tu acceso de administrador está listo. A continuación se muestran tus datos:</p>',
                'admin_cta_text' => 'Abrir panel',
                'admin_cta_url'  => rtrim((string) config('app.url'), '/') . '/admin_enarm',
                'ios_text'       => 'Descarga iOS',
                'ios_url'        => '',
                'android_text'   => 'Descarga Android',
                'android_url'    => '',
            ],
            default => $common + [
                'subject'        => 'Bienvenido(a) - Accesos',
                'brand_subtitle' => 'Accesos a tu cuenta',
                'body'           => '<p>Te damos la bienvenida. A continuación encontrarás tus accesos para iniciar sesión:</p>',
                'ios_text'       => 'Descarga iOS',
                'ios_url'        => '',
                'android_text'   => 'Descarga Android',
                'android_url'    => '',
                'admin_cta_text' => 'Abrir panel',
                'admin_cta_url'  => '',
            ],
        };
    }
}
