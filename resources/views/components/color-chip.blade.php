@props(['color', 'tam' => 'sm'])
{{--
    La muestra de un color de equipo (App\Models\Color). Estilo inline: Tailwind
    no tiene safelist, asi que un bg-[#xxx] armado en el servidor no existiria.
    Reemplaza al var(--color-negro) de antes, que no estaba definido en ningun
    lado y dejaba las muestras sin color.
--}}
<span {{ $attributes->class([
    'inline-flex items-center justify-center rounded-full border border-gray-300 dark:border-gray-600 font-medium',
    'px-3 py-1 text-xs' => $tam === 'sm',
    'w-5 h-5' => $tam === 'punto',
]) }} style="{{ $color->estilo() }}"
    title="{{ $color->nombre }}">@if ($tam !== 'punto'){{ $color->nombre }}@endif</span>
