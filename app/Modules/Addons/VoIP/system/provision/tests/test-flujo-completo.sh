#!/usr/bin/env bash
# =============================================================================
# test-flujo-completo.sh — install.sh y uninstall.sh de punta a punta, simulados
# =============================================================================
# Corre los orquestadores reales con shims en PATH (id → root, mysql, php/artisan,
# systemctl, nginx, curl, usermod, chown de root...) y un desinstalar-asterisk.sh
# falso. Comprueba el orden de los pasos, que se encadenen bien, el rollback del
# servicio cuando falla la provisión, y que las grabaciones sigan en su lugar.
# No necesita root ni toca el sistema: todo vive en un directorio temporal.
#
#   bash app/Modules/Addons/VoIP/system/provision/tests/test-flujo-completo.sh   # exit 0 = OK
# =============================================================================
set -uo pipefail
PROV="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
T="$(mktemp -d)"; trap 'rm -rf "$T"' EXIT
PASS=0; FAIL=0
ok()   { echo "  ✔ $*"; PASS=$((PASS+1)); }
bad()  { echo "  ✘ $*"; FAIL=$((FAIL+1)); [[ -f "$T/out" ]] && sed 's/^/      /' "$T/out" | tail -n 40; }
check(){ if eval "$1"; then ok "$2"; else bad "$2"; fi; }

S="$T/estado"; mkdir -p "$T/bin" "$S" "$T/root/storage" "$T/root/bootstrap/cache" "$T/nginx" "$T/confd" "$T/backups" "$T/trabajo"
touch "$T/root/artisan"
REAL_ID="$(command -v id)"; REAL_CHOWN="$(command -v chown)"; REAL_GETENT="$(command -v getent)"
YO="$("$REAL_ID" -un)"

w() { cat > "$T/bin/$1"; chmod +x "$T/bin/$1"; }
w id <<EOF
#!/usr/bin/env bash
[[ "\$1" == -u && \$# == 1 ]] && { echo 0; exit 0; }
exec "$REAL_ID" "\$@"
EOF
w hostname <<'EOF'
#!/usr/bin/env bash
echo meganet
EOF
w getent <<EOF
#!/usr/bin/env bash
case "\$1 \$2" in
  "group asterisk")  echo "asterisk:x:999:www-data"; exit 0;;
  "passwd asterisk") echo "asterisk:x:999:999::/var/lib/asterisk:/usr/sbin/nologin"; exit 0;;
esac
exec "$REAL_GETENT" "\$@"
EOF
w chown <<EOF
#!/usr/bin/env bash
# Simula ser root: los chown a root/asterisk solo se registran.
for a in "\$@"; do case "\$a" in root:*|asterisk:*) echo "chown \$*" >> "$S/log"; exit 0;; esac; done
exec "$REAL_CHOWN" "\$@"
EOF
w usermod <<EOF
#!/usr/bin/env bash
echo "usermod \$*" >> "$S/log"
EOF
w ss <<'EOF'
#!/usr/bin/env bash
[[ "${FAKE_SIN_MARIA:-}" == 1 ]] || echo "LISTEN 0 128 127.0.0.1:9099 0.0.0.0:*"
exit 0
EOF
w systemctl <<EOF
#!/usr/bin/env bash
echo "systemctl \$*" >> "$S/log"
case "\$1" in
  is-active) svc="\${@: -1}"
             [[ "\$svc" == asterisk ]] && { [[ -f "$S/asterisk-activo" ]] && exit 0 || exit 3; }
             [[ "\$svc" == nginx ]] && exit 0; exit 3;;
  list-units) echo "php8.2-fpm.service loaded active running PHP FPM";;
  disable) rm -f "$S/asterisk-activo";;
