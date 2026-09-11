#!/usr/bin/env bash
#
# ═══════════════════════════════════════════════════════════════════════════
# provisionar-asterisk.sh — instala Asterisk en el servidor donde corre MegaISP
# ═══════════════════════════════════════════════════════════════════════════
#
# Deriva de `referencia/etapa1-instalar-asterisk22.sh`, que conserva las
# decisiones de compilación originales y NO se modifica.
#
# ─── SE EJECUTA POR FASES, Y ESA ES LA DECISIÓN DE DISEÑO PRINCIPAL ───────
#
# Antes era un solo bloque: una invocación bajaba, compilaba, instalaba,
# preservaba Alembic y generaba configuración. Un fallo en el último 5% obligaba
# a repetir el 95% anterior — media hora de `make` para volver a morir donde
# mismo. La tabla `voip_provision_estado` registraba «descargar: completado» sin
# poder decir cuánto de todo eso se había alcanzado a hacer, así que
# `reutilizablePor()` no tenía nada útil que reutilizar.
#
# Ahora cada fase es una invocación con su propio estado:
#
#     --fase=descargar           baja el tarball al directorio de trabajo
#     --fase=verificar_hash      sha256 contra el manifiesto
#     --fase=dependencias        apt-get + cadena de Alembic (comando/driver/conexión)
#     --fase=compilar            extrae, ./configure, menuselect, make
#     --fase=instalar            make install/samples/ldconfig
#     --fase=preservar_alembic   copia contrib/ast-db-manage fuera del árbol
#     --fase=generar_config      asterisk.conf, unit de systemd y alembic.ini
#     --fase=limpiar             borra el árbol de fuentes y el trabajo
#     --fase=contrato            solo valida y sale (útil para diagnóstico)
#
# CADA FASE COMPRUEBA EL SERVIDOR ANTES DE TRABAJAR, no la tabla de estado: si
# Asterisk ya está instalado en la versión pedida, `instalar` lo dice y sale sin
# tocar nada. La tabla sirve para reportar; la verdad está en el disco.
#
# ─── EL CONTRATO SE VALIDA EN EL SEGUNDO CERO ─────────────────────────────
#
# Todas las variables se exigen y se validan —presencia, formato y coherencia—
# ANTES de ejecutar la fase, sea cual sea. No al usarlas.
#
# Esto no es celo: una provisión murió con `KeyError: 'ASTERISK_DB_DRIVER'` en el
# último paso, después de compilar media hora. Si el contrato se exige al
# arrancar, ese mismo fallo sale en el segundo cero y no cuesta nada.
#
# ─── NO TRAE DATOS PROPIOS ────────────────────────────────────────────────
#
# Todo lo que varía llega por entorno, desde `config/requisitos-voip.php`, que es
# el manifiesto que viaja con la actualización. Este script no sabe qué versión
# instala ni de dónde la baja: se lo dicen. La tabla completa del contrato está
# en `validar_contrato()`, que es la única fuente — si algo no está ahí, el
# script no lo consume.
#
# Cuando el llamador es el provisionador de PHP, esas variables llegan en un
# archivo (`--archivo-entorno=RUTA`) y no en el entorno: `sudo` lo vacía. La
# razón está a detalle junto al código que lo carga.
#
# ─── EL ÁRBOL DE ALEMBIC TIENE QUE SOBREVIVIR A LA LIMPIEZA ───────────────
#
# `contrib/ast-db-manage` vive DENTRO de las fuentes, y es lo único que genera el
# esquema de las tablas `ps_*`. Por eso se copia a $ASTERISK_SOPORTE_DIR/alembic
# antes de limpiar, junto con un archivo VERSION-ASTERISK.
#
# El `alembic.ini` con la cadena de conexión se GENERA en tiempo de provisión con
# las credenciales de este servidor y NUNCA se versiona: lleva la contraseña de la
# base realtime dentro.
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
# campo vacío. Un descubrimiento disparado por accidente en casa de un cliente
# aceptaría en silencio cualquier esquema que saliera — que es exactamente cómo se
# llegó al esquema remendado que este trabajo viene a corregir.
#
# ─── EL LOG SOBREVIVE AL FALLO ────────────────────────────────────────────
#
# El log del build pesa poco y es lo único que explica QUÉ falló, así que se
# guarda en una ruta fija y conocida, y el mensaje de error lo nombra.
#
# ─── EL SERVICIO NO SE ARRANCA ────────────────────────────────────────────
#
# Igual que el original: se instala la unit y se deja DESHABILITADA. Un Asterisk
# que empieza a escuchar antes de tener firewall es una invitación al fraude
# telefónico. Quien decide arrancarlo es el provisionador de PHP, después de
# escribir la configuración.
#
set -euo pipefail

# ── Argumentos ───────────────────────────────────────────────────────────
#
# El entorno NO sobrevive el salto a root: `sudo` trae `env_reset` por omisión y
# limpia todo lo que no esté en su `env_keep`. Las variables se exportaban
# correctamente y aun así llegaban vacías — el script moría culpando al
# manifiesto, que estaba completo.
#
# Por eso el llamador puede pasar un archivo con los valores:
#
#     sudo -n bash provisionar-asterisk.sh --fase=compilar --archivo-entorno=/ruta
#
# Un archivo y no `sudo -E` ni `sudo env VAR=…`:
#
#   · `sudo -E` exige la etiqueta SETENV en el sudoers de CADA servidor donde se
#     instale. Depender de eso convierte una configuración ajena en requisito.
#   · `sudo env VAR=…` pone la contraseña de la base realtime en la línea de
#     comandos, visible con un `ps` para cualquiera con acceso al servidor.
#
# El archivo lo escribe y lo borra el llamador; aquí solo se lee. La RUTA sí
# viaja como argumento, y una ruta no es un secreto.
ARCHIVO_ENTORNO=""
FASE=""

FASES_VALIDAS="contrato descargar verificar_hash dependencias compilar instalar preservar_alembic generar_config limpiar"

for arg in "$@"; do
    case "$arg" in
        --archivo-entorno=*) ARCHIVO_ENTORNO="${arg#*=}" ;;
        --fase=*)            FASE="${arg#*=}" ;;
        *) echo "ERROR: argumento no reconocido: ${arg}"; exit 1 ;;
    esac
done

if [[ -z "$FASE" ]]; then
    echo "ERROR: falta --fase=NOMBRE."
    echo "       Fases: ${FASES_VALIDAS// /, }"
    exit 1
fi

if [[ " ${FASES_VALIDAS} " != *" ${FASE} "* ]]; then
    echo "ERROR: fase desconocida «${FASE}»."
    echo "       Fases: ${FASES_VALIDAS// /, }"
    exit 1
fi

if [[ -n "$ARCHIVO_ENTORNO" ]]; then
    [[ -f "$ARCHIVO_ENTORNO" ]] || {
        echo "ERROR: no existe el archivo de entorno ${ARCHIVO_ENTORNO}."
        exit 1
    }

    # Lleva la contraseña de la base realtime dentro. Si alguien más puede
    # leerlo, el secreto ya está expuesto y seguir sería tapar la fuga.
    PERMISOS="$(stat -c %a "$ARCHIVO_ENTORNO")"
    if [[ "${PERMISOS: -2}" != "00" ]]; then
        echo "ERROR: ${ARCHIVO_ENTORNO} tiene permisos ${PERMISOS} y lleva credenciales dentro."
        echo "       Se exige que solo su dueño pueda leerlo (600)."
        exit 1
    fi

    set -a
    # shellcheck disable=SC1090
    . "$ARCHIVO_ENTORNO"
    set +a
fi

