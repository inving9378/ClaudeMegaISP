# Botón "Agregar item" (Torre de Control) — comportamiento de punta a punta

Item #858. Escrito porque nadie tenía claro qué hacía el botón más allá de "insertar la fila" —
era información que no existía en ningún lado.

## Paso 0 — gate de confirmación

`hostname -I` en la máquina donde se ejecutó este item devuelve `192.168.105.11 …` (dev). No
aparece `192.168.105.108` ni `v1megaisp.com.mx` → dev confirmado, se procedió.

## Qué hacía el botón ANTES de este item (comportamiento previo)

Archivos: `resources/js/components/module/releases/torre-control/RoadmapTab.vue` (modal + función
`addItem()`) → `POST /api/roadmap/items` → `RoadmapController::store()`
(`app/Modules/Addons/Roadmap/Controllers/RoadmapController.php`).

Paso a paso, del clic a la fila insertada:

1. `addItem()` arma el payload (`title`, `priority`, `target_version`, `prompt`) y hace
   `axios.post('/api/roadmap/items', payload)`. **No enviaba ninguna clave de idempotencia** ni
   deshabilitaba el botón mientras la petición estaba en vuelo → un doble clic disparaba dos
   `POST` reales, cada uno creando su propio item.
2. `RoadmapController::store()` valida los campos y arma `$data` (`status='pending'`,
   `position` = siguiente en la cola `pending`, sub-tareas por defecto si no vienen).
3. **`categoriaFronteraDura($texto)`** (`ThomasService`) escanea título+descripción+prompt en
   busca de palabras de la frontera dura (producción, borrar datos, dinero, credenciales):
   - Si **NO** encuentra nada (`$frontera === null`) → el item nace **`estado_aprobacion =
     'aprobado_irving'`**, `aprobado_por` = el actor, `revisado_at = now()`. Esto es la regla
     **#566 "crear es aprobar"**: como la creación pasó por sesión autenticada + permiso
     `roadmap_manage` en la UI de la Torre, se considera aprobación explícita de Irving y entra
     **directo a la cola ejecutable**, sin pasar por el triaje normal de Thomas/Revisor y **sin
     que se le calcule un `nivel_riesgo` A/B/C real** (queda como venga del form, casi siempre
     vacío).
   - Si **SÍ** encuentra frontera dura → el item se queda **sin** `estado_aprobacion` seteado
     aquí (nace como venga el default de la tabla — en la práctica `pendiente_revision`), a la
     espera de que el circuito de triaje normal (Thomas/Revisor, vía cron) lo clasifique y, si
     toca, lo mande a la bandeja `requiere_irving`.
