<div>
    @if ($openModal)
        <x-dialog-modal wire:model="openModal">
            <x-slot name="title">
                Eliminar Usuario: {{ $user->name }}
            </x-slot>

            <x-slot name="content">
                <hr>
                <div class="m-2">
                    <x-label>Nombre:</x-label>
                    <x-input type="text" value="{{ $user->name }}" class="w-full" disabled="true"></x-input>
                </div>

                <div class="m-2">
                    <x-label>Correo:</x-label>
                    <x-input type="email" value="{{ $user->email }}" class="w-full" disabled="true"></x-input>
                </div>

                <div class="m-2">
                    <x-label>Rol:</x-label>
                    <x-input type="text" value="{{ $user->rol_name() }}" class="w-full" disabled="true"></x-input>
                </div>

                @if ($esUnoMismo)
                    <p class="m-2 text-sm text-red-600 dark:text-red-400">No puedes eliminar ni desactivar tu propio usuario.</p>
                @elseif ($movimientos > 0)
                    {{-- El aviso ANTES de pulsar: las FK hacia users son nullOnDelete,
                         y borrar dejaria sus ventas sin vendedor en silencio. --}}
                    <div
                        class="m-2 rounded-lg border border-yellow-300 bg-yellow-50 p-3 text-sm text-yellow-900 dark:border-yellow-700 dark:bg-yellow-900/40 dark:text-yellow-100">
                        <p class="font-semibold">
                            Este usuario tiene {{ $movimientos }} venta(s), compra(s) o transferencia(s) registradas.
                        </p>
                        <p class="mt-1">
                            No se puede eliminar sin dejar esos registros sin responsable.
                            @if ($user->activo)
                                Puedes desactivarlo: no podrá entrar al sistema y su historial se conserva.
                            @else
                                Ya está desactivado.
                            @endif
                        </p>
                    </div>
                @else
                    <p class="m-2 text-sm text-gray-600 dark:text-gray-300">
                        No tiene ningún movimiento registrado, así que se puede eliminar sin consecuencias.
                    </p>
                @endif
            </x-slot>
            <x-slot name="footer">
                <x-secondary-button wire:click="closeModal()" wire:loading.attr="disabled">
                    Cancelar
                </x-secondary-button>
                @unless ($esUnoMismo)
                    @if ($movimientos > 0)
                        @if ($user->activo)
                            @can('user.desactivar')
                                <x-danger-button class="ml-2" wire:click="desactivar()" wire:loading.attr="disabled">
                                    Desactivar
                                </x-danger-button>
                            @endcan
                        @endif
                    @else
                        <x-danger-button class="ml-2" wire:click="destroy()" wire:loading.attr="disabled">
                            Eliminar
                        </x-danger-button>
                    @endif
                @endunless
            </x-slot>
        </x-dialog-modal>
    @endif
</div>
