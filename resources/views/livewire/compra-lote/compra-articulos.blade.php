<div>
    @php($puedeEditar = auth()->user()->can('compra.edit'))

    @if ($puedeEditar)
        <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
            <p class="text-xs text-gray-500 dark:text-gray-400">
                @if ($compra->esBorrador())
                    Cada artículo se guarda al momento. El stock entra al finalizar la compra.
                @else
                    Compra finalizada: cada cambio mueve el stock de la sucursal al instante.
                @endif
            </p>
            <div class="flex gap-2">
                @can('repuesto.create')
                    <button type="button" wire:click="abrirCrearArticulo('Repuesto')"
                        class="text-xs px-2 py-1 rounded border border-brand-300 text-brand-700 hover:bg-brand-50 dark:text-brand-300">+ Repuesto nuevo</button>
                @endcan
                @can('accesorio.create')
                    <button type="button" wire:click="abrirCrearArticulo('Accesorio')"
                        class="text-xs px-2 py-1 rounded border border-brand-300 text-brand-700 hover:bg-brand-50 dark:text-brand-300">+ Accesorio nuevo</button>
                @endcan
            </div>
        </div>

        @include('livewire.partials.buscador-articulos', ['placeholder' => 'Escanee o escriba código, SKU o nombre...', 'escanerContinuo' => true])
        <x-input-error for="detalles" class="mt-1" />
    @endif

    <div class="mt-3 overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200">
                <tr>
                    <th class="p-2 text-left">Artículo</th>
                    <th class="p-2 text-right w-24">Cantidad</th>
                    <th class="p-2 text-right w-32">Costo unit. Bs</th>
                    <th class="p-2 text-right w-28">Subtotal</th>
                    @if ($puedeEditar)
                        <th class="p-2 w-10"></th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse ($detalles as $d)
                    {{-- La clave es el id de la linea: el wire:model se enlaza por ese id, no por posicion. --}}
                    <tr class="border-t border-gray-200 dark:border-gray-700" wire:key="cd-art-{{ $d->id }}">
                        <td class="p-2">
                            {!! \App\Enums\LineaTipo::badge($d->tipo) !!}
                            <span class="text-gray-900 dark:text-gray-100">{{ $d->articulo()?->nombre }}</span>
                            @if ($d->articulo()?->sku)
                                <span class="text-xs text-gray-500">· {{ $d->articulo()->sku }}</span>
                            @endif
                        </td>
                        @if ($puedeEditar && isset($lineas[$d->id]))
                            <td class="p-2"><x-input type="number" min="1" class="w-full text-right" wire:model.blur="lineas.{{ $d->id }}.cantidad" onfocus="this.select()" /></td>
                            <td class="p-2"><x-input type="number" min="0" step="0.01" class="w-full text-right" wire:model.blur="lineas.{{ $d->id }}.costo" onfocus="this.select()" /></td>
                        @else
                            <td class="p-2 text-right">{{ $d->cantidad }}</td>
                            <td class="p-2 text-right">{{ number_format((float) $d->costo, 2) }}</td>
                        @endif
                        <td class="p-2 text-right">{{ number_format((float) $d->subtotal, 2) }}</td>
                        @if ($puedeEditar)
                            <td class="p-2 text-center">
                                <button type="button" wire:click="quitar({{ $d->id }})" wire:confirm="¿Quitar {{ $d->articulo()?->nombre }} de la compra?"
                                    class="text-red-600 hover:text-red-800 text-lg" title="Quitar">&times;</button>
                            </td>
                        @endif
                    </tr>
                    @error("lineas.{$d->id}.cantidad")
                        <tr wire:key="cd-art-err-c-{{ $d->id }}"><td colspan="5" class="px-2 text-sm text-red-600">{{ $message }}</td></tr>
                    @enderror
                    @error("lineas.{$d->id}.costo")
                        <tr wire:key="cd-art-err-k-{{ $d->id }}"><td colspan="5" class="px-2 text-sm text-red-600">{{ $message }}</td></tr>
                    @enderror
                @empty
                    <tr>
                        <td colspan="5" class="p-4 text-center text-gray-400">
                            Sin repuestos ni accesorios. Una compra puede traer solo equipos.
                        </td>
                    </tr>
                @endforelse
            </tbody>
            @if ($detalles->isNotEmpty())
                <tfoot>
                    <tr class="border-t border-gray-300 dark:border-gray-600 font-semibold">
                        <td class="p-2">Total de artículos</td>
                        <td class="p-2 text-right">{{ $detalles->sum('cantidad') }}</td>
                        <td></td>
                        <td class="p-2 text-right">Bs {{ number_format((float) $detalles->sum('subtotal'), 2) }}</td>
                        @if ($puedeEditar)
                            <td></td>
                        @endif
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
</div>