# ── Valores por omisión ──────────────────────────────────────────────────
#
# Solo para lo que de verdad tiene un valor sensato por omisión. Lo que NO puede
# adivinarse (versión, origen, hash, credenciales) se exige en el contrato y no
# se rellena aquí: un default silencioso en esos campos es cómo se instala la
# versión equivocada sin que nadie se entere.
: "${ASTERISK_IDIOMA:=es}"
: "${ASTERISK_ESPACIO_MIN_MB:=3072}"
: "${ASTERISK_LOG_DIR:=/var/log/megaisp}"
: "${ASTERISK_SOPORTE_DIR:=/usr/share/megaisp-asterisk}"
: "${ASTERISK_ESQUEMA_REALTIME:=}"
: "${ASTERISK_MODO_DESCUBRIMIENTO:=0}"
: "${ASTERISK_DB_HOST:=127.0.0.1}"
: "${ASTERISK_DB_PORT:=3306}"
: "${ASTERISK_DB_NAME:=asterisk}"
: "${ASTERISK_DB_USER:=}"
: "${ASTERISK_DB_PASSWORD:=}"
: "${ASTERISK_DB_DRIVER:=pymysql}"
# El nombre del DSN que res_odbc.conf va a nombrar y que unixODBC tiene que
# resolver. Es de los que SÍ admiten omisión: no identifica nada del servidor,
# solo tiene que decir lo mismo en /etc/odbc.ini y en res_odbc.conf.
: "${ASTERISK_ODBC_DSN:=asterisk-connector}"
# Dónde deja MegaISP los .conf que genera. extensions.conf los incluye por esta
# ruta, así que el script y el generador de PHP tienen que decir lo mismo.
: "${ASTERISK_GENERADOS_DIR:=/etc/asterisk/megaisp.d}"

# El directorio de trabajo es PERSISTENTE y ya no un `mktemp -d`: con las fases
# separadas, el tarball que baja `descargar` tiene que seguir ahí cuando corre
# `verificar_hash`, y el que ya se verificó no debe volver a bajarse.
: "${ASTERISK_TRABAJO:=/var/cache/megaisp-asterisk}"

ASTERISK_VERSION="${ASTERISK_VERSION:-}"
ASTERISK_ORIGEN="${ASTERISK_ORIGEN:-}"
ASTERISK_ARCHIVO="${ASTERISK_ARCHIVO:-}"
ASTERISK_SHA256="${ASTERISK_SHA256:-}"

# Exportadas explícitamente: los bloques de Python de más abajo las leen de
# `os.environ` en vez de recibirlas interpoladas en su código, y un valor que
# llegó por default (no por el archivo de entorno, que sí se carga con `set -a`)
# no estaría exportado. Sin esto, correr el script a mano fallaría en Python con
# un KeyError — la misma familia de fallo que motivó esta revisión, y por eso se
# hace aquí y no en el sitio de uso.
export ASTERISK_VERSION ASTERISK_ORIGEN ASTERISK_ARCHIVO ASTERISK_SHA256 \
       ASTERISK_IDIOMA ASTERISK_ESPACIO_MIN_MB ASTERISK_LOG_DIR \
       ASTERISK_SOPORTE_DIR ASTERISK_TRABAJO ASTERISK_ESQUEMA_REALTIME \
       ASTERISK_MODO_DESCUBRIMIENTO \
       ASTERISK_DB_HOST ASTERISK_DB_PORT ASTERISK_DB_NAME \
       ASTERISK_DB_USER ASTERISK_DB_PASSWORD ASTERISK_DB_DRIVER ASTERISK_ODBC_DSN \
       ASTERISK_GENERADOS_DIR

# ── EL CONTRATO ──────────────────────────────────────────────────────────
#
# Fuente única de lo que este script consume. Corre SIEMPRE y ANTES de la fase,
# cualquiera que sea: validar en el segundo cero cuesta milisegundos, y
# descubrir un campo mal puesto después de compilar cuesta media hora.
#
# Valida tres cosas distintas, y las tres importan:
#
#   presencia  — que esté y no venga vacía
#   formato    — que sea lo que dice ser (un sha256 son 64 hex, un puerto es un
#                número, un driver es uno de los que sabemos importar)
#   coherencia — que la combinación tenga sentido (modo descubrimiento CON
#                revisión declarada es una contradicción, no un descuido)
#
# El formato no es cosmético: `ASTERISK_DB_NAME` termina dentro de una URL de
# SQLAlchemy y de un `CREATE DATABASE`, y `ASTERISK_ARCHIVO` se concatena a la
# URL de descarga. Aceptar cualquier cosa ahí es aceptar que alguien la use.
ERRORES=()

exigir() {
    local nombre="$1" valor="${2-}" porque="$3"
    [[ -n "$valor" ]] || ERRORES+=("falta ${nombre} — ${porque}")
}

con_formato() {
    local nombre="$1" valor="${2-}" patron="$3" esperado="$4"
    [[ -z "$valor" ]] && return 0          # la ausencia la reporta `exigir`
    [[ "$valor" =~ $patron ]] || ERRORES+=("${nombre}='${valor}' no es ${esperado}")
}

