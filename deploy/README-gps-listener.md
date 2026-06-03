# GPS Listener (systemd) — activación

> Sub-fase 2.3a · módulo Flotas. **NO instalar hasta que se vaya a apuntar un GPS real al servidor.**
> El puerto 5027 está cerrado en el firewall por ahora; mientras tanto el listener no es necesario.

El listener es el comando `php artisan flotas:gps-listen` (TCP nativo, sin ReactPHP). Recibe los
pings del GPS, los parsea con el driver de la marca (hoy Ruptela), los guarda con
`FleetPositionService::saveBatch` (que ya dispara detección de geocercas y notificaciones) y
responde el ACK que el dispositivo espera.

## Activar como servicio systemd

```bash
# 1. Copiar la unit
sudo cp /var/www/megaisp/deploy/megaisp-gps-listener.service /etc/systemd/system/

# 2. Recargar systemd
sudo systemctl daemon-reload

# 3. Habilitar al boot
sudo systemctl enable megaisp-gps-listener

# 4. Arrancar
sudo systemctl start megaisp-gps-listener

# 5. Verificar estado
sudo systemctl status megaisp-gps-listener

# 6. Seguir el log de la app
tail -f /var/www/megaisp/storage/logs/gps-listener.log
#   (systemd además vuelca stdout/stderr a /var/log/megaisp-gps-listener.log)
```

## Abrir el puerto en el firewall

```bash
# ufw
sudo ufw allow 5027/tcp
# o iptables
sudo iptables -A INPUT -p tcp --dport 5027 -j ACCEPT

# Validar que está escuchando
ss -tlnp | grep 5027
```

## Detener / reiniciar

```bash
sudo systemctl restart megaisp-gps-listener
sudo systemctl stop megaisp-gps-listener
```

## Notas
- El servicio corre como `www-data` (mismo usuario que la app) para que los logs y la BD funcionen.
- `Restart=always` → si el proceso muere, systemd lo relanza a los 5 s.
- Para apuntar el GPS físico, ver **README-gps-real.md**.
