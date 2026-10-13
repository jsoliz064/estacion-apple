# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

Sistema de inventario y ventas de **Estación Apple**, una tienda de celulares, accesorios y repuestos. Es una copia del sistema de Digital World, que a su vez venía del de la importadora YRB (los requerimientos estaban en `docs/`, que no se copió). Laravel 12 + Livewire 3 + Tailwind 3 + Alpine, MySQL **≥ 8.0.16** (por los `CHECK`, ver abajo). Todo el dominio, la interfaz y los comentarios están en español. Se usa sobre todo desde el celular.

---

## ⚠️ Nunca ejecutes `php artisan test`

`tests/Pest.php` aplica `RefreshDatabase` a todo `tests/Feature`, y en `phpunit.xml` la línea de sqlite **está comentada**. La suite corre contra `DB_DATABASE` del `.env`, que es la base de trabajo: ejecutarla la vacía. Los tests que hay son los de Jetstream recién generados; nadie los mantiene.

La verificación en este repo se hace con **scripts de un solo uso en el scratchpad**, y es la convención de la casa:

```php
require 'D:/developer/laravel/estacion-apple/vendor/autoload.php';   // rutas ABSOLUTAS: el
$app = require 'D:/developer/laravel/estacion-apple/bootstrap/app.php'; // scratchpad está en otra unidad
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

DB::beginTransaction();
try {
    Auth::login(User::find(1));
    $c = Livewire::test(MiComponente::class)->set('campo', 1)->call('store');
    ok($condicion, 'lo que se comprueba');   // imprime OK/FALLA y acumula fallas
} finally {
    DB::rollBack();   // SIEMPRE
}
```

`Livewire::test()` es la herramienta principal: monta el componente de verdad, dispara los hooks `updatedX` y renderiza el blade, así que sirve tanto para la lógica como para comprobar el HTML (`$c->html()`). Cierra cada script con `Artisan::call('productos:auditar')`: tiene que decir «Sin inconsistencias.».

Si una comprobación falla, sospecha primero de la expectativa del script. En la práctica casi siempre el componente tenía razón.

## Comandos

```bash
composer dev          # serve + pail + vite, todo junto
npm run dev           # solo Vite (necesario: sin public/build toda ruta da 500 por @vite)
npm run build
php artisan migrate --force
php artisan tinker --execute='...'
php artisan productos:auditar         # detector de deriva, SOLO LECTURA (ver abajo)
php -l archivo.php                    # lint tras editar
```

Docker (`docker-compose.yml`) es el despliegue: un solo contenedor `estacion-apple` (php-fpm). No hay worker de colas: el bot de WhatsApp, que era su único uso, se retiró (docs/10), y `QUEUE_CONNECTION=sync`. El `README.md` solo documenta el arranque con Docker.

**El sistema arrancó de cero el 2026-10-12**: las migraciones están aplanadas (`0001_*` y `2026_10_04_*`, una por tabla), incluidos los cambios que el sistema de origen había agregado después en producción. **Desde que entre en producción ya no se editan**: un cambio de esquema va en **una migración nueva** (`php artisan migrate --force`), con su `down()`. Nunca `migrate:fresh` sin que el usuario lo pida, ni en local: la base de trabajo tiene datos de prueba del usuario. Los seeders dejan `admin@gmail.com` / `1234` con todos los permisos, la sucursal Almacén, el catálogo base de marcas y modelos y las categorías de accesorio.

---

## Arquitectura

### El dominio en una frase

Un **producto** es un teléfono concreto con IMEI único; un **repuesto** (pieza del taller) y un **accesorio** (lo que se vende en mostrador) son stock por cantidad, en **dos tablas** con un **stock común**. Ese par —pieza única contra stock fungible— explica casi todas las asimetrías del código. Una **venta** y una **compra** son un solo documento cuyas líneas pueden ser de los tres tipos.

```
                     compras ──> compras_detalles ─┬─> Producto (IMEI, estado, grado, tipo_venta, baja)
                                  (P / R / A)      │      │
                                                   │      ├──> ProductoReparacion ──> ProductoReparacionRepuesto ─┐
                                                   │      ├──> ProductoRegalo (accesorios regalados)           │
                                                   │      └──> ventas_detalles (cantidad 1)                    │
                                                   │                                                           │ baja de stock
                                                   └─> stock_sucursales ◄── stock_transferencias, stock_bajas  │
                                                       (la unica verdad)                                       │
                                                         │                                                     │
                     ventas ──> ventas_detalles ◄────────┴─────────────────────────────────────────────────────┘
                                 (P / R / A / C = cobro de una pieza montada: costo 0, NO mueve stock)
```

`ArticuloTipo` (Repuesto, Accesorio) es **la única fuente** de los nombres de tabla y columna que se interpolan en SQL (`tabla()`, `columna()`, `modelo()`, `permiso()`); `LineaTipo` (Producto, Repuesto, Accesorio) es el tipo de una línea de venta o de compra.

Todo lo que le pasa a un teléfono, a un artículo, a un cliente, a una venta, a una compra o a un usuario queda en **la bitácora** (`bitacoras`), con su autor y su antes/después. La escribe un Observer, no cada pantalla — ver la sección de abajo.

### Los servicios son los únicos escritores

| Lo que se escribe | Único camino |
|---|---|
| `stock_sucursales` y los totales cacheados | [StockService](app/Services/StockService.php) |
| `productos.estado` | [EstadoProductoService](app/Services/EstadoProductoService.php) |
| `ventas_detalles` | [VentaService](app/Services/VentaService.php) (`registrar`, `actualizar`) |
| anular una línea o una venta | [AnulacionVentaService](app/Services/AnulacionVentaService.php) |
| cobrar las piezas de una reparación | [RepuestosDeReparacionService](app/Services/RepuestosDeReparacionService.php) |
| `compras_detalles`, el alta de equipos y finalizar la compra | [CompraService](app/Services/CompraService.php) |
| la baja de un equipo o de unidades | [BajaService](app/Services/BajaService.php) |
| los regalos de un equipo | [ProductoRegalosService](app/Services/ProductoRegalosService.php) |
| buscar por IMEI / UPC / SKU / nombre | [BuscadorArticulosService](app/Services/BuscadorArticulosService.php) |
| `ventas_pagos`, `ventas.pagado`, `ventas.pagada_at` | [PagoService](app/Services/PagoService.php) |
| `reservas` y el estado Reserva | [ReservaService](app/Services/ReservaService.php) |
| el alta (y la devolución) del equipo recibido en permuta | [PermutaService](app/Services/PermutaService.php) |
| `compras_pagos`, `compras.pagado`, `compras.pagada_at` | [PagoProveedorService](app/Services/PagoProveedorService.php) |
| `compras_reclamos` y el estado Reclamo | [ReclamoService](app/Services/ReclamoService.php) |
| `comisiones` y `comisiones_liquidaciones` | [ComisionService](app/Services/ComisionService.php) |

