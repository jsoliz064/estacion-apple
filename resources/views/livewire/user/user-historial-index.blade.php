<div>
    <div>
        <a href="{{ route('users') }}"
            class="bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded-full">
            Ir a Usuarios</a>
    </div>

    <h2 class="text-center text-2xl font-bold text-gray-800 dark:text-white mt-4 mb-1">
        Historial de {{ $usuario->name }}
    </h2>
    <p class="text-center text-sm text-gray-500 dark:text-gray-400 mb-6">
        {{ $usuario->email }}
    </p>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <div
            class="bg-brand-100 dark:bg-brand-900 p-6 rounded-2xl shadow-lg border border-brand-200 dark:border-brand-700">
            <h3 class="text-sm font-medium text-brand-700 dark:text-brand-300">Acciones registradas</h3>
            <p class="mt-1 text-2xl font-semibold tracking-tight text-brand-900 dark:text-brand-100">
                {{ $acciones }}
            </p>
            {{-- La aclaracion importa: antes de la bitacora solo se anotaban los
                 cambios de estado de los telefonos, y el numero pareceria corto. --}}
            <p class="mt-1 text-xs text-brand-700 dark:text-brand-300">
                Antes de la bitácora solo quedaban registrados los cambios de estado de los teléfonos.
            </p>
        </div>

        <div
            class="bg-green-100 dark:bg-green-900 p-6 rounded-2xl shadow-lg border border-green-200 dark:border-green-700">
            <h3 class="text-sm font-medium text-green-700 dark:text-green-300">Hoy</h3>
            <p class="mt-1 text-2xl font-semibold tracking-tight text-green-900 dark:text-green-100">
                {{ $accionesHoy }}
            </p>
        </div>

        <div
            class="bg-gray-100 dark:bg-gray-900 p-6 rounded-2xl shadow-lg border border-gray-200 dark:border-gray-700">
            <h3 class="text-sm font-medium text-gray-600 dark:text-gray-400">Última acción</h3>
            <p class="mt-1 text-2xl font-semibold tracking-tight text-gray-900 dark:text-white">
                {{ $ultimaAccion ? \Carbon\Carbon::parse($ultimaAccion)->format('d/m/Y H:i') : '—' }}
            </p>
        </div>
    </div>

    <h3 class="font-semibold text-gray-800 dark:text-gray-100 mb-2">
        Comisiones ({{ rtrim(rtrim(number_format((float) $usuario->comision_porcentaje, 2), '0'), '.') }} % de la ganancia)
    </h3>
    <x-comision-cifras :cifras="$cifras" class="mb-6" />

    <div class="flex gap-2 border-b border-gray-200 dark:border-gray-700">
        @foreach (['comisiones' => 'Comisiones', 'bitacora' => 'Bitácora'] as $clave => $titulo)
            <button type="button" wire:click="verPestana('{{ $clave }}')" @class([
                'px-4 py-2 text-sm font-semibold border-b-2 -mb-px',
                'border-brand-600 text-brand-700 dark:text-brand-300' => $pestana === $clave,
                'border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300' => $pestana !== $clave,
            ])>{{ $titulo }}</button>
        @endforeach
    </div>

    <div class="mt-4">
        @if ($pestana === 'comisiones')
            @livewire('comision.comision-table', ['usuarioId' => $usuario->id], key('comisiones-usuario-' . $usuario->id))
        @else
            @livewire('bitacora.bitacora-table', ['usuarioId' => $usuario->id], key('bitacora-usuario-' . $usuario->id))
        @endif
    </div>
</div>
