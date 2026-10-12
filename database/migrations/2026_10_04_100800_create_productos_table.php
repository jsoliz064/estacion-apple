<?php

use App\Enums\BajaMotivo;
use App\Enums\Moneda;
use App\Enums\ProductoColor;
use App\Enums\ProductoEstado;
use App\Enums\ProductoGrado;
use App\Enums\ProductoTipoVenta;
use App\Enums\ProductoVersion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Un equipo concreto, identificado por IMEI. Todo en Bs.
     *
     * No tiene `compra_id`: que compra lo trajo lo dice su linea en
     * compras_detalles (UNIQUE producto_id), y tener el dato en dos sitios era
     * dos verdades que ningun CHECK puede atar. Un equipo de permuta (etapa 5)
     * no tiene linea de compra.
     */
    public function up(): void
    {
        Schema::create('productos', function (Blueprint $table) {
            $table->id();
            $table->string('imei', 20);
            // Codigo libre para buscar rapido lo que no tiene codigo de barras.
            // Opcional; un vacio se guarda como NULL (NormalizaCodigosTrait),
            // porque dos '' chocarian en el unique.
            $table->string('sku', 50)->nullable();
            $table->string('upc', 50)->nullable();
            $table->string('almacenamiento');
            $table->enum('version', array_map(fn($c) => $c->value, ProductoVersion::cases()))->nullable();
            $table->enum('color', array_map(fn($c) => $c->value, ProductoColor::cases()));
            $table->integer('bateria_porcentaje');
            $table->string('descripcion')->nullable();
            $table->text('detalles')->nullable();
            $table->enum('estado_grado', ProductoGrado::values())->nullable();
            $table->enum('estado', ProductoEstado::values())
                ->default(ProductoEstado::Inventario->value);
            $table->enum('tipo_venta', ProductoTipoVenta::values())
                ->default(ProductoTipoVenta::Venta->value);

            $table->decimal('costo_unidad', 10, 2)->default(0);
            // El costo puede cargarse en USD con su tipo de cambio. El costo en
            // Bs sigue en `costo_unidad` (lo deriva Producto al guardar) y es lo
            // unico que leen la compra, la venta y los reportes: estas columnas
            // solo dicen de donde salio ese numero.
            $table->enum('costo_moneda', Moneda::values())->default(Moneda::BOB->value);
            $table->decimal('costo_moneda_monto', 10, 2)->nullable();
            $table->decimal('costo_tipo_cambio', 10, 2)->nullable();
            // Cacheados: solo los escribe Producto::recalcularCosto().
            $table->decimal('costo_regalos', 10, 2)->default(0);
            $table->decimal('costo_reparacion', 10, 2)->default(0);
            $table->decimal('costo_total', 10, 2)->default(0);
            $table->decimal('precio_cliente', 10, 2)->default(0);
            $table->decimal('precio_cliente_ant', 10, 2)->default(0);
            $table->decimal('precio_vendedor', 10, 2)->default(0);
            $table->decimal('precio_vendedor_ant', 10, 2)->default(0);

            $table->boolean('venta_rapida')->default(false);
            $table->boolean('disponible_catalogo')->default(true);
            $table->boolean('garantia_activa')->default(false);

            // Baja: archiva el equipo sin tocar su estado (Fuera y Roto NO son
            // bajas). Ver BajaService. No es SoftDeletes a proposito: un scope
            // global romperia la bitacora (morphTo) y las lineas de venta.
            $table->timestamp('dado_de_baja_at')->nullable();
            $table->enum('motivo_baja', BajaMotivo::values())->nullable();
            $table->string('nota_baja')->nullable();
            $table->unsignedBigInteger('baja_user_id')->nullable();

            $table->foreignId('producto_modelo_id')->constrained('productos_modelos')->restrictOnDelete();
            $table->foreignId('sucursal_id')->nullable()->constrained('sucursales')->nullOnDelete();
            $table->timestamps();

            // La garantia de verdad contra el IMEI repetido: la regla unique:
            // valida con un SELECT previo y dos pestanas la pasan las dos.
            $table->unique('imei', 'productos_imei_unico');
            $table->unique('sku', 'productos_sku_unico');
            $table->index('upc', 'productos_upc_index');
            $table->index('estado', 'productos_estado_index');
            $table->index('tipo_venta', 'productos_tipo_venta_index');
            $table->index('dado_de_baja_at', 'productos_baja_index');
            $table->foreign('baja_user_id', 'productos_baja_user_fk')
                ->references('id')->on('users')->nullOnDelete();
        });

        // Fecha y motivo van juntos: una baja sin motivo no sirve al reporte de
        // perdidas, y un motivo sin fecha no es una baja.
        DB::statement('ALTER TABLE productos ADD CONSTRAINT productos_baja_completa
            CHECK ((dado_de_baja_at IS NULL) = (motivo_baja IS NULL))');

        // En Bs, sin dolares ni tipo de cambio; en USD, los dos. El IS NOT NULL
        // del tipo de cambio no sobra: con NULL, `> 0` da desconocido y un CHECK
        // solo rechaza lo FALSO, asi que un USD sin tipo de cambio pasaba.
        DB::statement("ALTER TABLE productos ADD CONSTRAINT productos_costo_moneda CHECK (
            (costo_moneda = 'BOB' AND costo_moneda_monto IS NULL AND costo_tipo_cambio IS NULL)
            OR (costo_moneda = 'USD' AND costo_moneda_monto IS NOT NULL
                AND costo_tipo_cambio IS NOT NULL AND costo_tipo_cambio > 0))");
    }

    public function down(): void
    {
        Schema::dropIfExists('productos');
    }
};
