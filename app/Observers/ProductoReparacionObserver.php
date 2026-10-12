<?php

namespace App\Observers;

use App\Models\ProductoReparacion;
use App\Services\ComisionService;

/**
 * Mantiene la comision del tecnico al dia con su reparacion. Una reparacion se
 * crea, se termina y se edita desde ProductoEstadoModal,
 * ProductoReparacionClienteModal, TecnicoTerminarModal, ReparacionEditModal y
 * CompraLoteAddModelModal; engancharse en cada una era la copia que diverge.
 *
 * El Observer no ve el query builder: productos_reparaciones se escribe
 * siempre con Eloquent (el UPDATE masivo de "pagado" se fue con esta etapa).
 */
class ProductoReparacionObserver
{
    public function saved(ProductoReparacion $reparacion): void
    {
        app(ComisionService::class)->sincronizarReparacion($reparacion);
    }
}
