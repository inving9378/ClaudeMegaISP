#!/usr/bin/env bash
# =============================================================================
# 30-acceso-web.sh — El usuario web de MegaISP en el grupo `asterisk`
# =============================================================================
# Se hace `source` desde install.sh. Define provision_acceso_web.
#
# La app (php-fpm y las colas, como $MEGAISP_USER) escribe en vivo la
# configuración generada (/etc/asterisk/megaisp.d: troncales, grupos, colas) y lee
# las grabaciones; las dos carpetas son del grupo `asterisk`. En dev se hizo a
# mano (usermod + reload de php-fpm); aquí queda en el instalador.
#
# Los procesos que ya corren no ven un grupo nuevo: se recarga php-fpm (o Apache
# si PHP corre como módulo) y el cierre hace queue:restart para las colas.
# =============================================================================

provision_acceso_web() {
    log_step "Acceso de ${MEGAISP_USER} a la configuración generada y a las grabaciones"
    getent group asterisk >/dev/null 2>&1 || die "No existe el grupo 'asterisk' (lo crea el paso 20); no se puede seguir."
    id "$MEGAISP_USER" >/dev/null 2>&1 || die "No existe el usuario web '${MEGAISP_USER}' (fíjalo con MEGAISP_USER=...)."

    if id -nG "$MEGAISP_USER" | tr ' ' '\n' | grep -qx asterisk; then
        log_skip "${MEGAISP_USER} ya es del grupo asterisk"
        return 0
    fi

    usermod -aG asterisk "$MEGAISP_USER" || die "usermod -aG asterisk ${MEGAISP_USER} falló."
    log_ok "${MEGAISP_USER} agregado al grupo asterisk"

    local unit recargados=0
    for unit in $(systemctl list-units --type=service --state=active --no-legend 'php*-fpm.service' 2>/dev/null | awk '{print $1}'); do
        if systemctl reload "$unit" >/dev/null 2>&1; then
            log_ok "${unit} recargado (los procesos de PHP toman el grupo nuevo)"
            recargados=$((recargados + 1))
        else
            log_warn "No pude recargar ${unit}; recárgalo a mano: systemctl reload ${unit}"
        fi
    done
    if (( recargados == 0 )) && systemctl is-active --quiet apache2 2>/dev/null; then
        if systemctl reload apache2 >/dev/null 2>&1; then
            log_ok "apache2 recargado (PHP como módulo de Apache)"
        else
            log_warn "No pude recargar apache2; recárgalo a mano: systemctl reload apache2"
        fi
    fi
}
