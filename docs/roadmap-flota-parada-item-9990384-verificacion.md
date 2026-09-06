# Item #9990384 — "Flota parada" del Circuito CC: verificación de los 3 bloqueadores

**Fecha de verificación:** 2026-09-05/06 (investigación wt-2) · **Documentado por:** wt-3 (item #9990397)

## Contexto

El item #9990384 fue creado por el auditor del circuito (2026-09-05) al detectar "hambruna real":
cola en 0 y terminales libres, pero todo lo encolado estaba bloqueado por dependencias que ningún
barrido puede resolver por su cuenta (merge manual pendiente, o item "fantasma" — `completado` sin
`merge_commit`, el código nunca llegó a `main`).

El propio item fue escalado por el mecanismo DES-TRABE (Opus) como categoría `negocio`: es un aviso
de gobernanza (3 dependencias esperando criterio/acción de Irving), no algo que el circuito pueda
destrabar inventando su propio criterio. Se descompuso en el sub-item de investigación #9990395,
que a su vez generó este sub-item de cierre documental (#9990397).

## Los 3 bloqueadores originales (al crearse #9990384, 2026-09-05)

| # | Item bloqueado | Depende de | Descripción del item bloqueado |
|---|---|---|---|
| 1 | #788 | #720 | Cablear `FlotasProrrateoService` en `ClientRepository::resolveFleetSubscriptionLine` |
| 2 | #9990265 | #9990244 | Embeber `<crm-orphan-documents>` en la vista de Documentos huérfanos CRM |
| 3 | #9990329 | #9990328 | MR-06b — Frontend: portar `LeafletMap.vue` + componentes vivos a MapaRed |

## Verificación de cada bloqueador (contra BD y git reales)

### 1. #788 → #720 (SIGUE bloqueado — correcto, no tocar)

`#720` ("Flotas SaaS: línea de facturación en `calculateAmounts()`", patrón PULL) está en:
- `estado_aprobacion = aprobado_irving`
- `nivel_riesgo = C`
- `esperando_merge_irving = true`
- `merge_commit = null` (sin mergear a `main`)
- `elegibleAutoMerge(720) = false` — por diseño: los items nivel C **nunca** son elegibles para
  auto-merge, requieren que Irving lo mergee a mano.

Es el comportamiento correcto del sistema: un item de nivel C (frontera de negocio/dinero — toca
facturación) legítimamente espera en la bandeja de Irving. **No es un bug, no se toca.**

### 2. #9990265 → #9990244 (SIGUE bloqueado — mismo patrón, correcto, no tocar)

`#9990244` ("Backend: endpoint JSON + export CSV + permiso para 'Documentos huérfanos' CRM") está
en el mismo estado exacto que #720:
- `estado_aprobacion = aprobado_irving`
- `nivel_riesgo = C`
- `esperando_merge_irving = true`
- `merge_commit = null`

Mismo diseño: espera merge manual de Irving. **No se toca.**

### 3. #9990329 → #9990328 (YA NO BLOQUEADO — se resolvió solo vía el mecanismo de paraguas)

`#9990328` ("MR-06a — Backend: portar los 6 controllers Geo (grupo vivo) a MapaRed") aparecía como
**fantasma** al crearse #9990384: `completado` sin `merge_commit` propio, el código nunca llegó a
`main` directamente. Pero es un paraguas: se había descompuesto en 5 sub-items
(`origen_item_id = 9990328`) — **#9990333 a #9990337** — y los 5 verificados
`completado` **y mergeados a `main`** (confirmado con `git merge-base --is-ancestor` contra cada
`merge_commit`). El hook de cierre en cascada (`RoadmapItem.php:459-491`) completó al padre #9990328
solo, cuando el último de sus 5 hijos cerró — sin intervención humana ni del circuito.

`#9990329` (el item que dependía de #9990328) es a su vez su propio paraguas, con 4 sub-items
propios:
- #9990388, #9990389, #9990390 → `completado` y mergeados a `main`.
- #9990391 → también un paraguas (de #9990392), que al momento de la investigación estaba
  `en_progreso` activamente trabajándose por otra terminal.

Conclusión: la cadena `#9990329 → #9990328` **ya no está bloqueada por falta de merge o por un
fantasma** — avanza sola por el mecanismo normal de paraguas/cascada del circuito, sin ninguna
acción adicional requerida de este item.

## Resumen

| Bloqueador | Estado a 2026-09-06 | Acción requerida |
|---|---|---|
| #788 → #720 | Sigue esperando merge manual de Irving (nivel C, diseño correcto) | Ninguna — bandeja de Irving |
| #9990265 → #9990244 | Sigue esperando merge manual de Irving (nivel C, diseño correcto) | Ninguna — bandeja de Irving |
| #9990329 → #9990328 | Resuelto — cadena de paraguas completa y mergeada, sigue avanzando sola | Ninguna |

De los 3 bloqueadores que motivaron la alerta de "flota parada" (#9990384), **2 son esperas
legítimas de decisión/merge de Irving** (nivel C, no destrababbles por el circuito por diseño) y
**1 ya se resolvió solo** por el mecanismo normal de cierre en cascada de paraguas. No hay ninguna
corrección de código pendiente derivada de esta verificación — es un reporte de estado read-only.

## Referencias

- Item raíz: #9990384 (auditor, categoría `negocio`, escalado por DES-TRABE)
- Sub-item de investigación: #9990395 (wt-2, cierre de #9990384 vía guard de paraguas)
- Este sub-item de documentación: #9990397 (wt-3)
- Cadena de paraguas verificada: #9990328 → {#9990333..#9990337} (5/5 completado+mergeado);
  #9990329 → {#9990388, #9990389, #9990390 completado+mergeado; #9990391 → #9990392 en progreso}
