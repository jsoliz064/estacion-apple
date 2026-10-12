@props(['options' => [], 'placeholder' => null])

@php
    // Mismo criterio que x-input: un select bloqueado tiene que verse bloqueado.
    $bloqueado = $attributes->has('disabled');

    $estado = $bloqueado
        ? 'bg-gray-100 text-gray-500 border-gray-200 cursor-not-allowed dark:bg-gray-800 dark:text-gray-400 dark:border-gray-700'
        : 'border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:ring-brand-500 focus:border-brand-500 dark:focus:ring-brand-600 dark:focus:border-brand-600';
@endphp

<div>
    <select {{ $attributes->merge(['class' => 'w-full rounded-md shadow-sm ' . $estado]) }}>
        @if ($placeholder)
            <option value="" selected>{{ $placeholder }}</option>
        @endif

        @foreach ($options as $value => $text)
            <option value="{{ $value }}">{{ $text }}</option>
        @endforeach
    </select>
</div>
