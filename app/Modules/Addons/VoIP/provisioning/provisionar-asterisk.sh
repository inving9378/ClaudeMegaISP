#!/usr/bin/env bash
#
# ═══════════════════════════════════════════════════════════════════════════
# provisionar-asterisk.sh — instala Asterisk en el servidor donde corre MegaISP
# ═══════════════════════════════════════════════════════════════════════════
#
# Deriva de `referencia/etapa1-instalar-asterisk22.sh`, que conserva las
# decisiones de compilación originales y NO se modifica. Aquí se le aplican los
# seis ajustes que lo vuelven ejecutable en cualquier servidor, no solo en aquel
# para el que fue escrito.
#
# ─── NO TRAE DATOS PROPIOS ────────────────────────────────────────────────
#
# Todo lo que varía llega por entorno, desde `config/requisitos-voip.php`, que es
# el manifiesto que viaja con la actualización. Este script no sabe qué versión
# instala ni de dónde la baja: se lo dicen.
#
#   ASTERISK_VERSION        22.11.0
#   ASTERISK_ORIGEN         https://…/artefactos/asterisk
#   ASTERISK_ARCHIVO        asterisk-22.11.0.tar.gz
#   ASTERISK_SHA256         hash esperado — LA ÚNICA FUENTE, viene de git
#   ASTERISK_IDIOMA         es
#   ASTERISK_ESPACIO_MIN_MB 3072
#   ASTERISK_TRABAJO        directorio de descarga (default: mktemp -d)
#
# Por eso el sha256 NO está embebido aquí (ajuste 5): en dos lugares acabaría
# divergiendo del manifiesto, y el día que difieran nadie sabría cuál manda.
#
# ─── EL SERVICIO NO SE ARRANCA ────────────────────────────────────────────
#
# Igual que el original: se instala la unit y se deja DESHABILITADA. Un Asterisk
# que empieza a escuchar antes de tener firewall es una invitación al fraude
# telefónico. Quien decide arrancarlo es el provisionador de PHP, después de
# escribir la configuración.
#
set -euo pipefail

# ── Parámetros, todos con validación ─────────────────────────────────────
: "${ASTERISK_VERSION:?falta ASTERISK_VERSION (lo pasa el manifiesto)}"
: "${ASTERISK_ORIGEN:?falta ASTERISK_ORIGEN}"
: "${ASTERISK_ARCHIVO:?falta ASTERISK_ARCHIVO}"
: "${ASTERISK_SHA256:?falta ASTERISK_SHA256 — el hash viene del manifiesto, nunca del sitio de descarga}"
: "${ASTERISK_IDIOMA:=es}"
: "${ASTERISK_ESPACIO_MIN_MB:=3072}"
: "${ASTERISK_TRABAJO:=}"

SRCDIR="/usr/src/asterisk-${ASTERISK_VERSION}"

# Ajuste 3 — libdir derivado, no fijo a x86_64-linux-gnu.
if command -v dpkg-architecture >/dev/null 2>&1; then
    MULTIARCH="$(dpkg-architecture -qDEB_HOST_MULTIARCH)"
else
    MULTIARCH="$(gcc -print-multiarch 2>/dev/null || echo "$(uname -m)-linux-gnu")"
fi
LIBDIR="/usr/lib/${MULTIARCH}"
MODDIR="${LIBDIR}/asterisk/modules"

# Ajuste 6 — la cabecera no nombra ningún servidor: se reporta el que sea.
echo "=== Provisión de Asterisk ${ASTERISK_VERSION} — inicio $(date -Is) ==="
echo "    servidor : $(hostname)"
echo "    arquitect: ${MULTIARCH}  →  libdir ${LIBDIR}"
echo "    origen   : ${ASTERISK_ORIGEN}"

# ── 0. Verificaciones previas ────────────────────────────────────────────
[[ $EUID -eq 0 ]] || { echo "ERROR: ejecutar como root."; exit 1; }

# Ajuste 4 — el espacio ABORTA, no solo informa.
LIBRE_MB="$(df -Pm /usr/src | awk 'NR==2{print $4}')"
echo "--- espacio libre en /usr/src: ${LIBRE_MB} MB (mínimo ${ASTERISK_ESPACIO_MIN_MB}) ---"
if (( LIBRE_MB < ASTERISK_ESPACIO_MIN_MB )); then
    echo "ERROR: espacio insuficiente. Hay ${LIBRE_MB} MB y se requieren ${ASTERISK_ESPACIO_MIN_MB} MB."
    echo "       Compilar Asterisk sin espacio falla a mitad y deja el árbol a medias."
    exit 1
fi

# ── 1. Descarga y verificación ───────────────────────────────────────────
LIMPIAR_TRABAJO=0
if [[ -z "$ASTERISK_TRABAJO" ]]; then
    ASTERISK_TRABAJO="$(mktemp -d)"; LIMPIAR_TRABAJO=1
fi
TARBALL="${ASTERISK_TRABAJO}/${ASTERISK_ARCHIVO}"

