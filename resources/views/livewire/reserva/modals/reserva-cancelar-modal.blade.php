<div>
    @if ($openModal && $reserva)
        <x-dialog-modal wire:model="openModal">
            <x-slot name="title">Cancelar reserva #{{ $reserva->id }}</x-slot>
            <x-slot name="content">
                <hr>
                <p class="m-2 text-sm text-gray-700 dark:text-gray-300">
                    {{ trim(($reserva->producto?->modelo?->nombre ?? 'Equipo') . ' ' . $reserva->producto?->almacenamiento) }}
                    (IMEI {{ $reserva->producto?->imei }}) reservado para <strong>{{ $reserva->cliente?->nombre }}</strong>
                    con una seña de <strong>Bs {{ number_format((float) $reserva->sena, 2) }}</strong> en {{ $reserva->metodo?->nombre }}.
                </p>
                <p class="m-2 text-sm text-gray-700 dark:text-gray-300">El equipo vuelve al inventario. ¿Qué pasa con la seña?</p>
                <div class="m-2 space-y-2">
                    @foreach (\App\Enums\SenaDestino::cases() as $opcion)
                        <label class="flex items-center gap-2 text-sm text-gray-800 dark:text-gray-200">
                            <input type="radio" wire:model="destino" value="{{ $opcion->value }}" class="text-brand-600 focus:ring-brand-500">
                            {{ $opcion->label() }}
                        </label>
                    @endforeach
                    <x-input-error for="destino" />
                </div>
                <div class="m-2">
                    <x-label value="Nota (opcional)" />
                    <x-input type="text" class="mt-1 w-full" wire:model="nota" maxlength="200" />
                </div>
            </x-slot>
            <x-slot name="footer">
                <x-secondary-button wire:click="closeModal()" wire:loading.attr="disabled">Volver</x-secondary-button>
                <x-danger-button class="ml-2" wire:click="cancelar()" wire:loading.attr="disabled">Cancelar reserva</x-danger-button>
            </x-slot>
        </x-dialog-modal>
    @endif
</div>
