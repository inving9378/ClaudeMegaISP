# Motor de Auditoría Continua (#559) — el generador de trabajo del circuito

> **Para qué existe.** El circuito ya sabía **repartir** (`circuito:scheduler`) y **juzgar**
> (Thomas / revisor / autopilot), pero no sabía **generar**. Cuando la cola se vaciaba, las 6
> terminales quedaban ociosas hasta que un humano escribiera items. Este motor cierra ese hueco:
> escanea el sistema módulo por módulo, detecta lo que falta y crea los items-hijo que lo cierran.

---

## Lo primero que hay que saber

| Necesito… | Dónde |
|---|---|
| **Apagarlo YA** | `config/circuito.php` → `auditor.enabled = false` · o `CIRCUITO_AUDITOR=false` en `.env` |
| **Subir/bajar el cap** | `config/circuito.php` → `auditor.cap_por_ciclo` (arranca en **10**) · o `CIRCUITO_AUDITOR_CAP` |
| **Ver qué haría sin que haga nada** | `php artisan circuito:auditor` (dry-run por default) |
| **Ver el último reporte** | tabla `settings`, clave `circuito_auditor_ultimo_reporte` (JSON) · log `roadmap_externo` |
| **Saber qué módulos ya están cerrados** | `php artisan circuito:auditor --dod` |

El **kill-switch no lo puede saltar `--forzar`**: se evalúa antes que todo lo demás. Un interruptor
que una bandera puede ignorar no es un interruptor.

Hay **dos** kill-switches y ambos frenan al motor:

1. **El propio** — `circuito.auditor.enabled`. Apaga sólo al generador; el reparto sigue igual.
2. **El global del circuito** — `circuito_pausado` (botón de la Torre). En pausa el motor no crea
   nada, como todo lo demás.

---

## Qué NO hace (fronteras de diseño, no omisiones)

- **No reparte.** Crea items y se va. Despachar sigue siendo del `scheduler`.
- **No aprueba.** Los items nacen por `RoadmapIntakeService` → `pendiente_revision`, igual que
  cualquier otro. Un item mecánico nace **nivel A**, y un A pendiente ya es reclamable por el pool
  (política vigente); quien decide sigue siendo el triaje de siempre.
- **No decide de producto.** Lo que huele a "¿qué debe hacer esto?" va a la bandeja de Irving con la
  pregunta concreta. Fabricar una decisión suya sería el peor modo de fallo posible.
- **No inventa trabajo.** Un módulo sin gaps no genera nada. Si no hay gaps en ningún lado, el motor
  se queda quieto, y eso está bien.

---

## Cuándo corre