validar_contrato() {
    # — Qué instalar, y de dónde. Nada de esto se puede adivinar. —
    exigir ASTERISK_VERSION "$ASTERISK_VERSION" "el manifiesto dice qué versión de Asterisk instalar"
    exigir ASTERISK_ORIGEN  "$ASTERISK_ORIGEN"  "sin origen no hay de dónde bajar el tarball"
    exigir ASTERISK_ARCHIVO "$ASTERISK_ARCHIVO" "sin nombre de archivo no hay qué bajar"
    exigir ASTERISK_SHA256  "$ASTERISK_SHA256"  "el hash viene del manifiesto, nunca del sitio de descarga"

    con_formato ASTERISK_VERSION "$ASTERISK_VERSION" '^[0-9]+\.[0-9]+\.[0-9]+$' "una versión x.y.z"
    con_formato ASTERISK_SHA256  "$ASTERISK_SHA256"  '^[0-9a-f]{64}$'           "un sha256 (64 hex en minúscula)"
    con_formato ASTERISK_ORIGEN  "$ASTERISK_ORIGEN"  '^https?://[^[:space:]]+$' "una URL http(s)"
    # Sin barras: se concatena a la URL de descarga, y un `../` ahí cambia a qué
    # host se pide el archivo.
    con_formato ASTERISK_ARCHIVO "$ASTERISK_ARCHIVO" '^[A-Za-z0-9._+-]+\.tar\.gz$' "un nombre de tarball sin rutas"

    # — Cómo instalarlo —
    con_formato ASTERISK_IDIOMA          "$ASTERISK_IDIOMA"          '^[a-z]{2}$' "un código de idioma de dos letras"
    con_formato ASTERISK_ESPACIO_MIN_MB  "$ASTERISK_ESPACIO_MIN_MB"  '^[0-9]+$'   "un número de MB"
    con_formato ASTERISK_MODO_DESCUBRIMIENTO "$ASTERISK_MODO_DESCUBRIMIENTO" '^[01]$' "0 o 1"

    for d in ASTERISK_LOG_DIR ASTERISK_SOPORTE_DIR ASTERISK_TRABAJO; do
        con_formato "$d" "${!d}" '^/[^[:space:]]*$' "una ruta absoluta"
    done

    # — La base realtime —
    #
    # DB_USER es obligatorio, y esto es un cambio: antes el script se saltaba en
    # silencio la generación de `alembic.ini` si no venía, y la provisión
    # terminaba «bien» sin haber generado el archivo del que depende el paso
    # siguiente. Ese silencio es de la misma familia que el fallo que motivó esta
    # revisión: fallar tarde y en otro sitio.
    exigir ASTERISK_DB_USER   "$ASTERISK_DB_USER"   "sin usuario no se puede abrir la base realtime ni generar alembic.ini"
    exigir ASTERISK_DB_NAME   "$ASTERISK_DB_NAME"   "el esquema realtime necesita una base con nombre"
    exigir ASTERISK_DB_DRIVER "$ASTERISK_DB_DRIVER" "Alembic necesita saber con qué driver de Python conectar"
    exigir ASTERISK_ODBC_DSN  "$ASTERISK_ODBC_DSN"  "res_odbc.conf nombra un DSN y unixODBC tiene que poder resolverlo"

    con_formato ASTERISK_DB_HOST "$ASTERISK_DB_HOST" '^[A-Za-z0-9._:-]+$' "un host o IP"
    con_formato ASTERISK_DB_PORT "$ASTERISK_DB_PORT" '^[0-9]{1,5}$'       "un puerto"
    if [[ "$ASTERISK_DB_PORT" =~ ^[0-9]+$ ]] && (( ASTERISK_DB_PORT < 1 || ASTERISK_DB_PORT > 65535 )); then
        ERRORES+=("ASTERISK_DB_PORT='${ASTERISK_DB_PORT}' está fuera de 1..65535")
    fi
    # Va a un CREATE DATABASE y a una URL de SQLAlchemy.
    con_formato ASTERISK_DB_NAME "$ASTERISK_DB_NAME" '^[A-Za-z0-9_]+$' "un identificador de base (letras, dígitos y _)"
    con_formato ASTERISK_DB_USER "$ASTERISK_DB_USER" '^[A-Za-z0-9_.-]+$' "un nombre de usuario de MySQL"

    # Lista blanca: son los tres que sabemos importar y que SQLAlchemy nombra
    # igual en su URL. Uno fuera de la lista falla al importar mucho después.
    case "$ASTERISK_DB_DRIVER" in
        pymysql|mysqldb|mysqlconnector) ;;
        "") ;;
        *) ERRORES+=("ASTERISK_DB_DRIVER='${ASTERISK_DB_DRIVER}' no es uno de: pymysql, mysqldb, mysqlconnector") ;;
    esac

    if [[ -z "$ASTERISK_DB_PASSWORD" ]]; then
        ERRORES+=("falta ASTERISK_DB_PASSWORD — la base realtime guarda las credenciales SIP de todas las extensiones; sin contraseña no se provisiona")
    fi

    # — Coherencia del modo descubrimiento —
    #
    # Se decide aquí, no después de media hora de `make`.
    if [[ "$ASTERISK_MODO_DESCUBRIMIENTO" == "1" && -n "$ASTERISK_ESQUEMA_REALTIME" ]]; then
        ERRORES+=("el modo descubrimiento es para cuando la revisión AÚN NO se conoce, pero el manifiesto ya trae '${ASTERISK_ESQUEMA_REALTIME}' — si de verdad quieres re-descubrirla, vacía esquema_realtime primero")
    fi
    if [[ "$ASTERISK_MODO_DESCUBRIMIENTO" == "0" && -z "$ASTERISK_ESQUEMA_REALTIME" ]]; then
        ERRORES+=("el manifiesto no declara esquema_realtime y NO se pidió modo descubrimiento — aceptar 'la que salga' es como se llegó al esquema remendado que este trabajo corrige; primera instalación: ASTERISK_MODO_DESCUBRIMIENTO=1")
    fi
    con_formato ASTERISK_ESQUEMA_REALTIME "$ASTERISK_ESQUEMA_REALTIME" '^[0-9a-z_]+$' "una revisión de Alembic"

    if (( ${#ERRORES[@]} > 0 )); then
        echo "═══════════════════════════════════════════════════════════════"
        echo "  CONTRATO INCOMPLETO — no se ejecuta la fase «${FASE}»"
        echo
        printf '  · %s\n' "${ERRORES[@]}"
        echo
        echo "  Estos valores salen de config/requisitos-voip.php y de la"
        echo "  conexión 'asterisk_rt' del .env. Se validan ANTES de trabajar:"
        echo "  descubrirlo después de compilar cuesta media hora."
        echo "═══════════════════════════════════════════════════════════════"
        exit 1
    fi
}

validar_contrato

# ── Derivados, ya con el contrato garantizado ────────────────────────────
SRCDIR="/usr/src/asterisk-${ASTERISK_VERSION}"
TARBALL="${ASTERISK_TRABAJO}/${ASTERISK_ARCHIVO}"

# libdir derivado, no fijo a x86_64-linux-gnu (ajuste 3 de los seis al original).
if command -v dpkg-architecture >/dev/null 2>&1; then
    MULTIARCH="$(dpkg-architecture -qDEB_HOST_MULTIARCH)"
else
    MULTIARCH="$(gcc -print-multiarch 2>/dev/null || echo "$(uname -m)-linux-gnu")"
fi
LIBDIR="/usr/lib/${MULTIARCH}"
MODDIR="${LIBDIR}/asterisk/modules"

# ── Los módulos sin los cuales el módulo VoIP de MegaISP no funciona ─────
#
# Una sola lista, en el ámbito global, porque la consumen DOS fases y tienen que
# hablar de lo mismo: `compilar` los pide a menuselect y comprueba que ninguno
# quedó excluido; `generar_config` comprueba que su .so existe de verdad en el
# disco. Tener la lista duplicada sería tener dos definiciones de «completo» que
# pueden divergir, y la que se quedara corta no avisaría de nada.
readonly MODULOS_REQUERIDOS=(
    chan_pjsip res_pjsip res_pjsip_session
    res_pjsip_registrar res_pjsip_outbound_registration
    res_pjsip_authenticator_digest
    res_pjsip_endpoint_identifier_ip
    res_pjsip_endpoint_identifier_user
    res_odbc res_config_odbc cdr_adaptive_odbc
    res_agi app_mixmonitor app_queue app_dial
    codec_alaw codec_ulaw format_wav format_gsm
)

# ── Root y log ───────────────────────────────────────────────────────────
# El orden importa: ser root habilita crear el log, y el log tiene que existir
# ANTES del primer paso que pueda fallar, o ese fallo no quedaría registrado.
[[ $EUID -eq 0 ]] || { echo "ERROR: ejecutar como root."; exit 1; }

mkdir -p "$ASTERISK_LOG_DIR"
chmod 750 "$ASTERISK_LOG_DIR"
LOG="${ASTERISK_LOG_DIR}/provision-asterisk-${FASE}-$(date +%Y%m%d-%H%M%S).log"
exec > >(tee -a "$LOG") 2>&1

# El trap ya NO borra el árbol de fuentes.
#
# Antes lo hacía en cada salida, y con una sola invocación monolítica tenía
# sentido. Con fases separadas es justo lo contrario: `instalar` necesita lo que
# dejó `compilar`, y borrarlo entre una y otra obliga a recompilar desde cero —
# el problema que estas fases vienen a resolver.
#
# El árbol lo borra `preservar_alembic` cuando ya nadie lo necesita, o la fase
# `limpiar` a petición. Si una fase falla, el árbol QUEDA a propósito: es lo que
# hace barato el reintento.
al_salir() {
    local rc=$?

    if (( rc != 0 )); then
        echo
        echo "═══════════════════════════════════════════════════════════════"
        echo "  LA FASE «${FASE}» FALLÓ (código de salida ${rc})"
        echo
        echo "  Log de esta fase:"
        echo "      ${LOG}"
        echo
        if [[ -d "$SRCDIR" ]]; then
            echo "  El árbol de fuentes se conserva en ${SRCDIR}:"
            echo "  al reintentar, las fases ya cumplidas no se repiten."
        fi
        echo "═══════════════════════════════════════════════════════════════"
    else
        echo "Log de la fase: ${LOG}"
    fi
    return $rc
}
trap al_salir EXIT

echo "=== Asterisk ${ASTERISK_VERSION} — fase «${FASE}» — $(date -Is) ==="
echo "    servidor : $(hostname)"
echo "    arquitect: ${MULTIARCH}  →  libdir ${LIBDIR}"
echo "    trabajo  : ${ASTERISK_TRABAJO}"

# ── Estado del servidor: lo que decide si una fase tiene algo que hacer ───
#
# Se consulta el DISCO, no la tabla de estado. La tabla dice qué se intentó; el
# disco dice qué hay. Cuando difieren, manda el disco: es lo que hace que un
# reintento sobre un servidor con Asterisk ya instalado no vuelva a compilarlo.
version_instalada() {
    [[ -x /usr/sbin/asterisk ]] || return 1
    /usr/sbin/asterisk -V 2>/dev/null | sed -nE 's/^Asterisk[[:space:]]+([^[:space:]]+).*/\1/p'
}

ya_instalado() {
    [[ "$(version_instalada || true)" == "$ASTERISK_VERSION" ]]
}

alembic_preservado() {
    [[ -d "${ASTERISK_SOPORTE_DIR}/alembic/config/versions" ]] \
        && [[ -n "$(find "${ASTERISK_SOPORTE_DIR}/alembic/config/versions" -name '*.py' -print -quit 2>/dev/null)" ]]
}

# El árbol extraído sirve; el compilado tiene además el binario enlazado.
fuentes_extraidas()  { [[ -f "${SRCDIR}/configure" ]]; }
fuentes_compiladas() { [[ -x "${SRCDIR}/main/asterisk" ]]; }

# Extrae solo si hace falta. Lo usan `compilar` y `preservar_alembic`: la segunda
# necesita `contrib/ast-db-manage`, que está en el tarball, pero NO necesita
# compilar nada para obtenerlo. Extraer toma segundos; compilar, media hora.
asegurar_fuentes() {
    fuentes_extraidas && { echo "--- fuentes ya extraídas en ${SRCDIR} ---"; return 0; }

    [[ -f "$TARBALL" ]] || {
        echo "ERROR: no está el tarball ${TARBALL}."
        echo "       Corre antes las fases descargar y verificar_hash."
        exit 1
    }

    echo "--- extrayendo en /usr/src ---"
    rm -rf "$SRCDIR"
    tar xzf "$TARBALL" -C /usr/src
}

# ═════════════════════════════════════════════════════════════════════════
#  FASES
# ═════════════════════════════════════════════════════════════════════════

fase_contrato() {
    echo "--- contrato válido ---"
    echo "    versión pedida   : ${ASTERISK_VERSION}"
    echo "    versión instalada: $(version_instalada || echo '(ninguna)')"
    echo "    alembic preservado: $(alembic_preservado && echo sí || echo no)"
    echo "    modo descubrimiento: ${ASTERISK_MODO_DESCUBRIMIENTO}"
}

# ── «Esta fase no tuvo nada que hacer» ────────────────────────────────────
#
# Lo emite un MARCADOR FIJO, y no la prosa del mensaje.
#
# El llamador de PHP distinguía «se hizo» de «ya estaba» buscando frases sueltas
# en la salida («ya presente», «nada que hacer», …). Eso convierte cada mensaje
# en interfaz sin que se note: al añadir a otra fase un «ya presente, se respeta»
# —hablando de otra cosa— esa fase pasó a reportarse como omitida habiendo hecho
# su trabajo. Es la misma familia que adivinar el formato de menuselect.ins
#
# El texto para el humano se sigue imprimiendo; lo que lee la máquina es esta
# línea, que no cambia.
sin_trabajo() {
    echo "$@"
    echo "@@FASE-SIN-TRABAJO@@"
}

fase_descargar() {
    if ya_instalado && alembic_preservado; then
        sin_trabajo "--- Asterisk ${ASTERISK_VERSION} ya instalado y Alembic preservado: no hay nada que bajar ---"
        return 0
    fi

    mkdir -p "$ASTERISK_TRABAJO"
    chmod 700 "$ASTERISK_TRABAJO"

    if [[ -f "$TARBALL" ]]; then
        sin_trabajo "--- tarball ya presente en ${TARBALL}, no se re-descarga ---"
        return 0
    fi

    # Espacio: se comprueba aquí, que es la primera fase que consume disco, y
    # ABORTA en vez de solo avisar (ajuste 4 de los seis al original).
    local libre
    libre="$(df -Pm /usr/src | awk 'NR==2{print $4}')"
    echo "--- espacio libre en /usr/src: ${libre} MB (mínimo ${ASTERISK_ESPACIO_MIN_MB}) ---"
    if (( libre < ASTERISK_ESPACIO_MIN_MB )); then
        echo "ERROR: espacio insuficiente. Hay ${libre} MB y se requieren ${ASTERISK_ESPACIO_MIN_MB} MB."
        echo "       Compilar Asterisk sin espacio falla a mitad y deja el árbol a medias."
        exit 1
    fi

    echo "--- descargando ${ASTERISK_ARCHIVO} ---"
    # `-fsS` es obligatorio: sin -f, curl guarda el cuerpo del 404 y el sha256 se
    # calcularía sobre una página de error. Comprobado el 2026-09-10, cuando
    # cuatro artefactos ausentes dieron todos el mismo hash — el del HTML de error.
    if ! curl -fsS --retry 3 --max-time 900 -o "$TARBALL" "${ASTERISK_ORIGEN}/${ASTERISK_ARCHIVO}"; then
        rm -f "$TARBALL"
        echo "ERROR: no se pudo descargar ${ASTERISK_ORIGEN}/${ASTERISK_ARCHIVO}"
        exit 1
    fi
    echo "    $(du -h "$TARBALL" | cut -f1) descargados"
}

fase_verificar_hash() {
    if ya_instalado && alembic_preservado && [[ ! -f "$TARBALL" ]]; then
        sin_trabajo "--- ya instalado y sin tarball que verificar: nada que hacer ---"
        return 0
    fi

    [[ -f "$TARBALL" ]] || { echo "ERROR: no está ${TARBALL}. Corre antes la fase descargar."; exit 1; }

    echo "--- verificando integridad (hash del manifiesto, NO del sitio) ---"
    if ! echo "${ASTERISK_SHA256}  ${TARBALL}" | sha256sum -c -; then
        # Se borra: un tarball que no coincide no debe quedar para que la
        # siguiente corrida lo encuentre y lo dé por bueno.
        rm -f "$TARBALL"
        echo "ERROR: el hash NO coincide. Se borró el archivo y no se tocó nada más."
        exit 1
    fi
}

fase_dependencias() {
    # Lista explícita y acotada: NO se usa install_prereq, que arrastra de más.
    echo "--- instalando dependencias ---"
    export DEBIAN_FRONTEND=noninteractive
    apt-get update
    apt-get install -y --no-install-recommends \
        build-essential autoconf automake libtool pkg-config \
        libedit-dev libjansson-dev libsqlite3-dev uuid-dev \
        libxml2-dev libssl-dev libcurl4-openssl-dev libsrtp2-dev \
        libncurses-dev unixodbc unixodbc-dev odbc-mariadb \
        ca-certificates wget \
        python3 python3-alembic python3-pymysql

    # ── Cadena de Alembic, verificada ANTES de compilar ──
    #
    # El árbol preservado no basta: sin el comando `alembic` o sin el driver de
    # Python no corre nada, y descubrirlo DESPUÉS de media hora de `make` es
    # tirar esa media hora.
    echo "--- verificando la cadena de Alembic ---"

    local alembic_bin=""
    if command -v alembic >/dev/null 2>&1; then
        alembic_bin="alembic"
    elif python3 -m alembic --help >/dev/null 2>&1; then
        alembic_bin="python3 -m alembic"
    else
        echo "ERROR: no hay comando 'alembic' utilizable pese a haber instalado python3-alembic."
        echo "       Sin él, el árbol preservado no sirve y el esquema realtime fallaría después."
        exit 1
    fi
    echo "    alembic: $($alembic_bin --version 2>&1 | head -1)"

    # El nombre del módulo de Python no siempre es el que usa la URL de
    # SQLAlchemy: 'mysqldb' se importa como MySQLdb y 'mysqlconnector' como
    # mysql.connector. El contrato acepta el nombre de la URL; aquí se traduce.
    local modulo
    case "$ASTERISK_DB_DRIVER" in
        pymysql)        modulo="pymysql" ;;
        mysqldb)        modulo="MySQLdb" ;;
        mysqlconnector) modulo="mysql.connector" ;;
    esac

    if ! ASTERISK_MODULO="$modulo" python3 -c 'import importlib,os; importlib.import_module(os.environ["ASTERISK_MODULO"])' 2>/dev/null; then
        echo "ERROR: el driver de Python '${modulo}' (para ASTERISK_DB_DRIVER=${ASTERISK_DB_DRIVER}) no se puede importar."
        echo "       Alembic no podrá abrir la conexión a la base realtime."
        exit 1
    fi
    echo "    driver: ${modulo} importable"

    # La conexión real. Todo por entorno: interpolar el usuario o el host dentro
    # del código Python haría que un valor con comillas se ejecutara como código.
    echo "--- probando la conexión a la base realtime ---"
    if ASTERISK_MODULO="$modulo" python3 - <<'PYCHK'
