#!/usr/bin/env bash
#
# ═══════════════════════════════════════════════════════════════════════════
# verificar-llamada.sh — llama de una extensión sembrada a otra y comprueba
#                        que el audio va en LOS DOS sentidos
# ═══════════════════════════════════════════════════════════════════════════
#
# Es la prueba que cierra el item #9990718. Que los quince pasos digan
# «completado» no demuestra que la central sirva: lo demuestra una llamada.
#
# ─── POR QUÉ DOS TONOS DISTINTOS Y NO UNO ─────────────────────────────────
#
# Con el mismo tono en los dos lados, «se grabó audio» no distingue entre audio
# que llegó del otro extremo y audio propio que se coló por un bucle. Cada lado
# emite una frecuencia distinta —440 Hz quien llama, 880 Hz quien contesta— y se
# comprueba que CADA UNO recibió la del OTRO. Eso ya no se puede confundir con un
# eco local: prueba las dos direcciones por separado.
#
# ─── QUÉ NO ES ────────────────────────────────────────────────────────────
#
# No es la suite de pruebas del módulo (#9990720). Es una verificación de campo:
# se corre contra la central real, en el servidor real, después de provisionar.
#
# Se ejecuta SIN ARGUMENTOS a propósito: un sudoers que autoriza `bash <script>`
# no autoriza `bash <script> --loquesea`, así que todo lo que necesita lo
# averigua solo, de la central que acaba de quedar instalada.
#
set -uo pipefail

[[ $EUID -eq 0 ]] || { echo "ERROR: ejecutar como root (lee la configuración de Asterisk)."; exit 1; }

TRABAJO="$(mktemp -d /tmp/megaisp-llamada-XXXXXX)"
FRECUENCIA_A=440
FRECUENCIA_B=880
SEGUNDOS_EN_LLAMADA=6

limpiar() {
    pkill -f "baresip -f ${TRABAJO}" 2>/dev/null || true
    sleep 1
    rm -rf "$TRABAJO"
}
trap limpiar EXIT

echo "=== Verificación de llamada — $(date -Is) ==="

# ── 1. El cliente SIP de prueba ──────────────────────────────────────────
#
# baresip es la herramienta de la PRUEBA, no de la central: se instala aquí y no
# en la fase `dependencias` del provisionador, porque un servidor de producción
# no tiene por qué llevar un softphone instalado.
if ! command -v baresip >/dev/null 2>&1; then
    echo "--- instalando baresip (cliente SIP de prueba) ---"
    DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends baresip-core >/dev/null 2>&1 \
        || { echo "ERROR: no se pudo instalar baresip-core."; exit 1; }
fi

MODDIR="$(dirname "$(find /usr/lib /usr/local/lib -name 'aufile.so' -path '*baresip*' -print -quit 2>/dev/null)")"
if [[ -z "$MODDIR" || ! -d "$MODDIR" ]]; then
    echo "ERROR: no encuentro los módulos de baresip (aufile.so)."
    echo "       Sin fuente de audio no se puede probar el audio."
    exit 1
fi
echo "    módulos de baresip: ${MODDIR}"

# Sin estos tres no hay prueba, y conviene decirlo aquí y no dentro de un log de
# baresip: aufile reproduce el tono, account lee las credenciales (sin él no hay
# registro) y menu aporta el comando de marcado.
for imprescindible in aufile.so account.so menu.so; do
    [[ -f "${MODDIR}/${imprescindible}" ]] || {
        echo "ERROR: falta el módulo ${imprescindible} de baresip en ${MODDIR}."
        exit 1
    }
done

# ── 2. Qué dos extensiones usar, y con qué contraseña ────────────────────
#
# Salen de la central misma, no de una lista escrita aquí: se leen de la base
# realtime que Asterisk está usando en este momento. Así la prueba no puede
# quedar desfasada del plan de numeración.
#
# Las credenciales se sacan de /etc/asterisk/res_odbc.conf, que es de donde las
# saca el propio Asterisk. Es root-only y no se imprime nada de él.
CONF=/etc/asterisk/res_odbc.conf
[[ -f "$CONF" ]] || { echo "ERROR: no está ${CONF}: ¿corrió la provisión?"; exit 1; }

