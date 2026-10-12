<?php

use App\Http\Controllers\CatalogoController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\CobranzaController;
use App\Http\Controllers\ComisionController;
use App\Http\Controllers\CompraController;
use App\Http\Controllers\CuentaPagarController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MetodoPagoController;
use App\Http\Controllers\ProductoCategoriaController;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\ProductoMarcaController;
use App\Http\Controllers\ProductoModeloController;
use App\Http\Controllers\ProveedorController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\ReservaController;
use App\Http\Controllers\RepuestoController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SucursalController;
use App\Http\Controllers\TecnicosController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VentaController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    // Sin can: a proposito: es la pagina de inicio tras el login
    // (fortify.home), y la vista ya cambia el panel por un saludo a quien no
    // tiene dashboard.index. Cerrarla dejaria a un vendedor en un 403 al entrar.
    Route::get('/', [DashboardController::class, 'index'])->name('welcome');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Antes sin can:, como roles: cualquier usuario autenticado entraba
    // escribiendo la URL. En roles era peor, porque desde ahi se edita que
    // permisos tiene cada rol, incluido el propio.
    Route::get('users', [UserController::class, 'index'])
        ->middleware('can:user.index')
        ->name('users');

    // Con can: y whereNumber como los historiales hermanos (clientes, repuestos):
    // el @can del boton solo lo esconde, y {id} capturaria cualquier ruta
    // literal que se agregue manana bajo el prefijo.
    Route::get('users/{id}/historial', [UserController::class, 'historial'])
        ->whereNumber('id')
        ->middleware('can:user.historial')
        ->name('users.historial');

    Route::get('roles', [RoleController::class, 'index'])
        ->middleware('can:rol.index')
        ->name('roles');

    // Desde aqui y hasta el final del grupo, cada ruta lleva el mismo permiso
    // que el @can del enlace o boton que la abre. Antes ninguna lo tenia: el
    // @can solo escondia el enlace y cualquier usuario con sesion entraba
    // escribiendo la URL.
    Route::get('proveedores', [ProveedorController::class, 'index'])
        ->middleware('can:proveedor.index')
        ->name('proveedores');

    // La ficha: lo que se le debe, sus compras, pagos y reclamos.
    Route::get('proveedores/{id}', [ProveedorController::class, 'historial'])
        ->whereNumber('id')
        ->middleware('can:proveedor.historial')
        ->name('proveedores.historial');

    // Lo que se le debe a los proveedores (docs/06).
    Route::get('cuentas-por-pagar', [CuentaPagarController::class, 'index'])
        ->middleware('can:cuenta-pagar.index')
        ->name('cuentas-por-pagar');

    // Comisiones de vendedores y tecnicos, y su liquidacion (docs/05).
    Route::get('comisiones', [ComisionController::class, 'index'])
        ->middleware('can:comision.index')
        ->name('comisiones');

    // Lo propio: el vendedor o el tecnico (por su usuario) ve lo suyo.
    Route::get('mis-comisiones', [ComisionController::class, 'mias'])
        ->middleware('can:comision.propias')
        ->name('mis-comisiones');

    Route::get('sucursales', [SucursalController::class, 'index'])
        ->middleware('can:sucursal.index')
        ->name('sucursales');

    Route::get('metodos-pago', [MetodoPagoController::class, 'index'])
        ->middleware('can:metodo-pago.index')
        ->name('metodos-pago');

    // Lo que esta por cobrar: las ventas a credito (docs/04).
    Route::get('cobranzas', [CobranzaController::class, 'index'])
        ->middleware('can:cobranza.index')
        ->name('cobranzas');

    // Equipos apartados con seña (docs/03).
    Route::get('reservas', [ReservaController::class, 'index'])
        ->middleware('can:reserva.index')
        ->name('reservas');

    Route::group(['prefix' => 'clientes'], function () {
        // Con can: desde el principio: el @can del blade solo esconde el enlace
        // del menu, no protege la URL.
        Route::get('/', [ClienteController::class, 'index'])
            ->middleware('can:cliente.index')
            ->name('clientes');

        // whereNumber por el mismo motivo que en el historial de repuestos: si
        // manana se agrega una ruta literal bajo este prefijo, {id} la capturaria.
        Route::get('{id}/historial', [ClienteController::class, 'historial'])
            ->whereNumber('id')
            ->middleware('can:cliente.historial')
            ->name('clientes.historial');
    });

    Route::group(['prefix' => 'tecnicos'], function () {
        Route::get('/', [TecnicosController::class, 'index'])
            ->middleware('can:tecnico.index')
            ->name('tecnicos');
        Route::get('{id}/productos', [TecnicosController::class, 'tecnicosProductos'])
            ->whereNumber('id')
            ->middleware('can:tecnico.productos')
            ->name('tecnicos.productos');
    });

    // Antes el grupo entero iba sin un solo can:, a diferencia de sus hermanos
    // de clientes y repuestos: el @can del menu solo escondia el enlace.
    Route::group(['prefix' => 'productos'], function () {
        Route::get('/', [ProductoController::class, 'index'])
            ->middleware('can:producto.index')
            ->name('productos');
        Route::get('marcas', [ProductoMarcaController::class, 'index'])
            ->middleware('can:producto-marca.index')
            ->name('productos.marcas');
        Route::get('categorias', [ProductoCategoriaController::class, 'index'])
            ->middleware('can:producto-categoria.index')
            ->name('productos.categorias');
        Route::get('modelos', [ProductoModeloController::class, 'index'])
            ->middleware('can:producto-modelo.index')
            ->name('productos.modelos');
        // whereNumber: {id} capturaria cualquier ruta literal que se agregue
        // despues bajo el prefijo.
        Route::get('{id}/historial', [ProductoController::class, 'productoHistorial'])
            ->whereNumber('id')
            ->middleware('can:producto.historial')
            ->name('productos.historial');

        // La miniatura de una foto, para las tablas. Sin permiso de modulo: la
        // usan Productos y el detalle de la compra, y el catalogo ya es publico.
        Route::get('imagenes/{imagen}/miniatura', \App\Http\Controllers\ProductoMiniaturaController::class)
            ->whereNumber('imagen')
            ->name('productos.miniatura');
    });

    // Repuestos y accesorios: dos tablas distintas (`repuestos`, `accesorios`)
    // con el mismo componente de inventario; el tipo lo fija el controlador.
    // Los literales ('categorias') van ANTES de '{id}/historial', y el
    // whereNumber() lo blinda igualmente.
    Route::group(['prefix' => 'inventario'], function () {
        Route::group(['prefix' => 'repuestos'], function () {
            Route::get('/', [RepuestoController::class, 'index'])
                ->middleware('can:repuesto.index')
                ->name('repuestos');
            Route::get('categorias', [RepuestoController::class, 'categorias'])
                ->middleware('can:repuesto-categoria.index')
                ->name('repuestos.categorias');
            Route::get('{id}/historial', [RepuestoController::class, 'historial'])
                ->whereNumber('id')
                ->middleware('can:repuesto.historial')
                ->name('repuestos.historial');
        });

        Route::group(['prefix' => 'accesorios'], function () {
            Route::get('/', [RepuestoController::class, 'accesorios'])
                ->middleware('can:accesorio.index')
                ->name('accesorios');
            Route::get('categorias', [RepuestoController::class, 'categoriasAccesorios'])
                ->middleware('can:accesorio-categoria.index')
                ->name('accesorios.categorias');
            Route::get('{id}/historial', [RepuestoController::class, 'historialAccesorio'])
                ->whereNumber('id')
                ->middleware('can:accesorio.historial')
                ->name('accesorios.historial');
        });
    });

    // Compras: una sola para equipos, repuestos y accesorios. Los literales
    // ('crear') van antes de '{id}', y whereNumber() lo blinda igualmente.
    Route::group(['prefix' => 'compras'], function () {
        Route::get('/', [CompraController::class, 'index'])
            ->middleware('can:compra.index')
            ->name('compras');
        Route::get('crear', [CompraController::class, 'create'])
            ->middleware('can:compra.create')
            ->name('compras.crear');
        Route::get('{id}', [CompraController::class, 'show'])
            ->whereNumber('id')
            ->middleware('can:compra.detalle')
            ->name('compras.detalle');
        Route::get('{id}/editar', [CompraController::class, 'edit'])
            ->whereNumber('id')
            ->middleware('can:compra.edit')
            ->name('compras.editar');
    });

    Route::group(['prefix' => 'ventas'], function () {
        Route::get('/', [VentaController::class, 'index'])
            ->middleware('can:venta.index')
            ->name('ventas');

        // El @can del blade solo oculta el boton, no protege la URL: sin este
        // middleware cualquier usuario autenticado entraba escribiendola.
        Route::get('/crear', [VentaController::class, 'create'])
            ->middleware('can:venta.create')
            ->name('ventas.crear');
        Route::get('{id}/editar', [VentaController::class, 'editar'])
            ->whereNumber('id')
            ->middleware('can:venta.edit')
            ->name('ventas.editar');

        Route::get('{id}/detalles', [VentaController::class, 'detalles'])
            ->whereNumber('id')
            ->middleware('can:venta.detalle')
            ->name('ventas.detalles');

        // La nota de venta para la impresora termica de 80 mm.
        Route::get('{id}/nota', [VentaController::class, 'nota'])
            ->whereNumber('id')
            ->middleware('can:venta.detalle')
            ->name('ventas.nota');

        // El recibo en PDF (carta), para compartirlo por WhatsApp.
        Route::get('{id}/pdf', [VentaController::class, 'pdf'])
            ->whereNumber('id')
            ->middleware('can:venta.detalle')
            ->name('ventas.pdf');

    });

    // El @can del blade solo oculta el enlace del menu; sin este middleware
    // cualquier usuario autenticado entraba escribiendo /reporte.
    Route::get('reporte', [ReporteController::class, 'index'])
        ->middleware('can:reporte.index')
        ->name('reporte');

    // Los cuatro reportes de la etapa 8 (docs/09), cada uno con su permiso.
    Route::group(['prefix' => 'reportes'], function () {
        Route::get('vendedores', [ReporteController::class, 'vendedores'])
            ->middleware('can:reporte.vendedores')
            ->name('reportes.vendedores');
        Route::get('productos', [ReporteController::class, 'productos'])
            ->middleware('can:reporte.productos')
            ->name('reportes.productos');
        Route::get('inventario', [ReporteController::class, 'inventario'])
            ->middleware('can:reporte.inventario')
            ->name('reportes.inventario');
        Route::get('clientes', [ReporteController::class, 'clientes'])
            ->middleware('can:reporte.clientes')
            ->name('reportes.clientes');
    });
});

Route::get('catalogo', [CatalogoController::class, 'index'])->name('catalogo');

// La foto de un equipo, publica como el catalogo que la muestra. Ver ProductoFotoController.
Route::get('productos/fotos/{imagen}', \App\Http\Controllers\ProductoFotoController::class)
    ->whereNumber('imagen')
    ->name('productos.foto');

// Los terminos de garantia, publicos: a esta pagina lleva el QR de la nota.
Route::view('garantia', 'garantia')->name('garantia');