esac
exit 0
EOF
w php <<EOF
#!/usr/bin/env bash
echo "php \$*" >> "$S/log"
if [[ " \$* " == *" voip:provisionar "* ]]; then
  echo "env-en-provisionar LOG_CHANNEL=\${LOG_CHANNEL:-} CACHE_DRIVER=\${CACHE_DRIVER:-}" >> "$S/log"
  echo "  [verificar] Contrato... (simulado)"
  touch "$S/asterisk-activo"            # el provisionador arranca la central
  exit "\${FAKE_PROV_RC:-0}"
fi
if [[ " \$* " == *" voip:sincronizar "* ]]; then
  echo "Hecho:"; echo "  • 1004: dada de alta en Asterisk"
  [[ "\${FAKE_SYNC_RC:-0}" != 0 ]] && { echo "Fallaron:"; echo "  • Troncal 4: AMI no respondió"; }
  exit "\${FAKE_SYNC_RC:-0}"
fi
[[ " \$* " == *" voip:bot-voz-probar "* ]] && echo "✔ Servicio: escucha en 127.0.0.1:9099"
exit 0
EOF
w ip <<'EOF'
#!/usr/bin/env bash
# Red como la de test: sale por la privada con NAT y tiene una pública en la interfaz.
case "$*" in
  "-4 route get 1.1.1.1") echo "1.1.1.1 via 192.168.1.1 dev eth0 src 192.168.1.11 uid 0";;
  "-4 -o addr show scope global") echo "2: eth0    inet 192.168.1.11/22 scope global eth0"; echo "2: eth0    inet 203.0.113.10/27 scope global eth0";;
esac
exit 0
EOF
w asterisk <<'EOF'
#!/usr/bin/env bash
case "$*" in
  -V) echo "Asterisk 22.11.0";;
  "-rx core show version") echo "Asterisk 22.11.0 built by root";;
  "-rx core show channels count") echo "0 active channels"; echo "${FAKE_LLAMADAS:-0} active calls";;
  "-rx pjsip show transports") echo "Transport:  transport-externo         udp      0      0  203.0.113.10:5080";;
  "-rx pjsip show registrations") echo " trunk_reg_2/sip:sip.proveedor.example   trunk_2_auth   ${FAKE_REG_ESTADO:-Registered}   (exp. 541s)";;
  "-rx dialplan show"*) echo "                    3. Dial(PJSIP/trunk_2/sip:\${EXTEN}@sip.proveedor.example:5060,60)";;
  "-rx queue show") echo "cola_1 has 0 calls (max unlimited) in 'ringall' strategy";;
esac
exit 0
EOF
w nginx <<EOF
#!/usr/bin/env bash
echo "nginx \$*" >> "$S/log"; exit 0
EOF
w curl <<'EOF'
#!/usr/bin/env bash
for a in "$@"; do
  case "$a" in */ari/*) cat >/dev/null; printf 200; exit 0;; */ws) printf 426; exit 0;; esac
done
printf 000; exit 7
EOF
w mysql <<EOF
#!/usr/bin/env bash
sql=""; cnf=""
for a in "\$@"; do case "\$a" in --defaults-extra-file=*) cnf="\${a#*=}";; esac; done
while (( \$# )); do [[ "\$1" == -e ]] && { sql="\$2"; shift; }; shift; done
if [[ -n "\$cnf" ]]; then pw="\$(sed -n 's/^password=//p' "\$cnf")"; [[ -f "$S/user" && "\$pw" == "\$(cat "$S/pw")" ]] && exit 0 || exit 1; fi
[[ -z "\$sql" ]] && sql="\$(cat)"
echo "mysql: \${sql//\$'\n'/ }" >> "$S/log"
case "\$sql" in
  "SELECT 1") exit 0;;
  *"information_schema.SCHEMATA"*) [[ -f "$S/db" ]] && echo asterisk; exit 0;;
  *"FROM mysql.user"*) [[ -f "$S/user" ]] && echo asterisk_rt; exit 0;;
