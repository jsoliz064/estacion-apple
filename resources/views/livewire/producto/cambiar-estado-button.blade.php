<div>
    {{-- Un equipo dado de baja no cambia de estado (EstadoProductoService lo
         rechaza): se pinta su baja, no un boton que fallaria. --}}
    @if ($dadoDeBaja ?? false)
        <span class="flex items-center justify-center"
            style="border: 1px dashed gray; border-radius: 5px; padding: 5px">
            <span style="color: gray">Dado de baja</span>
        </span>
    @elseif ($canChangeState)
        <button wire:click="cambiarEstado({{ $id }})" type="button"
            class="flex items-center justify-center cursor-pointer hover:scale-105 transition-transform"
            style="border: 1px solid {{ $color }}; border-radius: 5px; padding: 5px">
            <span style="color: {{ $color }}">{{ \App\Enums\ProductoEstado::labelDe($estado) }}</span>
        </button>
    @else
        <span class="flex items-center justify-center"
            style="border: 1px solid {{ $color }}; border-radius: 5px; padding: 5px">
            <span style="color: {{ $color }}">{{ \App\Enums\ProductoEstado::labelDe($estado) }}</span>
        </span>
    @endif
</div>
