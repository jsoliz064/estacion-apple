@php
    $marcados = count($seleccionados);
    $tarjeta = 'bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700';
@endphp
<div class="pb-24">
    <a href="{{ route('roles') }}"
        class="inline-flex items-center gap-2 px-4 py-2 rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-xs font-semibold uppercase tracking-widest text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">
        <i class="fa-solid fa-arrow-left"></i> Volver
    </a>

    <h2 class="text-center text-2xl font-bold text-gray-800 dark:text-white my-6">
        {{ $roleId ? 'Editar rol' : 'Crear rol' }}
    </h2>

    {{-- Nombre, buscador y contador: en el celular se apilan. --}}
    <div class="{{ $tarjeta }} p-4 grid grid-cols-1 md:grid-cols-3 gap-4 md:items-end">
        <div>
            <x-label>Nombre del rol</x-label>
            <x-input type="text" wire:model="nombre" class="w-full" maxlength="255" placeholder="Vendedor, Técnico..." />
            <x-input-error for="nombre" />
        </div>
        <div>
            <x-label>Buscar permiso</x-label>
            <x-input type="search" wire:model.live.debounce.300ms="buscar" class="w-full" placeholder="venta, producto.estado..." />
        </div>
        <p class="text-sm text-gray-600 dark:text-gray-300 md:text-right md:pb-2">
            <span class="font-bold text-gray-900 dark:text-white">{{ $marcados }}</span> de {{ $total }} permisos
        </p>
        <x-input-error for="seleccionados.*" class="md:col-span-3" />
    </div>

    @if ($grupos->isEmpty())
        <p class="mt-6 text-center text-gray-500 dark:text-gray-400">Ningún permiso coincide con «{{ $buscar }}».</p>
    @endif

    <div class="mt-4 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
        @foreach ($grupos as $clave => $grupo)
            @php
                $nombres = $grupo['permisos']->pluck('name');
                $enGrupo = $nombres->intersect($seleccionados)->count();
                $completo = $enGrupo === $nombres->count();
            @endphp
            <div class="{{ $tarjeta }} p-4" wire:key="grupo-{{ $clave }}">
                <div class="flex items-start justify-between gap-2 mb-3">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                        {{ $grupo['nombre'] }}
                        <span class="ml-1 text-sm font-normal text-gray-500 dark:text-gray-400">{{ $enGrupo }}/{{ $nombres->count() }}</span>
                    </h3>
                    <button type="button" wire:click="alternarGrupo(@js($clave))"
                        class="shrink-0 px-2.5 py-1 rounded-md text-xs font-medium bg-brand-100 text-brand-800 hover:bg-brand-200 dark:bg-gray-700 dark:text-gray-100 dark:hover:bg-gray-600">
                        {{ $completo ? 'Ninguno' : 'Todos' }}
                    </button>
                </div>

                <div class="space-y-1.5">
                    @foreach ($grupo['permisos'] as $permiso)
                        <label class="flex items-center gap-2 cursor-pointer" wire:key="perm-{{ $permiso->id }}">
                            <x-checkbox wire:model.live="seleccionados" value="{{ $permiso->name }}" />
                            <span class="text-sm text-gray-700 dark:text-gray-200 break-all">{{ $permiso->name }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>

    {{-- Fija abajo: en el celular, guardar queda a mano sin bajar por las tarjetas.
         fixed y no sticky: el contenedor del layout es overflow-auto pero no tiene
         altura propia (scrollea la pagina), asi que un sticky nunca se pegaba.
         isSidebarOpen es el del layout, como el md:ml-64 de <main>. --}}
    <div class="fixed bottom-0 inset-x-0 z-10 px-6 py-3 bg-brand-50/95 dark:bg-gray-700/95 border-t border-gray-200 dark:border-gray-600 flex justify-end gap-2"
        :class="isSidebarOpen ? 'md:left-64' : ''">
        <a href="{{ route('roles') }}"
            class="inline-flex items-center px-4 py-2 rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-xs font-semibold uppercase tracking-widest text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">
            Cancelar
        </a>
        <x-primary-button wire:click="guardar()" wire:loading.attr="disabled" wire:target="guardar">
            {{ $roleId ? 'Guardar cambios' : 'Crear rol' }}
        </x-primary-button>
    </div>
</div>
