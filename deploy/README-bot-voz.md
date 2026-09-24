# Daemon de voz de María (systemd) — activación

> MegaVoz Fase 6. **El engine YA está conectado al dialplan real** (`grupo-1`, cola real de
> Atención a Clientes) desde el 2026-09-24, protegido por un candado de pre-vuelo (ver abajo).
> Con `ia_bot_config.piloto_porcentaje = 0` (default seguro) ningún cliente real habla con
> María todavía — el interruptor de piloto lo decide el daemon EN VIVO, no este servicio.
> Esta unit es para que el daemon quede corriendo **de forma persistente** en vez de a mano.

El daemon es el comando `php artisan voip:bot-voz-escuchar` — un servidor TCP nativo
(`stream_socket_server`, sin ReactPHP, mismo molde que `flotas:gps-listen`) que atiende el
protocolo AudioSocket de Asterisk, con `pcntl_fork()` por llamada entrante (varias
conversaciones simultáneas). Escucha SOLO en `127.0.0.1:9099` — no se expone a la red, Asterisk
le habla local.

## ⚠️ Candado de pre-vuelo — por qué este servicio SÍ importa aunque el piloto esté en 0%

El dialplan generado (`DialplanGeneratorService::buildColaExten()`) antepone un
`TrySystem(nc -z -w1 127.0.0.1 9099)` antes de intentar `AudioSocket()`: si este daemon no
responde, el candado lo detecta y la llamada sigue derecho a la cola real — **nunca se cuelga**.
Esto se agregó tras encontrar en vivo (2026-09-24) que `AudioSocket()` contra un daemon caído
cuelga el canal por diseño de Asterisk (`app_audiosocket.c`: conexión fallida = `return -1` =
convención clásica de "colgar", no "seguir a la siguiente prioridad") — verificado con
`channel originate` real + ausencia de `ENTERQUEUE` en `queue_log`, y confirmado arreglado con
el mismo método tras el fix.

**Con este candado, es seguro tener `ia_bot_config.enabled=true` en el dialplan real SIN este
servicio corriendo** — simplemente nunca se intenta `AudioSocket()` y el flujo es idéntico al
de antes de Fase 6. Instalar este servicio es lo que permite que, cuando se suba
`piloto_porcentaje` por encima de 0, María empiece a atender de verdad sin que nadie tenga que
acordarse de arrancar el comando a mano.

## Activar como servicio systemd

```bash
# 1. Copiar la unit
sudo cp /var/www/megaisp/deploy/megaisp-bot-voz.service /etc/systemd/system/

# 2. Recargar systemd
sudo systemctl daemon-reload

# 3. Habilitar al boot
sudo systemctl enable megaisp-bot-voz

# 4. Arrancar
sudo systemctl start megaisp-bot-voz

# 5. Verificar estado
sudo systemctl status megaisp-bot-voz

# 6. Seguir el log de la app (transcripciones, costos, decisiones de piloto/horario)
tail -f /var/www/megaisp/storage/logs/megavoz-bot-voz.log
#   (systemd además vuelca stdout/stderr a /var/log/megaisp-bot-voz.log)
```

## Verificar que quedó escuchando

```bash
ss -tlnp | grep 9099
# Debe mostrar 127.0.0.1:9099 — NO 0.0.0.0 (no se expone a la red, Asterisk le habla local)
```

## Detener / reiniciar

```bash
sudo systemctl restart megaisp-bot-voz
sudo systemctl stop megaisp-bot-voz
```

## Subir el piloto (cuando Irving/David decidan)

`/voip/ia-bot` → guardar config con `piloto_porcentaje` > 0 y el horario de oficina deseado.
Cada guardado regenera el dialplan solo (`IaBotController::saveConfig()` ya llama
`DialplanGeneratorService::regenerar()`), pero el candado de pre-vuelo no cambia — sigue
protegiendo aunque el daemon se caiga después de subir el piloto.

## Notas

- El servicio corre como `www-data` (mismo usuario que la app) para que los logs y la BD
  funcionen.
- `Restart=always` → si el proceso muere, systemd lo relanza a los 5 s.
- Requiere la extensión `pcntl` de PHP CLI (ya presente en este servidor, verificada en vivo).
- No requiere abrir ningún puerto en el firewall — es tráfico local (`127.0.0.1`) entre
  Asterisk y este daemon.
