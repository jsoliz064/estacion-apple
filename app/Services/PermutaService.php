<?php

namespace App\Services;

use App\Enums\ProductoEstado;
use App\Enums\ProductoGrado;
use App\Enums\ProductoTipoVenta;
use App\Models\Producto;
use App\Models\ProductoModelo;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * La permuta: el cliente entrega un equipo como parte de pago (docs/03).
 *
 * Decision del usuario: es UN PAGO MAS. La venta vale lo que vale (7.000), el
 * equipo recibido paga una parte (2.500, metodo de sistema «Permuta») y el
 * resto se cobra. Asi la ganancia y la comision salen sobre lo vendido, y el
 * equipo entra al inventario con ese valor como costo, listo para revender.
 *
 * El equipo recibido no tiene compra: su origen es el pago de permuta
 * (Producto::permuta()). El auditor no lo cuenta como "equipo sin compra".
 *
 * No abre transaccion: la abre el componente, dentro de la de la venta.
 */
class PermutaService
{
    public function __construct(private PagoService $pagos) {}

    /**
     * Da de alta el equipo recibido y registra el pago.
     *
     * @param  array  $datos  producto_modelo_id, imei, almacenamiento, color,
     *                        estado_grado, bateria_porcentaje, valor
     */
    public function recibir(Venta $venta, array $datos, User $user, ?string $clave = null): Producto
    {
        $datos['imei'] = trim((string) ($datos['imei'] ?? ''));

        $v = Validator::make($datos, [
            'producto_modelo_id' => 'required|integer|exists:productos_modelos,id',
            'imei' => 'required|string|max:20|unique:productos,imei',
            'almacenamiento' => 'required|string|max:20',
            'color' => 'required|string',
            'estado_grado' => ['required', Rule::in(ProductoGrado::values())],
            'bateria_porcentaje' => 'required|integer|min:1|max:100',
            'valor' => 'required|numeric|min:0.01',
        ], [
            'imei.unique' => 'El IMEI del equipo recibido ya está registrado en el sistema.',
            'imei.required' => 'Escribe el IMEI del equipo recibido.',
            'valor.min' => 'Indica el valor que se le reconoce al equipo recibido.',
        ], [
            'producto_modelo_id' => 'modelo', 'estado_grado' => 'grado', 'bateria_porcentaje' => 'batería',
        ]);

        if ($v->fails()) {
            // Con prefijo: el formulario pinta los errores bajo cada campo.
            throw ValidationException::withMessages(collect($v->errors()->messages())
                ->mapWithKeys(fn($m, $campo) => ['permuta.' . $campo => $m])->all());
        }

        $valor = round((float) $datos['valor'], 2);
        $modelo = ProductoModelo::find($datos['producto_modelo_id']);

        // make() + anotar() + save(), como CompraService::agregarProducto: una
        // sola fila de bitacora con el estado con que nace y de donde vino.
        $producto = new Producto([
            'producto_modelo_id' => $modelo->id,
            'imei' => $datos['imei'],
            'almacenamiento' => $datos['almacenamiento'],
            'color' => $datos['color'],
            'estado_grado' => $datos['estado_grado'],
            'bateria_porcentaje' => (int) $datos['bateria_porcentaje'],
            'estado' => ProductoEstado::Inventario->value,
            'tipo_venta' => ProductoTipoVenta::Venta->value,
            'costo_unidad' => $valor,
            // Precio inicial = costo: el operador le pone precio despues. Fuera
            // del catalogo hasta entonces, para no publicarlo a precio de costo.
            'precio_cliente' => $valor,
            'precio_vendedor' => $valor,
            'disponible_catalogo' => false,
            'sucursal_id' => $venta->sucursal_id,
            'descripcion' => trim($modelo->nombre . ' color ' . $datos['color'] . ' de ' . $datos['almacenamiento']
                . ' con ' . (int) $datos['bateria_porcentaje'] . '% de batería IMEI: ' . $datos['imei']),
        ]);
        $producto->anotar(
            ProductoEstado::Inventario->value,
            "Recibido en permuta en la venta #{$venta->id} por Bs " . number_format($valor, 2),
            ['venta_id' => $venta->id],
        );
        $producto->save();
        $producto->recalcularCosto();

        $this->pagos->registrarPermuta($venta, $producto, $valor, $user, $clave);

        return $producto;
    }

    /**
     * Anular la venta devuelve el equipo recibido al cliente: sale del
     * inventario. Solo si nadie lo toco: si ya se vendio, se reparo o se le
     * agregaron regalos, la venta no se puede anular (el equipo ya no esta).
     */
    public function devolver(int $productoId, Venta $venta): void
    {
        $producto = Producto::whereKey($productoId)->lockForUpdate()->first();

        if (!$producto) {
            return;
        }

        $motivo = match (true) {
            $producto->estado !== ProductoEstado::Inventario->value => 'está en ' . ProductoEstado::labelDe($producto->estado),
            $producto->estaDadoDeBaja() => 'está dado de baja',
            $producto->ventaDetalle()->exists() => 'ya se vendió',
            $producto->reparaciones()->exists() => 'tiene reparaciones',
            $producto->regalos()->exists() => 'tiene regalos cargados',
            $producto->reservas()->exists() => 'tuvo una reserva',
            default => null,
        };

        if ($motivo) {
            throw ValidationException::withMessages([
                'detalles' => "El equipo recibido en permuta (IMEI {$producto->imei}) {$motivo}: no se puede anular la venta #{$venta->id}.",
            ]);
        }

        // Por Eloquent: el evento deleted borra cada miniatura.
        $producto->imagenes()->get()->each->delete();
        $producto->delete();
    }
}
