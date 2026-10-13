<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Una tanda de codigos de barras generados juntos (lo que se imprime de una
 * vez). Solo lo escribe CodigoBarrasService.
 */
class CodigoLote extends Model
{
    protected $table = 'codigos_lotes';
    protected $guarded = ['id'];

    protected $casts = [
        'cantidad' => 'integer',
        'copias' => 'integer',
    ];

    public function codigos()
    {
        return $this->hasMany(CodigoBarras::class, 'lote_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
