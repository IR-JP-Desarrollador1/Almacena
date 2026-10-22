# Plan de trabajo para el desarrollo de la aplicación de inventarios

## 1. Objetivo

Desarrollar e implementar una aplicación web para administrar empresas, usuarios y permisos, compras, inventarios y logística, de acuerdo con el documento de requerimientos. La implementación utilizará Laravel, Vite y MySQL local.

Este plan organiza el alcance en entregas incrementales. Las duraciones son una estimación inicial para planificar y deberán ajustarse después de validar los procesos operativos, el equipo disponible y las integraciones requeridas.

## 2. Alcance funcional organizado

1. **Fundamentos de plataforma:** configuración del proyecto, arquitectura, base de datos, ambientes y estrategia de despliegue.
2. **Empresas y estructura organizacional:** registro de empresas; catálogos de países, divisiones administrativas y ciudades; sedes, bodegas, ubicaciones y centros de costo.
3. **Acceso y administración:** autenticación, recuperación de acceso, usuarios, perfiles, permisos, alcance organizacional y auditoría.
4. **Datos maestros:** productos, unidades de medida y proveedores.
5. **Compras:** requisiciones, aprobaciones, cotizaciones, comparativos y órdenes de compra.
6. **Inventario:** entradas, salidas, devoluciones, costos, kardex, valoración, máximos, mínimos y reposición.
7. **Logística:** traslados, mercancía en tránsito, entregas, seguimiento y novedades.
8. **Control y consulta:** inventarios físicos, ajustes, reportes, indicadores, alertas e integraciones.

## 3. Plan por fases e hitos

| Fase | Duración estimada | Trabajo principal | Entregable / hito |
|---|---:|---|---|
| 0. Descubrimiento y decisiones | 1 semana | Confirmar actores, procesos actuales, reglas de aprobación, alcance inicial, datos a migrar, restricciones y necesidades de integración. El alcance acordado es una empresa con varias sedes; validar los procesos y las reglas operativas antes del diseño. | Alcance priorizado, glosario, decisiones registradas y criterios de aceptación refinados. |
| 1. Diseño funcional y técnico | 1 semana | Modelar procesos y estados; definir arquitectura Laravel/Vite/MySQL, estructura de módulos, modelo conceptual para una empresa con varias sedes, estrategia de autorización, auditoría, ambientes y diseño de interfaces principales. | Diseño validado, modelo inicial de datos y backlog listo para construcción. |
| 2. Plataforma, empresas y acceso | 2 semanas | Crear la base de la aplicación; implementar empresas y ubicación geográfica; autenticación, sesiones, recuperación, usuarios, asociación empresa-usuario, perfiles, permisos y alcance por empresa/sede/bodega. Incorporar auditoría de acciones administrativas. | Primer incremento ejecutable con empresas, cuentas y permisos; pruebas de aislamiento y acceso. |
| 3. Organización y datos maestros | 2 semanas | Implementar sedes, bodegas, ubicaciones y centros de costo; productos, unidades de medida y proveedores. Importar productos desde CSV/XLSX con validación por fila, omisión de duplicados, informe descargable y permisos. | Catálogos administrables y disponibles para compras e inventario; importación inicial de productos comprobable. |
| 4. Compras | 3 semanas | Implementar requisiciones y su flujo de aprobación; comparación de cotizaciones por producto; selección manual y justificada; evaluación de proveedores; órdenes por etapas, aprobación y conversión monetaria. | Flujo trazable desde solicitud hasta órdenes aprobadas, con controles por perfil. |
| 5. Inventario y costos | 3 semanas | Implementar recepciones y salidas parciales, lotes, movimientos, costos en moneda cotizada/base y selección FEFO/FIFO. Completar devoluciones, ajustes y valoración. | Movimientos trazables, costos convertidos y pruebas de transacciones; completar controles y consultas antes de cierre. |
| 6. Logística y control físico | 2 semanas | Implementar traslados, despacho, mercancía en tránsito, entregas en ubicaciones internas o externas, evidencias/novedades según se definan, inventario físico y ajustes autorizados. | Flujo de traslado y entrega trazable; conteos y ajustes con autorización. |
| 7. Reportes y alertas | 2 semanas | Construir reportes operativos, indicadores y alertas priorizados; definir exportación e impresión; concretar integraciones identificadas durante el descubrimiento. | Reportes validados por usuarios y alertas priorizadas disponibles. |
| 8. Pruebas, aceptación y salida | 2 semanas | Ejecutar pruebas funcionales, de permisos, aislamiento de datos, regresión y aceptación de usuario; corregir defectos; preparar datos, capacitación, respaldos, monitoreo y despliegue. | Aceptación del producto mínimo acordado y despliegue controlado a producción. |

