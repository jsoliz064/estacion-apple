<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tecnicos extends Model
{
    protected $table = 'tecnicos';
    protected $guarded = ['id'];

    public function reparaciones()
    {
        return $this->hasMany(ProductoReparacion::class, 'tecnico_id');
    }

    /** Su usuario del sistema, para «Mis comisiones». */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function comisiones()
    {
        return $this->hasMany(Comision::class, 'tecnico_id');
    }
}
