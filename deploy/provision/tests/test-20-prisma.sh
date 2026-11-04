#!/usr/bin/env bash
# =============================================================================
# test-20-prisma.sh — Esquema de Evolution en MySQL (_ensure_prisma_deploy) y parches
# =============================================================================
# Levanta un mysqld PRIVADO (datadir y socket temporales, puerto 3399) y un clon de
# Evolution en fa09d378 con los parches de deploy/provision/patches/, y corre el paso
# del instalador contra BD en distintos estados: nueva, con historial, la de prod
# (tablas de las 18 migraciones de Evolution sin historial y con el init fallido),
# la de dev (lid agregado a mano), estructura ajena con y sin datos, fallidas.
# No necesita root ni toca el MySQL del sistema. Tarda ~5 min (npm ci + Prisma).
#
#   bash deploy/provision/tests/test-20-prisma.sh
#   EVOLUTION_SRC=/opt/evolution-api bash ...   # repo de donde clonar (default: GitHub)
# =============================================================================
set -uo pipefail
P="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
L="$P/lib"
S="$(mktemp -d)"
E="$S/evolution-api"
MYSQLD="${MYSQLD:-$(command -v mysqld || echo /usr/sbin/mysqld)}"
cleanup() {
    [[ -f "$S/mysqld.pid" ]] && kill "$(cat "$S/mysqld.pid")" 2>/dev/null && sleep 3
    rm -rf "$S"
}
trap cleanup EXIT

echo "Preparando mysqld privado y clon de Evolution en $S ..."
mkdir -p "$S/roothome" "$S/backups"
PW="$(od -An -N8 -tx1 /dev/urandom | tr -d ' \n')"   # contraseña desechable del mysqld privado
"$MYSQLD" --no-defaults --initialize-insecure --datadir="$S/data" --log-error="$S/init.err" >/dev/null 2>&1 \
    || { echo "No pude inicializar mysqld ($MYSQLD)"; exit 2; }
"$MYSQLD" --no-defaults --datadir="$S/data" --socket="$S/mysqld.sock" --port=3399 --bind-address=127.0.0.1 \
    --mysqlx=OFF --pid-file="$S/mysqld.pid" --log-error="$S/mysqld.err" >/dev/null 2>&1 </dev/null &
for _ in $(seq 1 30); do [[ -S "$S/mysqld.sock" ]] && break; sleep 1; done
printf '[client]\nuser=root\nsocket=%s\n' "$S/mysqld.sock" > "$S/roothome/.my.cnf"
HOME="$S/roothome" mysql -e "CREATE USER 'evolution_user'@'127.0.0.1' IDENTIFIED BY '${PW}'; CREATE USER 'evolution_user'@'localhost' IDENTIFIED BY '${PW}'; GRANT ALL ON evolution_api.* TO 'evolution_user'@'127.0.0.1'; GRANT ALL ON evolution_api.* TO 'evolution_user'@'localhost';" \
    || { echo "mysqld privado no arrancó"; exit 2; }
git clone -q --no-checkout "${EVOLUTION_SRC:-https://github.com/EvolutionAPI/evolution-api.git}" "$E" && git -C "$E" checkout -q fa09d378 \
    || { echo "No pude clonar Evolution"; exit 2; }
