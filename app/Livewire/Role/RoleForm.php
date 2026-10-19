<?php

namespace App\Livewire\Role;

use App\Support\PermisosPorModulo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Crear o editar un rol en pantalla completa, con los permisos agrupados por
 * modulo. Reemplaza a los dos modales de antes, que mostraban los 113
 * permisos en una lista plana y no comprobaban el permiso en el servidor:
 * el @can solo escondia el boton.
 */
class RoleForm extends Component
{
    /** El rol del seeder: PermissionSeeder lo busca por este nombre. */
    public const ADMINISTRADOR = 'Administrador';

    #[Locked]
    public ?int $roleId = null;

    public string $nombre = '';

    /** Nombres de permiso marcados. No dependen del buscador: lo oculto sigue marcado. */
    public array $seleccionados = [];

    public string $buscar = '';

    public function mount(?int $roleId = null): void
    {
        $this->roleId = $roleId;
        abort_unless(Auth::user()?->can($this->permisoNecesario()), 403);

        if ($roleId) {
            $role = Role::with('permissions:id,name')->findOrFail($roleId);
            $this->nombre = $role->name;
            $this->seleccionados = $role->permissions->pluck('name')->all();
        }
    }

    private function permisoNecesario(): string
    {
        return $this->roleId ? 'rol.edit' : 'rol.create';
    }

    /**
     * Marca todos los permisos VISIBLES del grupo, o los desmarca si ya
     * estaban todos. Con el buscador activo solo toca lo que se ve.
     */
    public function alternarGrupo(string $clave): void
    {
        $visibles = $this->visibles()
            ->filter(fn($p) => PermisosPorModulo::clave($p->name) === $clave)
            ->pluck('name')
            ->all();

        $todos = array_diff($visibles, $this->seleccionados) === [];

        $this->seleccionados = $todos
            ? array_values(array_diff($this->seleccionados, $visibles))
            : array_values(array_unique([...$this->seleccionados, ...$visibles]));
    }

    public function guardar()
    {
        abort_unless(Auth::user()?->can($this->permisoNecesario()), 403);

        $this->nombre = trim($this->nombre);

        $this->validate([
            'nombre' => [
                'required', 'string', 'max:255',
                Rule::unique('roles', 'name')->where('guard_name', 'web')->ignore($this->roleId),
            ],
            'seleccionados' => 'array',
            'seleccionados.*' => 'string|exists:permissions,name',
        ], [
            'nombre.required' => 'Escriba el nombre del rol.',
            'nombre.unique' => 'Ya existe un rol con ese nombre.',
            'seleccionados.*.exists' => 'Uno de los permisos ya no existe. Recargue la página.',
        ]);

        $role = $this->roleId ? Role::findOrFail($this->roleId) : null;

        // PermissionSeeder le da todos los permisos buscandolo por nombre:
        // renombrado, el seeder fallaria y el rol quedaria sin los nuevos.
        if ($role && $role->name === self::ADMINISTRADOR && $this->nombre !== self::ADMINISTRADOR) {
            $this->addError('nombre', 'El rol Administrador no se puede renombrar.');

            return;
        }

        DB::transaction(function () use (&$role) {
            $role ??= Role::create(['name' => $this->nombre, 'guard_name' => 'web']);
            $role->update(['name' => $this->nombre]);
            // Tambien limpia la cache de permisos de spatie.
            $role->syncPermissions($this->seleccionados);
        });

        toastr()->success($this->roleId ? 'Rol actualizado.' : 'Rol creado.');

        return $this->redirectRoute('roles');
    }

    /** Los permisos que deja ver el buscador (por su nombre o por el de su grupo). */
    private function visibles()
    {
        $permisos = Permission::orderBy('name')->get(['id', 'name']);
        $termino = Str::lower(trim($this->buscar));

        if ($termino === '') {
            return $permisos;
        }

        return $permisos->filter(fn($p) => str_contains(Str::lower($p->name), $termino)
            || str_contains(Str::lower(PermisosPorModulo::nombre(PermisosPorModulo::clave($p->name))), $termino));
    }

    public function render()
    {
        return view('livewire.role.role-form', [
            'grupos' => PermisosPorModulo::agrupar($this->visibles()),
            'total' => Permission::count(),
        ]);
    }
}