import os, sys
try:
    import importlib
    drv = importlib.import_module(os.environ["ASTERISK_MODULO"])
    c = drv.connect(
        host=os.environ["ASTERISK_DB_HOST"],
        port=int(os.environ["ASTERISK_DB_PORT"]),
        user=os.environ["ASTERISK_DB_USER"],
        password=os.environ.get("ASTERISK_DB_PASSWORD", ""),
        connect_timeout=10,
    )
    c.close()
except Exception as e:
    sys.stderr.write(str(e) + "\n")
    sys.exit(1)
PYCHK
    then
        echo "    conexión a ${ASTERISK_DB_HOST}:${ASTERISK_DB_PORT} verificada"
    else
        echo "ERROR: el driver no pudo conectar a ${ASTERISK_DB_HOST}:${ASTERISK_DB_PORT} como '${ASTERISK_DB_USER}'."
        echo "       Se aborta ahora, antes de compilar: el esquema realtime fallaría igual"
        echo "       media hora más tarde, con el tiempo ya gastado."
        exit 1
    fi

    # Usuario dedicado del sistema (nunca root). Va aquí porque `make install`
    # necesita que exista para asignarle los directorios.
    if ! id asterisk >/dev/null 2>&1; then
        echo "--- creando usuario de sistema 'asterisk' ---"
        groupadd --system asterisk
        useradd --system --gid asterisk --no-create-home \
                --home-dir /var/lib/asterisk --shell /usr/sbin/nologin asterisk
    else
        echo "--- usuario 'asterisk' ya existe, se respeta ---"
    fi
}