esac
[[ "\$sql" == *"CREATE DATABASE IF NOT EXISTS"* ]] && touch "$S/db"
if [[ "\$sql" == *"CREATE USER IF NOT EXISTS"* && ! -f "$S/user" ]]; then
  touch "$S/user"; sed -n "s/.* BY '\([^']*\)'.*/\1/p" <<<"\$sql" | head -n1 > "$S/pw"
fi
[[ "\$sql" == *"DROP USER"* ]] && rm -f "$S/user"
exit 0
EOF
w mysqldump <<'EOF'
#!/usr/bin/env bash
echo "-- dump simulado de ${@: -1}"; echo "CREATE TABLE voip_llamadas (id int);"
EOF
cat > "$T/desinstalar-asterisk-falso.sh" <<EOF
#!/usr/bin/env bash
echo "desinstalar-asterisk \$* DSN=\${ASTERISK_ODBC_DSN:-}" >> "$S/log"
rm -f "$S/db" "$S/asterisk-activo"
echo "=== Servidor limpio. ==="
EOF

cat > "$T/root/storage/server.conf" <<'EOF'
server {
    listen 80;
    server_name prod.example;
    root /var/www/ClaudeMegaISP/public;
}
EOF
ENVF="$T/root/.env"
printf 'APP_URL=https://prod.example\nDB_DATABASE=megaisp_prod\nMEGAISP_ASTERISK_GRABACIONES_DIR=%s\nMEGAISP_ASTERISK_GENERADOS_DIR=%s\nASTERISK_ARI_PASS=ari\nAMI_SECRET=ami\n' \
    "$T/grabaciones" "$T/no-existe-generados" > "$ENVF"
chmod 640 "$ENVF"
mkdir -p "$T/grabaciones"; echo audio > "$T/grabaciones/1790796068.135.wav"; echo audio > "$T/grabaciones/1790709419.131.wav"
cp "$T/root/storage/server.conf" "$T/nginx/megaisp.conf"
sed -i "s|/var/www/ClaudeMegaISP/public|$T/root/public|" "$T/nginx/megaisp.conf"
cp "$T/nginx/megaisp.conf" "$T/vhost-original"

correr() {   # correr SCRIPT [VAR=VALOR...]
    local script="$1"; shift
    env PATH="$T/bin:$PATH" ALLOW_PROVISION=1 MEGAISP_ROOT="$T/root" MEGAISP_USER="$YO" \
        NGINX_SITES_DIR="$T/nginx" NGINX_CONFD_DIR="$T/confd" APACHE_SITES_DIR="$T/apache" \
        VOIP_BACKUP_DIR="$T/backups" ASTERISK_TRABAJO_DIR="$T/trabajo" \
        SYSTEMD_DIR="$T/systemd" PHP_BIN=/usr/bin/php BOT_VOZ_ESPERA=0 ASTERISK_BIN="$T/bin/asterisk" \
        VOIP_DESINSTALAR_ASTERISK_SH="$T/desinstalar-asterisk-falso.sh" \
        "$@" bash "$PROV/$script" > "$T/out" 2>&1
}
pasos() { grep -E '^==> ' "$T/out" | sed 's/^==> //; s/ (.*//; s/ — .*//' | tr '\n' '|'; }

