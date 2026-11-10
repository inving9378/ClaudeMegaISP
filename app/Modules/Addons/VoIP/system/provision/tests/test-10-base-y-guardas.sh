#!/usr/bin/env bash
# =============================================================================
# test-10-base-y-guardas.sh — lib/10-base-realtime.sh, guardas de common.sh y
# los pasos de .env de lib/90-desinstalar.sh
# =============================================================================
# MySQL SIMULADO: un shim `mysql` en PATH guarda en archivos si existen la base y
# el usuario y con qué password, y entiende las sentencias que mandan los scripts.
# `hostname` y `php` también son shims. No necesita root, no toca MySQL real.
#
#   bash app/Modules/Addons/VoIP/system/provision/tests/test-10-base-y-guardas.sh   # exit 0 = OK
# =============================================================================
set -uo pipefail
LIB="$(cd "$(dirname "${BASH_SOURCE[0]}")/../lib" && pwd)"
T="$(mktemp -d)"; trap 'rm -rf "$T"' EXIT
PASS=0; FAIL=0
ok()   { echo "  ✔ $*"; PASS=$((PASS+1)); }
bad()  { echo "  ✘ $*"; FAIL=$((FAIL+1)); [[ -f "$T/out" ]] && sed 's/^/      /' "$T/out"; }
check(){ if eval "$1"; then ok "$2"; else bad "$2"; fi; }

mkdir -p "$T/bin" "$T/db" "$T/root/storage" "$T/root/bootstrap/cache"
cat > "$T/bin/mysql" <<'EOF'
#!/usr/bin/env bash
# Estado: $FAKE_DB/db (existe la base), $FAKE_DB/user (existe el usuario), $FAKE_DB/pw.
sql=""; cnf=""
for a in "$@"; do
  case "$a" in --defaults-extra-file=*) cnf="${a#*=}";; esac
done
while (( $# )); do [[ "$1" == -e ]] && { sql="$2"; shift; }; shift; done
if [[ -n "$cnf" ]]; then   # login como asterisk_rt
  pw="$(sed -n 's/^password=//p' "$cnf")"
  [[ -f "$FAKE_DB/user" && "$pw" == "$(cat "$FAKE_DB/pw")" ]] && exit 0 || exit 1
fi
[[ -z "$sql" ]] && sql="$(cat)"
echo "$sql" >> "$FAKE_DB/log"
case "$sql" in
  "SELECT 1") exit "${FAKE_ROOT_FAIL:-0}" ;;
  *"FROM information_schema.SCHEMATA"*) [[ -f "$FAKE_DB/db" ]] && echo asterisk; exit 0 ;;
  *"FROM mysql.user"*) [[ -f "$FAKE_DB/user" ]] && echo asterisk_rt; exit 0 ;;
  *"FROM information_schema.tables"*) echo "${FAKE_TABLES:-0}"; exit 0 ;;
esac
[[ "${FAKE_CREATE_FAIL:-0}" == 1 && "$sql" == *"CREATE DATABASE"* ]] && { echo "ERROR 1044 (42000): Access denied" >&2; exit 1; }
[[ "$sql" == *"CREATE DATABASE IF NOT EXISTS"* ]] && touch "$FAKE_DB/db"
if [[ "$sql" == *"CREATE USER IF NOT EXISTS"* && ! -f "$FAKE_DB/user" ]]; then
  touch "$FAKE_DB/user"; sed -n "s/.* BY '\([^']*\)'.*/\1/p" <<<"$sql" | head -n1 > "$FAKE_DB/pw"
fi
if [[ "$sql" == *"ALTER USER"* ]]; then
  sed -n "s/.* BY '\([^']*\)'.*/\1/p" <<<"$sql" | head -n1 > "$FAKE_DB/pw"
fi
[[ "$sql" == *"DROP USER"* ]] && rm -f "$FAKE_DB/user" "$FAKE_DB/pw"
[[ "$sql" == *"DROP DATABASE"* ]] && rm -f "$FAKE_DB/db"
exit 0
EOF
cat > "$T/bin/hostname" <<'EOF'
#!/usr/bin/env bash
echo "${FAKE_HOSTNAME:-prod-server}"
EOF
cat > "$T/bin/php" <<'EOF'
#!/usr/bin/env bash
echo "php $*" >> "$FAKE_DB/php.log"
EOF
chmod +x "$T/bin/"*
export PATH="$T/bin:$PATH" FAKE_DB="$T/db"

