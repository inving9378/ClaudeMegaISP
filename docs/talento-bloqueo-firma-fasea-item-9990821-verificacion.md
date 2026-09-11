# Item #9990821 — Fase A middleware BloqueoDocumentoPendienteMiddleware (RESUELTO — ya implementado por #9990804)

**Fecha:** 2026-09-11

## Contexto

El item #9990821 se creó como **sub-item de seguimiento de #9990804** ("Middleware global de
bloqueo proporcional por documento tipo firma pendiente"), pidiendo específicamente la Fase A:
crear `BloqueoDocumentoPendienteMiddleware`, registrarlo en `Kernel.php` (grupo `web`) y nombrar
las 6 rutas exentas que aún no tenían nombre en el momento en que se escribió el spec.

## Hallazgo

Al llegar a ejecutar #9990821, **el item padre (#9990804) ya había mergeado la implementación
completa a `main`** — commit `ef927c48` ("feat(talento): implementa el middleware global de
bloqueo proporcional por firma pendiente"), integrado vía `b810b701`. Mismo patrón de carrera de
timing documentado repetidamente en `CLAUDE.md` (#733→#741→#753, #9990003, #9990353, #9990658):
el sub-item de seguimiento se generó a partir del spec original de la Fase A antes de que la
sesión que trabajaba el padre terminara de mergear su propia implementación completa (que
absorbió Fase A y siguió con las decisiones de Fase B ya resueltas por Irving en las preguntas
q2/q3 del propio item).

## Verificación punto por punto contra el spec de #9990821

1. **Clase creada** — `app/Modules/Addons/Talento/Middleware/BloqueoDocumentoPendienteMiddleware.php`
   existe y cubre los 8 pasos pedidos: kill switch OFF→next(), método no-escritura→next(), ruta
   exenta por nombre→next(), sin sesión→next(), permiso de bypass `talento.bypass_bloqueo_firma`
   (adición de Fase B/q4 de Irving, no pedida en el spec de Fase A pero superset seguro)→next(),
   `Actor::for($user)->talento()` null→next() (reusa el patrón único de identidad, tal como pedía
   el spec), query de documentos `status=pendiente` + `template.tipo=firma` vacía→next(), y
   evaluación de `modulos_bloqueados` contra `config('talento.modulos_operativos')` con sentinel
   `'*'` → 423 si bloquea.
   - Diferencia menor: no filtra `template.active=true` como pedía el spec textual — no se
     considera un defecto (un documento pendiente de un template inactivo es un caso de borde sin
     evidencia de ocurrir hoy; no se toca por minimalismo, es ajuste de Fase B si hiciera falta).
2. **Registrado en Kernel** — `app/Http/Kernel.php:46`, **al final** de `$middlewareGroups['web']`
   (después de `TrackLastVisitedRoute`, no en el stack global top-level) — exactamente como pedía
   el spec, con el comentario explicando el porqué (kill switch OFF por default).
3. **Rutas nombradas** — verificado con `route:list --name=talento`, las 6 rutas del spec ya
   estaban nombradas (no coincide 100% con los nombres propuestos en el spec original —
   `talento.documentos.firma` en vez de `talento.documentos.firmar` — pero cumplen la función y
   ya figuran en `RUTAS_EXENTAS` del middleware con el nombre real):
   - `talento.documentos.firma` (POST `/colaboradores/{id}/documentos/{docId}/firma`)
   - `talento.asistencia.checkin` / `.checkout` / `.ping`
   - `talento.portal.asistencia.checkin` / `.checkout`
4. **Rutas exentas base** (`login`, `logout`, `password.email`, `password.update`,
   `profile.password.change`) — confirmadas registradas en `routes/web.php` y
   `app/Modules/Core/Auth/routes.php`. El middleware solo intercepta métodos de escritura, así que
   `password.request`/`password.reset` (GET) quedan fuera de la lista sin necesidad — decisión
   correcta, no un hueco.
5. **Verificación técnica de esta vuelta:**
   - `php -l` limpio en los 3 archivos tocados (middleware, Kernel, routes.php).
   - `php artisan route:list --name=talento` muestra las 6 rutas con sus nombres reales.
   - `php artisan --version` bootea limpio (`Laravel Framework 10.48.4`).
   - `config('talento.bloqueo_firma_pendiente_enabled')` = `false` (kill switch OFF, sin `.env`
     override) — el middleware es inerte en dev hoy.
   - Migración `2026_09_11_220000_create_talento_bypass_bloqueo_firma_permission` ya corrida
     (`migrate:status` → `Ran`, batch 676).

## Conclusión

Nada pendiente de la Fase A. El trabajo real de #9990821 ya vive en `main` desde antes de que
esta vuelta lo reclamara. **Sin cambio de código de aplicación** — solo esta nota de verificación.
