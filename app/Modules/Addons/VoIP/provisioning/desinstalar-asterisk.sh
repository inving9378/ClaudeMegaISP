#!/usr/bin/env bash
#
# desinstalar-asterisk.sh — deja el servidor como si Asterisk nunca se hubiera
# instalado, para poder probar la provisión de cero.
#
# ⚠️ DESTRUCTIVO Y A PROPÓSITO. Existe para la prueba de fuego del item #9990718:
# «desinstalar Asterisk de dev por completo y aplicar la actualización, verificando
# que quedó todo funcionando sin tocar el servidor a mano». Si en algún punto hay
# que intervenir, el provisionador está incompleto y ese punto es el trabajo que
# falta.
#
# NO se ejecuta en producción ni en la instalación de un cliente. Exige
# confirmación explícita.
#
set -euo pipefail

: "${CONFIRMAR:=}"
: "${ASTERISK_DB_NAME:=asterisk}"
: "${SOPORTE_DIR:=/usr/share/megaisp-asterisk}"

# La confirmación también se acepta como argumento, y no solo por entorno.
#
# Misma razón que en el provisionador: `sudo` trae `env_reset` por omisión y
# limpia CONFIRMAR en el camino, así que `CONFIRMAR=… sudo -E bash …` sólo
# funciona si el sudoers de ese servidor trae la etiqueta SETENV. Depender de eso
# convierte la configuración de un servidor ajeno en requisito para poder borrar.
#
# Aquí el valor no es un secreto —es una frase fija cuyo único propósito es que
# nadie borre una central sin querer— así que pasarlo como argumento, donde un
# `ps` lo puede ver, no expone nada.
for arg in "$@"; do
    case "$arg" in
        --confirmar=*) CONFIRMAR="${arg#*=}" ;;
        --base=*)      ASTERISK_DB_NAME="${arg#*=}" ;;
        *) echo "ERROR: argumento no reconocido: ${arg}"; exit 1 ;;
    esac
done

if [[ "$CONFIRMAR" != "SI-BORRAR-ASTERISK" ]]; then
    echo "ERROR: esto borra Asterisk por completo — binario, configuración, base"
    echo "       realtime, usuario de sistema y unit."
    echo "       Para ejecutarlo:  sudo -n bash $0 --confirmar=SI-BORRAR-ASTERISK"
    exit 1
fi

[[ $EUID -eq 0 ]] || { echo "ERROR: ejecutar como root."; exit 1; }

echo "=== Desinstalación de Asterisk — $(date -Is) ==="

echo "--- 1. deteniendo y deshabilitando el servicio ---"
systemctl stop asterisk 2>/dev/null || true
systemctl disable asterisk 2>/dev/null || true
rm -f /etc/systemd/system/asterisk.service /lib/systemd/system/asterisk.service
systemctl daemon-reload
systemctl reset-failed asterisk 2>/dev/null || true

echo "--- 2. binarios y módulos ---"
rm -f /usr/sbin/asterisk /usr/sbin/astgenkey /usr/sbin/astdb2* /usr/sbin/safe_asterisk
rm -f /usr/sbin/rasterisk /usr/sbin/astcanary /usr/sbin/astversion /usr/sbin/aelparse
rm -f /usr/sbin/astdicts /usr/sbin/conf2ael /usr/sbin/muted /usr/sbin/smsq /usr/sbin/stereorize
rm -f /usr/sbin/streamplayer /usr/sbin/astcanary
for d in /usr/lib/*/asterisk; do rm -rf "$d"; done
rm -rf /usr/include/asterisk /usr/include/asterisk.h

echo "--- 3. configuración, datos y logs ---"
rm -rf /etc/asterisk /var/lib/asterisk /var/log/asterisk /var/spool/asterisk /var/run/asterisk

echo "--- 4. árbol de soporte (Alembic preservado) ---"
rm -rf "$SOPORTE_DIR"

echo "--- 5. base realtime ---"
mysql -e "DROP DATABASE IF EXISTS \`${ASTERISK_DB_NAME}\`;"

echo "--- 5b. DSN de unixODBC ---"
# Se quita SOLO la sección propia. /etc/odbc.ini es de todo el sistema y otra
# aplicación puede tener ahí su DSN; borrar el archivo entero le cortaría la
# conexión a un tercero que no tiene nada que ver con esta central.
#
# Tiene que salir para que la prueba de cero sea honesta: el provisionador ahora
# crea este DSN, y dejarlo puesto haría pasar una corrida que en un servidor
# recién instalado fallaría.
DSN="${ASTERISK_ODBC_DSN:-asterisk-connector}" python3 - <<'PYDSN'
import os

seccion = os.environ['DSN']

for archivo in ('/etc/odbc.ini',):
    if not os.path.exists(archivo):
        continue

    with open(archivo, encoding='utf-8') as fh:
        lineas = fh.read().splitlines()

    salida, dentro, quitada = [], False, False
    for linea in lineas:
        marca = linea.strip()
        if marca.startswith('[') and marca.endswith(']'):
            dentro = (marca == '[' + seccion + ']')
            if dentro:
                quitada = True
                continue
        if not dentro:
            salida.append(linea)

    if quitada:
        with open(archivo, 'w', encoding='utf-8') as fh:
            fh.write('\n'.join(salida).rstrip('\n') + '\n')
        print('    quitado [%s] de %s' % (seccion, archivo))
    else:
        print('    no había [%s] en %s' % (seccion, archivo))
PYDSN

echo "--- 6. usuario de sistema ---"
userdel asterisk 2>/dev/null || true
groupdel asterisk 2>/dev/null || true

echo "--- 7. fuentes, por si quedaron ---"
rm -rf /usr/src/asterisk-*

echo
echo "=== VERIFICACIÓN: no debe quedar nada ==="
printf "  binario /usr/sbin/asterisk : %s\n" "$([ -e /usr/sbin/asterisk ] && echo 'QUEDA' || echo 'no')"
printf "  /etc/asterisk              : %s\n" "$([ -d /etc/asterisk ] && echo 'QUEDA' || echo 'no')"
printf "  /var/lib/asterisk          : %s\n" "$([ -d /var/lib/asterisk ] && echo 'QUEDA' || echo 'no')"
printf "  %-26s : %s\n" "$SOPORTE_DIR" "$([ -d "$SOPORTE_DIR" ] && echo 'QUEDA' || echo 'no')"
printf "  usuario asterisk           : %s\n" "$(id asterisk >/dev/null 2>&1 && echo 'QUEDA' || echo 'no')"
printf "  unit de systemd            : %s\n" "$([ -e /etc/systemd/system/asterisk.service ] && echo 'QUEDA' || echo 'no')"
printf "  base %-21s : %s\n" "$ASTERISK_DB_NAME" "$(mysql -N -e "SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name='${ASTERISK_DB_NAME}'" 2>/dev/null)"
echo
echo "=== Servidor limpio. Ahora: php artisan voip:provisionar --descubrimiento ==="
