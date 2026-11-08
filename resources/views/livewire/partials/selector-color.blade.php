{{--
    El selector de color del equipo (alta y edicion). Recibe:
      $colores  la lista de Color::paraModelo()
      $modelo   la ruta del wire:model ('color' o 'producto.color')
      $actual   el nombre elegido, para repetirlo junto al rotulo

    El elegido se marca con un aro oscuro separado del chip y un ✓: antes era
    un aro brand-500, que en modo claro es gris medio y sobre una muestra de
    color casi no se veia. Las dos marcas son hermanas del radio (peer-checked
    solo alcanza a hermanos), asi responden al instante, sin esperar al servidor.
--}}
<div class="animate-fade-in">
    <div class="flex items-baseline gap-2">
        <x-label value="Color *" />
        @if ($actual)
            <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ $actual }}</span>
        @endif
    </div>
    <div class="grid grid-cols-3 gap-3 mt-2">
        @foreach ($colores as $color)
            <label wire:key="color-{{ $color->id }}" class="relative flex cursor-pointer">
                <input type="radio" wire:model.live="{{ $modelo }}" value="{{ $color->nombre }}" class="peer sr-only"
                    @checked($color->nombre === $actual)>
                <span class="flex w-full items-center justify-center rounded-full border-2 border-black/10 px-3 py-2 text-xs font-medium
                           transition-all duration-150 hover:shadow-md dark:border-white/20
                           peer-checked:scale-105 peer-checked:font-bold peer-checked:shadow-md
                           peer-checked:ring-[3px] peer-checked:ring-gray-900 peer-checked:ring-offset-2
                           dark:peer-checked:ring-white dark:peer-checked:ring-offset-gray-800
                           peer-focus-visible:ring-2 peer-focus-visible:ring-gray-500"
                    style="{{ $color->estilo() }}">{{ $color->nombre }}</span>
                <span class="pointer-events-none absolute -right-1.5 -top-1.5 hidden h-5 w-5 items-center justify-center rounded-full
                           bg-gray-900 text-[10px] text-white shadow ring-2 ring-white peer-checked:flex
                           dark:bg-white dark:text-gray-900 dark:ring-gray-800">
                    <i class="fa-solid fa-check"></i>
                </span>
            </label>
        @endforeach
    </div>
    <x-input-error for="{{ $modelo }}" class="mt-1" />
</div>
