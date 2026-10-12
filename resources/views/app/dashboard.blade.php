<x-main-layout>
    @if (auth()->user()->can('dashboard.index'))
        @livewire('dashboard.dashboard-index')
    @else
        <div class="ph-full ml-0 mt-14 mb-10 md:ml-0">
            <h1 class="text-lg font-semibold text-gray-600 dark:text-gray-200">
                Bienvenido: {{ auth()->user()->name }}
            </h1>
        </div>
    @endif
</x-main-layout>