**Ningún servicio abre transacción**: la abre el componente, para que un fallo revierta el stock **y** el documento. Los componentes no escriben esas tablas por su cuenta: si una pantalla necesita algo nuevo, va al servicio.

### El stock: `StockService`

`stock_sucursales` (repuesto_id **o** accesorio_id, sucursal_id, cantidad) es **la única verdad** del inventario. `repuestos.cantidad` y `accesorios.cantidad` son **totales cacheados** (la suma de esa tabla), en `$guarded` porque solo los escribe `recalcularTotales()`. Si un stock "no se guarda", ese `$guarded` es el primer sitio donde mirar.

Cada método recibe `(ArticuloTipo $tipo, int $id, ...)`. Reglas, todas con su motivo en el docblock:

- **No hay `mover($delta)` con signo.** `ingresar` / `retirar` / `ajustarEntrada` / `ajustarSalida` / `transferir`, siempre con cantidades positivas: la dirección vive en el nombre del método. Compras calcula `nuevo − original` y ventas `original − nuevo`, **las dos correctas**, y por eso se copiaban mal.
- **El `WHERE cantidad >= ?` de `retirar()` ES la validación de stock**, y es atómica. No la saques a un `SELECT` previo: entre un select y un update cabe otra venta.
- **El corte en `$cantidad === 0`** es lo que permite reguardar una venta vieja sin tocarle la cantidad.
- `recalcularTotales()` se llama **una vez al final** de cada guardado. Recuerda qué artículos tocó **por instancia** (`$tocados`): pide el servicio con `app(StockService::class)` dentro del flujo y no reutilices una instancia entre operaciones ajenas.

**El stock mínimo es por artículo y sucursal**: `stock_sucursales.minimo` (0 = sin mínimo), escrito solo por `StockService::fijarMinimo()` (un upsert que crea la fila en 0 unidades). Los movimientos solo tocan `cantidad`, así que no lo pisan. «Por agotarse» = `minimo > 0 AND cantidad <= minimo`, en una sucursal: `ArticuloDeStockTrait::scopeBajoStock()` (whereExists con alias propio) y `StockSucursal::estaPorAgotarse()`. Ya no existe `UMBRAL_BAJO_STOCK`.

**La sucursal de un movimiento se congela en la línea**, no se deduce del documento: `ventas_detalles.sucursal_id`, `compras_detalles.sucursal_id`, `productos_reparaciones_repuestos.sucursal_id`, `productos_regalos.sucursal_id`. Anular o quitar la línea devuelve el stock **a esa sucursal**, aunque el equipo se haya mudado (la reparación terminada se muda al Almacén).

**Las tablas de stock usan FK explícitas, no polimorfismo**: `repuesto_id` y `accesorio_id` nullable con un `CHECK` de exactamente uno, y en `stock_sucursales` dos `UNIQUE` compuestos (`(repuesto_id, sucursal_id)` y `(accesorio_id, sucursal_id)`; los NULL no chocan, así que el `ON DUPLICATE KEY` de `ingresar()` funciona). Por debajo de MySQL 8.0.16 los `CHECK` se ignoran **en silencio**.

`app/Models/MovimientoStock.php` es un **modelo virtual sin tabla** (`movimientos_stock` no existe): un `UNION ALL` de compras, ventas, reparaciones (solo repuestos), regalos (solo accesorios), bajas y la transferencia en dos ramas (Salida en el origen, Entrada en el destino). Solo se consulta con `paraArticulo($tipo, $id)`. Las ramas se emparejan **por posición** y son trece columnas: si agregas una, va en todas y en el mismo orden. Las ramas de reparación y de regalo son **condicionales por tipo**: si no, el historial del accesorio #5 mostraría las piezas del repuesto #5. Su docblock explica por qué `$table` y el alias del `fromSub` deben ser la misma cadena y por qué `$incrementing = false` — no lo toques sin leerlo. Un tipo de movimiento nuevo necesita su case en `MovimientoStockTipo`: el filtro hace de whitelist y lo que no esté en el enum **se filtra fuera en silencio**.

### Venta y compra unificadas

`ventas_detalles` y `compras_detalles` llevan `producto_id`, `repuesto_id` y `accesorio_id` nullable, y dos **columnas generadas STORED**:

- `tipo` (Producto / Repuesto / Accesorio): agrupa los reportes y **no puede contradecir** a las FK.
- `articulo_clave` (`P-12`, `R-5`, `A-3`, y `C-<id>` para un cobro de reparación): es la **clave natural** de la línea, con `UNIQUE (venta_id, articulo_clave)`. Vuelve idempotente la edición: reintentarla no duplica líneas.

Más `CHECK` de exactamente un artículo, equipo con cantidad 1 y cantidad ≥ 1. Las columnas generadas **no van en `$fillable`** (MySQL rechaza escribirlas).

- **El costo se relee de la base**, nunca del formulario: del equipo bloqueado (`costo_total`, que ya incluye regalos y reparaciones) o del artículo. La línea lo congela.
- **Orden de bloqueo idéntico en todos los flujos**: equipos por id y luego stock por (tipo, id). En otro orden, dos ventas simultáneas se bloquearían mutuamente.
- **Los regalos del equipo son líneas de la misma venta** (`producto_regalo_id`, clave `G<id>`): `VentaService::registrarRegalos()` las crea solas al vender el equipo, releyendo `productos_regalos` de la base.
  - Accesorio con precio 0, costo 0 (ya está en el `costo_total` del equipo) y `producto_asociado_id` = el equipo.
  - **Sin mover stock**: salió al regalarlo. `VentaDetalle::esRegalo()`; `stockYaDescontado()` = cobro o regalo.
  - No entran en el diff de `actualizar()`, ni en `MovimientoStock`, ni en las unidades de los reportes.
  - No se anulan sueltas: `VentaDetalle::borrarRegalosDe()` las **borra** con su equipo, antes de su línea (el CHECK `vd_regalo_es_accesorio` exige el equipo). El regalo sigue en `productos_regalos`.
- **El cobro de las piezas de una reparación es una línea más de la misma venta** (`producto_reparacion_repuesto_id`), con costo 0 —la pieza ya está dentro del costo del equipo— y **sin mover stock** (`VentaDetalle::stockYaDescontado()`). Se descobra **antes** de tocar la venta. La FK de cobro va en RESTRICT: `PiezasCobradasTrait` avisa antes de quitar o cambiar una pieza ya cobrada (con SET NULL, el cobro se convertía en una venta normal que movía stock).
- `venta_id` / `compra_id` van en **RESTRICT**: anular pasa por el servicio, que devuelve el stock; nunca por una cascada. Una venta sin líneas se borra (una cabecera vacía se lee como "no se guardó"). Una compra **en borrador** sin líneas sí es válida: se crea la cabecera y se carga después.
- **No existe `productos.compra_id`**: la línea de `compras_detalles` (UNIQUE `producto_id`) es la única verdad de qué compra trajo el equipo. `Producto::compra()` es un `hasOneThrough`; `Producto::compraDetalle()` y `ventaDetalle()` son las líneas.
- `ventas_detalles.tipo_venta` congela el tipo de venta del equipo al venderse: los reportes no cambian si luego se edita el equipo.
- **Hay una sola puerta de venta**: [VentaForm](app/Livewire/Venta/VentaForm.php) (crear y editar). El modal de estado del producto ya no vende: su botón «Vender» lleva a `ventas/crear?producto=`.

