<x-filament-panels::page>
    <div class="space-y-2">
        <div class="text-sm text-gray-500">
            Simulador: <strong>{{ $record->name }}</strong>
            <span class="ml-2">• Preguntas: <strong>{{ $record->questions()->count() }}</strong></span>
        </div>

        {{ $this->table }}
    </div>
</x-filament-panels::page>
