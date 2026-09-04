# Inventario de items aprobados parqueados y propuesta de destape priorizado (item #9990062)

Medición contra la BD de dev, 2026-09-04. Alcance: `estado_aprobacion IN (aprobado_claude,
aprobado_revisor, aprobado_irving)` AND `archivado_at IS NULL`. **146 items** con
`excluir_pool_automatico=1` (fuera del pool automático), sobre un total no filtrado por ese flag.

Este item es de **solo lectura** — no cambió ningún `excluir_pool_automatico` ajeno. Todo lo de
abajo es inventario + propuesta para que Irving decida.

## 1) Tabla resumen — por qué está parqueado cada grupo (146 = suma exacta)

| Categoría (mutuamente excluyente, en este orden de prioridad) | Cantidad | Qué significa |
|---|---|---|
| Hijo de un paraguas (`origen_item_id` set) | 90 | Item nacido de una descomposición (`circuito:sub-item`). El padre lo tiene fuera del pool hasta que el propio item cierre; el hook de cierre en cascada completa al padre solo. Patrón correcto, extensamente documentado en CLAUDE.md (familia "bucle reap sobre paraguas ya descompuesto": #738/#745/#830/#816/#818/#848/#905/#878/#906/#907/#924…). |
| Esperando merge de Irving (`esperando_merge_irving=1`) | 28 | Nivel C con rama propia y trabajo terminado, pero el merge a `main` requiere que Irving lo revise a mano (por diseño — nivel C nunca se auto-mergea). |
| Bloqueado por bucle anti-escalación (`bloqueado_por_bucle=1`) | 15 | 3+ escalaciones seguidas con la MISMA causa sin cambio material. El propio mecanismo anti-bucle lo sacó del pool porque insistir no iba a cambiar nada sin que Irving decida. |
| Otro motivo explícito (`motivo_bloqueo` puntual) | 5 | Ver detalle en la sección 3 — 2 son banderas de prueba obsoletas, 1 es dependencia de hardware físico, 2 son etiquetas del clasificador que su propio texto dice "no frena el despacho". |
| **Sin ninguna razón registrada Y sin hijos abiertos** | **2** | `#227` y `#231` — ver sección 3. Genuina laguna de bookkeeping: excluidos sin que quede escrito por qué. |
| Sin razón registrada pero SÍ son paraguas con hijos abiertos | 6 | `#663`, `#664`, `#936`, `#185`, `#904`, `#191` — el "sin razón" es solo que el flag no lleva `motivo_bloqueo` de texto, pero el estado es correcto (paraguas con trabajo real pendiente en sus hijos). |

Distribución por módulo (top 8 de 24 módulos, por volumen de items parqueados):

| Módulo | Parqueados |
|---|---|
| Mapa de Red | 30 |
| Roadmap / Circuito CC | 21 |
| DocumentacionCorporativa | 14 |
| Core / Permisos | 12 |
| Finanzas | 8 |
| Flotas | 8 |
| Infra | 7 |
| MegaFamilia | 7 |

Distribución por nivel de riesgo (de los 146): **C=69, B=70, A=4, sin clasificar=3**.

## 2) Los "candidatos nivel A" en sentido estricto: prácticamente no hay ninguno fuera de la épica congelada

El criterio literal del item (nivel_riesgo=A, sin dependencia bloqueante viva) da un resultado casi
vacío, y eso en sí mismo es un hallazgo:

- De los 146 parqueados, solo **4 son nivel A**.
- 3 de esos 4 son hijos de la épica **MAPA DE RED** (#936: `#943` MR-07, `#969` MR-31, `#938` MR-02)
  — **excluidos del top 10 a propósito**, tal como pide el spec del item: están congelados por
  diseño (regla D31) y los destapa el liberador en cascada `#971`, no un destape manual.
- El único nivel A restante (`#663`, "DocumentaciónCorporativa — Fase 1") **sí tiene una
  dependencia bloqueante viva**: su hijo `#732` sigue abierto (`aprobado_irving`). Está parqueado
  correctamente — no califica como candidato porque el propio criterio del spec lo excluye
  ("sin dependencia bloqueante viva").

**Conclusión de la sección 2: el pool nivel-A ya está bien gobernado.** No hay ningún item A
parqueado sin justificación real. El problema de fondo no es "hay candidatos A escondidos", es
que casi todo lo parqueado es B/C (139 de 146) — y B/C por política requieren sesión con Irving o
decisión de diseño, así que no puede destaparse "de inmediato sin decisión de Irving" (eso responde
también el punto 2 del spec).

## 3) Top candidatos a destapar — más allá del filtro estricto de nivel A

Dado que el filtro A-puro casi no arroja nada, la propuesta útil está en un lugar distinto: items
donde **el propio texto registrado dice que la razón del bloqueo ya no aplica**, independientemente
de su nivel de riesgo. Destapar el flag `excluir_pool_automatico` en estos casos es seguro y
aditivo porque **no salta la política de nivel** — un B sigue necesitando sesión con Irving y un C
sigue necesitando su merge manual; quitar el flag solo los regresa a la bandeja normal de triaje,
no los ejecuta.

### 3a. Candidatos SEGUROS de destapar de inmediato (4)

| # | Módulo/Nivel | Por qué es seguro destaparlo |
|---|---|---|
| **#226** | Infra / B | El clasificador (Opus) puso `[PARKED-PROD]` el 2026-08-26 con el texto **"Es un CONSEJO: no frena el despacho"** — pero el master switch quedó en 1 de todos modos. Irving ya lo aprobó (`aprobado_irving`, 2026-08-28). El propio registro contradice el estado actual: el flag está huérfano. |
| **#232** | Roadmap/Circuito CC / C | Mismo patrón que #226: clasificador puso `[BLOCKED-NEGOCIO]` con **"no frena el despacho"**, pero el flag quedó en 1. Más fuerte que #226: Irving no solo aprobó — **ya respondió las 3 preguntas de diseño** (`q1/q2/q3` en el log del 2026-08-28) que un nivel C necesitaría para avanzar. La decisión de diseño YA está tomada; solo falta que el flag deje de bloquear el despacho. |
| **#126** | Core/Permisos / sin clasificar | Excluido el 2026-08-25 durante una prueba supervisada de `#191` ("Quitar la bandera al cerrar la prueba" — así lo dice el propio log). Irving re-aprobó el item ese mismo día por la tarde (`aprobado_irving`, 16:51). Han pasado 9 días; la prueba cerró. Es además un bug real de UI reportado por Irving (modal se queda gris al aplicar pago / al abrir permisos) con diagnóstico ya hecho, sin fix aplicado — vale la pena que un terminal lo retome. |
| **#81** | CRM / sin clasificar | Mismo flag de prueba obsoleta que #126 (mismo incidente, mismo texto, misma fecha), re-aprobado por Irving el 2026-08-25 17:39. ⚠️ Diferencia importante: el contenido del propio item trae SU PROPIA frontera dura sin resolver — borrar 742 filas huérfanas de `document_crms` estaba detenido esperando que Irving decida entre refrescar el backup (`backup_db:process`, el cron diario llevaba ~12 días caído en esa fecha) o proceder sin refrescarlo. Destapar el flag de pool es seguro (no ejecuta el borrado), pero el trabajo real se va a topar de inmediato con esa misma pregunta y debería volver a escalar — no es "libre de fricción", es "libre del bug de bookkeeping". |

### 3b. Candidatos con laguna de bookkeeping — piden que Irving los complete, no que se destapen a ciegas (2)

| # | Módulo/Nivel | Qué falta |
|---|---|---|
| **#227** | Infra / B | "Exentar del blocklist DOS del MikroTik el tráfico a la IP pública de DEV" — toca un dispositivo de red en vivo. Excluido sin `motivo_bloqueo` ni hijos abiertos: no hay registro de por qué. Dado que el contenido SÍ es sensible (red en vivo), lo más probable es que haya sido una exclusión manual sin documentar en el momento de aprobarlo, no un error del sistema. Recomendación: Irving decide en sesión (nivel B lo exige de todos modos) y, al hacerlo, dejar el `motivo_bloqueo` escrito para que no vuelva a aparecer como "misterioso" en el próximo inventario. |
| **#231** | Roadmap/Circuito CC / B | "Podar los worktrees muertos: 3 no se pueden mergear y 5 llevan +700 commits de atraso" — implica borrar ramas/worktrees, difícil de revertir. Mismo patrón que #227: sin `motivo_bloqueo`, sin hijos, aprobado por Irving el 2026-08-28 sin comentario. Recomendación: sesión con Irving (nivel B) para confirmar cuáles worktrees son seguros de podar antes de destapar. |

### 3c. Hallazgo relacionado, fuera de alcance de este item: la épica MAPA DE RED sigue congelada porque su propio liberador nunca se mergeó

`#971` ("Liberador en cascada acotado para la épica MAPA DE RED") se marcó `completado` el
2026-09-03, pero **su `merge_commit` está vacío** — el comando `circuito:liberar-cascada-mapa-red`
que debía destapar `MR-02..MR-07` automáticamente cada 10 minutos vive solo en la rama
`circuito/item-971-liberador-cascada-mapa-red` (commit `b8488367`), no en `main`. Por eso los 3
items nivel-A de la épica (`#938`, `#943`, `#969`) y el resto de sus 27 hermanos siguen parqueados:
el mecanismo que los iba a soltar no está corriendo todavía. Esto es exactamente el tipo de caso que
cubre el item hermano **`#9990061`** ("Auditar 13 completados sin mergear y diagnosticar auto-merge
de Jarvis", creado en el mismo lote que este item) — no se duplica aquí, solo se deja anotado como
contexto de por qué el bloque más grande (30 de 146 parqueados) sigue sin moverse.

## 4) Los que requieren decisión explícita de Irving antes de destapar (con motivo concreto)

- **69 items nivel C** — por política, un nivel C nunca se auto-mergea ni se auto-aprueba; siempre
  necesita una decisión de diseño de Irving en algún punto de su ciclo (28 de ellos ya están
  literalmente "esperando merge de Irving").
- **70 items nivel B** — por política, requieren sesión con Irving confirmando antes de ejecutarse;
  destaparlos del pool no los haría auto-ejecutables, pero tampoco tiene sentido destaparlos sin que
  Irving los vaya a tomar en una sesión.
- **15 items bloqueados por bucle anti-escalación** (`bloqueado_por_bucle=1`) — por diseño, 3+
  escalaciones con la misma causa sin cambio material significan que insistir no sirve; necesitan
  que Irving rompa el empate con una decisión (ver la lista completa por módulo en la sección 1;
  ejemplos concretos: `#691` Finanzas — "Frontera dura de dinero/legal: exige autorización legal
  explícita"; `#726` Finanzas — depende de que `#725` corte la escritura primaria a `invoices` en 5
  subsistemas antes de poder avanzar; `#753` Inventario — nivel C con trabajo terminado, solo
  esperando el merge).
- **`#82`** (Flotas/B) — bloqueado a propósito hasta que Irving conecte el hardware GPS Ruptela
  físico y avise; no hay forma de verificar sin el hardware real, correctamente parqueado.
- **`#227` y `#231`** — ver sección 3b: requieren que Irving tome la decisión de fondo (qué exentar
  del MikroTik / qué worktrees podar) antes de destapar, aunque la EXCLUSIÓN en sí no tenga un
  motivo escrito.
- **30 items de la épica MAPA DE RED (`#936` y sus hijos)** — congelados a propósito por diseño
  (regla D31). No se destapan a mano; los libera `#971` en cuanto su rama se mergee a `main` (ver
  sección 3c, y el item `#9990061` para el diagnóstico de por qué no se ha mergeado).

## Resumen ejecutivo (para el reporte del item)

- 146 items aprobados están fuera del pool automático. El 95% (139) son B/C y requieren a Irving por
  política — no es un bug, es el diseño funcionando.
- El pool nivel-A ya está limpio: solo 4 items A parqueados, 3 son la épica congelada a propósito y
  el cuarto tiene una dependencia real y viva.
- El hallazgo accionable real: **4 items (`#226`, `#232`, `#126`, `#81`) tienen el flag de exclusión
  obsoleto** — su propio registro dice que la razón ya no aplica (prueba cerrada hace 9 días,
  clasificador que dice explícitamente "no frena el despacho", o decisión de diseño ya tomada) y en
  3 de los 4 casos Irving ya volvió a aprobar el item después de esa razón. Son los primeros
  candidatos a que Irving limpie con un `excluir_pool_automatico=0` explícito.
- 2 items (`#227`, `#231`) tienen una laguna de bookkeeping — excluidos sin motivo escrito — que vale
  la pena que Irving cierre con un comentario la próxima vez que los revise, aunque su contenido
  (red en vivo / borrado de ramas) justifica que sigan esperando su decisión.
- El bloque más grande (30 items, la épica Mapa de Red) no se mueve porque su propio mecanismo de
  destape (`#971`) no está mergeado a `main` todavía — issue cubierto por el item hermano `#9990061`.
