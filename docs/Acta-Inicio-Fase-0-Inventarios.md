# Acta de inicio — Fase 0: Descubrimiento y decisiones

**Proyecto:** Aplicación de inventarios  
**Fecha de inicio:** 20 de octubre de 2026  
**Duración estimada:** 1 semana  
**Estado:** Cerrada para iniciar construcción; los detalles operativos se refinan durante el desarrollo de cada módulo. Acceso, administración inicial y catálogos organizacionales base implementados en `app/`.

## Avance de implementación

- Esqueleto Laravel 12 y compilación Vite preparados.
- Inicio/cierre de sesión, recuperación y cambio de contraseña habilitados; no hay registro público.
- Los estados de cuenta controlan el inicio de sesión; las cuentas desactivadas se desconectan al solicitar una ruta protegida.
- Se registran accesos correctos, fallidos y bloqueos por intentos; se actualiza el último acceso exitoso.
- El primer usuario se aprovisiona por el comando interactivo `php artisan app:create-first-user`.
- Implementada la administración inicial de usuarios, perfiles/permisos y perfil de la empresa; los permisos operativos por área se completarán junto con sus módulos.
- Implementados los catálogos base de sedes, bodegas, ubicaciones y centros de costo, con permisos, validación de jerarquía y auditoría.

## 1. Decisiones confirmadas

| Tema | Decisión | Implicación |
|---|---|---|
| Modelo de organización | Una empresa con varias sedes | El MVP no contempla aislamiento entre varias empresas. Empresas, sedes, bodegas y permisos se modelarán para este escenario. Si se requiere multiempresa en el futuro, se debe evaluar el impacto antes de ampliar el alcance. |
| Alcance funcional inicial | Acceso; catálogos y estructura organizacional; compras; inventario; logística; reportes y alertas | Los seis bloques forman parte del MVP. Los flujos detallados y su prioridad interna se validarán en esta fase. |
| Datos e integraciones | No se requieren integraciones externas. Se requiere opción de cargue masivo de productos mediante Excel o CSV. | Definir estructura de plantilla, validación de errores y si la carga inicial incluye proveedores o existencias. |
| Base tecnológica de referencia | Laravel, Vite y MySQL, según los requerimientos | Validar versiones, infraestructura y restricciones del entorno durante el diseño técnico. |

## 2. Trabajo de descubrimiento

| Actividad | Resultado esperado | Responsable |
|---|---|---|
| Identificar usuarios, perfiles y responsables de aprobación | Actores, responsabilidades y matriz preliminar de permisos | Responsable funcional por asignar |
| Recorrer el ciclo de compras | Flujo preliminar acordado desde requisición hasta cotización, evaluación de proveedores y orden; validar estados y excepciones | Responsable funcional por asignar |
| Recorrer operaciones de inventario | Reglas preliminares de entradas con orden de compra, FIFO, lotes/vencimientos y despacho; validar costos, devoluciones, conteos y reversos | Responsable funcional por asignar |
| Validar operación logística | Registrar comprobante por movimiento; validar etapas de traslados, despacho, tránsito y entrega | Responsable funcional por asignar |
| Levantar estructura y datos maestros | Sedes, bodegas, ubicaciones, centros de costo, productos, unidades y proveedores | Responsable funcional por asignar |
| Identificar datos existentes e integraciones | No se requieren sistemas externos para el MVP; confirmar saldos y catálogos iniciales por cargar | Responsable funcional y técnico por asignar |
| Definir necesidades de consulta y reportes | Reportes prioritarios identificados; acordar filtros, granularidad y formato | Responsable funcional por asignar |
| Refinar criterios de aceptación y riesgos | Casos verificables para el MVP y condiciones de salida de cada módulo | Responsable funcional, desarrollo y QA |

## 3. Acuerdos funcionales preliminares

Estos acuerdos reflejan la información recibida para iniciar el análisis; no sustituyen la validación de estados, excepciones y casos de aceptación con los responsables de cada área.

