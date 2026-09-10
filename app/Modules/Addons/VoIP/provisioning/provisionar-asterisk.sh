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
#   ASTERISK_LOG_DIR        dónde queda el log (default: /var/log/megaisp)
#   ASTERISK_SOPORTE_DIR    dónde sobrevive Alembic (default: /usr/share/megaisp-asterisk)
#   ASTERISK_ESQUEMA_REALTIME  revisión de Alembic esperada (del manifiesto)
#   ASTERISK_MODO_DESCUBRIMIENTO  1 = primera vez, aún no se conoce la revisión
#   ASTERISK_DB_{HOST,PORT,NAME,USER,PASSWORD,DRIVER}  base realtime
#
# ─── ALEMBIC SE VERIFICA ANTES DE COMPILAR ────────────────────────────────
#
# Tener el árbol preservado no basta: sin el comando `alembic` y sin el driver de
# Python, `alembic upgrade head` no corre y el paso de la base realtime falla —
# después de media hora de `make`. Por eso la cadena completa (comando, driver y
# conexión real) se prueba en el pre-flight, cuando abortar todavía es barato.
#
# El `alembic.ini` con la cadena de conexión se GENERA en tiempo de provisión con
# las credenciales de este servidor y NUNCA se versiona: lleva la contraseña de la
# base realtime dentro.
#
# ─── EL ÁRBOL DE ALEMBIC TIENE QUE SOBREVIVIR A LA LIMPIEZA ───────────────
#
# `contrib/ast-db-manage` vive DENTRO de las fuentes, y es lo único que genera el
# esquema de las tablas `ps_*`. Si se borra junto con el árbol, el provisionador
# se queda sin con qué crear la base realtime y la instalación muere a la mitad.
#
# Por eso se copia a $ASTERISK_SOPORTE_DIR/alembic ANTES de limpiar, junto con un
# archivo VERSION-ESQUEMA que dice qué revisión quedó aplicada.
#
# ─── EL HUEVO Y LA GALLINA DE LA REVISIÓN ─────────────────────────────────
#
# La revisión de Alembic que corresponde a una versión de Asterisk solo se conoce
# ejecutándola. Pero el manifiesto tiene que declararla para poder validarla.
#
#   · Primera vez  → ASTERISK_MODO_DESCUBRIMIENTO=1. No exige la revisión: la
#                    ejecuta, la REPORTA, y esa se fija en el manifiesto.
#   · De ahí en más → valida contra la del manifiesto y ABORTA si no coincide.
#
# El modo se activa con bandera EXPLÍCITA, nunca automáticamente por encontrar el
# campo vacío. En la instalación de un cliente el manifiesto siempre viene
# completo, y un descubrimiento disparado por accidente allí aceptaría en silencio
# cualquier esquema que saliera — que es exactamente cómo se llegó al esquema
# remendado que este trabajo viene a corregir.
#
# ─── EL LOG SOBREVIVE AL FALLO ────────────────────────────────────────────
#
# El árbol de fuentes se borra siempre, también cuando la compilación revienta:
# pesa cientos de MB y no debe quedar en el servidor de nadie. Pero el log del
# build pesa poco y es lo único que explica QUÉ falló, así que se guarda aparte,
# en una ruta fija y conocida, y el mensaje de error lo nombra explícitamente.
#
# Borrar el árbol sin conservar el log dejaría un fallo sin diagnóstico posible.
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
: "${ASTERISK_LOG_DIR:=/var/log/megaisp}"
: "${ASTERISK_SOPORTE_DIR:=/usr/share/megaisp-asterisk}"
: "${ASTERISK_ESQUEMA_REALTIME:=}"
: "${ASTERISK_MODO_DESCUBRIMIENTO:=0}"
# Credenciales de la base realtime. Vienen del .env de MegaISP (ASTERISK_RT_DB_*).
: "${ASTERISK_DB_HOST:=127.0.0.1}"
: "${ASTERISK_DB_PORT:=3306}"
: "${ASTERISK_DB_NAME:=asterisk}"
: "${ASTERISK_DB_USER:=}"
: "${ASTERISK_DB_PASSWORD:=}"
: "${ASTERISK_DB_DRIVER:=pymysql}"

SRCDIR="/usr/src/asterisk-${ASTERISK_VERSION}"

# Ajuste 3 — libdir derivado, no fijo a x86_64-linux-gnu.
if command -v dpkg-architecture >/dev/null 2>&1; then
    MULTIARCH="$(dpkg-architecture -qDEB_HOST_MULTIARCH)"