### El producto: estado, baja y regalos

`ProductoEstado`: Inventario, Reparacion, Fuera, Roto, Reserva, Credito, Vendido, Reclamo, EnCompra. **Fuera** = salió del local (lo tiene alguien); **Roto** = está roto. **Ninguno es una baja.** No hay Tránsito ni Oferta: la oferta es `productos.tipo_venta` (Venta, Oferta, Venta externa), y un equipo en oferta está en Inventario. `Vendido` y `Credito` (`ProductoEstado::vendidos()`) **solo los escribe una venta**, `Reserva` **solo una reserva** (`ReservaService`), `Reclamo` **solo un reclamo al proveedor** (`ReclamoService`) y `EnCompra` **solo una compra en borrador** (`CompraService`): son `soloPorDocumento()` y no salen en el selector. `ProductoEstado::puedeAbrir()` decide si el botón de estado de las tablas abre el modal (para ver y operar el documento). `fueraDeCatalogo()` es lo que el catálogo público excluye (vendidos, rotos, reservados).

`productos.estado` lo escribe **solo** `EstadoProductoService::cambiar($productoId, $esperado, $destino, $descripcion, $enlaces, $exigirPermiso)`:

1. **Relee el producto con `lockForUpdate()`** y rechaza los dados de baja.
2. **Exige que siga en `$esperado`** — el estado con el que se abrió la pantalla. Si no, `ValidationException` que **nombra el estado que encontró**. Esto vuelve inocuo reintentar.
3. Comprueba `producto.estado.<destino>` **solo si `$exigirPermiso`** (donde el usuario elige el estado de un select ya filtrado). Cuando el cambio es consecuencia de otra operación —vender, terminar una reparación— no se exige: dejaría sin vender a quien puede vender.
4. `$producto->anotar($destino->value, ...)` y el `update()` desde **la misma variable**: la bitácora y el estado no pueden contradecirse.

**Por qué una lectura bloqueada y no un `UPDATE ... WHERE estado = ?`**: Laravel no activa `PDO::MYSQL_ATTR_FOUND_ROWS`, así que MySQL devuelve filas *cambiadas*, no *encontradas*. Un `SET estado='Fuera' WHERE estado='Fuera'` devuelve **0** y fingiría un conflicto. En `retirar()` el idiom funciona porque `cantidad - n` siempre cambia el valor.

**La baja es un atributo aparte, no un estado**: `dado_de_baja_at`, `motivo_baja` (`BajaMotivo`), `nota_baja`, `baja_user_id`, con un `CHECK` que exige fecha y motivo juntos. Archiva el equipo sin tocar su estado. **No usa SoftDeletes** (rompería `morphTo` y las relaciones): las consultas filtran con `Producto::scopeVigentes()` (listados) y `scopeDisponibles()` (vendible = Inventario **y** sin baja; es el único sitio de la pregunta "¿se puede vender?"). No se da de baja un equipo vendido, a crédito, reservado o en reparación. La baja de unidades de stock va a `stock_bajas` con su costo congelado; las dos alimentan la tarjeta de **Pérdidas** del reporte.

**Los regalos reemplazan al costo de envío**: `productos_regalos` (accesorio, cantidad, sucursal de origen y costo congelados). Bajan el stock del accesorio y suben `productos.costo_regalos`. Solo mientras el equipo no está vendido ni dado de baja: al vender, la línea congela el `costo_total` que ya los incluye.

`costo_total = costo_unidad + costo_regalos + costo_reparacion`, en Bs, y solo lo escribe `Producto::recalcularCosto()` (el trabajo externo no suma: lo paga el cliente).

`disponible_catalogo` **no se toca al vender**: es una casilla del operador, y el catálogo ya excluye lo vendido por estado.

### La bitácora: un Observer escribe, los servicios declaran la intención

`bitacoras` es **una** tabla polimórfica (`auditable_type` / `auditable_id`) para todo: el historial del teléfono, la pestaña «Cambios» de un artículo y el historial de cada usuario son filtros de la misma tabla.

Un modelo entra con `use Auditable;` (hoy: `Producto`, `Repuesto`, `Accesorio`, `Venta`, `Compra`, `Cliente`, `User`). Desde ahí [BitacoraObserver](app/Observers/BitacoraObserver.php) es **el único escritor** de los cambios de modelo, y hay dos vías para dar contexto:

```php
$producto->anotar('Vendido', "Vendido. Venta #65, Daniel, Bs 3.200", ['venta_id' => 65]);
$producto->update(['estado' => 'Vendido']);
// -> UNA fila: evento Vendido + la frase + {"estado": ["Inventario", "Vendido"]}

Bitacora::registrar($producto, 'garantia', 'Producto en reparacion por garantia...', [...]);
// -> un hecho suelto, para lo que NO es un save() del modelo
```

Sin `anotar()`, el Observer escribe `editado` con el diff a secas. Reglas:

- **`anotar()` va ANTES del save, nunca después.** Si el save no cambia ninguna columna, Eloquent no dispara `updated`; el Observer escucha también `saved`, porque **la nota es el hecho**.
- **La bitácora es inmutable**: `updating`/`deleting` lanzan excepción. Lo deshecho se registra como el hecho contrario (`cobro` → `cobro-anulado`, `baja` → `baja-revertida`, `regalo` → `regalo-quitado`).
- **`cambios` es siempre `[antes, después]`**; lo pinta un solo componente, `x-bitacora-cambios`. Un par equivalente (`0`/`false`, `"100.00"`/`100`) no es un cambio.
- **El SQL crudo es invisible para el Observer.** `StockService` mueve stock con `DB::statement`/`DB::update`, así que un ajuste, una transferencia o una baja de stock se registran a mano con `Bitacora::registrar()` (lo hacen `ArticuloStockSucursalTrait` y `BajaService`).
- **Nunca registra** `updated_at`, contraseñas, tokens 2FA, `clave_idempotencia` ni los totales cacheados `cantidad` (inundarían el historial con el eco de cada venta). Un modelo amplía la lista con `$auditarExcluye`.
- **`auditable_id` no tiene FK, a propósito**: borrar el sujeto no se lleva su historia. Las FK de contexto (`venta_id`, `compra_id`, `producto_reparacion_id`, `repuesto_id`, `accesorio_id`, `sucursal_id`) van en `set null`.

