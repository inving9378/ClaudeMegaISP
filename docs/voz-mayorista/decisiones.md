# Voz Mayorista — decisiones técnicas

Cada decisión con su porqué. Cuando había dos alternativas válidas se eligió una, se
documenta la razón, y se deja anotado qué haría falta para tomar la otra.

Sprint 1 · 2026-09-09.

---

## D1 · Es un addon, no un módulo Core

**Decisión:** vive en `app/Modules/Addons/VozMayorista`, con `"type": "addon"`.

El prompt original pedía módulo Core. No se pudo: `ModuleLifecycleService::install()`
rechaza los Core en su primera línea —"es core y se gestiona automáticamente"— y el
blindaje que exige el addendum se apoya justo en ese ciclo de vida: *"al activarse, si
no es operador, se niega y no se registra en `module_registry`"*. Un módulo Core no se
activa nunca: siempre está. No habría activación que negar.

De fondo: un módulo cuya definición es *"solo debe poder activarse en la instalación de
Meganet"* es, por construcción, activable. Eso es un addon.

**Para volver atrás:** habría que permitir Core en `install()`, que hoy gestiona 48
módulos. No se justifica por un caso.

---

## D2 · El rol de instalación es genérico, y su default es `cliente`

**Decisión:** `INSTANCE_ROLE` en el `.env`, leído por `config/instancia.php` y expuesto
por `App\Support\RolInstancia`. Cualquier módulo declara `"instance_role"` en su
manifiesto; el ciclo de vida lo respeta. **Default `cliente`.**

**Por qué el default es el restrictivo.** Una instalación a la que se le olvidó definir
`INSTANCE_ROLE` debe quedarse **sin** los módulos de operador, no con ellos. Si el
default fuera `operador`, un `.env` incompleto en la VM de un cliente le abriría el
panel comercial de Meganet —tarifas de carrier, márgenes, inventario de DID— y ese
error **no avisa**: simplemente funciona, hasta que alguien ve lo que no debía. El modo
de fallo correcto es de menos, nunca de más. Por el mismo criterio, un valor no
reconocido (`admin`, `1`, `true`, cadena vacía) cae a `cliente` en vez de intentar
interpretarlo.

**Genérico a propósito:** vienen más módulos que solo tienen sentido en Meganet
—facturación mayorista, panel de aprovisionamiento de VMs— y todos necesitan lo mismo.
Un módulo que no declara `instance_role` no exige nada y corre en cualquier
instalación, que es el caso de los 48 ya registrados: el mecanismo no cambia el
comportamiento de ninguno.

**Un solo punto de lectura.** Nadie llama a `config('instancia.rol')` ni a `env()` por
su cuenta. Si el criterio de "qué cuenta como operador" se decidiera en varios lugares,
uno acabaría siendo más permisivo que el resto — y ese sería el que abre la puerta.

---

## D3 · El blindaje son tres barreras, no una

| # | Dónde | Qué impide |
|---|---|---|
| 1 | `ModuleLifecycleService::install()` | Activar el módulo. No deja fila en `module_registry`. No admite `$force` |
| 2 | Middleware `rol.instancia:operador` | Entrar a cualquier ruta del módulo (403) |
| 3 | `ModuleServiceProvider::boot()` | Cargar rutas, vistas y migraciones; y con ello, que aparezca en el sidebar |

**Por qué tres.** Cada una cubre el hueco de la anterior, y el hueco es real:
`BaseModuleServiceProvider::moduleIsActive()` hace **fail-open** cuando el módulo no
está en `module_registry` —para no impedir el arranque del sistema si la tabla aún no
migró— así que un módulo sin registrar **sí** carga sus rutas. Si alguien además marca
`active = 1` a mano en la base, la barrera 1 queda saltada. Las otras dos siguen
cerradas. Un blindaje de una sola capa es un blindaje que depende de que nadie toque la
base directamente, y eso no se sostiene.

El 403 de la barrera 2 no revela qué módulo hay del otro lado.

---

