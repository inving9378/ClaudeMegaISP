#!/usr/bin/env bash
# =============================================================================
# test-60-sincronizar.sh — prueba de lib/60-sincronizar.sh
# =============================================================================
# systemctl, asterisk, php (artisan), mysqldump SIMULADOS (shims en PATH). No
# necesita root ni toca Asterisk ni la base.
#
#   bash app/Modules/Addons/VoIP/system/provision/tests/test-60-sincronizar.sh   # exit 0 = OK
# =============================================================================
set -uo pipefail
LIB="$(cd "$(dirname "${BASH_SOURCE[0]}")/../lib" && pwd)"
T="$(mktemp -d)"; trap 'rm -rf "$T"' EXIT
PASS=0; FAIL=0
ok()   { echo "  ✔ $*"; PASS=$((PASS+1)); }
bad()  { echo "  ✘ $*"; FAIL=$((FAIL+1)); [[ -f "$T/out" ]] && sed 's/^/      /' "$T/out"; }
check(){ if eval "$1"; then ok "$2"; else bad "$2"; fi; }

mkdir -p "$T/bin" "$T/root"
cat > "$T/bin/systemctl" <<'SH'
#!/usr/bin/env bash
echo "systemctl $*" >> "$FAKE_LOG"
[[ "$1" == is-active ]] && { [[ "${FAKE_ASTERISK_ACTIVO:-1}" == 1 ]] && exit 0 || exit 3; }
exit 0
SH
cat > "$T/bin/asterisk" <<'SH'
#!/usr/bin/env bash
case "$2" in
  "pjsip show transports")
     echo "Transport:  transport-udp             udp      0      0  0.0.0.0:5060"
     [[ "${FAKE_EXTERNO:-1}" == 1 ]] && echo "Transport:  transport-externo         udp      0      0  203.0.113.10:5080";;
  "pjsip show registrations")
     echo " <Registration/ServerURI..............................>  <Auth....................>  <Status.......>"
     echo "=========================================================================================="
     echo
     echo " trunk_reg_2/sip:sip.proveedor.example                   trunk_2_auth                Registered        (exp. 541s)"
     [[ -n "${FAKE_RECHAZADA:-}" ]] && echo " trunk_reg_4/sip:192.0.2.9                              trunk_4_auth                Rejected          (exp. 0s)"
     echo; echo "Objects found: 2";;
  "dialplan show _[2-9]XXXXXXXXX@from-internal")
     if [[ "${FAKE_SALIDA:-1}" == 1 ]]; then
       echo "[ Context 'from-internal' created by 'pbx_config' ]"
       echo "  '_[2-9]XXXXXXXXX' => 1. NoOp(Saliente) [megaisp_grupos.conf:61]"
       echo "                    3. Dial(PJSIP/trunk_2/sip:\${EXTEN}@sip.proveedor.example:5060,60) [megaisp_grupos.conf:63]"
     else
       echo "There is no existence of '_[2-9]XXXXXXXXX' extension in context 'from-internal'"
     fi;;
  "queue show")
     echo "cola_1 has 0 calls (max unlimited) in 'ringall' strategy (5s holdtime, 11s talktime), W:0, C:9, A:6"
     echo "   Members: ";;
esac
exit 0
SH
cat > "$T/bin/php" <<'SH'
#!/usr/bin/env bash
echo "php $*" >> "$FAKE_LOG"
case " $* " in
  *" voip:sincronizar "*)
     echo "Hecho:"; echo "  • 1004: dada de alta en Asterisk"
     if [[ "${FAKE_SYNC_RC:-0}" != 0 ]]; then echo "Fallaron:"; echo "  • Troncal 4: AMI no respondió"; fi
     exit "${FAKE_SYNC_RC:-0}";;
  *" voip:bot-voz-probar "*)
     echo "✔ Servicio: escucha en 127.0.0.1:9099"
     [[ "${FAKE_BOT_RC:-0}" != 0 ]] && echo "✘ Voz (lo que dice): la integración «OpenAI» no tiene clave."
     exit "${FAKE_BOT_RC:-0}";;
esac
exit 0
SH
cat > "$T/bin/mysqldump" <<'SH'
#!/usr/bin/env bash
echo "mysqldump $*" >> "$FAKE_LOG"
for a in "$@"; do case "$a" in --defaults-extra-file=*) f="${a#*=}"; stat -c 'cnf %a' "$f" >> "$FAKE_LOG"; cat "$f" >> "$FAKE_LOG.cnf";; esac; done
[[ "${FAKE_DUMP_RC:-0}" != 0 ]] && exit 1
echo "-- dump simulado: ${*: -2}"
SH
chmod +x "$T/bin/"*
export PATH="$T/bin:$PATH" FAKE_LOG="$T/fake.log"
YO="$(id -un)"

ENVF="$T/root/.env"
printf 'APP_URL=https://megaisp.example\nDB_DATABASE=megaisp_prod\nDB_USERNAME=megaisp\nDB_PASSWORD='"'"'p#ss"w0rd'"'"'\nASTERISK_BIND_EXTERNO=203.0.113.10:5080\n' > "$ENVF"

