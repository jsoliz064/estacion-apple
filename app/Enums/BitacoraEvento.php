<?php

namespace App\Enums;

use Illuminate\Support\Collection;

/**
 * Los eventos de la bitacora que NO son un estado de producto.
 *
 * La columna `evento` guarda dos familias: los estados de ProductoEstado
 * ('Vendido', 'Reparacion'...), cuando el hecho movio el estado de un telefono,
 * y estos, cuando no. Estan separados a proposito: un hecho que no es un cambio
 * de estado NO debe escribirse con nombre de estado, porque entonces contradice
 * al estado real -- es exactamente lo que hacia ProductoReparacionClienteModal
 * escribiendo 'Reparacion' sobre telefonos vendidos, y el auditor lo cazaba.
 *
 * OJO con la colacion: utf8mb4_unicode_ci no distingue mayusculas, asi que un
 * 'reparacion' en minuscula seria IGUAL a 'Reparacion' en un WHERE. Ningun
 * value de aqui puede coincidir, ni en minusculas, con uno de ProductoEstado.
 */
enum BitacoraEvento: string
{
    case Creado = 'creado';
    case Editado = 'editado';
    case Eliminado = 'eliminado';
    case Stock = 'stock';
    case Garantia = 'garantia';
    case Externo = 'externo';
    case Cobro = 'cobro';
    case CobroAnulado = 'cobro-anulado';
    case Baja = 'baja';
    case BajaRevertida = 'baja-revertida';
    case Regalo = 'regalo';
    case RegaloQuitado = 'regalo-quitado';
    case StockBaja = 'stock-baja';
    // El dinero que entra por una venta (PagoService). No es 'cobro': ese ya es
    // el cobro de las piezas de una reparacion.
    case Pago = 'pago';
    case PagoAnulado = 'pago-anulado';
    // La compra dejo de ser borrador: su stock entro y sus equipos se liberaron.
    case Finalizada = 'finalizada';

    public function label(): string
    {
        return match ($this) {
            self::Creado => 'Creado',
            self::Editado => 'Editado',
            self::Eliminado => 'Eliminado',
            self::Stock => 'Ajuste de stock',
            self::Garantia => 'Garantía',
            self::Externo => 'Trabajo externo',
            self::Cobro => 'Repuesto cobrado',
            self::CobroAnulado => 'Cobro anulado',
            self::Baja => 'Dado de baja',
            self::BajaRevertida => 'Baja revertida',
            self::Regalo => 'Regalo agregado',
            self::RegaloQuitado => 'Regalo quitado',
            self::StockBaja => 'Baja de stock',
            self::Pago => 'Pago recibido',
            self::PagoAnulado => 'Pago anulado',
            self::Finalizada => 'Compra finalizada',
        };
    }

    /** Clases LITERALES en cada rama: Tailwind no tiene safelist. */
    public function clases(): string
    {
        return match ($this) {
            self::Creado => 'bg-emerald-100 text-emerald-800',
            self::Editado => 'bg-gray-100 text-gray-700',
            self::Eliminado => 'bg-red-100 text-red-800',
            self::Stock => 'bg-sky-100 text-sky-800',
            self::Garantia => 'bg-amber-100 text-amber-800',
            self::Externo => 'bg-orange-100 text-orange-800',
            self::Cobro => 'bg-green-100 text-green-800',
            self::CobroAnulado => 'bg-rose-100 text-rose-800',
            self::Baja => 'bg-red-100 text-red-800',
            self::BajaRevertida => 'bg-lime-100 text-lime-800',
            self::Regalo => 'bg-pink-100 text-pink-800',
            self::RegaloQuitado => 'bg-fuchsia-100 text-fuchsia-800',
            self::StockBaja => 'bg-red-100 text-red-800',
            self::Pago => 'bg-teal-100 text-teal-800',
            self::PagoAnulado => 'bg-rose-100 text-rose-800',
            self::Finalizada => 'bg-emerald-100 text-emerald-800',
        };
    }

    /**
     * El badge de CUALQUIER evento, sea de esta familia o un estado.
     *
     * Lo usan las tres pantallas de historial (producto, repuesto y usuario):
     * antes cada tabla pintaba el estado a su manera y aqui serian tres copias.
     */
    public static function badge(?string $evento): string
    {
        if ($caso = self::tryFrom((string) $evento)) {
            return '<span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold '
                . $caso->clases() . '">' . e($caso->label()) . '</span>';
        }

        if ($estado = ProductoEstado::tryFrom((string) $evento)) {
            // style inline y no una clase: el color del estado es un valor
            // suelto ('green', '#f59e0b'...) y una clase compuesta no se genera.
            return '<span class="font-semibold" style="color:' . e($estado->color()) . '">'
                . e($estado->label()) . '</span>';
        }

        return '<span class="text-gray-500">' . e((string) $evento) . '</span>';
    }

    /** Las opciones del filtro: los estados y estos, juntos. */
    public static function opcionesConEstados(): array
    {
        return ProductoEstado::toSelectArray()
            ->merge(self::toSelectArray())
            ->toArray();
    }

    public static function toSelectArray(): Collection
    {
        return collect(self::cases())->mapWithKeys(fn($c) => [$c->value => $c->label()]);
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