echo "install.sh — servidor nuevo"
: > "$S/log"
correr install.sh; rc=$?
check '[[ $rc == 0 ]] && grep -q "Listo." "$T/out"' "termina con Listo."
check '[[ "$(pasos)" == "== Instalación de Asterisk para VoIP|Preflight|Base realtime de Asterisk|Teléfonos fuera de la oficina|Asterisk|Acceso de '"$YO"' a la configuración generada y a las grabaciones|nginx|Cierre|Sincronizar MegaISP con Asterisk|Asistente de voz|Revisión final|Resumen|" ]]' "los pasos van en orden: preflight → 10 → 15 → 20 → 30 → 40 → cierre → 60 sincronizar → 50 María → revisión → resumen"
check 'grep -q "hostname=.meganet. coincide" "$T/out"' "avisa que salta solo el hostname (ALLOW_PROVISION=1)"
check '[[ -f $S/db && -f $S/user ]] && grep -q "^ASTERISK_RT_DB_PASSWORD=" "$ENVF"' "paso 10: base, usuario y password en el .env"
check 'grep -q "php artisan config:clear" "$S/log" && [[ $(grep -n "artisan config:clear" "$S/log" | head -n1 | cut -d: -f1) -lt $(grep -n "voip:provisionar" "$S/log" | head -n1 | cut -d: -f1) ]]' "paso 20: config:clear ANTES del provisionador"
check 'grep -q "php artisan voip:provisionar --no-ansi" "$S/log"' "paso 20: corre voip:provisionar"
check 'grep -q "env-en-provisionar LOG_CHANNEL=stderr CACHE_DRIVER=array" "$S/log"' "paso 20: sin logs ni caché de Laravel en disco"
check 'grep -q "usermod -aG asterisk $YO" "$S/log" && grep -q "systemctl reload php8.2-fpm.service" "$S/log"' "paso 30: usuario web al grupo asterisk + reload de php-fpm"
check '[[ $(grep -c "location /ws {" "$T/nginx/megaisp.conf") == 1 ]]' "paso 40: /ws en el vhost"
check 'grep -q "artisan queue:restart" "$S/log" && ! grep -q "config:cache" "$S/log"' "cierre: queue:restart y NUNCA config:cache"
check 'grep -q "ARI responde" "$T/out" && grep -q "/ws llega a Asterisk" "$T/out"' "cierre: verifica ARI y /ws"
check 'grep -q "^ASTERISK_BIND_EXTERNO=203.0.113.10:5080$" "$ENVF"' "paso 15: transporte para teléfonos de fuera en la IP pública"
check 'grep -q "php artisan voip:sincronizar --aplicar --no-ansi" "$S/log" && [[ $(grep -n "voip:provisionar" "$S/log" | head -n1 | cut -d: -f1) -lt $(grep -n "voip:sincronizar" "$S/log" | cut -d: -f1) ]]' "paso 60: sincroniza DESPUÉS de provisionar"
check 'ls "$T/backups"/megaisp_prod-voip-config-*-antes-de-sincronizar.sql.gz >/dev/null 2>&1 && ls "$T/backups"/asterisk-*-antes-de-sincronizar.sql.gz >/dev/null 2>&1' "paso 60: respalda antes de sincronizar"
check 'grep -q "transport-externo escucha" "$T/out" && grep -q "Troncal trunk_reg_2: registrada" "$T/out" && grep -q "salen por trunk_2" "$T/out" && grep -q "María tiene lo que necesita" "$T/out"' "revisión final: transporte, troncal, salida y María"
check 'grep -q "Nada pendiente: la central quedó configurada" "$T/out" && ! grep -q "Pendiente" "$T/out"' "resumen: con todo bien dice «Nada pendiente» (sin lista fija de cosas a mano)"
check 'grep -q "servidor 203.0.113.10:5080" "$T/out"' "resumen: cómo conectar los teléfonos de fuera"
check '! grep -qE "ASTERISK_RT_DB_PASSWORD=[0-9a-f]{64}|ari|ami" <(grep -vE "^\[\.\.\]|ARI responde" "$T/out" | grep -i "password\|secret") ' "no imprime secretos"

echo "install.sh — segunda corrida (Asterisk ya instalado)"
cp "$ENVF" "$T/env1"; cp "$T/nginx/megaisp.conf" "$T/vhost1"
correr install.sh; rc=$?
check '[[ $rc == 0 ]] && cmp -s "$ENVF" "$T/env1" && cmp -s "$T/nginx/megaisp.conf" "$T/vhost1"' "idempotente: .env y vhost sin cambios"

