<div>
    <select
        {{ $attributes->merge(['class' => 'w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-brand-500 focus:border-brand-500 dark:focus:ring-brand-600 dark:focus:border-brand-600']) }}>
        @if ($placeholder)
            <option value="" selected>{{ $placeholder }}</option>
        @endif

        @foreach ($options as $option)
            <option value="{{ $option->id }}">{{ $option->nombre }}</option>
        @endforeach
    </select>
</div>
