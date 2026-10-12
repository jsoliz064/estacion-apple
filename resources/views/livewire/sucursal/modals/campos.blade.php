{{-- Campos compartidos por crear y editar. $esAlmacen bloquea nombre y estado:
     el codigo busca el Almacen por nombre y no puede quedar inactivo. --}}
@php($esAlmacen = $esAlmacen ?? false)

<div class="m-2">
    <x-label>Nombre:</x-label>
    <x-input type="text" wire:model="sucursal.nombre" class="w-full" :disabled="$esAlmacen"></x-input>
    @if ($esAlmacen)
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">El Almacén es obligatorio: su nombre no se puede cambiar.</p>
    @endif
    <x-input-error for="sucursal.nombre"></x-input-error>
</div>

<div class="m-2">
    <x-label>Dirección:</x-label>
    <x-input type="text" wire:model="sucursal.direccion" class="w-full" placeholder="Se imprime en la nota de venta"></x-input>
    <x-input-error for="sucursal.direccion"></x-input-error>
</div>

<div class="m-2">
    <x-label>Teléfono:</x-label>
    <x-input type="text" wire:model="sucursal.telefono" class="w-full" placeholder="Se imprime en la nota de venta"></x-input>
    <x-input-error for="sucursal.telefono"></x-input-error>
</div>

<div class="m-2">
    <label class="inline-flex items-center gap-2">
        <x-checkbox wire:model="sucursal.activa" :disabled="$esAlmacen" />
        <span class="text-sm text-gray-700 dark:text-gray-300">Activa</span>
    </label>
    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
        Una sucursal inactiva deja de ofrecerse al cargar productos o ventas, pero sus ventas viejas siguen en los reportes.
    </p>
</div>