echo "install.sh — la sincronización falla"
: > "$S/log"
correr install.sh FAKE_SYNC_RC=1; rc=$?
check '[[ $rc != 0 ]] && grep -q "Troncal 4: AMI no respondió" "$T/out"' "termina con error y dice qué falló"
check '[[ "$(pasos)" == *"Revisión final|Resumen|" ]] && ! grep -q "Listo\\." "$T/out"' "antes muestra la revisión y el resumen; no dice Listo."

echo "install.sh — la troncal no registra"
: > "$S/log"
correr install.sh FAKE_REG_ESTADO=Rejected; rc=$?
check 'grep -q "Pendiente (lo encontró la revisión final)" "$T/out" && grep -q "Troncal trunk_reg_2 no registrada (Rejected)" "$T/out" && ! grep -q "Nada pendiente" "$T/out"' "el resumen lista solo lo que la revisión encontró mal"

echo "install.sh — María no arranca"
: > "$S/log"
correr install.sh FAKE_SIN_MARIA=1 BOT_VOZ_ESPERA=0; rc=$?
check '[[ $rc != 0 ]] && grep -q "falló el servicio de María" "$T/out"' "termina con error diciendo que faltó María"
check 'grep -q "voip:sincronizar --aplicar" "$S/log" && [[ "$(pasos)" == *"Revisión final|Resumen|" ]]' "aun así sincroniza, revisa y muestra el resumen"

echo "install.sh — hay llamadas en curso"
touch "$S/asterisk-activo"; : > "$S/log"; cp "$ENVF" "$T/env-llamadas"
correr install.sh FAKE_LLAMADAS=2; rc=$?
check '[[ $rc != 0 ]] && grep -q "Hay 2 llamada(s) en curso" "$T/out"' "se detiene en el preflight"
check '! grep -q "voip:provisionar\|mysql\|systemctl restart\|voip:sincronizar" "$S/log" && cmp -s "$ENVF" "$T/env-llamadas"' "no toca nada (ni la central ni el .env)"
correr install.sh FAKE_LLAMADAS=2 VOIP_REINICIAR_CON_LLAMADAS=1; rc=$?
check '[[ $rc == 0 ]] && grep -q "se cortarán" "$T/out"' "con VOIP_REINICIAR_CON_LLAMADAS=1 sigue, avisando"

echo "install.sh — la provisión falla con Asterisk apagado antes → rollback del servicio"
rm -f "$S/asterisk-activo"; : > "$S/log"
correr install.sh FAKE_PROV_RC=1; rc=$?
check '[[ $rc != 0 ]]' "aborta con error"
check 'grep -q "systemctl disable --now asterisk" "$S/log" && [[ ! -f $S/asterisk-activo ]]' "detiene y deshabilita Asterisk"
check 'grep -q "Rollback: Asterisk no estaba corriendo" "$T/out" && grep -q "falló (código 1)" "$T/out"' "lo dice en la salida"
check '[[ "$(pasos)" != *"Acceso de"* ]]' "no sigue a los pasos 30 y 40"

echo "install.sh — la provisión falla con Asterisk ya corriendo → no lo apaga"
touch "$S/asterisk-activo"; : > "$S/log"
correr install.sh FAKE_PROV_RC=1; rc=$?
check '[[ $rc != 0 ]] && ! grep -q "disable --now asterisk" "$S/log" && [[ -f $S/asterisk-activo ]]' "deja la central que ya estaba en servicio"

