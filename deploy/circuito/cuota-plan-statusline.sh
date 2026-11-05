#!/usr/bin/env bash
# #1068 — Línea de estado de Claude Code que además deja la cuota del plan donde la Torre la lee.
#
# La cuota de la suscripción (ventana de 5 horas y semanal) no tiene una API pública: el único lugar
# soportado donde Claude Code la entrega es el JSON que le pasa a la línea de estado
# (`rate_limits.five_hour` / `rate_limits.seven_day`, con `used_percentage` y `resets_at`). Las
# sesiones interactivas corren este script en cada refresco; el circuito (`claude -p`) no, así que la
# medición vale "la última vez que alguien tuvo una terminal abierta" y la Torre muestra su edad.
#
# Nunca falla ni bloquea: sin jq, sin datos o sin permiso de escritura, solo imprime lo que pueda.
DEST="${CIRCUITO_CUOTA_PLAN_ARCHIVO:-/var/www/megaisp/storage/app/circuito/cuota-plan.json}"
IN="$(cat)"

RL="$(printf '%s' "$IN" | jq -c '.rate_limits // empty | with_entries(select(.key == "five_hour" or .key == "seven_day"))' 2>/dev/null)"
if [ -n "$RL" ] && [ "$RL" != "{}" ]; then
  TMP="$(mktemp "${DEST}.XXXXXX" 2>/dev/null)" \
    && printf '{"medido_at":%s,"rate_limits":%s}\n' "$(date +%s)" "$RL" > "$TMP" \
    && chmod 664 "$TMP" && mv -f "$TMP" "$DEST" || rm -f "$TMP" 2>/dev/null
fi

printf '%s' "$IN" | jq -r '[
    (.model.display_name // empty),
    (if .rate_limits.five_hour then "5 h \(.rate_limits.five_hour.used_percentage | floor)%" else empty end),
    (if .rate_limits.seven_day then "semana \(.rate_limits.seven_day.used_percentage | floor)%" else empty end)
  ] | join(" · ")' 2>/dev/null
exit 0
