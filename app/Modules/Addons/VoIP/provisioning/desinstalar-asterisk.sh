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

if [[ "$CONFIRMAR" != "SI-BORRAR-ASTERISK" ]]; then
    echo "ERROR: esto borra Asterisk por completo — binario, configuración, base"
    echo "       realtime, usuario de sistema y unit."
    echo "       Para ejecutarlo: CONFIRMAR=SI-BORRAR-ASTERISK sudo -E bash $0"
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