fase_compilar() {
    if ya_instalado; then
        echo "    (para forzar una recompilación, desinstala primero)"
        sin_trabajo "--- Asterisk ${ASTERISK_VERSION} ya está instalado: no se compila ---"
        return 0
    fi

    if fuentes_compiladas; then
        sin_trabajo "--- el árbol ya está compilado en ${SRCDIR}: se conserva ---"
        return 0
    fi

    asegurar_fuentes
    cd "$SRCDIR"

    # pjproject y jansson bundled: PJSIP necesita una pjproject compilada
    # específicamente para Asterisk; la del sistema no sirve.
    echo "--- ./configure (libdir derivado: ${LIBDIR}) ---"
    ./configure \
        --with-pjproject-bundled \
        --with-jansson-bundled \
        --libdir="${LIBDIR}"

    echo "--- generando menuselect.makeopts ---"
    make menuselect.makeopts
    local MS="menuselect/menuselect"

    echo "--- habilitando módulos requeridos ---"
    local m
    for m in "${MODULOS_REQUERIDOS[@]}"; do
        $MS --enable "$m" menuselect.makeopts
    done

    # ── Lo que hace que «estricto» signifique algo ──
    #
    # `menuselect --enable X` NO falla cuando X no se puede construir: si falta la
    # biblioteca de desarrollo, menuselect deja el módulo apagado y devuelve 0.
    # Un `unixodbc-dev` ausente daba así una compilación entera —media hora— que
    # terminaba «bien» y sin res_odbc, y el fallo salía a la luz mucho después
    # como una central que arranca pero no lee su base realtime.
    #
    # Se comprueba sobre `menuselect.makeopts`, que es el archivo que el propio
    # menuselect acaba de escribir y el que va a leer el `make`.
    #
    # Ese archivo lista los módulos EXCLUIDOS, repartidos POR CATEGORÍA —una
    # línea MENUSELECT_RES, otra MENUSELECT_APPS, otra MENUSELECT_CHANNELS…— y no
    # en una sola lista. Que uno de los requeridos aparezca en la suya, después de
    # haberlo pedido, significa exactamente que menuselect no pudo habilitarlo.
    #
    # Solo se miran las categorías donde viven módulos. MENUSELECT_BUILD_DEPS y
    # MENUSELECT_CFLAGS también empiezan igual pero contienen otra cosa, y contar
    # sus tokens como «módulos excluidos» daría abortos falsos.
    echo "--- comprobando que quedaron habilitados de verdad ---"
    if ! REQUERIDOS="${MODULOS_REQUERIDOS[*]}" python3 - menuselect.makeopts <<'PYCHECK'
import os, sys

CATEGORIAS_DE_MODULOS = (
    'ADDONS', 'APPS', 'BRIDGES', 'CDR', 'CEL', 'CHANNELS',
    'CODECS', 'FORMATS', 'FUNCS', 'PBX', 'RES',
)

excluidos = set()
with open(sys.argv[1], encoding='utf-8') as fh:
    for linea in fh:
        if not linea.startswith('MENUSELECT_'):
            continue
        clave, _, valor = linea.partition('=')
        if clave[len('MENUSELECT_'):].strip() in CATEGORIAS_DE_MODULOS:
            excluidos.update(valor.split())

faltantes = [m for m in os.environ['REQUERIDOS'].split() if m in excluidos]

if faltantes:
    print('ERROR: menuselect no pudo habilitar módulos que el módulo VoIP necesita:')
    for m in faltantes:
        print('         · %s' % m)
    print('       Casi siempre es una biblioteca de desarrollo ausente en tiempo de')
    print('       ./configure (unixodbc-dev para los res_*odbc). Compilar así daría un')
    print('       Asterisk que arranca y no sirve; se aborta ANTES del make.')
    sys.exit(1)

print('    %d módulos requeridos, ninguno excluido por menuselect'
      % len(os.environ['REQUERIDOS'].split()))
PYCHECK
    then
        exit 1
    fi

    # ── Backends de realtime/CDR/CEL que esta plataforma NO usa ──
    #
    # La plataforma es MySQL por ODBC, y solo eso. Los demás backends se compilan
    # por omisión y luego fallan al cargar en CADA arranque, dejando en el log
    # media docena de ERROR de PostgreSQL, LDAP, RADIUS y TDS que no significan
    # nada. Un log donde los errores normales son ruido es un log donde el error
    # de verdad pasa desapercibido.
    #
    # Es una lista por nombre, no un «apágalo todo y enciende estos veinte»:
    # Asterisk necesita más de cien módulos que nadie nombra en un requisito, y
    # recortarlos a ciegas produce una central que no levanta.
    echo "--- descartando backends que esta plataforma no usa ---"
    local b
    for b in res_config_pgsql cdr_pgsql cel_pgsql \
             res_config_ldap \
             cdr_radius cel_radius \
             cdr_tds cel_tds \
             cdr_sqlite3_custom cel_sqlite3_custom cdr_custom cel_custom \
             cdr_mysql res_config_sqlite3 cdr_beanstalkd; do
        $MS --disable "$b" menuselect.makeopts 2>/dev/null || true
    done

    # BUILD_NATIVE off: es una VM. Compilar con instrucciones nativas del host
    # puede reventar si la VM migra a otro nodo del clúster.
    echo "--- desactivando BUILD_NATIVE ---"
    $MS --disable BUILD_NATIVE menuselect.makeopts

    # Sonidos en alaw, que es el códec de la plataforma: en GSM habría que
    # transcodificar cada prompt en tiempo real. Inglés como respaldo por si
    # algún prompt no existe en español.
    echo "--- habilitando sonidos en alaw (ES + EN respaldo + MOH) ---"
    $MS --enable CORE-SOUNDS-ES-ALAW --enable CORE-SOUNDS-EN-ALAW \
        --enable MOH-OPSOUND-ALAW menuselect.makeopts

    echo "--- descartando el resto de variantes de sonido ---"
    local s
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

    echo "--- make -j$(nproc) ---"
    make -j"$(nproc)"
    echo "--- compilado; el árbol se conserva para la fase instalar ---"
}