for p in "$P"/patches/*.patch; do git -C "$E" apply "$p" || { echo "No aplica $p"; exit 2; }; done
printf 'DATABASE_PROVIDER=mysql\nDATABASE_CONNECTION_URI=mysql://evolution_user:%s@127.0.0.1:3399/evolution_api\n' "$PW" > "$E/.env"
(cd "$E" && npm ci --silent >/dev/null 2>&1) || { echo "npm ci falló"; exit 2; }

ok=0; bad=0
check() { if eval "$1"; then echo "  ✔ $2"; ok=$((ok+1)); else echo "  ✘ $2"; bad=$((bad+1)); fi; }
rootsql() { HOME="$S/roothome" mysql -N -B "$@"; }
reset_db() { rootsql -e "DROP DATABASE IF EXISTS evolution_api; CREATE DATABASE evolution_api CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"; rm -f "$S"/backups/*; }
migs() { ls -1 "$E/prisma/mysql-migrations" | grep -E '^[0-9]{14}_' | sort; }
aplica_upstream() {  # aplica las N primeras migraciones con el cliente mysql, sin historial
    local m; for m in $(migs | head -n "$1"); do rootsql -D evolution_api < "$E/prisma/mysql-migrations/$m/migration.sql"; done; }
n_aplicadas() { rootsql -D evolution_api -e "SELECT COUNT(*) FROM _prisma_migrations WHERE finished_at IS NOT NULL AND rolled_back_at IS NULL" 2>/dev/null || echo 0; }
hay_lid() { [[ "$(rootsql -e "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema='evolution_api' AND table_name='IsOnWhatsapp' AND column_name='lid'")" == 1 ]]; }
sin_ref() { [[ -z "$(rootsql -e "SHOW DATABASES LIKE 'evolution_api_ref'")" ]]; }
deploy_directo() { (cd "$E" && rm -rf prisma/migrations && cp -r prisma/mysql-migrations prisma/migrations && npx prisma migrate deploy --schema prisma/mysql-schema.prisma) > "$S/prep" 2>&1; }
correr() {
    ( source "$L/common.sh"
      EVOLUTION_USER="$(id -un)"; source "$L/20-evolution.sh"
      EVOLUTION_DIR="$E"; EVOLUTION_BACKUP_DIR="$S/backups"
      run_root() { HOME="$S/roothome" "$@"; }
      _ensure_prisma_deploy ) > "$S/out" 2>&1
    RC=$?
}
inserta_instancia() { rootsql -D evolution_api -e "INSERT INTO Instance (id,name,connectionStatus,integration,createdAt,updatedAt) VALUES ('t1','prueba','close','WHATSAPP-BAILEYS',NOW(),NOW())"; }
N=$(migs | wc -l)

echo "A) BD vacía (instalación nueva) — $N migraciones"
reset_db; correr
check '[[ $RC -eq 0 ]]' "termina bien (rc=$RC)"
check '[[ $(n_aplicadas) -eq $N ]]' "$N migraciones aplicadas"
check 'hay_lid' "IsOnWhatsapp tiene lid (la de Kafka lo borra; la de MegaISP lo repone)"

echo "B) segunda corrida"
correr
check '[[ $RC -eq 0 ]] && grep -q "No pending migrations" "$S/out" && ! grep -q "sin historial" "$S/out"' "nada pendiente"

echo "C) caso PROD probable: las 18 de Evolution, historial perdido, con datos y el init fallido"
reset_db; aplica_upstream 18; inserta_instancia
rootsql -D evolution_api -e "CREATE TABLE _prisma_migrations (id VARCHAR(36) NOT NULL PRIMARY KEY, checksum VARCHAR(64) NOT NULL, finished_at DATETIME(3) NULL, migration_name VARCHAR(255) NOT NULL, logs TEXT NULL, rolled_back_at DATETIME(3) NULL, started_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3), applied_steps_count INT UNSIGNED NOT NULL DEFAULT 0)"
deploy_directo
check 'grep -q "P3018" "$S/prep" && grep -q "already exists" "$S/prep"' "(preparación) migrate deploy directo da P3018 como en prod"
correr
check '[[ $RC -eq 0 ]]' "termina bien (rc=$RC)"
check 'grep -q "primeras 18 de $N migraciones" "$S/out"' "reconoce las 18 y no toca datos"
check 'grep -q "Applying migration .20251104120000_megaisp_lid" "$S/out"' "aplica solo la que falta (lid)"
check '[[ $(n_aplicadas) -eq $N ]] && hay_lid' "queda con $N aplicadas y lid"
check '[[ $(rootsql -D evolution_api -e "SELECT COUNT(*) FROM Instance WHERE id=\"t1\"") -eq 1 ]]' "conserva los datos"
check 'sin_ref && [[ -z "$(ls -A $S/backups)" ]]' "no deja la BD de referencia ni necesita respaldo"
correr
check '[[ $RC -eq 0 ]] && grep -q "No pending migrations" "$S/out"' "otra corrida: nada pendiente"

echo "D) caso dev: las 18 + lid a mano, historial perdido, con datos"
reset_db; aplica_upstream 18; rootsql -D evolution_api -e "ALTER TABLE IsOnWhatsapp ADD COLUMN lid VARCHAR(100) NULL"; inserta_instancia
correr
check '[[ $RC -eq 0 ]] && grep -q "primeras $N de $N" "$S/out"' "reconoce las $N (lid ya estaba)"
check '[[ $(n_aplicadas) -eq $N ]]' "$N aplicadas"

echo "E) estructura que no es de ninguna migración, SIN datos"
reset_db; aplica_upstream 18; rootsql -D evolution_api -e "ALTER TABLE Chat DROP COLUMN labels"
correr
check '[[ $RC -eq 0 ]] && grep -q "no tiene datos: se respalda y se recrea" "$S/out"' "respalda y recrea"
check 'ls "$S"/backups/evolution_api-*-antes-de-recrear.sql.gz >/dev/null 2>&1 && gzip -t "$S"/backups/*.sql.gz' "deja un respaldo válido"
check '[[ $(n_aplicadas) -eq $N ]] && hay_lid && sin_ref' "queda completa"
check '[[ $(rootsql -e "SELECT COUNT(*) FROM mysql.db WHERE Db=\"evolution_api\" AND User=\"evolution_user\"") -eq 2 ]]' "el usuario de Evolution conserva sus permisos"

echo "F) estructura que no es de ninguna migración, CON datos"
reset_db; aplica_upstream 18; rootsql -D evolution_api -e "ALTER TABLE Chat DROP COLUMN labels"; inserta_instancia
t_antes=$(rootsql -e "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema='evolution_api'")
correr
check '[[ $RC -ne 0 ]]' "aborta (rc=$RC)"
check 'grep -q "tiene datos (1 filas)" "$S/out" && grep -q "Instance: 1 filas" "$S/out"' "dice qué tabla tiene datos"
check 'grep -qE "^> col.Chat.labels" "$S/out"' "muestra la diferencia (Chat.labels)"
check '[[ $(rootsql -D evolution_api -e "SELECT COUNT(*) FROM Instance") -eq 1 ]] && [[ $(rootsql -e "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=\"evolution_api\"") -eq $t_antes ]]' "no toca nada"
check 'sin_ref && [[ -z "$(ls -A $S/backups)" ]]' "no deja la BD de referencia ni respalda"

echo "G) BD vacía con un intento fallido registrado"
reset_db; aplica_upstream 18
rootsql -D evolution_api -e "CREATE TABLE _prisma_migrations (id VARCHAR(36) NOT NULL PRIMARY KEY, checksum VARCHAR(64) NOT NULL, finished_at DATETIME(3) NULL, migration_name VARCHAR(255) NOT NULL, logs TEXT NULL, rolled_back_at DATETIME(3) NULL, started_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3), applied_steps_count INT UNSIGNED NOT NULL DEFAULT 0)"
deploy_directo
for t in $(rootsql -e "SELECT table_name FROM information_schema.tables WHERE table_schema='evolution_api' AND table_name<>'_prisma_migrations'"); do rootsql -D evolution_api -e "SET FOREIGN_KEY_CHECKS=0; DROP TABLE \`$t\`"; done
correr
check '[[ $RC -eq 0 ]] && grep -q "se marca como revertida" "$S/out" && [[ $(n_aplicadas) -eq $N ]]' "revierte el intento fallido e instala completa"

echo "H) BD con historial y una migración fallida"
reset_db; correr; rootsql -D evolution_api -e "UPDATE _prisma_migrations SET finished_at=NULL WHERE migration_name LIKE '20250918%'"
correr
check '[[ $RC -ne 0 ]] && grep -q "no se adivina su estado" "$S/out"' "aborta sin adivinar"

echo "I) migración de lid sobre una BD con historial que ya tiene lid (dev real)"
reset_db; (cd "$E" && mkdir -p "$S/m18" && rm -rf "$S/m18"/* && for m in $(migs | head -n 18); do cp -r prisma/mysql-migrations/$m "$S/m18/"; done; rm -rf prisma/migrations && cp -r "$S/m18" prisma/migrations && npx prisma migrate deploy --schema prisma/mysql-schema.prisma >/dev/null 2>&1)
rootsql -D evolution_api -e "ALTER TABLE IsOnWhatsapp ADD COLUMN lid VARCHAR(100) NULL"
correr
check '[[ $RC -eq 0 ]] && [[ $(n_aplicadas) -eq $N ]] && hay_lid' "aplica la de lid sin error (idempotente)"

echo; echo "Resultado: $ok OK, $bad fallidos"
(( bad == 0 ))