**Estimación inicial:** 18 semanas de calendario, con equipo y decisiones disponibles según lo supuesto en este documento. La integración de sistemas externos, migración compleja de datos o nuevos flujos de aprobación pueden ampliar el plazo. Se recomienda trabajar en iteraciones de dos semanas y revisar la estimación al cerrar las fases 0 y 1.

**Revisión de avance verificada al 22 de octubre de 2026**

La verificación se realizó sobre el código, las rutas, las migraciones, las pruebas y la documentación existente en `Almacena/app/` y `Almacena/docs/`. No se asigna un porcentaje global: las fases contienen alcances de tamaños distintos y varias reglas funcionales siguen sin definición.

| Fase | Estado | Evidencia y brecha pendiente |
|---|---|---|
| 0. Descubrimiento y decisiones | **Cerrada con decisiones diferidas** | Existe acta de inicio y se confirmaron empresa única, separación de aplicaciones, alcance por bodega, aprobación de requisiciones y órdenes, recepciones/salidas parciales, FEFO/FIFO y costo por lote. Las reglas para comparación y evaluación de proveedores ya están aprobadas; reversos, ajustes y logística siguen pendientes. |
| 1. Diseño funcional y técnico | **Base documentada; refinamiento pendiente** | El diseño registra el flujo vigente de órdenes, movimientos, monedas y conversión a moneda base. Falta especificar reversos, ajustes, traslados, reportes y las reglas de procesos aún fuera de alcance. |
| 2. Plataforma, empresas y acceso | **Parcialmente implementada** | Laravel 12, Vite, MySQL, autenticación, recuperación de acceso, invitaciones, usuarios, perfiles/permisos, administración de empresa única y auditoría están implementados. Los usuarios pueden tener varias bodegas; Administración (`companies.manage`) tiene acceso global a bodegas activas y los demás usuarios solo a las asignadas. Los cambios de alcance revocan sesiones. La empresa configura moneda base y el catálogo de monedas está disponible; siguen pendientes los catálogos geográficos normalizados y la validación de país/división/ciudad. |
| 3. Organización y datos maestros | **Implementada para el alcance actual** | Hay administración de sedes, bodegas, ubicaciones, centros de costo, áreas solicitantes, productos, unidades y proveedores. La importación de productos admite CSV/XLSX, limita tamaño y filas, valida unidad y estado, omite duplicados, conserva los errores por fila en el informe descargable y exige `catalogs.manage`; las pruebas cubren permisos, formatos y resultados parciales. |
| 4. Compras | **Parcialmente implementada** | Las requisiciones admiten borrador, envío, aprobación, rechazo con motivo, corrección y reenvío; cada una requiere una bodega autorizada. La comparación por producto registra ofertas con costo neto, impuesto, moneda/tasa, marca y plazo; exige dos proveedores por producto, muestra la calificación vigente, permite adjudicación parcial en etapas y requiere justificar selecciones que no tengan el menor total con impuestos en moneda base. Las etapas generan órdenes borrador independientes por proveedor, aprobables antes de recibir. El flujo directo de creación de órdenes redirige al comparador. La evaluación manual de proveedores conserva notas y promedio calculado. Falta validar el recorrido integral visual/autenticado y completar aceptación funcional fuera de las pruebas automatizadas. |
| 5. Inventario y costos | **Parcialmente implementada** | Hay recepciones parciales desde órdenes aprobadas y salidas parciales desde requisiciones aprobadas; los saldos no pueden excederse, se crean lotes y movimientos, se aplica FEFO/FIFO y se bloquean lotes vencidos. Cada lote y asignación conserva costos cotizado/base; la conversión usa la tasa de la orden. El kardex consulta el historial por bodega/producto, con fechas, documentos, lotes, saldo corrido, actor y costo unitario base. Faltan devoluciones, reversos, ajustes, existencias a una fecha, valoración agregada y reportes de costos. |
| 6. Logística y control físico | **Pendiente** | Traslados, mercancía en tránsito, entregas, inventarios físicos y ajustes autorizados no están implementados. Un futuro traslado deberá exigir acceso tanto a la bodega de origen como a la de destino. |
| 7. Reportes y alertas | **Parcialmente implementada** | Está disponible el kardex por producto y bodega, con filtros de fecha, saldo corrido paginado y costo unitario base. Siguen pendientes existencias a una fecha, valoración y costos agregados, cumplimiento de entregas por proveedor, los demás indicadores y alertas; faltan priorización y criterios de aceptación. |
| 8. Pruebas, aceptación y salida | **Parcialmente implementada** | La suite completa pasa; aún falta aceptación funcional integral, pruebas de respaldo/restauración, monitoreo, capacitación y preparación del despliegue productivo. |

