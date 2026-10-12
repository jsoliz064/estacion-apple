<div>
    @if ($openModal)
        <x-dialog-modal wire:model="openModal">
            <x-slot name="title">
                Editar Rol: {{ isset($role['name']) ? $role['name'] : '' }}
            </x-slot>

            <x-slot name="content">
                <hr class="my-4 border-gray-300">

                <div class="m-2">
                    <x-label>Nombre:</x-label>
                    <x-input type="text" wire:model="role.name" class="w-full"></x-input>
                    <x-input-error for="role.name"></x-input-error>
                </div>

                <div class="my-4">
                    <label for="permissions" class="block font-medium text-sm text-gray-700 mb-2">
                        Permisos del rol:
                    </label>
                    @php
                        $columnCount = 3;
                        $permissionsCount = $permissions->count();
                        $columnSize = ceil($permissionsCount / $columnCount);
                        $permissionsChunks = $permissions->chunk($columnSize);
                    @endphp
                    <div class="grid grid-cols-1 md:grid-cols-{{ $columnCount }} gap-4">
                        @foreach ($permissionsChunks as $permissionsChunk)
                            <div>
                                @foreach ($permissionsChunk as $permission)
                                    <div class="flex items-center mb-2">
                                        <input type="checkbox"
                                               id="{{ $permission['id'] }}"
                                               value="{{ $permission['id'] }}"
                                               wire:model="selectedPermissions"
                                               @if (in_array($permission['id'], $selectedPermissions)) checked @endif
                                               class="h-4 w-4 text-brand-600 focus:ring-brand-500 border-gray-300 rounded">
                                        <label for="{{ $permission['id'] }}" class="ml-2 text-sm text-gray-700">
                                            {{ $permission['name'] }}
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                </div>
            </x-slot>

            <x-slot name="footer">
                <x-secondary-button wire:click="closeModal()" wire:loading.attr="disabled">
                    Cancelar
                </x-secondary-button>
                <x-primary-button class="ml-2" wire:click="update()" wire:loading.attr="disabled">
                    Actualizar
                </x-primary-button>
            </x-slot>
        </x-dialog-modal>
    @endif
</div>
