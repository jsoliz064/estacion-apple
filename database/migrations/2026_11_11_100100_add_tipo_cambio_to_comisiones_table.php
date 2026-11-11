<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El tipo de cambio de cada comision y su monto en Bs (pedido de la tienda:
     * cada venta tiene su TC y la comision se paga en Bs). La de una venta usa
     * el TC congelado de la venta; la de una reparacion, que no tiene TC, el
     * del dia en que nace la comision. `monto` sigue en USD y es lo que se suma;
     * `monto_bs` = ROUND(monto x tipo_cambio, 2). Los escribe ComisionService.
     */
    public function up(): void
    {
        Schema::table('comisiones', function (Blueprint $table) {
            $table->decimal('tipo_cambio', 10, 4)->nullable()->after('monto');
            $table->decimal('monto_bs', 12, 2)->nullable()->after('tipo_cambio');
        });

        // Las que ya existen: la de venta con el TC de su venta; las demas (las
        // de reparacion, y la liquidada cuya venta se anulo) con el TC vigente
        // el dia en que nacio la comision, o el primero cargado si es anterior (y
        // el de respaldo si no hay ninguno, como TipoCambio::actual()).
        DB::statement('UPDATE comisiones c JOIN ventas v ON v.id = c.venta_id SET c.tipo_cambio = v.tipo_cambio');
        DB::statement(
            'UPDATE comisiones c SET c.tipo_cambio = COALESCE(
                (SELECT t.valor FROM tipos_cambio t WHERE t.created_at <= c.created_at ORDER BY t.created_at DESC, t.id DESC LIMIT 1),
                (SELECT t.valor FROM tipos_cambio t ORDER BY t.created_at ASC, t.id ASC LIMIT 1),
                ?
             )
             WHERE c.tipo_cambio IS NULL',
            [\App\Models\TipoCambio::RESPALDO]
        );
        DB::statement('UPDATE comisiones SET monto_bs = ROUND(monto * tipo_cambio, 2) WHERE tipo_cambio IS NOT NULL');
    }

    public function down(): void
    {
        Schema::table('comisiones', function (Blueprint $table) {
            $table->dropColumn(['tipo_cambio', 'monto_bs']);
        });
    }
};
