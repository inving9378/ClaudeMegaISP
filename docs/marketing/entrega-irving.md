# Marketing IA 360 — paquete de entrega para Irving (BETA)

> ## 🔑 2026-11-14 — ACCESO POR ROL, NO POR NOMBRE (decisión de Irving)
> - Entran a Marketing, con todo, quienes tengan **super-administrator, Administrador, DESARROLLADOR o Mostrador**
>   (`config/marketing.php` → `roles_con_acceso`). No necesitan estar en la lista ni tener fila en el equipo.
> - La migración `2026_11_14_120000` da a esos 4 roles todos los permisos de Marketing (aditiva; `down()` quita solo
>   lo que dio). Tras aplicarla: `php artisan permission:cache-reset`.
> - **`MARKETING_EQUIPO_LOGINS` ya no es obligatoria en prod**: sin ella esos roles entran igual. Queda como forma
>   opcional de dar acceso a alguien sin esos roles (con `marketing:equipo-fijar`).
> - Sale en la **V1.91-14.11.2026** junto con las Fases 1 y 2.

> ## ✅ 2026-11-13 19:50 — MARKETING YA ESTÁ EN DEV (http://192.168.105.11)
> - **Qué hay en dev:** Fases 0, 1 y 2 de Marketing IA 360 (Panel, Campañas 360 con IA, Reels y Videos a Video MVM,
>   menú de 9 secciones, diseño igual a Clientes) + ajustes de Henry. La Fase 3 (biblioteca/calendario) NO está en dev.
> - **Commits en main de dev:** `7708e6d31` (integración Fases 0–2) y `369000514` (ajustes de Henry).
> - **Etiquetas de respaldo (locales, no subidas):** `respaldo/antes-marketing-fase2-dev-20261113` (antes de integrar;
>   lote de migraciones 576) y `respaldo/antes-marketing-ajustes-henry-20261113`.
> - **La próxima actualización a producción lo incluye** (cuando Irving decida). Antes de aplicarla, agregar al `.env` de prod:
>   `MARKETING_EQUIPO_LOGINS=<login_user de Henry>,<login_user de Irving>` (en dev: `Henry,Irving`). Sin esa línea nadie entra a Marketing.
> - En dev se movió fuera de `public/` la carpeta `public/marketing` (atajo a los videos, creado a mano en mayo, sin git):
>   chocaba con la ruta `/marketing` (403 de nginx) y dejaba los videos visibles sin sesión. Revisar si prod la tiene.

> **Regla vigente (Irving, vía Henry, 2026-11-13):** nada se sube a producción ni se publica para «Buscar
> actualizaciones». Todo queda en **BETA** en la rama `feat/marketing-360-equipo`. Irving revisa en la semana, decide y
> aplica. Este archivo se actualiza al cerrar cada fase.

## 0. Estado
| Fase | Qué | Commits en la rama | Estado |
|---|---|---|---|
| 0 | Cimientos: tablas de empresa/equipo, roles, guía visual | `e0ddfa5aa` | Ya en main de dev (`aee783056`). **Ojo:** la sesión de Henry la publicó como release **V1.87-12.11.2026** antes de esta regla; existe en GitHub Releases pero **no se ha aplicado en prod**. Recomendación: no aplicarla sola (ver §4). |
| 1 | Rediseño ejecutivo, solo Meganet, acceso solo Henry e Irving | `98978ff71`, `08d58e7e3` | Solo en la rama (beta). Reporte: `docs/bitacora/2026-11-13-marketing-fase-1.md`. |
| 2 | Campañas 360 con IA + Reels/Videos a Video MVM + menú de Marketing | (en construcción) | — |

## 1. Qué cambia, por fase (en pantalla)
- **Fase 0:** tablas `marketing_empresas` (fila 1 = Meganet) y `marketing_empresa_usuario`; cada registro de Marketing queda
  ligado a Meganet. Sin cambios visibles importantes (la pantalla de marcas quedó oculta en la Fase 1).
- **Fase 1:**
  - Marketing abre en un Panel ejecutivo (4 indicadores del mes en MXN, embudo, campañas activas) con barra de
    navegación propia; 19 pantallas rediseñadas (claro/oscuro, celular).
  - **Acceso:** solo los usuarios de `MARKETING_EQUIPO_LOGINS` (Henry e Irving), como admin con todo. Cualquier otro
    usuario, aunque sea super-administrador, ve «No tienes acceso a Marketing». Sin sesión no abre nada.
  - Las rutas de Marketing ya no usan `check_route_permission` (usan sesión + equipo de Marketing + permiso por acción).
  - Se crean 13 permisos de Marketing que faltaban (Leads, Formularios y Brand Kit daban «sin permiso» a todos, también en prod).
  - Funciones viejas que daban error 500 (tablas inexistentes) responden 410 y salen de pantalla: lista completa en el reporte de Fase 1 §8.
  - Brand Kit ya no captura llaves de IA (manda a Integraciones). Sin costos en USD en pantalla.