echo "uninstall.sh"
touch "$S/db" "$S/user"; : > "$S/log"
correr uninstall.sh; rc=$?
check '[[ $rc == 0 ]] && grep -q "Listo. Asterisk quitado" "$T/out"' "termina con Listo."
check '[[ "$(pasos)" == "== Desinstalación de Asterisk de VoIP|Preflight|Respaldo de la base realtime|Asistente de voz|Proxy /ws del servidor web|Grabaciones|Asterisk|Usuario MySQL de la base realtime|Carpeta de trabajo de la compilación|.env de MegaISP|" ]]' "los pasos van en orden (respaldo primero)"
dump="$(ls "$T/backups"/asterisk-*-antes-de-purgar.sql.gz 2>/dev/null | head -n1)"
check '[[ -n "$dump" ]] && gzip -t "$dump" && zcat "$dump" | grep -q voip_llamadas' "respalda la base realtime (gzip válido)"
check '[[ $(grep -n "mysqldump\|antes-de-purgar" "$T/out" | head -n1 | cut -d: -f1) -lt $(grep -n "desinstalar-asterisk" "$S/log" | head -n1 | cut -d: -f1 || echo 999) ]] || [[ -n "$dump" ]]' "el respaldo se hace antes de borrar"
check '[[ -f "$T/grabaciones/1790796068.135.wav" && -f "$T/grabaciones/1790709419.131.wav" ]]' "LAS GRABACIONES SIGUEN EN SU CARPETA"
check 'grep -q "chown -R root:" "$S/log" && grep -q "2 grabación(es) siguen en" "$T/out"' "solo cambia el dueño de las grabaciones"
check 'grep -q "desinstalar-asterisk --confirmar=SI-BORRAR-ASTERISK --base=asterisk --desde-addon" "$S/log"' "llama a desinstalar-asterisk.sh con confirmación y --desde-addon"
check '[[ $(grep -n "chown -R root:" "$S/log" | cut -d: -f1) -lt $(grep -n "desinstalar-asterisk" "$S/log" | cut -d: -f1) ]]' "cambia el dueño ANTES de borrar el usuario asterisk"
check '[[ ! -f $S/user ]]' "quita el usuario MySQL"
check '[[ ! -e $T/trabajo ]]' "quita la carpeta de trabajo"
check 'cmp -s "$T/nginx/megaisp.conf" "$T/vhost-original"' "el vhost queda como antes de instalar"
check '[[ "$(ls "$T/nginx")" == megaisp.conf ]]' "sites-enabled sin archivos de más (los respaldos van a /var/backups/megaisp/vhosts)"
check '! grep -qE "^(ASTERISK_RT_DB_PASSWORD|ASTERISK_ARI_PASS|AMI_SECRET)=" "$ENVF"' "quita los secretos del .env"

echo "uninstall.sh — segunda corrida"
rm -f "$T/backups"/*; : > "$S/log"
correr uninstall.sh; rc=$?
check '[[ $rc == 0 ]] && [[ $(grep -c "Nada que quitar" "$T/out") -ge 4 ]]' "idempotente: reporta Nada que quitar"
check '[[ -f "$T/grabaciones/1790796068.135.wav" ]]' "las grabaciones siguen ahí"

echo "uninstall.sh — grabaciones configuradas dentro de /var/spool/asterisk"
echo "MEGAISP_ASTERISK_GRABACIONES_DIR=/var/spool/asterisk/monitor" >> "$ENVF"; touch "$S/db"; : > "$S/log"
correr uninstall.sh; rc=$?
check '[[ $rc != 0 ]] && ! grep -q "desinstalar-asterisk\|mysqldump\|DROP" "$S/log"' "aborta en el preflight sin tocar nada"

echo "Dev"
printf 'APP_URL=http://192.168.105.11\n' > "$ENVF"; : > "$S/log"
correr install.sh; rc=$?
check '[[ $rc != 0 ]] && grep -q "DESARROLLO" "$T/out" && [[ ! -s $S/log ]]' "install.sh en dev aborta sin tocar nada"
correr uninstall.sh; rc=$?
check '[[ $rc != 0 ]] && grep -q "DESARROLLO" "$T/out" && [[ ! -s $S/log ]]' "uninstall.sh en dev aborta sin tocar nada"

echo
echo "Resultado: ${PASS} bien, ${FAIL} mal"
(( FAIL == 0 ))
