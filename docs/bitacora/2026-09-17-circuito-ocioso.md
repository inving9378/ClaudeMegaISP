## 2026-09-17 14:49 — Por qué el circuito no está trabajando (diagnóstico read-only) + maqueta Panorama en Árbol recibida


**El circuito no está roto ni pausado**: `circuito:flags` = `pausado=0`, cron activo, 18 vueltas hoy (la última cerró #9991217 a las 14:13). Las 6 terminales están ociosas porque **la cola despachable está represada detrás de decisiones que solo tú puedes tomar**.

### La cadena, medida
- `scopeDespachable()` devuelve **31 items**. Los 31 tienen `depende_de` con al menos una dependencia abierta → `DependenciaGate` los rechaza a todos (`storage/logs/circuito-despacho-2026-09-17.log`: 5,197 líneas, todas `dependencia_sin_cerrar`). **0 items libres.**
- Las dependencias abiertas se remontan a **3 familias** de bloqueo, todas en tu cancha:

**A) 41 items nivel C TERMINADOS esperando tu merge** (`esperando_merge_irving=1`). Regla del modelo (`RoadmapItem::saving`, bloque 1): un nivel C con rama que intenta cerrar sin `merge_commit` se parquea a `aprobado_irving` + fuera del pool hasta que TÚ integres. Verifiqué las 40 ramas con `git merge-tree` contra `main`: **0 conflictos en todas**. Las 10 que destraban algo:

| # | commits | destraba (depende_de) | libera colisión |
|---|---|---|---|
| #9990536 MR-08 2a CatalogosController | 1 | #9990538 | #9990539, #9990455 |
| #9990555 MR-23 4b-i ImpactoAnalysisService | 3 | #9990556 | #9990559 → #9990560 → #9990561 |
| #9990504 MR-08 Fase 2 (paraguas) | 1 (docs) | #9990534 | — |
| #9991196 Vendedores 2a backend | 2 | #9991197, #9991198 | — |
| #9991194 Vendedores Fase 2 backend | 1 | #9991193, #9991195 | — |
| #9991141 F7a permiso releases.emitir | 2 | #9991142 | — |
| #9990674 F3 ReleasePublisher | 2 | #9991123 | — |
| #9990808 Motor de ventas catálogos | 3 | #9990809 | — |
| #9990767 Fase 4c-i matriz permisos | 1 | #9990768 | — |
| #9990607 Fase 3 pago → TalentoLedgerEntry | 1 | #9990612 | — |

Las otras 31 (VoIP, CIRC-05, agujeros de rutas, seguimientos…) no frenan a nadie hoy, pero son trabajo hecho que no llega a `main`. `#842` figura como esperando merge **sin rama** (dato inconsistente; revisar a mano).

**B) 5 colisiones pausadas cuyo "ganador" ya no corre** (`colision_pausada_por`): #9990539 y #9990455 ← #9990536; #9990559 ← #9990555; #9990834 ← #9990817; #9991091 ← #9990859. `reanudarColisionesResueltas()` solo libera cuando el ganador tiene `merge_commit` o cierra → se destraban solas al mergear (A).

**C) 52 items aprobados con el freno puesto** (`excluir_pool_automatico=1`, sin hijos abiertos, sin merge pendiente, sin terminal, sin `motivo_espera`). Parte son legítimos (insumos tuyos: #9990790 reglamento, #9991179 autorización; fronteras duras; MR-33/34/35 congelados a propósito; #9991161 esperando tu visto bueno), pero otros parecen residuo — p.ej. #9991143 y #9991058 son hijos aprobados que frenan a sus paraguas (#9990677, #9990858) sin motivo registrado. El propio código lo dice: *"Aprobar y desbloquear son dos cosas distintas — si ya quieres que corra, quita el freno"*. Solo 8 de los 52 tienen `motivo_espera` (CIRC-03 clasificó 8; el barrido no siguió).

Además: #9990786 (nivel A) sigue en `pendiente_revision` sin triaje del revisor.

### Qué destraba el circuito (en orden de rendimiento)
1. **Mergear las 10 ramas de la tabla** — botón «Mergear» en la Torre o `php artisan circuito:integrar <id> --force`. Con eso se liberan ~15 dependientes + las 5 colisiones y las 6 terminales vuelven a tener trabajo. Después, las otras 31 cuando quieras (todas limpias contra main).
2. **Quitar el freno** a los aprobados de (C) que ya no esperan nada, o ponerles `motivo_espera` para que dejen de contarse como cola.
3. Estructural (item aparte si lo apruebas): el volumen bajó de 114 vueltas el 09-14 a 16/32/18 los días siguientes porque el circuito ya consumió todo lo que no dependía de un merge tuyo — **los merges nivel C son hoy el cuello de botella del sistema**, no la capacidad de las terminales.

No mergeé ni toqué ningún flag: nivel C es decisión tuya y el merge de 41 ramas cambia `main` de forma no trivial.

Registrado como item de respuesta **#9991218** (nivel B, requiere_irving, motivo_espera=decision).

### Maqueta Panorama en Árbol
Irving pegó la maqueta en la sesión. Guardada en `docs/maquetas/panorama-arbol.html` (sin commitear — va en la rama del item) y registrada como **adjunto #9 (panorama-arbol.html, 41,318 bytes)** amarrado a #9991161 y #9990934 vía `RoadmapAdjuntoService::subir()`. Sigue pendiente el visto bueno del Paso 0 y las 2 decisiones (ubicación de los KPIs de intake; regla de tokens de contraste) antes de crear los 3 sub-items de fase.