ENVF="$T/root/.env"
run() {
    MEGAISP_ROOT="$T/root" MEGAISP_ENV="$ENVF" MEGAISP_USER="$(id -un)" \
    bash -c "set -euo pipefail; source '$LIB/common.sh'; source '$LIB/10-base-realtime.sh'; source '$LIB/90-desinstalar.sh'; $1" > "$T/out" 2>&1
}
reset_db() { rm -f "$T/db/"*; }
env_prod() { printf 'APP_URL=https://megaisp.example\nDB_DATABASE=meganet\nASTERISK_ARI_PORT=8088\n' > "$ENVF"; chmod 640 "$ENVF"; }

echo "Base realtime — servidor nuevo"
reset_db; env_prod
run 'provision_base_realtime'; rc=$?
check '[[ $rc == 0 ]]' "termina bien"
check '[[ -f $T/db/db && -f $T/db/user ]]' "crea base y usuario"
pw="$(grep '^ASTERISK_RT_DB_PASSWORD=' "$ENVF" | cut -d= -f2)"
check '[[ ${#pw} == 64 && $pw == "$(cat $T/db/pw)" ]]' "password generado (64 hex) igual en .env y MySQL"
check '! grep -q "$pw" "$T/out"' "el password no aparece en la salida"
check 'grep -qx "ASTERISK_RT_DB_HOST=127.0.0.1" "$ENVF" && grep -qx "ASTERISK_RT_DB_DATABASE=asterisk" "$ENVF" && grep -qx "ASTERISK_RT_DB_USERNAME=asterisk_rt" "$ENVF"' "escribe host, base y usuario en el .env"
check 'grep -qx "APP_URL=https://megaisp.example" "$ENVF"' "no toca las demás líneas del .env"
check '[[ $(stat -c %a "$ENVF") == 640 ]]' "conserva los permisos del .env"
check 'ls "$ENVF".provision-voip.*.bak >/dev/null 2>&1' "respalda el .env antes de escribir"
cp "$ENVF" "$T/env-tras-1"
run 'provision_base_realtime'; rc=$?
check '[[ $rc == 0 ]] && cmp -s "$ENVF" "$T/env-tras-1" && grep -q "OMITIDO" "$T/out"' "segunda corrida: no cambia nada"

echo "Base realtime — .env sin password pero el usuario ya existe (#201)"
reset_db; env_prod; touch "$T/db/user"; echo viejo > "$T/db/pw"
run 'provision_base_realtime'; rc=$?
check '[[ $rc != 0 ]] && grep -q "Desajuste" "$T/out"' "aborta explicando el desajuste"
check '[[ $(cat $T/db/pw) == viejo && ! -f $T/db/db ]]' "no toca MySQL"
check '! grep -q ASTERISK_RT_DB_PASSWORD "$ENVF"' "no escribe el .env"
VOIP_FORCE_ROTATE_DB_PASSWORD=1 run 'provision_base_realtime'; rc=$?
pw="$(grep '^ASTERISK_RT_DB_PASSWORD=' "$ENVF" | cut -d= -f2)"
check '[[ $rc == 0 && $pw == "$(cat $T/db/pw)" ]]' "con VOIP_FORCE_ROTATE_DB_PASSWORD=1 rota MySQL a juego con el .env"

echo "Base realtime — el password del .env no entra"
reset_db; env_prod; echo "ASTERISK_RT_DB_PASSWORD=delenv" >> "$ENVF"; touch "$T/db/user" "$T/db/db"; echo otro > "$T/db/pw"
run 'provision_base_realtime'; rc=$?
check '[[ $rc != 0 ]] && grep -q "no entra con el ASTERISK_RT_DB_PASSWORD" "$T/out"' "aborta sin tocar nada"
check '[[ $(cat $T/db/pw) == otro ]]' "no rota el password solo"

echo "Base realtime — MySQL rechaza la creación"
reset_db; env_prod
FAKE_CREATE_FAIL=1 run 'provision_base_realtime'; rc=$?
check '[[ $rc != 0 ]] && grep -q "rechazó la creación" "$T/out"' "aborta con el motivo"
check '! grep -q ASTERISK_RT_DB_PASSWORD "$ENVF"' "no deja un password en el .env que MySQL no tiene"

echo "Base realtime — host remoto / sin root de MySQL / nombre inválido"
env_prod; echo "ASTERISK_RT_DB_HOST=10.0.0.5" >> "$ENVF"
run 'provision_base_realtime'; rc=$?
check '[[ $rc != 0 ]] && grep -q "solo en el MySQL de este servidor" "$T/out"' "host remoto → aborta"
env_prod; reset_db
FAKE_ROOT_FAIL=1 run 'provision_base_realtime'; rc=$?
check '[[ $rc != 0 ]] && grep -q "como root por socket" "$T/out"' "sin acceso root a MySQL → aborta"
env_prod; echo 'ASTERISK_RT_DB_DATABASE=ast`;DROP' >> "$ENVF"
run 'provision_base_realtime'; rc=$?
check '[[ $rc != 0 ]] && grep -q "no es un nombre válido" "$T/out"' "nombre de base con caracteres raros → aborta"

