# Circuito CC #911 — Verificación del aflojo `paralelo_mismo_modulo` 1→2 (Fase 6)

Consolidación final de la Fase 6 (item #917) del item #911 ("Aflojar el pre-filtro de módulo
para usar las 6 terminales"). Reúne los 4 números medidos por #9990195 (Fase 6a, métricas 1+2)
y #9990196 (Fase 6b, métricas 3+4) + una lectura directa hecha en este mismo sub-item (#9990197)
para cerrar la métrica 4, que seguía sin número porque su item de instrumentación permanente
(#9990200) está bloqueado en `requiere_irving` (anti-loop, decisión de diseño pendiente).

**Corte PRE/POST:** commit `c1ee65f1` (2026-09-03 15:53:14 -0600) — momento en que Fase 5
(#916) subió `paralelo_mismo_modulo` de 1 a 2 tras cerrar los 2 puntos ciegos del detector de
colisiones (Fases 2-4, #913/#914/#915). Ventana POST medida: ~7.01h de operación real.
**Valor efectivo hoy en `torre_config`:** `paralelo_mismo_modulo = 2`.

---

## Métrica 1 — Ocupación media (terminales trabajando a la vez)

| | Crudo | Corregido (sin items-paraguas) |
|---|---|---|
| PRE (histórico, 242 items) | avg 7.10 term / max 15 | avg **0.0043** term |
| POST (74 items, 7.01h) | avg 0.30 term / max 6 | avg **0.2956** term |

El valor "crudo" está sesgado: 99.9% del área PRE viene de 37 items "paraguas" cuyo
`completed_at` se dispara en cascada automática al cerrar sus hijos (no es trabajo real de
terminal). Filtrando esos items, la comparación válida es **0.0043 → 0.2956 terminales
promedio** — la ventana POST tiene sustancialmente más ocupación real que el promedio
histórico PRE. Fuente: #9990195 → #9990198 (PRE: #9990202, POST: #9990203).

## Métrica 2 — Timing de colisiones detectadas en vivo

Snapshot por columna (`colision_pausada_por`/`colision_pausada_at` no-nulas) tomado a las
22:58 del 2026-09-03: **0 filas** dentro de la ventana POST (#9990199).

**Discrepancia detectada y reconciliada:** la Métrica 3 (ver abajo, calculada 4 minutos
después por el log de eventos en vez de por columna) sí encontró una colisión real dentro de
la ventana POST — item #9990076, pausado a las 21:41:02, ANTES del snapshot de la Métrica 2.
Causa: `colision_pausada_por`/`colision_pausada_at` son columnas de estado **transitorio** —
se limpian cuando la pausa se resuelve — así que una consulta por snapshot puede no ver una
colisión que ya se resolvió antes de la consulta. El log de eventos (`roadmap_items.log`) es
la fuente confiable para reconstruir el historial completo; la columna solo sirve para "¿hay
una colisión activa AHORA MISMO?".

**Conclusión corregida: 1 colisión real medida en la ventana POST** (no 0). Muestra
insuficiente (n=1) para calcular promedio/rango de timing.

## Métrica 3 — Vueltas perdidas por colisión vs. ganancia de ocupación

Sobre la única colisión real de la ventana POST (#9990076, pausado por el ganador #9990063 a
las 21:41:02; 4 reclamos muertos después hasta quedar parqueado como paraguas a las 22:10:44):

- **Costo:** ~2000s (4 vueltas perdidas × ~500s histórico por vuelta; gap crudo observado
  1281s).
- **Beneficio:** ~7351s = 2.04h-terminal (delta de ocupación corregida `0.2956 − 0.0043 =
  0.2913` term × ventana POST 7.01h).
- **Resultado: el beneficio supera al costo ~3.7x–5.7x** en esta única muestra (n=1 colisión).

Fuente: #9990196 → #9990201.

## Métrica 4 — Conflictos de merge no detectados (bitácora vs. código real)

El item que iba a construir la instrumentación **permanente** de esta métrica (#9990200) sigue
atascado en `requiere_irving` (anti-loop: 3 preguntas de diseño sin resolver — cómo detectar,
dónde persistir el dato, criterio exacto bitácora/código). Ese trabajo de infraestructura queda
pendiente aparte, sin tocarlo desde aquí.

Para esta consolidación se hizo una lectura puntual y read-only sobre `main` (sin construir
nada nuevo, sin competir con #9990200):

- `git log main --since="2026-09-03 15:53:14" --oneline` → **117 merges** con el patrón
  `Integra circuito #NNNN` en la ventana POST.
- De esos, **6 tuvieron conflicto de merge real** (detectado por git al integrar, resuelto de
  forma manual/explícita antes de completar el merge): items #880, #859, #852, #843, #753 y
  MR-32 (#971). **Los 6 fueron por choque en `CLAUDE.md`** (bitácora compartida que muchas
  sesiones en paralelo appendean al mismo tiempo) — **ninguno en código real** (`app/`,
  `resources/`, `routes/`, `database/`, `config/`).
- `git grep` de marcadores de conflicto sin resolver (`<<<<<<<`, `=======`, `>>>>>>>`) sobre el
  HEAD actual de todo el árbol versionado: **0 coincidencias**.

**Conclusión: 0 conflictos de merge no detectados en código real** dentro de la ventana POST.
Los únicos conflictos que ocurrieron fueron en bitácora, los detectó git de forma normal (no
fueron silenciosos) y se resolvieron antes de completar el merge — cero corrupción de código.

---

## Conclusión final — recomendación explícita

| Métrica | PRE / sin aflojo | POST / con aflojo (paralelo=2) |
|---|---|---|
| 1. Ocupación media (corregida) | 0.0043 term | 0.2956 term (+~68x) |
| 2. Colisiones reales detectadas | — | 1 (en 7.01h / 117 merges) |
| 3. Costo vs. beneficio de la única colisión | — | beneficio gana 3.7x–5.7x |
| 4. Conflictos de merge no detectados en código | — | 0 |

El aflojo cumplió lo que #911 buscaba: más terminales trabajando a la vez (ganancia de
ocupación de casi dos órdenes de magnitud sobre el promedio corregido), con un costo real muy
bajo — una sola colisión en 117 merges de la ventana medida, cuyo costo en vueltas perdidas
queda ampliamente cubierto por la ganancia de ocupación. Los conflictos de merge que sí
aparecieron fueron exclusivamente de bitácora (`CLAUDE.md`), git los detectó y se resolvieron
sin tocar código; no hubo ninguna corrupción silenciosa.

**Recomendación: MANTENER `paralelo_mismo_modulo` en 2.** No se encontró ningún número
preocupante que justifique revertir a 1. Tampoco se recomienda subirla más todavía: la muestra
de colisiones reales es de n=1, insuficiente para proyectar con confianza qué pasaría con un
paralelismo mayor; conviene acumular más horas de operación en 2 antes de evaluar subir de
nuevo.

Este documento **recomienda, no cambia la perilla**. Si Irving decide actuar sobre la
recomendación (mantenerla en 2, o revisar más adelante con más muestra), es una decisión aparte
— fuera de alcance de este sub-item.

---

## Fuentes

- Item padre: #911. Fases previas: #912 (inventario), #913 (footprint en vivo, ciego 1), #914
  (aviso temprano de colisión, protocolo del ejecutor), #915 (candado de esquema entre
  worktrees, ciego 2), #916 (aflojar la perilla a 2).
- Fase 6 (este documento): #917 (paraguas) → #9990195 (6a, métricas 1+2) → #9990198 (métrica
  1, con #9990202 PRE / #9990203 POST) y #9990199 (métrica 2); #9990196 (6b, métricas 3+4) →
  #9990201 (métrica 3) y #9990200 (métrica 4, instrumentación permanente — pendiente,
  `requiere_irving`); #9990197 (6c, este documento — consolida + resuelve el número de la
  métrica 4 por lectura puntual).
- Comandos usados en esta consolidación: `git log --since` sobre `main`, `git grep` de
  marcadores de conflicto sobre el HEAD actual, lectura de `comentarios_claude`/`log` de los
  items anteriores vía `php artisan tinker`.