El catálogo incluye COP, USD, EUR, GBP, CAD, MXN, BRL, CLP, ARS, PEN, CNY (yuan renminbi chino) y RUB (rublo ruso). La moneda base asignada a la empresa no puede cambiarse una vez que existe una cotización u orden; una moneda referenciada no se puede desactivar. La tasa se define como unidades de moneda base por una unidad cotizada y debe ser exactamente 1 cuando las monedas coinciden.

**Verificaciones realizadas:** `php artisan test` terminó con **103 pruebas y 1465 aserciones aprobadas**; Pint pasó en los archivos PHP modificados; `php artisan view:cache` compiló las vistas; las rutas de cotizaciones y evaluaciones se registraron correctamente, y `git diff --check` no encontró errores. La migración de cotizaciones/evaluaciones se aplicó a `inventarios` el 22 de octubre de 2026; se verificó el estado `Ran`, la estructura de las tablas y la vista autenticada de evaluación respondió HTTP 200. La migración usa nombres de índices compatibles con MySQL y reemplaza de forma explícita el índice único/referencial de requisición al permitir órdenes múltiples.

Los datos demo guardados en la base local sirven para pruebas y demostraciones; no son datos de producción ni completan los flujos transaccionales pendientes. La base y credenciales de ejecución deben verificarse en el entorno local activo antes de realizar tareas de mantenimiento o despliegue.

**Decisión de compras confirmada:** se configura cada área solicitante y se asigna un jefe; el sistema envía las requisiciones al jefe del área del solicitante y evita la autoaprobación. La asignación del área al usuario se mantiene en su cuenta.

## 4. Dependencias y secuencia recomendada

- El alcance inicial acordado es **una empresa con varias sedes**. Si se solicita multiempresa, deberá tratarse como una ampliación de alcance y reevaluarse antes de diseñar o modificar el esquema de datos, las consultas, los permisos, los reportes y las pruebas de seguridad.
- Empresas, usuarios, perfiles/permisos y auditoría son cimientos transversales: deben estar implementados antes de exponer operaciones de compras, inventario o logística.
- Sedes, bodegas, ubicaciones, productos, unidades y proveedores deben estar disponibles antes de construir los flujos transaccionales que los utilizan.
- El flujo de cotizaciones y adjudicación precede a las órdenes; las recepciones requieren una orden aprobada y las salidas una requisición aprobada.
- Las asignaciones de usuario a bodega son la base del alcance operativo. No se otorga acceso implícito por pertenecer a una sede; los movimientos futuros validarán la bodega correspondiente en cada operación y ambos extremos para traslados.
- Existencias, kardex y costos dependen de que cada movimiento tenga reglas inequívocas de confirmación, reversión y trazabilidad.
- Traslados y entregas dependen de inventario y ubicaciones; reportes e indicadores dependen de datos y estados estabilizados.

## 5. Criterios de aceptación transversales

La entrega no se considera terminada por módulo hasta que:

- Las pantallas y operaciones estén protegidas en el backend, además de ocultarse según permisos en la interfaz.
- Se valide tanto el permiso funcional como el ámbito de empresa, sede y bodega del usuario.
- Las requisiciones tengan una bodega autorizada; entradas y salidas validen su bodega, y los traslados comprueben acceso a origen y destino.
- Las operaciones críticas estén registradas con actor, empresa, acción, entidad y fecha; los registros históricos no se borren al desactivar usuarios, perfiles o empresas.
- Las reglas de integridad se apliquen en el servidor y en la base de datos donde corresponda; las operaciones relacionadas se ejecuten de manera atómica.
- Existan pruebas automatizadas para reglas principales, permisos y estados, junto con pruebas de aceptación del proceso.
- La documentación de uso y soporte se actualice para cada entrega.

## 6. Pruebas de aceptación derivadas del documento

Incluir como mínimo los escenarios de aceptación documentados:

