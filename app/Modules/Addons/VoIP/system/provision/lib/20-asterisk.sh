#!/usr/bin/env bash
# =============================================================================
# 20-asterisk.sh — Asterisk: descarga, compilación, base, configuración y arranque
# =============================================================================
# Se hace `source` desde install.sh (que ya cargó common.sh). Define
# provision_asterisk. No ejecuta nada al ser sourceado.
#
# No reimplementa nada: corre el provisionador del módulo,
#   php artisan voip:provisionar
# que ya hace los 15 pasos (contrato, descarga, sha256, dependencias de apt,
# compilación, instalación, Alembic, base realtime, configuración, credenciales
# AMI/ARI, plan de numeración, arranque y validación) y guarda el estado de
# cada uno en voip_provision_estado. Reintentar retoma desde el paso que falló
# sin recompilar lo ya hecho.
#
# Se corre como root (lo que necesita: apt, make install, systemd, /etc/asterisk),
# así que este paso cuida lo que root deja en el árbol de MegaISP:
#   - LOG_CHANNEL=stderr / CACHE_DRIVER=array: la corrida no crea logs ni caché
#     de Laravel en disco (un laravel-<fecha>.log creado por root dejaría a la app
#     sin poder escribir su log el resto del día).
#   - Al terminar, lo que root haya creado en storage/ y bootstrap/cache se le
#     devuelve al usuario web.
#   - La carpeta de configuración generada queda asterisk:asterisk con escritura
#     de grupo (la app la reescribe al guardar troncales y grupos).
#
# Rollback: si la provisión falla y Asterisk NO estaba corriendo antes de esta
# corrida, se detiene y deshabilita: no se deja una central a medias escuchando
# en el 5060. Todo lo demás queda como está, con el motivo a la vista, para
# reintentar.
# =============================================================================

# _devolver_al_usuario_web MARCA — chown de lo que root creó después de MARCA.
_devolver_al_usuario_web() {
    local marca="$1" grupo n
    grupo="$(megaisp_group)"
    n="$(find "$MEGAISP_ROOT/storage" "$MEGAISP_ROOT/bootstrap/cache" -user 0 -newer "$marca" 2>/dev/null | wc -l)"
    if (( n > 0 )); then
        find "$MEGAISP_ROOT/storage" "$MEGAISP_ROOT/bootstrap/cache" -user 0 -newer "$marca" -print0 2>/dev/null \
            | xargs -0 -r chown "${MEGAISP_USER}:${grupo}"
        log_info "${n} archivo(s) creados por root en storage/ y bootstrap/cache devueltos a ${MEGAISP_USER}:${grupo}"
    fi
}

# _permisos_generados — misma regla que provisionar-asterisk.sh (preparar_generados_dir):
# dueño asterisk, escritura de grupo (www-data es del grupo asterisk, paso 30).
_permisos_generados() {
    local gen; gen="$(voip_generados_dir)"
    [[ -d "$gen" ]] || return 0
    getent passwd asterisk >/dev/null 2>&1 || return 0
    chown -R asterisk:asterisk "$gen"
    chmod 770 "$gen"
    chmod -R g+w "$gen"
}

provision_asterisk() {
    log_step "Asterisk — php artisan voip:provisionar"
    require php "Se requiere php (CLI) para correr el provisionador de MegaISP"

    local -a args=(--no-ansi)
    local activo_antes=0 rc=0 marca
    if [[ -x "$ASTERISK_BIN" ]]; then
        log_info "Asterisk ya instalado ($("$ASTERISK_BIN" -V 2>/dev/null || echo 'versión desconocida')): el provisionador revalida y completa lo que falte"
    else
        # Servidor nuevo o después de purgar: no se retoma una corrida vieja
        # cuyos pasos de PHP dirían «completado» sobre un servidor limpio.
        args+=(--nueva)
        log_info "Asterisk no está instalado: provisión desde cero (--nueva). La compilación tarda de 30 a 60 minutos; cada paso se ve abajo."
    fi
    systemctl is-active --quiet asterisk 2>/dev/null && activo_antes=1

    # Las ASTERISK_RT_DB_* recién escritas (paso 10) se leen del .env: sin config
    # cacheada de por medio.
    run_as "$MEGAISP_USER" bash -c "cd '${MEGAISP_ROOT}' && php artisan config:clear" >/dev/null \
        || die "php artisan config:clear falló como ${MEGAISP_USER}; no se siguió."

    marca="$(mktemp)"
    ( cd "$MEGAISP_ROOT" && env LOG_CHANNEL=stderr CACHE_DRIVER=array php artisan voip:provisionar "${args[@]}" ) || rc=$?

    _devolver_al_usuario_web "$marca"
    rm -f "$marca"
    _permisos_generados

    if (( rc != 0 )); then
        if (( activo_antes == 0 )) && systemctl is-active --quiet asterisk 2>/dev/null; then
            systemctl disable --now asterisk >/dev/null 2>&1 || true
            log_warn "Rollback: Asterisk no estaba corriendo antes de esta corrida → se detuvo y deshabilitó (no queda una central a medias escuchando)."
        fi
        die "La provisión de Asterisk falló (código ${rc}); el paso y el motivo están arriba. Al reintentar se retoma desde ese paso sin recompilar lo ya hecho."
    fi
    log_ok "Asterisk provisionado y validado ($("$ASTERISK_BIN" -V 2>/dev/null || echo '?'))"
}