fase_instalar() {
    # Lo que pidió el operador explícitamente: si ya está, no se reinstala encima.
    if ya_instalado; then
        echo "--- Asterisk ${ASTERISK_VERSION} ya está instalado en /usr/sbin/asterisk ---"
        echo "    No se reinstala encima. Reinstalar sobreescribe módulos y sonidos de una"
        echo "    central que puede estar dando servicio."

        # Adoptar una instalación ajena tiene una consecuencia que hay que decir
        # en voz alta: sin haber corrido `make samples` nosotros, no hay huella
        # de cuál era el contenido original de /etc/asterisk, así que no se puede
        # distinguir «ejemplo que nadie tocó» de «archivo que el operador ajustó».
        #
        # Ante la duda gana no pisar: el generador propondrá su configuración como
        # .nuevo en vez de aplicarla. Es lo correcto —nadie quiere un provisionador
        # que se lleve por delante la configuración de una central en servicio—
        # pero significa que en este servidor la configuración NO queda automática,
        # y eso no puede descubrirse leyendo un paso que dice «completado».
        if [[ ! -f "${ASTERISK_SOPORTE_DIR}/huellas-ejemplos.json" ]]; then
            echo
            echo "    AVISO: se está ADOPTANDO una instalación que no hizo este provisionador."
            echo "    No hay huella de los ejemplos originales, así que /etc/asterisk se trata"
            echo "    entero como configuración ajena: lo que MegaISP genere se dejará al lado"
            echo "    como .nuevo y NO se aplicará solo. Revísalos y muévelos a mano, o"
            echo "    desinstala y provisiona de cero si la central no está dando servicio."
        fi

        sin_trabajo ""
        return 0
    fi

    local otra
    otra="$(version_instalada || true)"
    if [[ -n "$otra" ]]; then
        echo "--- hay Asterisk ${otra} instalado y el manifiesto pide ${ASTERISK_VERSION} ---"
        echo "    Se instala encima la versión del manifiesto, que es la que manda."
    fi

    fuentes_compiladas || {
        echo "ERROR: no hay un árbol compilado en ${SRCDIR}."
        echo "       Corre antes la fase compilar."
        exit 1
    }

    cd "$SRCDIR"
    echo "--- make install ---"
    make install
    echo "--- make samples ---"
    make samples
    ldconfig

    registrar_huellas_de_ejemplos

    echo "--- instalado: $(version_instalada || echo '¿?') ---"
}

# ── La huella de los ejemplos que acaba de dejar `make samples` ──────────
#
# Esto es lo que le permite al generador de configuración distinguir «archivo
# que Asterisk acaba de instalar como ejemplo» de «archivo que alguien editó a
# mano». Sin esa distinción no hay forma de cumplir las dos reglas a la vez:
#
#   · No pisar NUNCA lo que un operador ajustó por una razón.
#   · Dejar la central configurada en una instalación nueva.
#
# Y al no poder distinguir, ganaba la primera: en un servidor recién instalado
# `make samples` llena /etc/asterisk, el generador encontraba los nueve destinos
# ocupados, escribía sus propuestas como .nuevo al lado y no aplicaba ninguna.
# El paso se asentaba como «completado» y la central quedaba corriendo con los
# ejemplos de Asterisk: sin realtime, sin DSN, sin PJSIP. Un éxito que no lo era.
#
# Es el modelo de los conffiles de dpkg: se guarda la huella de lo que dejamos
# nosotros, y solo se considera «ajeno» lo que no coincide con ella.
registrar_huellas_de_ejemplos() {
    local destino="${ASTERISK_SOPORTE_DIR}/huellas-ejemplos.json"

    echo "--- registrando la huella de los ejemplos recién instalados ---"
    mkdir -p "$ASTERISK_SOPORTE_DIR"

    python3 - "$destino" <<'PYHUELLAS'
import glob, hashlib, json, os, sys

destino = sys.argv[1]
huellas = {}

for ruta in sorted(glob.glob('/etc/asterisk/*.conf')):
    try:
        with open(ruta, 'rb') as fh:
            huellas[os.path.basename(ruta)] = hashlib.sha256(fh.read()).hexdigest()
    except OSError:
        # Un archivo ilegible no debe tumbar la instalación: simplemente no se
        # registra, y el generador lo tratará como ajeno (que es lo prudente).
        pass

with open(destino, 'w', encoding='utf-8') as fh:
    json.dump(huellas, fh, indent=2, sort_keys=True)

print('    %d ejemplos registrados en %s' % (len(huellas), destino))
PYHUELLAS

    chmod 644 "$destino"
}

fase_preservar_alembic() {
    # `contrib/ast-db-manage` es lo único que genera el esquema de las `ps_*`. Si
    # se borra con el árbol, el provisionador se queda sin con qué crear la base
    # realtime y la instalación muere a la mitad.
    if alembic_preservado && [[ "$(cat "${ASTERISK_SOPORTE_DIR}/VERSION-ASTERISK" 2>/dev/null)" == "$ASTERISK_VERSION" ]]; then
        echo "--- el árbol de Alembic de ${ASTERISK_VERSION} ya está preservado ---"
        echo "    $(find "${ASTERISK_SOPORTE_DIR}/alembic/config/versions" -name '*.py' | wc -l) migraciones en ${ASTERISK_SOPORTE_DIR}/alembic"
        sin_trabajo ""
        return 0
    fi

    # Extraer basta: `contrib/` viene en el tarball y no requiere compilación.
    # Por eso esta fase puede cumplirse en un servidor donde Asterisk ya está
    # instalado pero el árbol de soporte se perdió, sin recompilar nada.
    asegurar_fuentes

    if [[ ! -d "${SRCDIR}/contrib/ast-db-manage" ]]; then
        echo "ERROR: no está contrib/ast-db-manage en las fuentes. Sin él no hay forma de"
        echo "       crear la base realtime, y la instalación quedaría a medias."
        exit 1
    fi

    echo "--- preservando el árbol de Alembic en ${ASTERISK_SOPORTE_DIR}/alembic ---"
    mkdir -p "${ASTERISK_SOPORTE_DIR}"
    rm -rf "${ASTERISK_SOPORTE_DIR}/alembic"
    cp -a "${SRCDIR}/contrib/ast-db-manage" "${ASTERISK_SOPORTE_DIR}/alembic"
    echo "${ASTERISK_VERSION}" > "${ASTERISK_SOPORTE_DIR}/VERSION-ASTERISK"
    echo "    $(find "${ASTERISK_SOPORTE_DIR}/alembic/config/versions" -name '*.py' 2>/dev/null | wc -l) migraciones de Alembic preservadas"

    # Ya nadie necesita el árbol: pesa cientos de MB y no se queda en el
    # servidor de nadie. Esta es la única fase que lo borra en camino de éxito.
    echo "--- limpiando el árbol de fuentes (ya no hace falta) ---"
    rm -rf "$SRCDIR"
}