DB_USER="$(grep -oP '^\s*username\s*=>\s*\K\S+' "$CONF" | head -1)"
DB_PASS="$(grep -oP '^\s*password\s*=>\s*\K.*' "$CONF" | head -1)"
DB_NAME="$(grep -oP '^\[\K[^]]+' "$CONF" | head -1)"

[[ -n "$DB_USER" && -n "$DB_NAME" ]] || { echo "ERROR: no pude leer las credenciales de ${CONF}."; exit 1; }

consulta() {
    MYSQL_PWD="$DB_PASS" mysql -N -B -h 127.0.0.1 -u "$DB_USER" "$DB_NAME" -e "$1" 2>/dev/null
}

# Dos extensiones del rango de técnicos de campo (1200-1299).
#
# Se prefiere ése y no «las dos más bajas» porque es el único de los seis que el
# seeder llena entero y de cero: los rangos bajos suelen tener extensiones que ya
# existían de antes —con su nombre y su contraseña propios, que el seeder respeta
# por ser idempotente— y probar con ellas mediría algo distinto de lo que este
# item promete, que es que las extensiones SEMBRADAS quedan listas para usarse.
#
# Si no hubiera (una instalación con otro plan de numeración), se cae a las dos
# más bajas que existan: mejor probar con lo que haya que no probar.
pareja_del_rango() {
    consulta "
        SELECT e.id, a.password
          FROM ps_endpoints e
          JOIN ps_auths a ON a.id = e.auth
         WHERE e.id REGEXP '^[0-9]+\$' ${1}
         ORDER BY CAST(e.id AS UNSIGNED)
         LIMIT 2;" | tr '\t' ' '
}

mapfile -t PAREJA < <(pareja_del_rango "AND CAST(e.id AS UNSIGNED) BETWEEN 1200 AND 1299")