else
    MULTIARCH="$(gcc -print-multiarch 2>/dev/null || echo "$(uname -m)-linux-gnu")"
fi
LIBDIR="/usr/lib/${MULTIARCH}"
MODDIR="${LIBDIR}/asterisk/modules"

# ── 0. Root, log y limpieza garantizada ──────────────────────────────────
# El orden importa: ser root habilita crear el log, y el log tiene que existir
# ANTES del primer paso que pueda fallar, o ese fallo no quedaría registrado.
[[ $EUID -eq 0 ]] || { echo "ERROR: ejecutar como root."; exit 1; }

mkdir -p "$ASTERISK_LOG_DIR"
chmod 750 "$ASTERISK_LOG_DIR"
LOG="${ASTERISK_LOG_DIR}/provision-asterisk-$(date +%Y%m%d-%H%M%S).log"
exec > >(tee -a "$LOG") 2>&1

LIMPIAR_TRABAJO=0
ASTERISK_TRABAJO_REAL=""

# El trap se instala AQUÍ, no más abajo: si el script muere en la descarga o en
# el chequeo de espacio, el mensaje con la ruta del log tiene que salir igual.
limpiar() {
    local rc=$?

    # El árbol pesa cientos de MB y no se queda en el servidor de nadie, ni
    # siquiera cuando la compilación falla — que es justo cuando más tienta
    # dejarlo "para revisar".
    if compgen -G "/usr/src/asterisk-*" >/dev/null 2>&1; then
        echo "--- limpiando árbol de fuentes ---"
        rm -rf /usr/src/asterisk-* 2>/dev/null || true
    fi
    if (( LIMPIAR_TRABAJO )) && [[ -n "$ASTERISK_TRABAJO_REAL" ]]; then
        rm -rf "$ASTERISK_TRABAJO_REAL" 2>/dev/null || true
    fi

    if (( rc != 0 )); then
        echo
        echo "═══════════════════════════════════════════════════════════════"
        echo "  LA PROVISIÓN FALLÓ (código de salida ${rc})"
        echo
        echo "  El árbol de fuentes se borró, pero el log COMPLETO del build"
        echo "  quedó guardado en:"
        echo
        echo "      ${LOG}"
        echo
        echo "  Ahí está la salida de ./configure, make y menuselect, que es"
        echo "  donde se ve el error real."
        echo "═══════════════════════════════════════════════════════════════"
    else
        echo "Log completo: ${LOG}"
    fi
    return $rc
}
trap limpiar EXIT

# Ajuste 6 — la cabecera no nombra ningún servidor: se reporta el que sea.
echo "=== Provisión de Asterisk ${ASTERISK_VERSION} — inicio $(date -Is) ==="
echo "    servidor : $(hostname)"
echo "    arquitect: ${MULTIARCH}  →  libdir ${LIBDIR}"
echo "    origen   : ${ASTERISK_ORIGEN}"
echo "    log      : ${LOG}"

# Coherencia del modo: se decide ANTES de compilar, no después de media hora.
if [[ "$ASTERISK_MODO_DESCUBRIMIENTO" == "1" ]]; then
    echo "--- MODO DESCUBRIMIENTO: la revisión de Alembic se reportará al final ---"
    if [[ -n "$ASTERISK_ESQUEMA_REALTIME" ]]; then
        echo "ERROR: el modo descubrimiento es para cuando la revisión AÚN NO se conoce,"
        echo "       pero el manifiesto ya trae '${ASTERISK_ESQUEMA_REALTIME}'."
        echo "       Si de verdad quieres re-descubrirla, vacía esquema_realtime primero."
        exit 1
    fi
elif [[ -z "$ASTERISK_ESQUEMA_REALTIME" ]]; then
    echo "ERROR: el manifiesto no declara esquema_realtime y NO se pidió modo descubrimiento."
    echo "       Sin revisión esperada no hay nada contra qué validar, y aceptar 'la que"
    echo "       salga' es como se llegó al esquema remendado que este trabajo corrige."
    echo "       Primera instalación: ASTERISK_MODO_DESCUBRIMIENTO=1"
    exit 1
fi

