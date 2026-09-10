#!/usr/bin/env bash
#
# ETAPA 1 — Instalación de Asterisk 22.11.0 LTS desde fuente
# Servidor: meganet (192.168.105.108) — PRODUCCIÓN
# Requiere: snapshot de Proxmox tomado (confirmado por Irving 09/09/2026)
#
# IMPORTANTE: este script NO arranca el servicio. El firewall y fail2ban
# (Etapa 6) deben montarse antes de que Asterisk escuche en la red.
#
set -euo pipefail

WORK="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
TARBALL="$WORK/asterisk-22.11.0.tar.gz"
SHA_ESPERADO="3bd5ee040509a3d3cd9b1ba9520c18e6ec0a7e7981ca68c457dcd36ba3c54d94"
SRCDIR="/usr/src/asterisk-22.11.0"
LOG="$WORK/etapa1-$(date +%Y%m%d-%H%M%S).log"

exec > >(tee -a "$LOG") 2>&1
echo "=== ETAPA 1 — inicio $(date -Is) ==="

# ── 0. Verificaciones previas ────────────────────────────────────────────
[[ $EUID -eq 0 ]] || { echo "ERROR: ejecutar como root (sudo bash $0)"; exit 1; }
[[ -f "$TARBALL" ]] || { echo "ERROR: no encuentro $TARBALL"; exit 1; }

echo "--- verificando integridad del tarball ---"
echo "${SHA_ESPERADO}  ${TARBALL}" | sha256sum -c - || {
    echo "ERROR: el hash del tarball NO coincide. Abortando."; exit 1; }

echo "--- espacio libre antes de empezar ---"
df -h / | tail -1

# ── 1. Dependencias de compilación ───────────────────────────────────────
# Lista explícita y acotada: NO usamos install_prereq, que arrastra de más.
echo "--- instalando dependencias ---"
export DEBIAN_FRONTEND=noninteractive
apt-get update
apt-get install -y --no-install-recommends \
    build-essential autoconf automake libtool pkg-config \
    libedit-dev libjansson-dev libsqlite3-dev uuid-dev \
    libxml2-dev libssl-dev libcurl4-openssl-dev libsrtp2-dev \
    libncurses-dev unixodbc unixodbc-dev \
    ca-certificates wget

# ── 2. Usuario dedicado del sistema (nunca root) ─────────────────────────
if ! id asterisk >/dev/null 2>&1; then
    echo "--- creando usuario de sistema 'asterisk' ---"
    groupadd --system asterisk
    useradd --system --gid asterisk --no-create-home \
            --home-dir /var/lib/asterisk --shell /usr/sbin/nologin asterisk
else
    echo "--- usuario 'asterisk' ya existe, se respeta ---"
fi

# ── 3. Extraer fuente ────────────────────────────────────────────────────
echo "--- extrayendo en /usr/src ---"
rm -rf "$SRCDIR"
tar xzf "$TARBALL" -C /usr/src
cd "$SRCDIR"

# ── 4. configure ─────────────────────────────────────────────────────────
# pjproject y jansson bundled: PJSIP necesita una pjproject compilada
# específicamente para Asterisk; la del sistema no sirve.
echo "--- ./configure ---"
./configure \
    --with-pjproject-bundled \
    --with-jansson-bundled \
    --libdir=/usr/lib/x86_64-linux-gnu

# ── 5. menuselect ────────────────────────────────────────────────────────
echo "--- generando menuselect.makeopts ---"
make menuselect.makeopts

MS="menuselect/menuselect"

# Estricto: si alguno de estos no se puede habilitar, abortamos.
# Son los módulos que el diseño del conmutador exige.
echo "--- habilitando módulos requeridos (estricto) ---"
$MS \
    --enable chan_pjsip \
    --enable res_pjsip \
    --enable res_pjsip_session \
    --enable res_pjsip_registrar \
    --enable res_pjsip_outbound_registration \
    --enable res_pjsip_authenticator_digest \
    --enable res_pjsip_endpoint_identifier_ip \
    --enable res_pjsip_endpoint_identifier_user \
    --enable res_odbc \
    --enable res_config_odbc \
    --enable cdr_adaptive_odbc \
    --enable res_agi \
    --enable app_mixmonitor \
    --enable app_queue \
    --enable app_dial \
    --enable codec_alaw \
    --enable codec_ulaw \
    --enable format_wav \
    --enable format_gsm \
    menuselect.makeopts

# BUILD_NATIVE off: esta es una VM QEMU. Compilar con instrucciones nativas
# del host puede reventar si la VM migra a otro nodo del cluster.
echo "--- desactivando BUILD_NATIVE (VM) ---"
$MS --disable BUILD_NATIVE menuselect.makeopts

# Sonidos: alaw, que es el códec de la plataforma. En GSM habría que
# transcodificar cada prompt en tiempo real. Español para el cliente
# final, inglés como respaldo por si algún prompt no existe en español.
# Estricto: los tres existen en 22.11.0 y son parte del diseño.
echo "--- habilitando sonidos en alaw (ES + EN respaldo) ---"
$MS \
    --enable CORE-SOUNDS-ES-ALAW \
    --enable CORE-SOUNDS-EN-ALAW \
    --enable MOH-OPSOUND-ALAW \
    menuselect.makeopts

