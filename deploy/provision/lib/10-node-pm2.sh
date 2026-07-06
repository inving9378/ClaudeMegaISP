#!/usr/bin/env bash
# =============================================================================
# 10-node-pm2.sh — Instalación idempotente de Node 20 y PM2
# =============================================================================
# Se hace `source` desde install.sh (que ya cargó common.sh). Define una única
# función: provision_node_pm2. No ejecuta nada al ser sourceado.
#
# Guardas (decisión 2 — "detecta y omite"):
#   - Node: si ya está en la mayor 20.x → OMITIR.
#   - PM2:  si el binario ya existe     → OMITIR.
# =============================================================================

# _node_major — imprime la versión mayor de Node instalada (vacío si no hay Node).
_node_major() {
    have node || return 0
    node -v 2>/dev/null | sed -E 's/^v([0-9]+).*/\1/'
}

provision_node_pm2() {
    log_step "Node ${NODE_MAJOR}.x + PM2"

    # --- Node 20 -------------------------------------------------------------
    local major; major="$(_node_major)"
    if [[ "$major" == "$NODE_MAJOR" ]]; then
        log_skip "Node ya está en la mayor ${NODE_MAJOR}.x ($(node -v))"
    else
        if [[ -n "$major" ]]; then
            log_info "Node presente pero en v${major}.x; instalando Node ${NODE_MAJOR}.x (NodeSource)"
        else
            log_info "Node ausente; instalando Node ${NODE_MAJOR}.x (NodeSource)"
        fi
        require curl "Se requiere 'curl' para el instalador de NodeSource"

        # Repo oficial NodeSource para la mayor deseada. El setup script es
        # idempotente (reconfigura el repo apt); luego instalamos el paquete.
        curl -fsSL "https://deb.nodesource.com/setup_${NODE_MAJOR}.x" | run_root bash -
        run_root apt-get install -y nodejs

        # Verificación dura: si tras instalar no quedó la mayor esperada, abortamos.
        major="$(_node_major)"
        [[ "$major" == "$NODE_MAJOR" ]] || die "La instalación de Node no dejó la mayor ${NODE_MAJOR}.x (quedó: '${major:-ninguna}')"
        log_ok "Node instalado: $(node -v)"
    fi

    # --- PM2 -----------------------------------------------------------------
    if have pm2; then
        log_skip "PM2 ya está instalado ($(pm2 -v 2>/dev/null))"
    else
        log_info "Instalando PM2 global (npm -g)"
        require npm "npm debería venir con Node; no se encontró"
        run_root npm install -g pm2
        have pm2 || die "PM2 no quedó disponible en PATH tras la instalación"
        log_ok "PM2 instalado: $(pm2 -v 2>/dev/null)"
    fi
}