if [[ -f "$TARBALL" ]]; then
    echo "--- tarball ya presente, no se re-descarga ---"
else
    echo "--- descargando ${ASTERISK_ARCHIVO} ---"
    # `-fsS` es obligatorio: sin -f, curl guarda el cuerpo del 404 y el sha256
    # se calcularía sobre una página de error. Comprobado el 2026-09-10, cuando
    # cuatro artefactos ausentes dieron todos el mismo hash — el del HTML de error.
    if ! curl -fsS --retry 3 --max-time 900 -o "$TARBALL" "${ASTERISK_ORIGEN}/${ASTERISK_ARCHIVO}"; then
        echo "ERROR: no se pudo descargar ${ASTERISK_ORIGEN}/${ASTERISK_ARCHIVO}"
        (( LIMPIAR_TRABAJO )) && rm -rf "$ASTERISK_TRABAJO"
        exit 1
    fi
fi

echo "--- verificando integridad (hash del manifiesto, NO del sitio) ---"
if ! echo "${ASTERISK_SHA256}  ${TARBALL}" | sha256sum -c -; then
    echo "ERROR: el hash NO coincide. Abortando sin tocar nada."
    rm -f "$TARBALL"
    (( LIMPIAR_TRABAJO )) && rm -rf "$ASTERISK_TRABAJO"
    exit 1
fi

# ── Limpieza garantizada del árbol de fuentes (ajuste 2) ─────────────────
# En trap, para que también limpie si la compilación falla a media: dejar
# fuentes y compilador en el servidor de un cliente es justo lo que se evita.
limpiar() {
    local rc=$?
    echo "--- limpiando árbol de fuentes y temporales ---"
    rm -rf /usr/src/asterisk-* 2>/dev/null || true
    (( LIMPIAR_TRABAJO )) && rm -rf "$ASTERISK_TRABAJO" 2>/dev/null || true
    return $rc
}
trap limpiar EXIT

# ── 2. Dependencias ──────────────────────────────────────────────────────
# Lista explícita y acotada: NO se usa install_prereq, que arrastra de más.
echo "--- instalando dependencias ---"
export DEBIAN_FRONTEND=noninteractive
apt-get update
apt-get install -y --no-install-recommends \
    build-essential autoconf automake libtool pkg-config \
    libedit-dev libjansson-dev libsqlite3-dev uuid-dev \
    libxml2-dev libssl-dev libcurl4-openssl-dev libsrtp2-dev \
    libncurses-dev unixodbc unixodbc-dev \
    ca-certificates wget

# ── 3. Usuario dedicado del sistema (nunca root) ─────────────────────────
if ! id asterisk >/dev/null 2>&1; then
    echo "--- creando usuario de sistema 'asterisk' ---"
    groupadd --system asterisk
    useradd --system --gid asterisk --no-create-home \
            --home-dir /var/lib/asterisk --shell /usr/sbin/nologin asterisk
else
    echo "--- usuario 'asterisk' ya existe, se respeta ---"
fi

# ── 4. Extraer y configurar ──────────────────────────────────────────────
echo "--- extrayendo en /usr/src ---"
rm -rf "$SRCDIR"
tar xzf "$TARBALL" -C /usr/src
cd "$SRCDIR"

# pjproject y jansson bundled: PJSIP necesita una pjproject compilada
# específicamente para Asterisk; la del sistema no sirve.
echo "--- ./configure (libdir derivado: ${LIBDIR}) ---"
./configure \
    --with-pjproject-bundled \
    --with-jansson-bundled \
    --libdir="${LIBDIR}"

# ── 5. menuselect ────────────────────────────────────────────────────────
echo "--- generando menuselect.makeopts ---"
make menuselect.makeopts
MS="menuselect/menuselect"

echo "--- habilitando módulos requeridos (estricto) ---"
$MS \
    --enable chan_pjsip --enable res_pjsip --enable res_pjsip_session \
    --enable res_pjsip_registrar --enable res_pjsip_outbound_registration \
    --enable res_pjsip_authenticator_digest \
    --enable res_pjsip_endpoint_identifier_ip \
    --enable res_pjsip_endpoint_identifier_user \
    --enable res_odbc --enable res_config_odbc --enable cdr_adaptive_odbc \
    --enable res_agi --enable app_mixmonitor --enable app_queue --enable app_dial \
    --enable codec_alaw --enable codec_ulaw --enable format_wav --enable format_gsm \
    menuselect.makeopts

# BUILD_NATIVE off: es una VM. Compilar con instrucciones nativas del host puede
# reventar si la VM migra a otro nodo del clúster.
echo "--- desactivando BUILD_NATIVE ---"
$MS --disable BUILD_NATIVE menuselect.makeopts

