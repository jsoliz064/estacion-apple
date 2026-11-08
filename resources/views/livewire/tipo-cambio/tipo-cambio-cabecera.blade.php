<div>
    <button type="button" wire:click="abrir"
        title="{{ $alDia ? 'Tipo de cambio del día' : 'El tipo de cambio no se actualizó hoy' }}"
        @class([
            'flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold shadow-sm whitespace-nowrap',
            'bg-white text-gray-800 hover:bg-brand-100 dark:bg-gray-700 dark:text-gray-100 dark:hover:bg-gray-600' => $alDia,
            'bg-amber-400 text-amber-950 hover:bg-amber-300' => !$alDia,
        ])>
        <span>TC {{ \App\Support\Dinero::tc($vigente?->valor ?? \App\Models\TipoCambio::actual()) }}</span>
        @unless ($alDia)
            <span class="hidden sm:inline font-normal">· sin actualizar hoy</span>
        @endunless
    </button>

    @if ($openModal)
        <x-dialog-modal wire:model="openModal" maxWidth="md">
            <x-slot name="title">Tipo de cambio</x-slot>

            <x-slot name="content">
                <p class="text-sm">
                    Bs por dólar. Todo se guarda en USD: el tipo de cambio solo cambia lo que se ve en Bs.
                    Las ventas y compras ya hechas conservan el suyo.
                </p>

                @can('tipo-cambio.actualizar')
                    <div class="mt-4">
                        <x-label for="tcValor" value="Tipo de cambio de hoy" />
                        <x-input id="tcValor" type="number" step="0.0001" min="0" class="mt-1 w-full"
                            wire:model="valor" wire:keydown.enter="actualizar" onfocus="this.select()" />
                        <x-input-error for="valor" class="mt-1" />
                    </div>
                @endcan

                <h4 class="mt-5 mb-1 text-sm font-semibold text-gray-700 dark:text-gray-200">Últimos cambios</h4>
                <ul class="divide-y divide-gray-200 text-sm dark:divide-gray-700">
                    @forelse ($historial as $fila)
                        <li class="flex justify-between gap-2 py-1.5" wire:key="tc-{{ $fila->id }}">
                            <span>{{ $fila->created_at->format('d/m/Y H:i') }} · {{ $fila->user?->name ?? 'Sistema' }}</span>
                            <span class="font-semibold tabular-nums">{{ \App\Support\Dinero::tc($fila->valor) }}</span>
                        </li>
                    @empty
                        <li class="py-1.5 text-gray-500">Todavía no se cargó ninguno.</li>
                    @endforelse
                </ul>
            </x-slot>

            <x-slot name="footer">
                <x-secondary-button wire:click="$set('openModal', false)">Cerrar</x-secondary-button>
                @can('tipo-cambio.actualizar')
                    <x-button class="ml-2" wire:click="actualizar" wire:loading.attr="disabled" wire:target="actualizar">Actualizar</x-button>
                @endcan
            </x-slot>
        </x-dialog-modal>
    @endif
</div>
