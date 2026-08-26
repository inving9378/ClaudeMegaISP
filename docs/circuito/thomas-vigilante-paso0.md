# Thomas vigilante — PASO 0: inventario medido (solo lectura)

> **Estado: nada tocado.** Todo lo de aquí sale de leer código, `ps`, `df`, el crontab y tres
> comandos read-only (`circuito:thomas --diagnostico`, `--dry`, y un `tinker` de conteos).
> Medido el **2026-08-25 ~17:15**, en dev (`.11`). Ninguna cifra viene de memoria ni del doc previo.
>
> Responde el §1 (Paso 0) y el §11 (privilegios, opinión, horas) del encargo "Thomas — el
> supervisor como vigilante, y su chat". Reenfoque de #208, sin item nuevo.

---

## 1. Qué hace hoy `tick()`

| Pregunta | Medido |
|---|---|
| **Cadencia** | **Ninguna propia.** Se llama desde `SchedulerCommand.php:86`, es decir cada vez que corre el cron del scheduler. Ese cron está comentado desde el 24-ago: `crontab -l` da **9 líneas `# PAUSADO-20260824-incidente:`** y **1 sola activa**, la de `circuito:compuertas-sonda`. **Thomas lleva un día sin latir y nadie lo nota.** |
| **Qué evalúa** | Dos cosas y nada más: hasta 20 items con consulta viva (`conConsultaViva()`) y hasta 30 items sin `eta_minutos` que estén en pool o en vuelo. |
| **Qué escribe** | `consulta_respuesta`, `consulta_resuelta_at`, `consulta_resuelta_por`; al escalar además `estado_aprobacion='requiere_irving'` y `worker_sid=null`; una entrada de historial por decisión (`reportes->append`); `eta_minutos` al sellar. |
| **Qué decide** | El orden de 5 pasos de `evaluar()`: frontera dura → contradicción declarada → opción `recomendada` → primera `reversible` → si ninguna es reversible, escala. |
| **Frenos** | Sale temprano si `thomas.enabled=false` o `isPaused()`. |

Medición en vivo de hoy:

```
circuito:thomas --diagnostico → terminales 6 (0 ocupadas, 6 libres), cola_ejecutable 1,
                                colisiones_modulo [], consultas_vivas 0,
                                ocio_con_cola TRUE, pausado FALSE
circuito:thomas --dry        → 0 consultas resueltas, 0 estimaciones selladas
```

`pausado: false` con el cron comentado es la foto exacta del problema: **el circuito no está
frenado, está desenchufado**, y el único que podría contarlo es el que se quedó sin cron.

## 2. Sus cuatro fronteras duras

`config/circuito.php:288` → `thomas.escalamiento`, cuatro listas de términos por substring:
**producción** · **borrar datos** · **dinero** · **credenciales/seguridad**. Más un quinto caso
que no es lista: la terminal declara el spec contradictorio (`declaraContradiccion()`).

Se evalúan contra la pregunta **más** título y módulo del item. Al tocar una,
`escalar()` (línea 211) hace exactamente tres cosas: marca `requiere_irving`, **suelta la terminal**
(`worker_sid=null`) y deja la escalación en el historial con categoría y motivo. La terminal
recibe **exit code 1** y aborta su vuelta.

## 3. Qué le puede consultar una terminal

Una sola puerta: `circuito:consultar <id> --sid=wt-K --pregunta="…" --opcion="…|recomendada|reversible"`.
El contrato es el **exit code**: `0` = PROCEDE con la opción que Thomas indicó, `1` = ESCALADO, detente.
La respuesta es inmediata y determinista (coincidencia de términos, sin IA), así que la terminal no
gasta su turno esperando.

## 4. Con qué usuario corre, y qué puede con él

Thomas tiene **dos pieles**, y esto es la mitad de todo el diseño:

| Piel | Usuario | Puede | No puede |
|---|---|---|---|
| CLI (scheduler, cron) | `meganet` uid 1000 | matar procesos del circuito (son suyos), leer `/proc`, leer y editar su propio crontab, leer los flock de `/home/meganet/circuito`, escribir logs (**está en el grupo `www-data`**, y `storage/logs` es 777) | hablar con supervisord; `sudo` sin contraseña salvo nginx/asterisk |
| HTTP (Torre) | `www-data` uid 33 | leer `storage/app` (777) | ver `/home/meganet` (0700), el crontab, los procesos, los flock |

