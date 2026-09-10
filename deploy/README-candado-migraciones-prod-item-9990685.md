# PROD — anclar CIRCUITO_CANDADO_MIGRACIONES en el `.env` (item roadmap #9990685)

## Por qué

`config('circuito.candado_migraciones')` (`config/circuito.php:340`) tiene como
default:

```
/var/www/megaisp/storage/app/circuito/migrate-esquema.lock
```

Esa ruta es correcta en **DEV** (el proyecto vive en `/var/www/megaisp`), pero en
**PROD** el proyecto está en `/var/www/ClaudeMegaISP` → esa ruta NO existe ahí.
El default del repo **NO se toca**: el item #9990003 lo fijó absoluto al checkout
principal a propósito, para que el candado sirva de verdad entre las 6 terminales
de dev (antes usaba `storage_path()`, que resuelve distinto por-worktree y no
serializaba nada — ver `docs/circuito-candado-esquema-storage-path-item-9990003-verificacion.md`).
Cambiar el default rompería justo eso en dev.

## No es un fix de algo caído — es correctitud diferible

El candado ya es **fail-safe**: `GuardedMigrateCommand` (líneas 109-129) hace
`@fopen()` + `flock()` sobre la ruta; si el archivo no se puede abrir (porque la
ruta no existe en prod), el propio comando solo avisa y sigue con la migración
normal (no bloquea el deploy). Además, en prod **no aplica** el problema que el
candado resuelve en dev (varias terminales migrando a la vez sobre la misma BD):
prod es una sola máquina y `DeploymentLock` ya serializa los deploys por caché.
Por eso el item quedó con prioridad baja y sin urgencia — esto es dejar el
candado **explícito y auditable** en prod, no reparar algo roto.

## Frontera dura — por qué el circuito NO lo ejecuta

Este cambio vive únicamente en el `.env` de producción (`ClaudeMegaISP`), una
máquina distinta a la de este worktree. El circuito de dev tiene prohibido tocar
`.env` o servicios de producción (ver `CLAUDE.md`, checklist pre-deploy punto 8;
mismo patrón que los runbooks `README-prod-debug-off-item-156.md` y
`README-session-secure-cookie-prod.md`). El brief de decisión del item #9990685
escaló a Irving y quedó aprobada la **Opción 1**: "Irving lo aplica ahora en la
próxima ventana de mantenimiento". Este documento es el runbook de esa ejecución
manual — no reemplaza la decisión, solo la deja lista para correr sin tener que
reconstruir los pasos.

## Paso 0 — confirmar que el directorio destino ya existe en PROD

El item de origen indica que `storage/app/circuito/` ya se creó en
`/var/www/ClaudeMegaISP` (aunque el archivo del lock todavía no exista — el
`fopen('c')` lo crea solo). Verificar antes de tocar el `.env`:

```bash
# En el servidor de PROD, como root o www-data:
ls -ld /var/www/ClaudeMegaISP/storage/app/circuito
```

Si no existe, crearlo primero (mismos permisos que el resto de `storage/app`,
propietario `www-data`):

```bash
mkdir -p /var/www/ClaudeMegaISP/storage/app/circuito
chown www-data:www-data /var/www/ClaudeMegaISP/storage/app/circuito
```

## Paso 1 — respaldar y editar el `.env` de PROD

```bash
# En el servidor de PROD, dentro de /var/www/ClaudeMegaISP (NUNCA el .env de dev):
cp .env .env.bak-$(date +%Y%m%d%H%M%S)

# Agregar (o corregir si ya existe con otro valor) la línea:
CIRCUITO_CANDADO_MIGRACIONES=/var/www/ClaudeMegaISP/storage/app/circuito/migrate-esquema.lock
```

## Paso 2 — warm-up (orden importa, NUNCA `config:cache` a ciegas)

```bash
php artisan config:clear
php artisan route:clear
php artisan queue:restart
```

No correr `php artisan config:cache` aquí salvo que ya se haya corrido antes
`php artisan config:auditar-env` y haya salido limpio (ver `CLAUDE.md`, sección
"`config:cache` — SÓLO detrás de `config:auditar-env`"). Si prod ya tenía config
cacheada de antes, el `config:clear` es obligatorio para que el `.env` editado en
el Paso 1 se vuelva a leer.

## Paso 3 — verificar

```bash
php artisan tinker --execute='echo config("circuito.candado_migraciones");'
# Debe imprimir exactamente:
# /var/www/ClaudeMegaISP/storage/app/circuito/migrate-esquema.lock
```

Opcional — confirmar que el candado ya no cae al fail-safe en el próximo
`php artisan migrate` (buscar que NO aparezca el warning de "no se pudo abrir el
candado" en el log de deploy/migración).

## Rollback

Sin riesgo de datos: restaurar el `.env.bak-*` del Paso 1 (o quitar la línea
`CIRCUITO_CANDADO_MIGRACIONES`) y repetir el Paso 2. El candado vuelve a su
comportamiento fail-safe actual (avisa y sigue) mientras se decide.

## Alcance

Solo `.env` de producción + warm-up de caché. No toca DEV, no toca BD, no toca
`config/circuito.php` ni ningún otro archivo versionado.