# Ajuste 4 — el espacio ABORTA, no solo informa.
LIBRE_MB="$(df -Pm /usr/src | awk 'NR==2{print $4}')"
echo "--- espacio libre en /usr/src: ${LIBRE_MB} MB (mínimo ${ASTERISK_ESPACIO_MIN_MB}) ---"
if (( LIBRE_MB < ASTERISK_ESPACIO_MIN_MB )); then
    echo "ERROR: espacio insuficiente. Hay ${LIBRE_MB} MB y se requieren ${ASTERISK_ESPACIO_MIN_MB} MB."
    echo "       Compilar Asterisk sin espacio falla a mitad y deja el árbol a medias."
    exit 1
fi

# ── 1. Descarga y verificación ───────────────────────────────────────────
if [[ -z "$ASTERISK_TRABAJO" ]]; then
    ASTERISK_TRABAJO="$(mktemp -d)"; LIMPIAR_TRABAJO=1
    ASTERISK_TRABAJO_REAL="$ASTERISK_TRABAJO"
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
        exit 1   # el trap limpia y nombra el log
    fi
fi

echo "--- verificando integridad (hash del manifiesto, NO del sitio) ---"
if ! echo "${ASTERISK_SHA256}  ${TARBALL}" | sha256sum -c -; then
    echo "ERROR: el hash NO coincide. Abortando sin tocar nada."
    rm -f "$TARBALL"
    exit 1   # el trap limpia y nombra el log
fi

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
    ca-certificates wget \
    python3 python3-alembic python3-pymysql

# ── 2b. Pre-flight de Alembic — ANTES de compilar, no después ────────────
#
# El árbol de Alembic puede estar preservado y aun así el paso de la base
# realtime falla: sin el comando `alembic` o sin el driver de Python, no corre
# nada. Y descubrirlo DESPUÉS de media hora de `make` es tirar esa media hora.
#
# Por eso la cadena completa —comando, driver y conexión real— se verifica aquí,
# cuando abortar todavía es barato.
echo "--- verificando la cadena de Alembic ---"

ALEMBIC_BIN=""
if command -v alembic >/dev/null 2>&1; then
    ALEMBIC_BIN="alembic"
elif python3 -m alembic --help >/dev/null 2>&1; then
    ALEMBIC_BIN="python3 -m alembic"
else
    echo "ERROR: no hay comando 'alembic' utilizable pese a haber instalado python3-alembic."
    echo "       Sin él, el árbol preservado no sirve de nada y el paso de la base realtime"
    echo "       fallaría después de compilar."
    exit 1
fi
echo "    alembic: $($ALEMBIC_BIN --version 2>&1 | head -1)"

python3 -c "import ${ASTERISK_DB_DRIVER}" 2>/dev/null || {
    echo "ERROR: el driver de Python '${ASTERISK_DB_DRIVER}' no se puede importar."
    echo "       Alembic no podrá abrir la conexión a la base realtime."
    exit 1
}
echo "    driver: ${ASTERISK_DB_DRIVER} importable"

# La conexión real. Se prueba solo si hay credenciales: en modo descubrimiento
# manual puede que la base aún no exista, y ahí el aviso basta.
if [[ -n "$ASTERISK_DB_USER" ]]; then
    if ASTERISK_DB_PASSWORD="$ASTERISK_DB_PASSWORD" python3 - <<PYCHK
import os, sys
try:
    import ${ASTERISK_DB_DRIVER} as drv
    c = drv.connect(host="${ASTERISK_DB_HOST}", port=${ASTERISK_DB_PORT},
                    user="${ASTERISK_DB_USER}", password=os.environ.get("ASTERISK_DB_PASSWORD",""),
                    connect_timeout=10)
    c.close()
except Exception as e:
    sys.stderr.write(str(e) + "\n"); sys.exit(1)
PYCHK
    then
        echo "    conexión a ${ASTERISK_DB_HOST}:${ASTERISK_DB_PORT} verificada"
    else
        echo "ERROR: el driver no pudo conectar a ${ASTERISK_DB_HOST}:${ASTERISK_DB_PORT} como '${ASTERISK_DB_USER}'."
        echo "       Se aborta ahora, antes de compilar: el paso de la base realtime fallaría igual"
        echo "       media hora más tarde, con el tiempo ya gastado."
        exit 1
    fi
else
    echo "    (sin ASTERISK_DB_USER: no se prueba la conexión)"
fi

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

