<?php

namespace App\Support;

/**
 * La UNICA lista de permisos del sistema, con la descripcion que ve el
 * administrador en Editar rol. Antes estaban sembrados a mano en dos seeders
 * (RoleSeeder y PermissionSeeder) y la pantalla de roles solo mostraba el
 * nombre tecnico: «producto.edit» no le dice nada a quien no programo esto.
 *
 * Un permiso nuevo va AQUI, con su descripcion y sus roles por defecto. En
 * produccion se aplica con `php artisan db:seed --class=PermisoSeeder --force`
 * (PermisoSeeder: crea los que falten, actualiza las descripciones y NO toca
 * los roles de un permiso que ya existia).
 *
 * `roles`: los roles que lo reciben al crearse. El Administrador recibe todos
 * siempre, por eso no se nombra.
 */
class CatalogoPermisos
{
    public const VENDEDOR = 'Vendedor';
    public const TECNICOS = 'Solo tecnicos';

    /** Sin uso en ninguna pantalla: PermisoSeeder los borra. */
    public const OBSOLETOS = [
        'producto.create',   // el alta de equipos va por compra.edit
    ];

    public const PERMISOS = [
        // --- Inicio y reportes
        'dashboard.index' => ['Ver el tablero de inicio con las cifras del negocio (ventas, inventario, deudas).', []],
        'reporte.index' => ['Ver el reporte general de ventas, compras y ganancias.', []],
        'reporte.vendedores' => ['Ver el reporte de ventas y ganancia por vendedor.', []],
        'reporte.productos' => ['Ver el reporte de equipos vendidos, comprados y su rotación.', []],
        'reporte.inventario' => ['Ver el reporte de inventario: cuánto dinero hay en stock por sucursal y las pérdidas.', []],
        'reporte.clientes' => ['Ver el reporte de clientes: quién compra más y quién debe.', []],

        // --- Usuarios, roles y sucursales
        'rol.index' => ['Ver la lista de roles.', []],
        'rol.create' => ['Crear un rol nuevo y elegir sus permisos.', []],
        'rol.edit' => ['Cambiar el nombre y los permisos de un rol.', []],
        'rol.delete' => ['Eliminar un rol.', []],
        'user.index' => ['Ver la lista de usuarios.', []],
        'user.create' => ['Crear un usuario nuevo.', []],
        'user.edit' => ['Editar un usuario: nombre, correo, contraseña, rol y comisión.', []],
        'user.delete' => ['Eliminar un usuario sin movimientos.', []],
        'user.desactivar' => ['Desactivar o reactivar un usuario (deja de poder entrar).', []],
        'user.historial' => ['Ver todo lo que hizo un usuario en el sistema.', []],
        'sucursal.index' => ['Ver la lista de sucursales.', []],
        'sucursal.create' => ['Crear una sucursal.', []],
        'sucursal.edit' => ['Editar o desactivar una sucursal.', []],
        'sucursal.delete' => ['Eliminar una sucursal sin movimientos.', []],

        // --- Proveedores y técnicos
        'proveedor.index' => ['Ver la lista de proveedores.', [self::VENDEDOR]],
        'proveedor.create' => ['Crear un proveedor.', []],
        'proveedor.edit' => ['Editar los datos de un proveedor.', []],
        'proveedor.delete' => ['Eliminar un proveedor.', []],
        'proveedor.historial' => ['Ver la ficha de un proveedor: sus compras, pagos y lo que se le debe.', []],
        'tecnico.index' => ['Ver la lista de técnicos.', [self::VENDEDOR, self::TECNICOS]],
        'tecnico.create' => ['Crear un técnico.', []],
        'tecnico.edit' => ['Editar los datos de un técnico.', []],
        'tecnico.delete' => ['Eliminar un técnico.', []],
        'tecnico.productos' => ['Ver los equipos que tiene cada técnico en reparación.', [self::VENDEDOR, self::TECNICOS]],
        'tecnico.productos.exportar' => ['Exportar a Excel los equipos de un técnico.', [self::VENDEDOR, self::TECNICOS]],
        'tecnico.productos.terminar' => ['Terminar varias reparaciones de un técnico a la vez.', [self::VENDEDOR]],
        'tecnico.producto.edit' => ['Editar una reparación: costo, piezas usadas y fechas.', [self::VENDEDOR]],

        // --- Catálogo de productos
        'producto-marca.index' => ['Ver las marcas.', [self::VENDEDOR]],
        'producto-marca.create' => ['Crear una marca.', []],
        'producto-marca.edit' => ['Editar una marca.', []],
        'producto-marca.delete' => ['Eliminar una marca.', []],
        'producto-categoria.index' => ['Ver las categorías de productos.', [self::VENDEDOR]],
        'producto-categoria.create' => ['Crear una categoría de productos.', []],
        'producto-categoria.edit' => ['Editar una categoría de productos.', []],
        'producto-categoria.delete' => ['Eliminar una categoría de productos.', []],
        'producto-modelo.index' => ['Ver los modelos de equipo.', [self::VENDEDOR]],
        'producto-modelo.create' => ['Crear un modelo de equipo.', []],
        'producto-modelo.edit' => ['Editar un modelo de equipo.', []],
        'producto-modelo.delete' => ['Eliminar un modelo de equipo.', []],
        'producto-modelo.almacenamiento' => ['Cargar el costo y precio de referencia de cada almacenamiento de un modelo.', []],
        'producto-modelo.colores' => ['Marcar qué colores se ofrecen al cargar un equipo de cada modelo.', []],
        'producto-color.index' => ['Ver los colores de equipo.', [self::VENDEDOR]],
        'producto-color.create' => ['Crear un color de equipo.', []],
        'producto-color.edit' => ['Editar o desactivar un color de equipo.', []],
        'producto-color.delete' => ['Eliminar un color que ningún equipo usa.', []],

        // --- Equipos
        'producto.index' => ['Ver la lista de equipos (Productos).', [self::VENDEDOR, self::TECNICOS]],
        'producto.edit' => ['Editar los datos de un equipo: IMEI, color, batería, grado, sucursal, fotos y estado.', []],
        'producto.editar-costo' => ['Cambiar el costo de un equipo ya cargado.', []],
        'producto.editar-precio' => ['Cambiar el precio de venta de un equipo ya cargado.', []],
        'producto.delete' => ['Eliminar un equipo.', []],
        'producto.historial' => ['Ver el historial de un equipo: todo lo que le pasó, quién y cuándo.', [self::VENDEDOR, self::TECNICOS]],
        'producto.historial.show' => ['Abrir el detalle de cada movimiento o reparación dentro del historial del equipo.', [self::VENDEDOR, self::TECNICOS]],
        'producto.garantia' => ['Mandar un equipo vendido a reparación por garantía.', [self::VENDEDOR]],
        'producto.trabajo-externo' => ['Mandar a reparar un equipo ya vendido con la garantía vencida (lo paga el cliente).', []],
        'producto.cambiar-sucursal' => ['Mover equipos de una sucursal a otra.', [self::VENDEDOR]],
        'producto.estado-masivo' => ['Cambiar el estado de varios equipos a la vez.', []],
        'producto.reporte' => ['Ver el valor del inventario de equipos en la pantalla Productos.', []],
        'producto.baja' => ['Dar de baja un equipo (robo, pérdida, daño) o revertir la baja.', []],
        'producto.regalos' => ['Agregar o quitar los accesorios de regalo de un equipo.', [self::VENDEDOR]],
        'producto.estado.inventario' => ['Pasar un equipo al estado Inventario (disponible para vender).', [self::VENDEDOR, self::TECNICOS]],
        'producto.estado.reparacion' => ['Mandar un equipo a reparación con un técnico.', [self::VENDEDOR, self::TECNICOS]],
        'producto.estado.fuera' => ['Pasar un equipo al estado Fuera (lo tiene alguien fuera del local).', [self::VENDEDOR]],
        'producto.estado.roto' => ['Marcar un equipo como Roto.', [self::VENDEDOR]],

        // --- Repuestos
        'repuesto.index' => ['Ver la lista de repuestos y su stock.', []],
        'repuesto.create' => ['Crear un repuesto.', []],
        'repuesto.edit' => ['Editar los datos de un repuesto y su stock por sucursal.', []],
        'repuesto.editar-costo' => ['Cambiar el costo de un repuesto.', []],
        'repuesto.editar-precio' => ['Cambiar el precio de venta de un repuesto.', []],
        'repuesto.delete' => ['Eliminar un repuesto sin movimientos.', []],
        'repuesto.historial' => ['Ver el historial de un repuesto: entradas, salidas y cambios.', []],
        'repuesto.transferir' => ['Pasar unidades de un repuesto de una sucursal a otra.', []],
        'repuesto.baja' => ['Dar de baja unidades de un repuesto (dañadas, perdidas).', []],
        'repuesto-categoria.index' => ['Ver las categorías de repuestos.', []],
        'repuesto-categoria.create' => ['Crear una categoría de repuestos.', []],
        'repuesto-categoria.edit' => ['Editar una categoría de repuestos.', []],
        'repuesto-categoria.delete' => ['Eliminar una categoría de repuestos.', []],

        // --- Accesorios
        'accesorio.index' => ['Ver la lista de accesorios y su stock.', []],
        'accesorio.create' => ['Crear un accesorio.', []],
        'accesorio.edit' => ['Editar los datos de un accesorio y su stock por sucursal.', []],
        'accesorio.editar-costo' => ['Cambiar el costo de un accesorio.', []],
        'accesorio.editar-precio' => ['Cambiar el precio de venta de un accesorio.', []],
        'accesorio.delete' => ['Eliminar un accesorio sin movimientos.', []],
        'accesorio.historial' => ['Ver el historial de un accesorio: entradas, salidas y cambios.', []],
        'accesorio.transferir' => ['Pasar unidades de un accesorio de una sucursal a otra.', []],
        'accesorio.baja' => ['Dar de baja unidades de un accesorio (dañadas, perdidas).', []],
        'accesorio-categoria.index' => ['Ver las categorías de accesorios.', []],
        'accesorio-categoria.create' => ['Crear una categoría de accesorios.', []],
        'accesorio-categoria.edit' => ['Editar una categoría de accesorios.', []],
        'accesorio-categoria.delete' => ['Eliminar una categoría de accesorios.', []],

        // --- Códigos de barras y tipo de cambio
        'codigo.index' => ['Ver e imprimir los lotes de stickers con código de barras.', []],
        'codigo.generar' => ['Generar lotes nuevos de stickers con código de barras.', []],
        'tipo-cambio.actualizar' => ['Cargar el tipo de cambio del día (USD a Bs).', []],

        // --- Compras y proveedores
        'compra.index' => ['Ver la lista de compras.', []],
        'compra.detalle' => ['Abrir el detalle de una compra.', []],
        'compra.create' => ['Crear una compra nueva.', []],
        'compra.edit' => ['Cargar y quitar equipos, repuestos y accesorios de una compra.', []],
        'compra.finalizar' => ['Finalizar una compra: su stock entra al inventario y los equipos quedan a la venta.', []],
        'compra.delete' => ['Eliminar una compra sin pagos (si ya se finalizó, su stock sale del inventario).', []],
        'compra.reclamo' => ['Abrir y cerrar reclamos al proveedor por un equipo fallado.', []],
        'cuenta-pagar.index' => ['Ver las cuentas por pagar a proveedores.', []],
        'pago-proveedor.create' => ['Registrar un pago a un proveedor.', []],
        'pago-proveedor.anular' => ['Anular un pago hecho a un proveedor.', []],

        // --- Ventas, cobros y reservas
        'venta.index' => ['Ver la lista de ventas.', [self::VENDEDOR]],
        'venta.create' => ['Registrar una venta.', [self::VENDEDOR]],
        'venta.edit' => ['Editar una venta ya registrada.', [self::VENDEDOR]],
        'venta.editar-precio' => ['Cambiar el precio de cada línea al vender o editar una venta. Sin este permiso solo se cambian la garantía y la cantidad; la rebaja va en el descuento de la venta.', []],
        'venta.reporte' => ['Ver el resumen de ventas (totales y ganancia) y la ganancia de cada venta.', []],
        'venta.delete' => ['Anular una venta entera (devuelve el stock y los pagos).', []],
        'venta.detalle' => ['Abrir el detalle de una venta, con su nota y recibo.', [self::VENDEDOR]],
        'venta.detalle.delete' => ['Quitar una línea de una venta (devuelve ese artículo al stock).', [self::VENDEDOR]],
        'cobranza.index' => ['Ver las ventas a crédito y lo que debe cada cliente.', [self::VENDEDOR]],
        'pago.create' => ['Cobrar a un cliente un pago de una venta a crédito.', [self::VENDEDOR]],
        'pago.anular' => ['Anular un pago cobrado a un cliente.', []],
        'reserva.index' => ['Ver las reservas de equipos.', [self::VENDEDOR]],
        'reserva.create' => ['Reservar un equipo para un cliente con una seña.', [self::VENDEDOR]],
        'reserva.cancelar' => ['Cancelar una reserva (devolviendo o reteniendo la seña).', [self::VENDEDOR]],
        'metodo-pago.index' => ['Ver los métodos de pago.', []],
        'metodo-pago.create' => ['Crear un método de pago.', []],
        'metodo-pago.edit' => ['Editar o desactivar un método de pago.', []],
        'metodo-pago.delete' => ['Eliminar un método de pago sin pagos.', []],

        // --- Clientes
        'cliente.index' => ['Ver la lista de clientes.', []],
        'cliente.create' => ['Crear un cliente.', []],
        'cliente.edit' => ['Editar los datos de un cliente.', []],
        'cliente.delete' => ['Eliminar un cliente que no tiene ventas.', []],
        'cliente.historial' => ['Ver la ficha de un cliente: sus compras, deudas y reservas.', []],

        // --- Comisiones
        'comision.index' => ['Ver las comisiones de todos los vendedores y técnicos.', []],
        'comision.liquidar' => ['Pagar (liquidar) las comisiones de un vendedor o técnico.', []],
        'comision.anular' => ['Anular una liquidación de comisiones ya pagada.', []],
        'comision.propias' => ['Ver mis propias comisiones.', [self::VENDEDOR, self::TECNICOS]],
    ];

    public static function descripcion(string $permiso): ?string
    {
        return self::PERMISOS[$permiso][0] ?? null;
    }

    /** @return array<int, string> */
    public static function rolesPorDefecto(string $permiso): array
    {
        return self::PERMISOS[$permiso][1] ?? [];
    }
}
