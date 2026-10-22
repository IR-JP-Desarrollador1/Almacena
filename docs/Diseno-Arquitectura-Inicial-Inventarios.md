# Borrador de diseño funcional y técnico — Inventarios

**Estado:** Línea base refinada con el alcance funcional actual; módulos posteriores continúan sujetos a decisiones específicas  
**Base funcional:** acuerdos de descubrimiento registrados en [Acta-Inicio-Fase-0-Inventarios.md](./Acta-Inicio-Fase-0-Inventarios.md)  
**Tecnologías de referencia:** Laravel, Vite y MySQL, según los requerimientos

Este documento registra decisiones confirmadas y pendientes para orientar la implementación incremental. Las funcionalidades no incluidas en el alcance actual se mantienen explícitamente como pendientes.

## 1. Alcance y decisiones base

- MVP para una empresa con varias sedes.
- Módulos incluidos: autenticación y permisos, estructura y catálogos, compras, inventario, logística, reportes y cargue masivo de productos.
- Sin integraciones externas en el alcance inicial.
- Entradas ordinarias vinculadas a una orden de compra y salidas ordinarias a una requisición.
- El jefe del área solicitante aprueba las requisiciones. Una requisición aprobada puede originar varias órdenes de compra en borrador, por etapas; cada orden requiere aprobación antes de recibir.
- Las cotizaciones se comparan por producto y requieren ofertas de al menos dos proveedores distintos. La selección es manual, permite adjudicar parcialmente por etapas y requiere justificación si no se elige el menor total con impuestos convertido a moneda base. Cada oferta registra precio neto, impuesto, moneda/tasa, marca y plazo; el costo trasladado a inventario permanece antes de impuestos.
- La calificación manual de proveedores conserva el historial de notas de distancia, tiempo de entrega y cumplimiento, de 1 a 5, y muestra el promedio simple más reciente.
- La empresa configura una moneda base. Las cotizaciones y órdenes conservan moneda cotizada, tasa de cambio (unidades de moneda base por unidad cotizada), costos cotizados y costos convertidos. La tasa debe ser 1 si las monedas coinciden; la moneda base queda bloqueada al existir cotizaciones u órdenes de compra.
- Se registra costo cotizado y costo convertido por lote. La salida conserva el costo del lote físico despachado, incluso cuando FEFO selecciona uno distinto del más antiguo.
- Se permiten recepciones y despachos parciales sin exceder las cantidades aprobadas. Cada producto configura si maneja vencimiento; si lo maneja, la recepción exige fecha y el despacho prioriza FEFO, con FIFO para desempatar. No se despachan lotes vencidos.
- La recepción exige una orden de compra aprobada y la salida una requisición aprobada; no hay aprobación adicional para el movimiento derivado.
- El catálogo inicial de monedas incluye COP, USD, EUR, GBP, CAD, MXN, BRL, CLP, ARS, PEN, CNY (yuan renminbi chino) y RUB (rublo ruso).
- El usuario podrá cargar productos mediante archivos Excel o CSV.

## 2. Propuesta de arquitectura

Se propone iniciar como un **monolito modular Laravel** con una interfaz web Vite y base de datos MySQL. Esta opción se ajusta al tamaño y tecnología del alcance descrito y evita introducir servicios distribuidos antes de que existan necesidades concretas que los justifiquen.

Separación funcional sugerida:

1. **Acceso:** autenticación, recuperación, sesiones, usuarios, perfiles, permisos y auditoría administrativa.
2. **Organización:** sedes, bodegas, ubicaciones y centros de costo.
3. **Catálogos:** productos, unidades, categorías, proveedores y monedas.
4. **Compras:** requisiciones, aprobaciones, comparación de cotizaciones, evaluaciones de proveedores y órdenes por etapas.
5. **Inventario:** lotes, entradas parciales, salidas parciales y costos; kardex, conteos, ajustes y devoluciones permanecen pendientes.
6. **Logística:** traslados, despacho, tránsito, entregas y comprobantes.
7. **Consultas:** existencias actuales e históricas, kardex, costos, cumplimiento y exportaciones. El kardex inicial consulta movimientos por bodega/producto con saldo corrido y costo en moneda base.

Los módulos compartirán servicios y validaciones del dominio, pero las reglas críticas no dependerán de controles visuales: autorización, alcance por sede/bodega, estados, cantidades y asociaciones se verificarán en el servidor.

## 3. Modelo conceptual inicial

Las siguientes entidades son una propuesta para validar durante el diseño; no constituyen aún un esquema físico final.