# Sonidos en alaw, que es el códec de la plataforma: en GSM habría que
# transcodificar cada prompt en tiempo real. Inglés como respaldo por si algún
# prompt no existe en español.
echo "--- habilitando sonidos en alaw (ES + EN respaldo + MOH) ---"
$MS --enable CORE-SOUNDS-ES-ALAW --enable CORE-SOUNDS-EN-ALAW \
    --enable MOH-OPSOUND-ALAW menuselect.makeopts

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

# ── 6. Compilar e instalar ───────────────────────────────────────────────
echo "--- make -j$(nproc) ---"
make -j"$(nproc)"
echo "--- make install ---"
make install
echo "--- make samples ---"
make samples
ldconfig

# ── 7. asterisk.conf: usuario y, sobre todo, idioma ──────────────────────
echo "--- fijando runuser/rungroup ---"
sed -i -E 's/^;?\s*runuser\s*=.*/runuser = asterisk/'   /etc/asterisk/asterisk.conf
sed -i -E 's/^;?\s*rungroup\s*=.*/rungroup = asterisk/' /etc/asterisk/asterisk.conf

# Ajuste 1 — SIN esta línea, Asterisk ignora los prompts en español y suena en
# inglés aunque los sonidos estén instalados. Es el ajuste que más se nota.
echo "--- fijando defaultlanguage = ${ASTERISK_IDIOMA} ---"
if grep -qE '^;?\s*defaultlanguage\s*=' /etc/asterisk/asterisk.conf; then
    sed -i -E "s/^;?\s*defaultlanguage\s*=.*/defaultlanguage = ${ASTERISK_IDIOMA}/" /etc/asterisk/asterisk.conf
else
    sed -i "/^\[options\]/a defaultlanguage = ${ASTERISK_IDIOMA}" /etc/asterisk/asterisk.conf
fi
grep -E '^(runuser|rungroup|defaultlanguage)' /etc/asterisk/asterisk.conf

echo "--- ajustando propietario de directorios ---"
chown -R asterisk:asterisk \
    /etc/asterisk /var/lib/asterisk /var/log/asterisk \
    /var/spool/asterisk "${LIBDIR}/asterisk" 2>/dev/null || true
chmod -R u=rwX,g=rX,o= /etc/asterisk

# ── 8. Unit de systemd, instalada y DESHABILITADA ────────────────────────
echo "--- instalando unit de systemd ---"
cat > /etc/systemd/system/asterisk.service <<UNIT
[Unit]
Description=Asterisk PBX
Documentation=man:asterisk(8)
Wants=network-online.target
After=network-online.target nss-lookup.target

[Service]
Type=simple
User=asterisk
Group=asterisk
ExecStart=/usr/sbin/asterisk -f -C /etc/asterisk/asterisk.conf
ExecReload=/usr/sbin/asterisk -rx 'core reload'
Restart=on-failure
RestartSec=5
NoNewPrivileges=true
PrivateTmp=true
ProtectSystem=full
ProtectHome=true

[Install]
WantedBy=multi-user.target
UNIT

systemctl daemon-reload
# Deliberadamente sin 'enable' ni 'start'.
systemctl disable asterisk 2>/dev/null || true

# ── 9. Validación ────────────────────────────────────────────────────────
echo
echo "=== VALIDACIÓN ==="
/usr/sbin/asterisk -V
echo "--- módulos ODBC ---"
ls -1 "${MODDIR}/" 2>/dev/null | grep -E 'res_odbc|res_config_odbc|cdr_adaptive_odbc' \
    || { echo "ERROR: faltan módulos ODBC — sin ellos no hay base realtime."; exit 1; }
echo "--- chan_pjsip ---"
ls -1 "${MODDIR}/chan_pjsip.so" >/dev/null 2>&1 \
    || { echo "ERROR: falta chan_pjsip."; exit 1; }
echo "--- módulos res_pjsip: $(ls -1 "${MODDIR}/" 2>/dev/null | grep -c '^res_pjsip') ---"
echo "--- prompts es/*.alaw: $(ls -1 /var/lib/asterisk/sounds/es/*.alaw 2>/dev/null | wc -l) ---"
echo "--- prompts en/*.alaw: $(ls -1 /var/lib/asterisk/sounds/en/*.alaw 2>/dev/null | wc -l) ---"
echo "--- moh/*.alaw: $(ls -1 /var/lib/asterisk/moh/*.alaw 2>/dev/null | wc -l) ---"
echo "--- archivos .gsm (esperado 0): $(find /var/lib/asterisk/sounds /var/lib/asterisk/moh -name '*.gsm' 2>/dev/null | wc -l) ---"
echo "--- idioma configurado ---"
grep -E '^defaultlanguage' /etc/asterisk/asterisk.conf \
    || { echo "ERROR: defaultlanguage no quedó fijado — los prompts sonarían en inglés."; exit 1; }
echo "--- servicio (debe estar inactivo y disabled) ---"
systemctl is-active asterisk || true
systemctl is-enabled asterisk || true

echo
echo "=== Provisión terminada $(date -Is) ==="
echo "El servicio NO fue arrancado: lo levanta el provisionador tras escribir la configuración."