# ── El DSN de unixODBC ────────────────────────────────────────────────────
#
# res_odbc.conf no abre la base por sí solo: nombra un DSN («asterisk-connector»)
# y quien lo resuelve es unixODBC, leyendo /etc/odbc.ini y /etc/odbcinst.ini. Si
# ese DSN no existe, Asterisk carga res_odbc sin quejarse y `odbc show all` sale
# vacío: el realtime queda mudo y las extensiones no se registran.
#
# En dev estos dos archivos existían porque alguien los escribió A MANO al
# instalar Asterisk la primera vez, y por eso la prueba de punta a punta no los
# echaba de menos. En un servidor recién puesto no están, que es justo el caso
# que este provisionador tiene que cubrir.
#
# Se actualiza SOLO la sección propia. Estos dos archivos son de todo el sistema
# —otra aplicación puede tener ahí su propio DSN— así que reescribirlos enteros
# sería romperle la conexión a un tercero.
upsert_seccion_ini() {
    local archivo="$1" seccion="$2" cuerpo="$3"

    touch "$archivo"

    ARCHIVO="$archivo" SECCION="$seccion" CUERPO="$cuerpo" python3 - <<'PYINI'
import os

archivo = os.environ['ARCHIVO']
seccion = os.environ['SECCION']
cuerpo  = os.environ['CUERPO'].strip('\n')

with open(archivo, encoding='utf-8') as fh:
    lineas = fh.read().splitlines()

salida, dentro, reemplazada = [], False, False

for linea in lineas:
    marca = linea.strip()
    if marca.startswith('[') and marca.endswith(']'):
        # Al abrir CUALQUIER sección se deja de estar dentro de la nuestra.
        dentro = (marca == '[' + seccion + ']')
        if dentro:
            salida.append('[' + seccion + ']')
            salida.extend(cuerpo.split('\n'))
            reemplazada = True
            continue
    if not dentro:
        salida.append(linea)

if not reemplazada:
    if salida and salida[-1].strip() != '':
        salida.append('')
    salida.append('[' + seccion + ']')
    salida.extend(cuerpo.split('\n'))

with open(archivo, 'w', encoding='utf-8') as fh:
    fh.write('\n'.join(salida).rstrip('\n') + '\n')
PYINI
}

# ── El directorio de lo que MegaISP genera ────────────────────────────────
#
# extensions.conf termina con un `#include` a megaisp_dialplan.conf, y Asterisk
# se queja en cada arranque si ese archivo no existe. El provisionador lo crea
# vacío —con su encabezado— para que el include siempre apunte a algo, incluso
# en una instalación recién hecha donde todavía no hay grupos ni troncales que
# generar. MegaISP lo reescribe cuando los haya.
#
# Fuera del árbol de la aplicación web (§6): Asterisk corre como root y no debe
# leer configuración de un directorio escribible por www-data.
preparar_generados_dir() {
    echo "--- preparando ${ASTERISK_GENERADOS_DIR} ---"
    mkdir -p "$ASTERISK_GENERADOS_DIR"

    local dialplan="${ASTERISK_GENERADOS_DIR}/megaisp_dialplan.conf"

    if [[ ! -f "$dialplan" ]]; then
        cat > "$dialplan" <<'GEN'
; MegaISP — lo generado automáticamente. No editar a mano.
; Vacío hasta que haya grupos de timbrado o troncales que generar.
GEN
        echo "    creado ${dialplan} (vacío, para que el #include no apunte al aire)"
    else
        echo "    ${dialplan} ya presente, se respeta"
    fi

    chown -R asterisk:asterisk "$ASTERISK_GENERADOS_DIR" 2>/dev/null || true
    chmod 750 "$ASTERISK_GENERADOS_DIR"
}

escribir_dsn_odbc() {
    echo "--- registrando el DSN de unixODBC ---"

    # El driver se busca donde lo deja el paquete de ESTA arquitectura; el
    # provisionador no puede dar por hecho x86_64 (ajuste 3 de los seis).
    local driver="${LIBDIR}/odbc/libmaodbc.so"

    if [[ ! -f "$driver" ]]; then
        echo "ERROR: no está el conector ODBC de MariaDB en ${driver}."
        echo "       Sin él no hay base realtime: lo instala el paquete odbc-mariadb"
        echo "       en la fase 'dependencias'."
        exit 1
    fi

    upsert_seccion_ini /etc/odbcinst.ini "MariaDB" \
"Description = MariaDB ODBC Connector
Driver      = ${driver}
Threading   = 0"

    # Sin contraseña ni usuario a propósito: esos van en res_odbc.conf, que sí
    # tiene permisos restringidos. /etc/odbc.ini lo lee todo el sistema.
    upsert_seccion_ini /etc/odbc.ini "${ASTERISK_ODBC_DSN}" \
"Description = Base realtime de Asterisk (MegaISP)
Driver      = MariaDB
Server      = ${ASTERISK_DB_HOST}
Port        = ${ASTERISK_DB_PORT}
Database    = ${ASTERISK_DB_NAME}
Charset     = utf8mb4"

    chmod 644 /etc/odbcinst.ini /etc/odbc.ini

    echo "    driver: ${driver}"
    echo "    DSN [${ASTERISK_ODBC_DSN}] → ${ASTERISK_DB_HOST}:${ASTERISK_DB_PORT}/${ASTERISK_DB_NAME}"

    # Que unixODBC lo confirme, en vez de darlo por hecho: isql abre la conexión
    # de verdad. Aquí todavía es barato descubrir que no abre.
    if command -v isql >/dev/null 2>&1; then
        if printf 'quit\n' | isql -v "${ASTERISK_ODBC_DSN}" "${ASTERISK_DB_USER}" "${ASTERISK_DB_PASSWORD}" >/dev/null 2>&1; then
            echo "    conexión ODBC verificada con isql"
        else
            echo "ERROR: el DSN [${ASTERISK_ODBC_DSN}] quedó escrito pero NO abre la base."
            echo "       Asterisk cargaría res_odbc y el realtime quedaría mudo."
            exit 1
        fi
    fi
}

