<?php

namespace Database\Seeders;

use App\Models\ProductoCategoria;
use App\Models\ProductoMarca;
use App\Models\ProductoModelo;
use App\Models\ProductoModeloAlmacenamiento;
use Illuminate\Database\Seeder;

/**
 * Catalogo base de marcas, categorias y modelos mas comunes (docs/00: "el
 * sistema nuevo arranca con los modelos y marcas mas comunes ya cargados").
 *
 * Los precios van en 0: los carga el negocio. Todo es firstOrCreate por nombre,
 * asi que se puede volver a correr sin duplicar, y las categorias se resuelven
 * por nombre (antes ProductoModeloSeeder tenia un producto_categoria_id => 1
 * escrito a mano).
 */
class CatalogoBaseSeeder extends Seeder
{
    private const IPHONE_ANTIGUO = ['64GB', '128GB', '256GB'];
    private const IPHONE = ['128GB', '256GB', '512GB'];
    private const IPHONE_PRO = ['128GB', '256GB', '512GB', '1TB'];
    private const IPHONE_17_PRO = ['256GB', '512GB', '1TB', '2TB'];

    public function run(): void
    {
        $catalogo = [
            'Apple' => [
                'iPhone' => [
                    'iPhone XR' => self::IPHONE_ANTIGUO,
                    'iPhone 11' => self::IPHONE_ANTIGUO,
                    'iPhone 11 Pro' => self::IPHONE_ANTIGUO,
                    'iPhone 11 Pro Max' => self::IPHONE_ANTIGUO,
                    'iPhone 12' => self::IPHONE_ANTIGUO,
                    'iPhone 12 mini' => self::IPHONE_ANTIGUO,
                    'iPhone 12 Pro' => self::IPHONE,
                    'iPhone 12 Pro Max' => self::IPHONE,
                    'iPhone 13' => self::IPHONE,
                    'iPhone 13 mini' => self::IPHONE,
                    'iPhone 13 Pro' => self::IPHONE_PRO,
                    'iPhone 13 Pro Max' => self::IPHONE_PRO,
                    'iPhone 14' => self::IPHONE,
                    'iPhone 14 Plus' => self::IPHONE,
                    'iPhone 14 Pro' => self::IPHONE_PRO,
                    'iPhone 14 Pro Max' => self::IPHONE_PRO,
                    'iPhone 15' => self::IPHONE,
                    'iPhone 15 Plus' => self::IPHONE,
                    'iPhone 15 Pro' => self::IPHONE_PRO,
                    'iPhone 15 Pro Max' => ['256GB', '512GB', '1TB'],
                    'iPhone 16' => self::IPHONE,
                    'iPhone 16 Plus' => self::IPHONE,
                    'iPhone 16 Pro' => self::IPHONE_PRO,
                    'iPhone 16 Pro Max' => ['256GB', '512GB', '1TB'],
                    'iPhone 17' => ['256GB', '512GB'],
                    'iPhone Air' => ['256GB', '512GB', '1TB'],
                    'iPhone 17 Pro' => ['256GB', '512GB', '1TB'],
                    'iPhone 17 Pro Max' => self::IPHONE_17_PRO,
                ],
            ],
        ];

        foreach ($catalogo as $marcaNombre => $categorias) {
            $marca = ProductoMarca::firstOrCreate(['nombre' => $marcaNombre]);

            foreach ($categorias as $categoriaNombre => $modelos) {
                $categoria = ProductoCategoria::firstOrCreate([
                    'nombre' => $categoriaNombre,
                    'producto_marca_id' => $marca->id,
                ]);

                foreach ($modelos as $modeloNombre => $almacenamientos) {
                    $modelo = ProductoModelo::firstOrCreate([
                        'nombre' => $modeloNombre,
                        'producto_categoria_id' => $categoria->id,
                    ]);

                    foreach ($almacenamientos as $almacenamiento) {
                        ProductoModeloAlmacenamiento::firstOrCreate(
                            ['producto_modelo_id' => $modelo->id, 'almacenamiento' => $almacenamiento],
                            ['precio' => 0, 'costo' => 0, 'precio_cliente' => 0],
                        );
                    }
                }
            }
        }
    }
}
