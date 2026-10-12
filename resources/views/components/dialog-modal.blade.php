@props(['id' => null, 'maxWidth' => null])

<x-modal :id="$id" :maxWidth="$maxWidth" {{ $attributes }}>
    {{-- El fondo principal se aplica aquí para cubrir tanto el contenido como el footer --}}
    <div class="bg-white dark:bg-gray-800">

        <div class="px-6 py-4">
            <div class="text-lg font-medium text-gray-900 dark:text-gray-100">
                {{ $title }}
            </div>

            <div class="mt-4 text-sm text-gray-600 dark:text-gray-400">
                {{ $content }}
            </div>
        </div>

        {{-- El footer tiene un fondo ligeramente distinto para separarse visualmente --}}
        <div class="flex flex-row justify-end px-6 py-4 bg-gray-100 dark:bg-gray-700 text-end">
            {{ $footer }}
        </div>
        
    </div>
</x-modal>