- **Empresas:** registro con campos obligatorios, prevención de identificación tributaria duplicada conforme a la regla acordada y rechazo de ciudad incompatible con su división administrativa (CA-EMP-001 a CA-EMP-003).
- **Autenticación:** acceso con credenciales válidas e inválidas, considerando estado de cuenta y política de intentos (CA-AUT-001 y CA-AUT-002).
- **Usuarios:** alta y asociación empresarial; desactivación que impida nuevos accesos y revoque sesiones según la política definida (CA-USR-001 y CA-USR-002).
- **Perfiles y permisos:** crear perfil, asignar permisos, rechazar operaciones no autorizadas y auditar modificaciones. Para el MVP, validar que el usuario no pueda operar fuera de las sedes y bodegas autorizadas; la prueba de aislamiento entre empresas aplica si el alcance pasa a multiempresa (CA-PER-001, CA-PER-002 y CA-PER-004; CA-PER-003 condicionado).
- **Seguridad de rutas:** el acceso directo a rutas protegidas debe validar autenticación, permisos y ámbito organizacional (CA-SEG-001).
- **Compras:** comprobar aprobación de requisiciones y órdenes; validar dos proveedores por producto, impuesto, moneda/tasa, comparación en moneda base, justificación, adjudicación por etapas y el costo neto que llega a inventario.
- **Inventario:** comprobar entradas desde órdenes aprobadas y salidas desde requisiciones aprobadas; controlar cantidades parciales, vencimiento, FEFO/FIFO y costo del lote exacto. Verificar conversión monetaria y conservación de moneda/tasa. Validar más adelante devoluciones, reversos y ajustes.
- **Logística y control:** exigir comprobante por movimientos, entradas, salidas, despachos, bajas por daño y ajustes por sobrantes/faltantes; validar contenido y autorizaciones antes de cerrar los casos.
- **Reportes:** validar inventario en línea, inventario a una fecha específica, costos por producto, kardex y cumplimiento de entregas por proveedor, con filtros y fórmulas acordados.
- **Cargue inicial:** el usuario aprobó el formato del catálogo de productos CSV/XLSX y sus encabezados estables. Las pruebas automatizadas incluyen un catálogo ficticio representativo, filas incorrectas, duplicados, resultado parcial y permisos; queda pendiente probar una carga con datos reales del negocio y revisar el informe descargable.

## 7. Riesgos y decisiones pendientes

| Riesgo o decisión | Acción recomendada |
|---|---|
| El alcance inicial es una empresa con varias sedes; una futura necesidad multiempresa impactaría datos y autorizaciones. | Mantener multiempresa fuera del MVP. Si cambia el alcance, evaluar el impacto arquitectónico antes de iniciar la fase 1. |
| El documento enumera bloques operativos, pero no especifica en detalle sus reglas, estados y aprobaciones. | Realizar talleres de proceso antes de desarrollar cada módulo y obtener aprobación del responsable funcional. |
| No se definen volúmenes de usuarios, productos, movimientos ni expectativas de rendimiento/ disponibilidad. | Establecer métricas y umbrales verificables en la fase 0; usarlos para pruebas de carga y dimensionamiento. |
| No se describen migraciones ni integraciones concretas. | Identificar sistemas, formatos, responsables y calidad de datos; estimar estas actividades como alcance explícito. |
| El manejo de datos personales y credenciales exige controles adecuados. | Usar mecanismos nativos de Laravel para autenticación cuando sean apropiados, hash seguro, tokens temporales de un solo uso y autorización del lado servidor; revisar configuración antes de producción. |
| Un movimiento confirmado no debe editarse como un registro ordinario. | Definir flujos de anulación/reversión, permisos de aprobación y trazabilidad antes de cerrar inventario y compras. |

## 8. Equipo y forma de trabajo asumidos

La estimación de 18 semanas presupone disponibilidad de un responsable funcional que resuelva dudas y acepte entregas, un equipo de desarrollo con capacidad para trabajar en paralelo, apoyo de QA y soporte de infraestructura para los ambientes y el despliegue. La composición exacta y el número de personas no están especificados en el documento; deben confirmarse antes de comprometer fechas.

- Mantener un backlog priorizado por valor y dependencias.
- Revisar el incremento con usuarios al final de cada iteración.
- Usar ambientes separados de desarrollo, pruebas y producción, con migraciones controladas.
- Definir y monitorear defectos, riesgos, decisiones y criterios de salida de cada fase.
- No habilitar datos de producción ni accesos reales antes de aprobar controles de seguridad, respaldos y pruebas de aceptación.

## 9. Condiciones de salida a producción

La aplicación estará lista para el primer despliegue cuando el alcance priorizado esté aceptado, los escenarios críticos de autorización y aislamiento pasen, no existan defectos bloqueantes, se haya probado el respaldo y la restauración, estén configurados los mecanismos de monitoreo y soporte, y los usuarios responsables hayan recibido capacitación. Los módulos no priorizados para la primera salida deben quedar identificados como entregas posteriores, no como funcionalidades implícitamente completas.

## 10. Referencia

Plan elaborado a partir de *Requerimientos Aplicación Inventarios.pdf*, en particular la ampliación de empresas, autenticación, usuarios, perfiles y permisos (secciones 13–19), los criterios de aceptación (sección 19) y la actualización del alcance general (sección 20).