Va enganchado dentro de `circuito:scheduler` (que corre cada minuto por cron), y no en su propia
línea de cron, por el mismo motivo que Thomas: **el scheduler es el único despachador** (#432 B1);
un cron paralelo abriría una segunda carrera sobre los mismos items.

Está colocado **antes** de calcular slots libres, así que lo que genere se reparte en **esa misma
vuelta**, no en la siguiente.

Corre sólo si se cumple todo esto:

```
motor encendido          (circuito.auditor.enabled)
Y circuito sin pausar    (circuito_pausado != 1)
Y cola < umbral          (auditor.umbral_cola, default 3)
Y pasó el intervalo      (auditor.min_intervalo_minutos, default 15)
```

### Cómo se mide "la cola"

Con `RoadmapCircuitoService::ejecutablesParalelo()` — **la misma puerta que usa el scheduler**, no
un conteo de items aprobados.

Esto importa más de lo que parece. Al 2026-08-08 había **87 items en `aprobado_irving` y 0
reclamables**: 35 fuera del pool automático, 26 esperando merge de Irving, 25 rotulados
`[BLOCKED-]`/`[PARKED-]`. Contar aprobados "en bruto" habría hecho creer que había cola con la flota
entera parada — justo el error que este motor viene a corregir.

---

## Orden de trabajo: los dos carriles y el round-robin

`config/circuito.php` → `auditor.carriles`:

- **`paralelo`** — módulos con acoplamiento ~0 (nadie los consume, no consumen a nadie). Sus items
  pueden correr a la vez en distintas terminales sin pisarse.
- **`serializado`** — la base acoplada: `Clientes` (in=8), `Configuracion` (in=9), `CRM` (in=8),
  `ModuleManager` (in=7). Sostienen a casi todo el sistema; dos cambios simultáneos ahí se pisan.
  Van al final.

**El orden de la lista es el orden de trabajo.** Un módulo sin gaps mecánicos se salta solo (está en
su DoD de Fase 1), así que la lista no se mantiene a mano cuando algo se termina.

### Por qué round-robin y no "un módulo hasta vaciarlo"

`modulo` **no es una etiqueta cosmética: es el footprint con el que el scheduler serializa** (#432
B2). Diez items del mismo módulo ocupan **una** terminal y dejan cinco ociosas — exactamente lo
contrario de lo que busca el motor. Por eso cada ciclo toma pocos items de muchos módulos
(`auditor.items_por_modulo_por_ciclo`, default 2) hasta llegar al cap.

Cada módulo igual avanza hacia su DoD ciclo a ciclo; lo que cambia es que la flota no se queda
esperando.

---

## Los seis detectores (Fase 1)

Se apagan uno por uno en `auditor.detectores`.

| Detector | Qué encuentra | Granularidad |
|---|---|---|
| `hueco_ruteado` | Método con **ruta activa** y cuerpo vacío. El hueco real: el usuario hace clic y no pasa nada. | 1 item por hueco |
| `enlace_roto` | URL del `module.json` (menú/admin_cards/config_sections) que no resuelve a ninguna ruta → 404 desde el menú. | 1 item por enlace |
| `todo` | `TODO`/`FIXME`/`HACK` **en comentario** (no la palabra española "todo"). | 1 item por archivo |
| `andamiaje` | Métodos públicos vacíos **sin ruta** — basura de `make:controller --resource`. | 1 item por módulo |
| `sin_clasificar` | Items de la Hoja de Ruta sin footprint, que por diseño **corren solos y bloquean a las 6 terminales**. | 1 item por lote |
| `semilla` | Pendientes del inventario 2026-08-08 que un escaneo no puede ver. | 1 item por pendiente |

`andamiaje` agrupa por módulo a propósito: Mapas solo tenía **93** métodos así, y un item por método
inundaría la Hoja de Ruta con basura del mismo tamaño que la basura que limpia.

### La semilla se auto-verifica

`app/Modules/Addons/Roadmap/Support/InventarioSemilla.php`. Cada entrada puede traer un `vigente`:
una closure que responde *"¿este gap sigue existiendo?"*. Sin ella, la entrada se cree a sí misma
para siempre.

No es teórico: en la primera corrida **dos entradas ya estaban obsoletas en cuestión de horas**
(el circuito registró `addon-talento` en `module_registry` mientras se escribía la lista). Si la
comprobación truena, el gap se descarta — fallar hacia "no generar trabajo" es el lado seguro.

---

## Clasificación: mecánico vs producto

```
gap detectado
   ├─ ¿toca la frontera dura de Thomas?  → PRODUCTO (bandeja de Irving)
   │    producción · borrar datos · dinero · credenciales
   ├─ ¿el texto pide una decisión?        → PRODUCTO
   │    (auditor.terminos_producto: preguntar, decidir, definir, confirmar con…)
   └─ resto                                → MECÁNICO (nivel A → cola)
```

La frontera dura reusa `circuito.thomas.escalamiento` **a propósito**: la política de qué despierta a
Irving debe vivir en un solo lugar, no duplicada por cada generador de trabajo. Es la **última
palabra**: un gap que la toque nunca sale como mecánico, diga lo que diga el detector que lo
encontró.

**Item mecánico** → `nivel_riesgo = A`, `pendiente_revision`, con un `prompt` ejecutable. Reclamable
por el pool.
**Item de producto** → `nivel_riesgo = C`, `estado_aprobacion = requiere_irving`, con la pregunta en
`preguntas[0]` marcada `requiere_irving: true` (ni el autopilot la toma, ni el pool lo reclama).

---

## Dedup: dos capas, y por qué hacen falta las dos

1. **Huella exacta** (`roadmap_items.auditor_fingerprint`, columna aditiva indexada) — cubre todo lo
   que creó el propio motor, **abierto o cerrado**. Si ya cerramos un gap, no se vuelve a crear
   nunca. Una columna sobrevive al renombrado y al cierre; un marcador en el título, no.

2. **Solapamiento de título dentro del mismo módulo** — cubre lo que crearon **Irving, Cowork o una
   terminal**. Sin esto el motor duplicaría trabajo humano: al encenderlo, el circuito ya estaba
   ejecutando #564 (Reportes), #565 (Talento en registry) y #567 (huecos de Mapas), que son
   exactamente gaps que el motor detecta.

Dos lecciones que costaron una corrida cada una:

- Los items #564/#565 viven con `modulo = ModuleManager`, **no** con el módulo que arreglan. Buscar
  candidatos sólo por módulo los habría duplicado → la búsqueda también va por palabras distintivas
  del título, sin importar el módulo.
- Pero entonces **el módulo tiene que volver a entrar como identidad al comparar**: sin ese guard,
  "GestionRed: eliminar 1 método de andamiaje resource sin ruta" mataba a "Mapas: eliminar 93
  métodos de andamiaje resource sin ruta" (comparten 5 palabras, sólo se distinguen por el módulo).
  Pasó de verdad: la primera corrida en vivo creó 6 items en vez de 10.

Hay además una **tercera verificación justo antes de escribir** cada item: entre el escaneo y el
alta pudo entrar un item por otra vía.

> **Compensación asumida:** el umbral de solapamiento se afina hacia el falso positivo. Un falso
> positivo cuesta un gap que no se crea (visible en la columna *"ya existen"* del reporte, y el
> módulo se queda fuera de su DoD). Un falso negativo cuesta duplicar trabajo humano en vuelo.

---

## Contención — el resumen

| Mecanismo | Dónde | Default |
|---|---|---|
| Kill-switch propio | `auditor.enabled` | `true` |
| Kill-switch global | `circuito_pausado` (Torre) | apagado |
| Cap duro por ciclo | `auditor.cap_por_ciclo` | **10** |
| Items por módulo por ciclo | `auditor.items_por_modulo_por_ciclo` | 2 |
| Umbral de cola | `auditor.umbral_cola` | 3 |
| Intervalo mínimo entre escaneos | `auditor.min_intervalo_minutos` | 15 min |
| Dedup | 3 capas (huella · título · pre-escritura) | — |
| Frontera dura | `circuito.thomas.escalamiento` | — |
| Módulos excluidos | `auditor.excluir_modulos` | Demo, Security, Voice |
| Dry-run | default del comando | sin `--apply` no escribe |

---

## Comandos

```bash
php artisan circuito:auditor                      # DRY-RUN del ciclo completo (no escribe nada)
php artisan circuito:auditor --detalle            # + el detalle completo de cada gap candidato
php artisan circuito:auditor --modulo=Tickets     # sólo un módulo
php artisan circuito:auditor --dod                # qué módulos están en su DoD de Fase 1
php artisan circuito:auditor --apply              # EN VIVO (respeta umbral e intervalo)
php artisan circuito:auditor --apply --cap=4      # en vivo con tope propio
php artisan circuito:auditor --forzar             # ignora umbral e intervalo (NO el kill-switch)
```

---

## DoD de Fase 1

Un módulo está cerrado cuando **no le quedan gaps mecánicos detectables**: sin huecos ruteados, sin
enlaces de menú rotos, sin TODO mecánicos, sin andamiaje muerto y con sus pendientes de la semilla
cerrados. Los gaps de **producto** no cuentan para el DoD — dependen de una respuesta de Irving, no
de trabajo pendiente.

`php artisan circuito:auditor --dod` lo reporta módulo por módulo.

---

## Fase 2 — specs por módulo con su DoD

El enganche ya está: `AuditorService::medirContraSpec(string $modulo)`. Hoy devuelve `[]` a
propósito, para no bloquear la Fase 1; `detectarGaps()` ya lo llama, así que el día que existan los
specs se llena ese método y **no se toca nada más** del motor.

Contrato esperado: un `RoadmapItem` con `modulo` = el módulo y un marcador en el título (p. ej.
`[SPEC]`), cuyo `description` liste los criterios de DoD verificables. Con eso el motor pasa de
"detectar lo que está roto" a **medir realidad contra lo que el módulo debería hacer** — que es
donde la Fase 1 se queda corta: hoy no puede ver una funcionalidad que nunca se escribió, sólo una
escrita a medias.

Cuando la Fase 2 esté viva, `InventarioSemilla` se retira: deja de hacer falta.
