## 2026-11-04 — Por qué #1161 a #1164 pasaron de `requiere_irving` a aprobados en 2 minutos

Solo diagnóstico, a pedido de Irving. No se cambió ningún proceso.

### Respuesta corta

`requiere_irving` no retiene un item. El cron `circuito:destrabe` revisa esa bandeja cada 4 minutos con un modelo de IA y devuelve al pool lo que considera técnico y seguro. Funcionó como está diseñado (#338, política en `autonomo`).

### Línea de tiempo (2026-11-04)

| Hora | Actor | Qué hizo | Evidencia |
|---|---|---|---|
| 14:05:58 | Sesión de Claude Code | Crea #1161 a #1164 en `requiere_irving` | `log` del item |
| 14:08:07 | `circuito:destrabe` | #1164 → `aprobado_revisor` | `log`, `comentarios_claude`, `claude-api-2026-11-04.log` |
| 14:08:09 | `circuito:destrabe` | #1163 → `aprobado_revisor` | ídem |
| 14:08:15 | `circuito:destrabe` | #1162 se queda (categoría `negocio`) | ídem |
| 14:08:18 | `circuito:destrabe` | #1161 → `aprobado_revisor` | ídem |
| 14:09:03 | `circuito:scheduler` | Despacha a las terminales wt-1 y wt-2 | `vuelta-20261104-140903-wt-*.log` |
| 14:09:29 y 14:09:35 | Hook del modelo `RoadmapItem` | Candado de cierre: pone `aprobado_irving` en #1161 y #1164 | `log`, `RoadmapItem.php:675` |
| 14:09:36 y 14:09:43 | `circuito:cerrar-vuelta` | Manda la rama a integración | `log`, `CerrarVueltaCommand.php:60` |
| 14:10:15 y 14:10:21 | `merge-runner` | Mezcla a main con `trigger: auto` | `roadmap-externo-2026-11-04.log` |

### Cadena de decisión

1. **Proceso:** crontab de `meganet`, `*/4 * * * * cron-wrap.sh circuito:destrabe --limit=5` → `DestrabeCommand` → `RevisorService::destrabar()`.
2. **Selección:** `requiere_irving`, sin freno vigente (`elegibleParaPool`), sin `en_desarrollo_humano` y sin haber pasado antes por el des-trabe. Los más nuevos primero.
3. **Decisión:** el modelo `claude-sonnet-5-5` clasifica. Con `tecnico_seguro` lo declara re-ejecutable. Leyó el comentario «Queda en su bandeja: no corre solo» y respondió que eso no lo vuelve sensible (#1161).
4. **Único control posterior:** `TorreAutomationPolicy::estadoInicial($item, 'destrabe')`. Con `nivel_automatizacion = autonomo` (techo C, sin cambios desde 2026-09-10) un nivel B pasa.
5. **Despacho y merge:** `scopeDespachable` admite `aprobado_revisor`; la integración manda A y B a la fila de merge automática y solo C espera a Irving.

### Lo que no coincide con lo escrito

- **CLAUDE.md dice que el nivel B requiere a Irving en sesión, y que el autopilot exige `confianza: alta` y `reversible: true`.** El des-trabe no pasa por el autopilot: esos dos datos no se le piden.
- **`aprobado_irving` no significa que Irving aprobó.** A las 14:09 lo escribió un hook de cierre (`RoadmapItem.php:675`) sin intervención humana. Diez sitios del código escriben ese valor, y varios son internos (parqueos y cierres).
- **`aprobado_por = destrabe(opus)` está fijo en el código,** pero el modelo que decidió fue `claude-sonnet-5-5`.
- **Dos niveles de confianza se confunden.** Una nota en el comentario no retiene nada: el modelo la lee como un dato más.

### Alcance

Items con `destrabe_reaprobado` en su log: 7, todos nivel B y de los últimos 30 días; 6 terminaron mezclados a main (#1098, #1115, #1128, #1138, #1161, #1164; #1163 se detuvo).

### Qué sí retiene un item hoy

| Mecanismo | Efecto | El botón de aprobar de Irving |
|---|---|---|
| `automatizacion_override = manual` | Todos los carriles automáticos reciben `requiere_irving` de la política | Funciona normal |
| Rótulo `[BLOCKED-…]` o `[PARKED-…]` | Queda fuera del pool | Pide quitar el freno |

Comprobado con #1168: el des-trabe lo evaluó a las 15:48 y se quedó en `requiere_irving`.

### Mi error en esta sesión

Registré los items en `requiere_irving` afirmando que no correrían solos, sin comprobarlo, y el prompt de #1164 autorizaba la corrección cuando Irving solo pidió verificar.
