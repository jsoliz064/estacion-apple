<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Support\Facades\DB;

class User extends Authenticatable
{
    use Auditable;
    use HasApiTokens, HasRoles;

    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory;
    use HasProfilePhoto;
    use Notifiable;
    use TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'comision_porcentaje',
        'activo',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'profile_photo_url',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'activo' => 'boolean',
            'comision_porcentaje' => 'decimal:2',
        ];
    }

    /**
     * Cuantos documentos llevan su firma. Las FK de ventas, ventas de
     * repuestos, compras de repuestos y transferencias hacia users son
     * nullOnDelete: borrarlo no fallaria, dejaria sus ventas sin vendedor (y sin
     * comision) en silencio. Por eso con movimientos no se borra, se desactiva
     * (docs/01).
     */
    public function cantidadMovimientos(): int
    {
        return DB::table('ventas')->where('user_id', $this->id)->count()
            + DB::table('compras')->where('user_id', $this->id)->count()
            + DB::table('stock_transferencias')->where('user_id', $this->id)->count()
            + DB::table('stock_bajas')->where('user_id', $this->id)->count()
            + DB::table('productos_regalos')->where('user_id', $this->id)->count()
            // La comision liquidada sobrevive a su venta anulada.
            + DB::table('comisiones')->where('user_id', $this->id)->count()
            + DB::table('comisiones_liquidaciones')->where('user_id', $this->id)->count();
    }

    public function comisiones()
    {
        return $this->hasMany(Comision::class, 'user_id');
    }

    /** El tecnico vinculado a este usuario, si lo hay. */
    public function tecnico()
    {
        return $this->hasOne(Tecnicos::class, 'user_id');
    }

    /**
     * Lo deja sin acceso: no podra volver a entrar (FortifyServiceProvider) y
     * sus sesiones abiertas se borran ya, sin esperar a que caduquen. El
     * middleware UsuarioActivo cubre ademas la cookie de "recordarme".
     */
    public function desactivar(): void
    {
        $this->update(['activo' => false]);

        DB::table(config('session.table', 'sessions'))->where('user_id', $this->id)->delete();
    }

    public function rol_name()
    {
        return !$this->getRoleNames()->isEmpty() ? $this->getRoleNames()[0] : 'sin rol';
    }

    //metodo para obtener el rol de un usuario
    public function rol_id()
    {
        $rol = DB::table('roles')
            ->join('model_has_roles', 'role_id', '=', 'roles.id')
            ->join('users', 'users.id', '=', 'model_has_roles.model_id')
            ->where('users.id', '=', $this->id)
            ->select('roles.id')
            ->get()
            ->first();
        if (!$rol) {
            return null;
        }
        return $rol->id;
    }
}
