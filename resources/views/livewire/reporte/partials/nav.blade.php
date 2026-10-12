{{-- Las pestanas entre reportes. $actual = nombre de ruta del reporte abierto. --}}
<nav class="mb-6 flex flex-wrap gap-2 border-b border-gray-200 dark:border-gray-700">
    @foreach (\App\Http\Controllers\ReporteController::REPORTES as $ruta => [$permiso, $titulo])
        @can($permiso)
            <a href="{{ route($ruta) }}" @class([
                'px-4 py-2 text-sm font-semibold border-b-2 -mb-px',
                'border-brand-600 text-brand-700 dark:text-brand-300' => $actual === $ruta,
                'border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300' => $actual !== $ruta,
            ])>{{ $titulo }}</a>
        @endcan
    @endforeach
</nav>
