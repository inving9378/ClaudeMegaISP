# Runbook — Backfill de `users` espejo huérfanos + rollback (item roadmap #105 / #655 / #656)

**Ejecutor: Irving, manualmente, directo en el servidor de PRODUCCIÓN.** El circuito (Claude Code
on-box) **nunca** corre esto contra prod — solo lo dejó implementado y verificado en dev. Este
documento es la guía paso a paso para correrlo allá.

## 1. Contexto

`client_main_information` (CMI) es la ficha de cliente; `users` (rol `client`) es la fila "espejo"
que usan MegaFamilia, scheduling y otros módulos internos para resolver al cliente como usuario del
sistema. Hoy esa fila espejo se crea automáticamente al dar de alta un cliente
(`ClientMainInformationObserver::createNewUserRoleClient`), pero **CMI creadas antes de que
existiera ese Observer** (o actualizadas con password después) se quedaron sin su fila `users` —
son los "huérfanos".

El comando `users:backfill-orphan-clients` repone esa fila espejo replicando **exactamente** el
mismo camino que usa el Observer (mismos campos, mismo `PasswordService::make()` a bcrypt, mismo
rol `client`). No es una ruta nueva de creación de usuarios ni afecta el login del portal cliente
(ese valida contra `client_main_information.password` en texto plano, no contra `users.password`)
ni abre acceso al panel admin (el rol `client` no está en `STAFF_ROLES` de `LoginController`).

## 2. Paso 0 — Backup de `users` antes de tocar nada

```bash
cd /var/www/megaisp
mysqldump --single-transaction -u<usuario> -p<password_de_.env> megaisp users > /var/backups/mysql/users-pre-backfill-$(date +%Y%m%d%H%M).sql
gzip /var/backups/mysql/users-pre-backfill-*.sql
```

Usar las credenciales reales de `config('database.connections.mysql.*')` (las mismas que usa
`backup_db:process`), **nunca** hardcodear la password en el historial de shell — usar `read -s` o
`.my.cnf` si hace falta. Verificar que el `.sql.gz` no esté vacío antes de continuar.

## 3. Paso 1 — Diagnóstico (solo lectura, no escribe nada)

```bash
php artisan users:backfill-orphan-clients --report
```

Revisar la salida:
- **Total huérfanos** vs **procesables** (con password) vs **sin password** (esos NO se tocan,
  igual que el Observer no los crearía hoy).
- **Colisiones con `login_user` ya existente en `users`** — si aparece alguna, ese candidato se
  saltará en la corrida real (no se sobreescribe nada); revisar si amerita investigación aparte
  antes de seguir.
- **Duplicados internos** (mismo `login_user` repetido entre los propios huérfanos) — el primero se
  crea, el resto se salta.
- Muestra de los primeros 10 registros que se llenarían, para validar que los datos se ven
  razonables (nombre, login, email).

Si el número de procesables es muy distinto al esperado, **detenerse aquí** y confirmar contra la
BD antes de seguir — el comando es idempotente y se puede volver a correr el reporte las veces que
haga falta.

## 4. Paso 2 — Dry-run de la corrida completa

```bash
php artisan users:backfill-orphan-clients --dry-run
```

Debe reportar el mismo total de "Creados" que el "procesables" del reporte del paso 1, con
"Saltados" solo por colisión/duplicado. No escribe nada en la BD (ni en `users` ni en el log de
auditoría).

## 5. Paso 3 — Corrida real acotada (muestra pequeña primero)

Antes de aplicar el total, correr contra una muestra chica para validar en producción con datos
reales:

```bash
php artisan users:backfill-orphan-clients --limit=10
```

El comando imprime al final el **`batch`** (uuid) de esa corrida y el comando exacto para
deshacerla, por ejemplo:

```
Backfill completado. batch=<uuid>
Para deshacer ESTE batch: php artisan users:rollback-orphan-backfill <uuid>
```

Verificar en la BD que los 10 `users` nuevos quedaron correctos (login, rol `client`, `client_id`)
y que **no** afectan el login del portal ni del panel admin del cliente de prueba.

## 6. Paso 4 — Corrida real completa

Si la muestra del paso 3 se ve bien, correr sin `--limit` para procesar el resto de los huérfanos
restantes:

```bash
php artisan users:backfill-orphan-clients
```

Anotar el **`batch`** que imprime — es el identificador para un rollback quirúrgico si algo sale
mal más adelante. Cada corrida (muestra + completa) genera su propio `batch` independiente; ambos
quedan registrados en la tabla `orphan_client_backfill_log` (client_id, user_id, login_user, batch,
created_at).

## 7. Verificación posterior

```bash
php artisan users:backfill-orphan-clients --report
```

Debe reportar **0 procesables** (o solo los que colisionaron/duplicaron, si los hubo — esos quedan
igual que antes, sin fila `users`, y requieren revisión manual aparte, no reintento automático).

Espot-check adicional: confirmar que un cliente recién backfillado puede seguir usando el portal
(login sigue validando contra `client_main_information.password`, no cambia) y que MegaFamilia/
scheduling ahora lo resuelven correctamente como usuario.

## 8. Rollback — si algo salió mal

El rollback es **quirúrgico por batch**: borra EXACTAMENTE los `users` creados en ese batch (usando
el mapeo guardado en `orphan_client_backfill_log`, verificando que el `login_user` actual siga
coincidiendo con el registrado por si el id fuera reciclado) y limpia el log de ese batch. No toca
ningún otro registro, de ningún otro batch.

```bash
# 1. Ver qué se borraría, sin tocar nada:
php artisan users:rollback-orphan-backfill <batch> --dry-run

# 2. Si se ve correcto, aplicar:
php artisan users:rollback-orphan-backfill <batch>
```

Si el rollback también necesita deshacerse (caso extremo), usar el backup del paso 0
(`mysqldump` con `--single-transaction`, restaurar solo la tabla `users`).

## 9. Verificado en dev (item #656)

Round-trip probado en dev con una corrida acotada (`--limit=2`) sobre huérfanos reales:
- `--report`/`--dry-run` no escriben nada (conteo de `users` sin cambio).
- Corrida real crea exactamente N filas `users` + N filas en `orphan_client_backfill_log` con el
  mismo `batch`.
- `rollback-orphan-backfill --dry-run` lista las filas sin borrar.
- `rollback-orphan-backfill` real borra exactamente esas N filas de `users`, limpia el log del
  batch, y el conteo total de `users` regresa al valor previo a la corrida — sin afectar ninguna
  otra fila.

## 10. Candado

Este runbook y los comandos que describe **no se ejecutan desde el circuito contra producción**.
El circuito solo los dejó implementados y verificados en dev (items #105/#655/#656); correrlos en
prod es una acción manual de Irving, siguiendo los pasos de este documento.