El esquema implementado materializa `users`, `companies`, `roles`, `permissions`, sus tablas de asociación, `access_logs` y `audit_logs`. La empresa se limita a un único registro mediante una clave constante con restricción única y define su moneda base en `currencies`. El perfil de empresa guarda por ahora país, división administrativa y ciudad como campos de texto. El primer incremento organizacional implementa sedes, bodegas, ubicaciones y centros de costo; los catálogos geográficos siguen pendientes. El catálogo de datos maestros incorpora monedas, unidades con código y abreviatura únicos, productos ligados a su unidad y configuración de vencimiento, y proveedores con identificación tributaria opcional y única cuando se informa. Las áreas solicitantes se asignan a usuarios; cada área tiene un jefe activo con permiso de decisión. Las requisiciones conservan solicitante, área, jefe que recibió el envío, líneas con una copia de los datos de producto/unidad, estado y auditoría de transiciones. El rechazo exige motivo y permite corrección y nuevo envío. Los campos de importación masiva no están definidos ni implementados.

| Área | Entidades principales | Responsabilidad |
|---|---|---|
| Organización | `sites`, `warehouses`, `locations`, `cost_centers` | Definir dónde se almacenan, solicitan y reciben productos. |
| Acceso | `users`, `roles`, `permissions`, asignaciones usuario-perfil | Identificar usuarios y limitar operaciones por perfil y alcance organizacional. |
| Catálogos | `currencies`, `products`, `units`, `suppliers` | Mantener monedas activas, moneda base de empresa, productos, unidades de medida y proveedores. El producto configura `tracks_expiration`. |
| Compras | `requisitions`, `requisition_items`, `purchase_orders`, `purchase_order_items` | Trazar requisición aprobada, orden de compra, aprobación separada, moneda cotizada, tasa de cambio y costos cotizado/base. El comparador de ofertas no está implementado. |
| Inventario | `receipts`, `receipt_items`, `warehouse_lots`, `inventory_issues`, `inventory_issue_allocations`, `stock_movements` | Registrar recepciones y despachos parciales, lote/vencimiento, historial de movimientos y costos cotizado/base. |
| Logística | `transfers`, `deliveries`, `movement_documents` | Trazar traslados, entregas, comprobantes y documentos de soporte. |
| Control | `audit_logs`, `import_batches`, `import_rows` | Auditar cambios administrativos y conservar resultados de cargues masivos. |

Relaciones y reglas candidatas:

- Una sede puede tener varias bodegas; una bodega puede organizar ubicaciones.
- Las requisiciones y órdenes tienen encabezado y líneas detalladas, con estados y marcas de tiempo.
- La recepción referencia la orden de compra y sus líneas; las líneas recibidas identifican producto, cantidad y lote cuando corresponda.
- La salida referencia la requisición y consume existencias de una bodega.
- Los movimientos confirmados se conservan como historial; las correcciones se representan con reversos o ajustes autorizados, no con edición silenciosa.
- Los reportes históricos se derivan del historial de movimientos con fecha efectiva y trazabilidad; no solo del saldo actual.
- Los permisos funcionales se combinan con el alcance asignado al usuario en bodegas. No se infiere acceso a todas las bodegas de una sede por tener asignación a una de ellas.

## 4. Reglas críticas del inventario

### 4.1 Recepción

1. Verificar que la orden de compra esté aprobada y pertenezca a una requisición autorizada.
2. Validar producto y cantidad contra el saldo pendiente de la orden; se permiten recepciones parciales, sin sobrantes.
3. Si el producto maneja vencimiento, requerir la fecha al recibirlo y asociarla al lote.
4. Registrar transaccionalmente cantidad, lote, costos en moneda cotizada y base, moneda y tasa de la orden.

### 4.2 Despacho y valoración

El orden físico de selección y la asignación de costo son reglas distintas:

- **Selección física:** FEFO entre lotes de productos que manejan vencimiento; FIFO para productos sin vencimiento y como desempate de fechas iguales.
- **Costo de salida:** aplicar el costo del lote físico seleccionado y registrar su trazabilidad.

Por lo tanto, el costo no se asigna necesariamente por orden FIFO de recepción cuando FEFO selecciona otro lote. Las asignaciones de cada salida guardan el costo del lote seleccionado y su valor en moneda base. El kardex, la valoración histórica, devoluciones y ajustes quedan pendientes.

### 4.3 Ajustes y devoluciones

Bajas por daño, sobrantes, faltantes, devoluciones y traslados deben generar documentos y movimientos con actor, motivo, ubicación y fecha. Los permisos y aprobaciones específicos están pendientes. No se debe permitir que una acción de edición ordinaria reescriba el historial confirmado.

## 5. Seguridad y permisos