4. Si no se mandó `modulo`, `ThomasService::clasificarModulo($texto)` se lo asigna ahí mismo
   (para que no bloquee las 6 terminales por "sin módulo", #432 B2).
5. `RoadmapItem::create($data)` — **aquí se inserta la fila**.
6. Si `$frontera === null`, `ThomasService::sellarEsfuerzo($item)` calcula y sella
   `eta_minutos`/`eta_asignada_at` de forma síncrona (#480) — antes de este paso ya existía; no
   se tocó.
7. Se escribe una entrada en `$item->log` (`evento: 'item_creado_ui'`) y se guarda.
8. Responde `201` con `{ item, aviso }`. **`aviso` ya existía** con dos variantes de texto
   ("Creado y aprobado: entra directo a la cola…" / "Creado, pero NO entra solo a la cola…"), pero
   ese texto era **aspiracional**: decía "una terminal libre lo toma en segundos" sin haber hecho
   nada para acelerar eso — el item simplemente quedaba `aprobado_irving` y punto.

**Lo que NO hacía:** en ningún punto de `store()` se llamaba a `requestDisparo()` (el método que
usa el botón "Jalar trabajo ahora") ni a ningún otro mecanismo de despacho inmediato. El item
quedaba **ejecutable pero quieto**, esperando a que el cron de `circuito:scheduler` (cada minuto)
lo recogiera en su siguiente corrida — hasta ~60s de espera con una terminal libre y el trabajo ya
listo. Ese es el síntoma exacto que reporta el item #858.

## Con qué valores nace el item hoy (sin frontera dura)

| Campo | Valor |
|---|---|
| `status` | `pending` |
| `estado_aprobacion` | `aprobado_irving` |
| `aprobado_por` | actor (`irving:<login_user\|email\|id>`) |
| `revisado_at` | `now()` |
| `nivel_riesgo` | el que venga del form (normalmente vacío/null — **no se calcula aquí**) |
| `worker_sid` | `null` (nadie lo ha reclamado todavía) |
| `modulo` | el enviado, o clasificado por `ThomasService::clasificarModulo` |

## Quién asigna `nivel_riesgo` y cuándo

**No lo asigna el botón Agregar.** El flujo de creación desde la Torre (`store()`) es una vía
*distinta* del triaje normal: como ya es aprobación explícita de Irving, no pasa por
`RevisorService`/Thomas para clasificar A/B/C. Ese triaje real (el que sí llena `nivel_riesgo` con
criterio) corre para los items que entran por la **vía externa** (Cowork/auditor,
`RoadmapIntakeService`, que sí nacen `pendiente_revision`) y para los que la creación en Torre
manda a esa cola por declarar frontera dura — vía el cron `circuito:revisar-backlog` (cada 2 min,
ver crontab) y comandos relacionados (`circuito:destrabe`, `circuito:priorizar-seguridad`).

## Cómo se despacha hoy el trabajo a las terminales

Dos caminos, **un solo despachador real**:

1. **Sondeo periódico (siempre activo):** cron `* * * * * circuito:scheduler` — corre cada
   minuto, sin condición. Es **el único comando que de verdad lanza vueltas y reclama items**
   (`app/Modules/Addons/Roadmap/Console/SchedulerCommand.php`): toma flock propio
   (`scheduler.lock`), respeta el kill switch (#342), el semáforo de paralelismo, el pre-filtro de
   módulo y la anti-colisión (#341, reclama atómico `aprobado_* → en_progreso` antes de lanzar), y
   lanza hasta N vueltas por-item en paralelo (una por worktree `wt-K`).
2. **Botón "Jalar trabajo ahora" (`disparar()` en `TorreControl.vue` → `POST
   /api/roadmap/circuito/disparar` → `RoadmapController::disparar()` →
   `RoadmapCircuitoService::requestDisparo()`):** **NO lanza nada por sí mismo.** Solo escribe una
   bandera (`settings` key `circuito_disparo_pendiente` vía `putSetting`, más una fila de auditoría en
   `circuito_disparos`). Esa bandera la consume el picker on-box
   `circuito:disparo-check --watch=58 --poll=3` (cron cada minuto, corre como `meganet`, sondea
   cada 3s durante 58s): al ver la bandera, adelanta una corrida de `circuito:scheduler` en vez de
   esperar al próximo minuto de cron. `circuito:scheduler` también drena/limpia esa bandera al
   arrancar. **El método concreto que "despacha" es siempre `circuito:scheduler`** — el botón
   solo lo adelanta.

Esto es clave para el diseño de #858: el despacho es **asíncrono por naturaleza** (la asignación
real a una terminal ocurre en la corrida del scheduler, 0–3s después del `POST` si hay picker
sondeando, no dentro del mismo request HTTP). No existe hoy una forma síncrona de saber, dentro
de la respuesta de `store()`, si el item quedó efectivamente tomado por una terminal libre o si se
quedó en cola por falta de capacidad — eso solo se sabe consultando el item unos segundos después
(`worker_sid`/`estado_aprobacion` cambia a `en_progreso`).

## Qué cambió con #858 (esta fase)

1. **`RoadmapController::store()`** ahora llama a `RoadmapCircuitoService::requestDisparo()`
   —el **mismo método** que usa "Jalar trabajo ahora"— justo después de crear el item, **solo
   cuando el item entró directo a la cola** (`$frontera === null` → `aprobado_irving`). No se creó
   ninguna ruta de despacho nueva: se reutiliza la bandera + el picker + el scheduler existentes.
   Si `requestDisparo()` falla (p.ej. circuito en pausa) o lanza excepción, se loguea
   (`circuito.agregar_item.disparo_fallo`) y el item **queda creado igual** — nunca se pierde por
   un fallo de despacho (criterio de aceptación #7).
2. **Idempotencia de doble clic:** el modal ahora deshabilita el botón mientras la petición está
   en vuelo (`addingItem`) y envía una `idempotency_key` (UUID por intento). `store()` acepta
   `idempotency_key` opcional: si la misma clave ya se usó en los últimos 30s (caché, TTL 30s),
   devuelve el item ya creado (`idempotente: true`) en vez de duplicarlo — el frontend no lo vuelve
   a insertar en la lista. Cubre el caso de doble clic y de un reintento de red del mismo intento.
3. **Mensaje (`aviso`)** distingue si el disparo se pudo adelantar o no (mismo texto aspiracional
   de antes cuando el disparo falla, ahora honesto sobre el resultado real de `requestDisparo()`).

## Qué queda pendiente (fuera de esta fase, ver sub-items de #858)

- Un aviso que resuelva **de forma dinámica y verificada** cuál de las 4 situaciones de la sección
  3 del spec ocurrió de verdad (`lanzado a wt-N` / `en cola, sin terminal libre` / etc.) requiere
  sondear el item tras crearlo (el despacho es asíncrono, no hay forma síncrona de saberlo en el
  mismo request) — no se intentó en esta fase para no bloquear el request de creación con lógica
  de sondeo/latencia adicional.
- La **ventana de deshacer de 15s** que cancela la asignación y libera la terminal requiere una
  capacidad nueva (revertir `en_progreso → aprobado_irving`, liberar el flock del worktree y, si
  ya arrancó `vuelta.sh`, detenerlo limpiamente) — no existe hoy un endpoint de "liberar item en
  vuelo" reutilizable; construirlo a la ligera es justo el tipo de cambio que puede pelear con el
  scheduler/anti-colisión (#341) si no se diseña con cuidado. Se dejó registrado como sub-item.