Medido: `id meganet` → grupos incluyen **33(www-data)**, 27(sudo), 123(mysql).
`sudo -n -l` → `(ALL:ALL) ALL` **con contraseña**; NOPASSWD sólo nginx, `tee` de sites-enabled y
asterisk. Sin TTY, en cron, eso es igual a nada.
`supervisorctl status` como meganet → `PermissionError` sobre `/run/supervisor.sock`
(`srwx------ root:root`). La sonda ya lo reporta: `workers.ctl_legible: false`.

## 5. Qué de Thomas vive en base y qué en archivo

| En BASE (muere con MySQL) | En ARCHIVO (sobrevive) |
|---|---|
| todo lo que sabe y escribe: `roadmap_items.consulta_*`, `estado_aprobacion`, `worker_sid`, `eta_minutos`, el historial de decisiones | su criterio: `config/circuito.php → thomas.*` |
| `circuito_ejecuciones` (**1 fila**), `circuito_revisiones` (85), `torre_config` (1), `torre_compuerta_cambios` (5) | el freno: centinela en `storage/app/circuito` (#170), ruta absoluta, y `freno-fallos.log` |
| el watchdog entero: `settings.circuito_watchdog_*` (beat, alertas, log, intentos) — hoy **0 filas** | el snapshot del SO: `storage/app/torre/compuertas-so.json`, reescrito cada minuto |
| | bitácoras de vuelta: `/home/meganet/circuito/logs`, **839 archivos, 4.5 MB**, del 10-jul al 25-ago |

**Su criterio es archivo; su memoria es base.** Por eso hoy no existe modo mínimo: con MySQL caído
Thomas no es que responda mal, es que no tiene de dónde responder.

## 6. `tick()` y `eta_minutos` (#209) — CONFIRMADO

`Schema::hasColumn('roadmap_items','eta_minutos')` → **SI**. `--diagnostico` la lee en el `get()` y
sale rc=0; `--dry` corre completo sin excepción. 215 items, 0 en progreso, 0 consultas vivas.
No truena.

---

## 7. Las 14 señales del §2, contra lo que ya se mide

8 ya se miden, 1 es imposible con el usuario actual, 5 no existen.

| Señal | Hoy | Dónde |
|---|---|---|
| proceso del circuito con PPID=1 | ✅ | sonda `ejecutor.huerfanas` + `pids_huerfanos` |
| `vuelta.sh` más viejo que su timeout | ✅ | sonda `mas_vieja_seg` vs `timeout_nominal` (600) |
| item `en_progreso` sin proceso vivo | ✅ | compuerta `reservados` (bd+so) |
| errores por minuto | ✅ | sonda `errores_ult_min` |
| tamaño de logs | ⚠️ parcial | sonda mide **sólo** el `laravel.log` del checkout principal — ver §8 |
| disco | ✅ | sonda `disco_uso_pct` (**65%**, 50 G libres) |
| tablas clave presentes | ✅ | compuerta `bd` |
| items despachables contra pool real | ✅ | `diagnostico.cola_ejecutable` + `ocio_con_cola` |
| supervisord STOPPED con el proceso vivo | ❌ **imposible** | no hay socket; hoy sólo se cuentan procesos con `ps` |
| RAM y swap | ❌ | nadie del circuito las mira (**swap al 82%: 1686/2047 MB**) |
| invocaciones de `claude -p` por hora | ❌ | `circuito_ejecuciones` tiene **1 fila** y registra al terminar, no al lanzar; la sonda sólo da un conteo instantáneo |
| `claimed_at` renovándose con `updated_at` congelado | ❌ | — |
| reclamo sin `worker_sid` | ❌ | — |
| commit en HEAD desatado | ❌ | y las vueltas corren `checkout --detach`, así que un commit ahí es invisible |
| urgentes inalcanzables por footprint | ❌ | `colisiones_modulo` es vecino, no es esto |

## 8. Cuatro hallazgos que salieron de medir

**a) 1.8 GB de log que nadie mira.** `/home/meganet/circuito/wt-2/storage/logs` pesa **1.8 GB** —
por eso wt-2 ocupa 2.5 GB contra los ~780 MB de sus seis hermanos. Cada worktree tiene su
`storage/` REAL (por eso el freno #170 usa ruta absoluta), así que hay **siete** `laravel.log`
creciendo y la sonda mide **uno**: el del checkout principal, 39.9 MB. El escalón de disco del §5
aplicado sobre la medición de hoy liberaría el archivo equivocado.

**b) Cinco `claude` viejos que ningún detector ve.** Ahora mismo hay 4 procesos `claude` de
**41 días 21 horas** (~150 MB RSS cada uno) y uno de **1 día 7 horas** (580 MB). Su PPID es un
`-bash` interactivo, **no 1**, así que el detector de huérfanos por PPID=1 no los ve; y no son
vueltas del circuito, así que el reaper tampoco. ~1.2 GB de RAM en procesos que nadie reclama.

