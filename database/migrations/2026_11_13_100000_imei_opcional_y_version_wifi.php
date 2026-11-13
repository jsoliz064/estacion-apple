<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El IMEI pasa a ser opcional: un iPad Wi-Fi (o un reloj, una Mac) no tiene
     * IMEI ni chip, y la tienda no podia cargarlo. Decision del usuario: cada
     * equipo tiene IMEI O serial (el «Serial o UPC», `upc`).
     *
     *  - `productos_imei_unico` se queda: MySQL admite varios NULL en un UNIQUE.
     *  - `serial_sin_imei` (generada) con su UNIQUE es la garantia de verdad de
     *    los que no tienen IMEI: dos de ellos no comparten serial. Los que
     *    tienen IMEI siguen pudiendo repetir el UPC de su caja.
     *  - El CHECK impide un equipo sin IMEI ni serial.
     *  - La version suma «Solo Wi-Fi» (`Wifi`).
     */
    public function up(): void
    {
        DB::statement("UPDATE productos SET imei = NULL WHERE TRIM(imei) = ''");

        Schema::table('productos', function (Blueprint $table) {
            $table->string('imei', 20)->nullable()->change();
        });

        DB::statement("ALTER TABLE productos MODIFY version ENUM('Chip','eSIM','Wifi') NULL");
        DB::statement('ALTER TABLE productos ADD COLUMN serial_sin_imei VARCHAR(50)
            GENERATED ALWAYS AS (IF(imei IS NULL, upc, NULL)) STORED AFTER upc');
        DB::statement('ALTER TABLE productos ADD UNIQUE productos_serial_sin_imei_unico (serial_sin_imei)');
        DB::statement('ALTER TABLE productos ADD CONSTRAINT productos_imei_o_serial
            CHECK (imei IS NOT NULL OR upc IS NOT NULL)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE productos DROP CHECK productos_imei_o_serial');
        DB::statement('ALTER TABLE productos DROP INDEX productos_serial_sin_imei_unico');
        DB::statement('ALTER TABLE productos DROP COLUMN serial_sin_imei');
        DB::statement("UPDATE productos SET version = NULL WHERE version = 'Wifi'");
        DB::statement("ALTER TABLE productos MODIFY version ENUM('Chip','eSIM') NULL");

        // Un equipo sin IMEI no cabe en la columna NOT NULL: se le pone su
        // serial (o su id) para poder revertir sin perder la fila.
        DB::statement("UPDATE productos SET imei = LEFT(COALESCE(upc, CONCAT('SIN-IMEI-', id)), 20) WHERE imei IS NULL");
        Schema::table('productos', function (Blueprint $table) {
            $table->string('imei', 20)->nullable(false)->change();
        });
    }
};
