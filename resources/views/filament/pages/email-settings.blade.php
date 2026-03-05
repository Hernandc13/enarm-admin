<x-filament-panels::page>
    <form wire:submit.prevent="save">
        {{ $this->form }}

        <x-filament::button type="submit" color="primary" class="mt-6">
            Guardar
        </x-filament::button>
    </form>
</x-filament-panels::page>