## D4 · Prefijo de rutas `/voz-mayorista`, no `/voz`

`/voz` **ya está ocupado**: el módulo `Planes` (activo) lo usa para los planes de
servicio de voz que se venden al suscriptor final — `voz/crear`, `voz/table`,
`voz/editar/{id}`, modelo `Voise`, y 10 permisos `plan_*_voz` / `client_service_voz`.

Son dominios distintos: aquel **vende un plan a un cliente**, este **administra el
negocio mayorista**. Compartir raíz solo serviría para confundir un `route:list`.

Los permisos `voz.` y las tablas `voz_` **sí** se conservan: ahí no hay colisión.

El módulo `Planes` no se tocó. La colisión se reporta, no se arregla desde aquí.

---

## D5 · Dinero: `decimal(12,6)` para tarifas, `bigint` en centavos para importes

- **Tarifas por minuto:** `decimal(12,6)`. $0.17 → `0.170000`. Seis decimales porque las
  tarifas mayoristas se negocian a ese nivel, y redondear antes de multiplicar por miles
  de minutos produce diferencias que se ven en la factura.
- **Importes y saldos:** `bigint` en centavos. **Nunca** float ni double, en ningún punto.
- **Redondeo una sola vez**, al emitir el documento de cobro. Los cálculos intermedios
  conservan precisión completa.
- **Fracción facturable parametrizable por carrier**: `facturacion_minima_segundos` e
  `incremento_segundos`, ambos por defecto `60`. ⚠️ **Pendiente de confirmar con Irving
  cómo factura Servnet en realidad.** Se dejó parametrizable en vez de asumir, porque
  errar aquí escala con cada llamada.

---

## D6 · El único de tarifas usa `prefijo NOT NULL` con `''` como "sin prefijo"

**El problema:** el único pedido era `(voz_carrier_id, destino_codigo, prefijo,
vigente_desde)` con `prefijo` nullable. En MySQL **dos NULL no colisionan** en un índice
único, así que las tarifas generales —las de `mx_fijo` y `mx_movil`, que no llevan
prefijo— podrían duplicarse sin que la base lo impidiera. Una tarifa duplicada con
vigencias solapadas es exactamente lo que rompe una retarificación y lo que impide
defender una factura.

**Decisión:** `prefijo` es `NOT NULL` con default `''`, donde `''` significa "tarifa
general del destino". El único vuelve a morder en todos los casos, sin columnas extra.

**Consecuencia a recordar:** al consultar, vacío no es NULL. El resolutor de prefijos
ordena por longitud descendente, así que `''` queda naturalmente al final, como
fallback — que es el comportamiento correcto.

**Alternativa descartada:** columna generada `prefijo_norm = COALESCE(prefijo,'')` con
el único encima. Semánticamente más limpia (NULL seguiría significando "no aplica"), a
cambio de una columna calculada y un índice más. Si algún día hace falta distinguir
"sin prefijo" de "prefijo desconocido", ese es el camino.

---

## D7 · `voz_cdr` sin claves foráneas, para poder particionar

MySQL **no admite FKs en tablas particionadas**, y el sprint pedía las dos cosas.

**Decisión:** `voz_cdr` va **sin FK**; la integridad se sostiene en los servicios y en
las pruebas. Es el patrón habitual en tablas de CDR y de log de alto volumen, y deja la
puerta abierta al particionado por periodo, que es lo que esta tabla va a necesitar de
verdad: crece a millones de filas y quitarle las FKs *después* para poder particionarla
es una migración pesada y con ventana de mantenimiento.

Índices: `(voz_tenant_id, inicio)`, `(uniqueid)`, `(procesado, inicio)`.

**Alternativa descartada:** conservar FKs y archivar por periodo a una tabla histórica
con un comando programado, como ya hace `activitylog:archive` en este repo. Es coherente
con un patrón existente y mantiene integridad referencial en la base; se descartó porque
rinde peor en consultas sobre rangos grandes, que es justo lo que se le va a pedir.

---

## D8 · Multiempresa por scope lógico, no por base separada