# ── Alembic, a salvo de la limpieza ──────────────────────────────────────
# Va INMEDIATAMENTE después de install y antes de cualquier otra cosa que pueda
# fallar: si el script muriera aquí, el trap borraría las fuentes y con ellas la
# única copia del árbol que genera el esquema.
echo "--- preservando el árbol de Alembic en ${ASTERISK_SOPORTE_DIR}/alembic ---"
if [[ ! -d "${SRCDIR}/contrib/ast-db-manage" ]]; then
    echo "ERROR: no está contrib/ast-db-manage en las fuentes. Sin él no hay forma de"
    echo "       crear la base realtime, y la instalación quedaría a medias."
    exit 1
fi
mkdir -p "${ASTERISK_SOPORTE_DIR}"
rm -rf "${ASTERISK_SOPORTE_DIR}/alembic"
cp -a "${SRCDIR}/contrib/ast-db-manage" "${ASTERISK_SOPORTE_DIR}/alembic"
echo "${ASTERISK_VERSION}" > "${ASTERISK_SOPORTE_DIR}/VERSION-ASTERISK"
echo "    $(find "${ASTERISK_SOPORTE_DIR}/alembic/config/versions" -name '*.py' 2>/dev/null | wc -l) migraciones de Alembic preservadas"

# El alembic.ini se GENERA aquí, con las credenciales de este servidor, y NUNCA
# se versiona: lleva la contraseña de la base realtime en su sqlalchemy.url.
if [[ -n "$ASTERISK_DB_USER" ]]; then
    echo "--- generando alembic.ini (fuera del repo, 600) ---"
    ALEMBIC_INI="${ASTERISK_SOPORTE_DIR}/alembic/config.ini"
    cp "${ASTERISK_SOPORTE_DIR}/alembic/config.ini.sample" "$ALEMBIC_INI"
    # `python -` evita que la contraseña aparezca en la línea de comandos, donde
    # cualquiera con acceso al servidor la vería con un simple `ps`.
    ASTERISK_DB_PASSWORD="$ASTERISK_DB_PASSWORD" python3 - "$ALEMBIC_INI" <<'PYINI'
import os, re, sys, urllib.parse
ruta = sys.argv[1]
url = "mysql+${ASTERISK_DB_DRIVER}://{u}:{p}@{h}:{P}/{d}".format(
    u=urllib.parse.quote_plus("${ASTERISK_DB_USER}"),
    p=urllib.parse.quote_plus(os.environ.get("ASTERISK_DB_PASSWORD", "")),
    h="${ASTERISK_DB_HOST}", P="${ASTERISK_DB_PORT}", d="${ASTERISK_DB_NAME}")
txt = open(ruta, encoding="utf-8").read()
txt = re.sub(r"(?m)^sqlalchemy\.url\s*=.*$", "sqlalchemy.url = " + url, txt)
open(ruta, "w", encoding="utf-8").write(txt)
PYINI
    chmod 600 "$ALEMBIC_INI"
    chown root:root "$ALEMBIC_INI" 2>/dev/null || true
    echo "    ${ALEMBIC_INI} (permisos $(stat -c '%a' "$ALEMBIC_INI"), no versionado)"
fi

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

echo "--- árbol de Alembic disponible ---"
[[ -d "${ASTERISK_SOPORTE_DIR}/alembic/config/versions" ]] \
    || { echo "ERROR: el árbol de Alembic no quedó preservado."; exit 1; }

echo
echo "=== Provisión terminada $(date -Is) ==="
if [[ "$ASTERISK_MODO_DESCUBRIMIENTO" == "1" ]]; then
    echo
    echo "═══════════════════════════════════════════════════════════════"
    echo "  MODO DESCUBRIMIENTO — falta un paso manual"
    echo
    echo "  Asterisk quedó instalado y el árbol de Alembic preservado en:"
    echo "      ${ASTERISK_SOPORTE_DIR}/alembic"
    echo
    echo "  El provisionador ejecutará ahora 'alembic upgrade head' y"
    echo "  reportará la revisión resultante. Ese valor hay que fijarlo en"
    echo "  config/requisitos-voip.php → asterisk.esquema_realtime"
    echo
    echo "  A partir de la siguiente ejecución se valida contra él, y una"
    echo "  revisión distinta aborta en vez de aplicarse en silencio."
    echo "═══════════════════════════════════════════════════════════════"
else
    echo "Revisión de esquema esperada: ${ASTERISK_ESQUEMA_REALTIME}"
fi
echo "El servicio NO fue arrancado: lo levanta el provisionador tras escribir la configuración."
