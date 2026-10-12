@aware(['isTailwind', 'isBootstrap'])

{{-- Las tablas con codigos (productos, repuestos, accesorios, lotes) declaran
     public bool $buscarConEscaner = true y ganan el boton de camara: el lector
     escribe el codigo en el campo y la busqueda corre como si se tecleara. --}}
@php($conEscaner = property_exists($this, 'buscarConEscaner') && $this->buscarConEscaner)

<div
    @class([
        'mb-3 mb-md-0 input-group' => $isBootstrap,
        'rounded-md shadow-sm' => $isTailwind,
        'flex' => ($isTailwind && !$this->hasSearchIcon),
        'relative inline-flex flex-row' => $this->hasSearchIcon,
    ])
    @if ($conEscaner) data-escaner @endif>

        @if($this->hasSearchIcon)
            <x-livewire-tables::tools.toolbar.items.search.icon :searchIcon="$this->getSearchIcon" :searchIconClasses="$this->getSearchIconClasses" :searchIconOtherAttributes="$this->getSearchIconOtherAttributes"  />
        @endif

        <x-livewire-tables::tools.toolbar.items.search.input />

        @if ($this->hasSearch)
            <x-livewire-tables::tools.toolbar.items.search.remove />
        @endif

        @if ($conEscaner)
            <x-boton-escaner modo="input" class="ml-2" />
        @endif
</div>
