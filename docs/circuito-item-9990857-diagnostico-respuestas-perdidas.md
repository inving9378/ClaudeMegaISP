# CIRC-02a Fase 2 — Diagnóstico final: respuestas de Irving perdidas / `comentarios_claude` sobreescrito

Generado: 2026-09-12 (item #9990873, worker wt-3). Diagnóstico SOLO LECTURA — no se tocó BD ni
código fuera de este archivo (y del cierre de los items #9990857/#9990872/#9990873 vía los
comandos normales del circuito). Depende de la Fase 1 (#9990872, `docs/circuito-item-9990857-fase1-datos.md`),
usada como insumo sin re-derivarla.

## Resumen ejecutivo (las 3 cifras pedidas por el prompt original de #9990857)

**No hay ningún item de Irving parado esperando por una respuesta que el sistema haya ignorado.**

- **Items en grupo (b) "siguen parados"**: **0**. La Fase 1 ya lo estableció con precisión (191
  candidatos totales, 191 en grupo (a) avanzaron, 0 en grupo (b)) sobre el universo completo de
  1573 items con log no vacío. Esta Fase 2 no repite ese cómputo — lo usa como insumo.
- **Tiempo promedio parado / caso más viejo**: **no aplica** (0 casos). No hay "trabajo bloqueado
  detrás" de ningún comentario ignorado.
- **Caso cercano (near-miss), para que quede constancia**: #9990853 ("corrección del Circuito CC —
  para desatorar el flujo") SÍ tiene un comentario humano (`irving:admin`, 2026-09-11 17:58) y hoy
  sigue en `requiere_irving`, pero el patrón es DISTINTO al que busca este diagnóstico: el
  comentario **fue reprocesado** (una vuelta lo tomó después) y **el reintento falló por su propia
  causa** (worker murió/timeout, `reaper` lo reencoló) — no es un "comentario ignorado, nunca
  vuelto a ver". Es el objeto de otro trabajo (estabilidad del reaper/scheduler), no de CIRC-02.

Adicional a lo pedido: se investigó también el punto 4 del método del item — **sobreescritura de
`comentarios_claude`** — con hallazgo relevante (ver sección siguiente): **hay 3 puntos del código
que SÍ reemplazan por completo el campo en vez de acumularlo**, pero, verificado contra la
historia real, **ninguno ha causado hoy una pérdida irreversible de una respuesta genuina de
Irving**. El riesgo es de código (estructural), no un hecho consumado.

## Grupo (b) — SIGUEN PARADOS (tabla completa, heredada de la Fase 1)

*(vacía — 0 filas, ver Fase 1 §"Resultado")*

| Item | Comentario humano (ts / por / vía) | Días parado | Texto/resumen del comentario | Sub-items generados |
|---|---|---:|---|---|
| — | — | — | — | — |

No hay nada que tabular: la Fase 1 ya clasificó los 191 candidatos con comentario humano posterior
a una escalada `requiere_irving`, y los 191 avanzaron. No existe ningún item cuyo comentario haya
quedado sin efecto.

## Sobreescritura de `comentarios_claude` — método, hallazgo y conclusión

### Paso 1-2 del método: ¿existe un historial de versiones de la columna?

**No.** Verificado directamente:

- `RoadmapItem` (`app/Modules/Addons/Roadmap/Models/RoadmapItem.php`) **NO** usa el trait
  `Spatie\Activitylog\Traits\LogsActivity` ni ningún otro mecanismo de auditoría de columnas —
  extiende `Illuminate\Database\Eloquent\Model` a secas.
- La tabla `activity_log` (del paquete Spatie Activitylog, sí instalado y usado por **otros**
  modelos del sistema — `Client`, `CrmMainInformation`, etc.) existe en la BD con **227,388** filas,
  pero **0** corresponden a `subject_type` de Roadmap. Confirmado por query directa.

**Conclusión del paso 2: no hay forma de recuperar byte a byte un valor anterior de
`comentarios_claude` con el mecanismo estándar del sistema.** Si algo se sobreescribió y no dejó
copia en otro campo o en `log`, es irrecuperable — se puede CONTAR el hecho, no reconstruir el
contenido (tal como pide el método).

*(Nota, fuera de alcance de esta fase: existen respaldos diarios `mysqldump` de la BD completa en
`/var/backups/mysql/` con 14 días de retención — ver CLAUDE.md, sección "Backup de base de
datos". Teóricamente un valor perdido HACE MENOS DE 14 DÍAS podría reconstruirse cargando un dump
viejo en una BD desechable y comparando. No se hizo aquí: es una operación pesada, fuera del
alcance "solo lectura ligera" de este diagnóstico, y — como se ve abajo — no se encontró ningún
caso real que la justificara.)*

### Paso 3: grep de los puntos del código que escriben `comentarios_claude`

Se localizaron **todas** las asignaciones a `comentarios_claude` en `app/Modules/Addons/Roadmap/`.
La inmensa mayoría **acumula** (`.=` con un "sello" con timestamp — el mismo patrón que se ve en
el propio `comentarios_claude` de este item #9990873, que conserva TRIAJE + REVISOR + DES-TRABE
como bloques separados con `---`). Pero hay **3 sitios que REEMPLAZAN por completo** el campo:

| # | Archivo:línea | Disparador | ¿Deja copia en otro lado? |
|---|---|---|---|
| 1 | `Controllers/RoadmapController.php:1762` (método de decisión — botones Aprobar/Rechazar/Cerrar/Cancelar/Comentar de la Torre) | Irving manda un `comentario` junto con su decisión | **Sí** — el mismo comentario se escribe también en `log[]` (línea 1776, llave `comentario`), append-only. No se pierde aunque `comentarios_claude` se reemplace. |
| 2 | `Controllers/RoadmapController.php:1927` (`seguimiento()` — crear sub-item y cerrar el origen en el mismo acto) | Irving cierra el item origen con un comentario al crear un seguimiento | **Sí** — mismo patrón, se duplica en `log[]` (línea 1930). |
| 3 | `Services/RoadmapCircuitoService.php:1182` (`applyWrite()`, usado por **toda** la API externa `roadmap-externo` que consume Claude Cowork: `setItemPath`, `setItemPathB64`, `set` por query) | Cowork escribe `comentarios_claude` vía `$item->update($data)` (mass-assignment directo) | **No.** `applyWrite()` no toca `log[]` en absoluto — un reemplazo por esta vía no deja NINGÚN rastro dentro del item. Solo queda una línea en el archivo de texto `storage/logs/roadmap-externo-*.log` (canal `roadmap_externo`, fuera de la BD, sujeto a rotación). |

**Los sitios 1 y 2 están, en la práctica, protegidos por el `log[]` append-only** (aunque
`comentarios_claude` se reemplace, el texto sigue disponible ahí). **El sitio 3 es el único
genuinamente sin red de seguridad** — si algún día Cowork sobreescribe con un comentario real y
nadie mira el archivo de log de esa fecha antes de que rote, ese contenido se pierde para siempre.

### Paso 4: comparación empírica contra la historia real (¿ya pasó?)

Script reproducible (`/tmp/circ02a_fase2.php`, íntegro al final de este documento), corrido contra
**todo** el universo de items con log no vacío (1589 al momento de correr — la Fase 1 midió 1573
horas antes; la diferencia son items nuevos creados entre ambas corridas, incluido el propio
#9990873/#9990872).

**Método:** reutiliza la MISMA taxonomía de actores humano/automático de la Fase 1 (para no confundir
notas automáticas que también usan la llave `comentario` — ver hallazgo abajo — con comentarios
humanos reales). Para cada entrada de `log` de un actor **humano**, si trae una llave `comentario`
no vacía, se busca un fragmento normalizado de ese texto (primeros 40 caracteres, espacios
colapsados) dentro del `comentarios_claude` ACTUAL del item. Si el fragmento del comentario más
RECIENTE no aparece → sospechoso de sobreescritura total (algo lo pisó después). Si aparece el más
reciente pero uno anterior no → sobreescritura parcial (esperable mecánicamente: cada reemplazo
solo puede reflejar el último).

**Resultado bruto:** de 1589 items, solo **5** tienen alguna entrada de log con comentario de un
actor humano (el resto de "comentario humano" que aparecía en logs es, en realidad, ruido de
namespaces automáticos como `consola:circuito:integrar` o `circuito:despacho`, que también usan
la llave `comentario` para notas internas — excluidos correctamente por la taxonomía). De esos 5,
**4 marcaron como "sospechoso"** (el fragmento no aparece en `comentarios_claude` hoy) y 1 quedó
intacto.

**Se investigó cada uno de los 4 a mano — CONCLUSIÓN: los 4 son falsos positivos, no pérdidas reales:**

| Item | Comentario detectado | Causa real (verificada) |
|---|---|---|
| #156 | `"destrabe-forzado-irving"` | **No es un comentario libre de Irving.** Es un marcador técnico que escribe automáticamente el hook `RoadmapItem::saving()` (línea ~697) cada vez que cambian las banderas de bloqueo (`motivo_bloqueo`), reusando la llave `comentario` del log para auditoría de flags — no para narrativa humana. Se atribuye a `irving:admin` porque fue SU acción la que disparó el cambio de bandera, no porque él haya escrito ese texto. |
| #9990713 | `"destrabe-forzado-irving"` | Mismo mecanismo que #156. |
| #648 | `"no se ve nada en esa pantalla esta en negro"` | Comentario real de Irving, pero via `RoadmapController::validacionReportar()` (línea 2728, botón "⚠ Reportar problema" de la validación visual) — ese método **guarda el texto en la columna dedicada `comentario_validacion`**, no en `comentarios_claude` (son campos con propósitos distintos a propósito). Verificado: `comentario_validacion` del item #648 hoy contiene el texto completo, intacto. **Nada se perdió** — mi heurística buscó en el campo equivocado. |
| #662 | `"esta el modulo de documentacion corporativa pero esta como en fichas..."` | Mismo mecanismo que #648 — `comentario_validacion` de #662 conserva el texto completo hoy. **Nada se perdió.** |

**Sobreescritura parcial:** 0 casos.

**Conclusión del paso 4: 0 pérdidas reales confirmadas** en la historia completa del roadmap hasta
hoy. Los 3 puntos de reemplazo de código (paso 3) son un riesgo estructural real, pero:
- Los sitios 1 y 2 (`decidir()`, `seguimiento()`) nunca han perdido nada porque el `log[]` ya
  guarda copia — y en la práctica Irving casi nunca manda `comentario` libre por esa vía (responde
  la mayoría de las veces con `respuestas` a preguntas estructuradas, no texto libre).
- El sitio 3 (API externa de Cowork, `applyWrite`) es el único sin red de seguridad, pero se
  revisaron los **19 archivos** `storage/logs/roadmap-externo-*.log` disponibles (desde
  2026-07-14 hasta hoy) y **ninguno registra jamás una escritura con el campo `comentarios_claude`**
  — de hecho el canal completo solo tiene **3** líneas de auditoría en total, y las 3 son de
  pruebas (`"verb":"TEST"`). Es decir: la vía de riesgo existe en el código, pero **empíricamente
  nunca se ha usado** para escribir `comentarios_claude` en la ventana de logs disponible.

## Recomendación para una eventual Fase 3 (NO ejecutada aquí — decisión de Irving en q4: "reportar
en Fase 2, remediar en Fase 3")

Dado que no hay ningún item con contenido que restaurar (0 pérdidas reales), una Fase 3 futura no
necesitaría un "plan de restauración por item" — se reduce a **cerrar el riesgo estructural antes
de que produzca una pérdida real**:

1. `RoadmapController.php:1762` y `:1927` — cambiar `$item->comentarios_claude = $data['comentario'];`
   por un `.=` con el mismo patrón de "sello" con timestamp que usa el resto del código
   (`RevisorService`, `MergeRunner`, etc.), para que un comentario de Irving conviva con el
   historial narrativo en vez de borrarlo.
2. `RoadmapCircuitoService::applyWrite()` (línea 1182) — cuando `$data` trae `comentarios_claude`,
   no hacer `$item->update($data)` a secas: separar ese campo y aplicarlo con el mismo patrón de
   acumulación, y opcionalmente anotar la escritura en `log[]` (hoy no dejaba ningún rastro dentro
   del item — el único punto de los 3 sin red de seguridad).

No se crea el sub-item de Fase 3 en esta vuelta (el spec del item pide explícitamente "NO
re-encolar nada"); queda documentado aquí para quien retome CIRC-02.

## Snippet reproducible (íntegro)

```php
<?php
// Ver /tmp/circ02a_fase2.php en el momento de esta corrida (2026-09-12, worker wt-3).
// Reutiliza la taxonomía de actores humano/automatico de la Fase 1 (#9990872).

$HUMANO_EXACTO = [
    'irving:admin', 'irving:CARLOS', 'irving:Irving', 'irving:david_marsal',
    'irving:admin (via claude-code)', 'irving:admin (dictado a claude-code)',
    'irving:pedido-directo', 'irving:re-sello', 'irving:merge',
    'david:relay-irving', 'david_marsal',
    'claude-code (por instruccion de irving:admin)',
    'claude-code (por instruccion explicita de irving:admin)',
    'claude-code (sesion supervisada, por instruccion de irving:admin)',
];
$AUTOMATICO_EXACTO = [
    'jarvis-mecanico', 'merge-runner', 'paraguas', 'thomas-mecanico', 'limite_cuenta',
    'consola:tinker', 'reaper-rapido', 'valvula:contexto', 'revisor:backlog', 'soltar-claim',
    'jarvis:verificarCierre', 'thomas:verificarCierre', 'timeout', 'timeout:reanudado',
    'jarvis-ya-decidido', 'colision-check', 'destrabe(opus)', 'autopilot', 'auditor',
    'revisor:triaje-null', 'valvula:nacimiento', 'claude-code',
];
$AUTOMATICO_PREFIJOS = [
    'consola:circuito:', 'circuito:', 'wt-', 'jarvis:', 'jarvis-', 'thomas:', 'thomas-',
    'revisor:', 'valvula:', 'consola:',
];
$AUTOMATICO_EXTRA_EXACTO = ['jarvis', 'thomas', 'barrido', 'reaper'];

function classify(string $por, array $HUMANO_EXACTO, array $AUTOMATICO_EXACTO, array $AUTOMATICO_PREFIJOS, array $AUTOMATICO_EXTRA_EXACTO): string {
    if (in_array($por, $HUMANO_EXACTO, true)) return 'humano';
    if (in_array($por, $AUTOMATICO_EXACTO, true)) return 'automatico';
    if (in_array($por, $AUTOMATICO_EXTRA_EXACTO, true)) return 'automatico';
    if (str_starts_with($por, 'irving:')) return 'humano';
    if (preg_match('/^claude-code\s*\(.*(instruccion|dictado).*irving.*\)$/i', $por) || preg_match('/^claude-code\s*\(.*irving.*(instruccion|dictado).*\)$/i', $por)) return 'humano';
    if (preg_match('/^claude-code\s*\(/i', $por) || preg_match('/^Claude Code\s*\(/', $por)) return 'ambiguo';
    if (preg_match('/^claude-code:/i', $por)) return 'automatico';
    foreach ($AUTOMATICO_PREFIJOS as $pref) {
        if (str_starts_with($por, $pref)) return 'automatico';
    }
    return 'ambiguo';
}

function norm(string $s): string { return trim(preg_replace('/\s+/', ' ', $s)); }
function fragmento(string $texto, int $len = 40): string { return mb_substr(norm($texto), 0, $len); }

$items = \App\Modules\Addons\Roadmap\Models\RoadmapItem::whereNotNull('log')
    ->get(['id', 'title', 'estado_aprobacion', 'log', 'comentarios_claude', 'origen_item_id']);

$conComentarios = [];
foreach ($items as $it) {
    $log = (array) $it->log;
    $entradas = [];
    foreach ($log as $idx => $e) {
        if (!is_array($e)) continue;
        $por = (string) ($e['por'] ?? '');
        if ($por === '') continue;
        if (classify($por, $HUMANO_EXACTO, $AUTOMATICO_EXACTO, $AUTOMATICO_PREFIJOS, $AUTOMATICO_EXTRA_EXACTO) !== 'humano') continue;
        $c = trim((string) ($e['comentario'] ?? ''));
        if ($c === '' || mb_strlen($c) < 3) continue;
        $entradas[] = ['idx' => $idx, 'ts' => $e['ts'] ?? null, 'por' => $por, 'texto' => $c];
    }
    if (!empty($entradas)) $conComentarios[] = ['item' => $it, 'entradas' => $entradas];
}

// Clasificación intacto / parcial / total según si el fragmento de cada entrada aparece
// (normalizado, case-insensitive) dentro de comentarios_claude ACTUAL del item.
// (ver cuerpo completo corrido el 2026-09-12 en /tmp/circ02a_fase2.php)
```

## Cierre

Junto con este archivo se cierran, vía los comandos normales del circuito:
- **#9990872** (Fase 1) — ya estaba `completado` desde antes.
- **#9990873** (Fase 2, este item) — pasa a `completado`.
- **#9990857** (paraguas/padre) — pasa a `completado` en el mismo acto, tal como pide el punto 5
  del método: no hay UI para este diagnóstico, el "producto" es este documento.
