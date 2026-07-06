#!/usr/bin/env bash
# =============================================================================
# 30-nginx.sh — Inyección idempotente de `location /evolution/` en el vhost
# =============================================================================
# Se hace `source` desde install.sh (que ya cargó common.sh). Define
# provision_nginx. No ejecuta nada al ser sourceado.
#
# Evolution NO se expone como server{} propio: se cuelga como `location` DENTRO
# del vhost existente de MegaISP (igual que en dev). El proxy va a
# http://127.0.0.1:8080/ (barra final → strippea el prefijo /evolution) con los
# headers Upgrade/Connection para el WebSocket del QR/estado.
#
# Guardas de idempotencia (decisión 2):
#   - Si el vhost ya contiene `location /evolution/` → OMITIR (no duplica).
#   - Backup del vhost antes de tocarlo; si `nginx -t` falla, se revierte.
# =============================================================================

# _detect_vhost — imprime la ruta del vhost de MegaISP.
# Prioridad: override NGINX_VHOST → vhost cuyo `root` apunta a $MEGAISP_ROOT/public
# → fallback a sites-available/megaisp.conf. Aborta si no encuentra ninguno.
_detect_vhost() {
    if [[ -n "${NGINX_VHOST:-}" ]]; then
        [[ -f "$NGINX_VHOST" ]] || die "NGINX_VHOST='${NGINX_VHOST}' no existe"
        printf '%s' "$NGINX_VHOST"
        return 0
    fi
    local f
    f="$(grep -rlF "${MEGAISP_ROOT}/public" \
            /etc/nginx/sites-available /etc/nginx/conf.d 2>/dev/null | head -n1 || true)"
    if [[ -z "$f" && -f /etc/nginx/sites-available/megaisp.conf ]]; then
        f="/etc/nginx/sites-available/megaisp.conf"
    fi
    [[ -n "$f" ]] || die "No pude detectar el vhost de MegaISP. Fija NGINX_VHOST=/ruta/al/vhost y reintenta."
    printf '%s' "$f"
}

provision_nginx() {
    log_step "nginx — location /evolution/ en el vhost de MegaISP"
    require nginx "Se requiere nginx instalado"

    local vhost; vhost="$(_detect_vhost)"
    log_info "Vhost detectado: ${vhost}"

    # Guarda: ya inyectado → nada que hacer.
    if grep -qE 'location[[:space:]]+/evolution/' "$vhost"; then
        log_skip "El vhost ya contiene 'location /evolution/'"
        return 0
    fi

    # Bloque a inyectar. Comillas simples en el heredoc → las variables nginx
    # ($http_upgrade, $host, ...) NO se expanden en bash; se escriben literales.
    local block
    block="$(cat <<'NGINX_BLOCK'

    location /evolution/ {
        proxy_pass         http://127.0.0.1:8080/;
        proxy_http_version 1.1;
        proxy_set_header   Upgrade          $http_upgrade;
        proxy_set_header   Connection       "upgrade";
        proxy_set_header   Host             $host;
        proxy_set_header   X-Real-IP        $remote_addr;
        proxy_set_header   X-Forwarded-For  $proxy_add_x_forwarded_for;
        proxy_read_timeout 300s;
        client_max_body_size 50M;
    }
NGINX_BLOCK
)"

    log_info "Inyectando el bloque location /evolution/"
    run_root cp -n "$vhost" "${vhost}.provision.bak" || true

    # Insertar el bloque justo después de la primera línea `server_name` (ancla
    # confiable DENTRO del server{}). ENVIRON evita problemas de escape.
    local tmp; tmp="$(mktemp)"
    _EVO_NGINX_BLOCK="$block" awk '
        BEGIN { b = ENVIRON["_EVO_NGINX_BLOCK"]; done = 0 }
        { print }
        (done == 0 && $0 ~ /server_name/) { print b; done = 1 }
    ' "$vhost" > "$tmp"
    run_root cp "$tmp" "$vhost"
    rm -f "$tmp"

    # Validar la config; si rompe, revertir para no dejar nginx caído.
    if run_root nginx -t >/dev/null 2>&1; then
        if run_root systemctl reload nginx >/dev/null 2>&1 || run_root nginx -s reload >/dev/null 2>&1; then
            log_ok "nginx recargado con el nuevo location /evolution/"
        else
            log_warn "Se inyectó el bloque pero no pude recargar nginx; recarga manualmente."
        fi
    else
        run_root cp "${vhost}.provision.bak" "$vhost"
        die "nginx -t falló tras la inyección; se revirtió el vhost. Revisar a mano."
    fi
}