if [[ ${#PAREJA[@]} -lt 2 ]]; then
    echo "    (sin pareja en 1200-1299; se usan las dos extensiones más bajas)"
    mapfile -t PAREJA < <(pareja_del_rango "")
fi

if [[ ${#PAREJA[@]} -lt 2 ]]; then
    echo "ERROR: hacen falta al menos DOS extensiones provisionadas en la base realtime."
    echo "       Hay ${#PAREJA[@]}. La siembra del provisionador debería dejar 30."
    exit 1
fi

EXT_A="${PAREJA[0]%% *}"; SEC_A="${PAREJA[0]#* }"
EXT_B="${PAREJA[1]%% *}"; SEC_B="${PAREJA[1]#* }"

echo "    llamará ${EXT_A} → ${EXT_B}"

# ── 3. Dos softphones, cada uno con su tono ──────────────────────────────
#
# audio_player alsa,null: la máquina no tiene tarjeta de sonido, y el PCM «null»
# de ALSA existe siempre. Sin un reproductor válido baresip no completa la
# negociación de audio y la prueba fallaría por una razón que no es la central.
#
# sndfile graba las DOS direcciones de cada llamada: -enc.wav lo que se envió y
# -dec.wav lo que se recibió. -dec.wav es el que importa.
preparar_ua() {
    local dir="$1" ext="$2" sec="$3" freq="$4" answermode="$5"

    mkdir -p "${dir}/dumps"

    # Los nombres y la disponibilidad de los módulos cambian entre versiones de
    # baresip, y un `module X.so` que no existe sólo produce un aviso — pero si
    # el que falta es esencial, la llamada falla por una razón que no tiene nada
    # que ver con la central que estamos probando. Se declara sólo lo presente, y
    # se aborta con nombre y apellido si falta alguno de los imprescindibles.
    {
        echo "module_path             ${MODDIR}"

        local m
        for m in g711.so aufile.so alsa.so sndfile.so stdio.so; do
            [[ -f "${MODDIR}/${m}" ]] && echo "module                  ${m}"
        done

        # De aplicación: account.so lee el archivo de cuentas (sin él no hay
        # registro) y menu.so es quien aporta el comando /dial.
        for m in account.so contact.so menu.so; do
            [[ -f "${MODDIR}/${m}" ]] && echo "module_app              ${m}"
        done

        cat <<CFG
audio_source            aufile,${dir}/tono.wav
audio_player            alsa,null
audio_txmode            poll
sip_listen              0.0.0.0:0
CFG
    } > "${dir}/config"

    # El tono, como archivo de 8 kHz.
    #
    # No se usa el generador `ausine` de baresip porque sólo produce a 48 kHz, y
    # esta versión (1.0.0) no trae módulo de remuestreo: con PCMA —que es a 8 kHz,
    # y es el códec de esta plataforma— la fuente ni siquiera arrancaba
    # («start_source failed: Operation not supported») y la llamada se establecía
    # MUDA. Un WAV a la tasa correcta lo evita sin depender de módulos ausentes.
    RUTA="${dir}/tono.wav" FREQ="$freq" python3 - <<'PYTONO'
import math, os, struct, wave

ruta = os.environ['RUTA']
freq = float(os.environ['FREQ'])
tasa, segundos, amplitud = 8000, 30, 12000

with wave.open(ruta, 'wb') as w:
    w.setnchannels(1)
    w.setsampwidth(2)
    w.setframerate(tasa)
    w.writeframes(b''.join(
        struct.pack('<h', int(amplitud * math.sin(2 * math.pi * freq * n / tasa)))
        for n in range(tasa * segundos)
    ))
PYTONO

    cat > "${dir}/accounts" <<ACC
<sip:${ext}@127.0.0.1;transport=udp>;auth_pass=${sec};answermode=${answermode};regint=60;audio_codecs=PCMA/8000/1,PCMU/8000/1
ACC

    : > "${dir}/contacts"
    chmod 700 "$dir"
}

DIR_A="${TRABAJO}/a"; DIR_B="${TRABAJO}/b"
preparar_ua "$DIR_A" "$EXT_A" "$SEC_A" "$FRECUENCIA_A" manual
preparar_ua "$DIR_B" "$EXT_B" "$SEC_B" "$FRECUENCIA_B" auto

echo "--- levantando los dos softphones ---"
# Cada uno arranca DENTRO de su carpeta de grabaciones.
#
# El módulo sndfile de esta versión de baresip ignora `sndfile_path` y guarda en
# el directorio actual («sndfile: saving files in .»), así que los dos softphones
# escribirían sus WAV en el mismo sitio y no habría forma de saber cuál grabó
# qué — que es justo lo que esta prueba necesita distinguir. El directorio de
# trabajo sí se respeta siempre, así que se usa ése.
mkfifo "${TRABAJO}/fifo_a"
( cd "${DIR_A}/dumps" && exec baresip -f "$DIR_A" < "${TRABAJO}/fifo_a" > "${TRABAJO}/a.log" 2>&1 ) &
exec 3> "${TRABAJO}/fifo_a"

( cd "${DIR_B}/dumps" && exec baresip -f "$DIR_B" < /dev/null > "${TRABAJO}/b.log" 2>&1 ) &

# ── 4. Esperar a que los dos REGISTREN, mirando la central ───────────────
#
# Se le pregunta a Asterisk, no al softphone: lo que importa es que la central
# los tenga por registrados, que es lo que hace posible la llamada.
echo "--- esperando el registro de ambas en la central ---"
registradas=0
for _ in $(seq 1 30); do
    # Se cuentan las DOS por separado en vez de contar líneas: así una extensión
    # con dos contactos no puede hacer pasar la prueba por las dos.
    registradas=0
    contactos="$(asterisk -rx 'pjsip show contacts' 2>/dev/null || true)"
    grep -qE "[^0-9]${EXT_A}/sip:" <<<"$contactos" && registradas=$((registradas + 1))
    grep -qE "[^0-9]${EXT_B}/sip:" <<<"$contactos" && registradas=$((registradas + 1))
    [[ "$registradas" -ge 2 ]] && break
    sleep 1
done

if [[ "$registradas" -lt 2 ]]; then
    echo "ERROR: solo ${registradas}/2 extensiones se registraron en la central."
    echo "--- pjsip show contacts ---"; asterisk -rx 'pjsip show contacts' 2>&1 | head -20
    echo "--- log del softphone A ---"; tail -20 "${TRABAJO}/a.log"
    exit 1
fi
echo "    2/2 registradas"

# ── 5. La llamada ────────────────────────────────────────────────────────
echo "--- ${EXT_A} llama a ${EXT_B} ---"
printf '/dial %s\n' "$EXT_B" >&3
sleep 3

en_curso="$(asterisk -rx 'core show channels' 2>/dev/null | grep -c 'active call' || true)"
asterisk -rx 'core show channels' 2>/dev/null | tail -3

echo "--- ${SEGUNDOS_EN_LLAMADA}s de conversación ---"
sleep "$SEGUNDOS_EN_LLAMADA"

printf '/hangup\n' >&3
sleep 2
printf '/quit\n' >&3 || true
exec 3>&-
sleep 1
pkill -f "baresip -f ${TRABAJO}" 2>/dev/null || true
sleep 1

# ── 6. El veredicto: ¿qué recibió CADA uno? ──────────────────────────────
#
# Se mide la frecuencia dominante de lo que grabó cada lado en su -dec.wav. Si A
# recibió 880 y B recibió 440, el audio cruzó en las dos direcciones. Si alguno
# recibió su propia frecuencia, hay un bucle y no una llamada.
echo
echo "=== VEREDICTO ==="
DIR_A="$DIR_A" DIR_B="$DIR_B" FA="$FRECUENCIA_A" FB="$FRECUENCIA_B" python3 - <<'PYAUDIO'
import glob, math, os, sys, wave

def dominante(ruta):
    """Frecuencia con más energía, por Goertzel sobre las candidatas."""
    with wave.open(ruta, 'rb') as w:
        if w.getsampwidth() != 2:
            return None, 0
        tasa = w.getframerate()
        crudo = w.readframes(w.getnframes())

    n = len(crudo) // 2
    if n < tasa // 4:            # menos de un cuarto de segundo: no es señal
        return None, 0

    muestras = [int.from_bytes(crudo[i*2:i*2+2], 'little', signed=True) for i in range(n)]
    rms = math.sqrt(sum(m*m for m in muestras) / n)

    mejor, mejor_e = None, 0.0
    for objetivo in (440, 880):
        k = 2 * math.cos(2 * math.pi * objetivo / tasa)
        s1 = s2 = 0.0
        for m in muestras:
            s0 = m + k * s1 - s2
            s2, s1 = s1, s0
        energia = s1*s1 + s2*s2 - k*s1*s2
        if energia > mejor_e:
            mejor, mejor_e = objetivo, energia

    return mejor, rms

def recibido(directorio):
    dumps = sorted(glob.glob(os.path.join(directorio, 'dumps', '*dec.wav')))
    if not dumps:
        return None, 0, None
    ruta = max(dumps, key=os.path.getsize)
    f, rms = dominante(ruta)
    return f, rms, os.path.basename(ruta)

esperado_a = int(os.environ['FB'])   # A debe recibir el tono de B
esperado_b = int(os.environ['FA'])   # B debe recibir el tono de A

fa, rms_a, archivo_a = recibido(os.environ['DIR_A'])
fb, rms_b, archivo_b = recibido(os.environ['DIR_B'])

filas = [
    ('A→B  (lo que recibió quien llamó)',   fa, esperado_a, rms_a, archivo_a),
    ('B→A  (lo que recibió quien contestó)', fb, esperado_b, rms_b, archivo_b),
]

ok = True
for etiqueta, medido, esperado, rms, archivo in filas:
    if archivo is None:
        print('  %-38s SIN GRABACIÓN — no hubo audio' % etiqueta)
        ok = False
        continue
    bien = (medido == esperado) and rms > 200
    ok = ok and bien
    print('  %-38s %s  recibió %sHz (esperado %dHz)  nivel %d' % (
        etiqueta, 'OK  ' if bien else 'FALLA', medido or '?', esperado, rms))

print()
if ok:
    print('  AUDIO EN AMBOS SENTIDOS: cada lado recibió el tono del otro.')
    sys.exit(0)

print('  NO se confirmó audio bidireccional.')
sys.exit(1)
PYAUDIO
VEREDICTO=$?

echo
if [[ $VEREDICTO -eq 0 ]]; then
    echo "=== LLAMADA VERIFICADA: ${EXT_A} ↔ ${EXT_B}, audio en ambos sentidos ==="
else
    echo "=== FALLÓ la verificación de la llamada ==="
    echo "--- softphone que llamó (${EXT_A}) ---"
    tail -25 "${TRABAJO}/a.log"
    echo "--- softphone que contestaba (${EXT_B}) ---"
    tail -25 "${TRABAJO}/b.log"
    echo "--- la central ---"
    tail -20 /var/log/asterisk/messages.log 2>/dev/null | grep -viE 'deprecated|adsi|getcpeid' | tail -12
fi

exit $VEREDICTO