run() {
    : > "$FAKE_LOG"; rm -f "$FAKE_LOG.cnf"
    MEGAISP_ROOT="$T/root" MEGAISP_USER="$YO" VOIP_BACKUP_DIR="$T/backups" ASTERISK_BIN="$T/bin/asterisk" \
    bash -c "set -euo pipefail; source '$LIB/common.sh'; source '$LIB/60-sincronizar.sh'; $1" > "$T/out" 2>&1
}

echo "1) Sincroniza con todo en orden"
run 'provision_sincronizar; echo "FALLAS=$SINCRONIZAR_FALLAS"'; rc=$?
check '[[ $rc == 0 ]] && grep -q "FALLAS=0" "$T/out" && grep -q "MegaISP y Asterisk alineados" "$T/out"' "termina bien"
check 'grep -q "php artisan voip:sincronizar --aplicar --no-ansi" "$FAKE_LOG"' "corre voip:sincronizar --aplicar como el usuario web"
check 'grep -q "1004: dada de alta" "$T/out"' "muestra lo que hizo"
check 'ls "$T/backups"/asterisk-*-antes-de-sincronizar.sql.gz >/dev/null 2>&1' "respalda la base realtime"
dump="$(ls "$T/backups"/megaisp_prod-voip-config-*-antes-de-sincronizar.sql.gz 2>/dev/null | head -n1)"
check '[[ -n "$dump" ]] && gzip -t "$dump" && zcat "$dump" | grep -q "voip_extensiones voip_troncales"' "respalda voip_extensiones y voip_troncales de MegaISP (gzip válido)"
check '[[ $(grep -n "mysqldump" "$FAKE_LOG" | tail -n1 | cut -d: -f1) -lt $(grep -n "voip:sincronizar" "$FAKE_LOG" | cut -d: -f1) ]]' "los respaldos van ANTES de sincronizar"
check '! grep -q "p#ss" "$FAKE_LOG" && grep -q "^cnf 600" "$FAKE_LOG"' "la contraseña de la base va en archivo 600, nunca en argv"
check 'grep -qxF '"'"'password="p#ss\"w0rd"'"'"' "$FAKE_LOG.cnf"' "la contraseña va entre comillas y escapada (# y \" no la cortan)"
check '[[ "$(stat -c %a "$T/backups")" == 700 ]]' "carpeta de respaldos 700"
check '[[ -z "$(find /tmp -maxdepth 1 -newer "$ENVF" -name "tmp.*" -user "$YO" -exec grep -l "p#ss" {} + 2>/dev/null)" ]]' "no deja el archivo de credenciales"

echo "2) Una parte falla"
FAKE_SYNC_RC=1 run 'provision_sincronizar; echo "FALLAS=$SINCRONIZAR_FALLAS"'; rc=$?
check '[[ $rc == 0 ]] && grep -q "FALLAS=1" "$T/out"' "no se detiene ahí: marca la falla para el final"
check 'grep -q "Troncal 4: AMI no respondió" "$T/out" && grep -q "Respaldos:" "$T/out"' "dice qué falló y dónde quedaron los respaldos"

echo "3) Asterisk apagado"
FAKE_ASTERISK_ACTIVO=0 run provision_sincronizar; rc=$?
check '[[ $rc != 0 ]] && ! grep -q "mysqldump\|voip:sincronizar" "$FAKE_LOG"' "aborta sin respaldar ni sincronizar"

echo "4) No se pudo respaldar"
FAKE_DUMP_RC=1 run provision_sincronizar; rc=$?
check '[[ $rc != 0 ]] && ! grep -q "voip:sincronizar" "$FAKE_LOG"' "aborta sin sincronizar"

echo "5) Revisión final con todo bien"
run verificar_telefonia; rc=$?
check '[[ $rc == 0 ]]' "termina bien"
check 'grep -q "transport-externo escucha en 203.0.113.10:5080" "$T/out"' "teléfonos de fuera: transporte arriba"
check 'grep -q "Troncal trunk_reg_2: registrada" "$T/out"' "troncal registrada"
check 'grep -q "salen por trunk_2" "$T/out"' "salida a la calle y por qué troncal"
check 'grep -q "Colas en Asterisk: 1" "$T/out"' "cuenta las colas"
check 'grep -q "php artisan voip:bot-voz-probar --revisar --no-ansi" "$FAKE_LOG" && grep -q "María tiene lo que necesita" "$T/out"' "revisa a María sin llamar a OpenAI"

echo "6) Revisión final con problemas (solo avisa)"
FAKE_EXTERNO=0 FAKE_RECHAZADA=1 FAKE_SALIDA=0 FAKE_BOT_RC=1 run verificar_telefonia; rc=$?
check '[[ $rc == 0 ]]' "no detiene la instalación"
check 'grep -q "no levantó transport-externo" "$T/out"' "avisa que falta el transporte"
check 'grep -q "Troncal trunk_reg_4: Rejected" "$T/out"' "avisa de la troncal rechazada"
check 'grep -q "No hay salida a la calle" "$T/out"' "avisa que no hay salida"
check 'grep -q "no tiene clave" "$T/out" && grep -q "A María le falta" "$T/out"' "muestra lo que le falta a María"

echo
echo "Resultado: ${PASS} bien, ${FAIL} mal"
(( FAIL == 0 ))