**El `evento` tiene dos familias que no deben mezclarse.** Un estado de `ProductoEstado` cuando el hecho **movió** el estado del teléfono; un [BitacoraEvento](app/Enums/BitacoraEvento.php) (`creado`, `editado`, `garantia`, `cobro`, `baja`, `regalo`, `stock-baja`...) cuando no. El auditor compara la última fila **de estado** con el estado real. Y **cuidado con la colación**: `utf8mb4_unicode_ci` no distingue mayúsculas, así que un evento `'reparacion'` sería **igual** a `'Reparacion'` en un `WHERE`.

La bitácora registra **lo que una persona hizo**; `MovimientoStock` contesta **a dónde fue el stock**. No compiten, y por eso el historial de un artículo tiene dos pestañas.

### Sucursales y usuarios: se desactivan, no se borran

Casi todas las FK hacia `sucursales` y hacia `users` son `nullOnDelete`: borrar una sucursal o un vendedor **no falla**, deja sus ventas sin sucursal o sin vendedor en silencio. Por eso los modales de eliminar cuentan los movimientos antes (`Sucursal::cantidadMovimientos()`, `User::cantidadMovimientos()`) y, si hay alguno, la única salida es **desactivar** (docs/01).

- **Sucursal inactiva**: deja de ofrecerse al **cargar** algo — los selects de alta usan `Sucursal::activas()` y su regla es `exists:sucursales,id,activa,1` —. Los **filtros** siguen con todas. Un select de **edición** usa `Sucursal::paraSelect($actualId)`.
- **El Almacén** (`Sucursal::ALMACEN`, `Sucursal::almacenId()`) es obligatorio: el código lo busca **por nombre**. Lo siembra `SucursalSeeder`, y no se puede eliminar, desactivar ni renombrar, también en el servidor.
- **Usuario inactivo**: no entra (`Fortify::authenticateUsing`) y pierde la sesión abierta (middleware `UsuarioActivo`). `User::desactivar()` borra sus `sessions`. Permiso propio `user.desactivar`; nadie se desactiva a sí mismo.
- **No hay registro público ni autoborrado de cuenta**, ni **usuarios por sucursal**: todos ven y operan todo.

### Idempotencia: reintentar un guardado no crea un segundo documento

El fallo: se registra una venta, el servidor hace COMMIT, y la respuesta no llega (el celular perdió señal). El usuario reintenta. Sin protección se creaba una **segunda venta**, o el reintento moría con *"el producto ya no está disponible"*, que habla del producto y no del guardado.

| Capa | Para qué | Quién |
|---|---|---|
| **Precondición** | que no se escriba dos veces | el índice `UNIQUE` y el `SELECT ... FOR UPDATE` |
| **Comodidad** | que el reintento vea «ya se guardó: #124» | un `SELECT` por la clave, antes de abrir la transacción |

[GuardadoIdempotenteTrait](app/Traits/GuardadoIdempotenteTrait.php): `#[Locked] public string $claveIdempotencia` sembrada con `nuevaClaveIdempotencia()` **en `mount()`, nunca en `render()`**, `yaGuardado()` antes de la transacción y `esClaveDuplicada()` (busca `clave_idem` en el nombre del índice) en el `catch (QueryException)`. Lo usan `VentaForm` y `CompraForm` al crear. La edición va por **clave natural** (`vd_venta_articulo_unico` / `cd_compra_articulo_unico`) y el alta de teléfonos por el IMEI.

### Pagos y crédito: `PagoService`

Lo cobrado de una venta vive en `ventas_pagos` (al vender, `momento` Venta; después, `momento` Cobro) y lo escribe **solo** [PagoService](app/Services/PagoService.php). En `ventas` quedan `pagado` (la suma, cacheada), `saldo` (**columna generada** `total - pagado`: no va en `$fillable` y no se refresca en el modelo hasta releerlo; para lógica usa `Venta::saldoPendiente()`) y `pagada_at`.

- **«Pago» y no «cobro» en el código**: «cobro» ya es el cobro de las piezas de una reparación (una línea de venta, `BitacoraEvento::Cobro`). En pantalla dice «Cobrar» y «Cobranzas»; los eventos de bitácora son `pago` / `pago-anulado`, sobre la **venta**.
- **`sincronizar()` es el único que decide el estado de cobro**: recalcula `pagado`, pone `pagada_at` al llegar a saldo cero (es la fecha que liberará la comisión, etapa 7) y mueve los equipos **Credito ↔ Vendido**. Lo llaman registrar y anular un pago, `VentaService::actualizar()` y `AnulacionVentaService::anularLinea()`. Una venta con saldo **exige `cliente_id`**.
- **VentaService decide Vendido o Credito ANTES de vender los equipos** (con el total previsto, incluidos los cobros de taller), para que el historial del equipo no anote Vendido y enseguida Credito.
- **No se baja el total por debajo de lo cobrado**: lo rechaza `Venta::recalcularTotales()` con un mensaje, antes de que el `CHECK ventas_pagado_rango` lo haga con un error de SQL.
- **Anular la venta entera (o su última línea) borra sus pagos** (`anularTodos()`), uno por uno en la bitácora: se entiende que el dinero se devolvió. La FK de `ventas_pagos.venta_id` va en RESTRICT.
- **Idempotencia del cobro**: `CobroModal` reparte una clave entre los pagos que crea; `UNIQUE (clave_idempotencia, venta_id, metodo_pago_id)`. Por eso `PagoService` junta en uno los pagos del mismo método.
- **Orden de bloqueo**: la venta, después sus equipos por id, después el stock.
- Los métodos de pago (`metodos_pago`) se desactivan, no se borran, si tienen pagos: `MetodoPago::activos()` para cobrar, todos para los filtros.
- Refrescar tras un pago: todos los componentes que muestran saldo escuchan **`pagosActualizados`** (y las reservas, **`reservasActualizadas`**).
- **Tres clases de pago, todas con `monto` en Bs**:
  - a mano (`registrar()`), en Bs o en **USD** (`moneda`, `monto_moneda`, `tipo_cambio`; Bs = USD × TC). `PagoService::ultimoTipoCambio()` propone el último;
  - la **permuta** (`registrarPermuta()`): el método de sistema «Permuta» (`MetodoPago::PERMUTA`, `sistema = 1`, fuera de `activos()`) con `producto_id` = el equipo recibido. Decisión del usuario: es un pago, no un descuento; la venta vale lo vendido;
  - la **seña** de una reserva (`registrarSena()`, momento `Sena`), al concretarla.
  Las dos últimas **no se anulan sueltas**: se deshacen anulando la venta, y entonces `anularTodos()` devuelve el equipo recibido (`PermutaService::devolver()`, que se niega si ya se vendió o reparó) y cancela la reserva con la seña devuelta.