**c) Matar por nombre mataría esta sesión.** `ps | grep claude` da 11 coincidencias e incluye
**el Claude Code con el que estás trabajando** (PID 1525737, 8 min). Un `pkill claude` es
autoinmune. La lista de muertes tiene que salir del registro de PIDs del circuito, no del nombre
del binario — y hoy ese registro no existe: `circuito_ejecuciones` guarda 1 fila y no guarda PID.

**d) Ya hay cinco engranes, no dos.** `ThomasService` (decide) · `WatchdogService` (detecta,
se auto-cura acotado, escala a los 3 intentos, **estado en `settings`**) · `SupervisorService`
("Thomas T", vista read-only) · `CompuertasService` (14 compuertas + sonda) ·
`EnvironmentHealthService` (#891, panel de salud **con sus propios umbrales de disco**).
Un vigilante nuevo sería el sexto.

---

## 9. §11.2 — ¿necesita el puente de privilegios? Sí, y sólo para una cosa

- **Para matar: NO.** Los procesos del circuito son de `meganet`; `kill` directo, sin sudo.
- **Para supervisord: SÍ, sin excepción.** El socket es `root:root` 0700 y el `sudo` de meganet
  pide contraseña; en cron eso no existe. Sin puente, "supervisord dice STOPPED y el proceso está
  vivo" —el bloqueo que costó una hora el 24-ago— **no se puede medir**, ni ver, ni arreglar.
- **Corrección al diseño del puente:** hoy su línea de sudoers nombra sólo a `www-data`. La mitad
  vigilante de Thomas corre como `meganet`. Hay que enumerar a los dos. Y ojo con la asimetría:
  para `www-data` el puente **es** una elevación (por eso todo su diseño); para `meganet`, que ya
  tiene `ALL:ALL`, **no eleva nada** — sólo lo vuelve usable sin TTY. Son dos decisiones
  distintas y conviene aprobarlas por separado.
- Para comprimir y truncar logs: no hace falta puente. `meganet` está en el grupo `www-data` y
  `storage/logs` es 777.

## 10. §11.3 — Opinión con el código enfrente: qué está del lado equivocado

**Lo que está bien y ya tiene mecanismo:** el freno asimétrico. `FrenoCircuito::poner()` escribe
un archivo y `isPaused()` lo lee **antes** que la base; `setPaused()` exige humano autenticado con
`circuito.pause` y **lanza excepción desde CLI** (#342). Es exactamente el §4: la máquina puede
frenar, sólo el humano suelta. **Recomendación: Thomas pausa ÚNICAMENTE por el centinela, jamás
por `settings`.** Así "Thomas no reanuda una pausa que puso Irving" deja de ser una regla que hay
que recordar y pasa a ser física: son dos mecanismos distintos y cada uno sabe quién lo puso.

**Lo que movería de nivel:**

1. **Truncar un log activo (escalón 90%) no es "resuelve y no interrumpe": es pérdida de datos
   irreversible.** Va en ACTÚA Y ME AVISA como mínimo, y con una precondición dura: si la ventana
   forense no se escribió **con éxito verificado**, no se trunca; se escala. Preservar tiene que
   ser un exit code, no una buena intención — el 24-ago casi se pierde la única evidencia.
2. **Matar un huérfano está hoy un nivel más abajo de lo que dice el encargo.** `reap-stuck` y el
   watchdog ya sueltan reclamos solos y no se lo cuentan a nadie. Separaría dos cosas que hoy van
   juntas: **soltar el reclamo** es reversible y con precedente → RESUELVE Y NO INTERRUMPE;
   **matar el PID** mata el trabajo no commiteado de esa vuelta → ACTÚA Y ME AVISA.
3. **"Media marcha" no puede escribir en la misma fila de config que tocas tú.** Si Thomas baja
   `nivel_automatizacion` o sube un umbral escribiendo `torre_config`, el panel te va a mostrar
   valores que tú no pusiste, y en dos días nadie sabe cuál era el tuyo. Tiene que ser una **capa
   de override aparte, con motivo y con vencimiento**, que el panel pinte como "puesto por Thomas,
   por X, vence en Y". Es literalmente la divergencia que ya pagamos con `eta_minutos` contra
   `eta_segundos`.
4. **El peldaño 3 (crear item de investigación) necesita el freno del auditor, o repite el bug.**
   Una cascada de errores generaría una cascada de items. El auditor ya resolvió esto con
   `auditor_fingerprint` + cooldown; Thomas debe reusar la misma firma y un tope por causa por día.
5. **"ME PREGUNTA" hoy no tiene casa para lo que no es un item.** El único canal de Thomas hacia ti
   es `estado_aprobacion='requiere_irving'` sobre un item. Una pregunta de disco no tiene item.
   O se crea un item sintético, o el chat es la bandeja — pero **una sola cola**, y me inclino por
   el item sintético con el chat como vista encima: así "lo que me está preguntando" y "lo que está
   en mi bandeja" no pueden divergir.
6. **§9 "sólo mata lo que identifique por su propio registro" no es implementable hoy.** Ese
   registro no existe (ver §8c). Es prerrequisito, no detalle: sin él, la única forma de matar es
   por nombre, y por nombre se mata la sesión de Irving.

**Una verdad de umbrales, no dos.** El disco ya tiene umbrales en `config/torre_salud.php`
(amarillo 85, rojo 93) que pinta el panel de Salud. Los escalones del encargo son 80/85/90/95. Si
se agregan aparte, el panel dirá "verde" a 82% mientras Thomas ya está actuando. Los escalones
mandan y `torre_salud` deriva de ellos.

**Trampa en la lista blanca:** "artefactos de build" suena inocuo y aquí no lo es. `public/js/app.js`
**no se commitea** (se recompila en cada entrega): borrarlo deja la app rota hasta que alguien corra
el build. Regenerables de verdad, medidos hoy: `storage/debugbar` (321 MB), `storage/framework/cache`,
los `*.log` de vuelta de más de N días (839 archivos, 4.5 MB) y los `laravel.log` de los **siete**
worktrees (1.8 GB sólo wt-2). Aparte, y no es de Thomas sino tuyo: `storage/backup_test` pesa
**4.0 GB** y no está en ninguna lista.

## 11. §11.4 — Horas y qué entregaría primero

Disco hoy: **65%** (91 G de 148 G, 50 G libres). Ningún escalón está cerca. Hay tiempo de hacerlo bien.

| # | Entrega | Horas |
|---|---|---|
| **A** | **Modo mínimo + cron propio + hombre muerto.** Thomas mide y avisa desde archivo, con su línea de cron aparte (nombre que ningún barrido de `PAUSADO-` se lleve) y una marca "midió hace X" que la Torre pinta en rojo al envejecer. La sonda ya corre cada minuto como meganet: se extiende, no se inventa. | 4–6 |
| **B** | **Fusionar el watchdog dentro de Thomas** y dejar una sola verdad de umbrales. No agrega función: evita el sexto engrane. | 4–6 |
| **C** | **Las 5 medidas que faltan** + RAM/swap + los siete `laravel.log` + serie de invocaciones en archivo (fuente real de "por hora"). | 5–7 |
| **D** | **Recursos**: preservar→comprimir→truncar→borrar, lista blanca, intocables, presupuesto, registro auditable, renglones en Configuración. | 12–16 |
| **E** | **Escalera peldaños 1–2** (aislar / media marcha) con la capa de override que vence. | 10–14 |
| **F** | **Peldaño 3**: item de investigación con evidencia adjunta, dedupe por firma, nunca a la terminal implicada. | 6–8 |
| **G** | **El chat** (§7): bandeja por nivel, "nunca desde memoria", modo mínimo, permiso propio, persistencia auditable. | 16–22 |
| | **Total** | **57–79 h** (8–10 días de trabajo) |

**Primero A, y sin discusión.** Todo lo demás vale cero si el vigilante puede morirse callado — y
hoy no es hipotético: su cron es una de las 9 líneas comentadas y lleva un día sin latir sin que
ninguna pantalla lo diga. **Segundo B**, antes de agregar una sola función nueva, justo para no
construir el sexto engrane que el encargo prohíbe. Después C → D → E → F, y **G al final**: el chat
es la cara de todo esto y no puede contar lo que Thomas todavía no sabe medir.

## 12. Lo que no toqué

Nada. Cero escrituras: sólo lectura, `--dry`, `--diagnostico` y conteos. No se tocó el crontab, ni
el freno, ni los 1.8 GB de wt-2, ni los cinco `claude` viejos — esos tres son decisión tuya y los
dejo señalados, no resueltos.
