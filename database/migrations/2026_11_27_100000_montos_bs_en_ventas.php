<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Los Bs que se escribieron en la venta (pedido de la tienda: «te lo dejo en
     * 11500 Bs»). Todo sigue en USD; esto es lo escrito, para mostrarlo exacto:
     * 11500 / 12 = 958.33 USD y 958.33 x 12 = 11499.96, asi que derivar los Bs
     * del USD no devolvia lo que se le dijo al cliente.
     *
     *  - ventas_detalles.precio_bs: el precio unitario escrito en Bs (NULL = en USD).
     *  - ventas.descuento_bs: el descuento en Bs, escrito o derivado de un total
     *    escrito en Bs (NULL = en USD).
     *
     * La cuenta (y el redondeo del USD) vive en App\Support\MontosVentaBs.
     */
    public function up(): void
    {
        Schema::table('ventas_detalles', function (Blueprint $table) {
            $table->decimal('precio_bs', 12, 2)->nullable()->after('precio');
        });

        Schema::table('ventas', function (Blueprint $table) {
            $table->decimal('descuento_bs', 12, 2)->nullable()->after('descuento');
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropColumn('descuento_bs');
        });

        Schema::table('ventas_detalles', function (Blueprint $table) {
            $table->dropColumn('precio_bs');
        });
    }
};