- El equipo recibido en permuta **no tiene compra**: su origen es `Producto::permuta()` (el auditor no lo cuenta como "sin compra") y su costo no se edita (es el pago).

### Reservas: `ReservaService`

Un equipo apartado por un cliente con una seña (`reservas`). Mientras está `Activa`, el equipo está en estado Reserva: fuera de la venta y del catálogo. **No vence**; se **concreta** (la venta con `cabecera['reserva_id']`: `VentaService` bloquea la reserva primero, exige su equipo en la venta, fija el cliente, vende el equipo esperando Reserva y al final registra la seña) o se **cancela** eligiendo `SenaDestino` (Devuelta / Retenida). Una sola reserva activa por equipo: columna generada `producto_activo` con UNIQUE. **Orden de bloqueo: reserva → venta → equipos → stock.**

### Compras en borrador: `CompraService::finalizar()`

Una compra de 50 equipos no se carga en una sola pantalla sin perderla al primer corte de internet. Por eso `CompraForm` crea **solo la cabecera** (`crear()`), en borrador, y todo lo demás se guarda línea por línea desde el detalle (`CompraLoteIndex`):
- los equipos por `CompraLoteAddModelModal` → `agregarProducto()`;
- los repuestos y accesorios por `CompraArticulos` → `guardarArticulo()` / `quitarArticulo()`, cada acción en su transacción.

- **Borrador = `compras.finalizada_at` NULL.** `CompraEstado::Borrador` se deriva de eso (`Compra::esBorrador()`, `scopeFinalizadas`).
- **En borrador nada entra al inventario.**
  - Las líneas de artículo no tocan el stock.
  - Los equipos nacen en **`ProductoEstado::EnCompra`** (`soloPorDocumento`, fuera de `disponibles` y del catálogo). Su línea guarda en `compras_detalles.estado_destino` el estado elegido: Inventario, Fuera o Roto, nunca Reparación (`estadosDestinoBorrador()`).
  - `BajaService` y `ProductoRegalosService` lo rechazan.
- **`finalizar()`** sigue el orden de bloqueo de la casa: compra → equipos por id → stock.
  1. `EstadoProductoService::cambiar(EnCompra → destino)` y limpia `estado_destino`.
  2. `ingresar()` a la sucursal de cada línea.
  3. La fila `finalizada` en la bitácora de la compra.
  4. Lo pagado al recibir (`PagoProveedorService::registrar`).

  Rechaza una compra ya finalizada (así un reintento no mete el stock dos veces) y una sin líneas.
- **Finalizada, se sigue corrigiendo**: los mismos métodos ven `esBorrador()` y mueven el stock al instante (`ingresar`, `ajustarEntrada`, `retirar`).
- **Lo que debe ignorar los borradores**:
  - la rama de compras de `MovimientoStock`;
  - `ReporteIndex::lineasCompra()`;
  - el valor de inventario.

  Una tabla que pinta `estado()` con `label()` necesita `compras.finalizada_at` en `setAdditionalSelects`, o toda compra sale Borrador.
- **Eliminar un borrador** lo borra entero, con sus equipos. Solo lo impiden los pagos.

### Cuentas por pagar y reclamos al proveedor

- **`compras_pagos` es el espejo de `ventas_pagos`**: `compras.pagado` cacheado, `saldo` generado, `pagada_at` (solo si el total es mayor que 0). Lo escribe solo `PagoProveedorService`; `Compra::recalcularTotal()` rechaza un total por debajo de lo pagado y resincroniza `pagada_at`. Una compra con pagos **no se elimina** (decisión del usuario). Los componentes escuchan **`pagosProveedorActualizados`**.
- **Las filas de pago son una sola lógica**: `Services\Concerns\FilasDePago` (validar, Bs/USD, juntar por método y moneda, filas en 0 ignoradas) del lado del servidor, y `Traits\FilasDePagoFormTrait` + el parcial `livewire.partials.filas-pago` del lado de la pantalla. Venta y compra los usan; no se copian.
- **Reclamo**: `compras_reclamos` (un reclamo abierto por equipo, columna generada `producto_abierto`). El estado de la compra (`CompraEstado`: Borrador / Recibida / Con reclamo / Resuelta) **se deriva** de `finalizada_at` y de los reclamos (`Compra::estado()`, `scopeConEstado`, `scopeConConteoReclamos`), no se guarda.
- **Cerrar un reclamo**: Reemplazo (nuevo equipo en la misma compra con el costo del fallado, por `CompraService::agregarProducto`), Descuento o Aceptado (vuelve a Inventario o Roto). En los dos primeros el fallado **se devuelve**: `costo_unidad` y su línea de compra a 0 y baja con `BajaMotivo::Devolucion`, que solo se usa desde un reclamo (`BajaService` lo exige en ambos sentidos), no se revierte y no se ofrece a mano (`BajaMotivo::manuales()`).
- **Orden de bloqueo**: compra → equipos por id → stock.

### Comisiones: `ComisionService`

Una fila de `comisiones` por venta (para su vendedor, `user_id`) o por reparación (para su técnico, `tecnico_id`), **aunque el monto sea 0**: así el % queda congelado el día que nace. El estado (`ComisionEstado`: Pendiente / Por pagar / Pagada) **se deriva** de `ganada_at` y `liquidacion_id`; los scopes de `Comision` son la única traducción a SQL.

- **Vendedor**: % de su ficha sobre `Venta::ganancia()` (toda la venta; la permuta es un pago). Ganancia negativa → 0. Se gana con `pagada_at`. Lo mantiene `sincronizarVenta()`, que llama **`PagoService::sincronizar()`** al final: por ahí pasan todos los flujos de la venta. `AnulacionVentaService` llama `desligarVenta()` antes de borrar la venta.
- **Técnico**: % de su ficha sobre `productos_reparaciones.costo` (la mano de obra). Se gana al terminar (`fecha_recogida`). **La de garantía no comisiona.** La mantiene **`ProductoReparacionObserver`** (`saved`): la reparación se escribe desde cinco pantallas y engancharse en cada una era la copia que diverge. Por eso **`productos_reparaciones` no se escribe con el query builder**, y el servicio **relee la fila** (la instancia guardada puede estar vieja: otra pantalla la terminó).
- **La liquidada no se toca**: editar o anular su documento no la cambia. `venta_id` / `producto_reparacion_id` van en SET NULL (y fuera de los CHECK), así la pagada sobrevive con su `referencia` congelada. Anular una liquidación devuelve sus comisiones a por pagar, resincronizadas, y borra las que ya no tienen documento.
- Una persona es una clave `U-5` / `T-3` (`Comision::partirClave`, `scopeDeBeneficiario`); `scopeDeUsuario` junta lo del usuario y lo del técnico vinculado a él (`tecnicos.user_id`, «Mis comisiones»). `Comision::cifras()` da las tres cifras en un SELECT.
- Refrescar: **`comisionesActualizadas`**.