echo "Guarda de dev"
printf 'APP_URL=http://192.168.105.11\n' > "$ENVF"
ALLOW_PROVISION=1 run 'abort_if_dev_server'; rc=$?
check '[[ $rc != 0 ]] && grep -q "DESARROLLO (APP_URL" "$T/out"' "APP_URL de dev aborta aunque venga ALLOW_PROVISION=1"
env_prod
FAKE_HOSTNAME=meganet run 'abort_if_dev_server'; rc=$?
check '[[ $rc != 0 ]]' "hostname 'meganet' sin ALLOW_PROVISION → aborta"
FAKE_HOSTNAME=meganet ALLOW_PROVISION=1 run 'abort_if_dev_server'; rc=$?
check '[[ $rc == 0 ]]' "hostname 'meganet' con ALLOW_PROVISION=1 y APP_URL de prod → sigue"

echo "Guarda de grabaciones"
env_prod
run 'abort_if_grabaciones_en_riesgo'; rc=$?
check '[[ $rc == 0 ]] && grep -q "/var/lib/megaisp/grabaciones" "$T/out"' "carpeta por omisión → sigue"
for mala in /var/lib/asterisk/monitor /var/spool/asterisk/monitor /etc/asterisk /var/cache/megaisp-asterisk/g; do
    env_prod; echo "MEGAISP_ASTERISK_GRABACIONES_DIR=${mala}" >> "$ENVF"
    run 'abort_if_grabaciones_en_riesgo'; rc=$?
    check '[[ $rc != 0 ]] && grep -q "ABORTADO sin tocar nada" "$T/out"' "${mala} → aborta"
done
env_prod; echo "MEGAISP_ASTERISK_GRABACIONES_DIR=/var/lib/asterisk-grabaciones" >> "$ENVF"
run 'abort_if_grabaciones_en_riesgo'; rc=$?
check '[[ $rc == 0 ]]' "/var/lib/asterisk-grabaciones (parecido, pero fuera) → sigue"
env_prod; echo "MEGAISP_ASTERISK_GRABACIONES_DIR=grabaciones" >> "$ENVF"
run 'abort_if_grabaciones_en_riesgo'; rc=$?
check '[[ $rc != 0 ]]' "ruta relativa → aborta"
mkdir -p "$T/lnk"; ln -s /var/spool/asterisk/monitor "$T/lnk/grab"
env_prod; echo "MEGAISP_ASTERISK_GRABACIONES_DIR=$T/lnk/grab" >> "$ENVF"
run 'abort_if_grabaciones_en_riesgo'; rc=$?
check '[[ $rc != 0 ]]' "symlink que apunta dentro de /var/spool/asterisk → aborta"

echo "Desinstalar — secretos del .env"
env_prod; printf 'ASTERISK_RT_DB_HOST=127.0.0.1\nASTERISK_RT_DB_PASSWORD=x1\nASTERISK_ARI_USER=medussa\nASTERISK_ARI_PASS=x2\nAMI_SECRET=x3\nAMI_USERNAME=megaisp\n' >> "$ENVF"
rm -f "$T/db/php.log"
run 'desinstalar_env'; rc=$?
check '[[ $rc == 0 ]]' "termina bien"
check '! grep -qE "^(ASTERISK_RT_DB_PASSWORD|ASTERISK_ARI_PASS|AMI_SECRET)=" "$ENVF"' "quita los 3 secretos"
check 'grep -qx "ASTERISK_RT_DB_HOST=127.0.0.1" "$ENVF" && grep -qx "AMI_USERNAME=megaisp" "$ENVF" && grep -qx "APP_URL=https://megaisp.example" "$ENVF"' "deja lo que no es secreto"
check 'ls "$ENVF".purge-voip.*.bak >/dev/null 2>&1 && grep -q "AMI_SECRET=x3" "$ENVF".purge-voip.*.bak' "respalda el .env antes"
check 'grep -q "config:clear" "$T/db/php.log" && grep -q "queue:restart" "$T/db/php.log"' "refresca la config de MegaISP"
run 'desinstalar_env'
check 'grep -q "Nada que quitar" "$T/out"' "segunda corrida: Nada que quitar"

echo "Desinstalar — respaldo de la base"
reset_db; env_prod
run 'desinstalar_respaldo_bd'; rc=$?
check '[[ $rc == 0 ]] && grep -q "Nada que quitar: base de datos" "$T/out"' "sin base → Nada que quitar"

echo
echo "Resultado: ${PASS} bien, ${FAIL} mal"
(( FAIL == 0 ))
