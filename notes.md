# Modulos del sistema:
- usuarios: agregar atributo de % de comision para las ventas.
- roles: se mantiene.
- tecnicos: agregar atributo de comision por cada reparacion que es 50% y 50%.
- proveedores: se mantiene.
## inventario
- marcas de productos: se mantiene.
- categorias de productos: se mantiene.
- modelos de productos: se mantiene.
- categorias de repuestos y accesorios: se mantiene.
- productos: costo de envio cambiar por costo de accesorios de regalo. y ver el detalle de los accesorios agregados.
modificar condicion del producto a: grado a +, grado 1, grado 2, grado 3.
tipo de venta: venta, oferta, venta externa.
estados: quitar el estado transito, venta de credito. reserva (se paga un monto).
todo en moneda bs.
agregar codigo upc para buscar por codigo de barra agregar un paquete buscador para abrir la camara y leer el codigo de barra.
- repuestos: todo en bolivianos, cambiar el costo por codigo (solo a nivel de front el costo debe llamarse codigo en lugar de costo). tambien agregar upc para lector de codigos de barras.
## Ventas
- venta de productos: agregar por detalle: agregar accesorios asociados al telefono para que se venda con el telefono.
agregar dar de baja: a productos y accesorios. (algo q se daña o se pierde)
agregar la venta por permuta. (a la hora de hacer la venta se recibe un dispositivo, se lo registra y el costo y se calcula la diferencia a pagar).
imprimir nota de venta: NOTA DE COMPRA, titulo, logo, id se vera el detalle luego.
- comision por ventas: cada usuario que realiza una venta tendra un % de comision. Este % estara en su perfil. y en el modulo de usuarios se debe registrar que comisiones de vnetas ha realizado en que fechas y cuales quedan pendiente de pagos, pagar comisiones y quede registrado que ventas y total.

- agregar venta a credito: se puede realizar una venta pendiente de cobro, registrar los pagos y ver por cada cliente las deudas. y realizar cobros hasta completar por las ventas que tiene pendiente de pagos.

- agregar metodos de pagos al realizar una venta. crear un modulo para registrar que metodos de pagos se reciben y registra multiple pago con diferentes metodos de pagos por cada venta.

- al registrar los detalles de las ventas permitir escanear mediante camara el codigo upc para hacerlo mas rapido, tambien en repuestos y accesorios.

## Compras
- compras: por cada compra agregar un estado. si esta mal reclamar al proveedor. seleccionar que productos estan mal para luego reclamar al proveedor y pedir el cambio del producto.
- cuentas por pagar en las compras. las compras pueden ser a credito tambien y registrar los pagos al proveedor por que compra, hasta completar.
- igual agregar el scaner de codigos de barras al comprar. para hacerlo mas rapido.

## Catalogo
- catalogo publico: se mantiene.

## Reportes
- se mantiene.
- agregar reportes por vendedores.
- productos.
- inventario.
- cientes. 