# Todo lo demás fuera, incluidas las variantes GSM que ya no se usan.
echo "--- descartando el resto de variantes de sonido ---"
for s in CORE-SOUNDS-ES-GSM CORE-SOUNDS-ES-WAV CORE-SOUNDS-ES-ULAW \
         CORE-SOUNDS-ES-G722 CORE-SOUNDS-ES-G729 CORE-SOUNDS-ES-SLN16 \
         CORE-SOUNDS-ES-SIREN7 CORE-SOUNDS-ES-SIREN14 \
         CORE-SOUNDS-EN-GSM CORE-SOUNDS-EN-WAV CORE-SOUNDS-EN-ULAW \
         CORE-SOUNDS-EN-G722 CORE-SOUNDS-EN-G729 CORE-SOUNDS-EN-SLN16 \
         CORE-SOUNDS-EN-SIREN7 CORE-SOUNDS-EN-SIREN14 \
         MOH-OPSOUND-GSM MOH-OPSOUND-WAV MOH-OPSOUND-ULAW \
         MOH-OPSOUND-G722 MOH-OPSOUND-G729 MOH-OPSOUND-SLN16 \
         MOH-OPSOUND-SIREN7 MOH-OPSOUND-SIREN14; do
    $MS --disable "$s" menuselect.makeopts 2>/dev/null || true
done

echo "--- resumen: módulos clave que quedaron habilitados ---"
grep -E 'MENUSELECT_(CORE_SOUNDS|MOH)' menuselect.makeopts || true

# ── 6. Compilar e instalar ───────────────────────────────────────────────
echo "--- make -j$(nproc) (esto tarda; paciencia) ---"
make -j"$(nproc)"

echo "--- make install ---"
make install

echo "--- make samples (plantillas base; la config real va en Etapa 5) ---"
make samples

ldconfig

# ── 7. Correr como usuario 'asterisk', no root ───────────────────────────
echo "--- fijando runuser/rungroup en asterisk.conf ---"
sed -i -E 's/^;?\s*runuser\s*=.*/runuser = asterisk/'   /etc/asterisk/asterisk.conf
sed -i -E 's/^;?\s*rungroup\s*=.*/rungroup = asterisk/' /etc/asterisk/asterisk.conf
grep -E '^(runuser|rungroup)' /etc/asterisk/asterisk.conf

echo "--- ajustando propietario de directorios ---"
chown -R asterisk:asterisk \
    /etc/asterisk /var/lib/asterisk /var/log/asterisk \
    /var/spool/asterisk /usr/lib/x86_64-linux-gnu/asterisk 2>/dev/null || true
chmod -R u=rwX,g=rX,o= /etc/asterisk

# ── 8. Unit de systemd (sin habilitar, sin arrancar) ─────────────────────
echo "--- instalando unit de systemd ---"
cat > /etc/systemd/system/asterisk.service <<'UNIT'
[Unit]
Description=Asterisk PBX
Documentation=man:asterisk(8)
Wants=network-online.target mysql.service
After=network-online.target mysql.service

[Service]
Type=simple
User=asterisk
Group=asterisk
ExecStart=/usr/sbin/asterisk -f -C /etc/asterisk/asterisk.conf
ExecReload=/usr/sbin/asterisk -rx 'core reload'
Restart=on-failure
RestartSec=5
# Endurecimiento básico
NoNewPrivileges=true
PrivateTmp=true
ProtectSystem=full
ProtectHome=true

[Install]
WantedBy=multi-user.target
UNIT

systemctl daemon-reload
# Deliberadamente NO se hace 'enable' ni 'start':
# el servicio se levanta hasta tener firewall + fail2ban (Etapa 6).
systemctl disable asterisk 2>/dev/null || true

# ── 9. Validación de la etapa ────────────────────────────────────────────
echo
echo "=== VALIDACIÓN ETAPA 1 ==="
echo "--- versión instalada ---"
/usr/sbin/asterisk -V
echo "--- binario y usuario ---"
ls -l /usr/sbin/asterisk
id asterisk
echo "--- módulos ODBC compilados ---"
ls -1 /usr/lib/x86_64-linux-gnu/asterisk/modules/ 2>/dev/null \
    | grep -E 'res_odbc|res_config_odbc|cdr_adaptive_odbc' || echo "OJO: faltan módulos ODBC"
echo "--- módulos PJSIP compilados (conteo) ---"
ls -1 /usr/lib/x86_64-linux-gnu/asterisk/modules/ 2>/dev/null \
    | grep -c '^res_pjsip' || true
ls -1 /usr/lib/x86_64-linux-gnu/asterisk/modules/chan_pjsip.so 2>/dev/null \
    || echo "OJO: falta chan_pjsip"
echo "--- sonidos instalados: español en alaw ---"
ls -1 /var/lib/asterisk/sounds/es/*.alaw 2>/dev/null | wc -l \
    | xargs -I{} echo "prompts es/*.alaw: {}"
echo "--- sonidos instalados: inglés de respaldo ---"
ls -1 /var/lib/asterisk/sounds/en/*.alaw 2>/dev/null | wc -l \
    | xargs -I{} echo "prompts en/*.alaw: {}"
echo "--- música en espera ---"
ls -1 /var/lib/asterisk/moh/*.alaw 2>/dev/null | wc -l \
    | xargs -I{} echo "moh/*.alaw: {}"
echo "--- no debe quedar ningún .gsm ---"
find /var/lib/asterisk/sounds /var/lib/asterisk/moh -name '*.gsm' 2>/dev/null | wc -l \
    | xargs -I{} echo "archivos .gsm encontrados: {} (esperado 0)"
echo "--- peso de los sonidos ---"
du -sh /var/lib/asterisk/sounds /var/lib/asterisk/moh 2>/dev/null || true
echo "--- estado del servicio (debe estar inactivo y disabled) ---"
systemctl is-active asterisk || true
systemctl is-enabled asterisk || true
echo "--- espacio libre después ---"
df -h / | tail -1
echo
echo "=== ETAPA 1 terminada $(date -Is) ==="
echo "Log completo: $LOG"
echo "El servicio NO fue arrancado. Siguiente: Etapa 2 (base realtime)."
