#!/usr/bin/env bash
# CANDADO DE LA BASE DE PRUEBAS, A PRUEBA DE RAMAS — incidente del 2026-08-25 18:14.
#
# ── QUÉ PROTEGE ─────────────────────────────────────────────────────────────────────────────────
#
# `phpunit.xml` declaraba `DB_DATABASE=megaisp` (la base de la app) y `Tests\TestCase::setUp()`
# corre `migrate:fresh --seed`. La terminal del item #171 corrió la suite y dejó dev en 0 tablas.
# Fue la SEGUNDA vez: el mismo mecanismo la vació el 22-ago.
#
# El candado de fondo es de PHP y vive en el repo (`tests/GuardBaseDePruebas.php`, commit
# 04ec4395). Pero el repo SE BIFURCA: cada worktree puede estar en una rama creada ANTES de ese
# commit, y ahí el candado no existe. Justo el caso de wt-1 la noche del incidente.
#
# Este archivo es la capa que NO se bifurca. El cron invoca los wrappers por RUTA ABSOLUTA
# (/var/www/megaisp/deploy/circuito/...), o sea siempre la copia de `main`, opere sobre el
# worktree que opere. Misma razón por la que el centinela del freno usa ruta absoluta (#170).
#
# ── LA REGLA ────────────────────────────────────────────────────────────────────────────────────
#
# Un árbol es apto para correr pruebas sólo si su `phpunit.xml` declara una base terminada en
# `_test` Y trae el candado de PHP. Cualquier otra cosa —incluido no poder averiguarlo— aborta.
# Fail-closed: no saber contra qué base corre `migrate:fresh` es el caso peligroso, no uno benigno.
#
# ── POR QUÉ ESCRIBE EN UN ARCHIVO ───────────────────────────────────────────────────────────────
#
# Todas las líneas del crontab terminan en `>/dev/null 2>&1`. Un candado que aborta en silencio
# es indistinguible de un circuito tranquilo — la misma familia de fallo que el revisor que escala
# todo y se ve *prudente*. El motivo del aborto va SIEMPRE a este log.

GUARD_LOG="${GUARD_LOG:-/var/www/megaisp/storage/app/circuito/guard-bd-pruebas.log}"

# guard_bd_pruebas <directorio_del_arbol> [etiqueta]
# 0 = apto para pruebas · 1 = NO apto (el motivo queda en $GUARD_LOG y en stderr).
guard_bd_pruebas() {
  local dir="${1:-}" etiqueta="${2:-$(basename "${1:-?}")}" xml db motivo=""

  xml="$dir/phpunit.xml"

  if [ -z "$dir" ] || [ ! -d "$dir" ]; then
    motivo="el directorio '$dir' no existe"
  elif [ ! -r "$xml" ]; then
    motivo="no hay phpunit.xml legible en $dir (no puedo saber contra qué base correrían las pruebas)"
  else
    db="$(grep -oE 'name="DB_DATABASE"[^>]*value="[^"]*"' "$xml" | head -1 | sed -E 's/.*value="([^"]*)".*/\1/')"
    if [ -z "$db" ]; then
      motivo="phpunit.xml no declara DB_DATABASE: la suite caería al .env de la app (megaisp)"
    elif [ "${db%_test}" = "$db" ]; then
      motivo="phpunit.xml declara DB_DATABASE=$db, que NO es una base de pruebas"
    elif [ ! -f "$dir/tests/GuardBaseDePruebas.php" ]; then
      motivo="falta tests/GuardBaseDePruebas.php: este árbol es anterior al candado (commit 04ec4395)"
    fi
  fi

  [ -z "$motivo" ] && return 0

  local msg="[$(date +%FT%T)] ABORTADO ($etiqueta): $motivo"
  printf '%s\n' "$msg" >> "$GUARD_LOG" 2>/dev/null
  printf '%s\n' "$msg" >&2
  printf '  Correr la suite así vacía la base de dev (pasó el 2026-08-22 y el 2026-08-25 18:14).\n' >&2
  printf '  Se arregla mergeando main en esa rama: git -C %s merge main\n' "$dir" >&2
  return 1
}
