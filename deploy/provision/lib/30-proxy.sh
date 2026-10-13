#!/usr/bin/env bash
# =============================================================================
# 30-proxy.sh — Elige el servidor web y cuelga /evolution/ de él (item #9991239)
# =============================================================================
# Se hace `source` desde install.sh DESPUÉS de 30-nginx.sh y 30-apache.sh.
# Define provision_web_proxy, que despacha a provision_nginx o provision_apache.
#
# Antes el paso 30 era solo nginx; en un server con Apache (producción) moría ahí
# y el paso 40 (arrancar Evolution en PM2 + arranque en boot) nunca corría.
#
# Overrides:
#   WEB_SERVER=nginx|apache   forzar el servidor web (si ambos están activos)
#   NGINX_VHOST / APACHE_VHOST  forzar el vhost (implica el servidor)
#   SKIP_WEB_PROXY=1          omitir el proxy por completo (plan B consciente):
#                             MegaISP habla con Evolution por 127.0.0.1:8080 y
#                             sigue funcionando; solo no queda publicada en
#                             <APP_URL>/evolution (consola de Evolution, URLs de media).
# =============================================================================

# _detect_web_server — imprime "nginx" o "apache".
_detect_web_server() {
    case "${WEB_SERVER:-}" in
        nginx|apache) printf '%s' "$WEB_SERVER"; return 0 ;;
        "") ;;
        *) die "WEB_SERVER='${WEB_SERVER}' no es válido (usa nginx o apache)." ;;
    esac
    if [[ -n "${NGINX_VHOST:-}" ]];  then printf nginx;  return 0; fi
    if [[ -n "${APACHE_VHOST:-}" ]]; then printf apache; return 0; fi

    local nginx_on=0 apache_on=0
    systemctl is-active --quiet nginx 2>/dev/null && nginx_on=1
    systemctl is-active --quiet apache2 2>/dev/null && apache_on=1
    if (( nginx_on && apache_on )); then
        die "nginx y apache2 están activos a la vez; indica cuál sirve MegaISP con WEB_SERVER=nginx o WEB_SERVER=apache."
    fi
    if (( nginx_on ));  then printf nginx;  return 0; fi
    if (( apache_on )); then printf apache; return 0; fi

    # Ninguno activo como servicio: decidir por lo instalado (solo si es inequívoco).
    if have nginx && ! have apache2ctl; then printf nginx;  return 0; fi
    if have apache2ctl && ! have nginx; then printf apache; return 0; fi
    die "No pude determinar el servidor web (nginx o apache2). Fija WEB_SERVER=nginx o WEB_SERVER=apache."
}

provision_web_proxy() {
    if [[ "${SKIP_WEB_PROXY:-}" == "1" ]]; then
        log_step "Proxy /evolution/ — OMITIDO por SKIP_WEB_PROXY=1"
        log_warn "No se publica Evolution en <APP_URL>/evolution. MegaISP sigue hablando con ella por http://127.0.0.1:${EVOLUTION_PORT}."
        return 0
    fi
    local ws; ws="$(_detect_web_server)"
    log_info "Servidor web de MegaISP: ${ws}"
    case "$ws" in
        nginx)  provision_nginx ;;
        apache) provision_apache ;;
    esac
}