### Accesorios del equipo

`ventas_detalles.producto_asociado_id` agrupa un accesorio o repuesto bajo el equipo con el que se vendió (detalle y nota). `VentaService` solo lo acepta si ese equipo está en la misma venta, y quitar el equipo lo desasocia. La nota térmica es `ventas/{id}/nota` (`VentaController@nota`), HTML suelto de 80 mm que se imprime solo. El **recibo PDF** (carta) es `ventas/{id}/pdf` (dompdf, `recibo-pdf.blade.php`): **solo tablas**, dompdf no entiende flex ni grid. Los dos salen de `VentaController::cargarRecibo()`, el único agrupado de lo vendido bajo su equipo. Llevan el **QR de garantía** (`App\Support\QrGarantia`, SVG de BaconQrCode) que apunta a la página pública `/garantia`: usa `route()`, así que **`APP_URL` de producción debe ser la dirección pública**. El botón de WhatsApp del detalle (Alpine `compartirRecibo`) comparte el PDF con el menú del celular (Web Share con archivos); donde no se puede, lo descarga y abre `wa.me` con `Cliente::telefonoWhatsapp()`.

### El lector de códigos: la cámara imita a la pistola

La pistola USB "teclea" el código y aprieta Enter. La cámara del celular hace **exactamente lo mismo**, así que el servidor tiene un solo camino para las dos.

- **Un solo lector**, en el layout: [x-escaner-overlay](resources/views/components/escaner-overlay.blade.php) + el Alpine `escaner` de [app.js](resources/js/app.js). [escaner.js](resources/js/escaner.js) se carga con `import()` al abrirlo. Usa el `BarcodeDetector` nativo donde existe y, si no, `barcode-detector/ponyfill` (zxing-wasm). **El `.wasm` sale de `public/build`** (`?url` de Vite + `prepareZXingModule`), no del CDN por defecto de la librería.
- Cada campo va en un contenedor `data-escaner` con su `<input>` y un [x-boton-escaner](resources/views/components/boton-escaner.blade.php). `modo="enter"` (buscadores: escribe y dispara Enter) o `modo="input"` (un `wire:model` común: dispara input y change, y luego Enter). `continuo` deja la cámara abierta para leer varios.
- **El Enter de un buscador manda `$el.value`, no la propiedad**: `x-on:keydown.enter.prevent="$wire.metodo($el.value); $el.value = ''"`. El `wire:model.live.debounce` todavía no tiene el código cuando llega el Enter de la pistola, y el servidor recibía el campo vacío o a medias.
- La regla del Enter es **un código exacto y único elige; si no, queda la lista** ([EligePorCodigoTrait](app/Traits/EligePorCodigoTrait.php), y `porCodigo()` en la venta). Se compara sobre las filas que el componente **ya filtró**, para no saltarse sus reglas. El UPC de la caja de un teléfono es del modelo y no elige solo: el IMEI sí.
- **La pistola se escucha en segundo plano** ([pistola.js](resources/js/pistola.js), arrancada desde el Alpine `escaner`, así que solo en el `main-layout`): reconoce la ráfaga por el tiempo entre teclas (`keydown` en **captura**) y la entrega al **buscador** de la pantalla aunque el foco esté en otro campo.
  - El buscador es el contenedor con `data-escaner-principal="<prioridad>"`, el visible y habilitado de mayor prioridad. Con un modal abierto, solo el del `.jetstream-modal` de más arriba. Hoy son `buscador-articulos` (2) y la búsqueda de las tablas con escáner (1), más los buscadores de 7 modales. **Un buscador nuevo necesita su `data-escaner-principal`**, o la pistola no lo encuentra.
  - Con el foco en un campo `data-escaner` que **no** es el buscador (IMEI, SKU, UPC del alta, IMEI de la permuta), la pistola escribe ahí como siempre. Sin buscador visible no toca nada.
  - Lee el modo del `data-modo` de `x-boton-escaner`. Cámara y pistola escriben con la misma `escribirCodigo()`. En desarrollo, `simularPistola('…')` en la consola.
- **Un campo de código no lleva `wire:model.live` sin debounce**: la pistola teclea 15 dígitos y cada uno era una petición. El IMEI del alta de equipo va con `.change`, y su Enter salta de campo y nunca guarda.
- La búsqueda de las tablas gana el botón con `public bool $buscarConEscaner = true` (plantilla publicada `vendor/livewire-tables/.../search-field.blade.php`).
- **La cámara de fotos del equipo** también es una sola, en el layout: [x-camara-fotos](resources/views/components/camara-fotos.blade.php) + el Alpine `camaraFotos`. Se abre con `$dispatch('abrir-camara-fotos', { alTomar: (foto) => subirFoto($wire, 'fotoNueva', foto) })` y queda abierta para varias fotos (JPEG de 1280 px como mucho). **No va dentro de un componente Livewire**: cada respuesta del servidor le devolvía su `hidden`. Las dos cámaras comparten `abrirCamara()` / `detenerCamara()` de `escaner.js`, que guarda **el único stream encendido** y apaga el anterior al abrir: un stream perdido del lector dejaba las fotos en pantalla negra.
- **Las fotos del equipo son archivos en el disco `public`** (`productos/{producto_id}/{uuid}.jpg`), no base64: `productos_imagenes.ruta` es la referencia y `ProductoImagen::url()` la URL: la ruta pública `productos/fotos/{id}` ([ProductoFotoController](app/Http/Controllers/ProductoFotoController.php), caché eterno). **No `/storage/...`**: en producción el servidor web es otro contenedor y no llega al enlace `public/storage`, así que las fotos daban 404. La cámara entrega un data URL que **`subirFoto()`** (app.js) sube con el upload de Livewire a la propiedad `fotoNueva` (`WithFileUploads`); el modal la valida en `updatedFotoNueva()` y la deja en `$fotos` hasta guardar. Se guardan **solo** con `ProductoImagen::guardar()` (JPEG 85, lado mayor ≤ 1280 px; el JPEG de la cámara, que ya cabe, se guarda tal cual sin pasar por GD. GD necesita JPEG: el `Dockerfile` lo configura con `--with-jpeg`, sin eso ninguna foto dejaba guardar el equipo en producción) y se borran **por Eloquent**: el evento `deleted` se lleva el archivo y su miniatura (`whereIn()->delete()` del query builder los dejaría huérfanos). El **respaldo** es la base **y** `storage/app/public`.
- **Las tablas (Productos, detalle de la compra) muestran la primera foto como miniatura por URL**: `ProductoMiniaturaController` (`productos.miniatura`) la reduce una vez a 160 px con GD y la guarda en `storage/app/miniaturas/{id}-160.jpg`, con caché eterno porque una imagen no se edita (por eso el lado va en el archivo y en la URL, `?l=160`: cambiarlo sin cambiar la URL dejaría la vieja en el navegador). El id sale de una subconsulta del `builder()` (`subconsultaPrimera()`) y la celda de `html()`. En el celular la columna va en el desplegable (`collapseOnTablet`, que pinta el mismo HTML: 80 px allí y 40 px en la celda de escritorio), y tocarla abre el visor a pantalla completa ([ProductoFotosModal](app/Livewire/Producto/Modals/ProductoFotosModal.php), evento `openProductoFotosModal`, Alpine `visorFotos`), montado en Productos y en el detalle de la compra. Solo mira: las fotos se sacan y se quitan en el modal de edición.
- La cámara **exige HTTPS** (`window.isSecureContext`); `localhost` cuenta como seguro. El overlay va en `z-[70]`, encima de los modales: su `x-trap.inert` solo pone `aria-hidden` afuera, así que los toques llegan.

