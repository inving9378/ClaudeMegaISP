#!/usr/bin/env bash
# WRAPPER DE LA VIGILIA DE THOMAS — deliberadamente SEPARADO de cron-wrap.sh.
#
# POR QUÉ NO USA cron-wrap.sh: el 24-ago se comentaron de golpe las nueve líneas del circuito
# marcándolas `# PAUSADO-20260824-incidente:`. Ese barrido busca `cron-wrap.sh`. Si la vigilia
# colgara del mismo wrapper, pausar el circuito dejaría ciego al que tiene que mirar mientras
# está pausado — y eso es justo lo que pasó: Thomas lleva desde entonces sin latir.
#
# Pausar el circuito NO debe apagar al vigilante. Son dos cosas distintas y por eso tienen dos
# caminos distintos, igual que la sonda de compuertas tiene el suyo.
#
# Uso en crontab (como meganet):
#   * * * * * /var/www/megaisp/deploy/circuito/vigilia-wrap.sh >/dev/null 2>&1
set -uo pipefail

cd /var/www/megaisp || exit 1

# Los errores NO van a /dev/null: un vigilante que falla en silencio es el problema que este
# archivo existe para evitar. El comando es callado cuando todo va bien, así que este log sólo
# crece cuando hay algo que contar.
ERRLOG="/home/meganet/circuito/logs/vigilia-errores.log"

if ! out="$(php artisan circuito:thomas-vigilar 2>&1)"; then
  printf '[%s] %s\n' "$(date +%FT%T)" "$out" >> "$ERRLOG" 2>/dev/null
  exit 1
fi
exit 0
