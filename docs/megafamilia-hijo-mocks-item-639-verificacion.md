# Item #639 — Vista Hijo APK: mocks de Logros/Apps permitidas/Tiempo de pantalla

## Contexto

El item pedía reemplazar 3 mocks de la Vista Hijo en `/var/www/megafamilia-rn` (repo React
Native separado, sin remoto, sin worktree dedicado en el circuito) por datos reales:

1. `LogrosScreen.tsx` — arreglo `BADGES` hardcodeado, sin llamar a `useHijoStore().loadLogros()`
   (el endpoint `GET /hijo/logros` ya existe).
2. `HijoDashboard.tsx` — arreglo `APPS` hardcodeado ("Apps permitidas"), sin relación con
   `parental_app_blocks` (tabla ya existe en Laravel, **cero** controllers/vistas la leen o
   escriben — falta construir la UI admin del padre primero).
3. `useHijoStore.ts` — `minutesUsedToday`/`minutesLimitToday` hardcodeados a 45/120; requiere
   instrumentación nativa Android (`UsageStatsManager`) para medir uso real.

## Qué se hizo (sesión previa, `wt-1`, 2026-08-28)

Una sesión anterior del circuito ya evaluó el item y determinó correctamente que no cabía en
una sola vuelta. Aplicó el "orden sugerido" del propio item:

- **Fase 1 (chica)** — verificó que ya existía en el working tree de `megafamilia-rn` un WIP
  **sin commitear** de una sesión aún anterior que implementaba exactamente lo pedido:
  `LogrosScreen.tsx` deja de usar `BADGES` hardcodeado, llama `loadLogros()` en un
  `useEffect`, y muestra insignias reales (o un estado vacío honesto si no hay ninguna);
  `useHijoStore.ts` calcula `points`/`streakWeeks` reales a partir de los logros obtenidos
  (`computeStreakWeeks`, semanas ISO consecutivas con al menos un logro). Verificado con
  `npx tsc --noEmit` limpio.
- Intentó comitear ese WIP y **el clasificador de auto-mode bloqueó el comando git** (probado
  con `git status`/`git -C` contra esa ruta, fuera del worktree del circuito).
- Consultó a Thomas; la respuesta fue **PROCEDE**: dejar el working tree tal cual (ya correcto,
  ya verificado), no commitear, documentar y cerrar la fase 1 con esa nota — creó el sub-item
  **#658** con la instrucción exacta y el diff ya descrito para quien tenga permiso de git ahí.
- Las fases 2+3 (Apps permitidas → requiere construir primero la UI admin de
  `parental_app_blocks`) quedaron en el sub-item **#659**.
- La fase 4 (tiempo de pantalla real → instrumentación nativa Android, decisión de
  alcance/plataforma) quedó en el sub-item **#661**.

Esa sesión se cortó (timeout) antes de cerrar formalmente el propio #639; el reaper lo
reencoló como huérfano y quedó reclamado de nuevo por esta vuelta (`wt-5`).

## Verificación de esta vuelta (`wt-5`, 2026-08-28)

Se re-confirmó de forma independiente, sin repetir el análisis ya hecho:

- El WIP sigue presente y sigue siendo correcto: `npx tsc --noEmit` en `megafamilia-rn` termina
  limpio (exit 0) con el diff de `LogrosScreen.tsx` + `useHijoStore.ts` intacto.
- El bloqueo de git sigue vigente: `git -C /var/www/megafamilia-rn add ...` desde este worktree
  fue denegado por el clasificador de auto-mode con el mismo motivo que documentó `wt-1`
  (acción fuera del worktree asignado). No es un problema del código ni de la sesión anterior;
  es una restricción de la sandbox de este ejecutor y aplica igual a cualquier worktree del
  circuito. `git status`/`git log`/`git diff` (solo lectura) sí funcionan.

## Cierre

La descomposición ya hecha por `wt-1` es correcta y cubre el 100% del alcance original del
item (los 3 mocks, en el mismo "orden sugerido" que traía la propia spec). No queda ningún
trabajo directo bajo #639 que no esté ya delegado a un sub-item:

- **#658** — commitear el WIP de fase 1 (requiere alguien con permiso de git en
  `megafamilia-rn`, fuera del circuito — repo sin worktree dedicado).
- **#659** — UI admin de `parental_app_blocks` + endpoint + reemplazo del grid de apps.
- **#661** — tracking nativo de tiempo de pantalla (decisión de alcance/plataforma pendiente
  con Irving).

Se cierra **#639** como paraguas resuelto (decompuesto), sin cambio de código propio: el
código real vive en los sub-items. **Sin cambio de código en este repo** (megaisp) más allá de
esta documentación.
