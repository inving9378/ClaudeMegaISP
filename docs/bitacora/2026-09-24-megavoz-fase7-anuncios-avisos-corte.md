## 2026-09-24 11:20 — MegaVoz Fase 7: campañas de aviso/anuncio/corte por zona

Instrucción explícita de Irving tras dar los datos reales del troncal Grandstream:
"cuando termines sigue con la fase 7". Plan original: "Anuncios/avisos/corte por zona
(reutiliza el motor del Blaster, tipos cobranza/aviso/anuncio/corte, cada uno con su
troncal y límite de canales propio; botón de corte por distrito/zona/caja de la
nomenclatura DxZyCz)".

### Diseño: generalizar, no duplicar

`CobranzaBlaster` (el blaster de cobranza) ya es un sistema completo y probado
(campañas, jobs, TTS, AMI). En vez de construir un sistema paralelo para
aviso/anuncio/corte, se generalizó el existente — exactamente lo que pedía el plan
("reutiliza el motor del Blaster").

- `cobranza_campanas` += `tipo` (cobranza/aviso/anuncio/corte, default cobranza —
  cero cambio de comportamiento para lo que ya existe), `troncal_id` (nullable,
  cae al troncal global de siempre si no se elige), `max_canales_simultaneos`
  (nullable, sin límite = comportamiento actual).
- `AmiConnectionService::originate()` acepta un endpoint de troncal opcional — cada
  campaña puede salir por SU PROPIA troncal.
- `voip_troncales.proposito` += `saliente_anuncio` (Fase 1 solo había declarado 3
  propósitos salientes — cobranza/avisos/corte — para 4 tipos de campaña; faltaba
  el de anuncio).
- `BlastCampanaJob`: cobranza sigue con su plantilla de mensaje (nombre+monto+
  fecha, intacta); aviso/anuncio/corte hablan el texto libre que el admin escribió
  al crear la campaña, vía `generateAudioCached()` — **ya existía en
  `CobranzaTtsService` sin ningún consumidor**, cachea por hash del texto (un solo
  audio por campaña, no uno por llamada).
- **`max_canales_simultaneos` es la primera vez que existe un límite de
  concurrencia real** — antes "50" en `BlastCampanaJob` era solo el tamaño del
  LOTE que procesa cada corrida del job (cada 5 min), nunca un tope de cuántas
  llamadas pueden estar sonando/hablando AL MISMO TIEMPO. Ahora, si se configura,
  se resta lo que ya está `marcando` y solo se completa el lote hasta ese tope.
- `CobranzaCampanaService::cargarPorZona()` — el "botón de corte por distrito/
  zona/caja": resuelve clientes vía `box_zones→zones→districts`, la nomenclatura
  DxZyCz REAL de `NomenclatureController` (`D{district->id}Z{zone->id}C{box->id}:
  {client->id}`), no una tabla nueva paralela. `cargarMorosos()`/`activarCampana()`
  quedaron intactos — cobranza sigue siendo SIEMPRE por morosos.
- `CampanaController::activarPorZona()` — corte exige el permiso dedicado que ya
  pedía el plan (`cobranza.corte.lanzar`); aviso/anuncio comparten `cobranza.manage`
  (mismo criterio que el resto de acciones sobre campañas). + 3 lookups de solo
  lectura (distritos/zonas/cajas) para el selector en cascada de la UI.
- UI (`CobranzaCampanas.vue`): selector de tipo + troncal + mensaje libre + canales
  simultáneos en "Nueva campaña"; modal nuevo de distrito→zona→caja en cascada
  para activar aviso/anuncio/corte (el botón normal de "Activar" sigue siendo solo
  para cobranza, que carga morosos directo sin pedir zona).

### Bug real preexistente encontrado (y corregido) al probar

`cargarMorosos()` filtraba por `clients.status = 'active'` — **esa columna no
existe en la tabla `clients`**. El estado real del cliente vive en
`client_main_information.estado` (`'Activo'/'Bloqueado'/'Cancelado'/'Inactivo'`,
capitalizado). Se descubrió al copiar el mismo patrón para `cargarPorZona()` y
probarlo en transacción+rollback contra datos reales — truena con "Column not
found". Corregido en los dos métodos (`cmi.estado = 'Activo'`).

**Implicación seria**: `activarCampana()` es el flujo REAL de cobranza (documentado
como "en producción" en `module.json`/CLAUDE.md) — con este bug, **nunca pudo
haberse ejecutado con éxito en dev**, porque la query truena en el primer intento
real. Tras el fix, la query corre — pero `cargarMorosos()` tropieza con un
**segundo bug preexistente, separado, NO corregido aquí**: un cliente real tiene
en `cmi.phone` un valor de teléfono corrupto/duplicado (`"5617491441
5576808632"`, dos números con doble espacio) que excede el ancho de la columna
`cobranza_llamadas.telefono` — `Data too long for column 'telefono'`. Es un
problema de calidad de dato de UN cliente específico (o de falta de saneo antes
de insertar), no de esquema — fuera de alcance de este arreglo puntual. Queda
como hallazgo para revisar aparte (¿sanear el dato del cliente, o sanitizar/
truncar en el insert?).

`cargarPorZona()` se probó limpia (0 insertados) — el único `box_zones` de
prueba en dev apunta a `client=16`, que ya no existe (dato huérfano de 2024), no
un bug de la query.

### Pendiente

- No se probó un blast REAL (ninguna llamada saliente de aviso/anuncio/corte se
  disparó — solo se verificó la lógica de carga de clientes en transacción con
  rollback, sin tocar `cobranza_llamadas` de verdad ni disparar `BlastCampanaJob`).
- El bug de datos sucios en `cmi.phone` de al menos un cliente real, sin resolver.
- Fase 6 (IA de voz real) sigue siendo la última fase del plan original — no
  arrancada.