`voz_tenant_id` en **toda** tabla con datos de cliente desde el día uno, con scope
global de Eloquent y la posibilidad explícita y auditada de que un operador de Meganet
vea todos.

**No se usa `stancl/tenancy`.** El aislamiento aquí es lógico dentro de la instalación
de Meganet, no por base separada: los tenants de voz son clientes del negocio mayorista
de un mismo operador, no instalaciones independientes. Meter una librería de
multi-tenancy por base traería aislamiento físico que nadie pidió y complicaría cada
consulta agregada del panel —capacidad, consumo contra el mínimo, margen— que por
definición cruza todos los tenants.

---

## D9 · Meganet es también un tenant: el interno

Meganet consume su propia telefonía, y ese consumo cuenta contra el mínimo de $30,000
igual que el de los clientes. Sin modelarlo, el indicador principal del panel queda
incompleto y no se puede distinguir el tráfico que **genera ingreso** del que solo
**genera costo**.

- `voz_tenants.es_interno` boolean, default `false`.
- Lo crea un **seeder idempotente**, resuelto **por código**, nunca por id.
- **Solo puede existir uno.** Se valida.
- **Exento** de la obligación de límites diario/mensual y de suscripción a un plan
  comercial: no se factura a sí mismo. Todo lo demás le aplica igual — DID, canales,
  rangos, extensiones, tarificación al costo.
- **Tarificación:** calcula `costo` real, y deja `venta` y `margen` en **cero**. Es
  consumo propio, no venta.
- **Excluido** de los reportes comerciales de margen. **Incluido** en el consumo total
  contra el mínimo del carrier.

El panel desglosa el indicador en **tres cifras**, no una:

```
Consumo del mes contra el mínimo de $30,000
  · consumo interno de Meganet ......... $X   (costo puro)
  · consumo revendido a clientes ....... $Y   (genera ingreso)
  · mínimo sin aprovechar .............. $Z   (dinero perdido)
```

La tercera es la cifra más importante del módulo: es lo que se paga cada mes sin usar.
Va grande y en el primer bloque del panel.

---

## D10 · Numeración en E.164 y tiempo en UTC

- Todo número en **E.164** (`+521234567890`), una sola columna, sin espacios ni guiones.
  El formateo para presentación es de la capa de vista. Helper único de normalización,
  con pruebas para fijos y móviles mexicanos, con y sin `+52`, con y sin el `1` histórico.
- Todo timestamp en **UTC** en base; presentación en `America/Mexico_City`. Los periodos
  de consumo se cierran en horario de Ciudad de México y se guarda **explícitamente** la
  zona con la que se cerró.
- **No se repite el patrón legacy** de fechas en `VARCHAR` `DD/MM/YYYY` que existe en
  documentos. Aquí todo es `datetime` o `date` nativo.

---

## D11 · Secretos

Ninguna contraseña de troncal, PBX o carrier se guarda en claro: cifrado con el
mecanismo de Laravel. **Nunca** se devuelven en respuestas de API ni se escriben en log.
Cuando un secreto se genera automáticamente, la auditoría registra **quién, cuándo y qué
componente** lo generó — el valor no, el origen sí.

En la bitácora, los valores sensibles se registran como `"***"` con nota del campo
afectado.

---

## Pendientes anotados

| Qué | Por qué está abierto |
|---|---|
| Cómo factura Servnet la fracción (D5) | Se dejó parametrizable 60/60; falta confirmar el dato real con Irving |
| `INSTANCE_ROLE=operador` en el `.env` de producción | El `.env` no se commitea: es paso manual del despliegue |
| Colisión conceptual `voip_*` ↔ `voz_*` (D4) | Dos módulos modelan troncales y extensiones con propósitos distintos. Reportado, no tocado: el módulo VoIP tiene su propio plan de saneamiento |
| `moduleIsActive()` hace fail-open (D3) | Comportamiento del sistema, no del módulo. Anotado porque de él depende que la barrera 1 no sea suficiente sola |