### El cliente: una ficha y un texto congelado

`ventas.cliente_id` enlaza con la ficha, y la columna de texto `ventas.cliente` sigue ahí como **archivo** de lo que se escribió: se llena en el `create()` y nunca se actualiza.

> **La ficha es lo que se muestra y por lo que se navega; el texto es el archivo.**

Vive en `Venta::nombreCliente()` (`$this->fichaCliente?->nombre ?? $this->cliente`); ningún blade lee la columna a pelo. **La relación se llama `fichaCliente()` y NO `cliente()`**: `cliente` es una columna, Eloquent resuelve primero los atributos y la relación quedaría inalcanzable. El historial del cliente va **estrictamente por `cliente_id`**. El cliente es **opcional** en la venta (la de mostrador sin ficha es lo normal); lo obligatorio es el `nombre` dentro de la ficha.

Elegirlo es [ClienteBuscadorTrait](app/Traits/ClienteBuscadorTrait.php) + [x-cliente-picker](resources/views/components/cliente-picker.blade.php): teclear, mirar el catálogo o **crear al vuelo**. El alta despacha **`clienteCreado` y no `refreshClienteTable`**: ese lo despachan también los modales de editar y eliminar **sin argumento**.

### El dinero: todo en Bs

**Todo se calcula en Bs.** El dólar entra solo en dos sitios, y en los dos el monto en Bs es lo único que lee el resto: los pagos en USD (ver «Pagos y crédito») y **el costo de un equipo cargado en USD** (`productos.costo_moneda`, `costo_moneda_monto`, `costo_tipo_cambio`, con un CHECK de los tres). `costo_unidad` sigue en Bs y **lo deriva el hook `saving` de `Producto`** (USD × TC): la línea de compra, el costo congelado de la venta, la ganancia y los reportes no saben de dólares. Quien deja un equipo en costo 0 (la devolución de un reclamo) lo pasa a BOB, o el hook le devolvería el costo. Los precios están siempre en Bs. `ventas.total = subtotal − descuento + mano_obra` (y `saldo = total − pagado`, ver «Pagos y crédito»), y `costo_total = Σ subtotal_costo + mano_obra`: la mano de obra suma al total **y** al costo, para cancelarse en la ganancia. Los cobros de piezas van con **costo 0** por la misma razón. Las reparaciones: `costo_total = costo (mano de obra) + costo_repuestos`.

[ReporteIndex](app/Livewire/Reporte/ReporteIndex.php) es **una familia de consultas** sobre `ventas_detalles` / `compras_detalles` filtrada por `tipo`; el descuento de cabecera se prorratea entre **todas** las líneas de la venta. No hay respaldo de "20 % de ganancia" para equipos sin costo: la línea congela el costo real.

Los reportes son **una pantalla por reporte** (`Reporte/ReporteIndex` el general, `ReporteVendedoresIndex`, `ReporteProductosIndex`, `ReporteInventarioIndex`, `ReporteClientesIndex`), cada uno con su permiso `reporte.*`. `ReporteController::REPORTES` es la única lista (pestañas `partials/nav` e ítem del menú, que va a `primeraRuta()`).
- `ReporteFiltrosTrait` tiene el filtro de sucursal (`porSucursal($query, 'v.sucursal_id')`) y **las dos expresiones del descuento prorrateado** (`DESCUENTO_PRORRATEADO`, `INGRESO_NETO`, con los alias `d` / `v`): una sola copia para que los reportes cuadren entre sí.
- `ReportePeriodoTrait` tiene el período de los reportes nuevos (el mes actual, `mes(±1)`).
- La **entrada** de un equipo es `COALESCE(compras.fecha, DATE(productos.created_at))`: la permuta no tiene compra.
- Son tablas Blade sobre consultas agregadas, sin rappasoft; las listas largas se paginan con nombre propio.

---

## Convenciones de Livewire (importantes)

**No existe `#[Layout]` en este proyecto.** Una pantalla completa es siempre el mismo sándwich:

```
routes/web.php ──> Controller ──> resources/views/app/<modulo>/<accion>.blade.php
                                     <x-main-layout> @livewire('modulo.componente') </x-main-layout>
```

**Cada módulo es una tríada** `XIndex` (la página y los botones) + `XTable` (datatable de rappasoft) + `X/Modals/*`. Los modales se declaran **una vez** al final del blade del índice y se abren por evento:

```php
$this->dispatch('openProductoEstadoModal', $id);   // el modal lo recibe con #[On('openProductoEstadoModal')]
$this->dispatch('refreshProductoTable');           // tras guardar
$this->dispatch('filtersUpdated', [...]);          // Index -> Table
```

**Repuestos y accesorios comparten pantallas** (`Articulo/*`, `ArticuloHistorial/*`, `StockTransferenciaModal`, `StockBajaModal`, `ArticuloSelectorModal`). El tipo llega por **parámetro de montaje** con `#[Locked]`, nunca por evento: el `filtersUpdated` que despacha `mount()` del índice llega antes de que la tabla exista. Los `@can` salen de `ArticuloTipo::permiso()` (`repuesto.*` / `accesorio.*`).

**`wire:key` debe llevar el índice** cuando el `wire:model` se enlaza por posición (`lineas.{{ $index }}.precio`). `wire:model` no desmonta su enlace al cambiar el atributo, y reutilizar la fila deja dos inputs escribiendo en el mismo sitio. Convención: `wire:key="linea-{{ $id }}-{{ $index }}"`.

**Livewire rehidrata los modelos por id, sin relaciones.** Los `load()` y los catálogos van en `render()`, nunca en `mount()` ni en `openModal()`. Un `if ($this->openModal)` alrededor deja el componente cerrado en 0 consultas.

**Paginación manual** (`->paginate($n, ['*'], 'page', $this->pagina)`) en los modales selectores: `WithPagination` reescribiría el query string de la pantalla de fondo.