## 2. Migraciones y cómo deshacerlas
| Migración | Qué hace | Deshacer (`php artisan migrate:rollback --step=N`) |
|---|---|---|
| `2026_11_12_100000_create_marketing_empresas_table` | tabla + fila Meganet | `down()` borra la tabla |
| `2026_11_12_100100_create_marketing_empresa_usuario_table` | tabla del equipo | `down()` borra la tabla |
| `2026_11_12_100200_create_marketing_empresas_gestionar_permission` | permiso `marketing.empresas.gestionar` (super-admin y DESARROLLADOR) | `down()` lo quita |
| `2026_11_12_100300_asigna_equipo_actual_a_empresa_meganet` | **escribe datos**: mete al equipo por rol (~12 admin + 1) | `down()` borra solo esas filas (empresa 1, `created_by` nulo) |
| `2026_11_13_100000_crea_permisos_faltantes_de_marketing` | 13 permisos para super-admin y DESARROLLADOR | `down()` vacío (catálogo); quitar a mano si se quiere |
| `2026_11_13_100100_agrega_preferred_voice_model_a_marketing_niches` | columna que el código ya usaba (solo si falta) | `down()` vacío a propósito (en dev ya existía con datos) |
| `2026_11_13_100200_restringe_equipo_marketing_a_lista_env` | **escribe datos**: deja activos solo a los de la lista; desactiva (no borra) al resto; marca `equipo_fijado_at` | `down()` borra la marca → **nadie** entra a Marketing (no reactiva a nadie) |

Todas son aditivas. El deploy ensaya las migraciones en `{db}_dryrun` antes de aplicarlas. Para regresar el **código**,
el deploy lo hace solo si falla antes de `migrate`; después de `migrate` la BD no se restaura sola (usar el respaldo del deploy).

## 3. Línea del `.env` (usuarios permitidos) — agregar en prod ANTES de aplicar
```
MARKETING_EQUIPO_LOGINS=<login_user de Henry>,<login_user de Irving>
```
- Son los `login_user` de producción, separados por coma (Henry los confirma). No va en el repositorio.
- Si falta o está vacía, **nadie** entra a Marketing. Se arregla agregándola y corriendo
  `php artisan marketing:equipo-fijar <login> <login> --aplicar` (sin `--aplicar` solo muestra qué haría).
- Tras editar el `.env`: `ls -la .env` y, si cambió el grupo, `chgrp www-data .env` (si no, el sitio da 500).

## 4. Pasos para aplicar en producción (cuando Irving decida)
1. Bitácora previa en `docs/bitacora/` (qué versión, quién, respaldo).
2. Crear la versión **desde esta rama** (no de main completo) con el procedimiento de release parcial: rama release
   desde el último tag aplicado en prod + los commits de Marketing; publicar con `releases:publish-github`.
   Incluir Fase 0 + Fase 1 (+ fases siguientes aprobadas) **juntas**: la Fase 0 sola daría acceso a Marketing a ~12
   personas por rol; con la Fase 1 la restricción se aplica en el mismo `migrate`.
3. Agregar la línea del `.env` (§3).
4. «Buscar actualizaciones» → aplicar. El deploy respalda la BD, ensaya migraciones, compila, migra.
5. Después: `php artisan permission:cache-reset` en prod.
6. Prueba rápida: con el usuario de Henry → Marketing abre el Panel; Leads abre (antes «sin permiso»); con otro usuario
   de LM → «No tienes acceso a Marketing».
7. Si falla en `migrate`: no reintentar; revisar `storage/logs/self-update-{id}.log`; restaurar con el respaldo del paso `backup_db`.

## 5. Fila de videos (F2-3) — necesaria para «Producir en Video MVM» (Fase 2)
Pasos exactos en `docs/marketing/pasos-irving-produccion.md` §2: verificar ffmpeg, crear
`/etc/supervisor/conf.d/megaisp-video-render.conf` (1 proceso, `nice`, `--queue=video-render --timeout=600`),
`supervisorctl reread && update`, probar y cómo apagarlo. En dev hace falta lo mismo con `/var/www/megaisp`.

## 6. Riesgos
| Riesgo | Cómo se cubre |
|---|---|
| Aplicar V1.87 sola abre Marketing a ~12 personas por rol | Aplicarla solo junto con la Fase 1 (§4) |
| Falta la línea del `.env` | Nadie entra (falla segura); se corrige con §3 |
| Super-administradores pierden acceso a Marketing que tenían | Esperado: decisión de Henry (solo Henry e Irving) |
| Rutas viejas 410 | Eran 500 por tablas inexistentes; nada que funcionara se quitó |
| Sin procesador de `video-render` | Videos se quedan «En cola» (ya pasaba); §5 |

## 7. Reportes de prueba
- Fase 0: `docs/bitacora/2026-11-12-marketing-fase-0.md` — 0 fallas nuevas, 526 pruebas nuevas.
- Fase 1: `docs/bitacora/2026-11-13-marketing-fase-1.md` — suite 2,744 · 139 fallas, todas de la línea base · **0 nuevas**;
  179 pruebas nuevas; acceso probado ruta por ruta (134); ataques en navegador OK (doble clic, recarga, URL ajena, 375 px).
  Detalle QA: `docs/marketing/fase-1-qa-reporte.md`.
