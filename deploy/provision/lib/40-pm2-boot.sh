#!/usr/bin/env bash
# =============================================================================
# 40-pm2-boot.sh — Arranque de Evolution en PM2 y persistencia en boot
# =============================================================================
# Se hace `source` desde install.sh (que ya cargó common.sh y 20-evolution.sh,
# de donde reutiliza el ayudante _evo y $EVOLUTION_USER). Define provision_pm2_boot.
#
# Deja Evolution corriendo bajo PM2 (fork_mode, nombre $EVOLUTION_PM2_NAME) y
# habilitado para arrancar en el boot vía systemd (pm2 startup + pm2 save).
#
# Guardas de idempotencia (decisión 2):
#   - Si el proceso PM2 ya existe → OMITIR el `pm2 start` (no duplica).
#   - Si el servicio systemd de PM2 ya está instalado → OMITIR `pm2 startup`.
#   - `pm2 save` es idempotente → se corre siempre para persistir la lista.
# =============================================================================

provision_pm2_boot() {
    log_step "PM2 — arranque de Evolution y persistencia en boot"
    require pm2 "PM2 no está disponible (¿corrió el paso 10-node-pm2?)"

    # --- 1) Arrancar el proceso si no existe --------------------------------
    if _evo pm2 describe "$EVOLUTION_PM2_NAME" >/dev/null 2>&1; then
        log_skip "El proceso PM2 '${EVOLUTION_PM2_NAME}' ya existe"
    else
        [[ -f "$EVOLUTION_DIR/dist/main.js" ]] || die "No existe ${EVOLUTION_DIR}/dist/main.js; ¿falló el build?"
        log_info "Arrancando '${EVOLUTION_PM2_NAME}' en PM2 (fork_mode)"
        # cwd = $EVOLUTION_DIR para que Evolution lea su .env y su carpeta instances/
        _evo bash -c "cd '${EVOLUTION_DIR}' && pm2 start dist/main.js --name '${EVOLUTION_PM2_NAME}'"
        log_ok "Proceso '${EVOLUTION_PM2_NAME}' arrancado"
    fi

    # --- 2) Habilitar arranque en boot (systemd) ----------------------------
    local home
    home="$(getent passwd "$EVOLUTION_USER" | cut -d: -f6)"
    [[ -n "$home" ]] || die "No pude resolver el HOME del usuario '${EVOLUTION_USER}'"

    if systemctl list-unit-files 2>/dev/null | grep -q "pm2-${EVOLUTION_USER}"; then
        log_skip "El servicio systemd 'pm2-${EVOLUTION_USER}' ya está instalado"
    else
        log_info "Instalando el servicio systemd de PM2 (arranque en boot)"
        # pm2 startup genera e instala la unit systemd para el usuario indicado.
        run_root env PATH="$PATH" pm2 startup systemd -u "$EVOLUTION_USER" --hp "$home"
        log_ok "Servicio systemd de PM2 instalado"
    fi

    # --- 3) Persistir la lista de procesos (idempotente) --------------------
    log_info "pm2 save (persistiendo la lista de procesos)"
    _evo pm2 save
    log_ok "Lista de procesos PM2 persistida"
}
