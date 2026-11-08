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
            // Serial o UPC: el de la caja (lo comparten los del mismo modelo) o
            // un sticker propio (unico). Un vacio se guarda como NULL
            // (NormalizaCodigosTrait). No hay SKU: la tienda no lo usa.
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

            // Todo en USD. Un solo precio de venta (antes habia de cliente y de
            // vendedor, y la venta elegia uno con un select).
            $table->decimal('costo_unidad', 10, 2)->default(0);
            $table->decimal('precio', 10, 2)->default(0);
            // Costo y precio pueden cargarse en Bs, con UN tipo de cambio para
            // los dos (MontosEnMonedaTrait). Se convierten una vez al guardar:
            // `costo_unidad` y `precio` quedan en USD y son lo unico que leen la
            // compra, la venta y los reportes; estas columnas solo dicen de
            // donde salieron esos numeros.
            $table->enum('moneda', Moneda::values())->default(Moneda::USD->value);
            $table->decimal('costo_moneda_monto', 12, 2)->nullable();
            $table->decimal('precio_moneda_monto', 12, 2)->nullable();
            $table->decimal('tipo_cambio', 10, 4)->nullable();
            // Cacheados: solo los escribe Producto::recalcularCosto().
            $table->decimal('costo_regalos', 10, 2)->default(0);
            $table->decimal('costo_reparacion', 10, 2)->default(0);
            $table->decimal('costo_total', 10, 2)->default(0);

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

        // En USD, sin bolivianos ni tipo de cambio; en Bs, los tres. El IS NOT
        // NULL del tipo de cambio no sobra: con NULL, `> 0` da desconocido y un
        // CHECK solo rechaza lo FALSO, asi que un Bs sin tipo de cambio pasaba.
        DB::statement("ALTER TABLE productos ADD CONSTRAINT productos_montos_moneda CHECK (
            (moneda = 'USD' AND costo_moneda_monto IS NULL AND precio_moneda_monto IS NULL AND tipo_cambio IS NULL)
            OR (moneda = 'BOB' AND costo_moneda_monto IS NOT NULL AND precio_moneda_monto IS NOT NULL
                AND tipo_cambio IS NOT NULL AND tipo_cambio > 0))");
    }

    public function down(): void
    {
        Schema::dropIfExists('productos');
    }
};
