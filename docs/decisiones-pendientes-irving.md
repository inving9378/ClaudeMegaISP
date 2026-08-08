# Decisiones pendientes — una sola pasada

> Generado por Thomas el 2026-08-08 16:50. Son las decisiones que **no**
> puede tomar solo: estratégicas o irreversibles. Todo lo demás ya lo resolvió y está corriendo.
>
> **Si no contestas en 48 h**, Thomas procede con la recomendación en los puntos marcados
> ♻️ *reversible* y lo deja registrado para que lo revises después. Los no reversibles esperan.

## 1. #530 — Deploy a prod falla en "Sincronizar codigo" (exit 1): el codigo de V1.26-V1.29 nunca se empujo a origin/main, solo los tags

- **Módulo:** Core / Release · **Nivel:** C
- **Qué hay que definir:** Fase A: ¿Cómo resolver que el código de V1.26-V1.29 (637 commits) no está en origin/main?
- **Recomendación de Thomas:** Opción 1: Auditar los 637 commits con git_staging_gate (buscar secretos/artefactos), y si pasan, hacer `git push origin main` una sola vez. En adelante, publicar versión SOLO tras empujar main — Pro: repo deja de mentir, clones nuevos funcionan, gate previene fuga de secretos. Contra: requiere trabajo de auditoría previo; si aparecen secretos históricos hay que limpiarlos primero (filter-repo o similar).  ⚠️ *no reversible — espera tu respuesta*

## 2. #547 — Fix: items "terminados, esperan merge" que no avanzan (auto-merge al decidir)

- **Módulo:** Roadmap / Circuito CC · **Nivel:** C
- **Qué hay que definir:** ¿Cómo abordar el arranque del fix del auto-merge (Fase A antes de tocar código)?
- **Recomendación de Thomas:** Opción 1: Ejecutar Fase A de diagnóstico read-only (crontab -l, logs del merge-runner, ps del proceso, listar items atascados con su estado_aprobacion real, buscar worktrees/locks huérfanos) y reportar hallazgos ANTES de tocar el runner o estados. Pro: respeta el gate del PASO 0 y evita merges a ciegas de items posiblemente rotos. Contra: agrega un turno antes del fix.  ♻️ *reversible*

## 3. #565 — Registrar addon-talento en module_registry (hacerlo gobernable)

- **Módulo:** ModuleManager · **Nivel:** C
- **Qué hay que definir:** ¿Registrar addon-talento en module_registry como activo (installed_version=2.0.0, keep_data=true) sin cambiar su comportamiento?
- **Recomendación de Thomas:** Opción 1: Registrar Talento en module_registry como activo, installed_version=2.0.0, keep_data=true; validar que aparece en Module Manager y que activar/desactivar/reactivar no pierde datos ni permisos Spatie; commit selectivo + warm-up (config:clear + route:clear + queue:restart) + permissions:sync-roles — Pro: vuelve gobernable el módulo más grande (54 tablas, 240 rutas), cierra el fallo-abierto de isActive() ?? true, comportamiento idéntico. Contra: al volverse apagable existe riesgo de que alguien lo desactive por error y tumbe 240 rutas.  ⚠️ *no reversible — espera tu respuesta*

## 4. #566 — Destrabar la cola: ensanchar auto-aprobación del autopilot + re-triaje de la bandeja

- **Módulo:** Roadmap / Circuito CC · **Nivel:** C
- **Qué hay que definir:** ¿Ensanchar la política de auto-aprobación del autopilot (Thomas) para incluir limpieza de andamiaje muerto, cerrar huecos ruteados, rutas 404, reclasificar 'Sin clasificar' y bugs mecánicos con patrón claro?
- **Recomendación de Thomas:** Opción 1: Aplicar la recalibración exacta como está descrita (las 3 condiciones AND: reversible + mecánico + no-prod), con las 5 categorías explícitas whitelistadas en config/circuito.php → thomas — Pro: destraba la cola ya, respeta la frontera dura (negocio/prod/dinero/seguridad siguen escalando), es la propuesta que Irving ya aprobó el 2026-08-08. Contra: 'bugs acotados con patrón existente claro' es subjetivo y puede colar cosas borderline si el criterio no queda bien codificado.  ♻️ *reversible*

