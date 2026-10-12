<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;

/**
 * La ficha de un cliente.
 *
 * Sustituye al nombre escrito a mano en `ventas.cliente`. Esa columna sigue ahi
 * como archivo de lo que se escribio en cada venta; quien manda para mostrar y
 * para navegar es esta ficha (ver Venta::nombreCliente()).
 *
 * Solo $guarded y NUNCA $fillable: declarar los dos es la trampa que el
 * CLAUDE.md documenta -- gana $fillable, y una columna que falte ahi la descarta
 * create() en silencio. Ya se cobro a mano_obra y a clave_idempotencia.
 */
class Cliente extends Model
{
    use Auditable;

    protected $table = 'clientes';
    protected $guarded = ['id'];

    /** Sus ventas: equipos, repuestos y accesorios van en el mismo documento. */
    public function ventas()
    {
        return $this->hasMany(Venta::class, 'cliente_id');
    }

    public function reservas()
    {
        return $this->hasMany(Reserva::class, 'cliente_id');
    }

    /** Lo que debe: la suma de los saldos de sus ventas a credito. */
    public function deuda(): float
    {
        return round((float) $this->ventas()->conSaldo()->sum('saldo'), 2);
    }

    /**
     * Las lineas de equipo con garantia vigente (vence hoy o despues), con su
     * venta y su producto: es lo que se mira cuando el cliente vuelve.
     */
    public function garantiasVigentes()
    {
        return VentaDetalle::query()
            ->with(['producto.modelo', 'venta'])
            ->whereNotNull('producto_id')
            ->whereDate('garantia_fecha_exp', '>=', now()->toDateString())
            ->whereHas('venta', fn($q) => $q->where('cliente_id', $this->id))
            ->orderBy('garantia_fecha_exp')
            ->get();
    }

    /** Cuantas ordenes tiene, para el modal de eliminar. */
    public function cantidadOrdenes(): int
    {
        return $this->ventas()->count();
    }

    /**
     * El telefono como lo pide wa.me: solo digitos, con el 591 delante si es un
     * celular boliviano de 8 cifras. Null si no parece un numero.
     */
    public function telefonoWhatsapp(): ?string
    {
        $digitos = preg_replace('/\D+/', '', (string) $this->telefono);

        if (strlen($digitos) === 8) {
            return '591' . $digitos;
        }

        return strlen($digitos) >= 10 && strlen($digitos) <= 15 ? $digitos : null;
    }
}
