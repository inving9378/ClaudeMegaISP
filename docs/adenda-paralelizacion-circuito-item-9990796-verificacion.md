# Item #9990796 — Adenda al prompt semilla: paralelización del circuito (reporte)

## Objetivo del item

Ejecutar la adenda: crear los items faltantes del motor Ventas/Talento (A1, A2, A4, A6),
reorganizar las dependencias de los items #9990775–#9990789 para que corran en olas paralelas en
vez de en cadena lineal, y declarar zonas de archivos exclusivas por item. Regla explícita:
**solo crear/reorganizar items del roadmap, sin tocar código.**

## Paso 0 — gate de confirmación

1. Trabajo solo en dev. ✅
2. `docs/reglamento-ventas-comisiones.md` — **NO existe en el repo** (confirmado de nuevo: búsqueda
   exhaustiva ya documentada en `docs/reglamento-ventas-comisiones-item-9990775-verificacion.md`,
   item #9990775). El propio Paso 0 anticipa este escenario exacto y remite al item de respuesta
   **#9990790** (`aprobado_irving`, esperando que Irving entregue el texto fuente).
3. Alcance: solo roadmap, sin código. ✅

**Decisión (registrada vía `circuito:reportar --tipo=decision`):** se procede con la reorganización
completa pese al punto 2, en vez de detener todo el item. Razón: Irving aprobó explícitamente
"Opción 1: Aprobar tal cual" para #9990796 a las 13:43:29, **diez minutos después** de haber visto
y aprobado #9990790 (13:33:42) — es decir, aprobó la adenda ya sabiendo que el reglamento seguía sin
subir. El propio patrón ya usado por #9990781 (un hermano de esta misma familia, creado antes que
la adenda) confirma que el criterio establecido es "si el reglamento no ha cerrado, sembrar solo lo
que se conoce con certeza y dejar el resto listo sin inventar" — degradar con gracia, no bloquear
todo el circuito. Los ítems que sí necesitan el contenido real del reglamento (A1 y, por herencia,
#9990782/#9990783/#9990784/#9990787) quedan con su bloqueo documentado en su propio prompt,
apuntando a #9990790 — el mismo camino que ya siguió #9990775 cuando lo tocó.

## Hallazgo antes de empezar: parte del trabajo ya estaba hecho

Los 4 items "faltantes del plan original" (9A, 9B, 9C, 15) **ya existían** al tomar este item,
creados independientemente antes que la adenda (13:29:46–47, la adenda es de 13:37:45):

| Clave adenda | Item real | Estado al tomar #9990796 |
|---|---|---|
| 9A | #9990792 — Expediente digital del colaborador | `aprobado_irving` |
| 9B | #9990793 — Manuales y cursos obligatorios (Academia) | `aprobado_revisor` |
| 9C | #9990794 — Escalafón | `aprobado_irving` |
| 15 | #9990795 — App del colaborador | `aprobado_irving` |

No se crearon de nuevo (habría duplicado el trabajo). Se verificó que sus títulos/alcance
corresponden 1:1 a lo que pedía la adenda para 9A/9B/9C/15.

## 1. IDs de los items nuevos creados

| Clave | ID | Nivel | Módulo | Zona de archivos exclusiva |
|---|---|---|---|---|
| A1 | **#9990798** | A | Ventas / Talento | `app/.../Services/Comision/` + `tests/` |
| A2 | **#9990799** | B | Ventas / Talento | `database/migrations/` |
| A4 | **#9990800** | A | Talento | `resources/js/components/firma/` |
| A6 | **#9990801** | A | Ventas / Talento | `docs/` |
| 9A | #9990792 (ya existía) | B | Talento | — |
| 9B | #9990793 (ya existía) | B | Talento | — |
| 9C | #9990794 (ya existía) | C | Talento | — |
| 15 | #9990795 (ya existía) | C | Talento | — |

Los cuatro nuevos (A1/A2/A4/A6) nacieron `pendiente_revision` vía `RoadmapIntakeService::crear()`
(candado central: quien crea no se auto-aprueba), con `nivel_riesgo_origen=interno` — cumple la
condición para que A1/A4/A6 (nivel A) puedan llegar a `aprobado_claude` por el pipeline normal.

Además, `#9990784` se **renombró** de "Generación del Convenio Individual de Comisiones desde el
expediente de Talento (módulo Plantillas)" a **"Convenio prellenado desde plantilla"** y se marcó
`origen_item_id=9990792` (hijo de 9A): la generación del Convenio es una parte del expediente, no
un item independiente.

## 2. Dependencias corregidas

| Item | `depende_de` antes | `depende_de` ahora |
|---|---|---|
| #9990781 Catálogos parametrizables | `[9990778]` | `[]` (nada) |
| #9990782 Motor de comisiones | `[9990781]` | `[9990798, 9990799, 9990781]` (A1+A2+#9990781) |
| #9990780 Motor de custodia | `[9990779]` | `[9990799]` (A2) |
| #9990778 Identidad unificada | `[9990777]` | `[9990801]` (A6) |
| #9990779 Catálogo único prospectos | `[9990778]` | `[9990778, 9990780]` |
| #9990783 Maduración | `[9990782]` | `[9990782, 9990798]` (#9990782+A1) |
| 9A Expediente (#9990792) | `[9990778]` | `[9990799, 9990800]` (A2+A4) |

Cada cambio quedó anotado en el `comentarios_claude` del item correspondiente (bloque
"REORGANIZACIÓN DE OLAS (#9990796)"), con el motivo tomado literalmente de la tabla de la adenda.

No se tocaron `title`/`description`/`prompt` de estos 7 items (fuera de la nota agregada) — la
adenda solo pidió corregir dependencias, no reescribir el alcance ya aprobado de cada uno.

## 3. Items listos para tomar en la Ola 1 — verificación de zonas

**Ola 1 (cinco frentes, cero dependencias):** A1 (#9990798) · A2 (#9990799) · A4 (#9990800) ·
A6 (#9990801) · #9990781 (catálogos).

Zonas declaradas explícitamente por la adenda (A1/A2/A4/A6) **no se traslapan entre sí**:
`app/.../Services/Comision/`+`tests/` · `database/migrations/` · `resources/js/components/firma/` ·
`docs/` — cuatro carpetas distintas, sin intersección.

**Advertencia menor (no bloqueante):** `#9990781` no tiene una "zona de archivos" declarada en la
tabla de la adenda (esa columna solo existe para A1/A2/A4/A6), pero por su propio alcance ("tablas
de catálogo con CRUD básico, permisos propios") también va a crear migraciones nuevas en
`database/migrations/`, la misma carpeta que A2. Esto **no es un traslape real**: cada item crea
archivos de migración nuevos y distintos (nombres de archivo con timestamp propio, tablas
distintas — catálogos vs. el esquema del motor), así que no hay colisión de merge esperada; se deja
anotado por transparencia, tal como pide el punto 3 del "Al terminar". Si se quiere zona 100%
exclusiva, sería cuestión de declarar la zona de `#9990781` en una vuelta futura (no se tocó su
`prompt` en este item, fuera del alcance de "corregir dependencias").

**Total: 5 items listos para Ola 1**, dependencias correctas, zonas sin traslape real.

## 4. Items que siguen sin poder entrar a la cola, con motivo exacto

- **A1 (#9990798):** puede tomarse (Ola 1, sin dependencias), pero quien lo ejecute chocará con el
  mismo bloqueo que #9990775 — el reglamento (`docs/reglamento-ventas-comisiones.md`) no existe.
  Su propio `prompt` ya lo advierte y apunta a #9990790 en vez de inventar reglas.
- **#9990782, #9990783, #9990784, #9990787** (motor de comisiones, maduración, convenio,
  Embajadores): heredan el mismo bloqueo por transitividad (dependen de A1 o de #9990782), además
  de sus propias dependencias de Ola 2/3 — no son ejecutables hoy, como ya anticipaban sus propios
  prompts ("si el reglamento no había cerrado... este item se bloquea").
- **Ola 4 (9C #9990794, #13, #14, 15 #9990795)**: requieren decisión de Irving (nivel C), tal como
  ya estaban antes de este item — sin cambio.
- **"Fuera de ola" — #9990777** (`CheckRoutePermission` lee permisos de rol): nivel C, toca el
  corazón de permisos de toda la plataforma. Confirmado que no corre en paralelo con nada que
  toque middleware/rutas — sin cambio, ya estaba así.

## Qué sigue

Cuando Irving entregue el texto real de `docs/reglamento-ventas-comisiones.md` (#9990790), A1
queda desbloqueado y arrastra en cadena a #9990782→#9990783/#9990784/#9990787. Mientras tanto, A2,
A4, A6 y #9990781 pueden ejecutarse en paralelo hoy mismo sin ningún bloqueo.