fase_generar_config() {
    # asterisk.conf NO se toca aquí: lo escribe la plantilla de MegaISP.
    #
    # Antes esta fase lo parcheaba con `sed` (runuser, rungroup, defaultlanguage)
    # y además existía `asterisk.conf.tpl`, que lo escribe entero. Dos escritores
    # para el mismo archivo, y el resultado no era que uno ganara: el archivo
    # parcheado ya no coincidía con la huella del ejemplo, así que el generador lo
    # daba por «editado a mano» y dejaba su versión al lado como .nuevo sin
    # aplicarla. Cada provisión reportaba un archivo propuesto que nadie había
    # tocado.
    #
    # Gana la plantilla: es declarativa, lleva las tres líneas del sed y además el
    # bloque [directories] con el libdir derivado de esta arquitectura.

    echo "--- ajustando propietario de directorios ---"
    chown -R asterisk:asterisk \
        /etc/asterisk /var/lib/asterisk /var/log/asterisk \
        /var/spool/asterisk "${LIBDIR}/asterisk" 2>/dev/null || true
    chmod -R u=rwX,g=rX,o= /etc/asterisk

    # ── alembic.ini ──
    #
    # Se GENERA aquí, con las credenciales de este servidor, y NUNCA se versiona:
    # lleva la contraseña de la base realtime en su sqlalchemy.url.
    #
    # Todos los valores viajan por ENTORNO al intérprete, ninguno interpolado en
    # el código. La versión anterior los metía dentro de un heredoc citado
    # (<<'PYINI'), donde bash no expande nada: `${ASTERISK_DB_DRIVER}` llegaba
    # literal a Python y `str.format()` lo leía como un campo suyo, con lo que la
    # provisión moría en `KeyError: 'ASTERISK_DB_DRIVER'` — después de compilar.
    # Por entorno no hay dos capas de sustitución que confundir, y de paso una
    # contraseña con `{`, `$` o comillas deja de poder romper (o ejecutar) nada.
    echo "--- generando alembic.ini (fuera del repo, 600) ---"

    local muestra="${ASTERISK_SOPORTE_DIR}/alembic/config.ini.sample"
    local ini="${ASTERISK_SOPORTE_DIR}/alembic/config.ini"

    [[ -f "$muestra" ]] || {
        echo "ERROR: no está ${muestra}."
        echo "       Corre antes la fase preservar_alembic."
        exit 1
    }

    cp "$muestra" "$ini"
    # Los permisos ANTES de escribir el secreto: si se pusieran después y el
    # proceso muriera en medio, la contraseña quedaría en un archivo legible por
    # todos. Fue exactamente lo que pasó al fallar el KeyError.
    chmod 600 "$ini"
    chown root:root "$ini" 2>/dev/null || true

    ASTERISK_INI="$ini" python3 - <<'PYINI'
import os, re, sys, urllib.parse

ruta = os.environ["ASTERISK_INI"]

url = "mysql+{drv}://{u}:{p}@{h}:{P}/{d}".format(
    drv=os.environ["ASTERISK_DB_DRIVER"],
    u=urllib.parse.quote_plus(os.environ["ASTERISK_DB_USER"]),
    p=urllib.parse.quote_plus(os.environ.get("ASTERISK_DB_PASSWORD", "")),
    h=os.environ["ASTERISK_DB_HOST"],
    P=os.environ["ASTERISK_DB_PORT"],
    d=os.environ["ASTERISK_DB_NAME"],
)

txt = open(ruta, encoding="utf-8").read()
txt, n = re.subn(r"(?m)^sqlalchemy\.url\s*=.*$", "sqlalchemy.url = " + url.replace("\\", "\\\\"), txt)

# Si el sample cambia de forma y la línea no aparece, el archivo quedaría con la
# URL de ejemplo (`user:pass@localhost`) y Alembic apuntaría a una base que no
# es. Se falla aquí, no allá.
if n != 1:
    sys.stderr.write(
        "no se pudo fijar sqlalchemy.url en %s: se esperaba 1 coincidencia y hubo %d\n" % (ruta, n)
    )
    sys.exit(1)

open(ruta, "w", encoding="utf-8").write(txt)
PYINI

    # Que no quede en duda que la sustitución ocurrió: se comprueba la forma de
    # la línea sin imprimir la contraseña.
    if ! grep -qE "^sqlalchemy\.url = mysql\+${ASTERISK_DB_DRIVER}://" "$ini"; then
        echo "ERROR: ${ini} no quedó con la cadena de conexión esperada."
        exit 1
    fi
    echo "    ${ini} (permisos $(stat -c '%a' "$ini"), no versionado)"
    echo "    sqlalchemy.url → mysql+${ASTERISK_DB_DRIVER}://${ASTERISK_DB_USER}:***@${ASTERISK_DB_HOST}:${ASTERISK_DB_PORT}/${ASTERISK_DB_NAME}"

    # ── Lo que MegaISP genera ──
    preparar_generados_dir

    # ── DSN de unixODBC ──
    escribir_dsn_odbc

    # ── Unit de systemd, instalada y DESHABILITADA ──
    #
    # RuntimeDirectory=asterisk NO es decorativo: es lo que hace que la central
    # se pueda administrar.
    #
    # Asterisk abre su socket de control en /var/run/asterisk/asterisk.ctl, y ese
    # directorio vive en tmpfs — se evapora en cada arranque del servidor. Sin
    # esta línea sólo existe el que dejó `make install`, propiedad de root, y el
    # proceso —que corre como el usuario `asterisk`— no puede escribir ahí.
    #
    # El modo de fallo es de los malos, porque NO se parece a un fallo: Asterisk
    # arranca, carga sus módulos, systemd lo reporta `active (running)` y el log
    # no dice nada. Pero `asterisk -rx` contesta «Unable to connect to remote
    # asterisk», así que no hay CLI, no hay `core reload`, y todo lo que valide
    # la central preguntándole a ella da por muerto lo que está vivo.
    #
    # systemd crea el directorio con el dueño y el modo correctos en cada
    # arranque y lo retira al parar. Es la pieza que reemplaza al `mkdir` a mano
    # que en un servidor instalado a mano nadie nota que hizo.
    echo "--- instalando unit de systemd ---"
    cat > /etc/systemd/system/asterisk.service <<'UNIT'
[Unit]
Description=Asterisk PBX
Documentation=man:asterisk(8)
Wants=network-online.target
After=network-online.target nss-lookup.target mariadb.service mysql.service

[Service]
Type=simple
User=asterisk
Group=asterisk
RuntimeDirectory=asterisk
RuntimeDirectoryMode=0750
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
    # Deliberadamente sin 'enable' ni 'start': lo levanta el provisionador de PHP
    # después de escribir la configuración de MegaISP.
    systemctl disable asterisk 2>/dev/null || true

    # ── Validación de lo que esta fase deja ──
    echo
    echo "=== VALIDACIÓN DE LA INSTALACIÓN ==="
    /usr/sbin/asterisk -V
    # La comprobación autoritativa: el .so está en el disco o no está.
    #
    # La de `compilar` mira lo que menuselect DIJO que iba a construir, y se hace
    # allá porque avisa antes del `make`. Ésta mira lo que quedó construido, que
    # es lo único que Asterisk podrá cargar. Entre una y otra pueden pasar cosas
    # —un módulo que falla al compilar sin tumbar el `make`, un `make install`
    # incompleto— y esta fase corre después, así que es la que puede verlas.
    echo "--- módulos requeridos instalados ---"
    faltan_so=()
    for m in "${MODULOS_REQUERIDOS[@]}"; do
        [[ -f "${MODDIR}/${m}.so" ]] || faltan_so+=("$m")
    done

    if [[ ${#faltan_so[@]} -gt 0 ]]; then
        echo "ERROR: se compiló e instaló, pero estos módulos no están en ${MODDIR}:"
        printf '         · %s.so\n' "${faltan_so[@]}"
        echo "       Sin ellos la central arranca y no sirve: no habría realtime, o no"
        echo "       habría canal SIP. Se aborta aquí en vez de dejarlo para el runtime."
        exit 1
    fi
    echo "    ${#MODULOS_REQUERIDOS[@]}/${#MODULOS_REQUERIDOS[@]} presentes en ${MODDIR}"
    echo "--- módulos res_pjsip: $(ls -1 "${MODDIR}/" 2>/dev/null | grep -c '^res_pjsip') ---"
    echo "--- prompts es/*.alaw: $(ls -1 /var/lib/asterisk/sounds/es/*.alaw 2>/dev/null | wc -l) ---"
    echo "--- prompts en/*.alaw: $(ls -1 /var/lib/asterisk/sounds/en/*.alaw 2>/dev/null | wc -l) ---"
    echo "--- moh/*.alaw: $(ls -1 /var/lib/asterisk/moh/*.alaw 2>/dev/null | wc -l) ---"
    # El idioma ya no se comprueba aquí: lo fija asterisk.conf, que en este punto
    # todavía es el ejemplo de `make samples` — la plantilla se aplica en el paso
    # siguiente. Quien lo verifica ahora es el paso `validar`, y lo hace mejor:
    # se lo pregunta a Asterisk ya arrancado, que es lo que prueba que lo LEYÓ.
    echo "--- servicio (debe estar inactivo y disabled) ---"
    systemctl is-active asterisk || true
    systemctl is-enabled asterisk || true

    echo
    if [[ "$ASTERISK_MODO_DESCUBRIMIENTO" == "1" ]]; then
        echo "MODO DESCUBRIMIENTO: el provisionador ejecutará 'alembic upgrade head' y"
        echo "reportará la revisión resultante, para fijarla en el manifiesto."
    else
        echo "Revisión de esquema esperada: ${ASTERISK_ESQUEMA_REALTIME}"
    fi
    echo "El servicio NO fue arrancado: lo levanta el provisionador tras escribir la configuración."
}

fase_limpiar() {
    if [[ -d "$SRCDIR" ]]; then
        echo "--- borrando el árbol de fuentes ${SRCDIR} ---"
        rm -rf "$SRCDIR"
    fi
    # Cualquier árbol de OTRA versión también sobra.
    if compgen -G "/usr/src/asterisk-*" >/dev/null 2>&1; then
        echo "--- borrando árboles de otras versiones ---"
        rm -rf /usr/src/asterisk-*
    fi
    if [[ -d "$ASTERISK_TRABAJO" ]]; then
        echo "--- borrando el directorio de trabajo ${ASTERISK_TRABAJO} ---"
        rm -rf "$ASTERISK_TRABAJO"
    fi
    echo "--- limpio ---"
}

"fase_${FASE}"

echo "=== fase «${FASE}» terminada $(date -Is) ==="