| Área | Acuerdo levantado | Validación necesaria |
|---|---|---|
| Perfiles y permisos | Los permisos se asignarán en la sección de perfiles y permisos del módulo de autenticación y seguridad. | Refinar permisos por acción, alcance y administración al implementar acceso y cada módulo. |
| Perfiles iniciales | Se requieren perfiles de compras, almacén, logística, administración y auditoría. | Definir permisos concretos, alcance por sede/bodega y restricciones de aprobación durante el desarrollo del módulo de acceso. |
| Requisiciones | El jefe del área solicitante aprueba las requisiciones. | Precisar si existen umbrales, delegaciones, rechazo, devolución para corrección y aprobaciones adicionales. |
| Jefatura de área | Cada área solicitante tendrá un jefe asignado; el usuario solicitante queda asociado a un área y la requisición se dirige a su jefe. El sistema impide que el solicitante apruebe su propia requisición. | Confirmar responsables por área y permisos de las cuentas antes de operar el flujo. |
| Cotizaciones y órdenes | Las cotizaciones son insumos para evaluar precios de proveedores; la evaluación de proveedores participa en la decisión de la orden de compra. | Definir criterios y ponderaciones de evaluación, número de cotizaciones, excepciones y quién autoriza la orden. |
| Recepción y salida | No se permitirán entradas sin orden de compra ni salidas sin requisición. No habrá aprobación adicional del movimiento cuando este se origine en una orden de compra o requisición autorizada. | Definir tratamiento de recepciones parciales, sobrantes, faltantes, daños, devoluciones y autorizaciones para ajustes y movimientos no ordinarios. |
| Costo de salida | Se aplicará el costo específico del lote físico despachado. Esta regla prevalece sobre FIFO como método contable de valoración cuando el lote elegido por FEFO no sea el primero recibido. FIFO se mantiene como regla de selección física para productos sin vencimiento y como desempate. | Validar con contabilidad durante el desarrollo del módulo de inventario, antes de implementar valoración, devoluciones, ajustes y reportes de costos. |
| Lotes y despacho | Cada producto tendrá una configuración que indica si maneja vencimiento. Si la maneja, la entrada requiere fecha de vencimiento y el despacho prioriza vencimiento más próximo (FEFO); FIFO desempata. Si no maneja vencimiento, se despacha por FIFO. | Confirmar reglas de lotes, cantidades parciales, vencimientos iguales y productos vencidos/bloqueados. |
| Comprobantes | Se requiere comprobante por movimiento. Se mencionaron entradas, salidas, despachos, bajas por daño, ajustes por sobrantes/faltantes, requisiciones, cotizaciones y órdenes de compra. | Confirmar si el comprobante es documento imprimible, registro de aprobación, evidencia adjunta o combinación; definir numeración y contenido mínimo. |
| Reportes | Se priorizan inventario en línea, inventario a una fecha específica, costos por producto, kardex de movimientos y cumplimiento de entregas por proveedor. El inventario histórico y el kardex requieren conservar la trazabilidad de movimientos. | Definir filtros, nivel de detalle, valoración de costo, reglas de cumplimiento y exportación. |
| Cargue inicial | Se requiere cargue masivo de productos desde Excel o CSV. | Definir plantilla, columnas obligatorias, tratamiento de duplicados y si habrá carga inicial de proveedores y existencias. |

## 4. Decisiones diferidas al desarrollo

Las siguientes reglas no bloquean el inicio de la construcción. Se detallarán con el responsable funcional al trabajar el módulo correspondiente y antes de implementar el comportamiento afectado:

1. **Compras e inventario:** recepciones parciales, sobrantes, faltantes, daños, devoluciones, bajas y ajustes; documentar autorizaciones. La entrada/salida ordinaria requiere orden de compra/requisición autorizada, sin aprobación adicional.
2. **Seguridad:** permisos concretos, alcances por sede/bodega y aprobaciones para los perfiles iniciales.
3. **Inventario y contabilidad:** validar el costo específico por lote y su prevalencia sobre FIFO contable; acordar costos para devoluciones, ajustes y traslados antes de implementar esas reglas o reportes.
4. **Lotes:** tratamiento de productos vencidos y condiciones de bloqueo o autorización.
5. **Documentos:** estructura, numeración y evidencias de comprobantes, movimientos y documentos de compra.
6. **Cargue masivo:** columnas, claves de unicidad, formato y si incluye proveedores o existencias iniciales.
7. **Operación:** volúmenes esperados y objetivos de rendimiento y disponibilidad antes de dimensionar el despliegue.
8. **Reportes:** filtros, granularidad, exportaciones y definición de cumplimiento de entregas por proveedor, antes de implementar cada consulta.

## 5. Criterio de cierre de la fase

La fase 0 se cierra cuando:

- El responsable funcional y los participantes clave están identificados.
- Está confirmado el alcance de una empresa con varias sedes, los módulos del MVP y que no se necesitan integraciones externas.
- Se han registrado procesos iniciales, responsables funcionales por asignar y decisiones diferidas con el módulo en que deben resolverse.
- Se dispone de un modelo conceptual y arquitectura inicial; las reglas específicas se convertirán en criterios de aceptación antes de implementar cada módulo.

## 6. Siguiente paso

Continuar con la fase de plataforma y acceso. Refinar permisos durante su implementación y acordar las reglas contables y operativas de inventario con los responsables del área antes de desarrollar esos comportamientos.