- Usar autenticación y gestión segura de sesiones del framework; no almacenar contraseñas legibles.
- Asignar permisos a través de perfiles. Los perfiles iniciales solicitados son compras, almacén, logística, administración y auditoría.
- Cada usuario puede tener acceso a una o varias bodegas mediante asignaciones explícitas. Los usuarios sin asignaciones no pueden operar en ninguna bodega; el perfil con permiso `companies.manage` tiene acceso global a las bodegas activas de la empresa.
- Las requisiciones nuevas deben asociarse a una bodega activa y autorizada; solicitante y jefe aprobador deben tener acceso a esa bodega. El acceso a listas, detalles y acciones se filtra y valida en backend. Las requisiciones históricas sin bodega quedan disponibles solo al acceso global, hasta que se defina un proceso de regularización.
- Un cambio de asignaciones de bodega debe auditarse y revocar las sesiones existentes del usuario.
- Validar permisos en backend para páginas, acciones y endpoints, y comprobar el alcance organizacional en cada consulta y modificación.
- Entradas y salidas validan permiso funcional y acceso a la bodega afectada; un traslado futuro exigirá acceso a dos bodegas distintas, tanto origen como destino.
- Mantener auditoría de cambios de perfiles y permisos, administración de usuarios y operaciones sensibles.
- No implementar aislamiento entre empresas como parte del MVP.

## 6. Cargue masivo de productos

Flujo propuesto:

1. Descargar plantilla y validar formato y tamaño admitidos.
2. Leer y validar el archivo sin escribir cambios parciales.
3. Mostrar errores por fila y permitir corregir el archivo antes de confirmar.
4. Confirmar el lote de importación de manera controlada y presentar resumen de creados, rechazados y duplicados.
5. Registrar usuario, fecha, archivo de referencia y resultado, evitando guardar contenido innecesario o sensible.

Columnas, claves de unicidad, formato de fechas, política de actualización de existentes, tamaño máximo y carga de proveedores/existencias están pendientes de definición.

## 7. Reportes y aceptación técnica

Reportes iniciales acordados: inventario en línea, inventario a una fecha específica, costo por producto, kardex de movimientos y cumplimiento de entregas por proveedor. El kardex básico está implementado por bodega/producto, con filtros de fecha, saldo corrido, documento, lote, actor y costo unitario en moneda base. Existencias a una fecha, valoración consolidada, costos agregados por producto, cumplimiento de proveedor, exportación y zona horaria siguen pendientes de definición e implementación.

Pruebas de diseño necesarias:

- No registrar recepción sin orden ni salida sin requisición.
- Bloquear recepción sin fecha cuando el producto tenga vencimiento configurado.
- Seleccionar lotes por FEFO/FIFO y productos sin vencimiento por FIFO.
- Calcular el costo de una salida a partir del lote físico seleccionado y conservar el costo original de ese lote.
- Convertir costos con la tasa registrada en la orden, exigir tasa 1 para moneda coincidente y conservar moneda cotizada y moneda base en lotes y movimientos.
- Impedir cambiar la moneda base de empresa y desactivar monedas que ya estén referenciadas por la empresa o por órdenes.
- Rechazar acceso fuera de sedes/bodegas autorizadas, tanto en interfaz como en endpoints.
- Verificar el saldo corrido del kardex por bodega/producto, incluyendo entradas positivas, salidas negativas, paginación y aislamiento de bodegas.
- Validar importación válida, filas erróneas, duplicados y fallas sin dejar cargas parciales.
- Verificar que reversos y ajustes conserven el rastro del movimiento original.

## 8. Decisiones que se refinan durante el desarrollo

1. **Antes de ampliar compras:** definir reversos de órdenes, devoluciones y reglas para cambios de moneda base; la comparación de cotizaciones, evaluación de proveedores y órdenes por etapas están aprobadas e implementadas.
2. **Antes de ampliar inventario:** definir devoluciones, sobrantes/faltantes, daños, reversos, ajustes y saldos iniciales.
3. **Antes de traslados y documentos:** definir permisos, aprobaciones, estados de tránsito, numeración y evidencias; el traslado deberá validar acceso a origen y destino.
4. **Antes del importador de productos:** definir columnas, unicidad y si se cargarán proveedores y saldos iniciales.
5. **Antes de implementar reportes:** definir filtros, fórmulas, exportaciones y tratamiento contable de la valoración histórica.
6. **Antes de dimensionar producción:** establecer volúmenes y objetivos de rendimiento y disponibilidad.

Las decisiones confirmadas se materializan con migraciones y pruebas automatizadas. Las funcionalidades todavía pendientes no deben exponerse hasta acordar y validar sus reglas operativas.
