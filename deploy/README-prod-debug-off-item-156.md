# PROD .198 — apagar APP_DEBUG y Debugbar (item roadmap #156)

## Por qué

En DEV (.11) `APP_DEBUG=true` y Laravel Debugbar están activos a propósito
(desarrollo). En **producción (.198)** esos mismos flags exponen stack
traces, queries SQL y variables de entorno a cualquier visitante que
provoque un error — fuga de información. `config/debugbar.php:17` ya lee
`DEBUGBAR_ENABLED` vía `env()`, así que no hace falta tocar código: es
puramente configuración del `.env` de prod.

## Frontera dura — por qué el circuito NO lo ejecuta

Este cambio vive **únicamente** en el `.env` de producción (.198), una
máquina distinta a la de este worktree. El circuito de dev tiene prohibido
tocar `.env` o servicios de producción (ver CLAUDE.md, checklist
pre-deploy punto 8) — por eso el brief de decisión del item #156 escaló a
Irving (opción elegida: "Irving ejecuta manualmente en .198"). Este
documento es el runbook de esa ejecución manual; no reemplaza la decisión,
solo la deja lista para correr sin tener que reconstruir los pasos.

## Paso 1 — editar el `.env` de PROD (en el box .198, NUNCA el de dev)

```bash
# En el servidor .198, dentro de /var/www/megaisp (o la ruta real de prod):
APP_DEBUG=false
DEBUGBAR_ENABLED=false
```

Si la línea `DEBUGBAR_ENABLED` no existe todavía en ese `.env`, agregarla
(el default de `config/debugbar.php` es `null`, que el paquete trata como
"activo si `APP_DEBUG`" — hay que ponerla explícita en `false` para que no
dependa de esa inferencia).

## Paso 2 — warm-up (orden importa, NUNCA `config:cache` a ciegas)

```bash
php artisan config:clear
php artisan route:clear
php artisan queue:restart
```

No usar `php artisan config:cache` aquí salvo que ya se haya corrido
`php artisan config:auditar-env` antes y haya salido limpio (ver CLAUDE.md,
sección "`config:cache` — SÓLO detrás de `config:auditar-env`"). Si prod ya
tenía config cacheada de antes, un `config:clear` es obligatorio para que
el `.env` editado en el Paso 1 se vuelva a leer.

## Paso 3 — verificar

```bash
# 1. Forzar un error controlado (ruta inexistente) y confirmar que NO se ve stack trace/queries:
curl -s -o /tmp/prod-debug-check.html -w "%{http_code}\n" https://<dominio-o-ip-prod>/esta-ruta-no-existe
grep -qi "whoops\|stack trace\|debugbar" /tmp/prod-debug-check.html && echo "FALLO: sigue expuesto" || echo "OK: sin fuga de info"
rm -f /tmp/prod-debug-check.html

# 2. Confirmar que el HTML de cualquier página YA NO carga los assets de Debugbar:
curl -s https://<dominio-o-ip-prod>/ | grep -qi "debugbar" && echo "FALLO: debugbar sigue inyectando assets" || echo "OK: sin debugbar en el HTML"
```

Ambos deben imprimir `OK`. Si alguno da `FALLO`, repetir el Paso 2
(probable config cacheada vieja) antes de sospechar del Paso 1.

## Rollback

Si algo se rompe, revertir es trivial y sin riesgo de datos: regresar las
dos líneas a sus valores previos en el `.env` de prod y repetir el Paso 2.
Ninguna migración ni dato se toca en este cambio.

## Alcance

Solo `.env` de producción + warm-up de caché. No toca DEV, no toca BD, no
toca código (el soporte de `DEBUGBAR_ENABLED` ya existe en
`config/debugbar.php`).
