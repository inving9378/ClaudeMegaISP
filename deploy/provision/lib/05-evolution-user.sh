#!/usr/bin/env bash
# =============================================================================
# 05-evolution-user.sh — Usuario de sistema dedicado para Evolution
# =============================================================================
# Se hace `source` desde install.sh (que ya cargó common.sh). Define
# provision_evolution_user. No ejecuta nada al ser sourceado.
#
# Motivo (item Hoja de Ruta): en el pipeline de prod el deploy corre como
# www-data, que no tiene HOME propio adecuado para PM2. En vez de ejecutar
# Evolution como www-data o como ${SUDO_USER}, se usa un usuario de SISTEMA
# dedicado 'evolution' (con home propio, sin shell de login) → PM2 guarda su
# estado en /home/evolution/.pm2 y el proceso queda aislado.
#
# Este paso corre ANTES de 20-evolution.sh (que ya usa $EVOLUTION_USER como
# dueño del código). El default de EVOLUTION_USER es 'evolution' (ver más abajo
# y en 20-evolution.sh); se puede sobreescribir con EVOLUTION_USER=... si se
# quiere otro usuario.
#
# Guarda de idempotencia (decisión 2):
#   - Si el usuario ya existe (`id evolution`) → OMITIR la creación.
# =============================================================================

# Usuario dedicado por defecto. Si el llamador ya fijó EVOLUTION_USER, se respeta.
: "${EVOLUTION_USER:=evolution}"

# Home y shell del usuario de sistema.
: "${EVOLUTION_USER_HOME:=/home/${EVOLUTION_USER}}"
: "${EVOLUTION_USER_SHELL:=/usr/sbin/nologin}"

provision_evolution_user() {
    log_step "Usuario de sistema '${EVOLUTION_USER}' (ejecución de Evolution)"

    # Si se dejó un usuario personalizado que NO es el dedicado, no lo creamos:
    # se asume que ya existe y es responsabilidad de quien lo fijó.
    if [[ "$EVOLUTION_USER" != "evolution" ]]; then
        if id "$EVOLUTION_USER" &>/dev/null; then
            log_skip "Usuario personalizado '${EVOLUTION_USER}' ya existe"
            return 0
        fi
        die "EVOLUTION_USER='${EVOLUTION_USER}' no existe y no es el usuario dedicado 'evolution'. Créalo o usa el default."
    fi

    # Guarda if-existe: no recrear.
    if id "$EVOLUTION_USER" &>/dev/null; then
        log_skip "Usuario '${EVOLUTION_USER}' ya existe"
        return 0
    fi

    log_info "Creando usuario de sistema '${EVOLUTION_USER}' (home ${EVOLUTION_USER_HOME}, sin login)"
    require useradd "Se requiere 'useradd' para crear el usuario de sistema"
    # --system: cuenta de servicio; --create-home: HOME real para PM2;
    # --shell nologin: sin shell de login.
    run_root useradd --system --create-home \
        --home-dir "$EVOLUTION_USER_HOME" \
        --shell "$EVOLUTION_USER_SHELL" \
        --comment "Evolution API service user" \
        "$EVOLUTION_USER"

    id "$EVOLUTION_USER" &>/dev/null || die "No se pudo crear el usuario '${EVOLUTION_USER}'"
    log_ok "Usuario '${EVOLUTION_USER}' creado"
}
