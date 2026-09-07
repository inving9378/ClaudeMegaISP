# MR-29 (item roadmap #967) — Rúbrica de comparación congelada: fuente de medición por criterio

**Este item no compara ni mide nada** (eso es MR-27, #963). Su único trabajo es dejar identificada
y verificada, contra el sistema real de dev, la fuente concreta con la que se va a medir cada uno
de los 6 criterios de la rúbrica que quedó congelada en la `description` de #967. Cero mediciones
ejecutadas — solo verificación de que la query/endpoint/servicio referido existe con ese nombre
exacto de tabla/columna/ruta, o constancia de que todavía no existe y de qué item depende.

Verificado 2026-09-07 contra el esquema y los datos reales del dev (192.168.105.11).

## Criterio 1 — Paridad de conteo por tipo de elemento

Los "tipos de elemento" de D11 (source/cupboard/junction_box/pack/splitter/equipo activo-pasivo)
no son tablas propias: viven como valores de dos columnas string, confirmadas en el código real:

- `map_layers.dialog` / `mapared_layers.dialog` → `cupboard`, `junction_box`, `pack`, `source`
  (+ el resto del catálogo real: `route`, `service_box`, `kmz`, `region`, `pole`, `site`, `note`).
- `map_devices.type` / `mapared_devices.type` → `splitter` (+ `olt`/`router`/`switch` = "activo";
  `organizer`/`rack`/`charole`/`drop` = "pasivo"; `client` es el nodo del cliente, no equipo).

Query (misma forma en las 4 tablas, cambiando `map_`→`mapared_` y la columna):

```sql
SELECT dialog, COUNT(*) FROM map_layers  GROUP BY dialog;
SELECT dialog, COUNT(*) FROM mapared_layers GROUP BY dialog;
SELECT type,   COUNT(*) FROM map_devices GROUP BY type;
SELECT type,   COUNT(*) FROM mapared_devices GROUP BY type;
```

Conteo real verificado hoy en el lado legacy (`map_*`): `junction_box`=226, `cupboard`=1,
`splitter`=1035, `olt`=2, `router`=1, `switch`=1, `organizer`=14, `rack`=6, `charole`=5675,
`drop`=518, `client`(device)=1533, `route`=3084, `service_box`=1660. `pack` y `source` dan **0
filas reales** en el legacy hoy (tipos previstos por D11, sin dato de producción todavía) — para
esos dos la paridad es trivial (0=0) mientras no exista un dato real de ese tipo.

**Bloqueo:** la query está verificada contra el esquema real y lista para usarse. El lado
`mapared_*` hoy solo tiene datos de siembra de prueba (9 `mapared_devices`, 5 `mapared_devices_ports`,
0 en el resto) porque **MR-05 (#941) no está implementado**: el comando
`mapared:importar-legacy` que ese item define no existe en el repo (grep sin resultados en
`app/Modules/Addons/MapaRed/Console/`; solo existe `mapared:backfill`, de MR-15, que es la
conversión AL modelo nuevo, no la copia cruda). Confirmado también por el item de respuesta
`#9990421` ("mirror vacío bloquea toda conversión"). La comparación real de esta fila depende de
que `#941` se implemente y se corra.

La evidencia de "omitidos" que pide la columna 3 (filas omitidas y por qué: lat/lng nula, padre
inexistente, tipo desconocido) es justamente el reporte final que el propio prompt de `#941` le
exige a ese comando — hoy no existe porque el comando no existe.

## Criterio 2 — Elementos huérfanos en el módulo nuevo

Confirmado contra el esquema real de `mapared_*` (migración `2026_09_04_160000_create_mapared_mirror_tables.php`):

- Padre inexistente (dispositivos): `mapared_devices.parent_id` (jerarquía device→device, ej. un
  splitter colgado de un rack) y `mapared_devices.layer_id` (device→layer dueño).

  ```sql
  SELECT d.id FROM mapared_devices d LEFT JOIN mapared_devices p ON p.id = d.parent_id
    WHERE d.parent_id IS NOT NULL AND p.id IS NULL;
  SELECT d.id FROM mapared_devices d LEFT JOIN mapared_layers l ON l.id = d.layer_id
    WHERE d.layer_id IS NOT NULL AND l.id IS NULL;
  ```

- Padre inexistente (layers): `mapared_layers.project_id` → `mapared_proyects.id`,
  `mapared_layers.service_box_id` → `mapared_layers.id` (self, la NAP dueña de un drop).

- lat/lng nulos o (0,0): aplica de verdad **solo a `mapared_layers`**. Las columnas D8
  (`lat`/`lng`/`geom_json`/`bbox_*`) se agregaron a las 9 tablas espejo por uniformidad, pero
  `map_devices`/`mapared_devices` nunca tuvieron coordenada geográfica propia en el origen — su
  posición (`position_x`/`position_y`) es un punto de diagrama dentro de un rack, no un punto en
  el mapa. La cláusula de la rúbrica "que no vinieran ya así del origen" excluye explícitamente a
  `mapared_devices` de este chequeo: ahí `lat`/`lng` NULL es el estado normal heredado, no una
  regresión. En `mapared_layers` sí es real (la geometría vive en la columna `coords` JSON, y las
  columnas D8 se derivan de ahí):

  ```sql
  SELECT id FROM mapared_layers WHERE (lat IS NULL OR lng IS NULL) OR (lat = 0 AND lng = 0);
  ```

  — contrastar contra la misma condición sobre `coords` en `map_layers`, para no contar como
  huérfano algo que ya nacía sin coordenadas en el origen (ej. carpetas `classification=project`
  sin geometría propia).

**Bloqueo:** mismas tablas/columnas del Criterio 1, ya verificadas contra el esquema real. La
ejecución real depende también de `#941` (MR-05) por la misma razón: hoy `mapared_*` no tiene los
datos reales que hagan la comparación significativa.

## Criterio 3 — Trazo OLT→ONT en la zona piloto `T-TULTITLAN-FO96-*`

La fuente prevista por la rúbrica es "la salida del motor de MR-16 para cada NAP de la zona".
**MR-16 (`#952`) no existe en el código** — escaló `requiere_irving` por timeout sin commits
(confirmado leyendo el item real). No hay ningún servicio de grafo genérico en
`app/Modules/Addons/MapaRed/Services/`; lo único parecido es
`OpticalBudgetService::trazarRuta()` (MR-18, `#954`), y el propio docblock de esa clase aclara que
es "un caminador INTERNO mínimo... si MR-16 aterriza después, puede reemplazar este método" — no
es el motor genérico que este criterio necesita invocar.

Enumeración de NAPs de la zona (para cuando MR-16 exista): la zona piloto sí tiene dato real hoy
en el legacy — `map_proyects` trae 8 carpetas con "TULTITLAN" en el nombre (ids `640`, `712`,
`1496`, `1956`, `1957`, `1962`, `2033`, `2088`; ej. `T-TULTITLAN-ST1..ST4` bajo el padre `1955`), y
`mapared_cables.zona` (tabla nueva de MR-09, hoy vacía) es el campo pensado para portar esa misma
etiqueta a las tablas nuevas. Una vez migrados los datos, la enumeración de NAPs de la zona sería
`mapared_layers` con `dialog IN ('service_box','junction_box')` dentro del árbol de esas carpetas,
o vía `mapared_cables.zona LIKE 'TULTITLAN%'` siguiendo la cascada de hilos/empalmes hasta la NAP
— pero ese "seguir la cascada" es exactamente el grafo que MR-16 tiene que construir; no se
improvisa un sustituto aquí (sería duplicar el alcance de MR-16, cosa que el propio
`OpticalBudgetService` evitó a propósito según su docblock).

**Bloqueo:** depende de `#952` (MR-16, el motor no existe) y, para tener NAPs reales que recorrer,
de `#941` (MR-05, copia de los datos reales de Tultitlán a `mapared_*`).

## Criterio 4 — Alta de una NAP nueva en ≤3 pasos

Pantalla: `/mapa-red` (componente `resources/js/components/module/mapared/LeafletMapRed.vue`).

Backend ya construido: `POST /mapa-red/api/elementos/nap` `{lat, lng, tipo_splitter_id?}`
(MR-24d, `#9990437`) — resuelve zona/proyecto por punto (`ZonaResolverService`), snap a troncal
cercano ≤15m (`SnapService`), genera nombre con `NomenclaturaService::siguienteNap(zona)` (patrón
`NAP-{ZONA}-{N}`) y crea el `MapaRedLayer(dialog='service_box')` + splitter opcional, sin pedir
nombre a mano. Verificado en el commit `af4e7619` ("MR-24d: endpoint de alta rápida de NAP"), pero
ese commit vive en la rama `circuito/item-9990437-mr-24d-endpoint-de-alta-rapida-de-nap` y
**todavía no está mergeado a `main`** (no aparece en el árbol de este worktree, que está al día
con `main`) — hay que reverificar cuando MR-27 mida, por si para entonces ya se integró.

Falta el frente para "clic en el mapa → tipo+splitter → guardar" sin pasar por el flujo viejo
(menú contextual → `BoxServiceComponent.vue`, que hoy EXIGE nombre a mano): eso es MR-24e
(`#9990439`, "Modo dibujo en el mapa"), que sigue `requiere_irving` — sin esa pieza no hay cómo
correr el flujo real de 3 clics para tomarle el screenshot a Irving.

**Bloqueo:** depende de `#9990439` (MR-24e) para la UI; el backend (`#9990437`) ya existe pero aún
no está en `main`.

## Criterio 5 — Carga del mapa con todos los elementos de Tultitlán visibles en ≤3s

Pantalla: `/mapa-red`. Llamada real que carga el mapa: `GET /mapa-red/api/layers`
(`LayersController::index`) — `SELECT id, type, coords, data, text, label, dialog FROM
mapared_layers` **sin ningún filtro** (ni por zona ni por proyecto): trae toda la tabla en una
sola respuesta, igual que el módulo viejo (paridad 1:1 de MR-06, sin optimizar todavía). Medición:
Network tab del navegador sobre esa request, 3 corridas, reportar la peor (tal como pide la
evidencia de la fila).

No hay endpoint separado por zona porque el filtro por capa/zona (D22) es MR-22 (`#958`), que
sigue `requiere_irving` — hoy medir "Tultitlán" en los hechos mide el mapa completo, no un recorte
por zona. Es una salvedad a anotar junto con el número cuando se mida, no algo a corregir en este
ítem.

Contador visible: **no existe hoy un contador de elementos en la UI** de `LeafletMapRed.vue` (se
buscó por texto/label de "N elementos" y no hay ninguno). El "contador visible" de la evidencia
tendría que salir de la consola del navegador (`response.length` del propio fetch) o del panel
Network (cantidad de features del payload), no de un contador en pantalla. No se construye ese
contador en este ítem (fuera de alcance de MR-29: no se agregan features).

**Medible hoy mismo**, sin bloqueo de otro item, con las dos salvedades anotadas arriba.

## Criterio 6 — Presupuesto óptico calculado vs. RX real de MultiOLT (≤3 dB, ≥20 ONUs)

Fuente ya construida y conectada (MR-18, `#954`, completado, merge `87fa06a4`):

- Endpoint: `GET /mapa-red/api/enlaces-servicio/{id}/presupuesto-optico`
- Controller: `EnlacesServicioController::presupuestoOptico`
- Servicio: `OpticalBudgetService::calcular($enlace, $ventana)` — camina el trazo interno mínimo
  (splitters + empalmes) sumando pérdidas, calcula `rx_estimado_dbm`, y lo compara contra
  `rx_real_dbm` vía `OpticalBudgetService::rxRealDeMultiOlt()`:

  ```php
  $onu = OltOnu::query()->where('sn', $enlace->ont_serie)->first();
  $campo = $ventana === '1310' ? 'signal_1310' : 'signal_1490';
  ```

  — el RX real sale de `olt_onus.signal_1490` / `olt_onus.signal_1310` (tabla real de
  `addon-gestion-red`/MultiOLT, D18: no hay lector de OLT propio, se reusa este), emparejado por
  número de serie (`olt_onus.sn` == `mapared_enlaces_servicio.ont_serie`, exactamente D19: "se
  resuelve por nombre/serie, nunca por ID de BD").

Datos reales disponibles hoy: `olt_onus` sí tiene datos reales de MultiOLT (2954 ONUs, 1986 con
`signal_1490` no nulo) — esa mitad del criterio no depende de nada. Lo que falta es la otra mitad:
`mapared_enlaces_servicio` tiene **0 filas** hoy — no hay ningún enlace de servicio real que
invocar contra el endpoint, así que no hay cómo llegar a las 20+ ONUs que pide el umbral. Depende
de que existan enlaces de servicio reales con `ont_serie` poblado y coincidente con un `sn` real
de `olt_onus`, lo cual depende de `#941` (MR-05, traer los clientes/ONTs reales) y de que se den
de alta esos `mapared_enlaces_servicio` (la entidad ya existe, MR-14/`#950`, completada, pero sin
datos reales todavía por la misma razón).

**Bloqueo:** el endpoint y el emparejamiento con MultiOLT ya están construidos y verificados;
falta población real de `mapared_enlaces_servicio`, que depende de `#941` (MR-05).

## Resumen de dependencias para MR-27 (`#963`)

- **`#941` (MR-05, comando de copia — no implementado todavía)** bloquea, total o parcialmente,
  los criterios 1, 2, 3 y 6.
- **`#952` (MR-16, motor de grafo — no implementado)** bloquea el criterio 3.
- **`#9990439` (MR-24e, modo dibujo en el mapa — no implementado)** bloquea el criterio 4.
- El **criterio 5** es medible hoy mismo, con las dos salvedades anotadas (sin filtro de zona
  real, sin contador en UI — ambas cosas de otros items, no de éste).

Ningún hallazgo de esta vuelta requiere decisión de Irving: son huecos de dependencia ya conocidos
y ya registrados como items propios de la Hoja de Ruta (`#941`, `#952`, `#9990439`). No se genera
item de respuesta.
