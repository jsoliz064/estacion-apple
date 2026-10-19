<div>
    <h2 class="text-center text-2xl font-bold text-gray-800 dark:text-white mb-6">Roles</h2>
    @can('rol.create')
        {{-- Crear y editar van a su pantalla completa (RoleForm), no a un modal. --}}
        <a href="{{ route('roles.crear') }}"
            class="inline-flex items-center px-4 py-2 bg-brand-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest shadow-sm hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 transition ease-in-out duration-150">
            Crear Rol
        </a>
    @endcan
    <div class="mt-4">
        @livewire('role.role-table')
    </div>
    @livewire('role.modals.role-destroy-modal')
</div>
