# CIRC-05 PASO 6 (#9991016) — guardrail de entorno + host de roadmap-externo

Item: verificar/documentar que `api/roadmap-externo/*` no queda montada en prod y validar
el host de la petición. Decisión aprobada por Irving (opciones recomendadas en las 3
preguntas del item): guard en capa de rutas (`app()->environment()`) + middleware de host
con whitelist en `config/circuito.php` + esta bitácora.

## (1) ¿Queda montada en producción?

**Antes de este item:** implícitamente NO por falta de token válido, pero las rutas SÍ se
registraban igual en cualquier entorno — `BaseModuleServiceProvider::boot()` carga
`routes.php` de cada módulo activo con `loadRoutesFrom()`, sin mirar `APP_ENV`. El único
candado real era que `RoadmapExternalController::tokenOk()` (línea ~647) rechaza
explícitamente un token esperado vacío:

```php
private function tokenOk(string $given, string $expected): bool
{
    // Un token no configurado NUNCA valida (evita bypass con string vacío).
    return $expected !== '' && hash_equals($expected, $given);
}
```

**CONFIRMADO por lectura de código** (no había que asumirlo): `hash_equals('', '')` sí
devuelve `true` en PHP, pero el guard `$expected !== ''` corta ANTES de llegar ahí — así que
si `ROADMAP_EXTERNAL_READ_TOKEN`/`WRITE_TOKEN`/`CREATE_TOKEN` faltan en el `.env` de prod
(no están, es dev-only), **todo intento de acceso ya se rechazaba** con 403 aun antes de
este item. Esto era una sola capa (ausencia de secreto), no una frontera de entorno.

**Después de este item:** segunda capa, aditiva — `app/Modules/Addons/Roadmap/routes.php`
envuelve los 3 grupos `Route::prefix('api/roadmap-externo')` (lectura/escritura/creación) en:

```php
if (app()->environment(['local', 'dev'])) { ... }
```

Verificado con el propio artisan del repo (whitelist positiva, no negativa — un entorno
nuevo que no sea `local`/`dev` queda fuera por default):

```
$ php artisan route:list --path=roadmap-externo            # dev, APP_ENV=local
  → 11 rutas registradas (sin cambio de comportamiento en dev)

$ APP_ENV=production php artisan route:list --path=roadmap-externo
  → "Your application doesn't have any routes matching the given criteria."
```

Con `APP_ENV=production` las rutas **ni se registran** — no es un 404/403 en runtime, es
que el `Router` de Laravel nunca las conoce. Fuera de alcance de este item (otro controller):
el conector MCP (`Route::prefix('mcp')`, `RoadmapMcpController`, línea 101 de `routes.php`)
sigue sin guard de entorno — mismo patrón, pendiente si se decide extenderlo.

## (2) Validación de host

Antes de este item, cero verificación de `Host:`/`getHost()` en `RoadmapExternalController`
ni en `RoadmapIntakeService` (grep confirmado en el propio item original). El nginx de dev
(`/etc/nginx/sites-enabled/megaisp.conf`) es `listen 80 default_server; server_name _;` →
**acepta cualquier Host header** y lo reenvía a esta app tal cual, así que el filtrado real
tiene que pasar por Laravel.

Middleware nuevo `App\Modules\Addons\Roadmap\Http\Middleware\ValidateRoadmapExternoHost`
(aplicado a los 3 grupos de rutas de `RoadmapExternalController`, no al MCP): rechaza con
`403` cualquier request cuyo `$request->getHost()` no esté en
`config('circuito.roadmap_externo_hosts')` (whitelist, `ROADMAP_EXTERNO_HOSTS` en `.env`,
default `dev.meganett.com.mx,192.168.105.11,localhost,127.0.0.1` — `dev.meganett.com.mx` es
el dominio real por el que llega Cowork, ver `config/torre_salud.php:cert_host`). Loguea el
rechazo en el canal `circuito_despacho` (host/ip/path, sin loguear el token).

### Prueba manual (host real de dev vs. Host spoofeado)

`curl` contra el nginx vivo de esta caja sirve al checkout **principal**
(`/var/www/megaisp`, no este worktree), así que para probar el middleware antes de mergear
se instanció directo y se llamó `handle()` con requests sintéticos (`Illuminate\Http\Request`
+ closure de éxito), sin tocar la red:

```
dev.meganett.com.mx  => HTTP 200   (whitelist)
192.168.105.11       => HTTP 200   (whitelist, IP local de dev)
localhost            => HTTP 200   (whitelist)
127.0.0.1            => HTTP 200   (whitelist)
evil.example.com     => HTTP 403   (Host spoofeado, rechazado)
192.168.105.198      => HTTP 403   (IP que NO está en la whitelist, rechazado)
```

Tras el merge a `main`, repetir la prueba con `curl -H "Host: <lo que sea>"` contra el
vhost real es la verificación de humo recomendada para quien despliegue este cambio.

## Dónde vive el guardrail

- `app/Modules/Addons/Roadmap/routes.php` — guard de entorno (líneas del bloque
  `api/roadmap-externo`).
- `app/Modules/Addons/Roadmap/Http/Middleware/ValidateRoadmapExternoHost.php` — guard de host.
- `config/circuito.php` → `roadmap_externo_hosts` — whitelist configurable sin tocar código
  (`env('ROADMAP_EXTERNO_HOSTS', ...)`).

**Nota de ubicación:** el item original sugería una bitácora en `app/Modules/Auditoria/`,
pero ese directorio no existe en el repo (`Auditoria` es solo la etiqueta de `modulo` que
usa la Hoja de Ruta para clasificar items, no un módulo real de `app/Modules/`). Se documentó
aquí, en `docs/`, siguiendo la misma convención que `docs/circuito-seguridad-tokens.md`
(mismo tema: seguridad de esta misma superficie externa), más esta entrada en la skill
`circuito-cc` (sección "Topología").
