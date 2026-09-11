# PROD .198 (v1megaisp.com.mx / 192.168.105.108) — pase a producción de la normalización de serie del equipo (item roadmap #9990833)

## Por qué

El SN del equipo se captura hoy de dos formas distintas para el mismo aparato:
la OLT lo reporta como `ECOMC8012F9B` (4 caracteres ASCII de vendor + 8 hex) y
la captura manual lo guarda como `45434F4DC8012F9B` (los mismos 8 bytes, todo
en hex). Son el mismo número — buscar por SN no encontraba nada porque los dos
formatos nunca se comparaban entre sí. Este trabajo (Fases 1-4 de #9990833) ya
está resuelto y verificado en **dev**: normalizador (`ClienteSearchService::
normalizarSn()`/`formatoCorto()`), columnas nuevas, comando de backfill,
buscador v2 y reporte de discrepancias. Este documento es el checklist para
llevarlo a **producción** — nada de esto se ejecuta desde el circuito.

## Frontera dura — por qué el circuito NO ejecuta esto (D7)

Este documento **solo se escribe**. Ninguna terminal del Circuito CC se
conecta a `192.168.105.108` ni a `v1megaisp.com.mx`, ni corre estos comandos,
ni lee esa base de datos. La ejecución real en prod es **manual de Irving**.
El guardrail de producción del Circuito CC sigue vigente sin excepción (ver
`CLAUDE.md`, checklist pre-deploy).

## Qué se despliega

| Pieza | Dónde vive en el código |
|---|---|
| Migración aditiva (3 columnas + índice) | `database/migrations/2026_09_11_220000_add_serie_equipo_columns_to_client_additional_information.php` |
| Comando de backfill | `app/Console/Commands/Active/NormalizarSeriesClientesCommand.php` (`clientes:normalizar-series`) |
| Normalizador | `App\Modules\Core\Clientes\Services\ClienteSearchService::normalizarSn()` / `formatoCorto()` |
| Flag de reversión | `config/clientes_busqueda.php` → `v2_habilitado`, env `CLIENTES_BUSQUEDA_V2` (default `false`) |

Las 3 columnas nuevas viven en **`client_additional_information`** (no en
`clients`): `serie_equipo` (formato corto legible), `serie_equipo_norm`
(16 hex canónico, indexado — es contra la que busca el buscador v2) y
`serie_equipo_origen` (`olt` / `manual`). **`modem_sn` es intocable**: el
comando nunca escribe ahí, solo lo lee como última fuente.

## Orden obligatorio del deploy

**Migración → código → backfill → flag.** El flag es el último acto y el
único paso reversible al instante. No alterar este orden: encender el flag
antes del backfill mostraría un buscador v2 sin datos que buscar.

## Versionado

Esto sale en una **versión numerada con su changelog**, publicada como
**GitHub Release** — no como parche manual en el servidor. El changelog debe
cubrir todo el rango de versiones pendientes de aplicar en prod, no solo
esta. Ver `RemoteDeployCommand` / flujo normal de "Buscar actualizaciones".

## Paso 1 — Respaldo puntual (además del automático de las 02:00)

En el servidor de producción, antes de tocar nada:

```bash
mysqldump -u root -p <BD_PROD> clients client_additional_information \
  > /var/backups/mysql/clients_pre_serie_$(date +%F_%H%M).sql
ls -lh /var/backups/mysql/clients_pre_serie_*
```

## Paso 2 — Migración aditiva

Se aplica con el flujo normal de deploy (`php artisan migrate --force`,
dentro del pipeline de `remote:deploy`, que ya corre `deploy:dry-run-migrations`
antes). Solo agrega las 3 columnas nullable + su índice — **nada las usa
todavía**, así que este paso por sí solo no cambia ningún comportamiento
visible.

## Paso 3 — Dry-run en prod (solo lectura)

```bash
php artisan clientes:normalizar-series --dry-run --csv=/tmp/series_prod.csv
```

No escribe nada en la base de datos; solo cuenta y genera el CSV de
excepciones (`cliente_id, valor_original, origen, razon`).

**GATE HUMANO — obligatorio antes de continuar:** si las excepciones superan
el **5% del padrón** (columna `excepciones` de la salida del comando, sobre
el total de clientes con candidato), **detenerse y revisar** `series_prod.csv`
antes de seguir al Paso 4. Un porcentaje alto significa que hay un formato de
vendor que el normalizador no reconoce — no se corrige a mano ni se fuerza,
se investiga primero (D5 del item padre: las excepciones se reportan, no se
corrigen).

Nota: un cliente de WiFi sin fila en `olt_onus` que normaliza correctamente
desde `modem_sn` (`serie_equipo_origen = 'manual'`) **no** es una excepción —
es el comportamiento esperado (D4).

## Paso 4 — Backfill real

```bash
php artisan clientes:normalizar-series --chunk=500
```

Idempotente (correrlo dos veces da el mismo resultado, verificado en dev) y
usa `chunkById` para no bloquear la tabla `clients` durante el proceso.

## Paso 5 — Verificación antes de encender

```sql
SELECT COUNT(*) total,
       SUM(serie_equipo_norm IS NOT NULL) AS normalizados,
       SUM(serie_equipo_norm IS NULL AND modem_sn IS NOT NULL) AS excepciones
FROM client_additional_information
WHERE deleted_at IS NULL;
```

Confirmar también que `modem_sn` no cambió ni una fila respecto al respaldo
del Paso 1 (criterio de aceptación #6 del item padre — es el crítico: la
columna legada debe quedar intacta).

## Paso 6 — Encender

```bash
# En el .env de PROD:
CLIENTES_BUSQUEDA_V2=true
```

```bash
php artisan config:cache && php artisan view:cache
```

## Reversión

```bash
# En el .env de PROD:
CLIENTES_BUSQUEDA_V2=false
```

```bash
php artisan config:cache
```

Cinco segundos, sin tocar base de datos ni revertir código ni migraciones —
el buscador vuelve al comportamiento anterior de inmediato.

## Alcance

Solo prod (.198 / `v1megaisp.com.mx`). No toca dev. La migración y el
backfill no alteran `modem_sn` en ningún caso (verificado en dev con el
criterio de aceptación #6 del item #9990833). El código de las 4 piezas de
la tabla de arriba ya está en `main`, verificado en dev — este documento no
agrega código nuevo, solo el procedimiento de aplicarlo en producción.
