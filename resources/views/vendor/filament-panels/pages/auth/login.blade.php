<x-filament-panels::page.simple>

    <x-slot name="heading">
        <div class="text-center">

            <div class="mt-3 flex justify-center">
                <img
                    src="{{ asset('images/logo-enarm.png') }}"
                    alt="Logo ENARM"
                    class="h-20 w-20 object-contain"
                >
            </div>

            <div class="mt-3 text-base font-semibold" style="color:#012e82;">
                Entre a su cuenta
            </div>
        </div>
    </x-slot>


    @if (filament()->hasRegistration())
        <x-slot name="subheading">
            {{ $this->registerAction }}
        </x-slot>
    @endif

    {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::AUTH_LOGIN_FORM_BEFORE, scopes: $this->getRenderHookScopes()) }}

    <x-filament-panels::form id="form" wire:submit="authenticate">
        {{ $this->form }}

        <x-filament-panels::form.actions
            :actions="$this->getCachedFormActions()"
            :full-width="$this->hasFullWidthFormActions()"
        />
    </x-filament-panels::form>

    {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::AUTH_LOGIN_FORM_AFTER, scopes: $this->getRenderHookScopes()) }}
</x-filament-panels::page.simple>