**rappasoft/laravel-livewire-tables** solo hace SELECT de los campos declarados como columna: para leer otra columna dentro de un `->format()` o `->label()` hace falta `setAdditionalSelects(['tabla.columna'])`. Las columnas con punto (`venta.id`, `user.name`) unen una relación `belongsTo` sola; para un `hasOne`/`hasOneThrough` (la compra de un equipo) usa `->label()` con un `with()` en el builder.

---

## Trampas conocidas

- **`$fillable` gana a `$guarded`.** `Venta` declara `$fillable`: una columna que falte ahí la descarta `create()` **en silencio** (ya pasó con `mano_obra`). Sin `clave_idempotencia` la clave entraría como NULL, el índice único admite todos los NULL y **la idempotencia quedaría inerte sin un solo error**. Las columnas generadas (`tipo`, `articulo_clave`) **no** van ahí.
- **Un SKU o UPC vacío se guarda como NULL** (`NormalizaCodigosTrait`): con `''` el segundo artículo sin SKU choca con el índice único.
- **Los índices únicos son la garantía de verdad, no la regla `unique:`.** Los de dominio: `productos.imei`, `productos_sku_unico` (y los de repuestos y accesorios), `ventas_detalles_producto_unico` (un teléfono, una sola venta; anular **borra** la línea, así que revender funciona), `compras_detalles_producto_unico`, `vd_reparacion_repuesto_unico`, `vd_venta_articulo_unico`, `cd_compra_articulo_unico`, `clave_idempotencia` en `ventas` y `compras`, y los dos de `stock_sucursales`.
- **MySQL prohíbe acciones referenciales en columnas que usa un `CHECK` o una columna generada.** Por eso las FK de artículo y de cabecera de las líneas y del stock van en **RESTRICT**: borrar un artículo con movimientos lo impide el código con un mensaje claro, no una cascada.
- **`php artisan productos:auditar`** es el detector de deriva, de solo lectura: vendidos sin línea de venta, líneas sin vendido, ventas vacías, regalos de equipos vendidos sin su línea de venta (o al revés), compras finalizadas vacías, equipos En compra que no coinciden con el borrador de su compra, equipos sin línea de compra, IMEI repetidos, historial en desacuerdo con el estado, bajas vendidas, el descuadre de `repuestos.cantidad` y `accesorios.cantidad`, de `costo_regalos`, de `costo_total`, del costo de la línea de compra, del costo en USD contra su tipo de cambio y de los totales de venta y compra, `pagado` contra sus pagos, `pagada_at` contra el saldo, equipos en Credito/Vendido que no coinciden con el saldo de su venta, ventas a crédito sin cliente, equipos en Reserva sin reserva activa (o al revés), reservas concretadas sin su seña, permutas sin equipo o con otro costo, `compras.pagado` contra sus pagos, `pagada_at` de compras, equipos en Reclamo sin reclamo abierto (o al revés), devueltos al proveedor con costo, ventas y reparaciones sin su comisión (o comisiones que sobran), comisiones con `ganada_at`, base o monto en desacuerdo con su documento y liquidaciones descuadradas. Hoy dice **«Sin inconsistencias.»**: lo que importa es que siga así.
- **`Venta::cliente` es una columna, no la relación.** La ficha es `fichaCliente()` y lo que se pinta sale de `nombreCliente()`.
- **Dentro de una etiqueta `<x-…>` solo valen `@class` y `@style`.** Cualquier otra directiva —`@disabled`, `@checked`— impide que `ComponentTagCompiler` compile el componente, y al navegador le llega un `<x-checkbox>` **literal**, sin ningún error. En un componente va `:disabled="$expr"`; `@disabled(...)` solo sobre HTML plano. Para comprobarlo: tras `php artisan view:cache`, ningún archivo de `storage/framework/views` debe contener `<x-`.
- **El Observer no ve lo que no pasa por Eloquent.** `DB::table()->update()`, `DB::statement()` y el `update()` del query builder no dejan fila en la bitácora. Si el hecho importa, se registra a mano.
- **Un modal que regenera campos deja ese cambio en la bitácora.** `CompraLoteProductoEditModal` reescribe la `descripcion` ante cualquier cambio: no es ruido, la base cambió de verdad.
- **Tailwind no tiene safelist.** Una clase compuesta (`'bg-' . $color`) nunca se genera. Clases literales en cada rama (`ProductoTipoVenta::badgeClasses()`, `LineaTipo::badgeClasses()`), o `style` inline (`ProductoEstado::color()`, `Repuesto::getDivColor()`).
- **`brand-*` no es un color fijo**: son variables CSS (`resources/css/app.css`). Grises neutros en modo claro y el verde salvia heredado bajo `.dark`. Para cambiar el color de la marca se tocan esas variables, no las vistas. El fondo, el menú y la cabecera del layout usan `brand-50` / `brand-200` / `brand-300` en claro y sus `dark:bg-gray-*` en oscuro.
- **`@can` en el blade solo esconde el botón.** La ruta lleva su `->middleware('can:...')` (las de `{id}` además `whereNumber('id')`) y el componente su `abort_unless()`. Las únicas rutas abiertas son `/`, `/dashboard` y el catálogo público. Permisos de spatie, `modulo.accion`; `PermissionSeeder` le da todos al Administrador.
- **El selector de estado filtra por permiso** (`producto.estado.<estado>` en minúscula) y quita los de `soloPorDocumento()`. Un estado nuevo necesita su permiso, su caso en el enum y una migración que altere el enum de la columna (el DDL no se actualiza solo).
- **MySQL corta los identificadores a 64 caracteres** y su DDL **no es transaccional**. Nombra explícitamente índices, FK y `CHECK` (la convención del repo: `vd_*`, `cd_*`, `sb_*`, `prr_*`).
- **La lógica compartida vive en `app/Traits/`** (`CarritoBuscadorTrait`, `ArticuloStockSucursalTrait`, `PiezasCobradasTrait`, `ArticuloDeStockTrait`, `RepuestosReparacionFormTrait`), no en una copia más. Las piezas de una reparación se eligen en `RepuestosReparacionModal`, que responde con `repuestosReparacionElegidos(origen, …)`: los tres modales de reparación están montados a la vez en Productos, y solo el que lo abrió (`origen` = su `getId()`) toma la respuesta. Cada línea nueva lleva su propia `sucursal_id`. El fallo recurrente del repo heredado fue la copia que divergió.
- Los `tipo` y `tipo_venta` de las líneas de venta y compra son **históricos a propósito**: guardan lo que el artículo era al operar, para que reclasificarlo no reescriba un período cerrado.

## Estilo

Los comentarios explican **por qué**, no qué, y muy a menudo citan el fallo concreto que los motivó ("antes era un `where('imei', ...)` a secas, así que cambiar el origen colaba un producto que no pertenecía"). Escríbelos en español, sin tildes dentro del código PHP —el repo lo hace así— pero **con tildes en todo el texto que ve el usuario**.
