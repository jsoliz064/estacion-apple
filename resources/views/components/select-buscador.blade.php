@props(['options' => [], 'placeholder' => 'Todos', 'id' => null])

@php
    // [valor => texto] a una lista: un objeto JSON con claves numericas lo
    // reordena el navegador por clave y se perderia el orden por nombre.
    $opciones = collect($options)->map(fn($texto, $valor) => ['valor' => (string) $valor, 'texto' => (string) $texto])->values();
@endphp

{{-- Un select con buscador, todo en el navegador: con veinte modelos o mas un
     select comun ya obliga a desplazarse a ciegas en el celular. El valor va
     por @entangle del wire:model que se le pase (con su .live si lo trae).
     El wire:key cambia con la lista, para que Alpine la vuelva a leer si el
     servidor la cambia (x-data solo se evalua al montar). --}}
<div wire:key="select-buscador-{{ $id }}-{{ md5($opciones->toJson()) }}"
    x-data="{
        abierto: false,
        busqueda: '',
        activo: 0,
        valor: @entangle($attributes->wire('model')),
        opciones: @js($opciones),
        vacio: @js($placeholder),
        normalizar(t) { return String(t).toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, ''); },
        get filtradas() {
            const q = this.normalizar(this.busqueda.trim());
            const todas = [{ valor: '', texto: this.vacio }, ...this.opciones];
            return q ? this.opciones.filter(o => this.normalizar(o.texto).includes(q)) : todas;
        },
        get etiqueta() {
            return this.opciones.find(o => o.valor === String(this.valor ?? ''))?.texto ?? this.vacio;
        },
        abrir() {
            this.abierto = true;
            this.busqueda = '';
            this.activo = 0;
            this.$nextTick(() => this.$refs.buscar.focus());
        },
        elegir(o) {
            if (!o) return;
            this.valor = o.valor;
            this.abierto = false;
        },
        mover(paso) {
            const n = this.filtradas.length;
            if (n) this.activo = (this.activo + paso + n) % n;
        },
    }"
    x-on:click.outside="abierto = false" x-on:keydown.escape="abierto = false"
    {{ $attributes->whereDoesntStartWith('wire:model')->merge(['class' => 'relative']) }}>

    <button type="button" @if ($id) id="{{ $id }}" @endif x-on:click="abierto ? abierto = false : abrir()"
        class="mt-1 flex h-10 w-full items-center justify-between gap-2 rounded-md border border-gray-300 bg-white px-3 text-left shadow-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
        <span class="truncate" x-text="etiqueta">{{ $placeholder }}</span>
        <i class="fa-solid fa-chevron-down text-xs text-gray-400"></i>
    </button>

    <div x-show="abierto" x-cloak x-transition.opacity
        class="absolute z-30 mt-1 w-full rounded-md border border-gray-200 bg-white shadow-lg dark:border-gray-600 dark:bg-gray-700">
        <div class="p-2">
            <input type="text" x-ref="buscar" x-model="busqueda" x-on:input="activo = 0"
                x-on:keydown.arrow-down.prevent="mover(1)" x-on:keydown.arrow-up.prevent="mover(-1)"
                x-on:keydown.enter.prevent="elegir(filtradas[activo])"
                placeholder="Buscar…" autocomplete="off"
                class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
        </div>
        <ul class="max-h-60 overflow-y-auto pb-1 text-sm">
            <template x-for="(o, i) in filtradas" :key="o.valor">
                <li>
                    <button type="button" x-on:click="elegir(o)" x-on:mouseenter="activo = i"
                        class="flex w-full items-center justify-between px-3 py-2 text-left text-gray-700 dark:text-gray-200"
                        :class="activo === i ? 'bg-gray-100 dark:bg-gray-600' : ''">
                        <span x-text="o.texto"></span>
                        <i x-show="o.valor === String(valor ?? '')" class="fa-solid fa-check text-xs text-brand-600"></i>
                    </button>
                </li>
            </template>
            <li x-show="filtradas.length === 0" class="px-3 py-2 text-gray-500 dark:text-gray-400">Sin resultados.</li>
        </ul>
    </div>
</div>
