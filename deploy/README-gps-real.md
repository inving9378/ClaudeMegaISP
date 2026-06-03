# Apuntar un GPS Ruptela real al servidor — guía operativa

> Sub-fase 2.3a · módulo Flotas. Para cuando Irving decida conectar el GPS Ruptela físico.
> Todo el backend ya está listo y validado con simulador; solo falta este paso operativo.

## 1. Lado servidor

```bash
# a) Definir la IP pública del servidor en .env (la usa la UI para mostrar instrucciones)
#    Edítalo a mano:
GPS_LISTENER_PUBLIC_IP=TU.IP.PUBLICA.AQUI

# b) Activar el servicio systemd (ver README-gps-listener.md)
sudo cp /var/www/megaisp/deploy/megaisp-gps-listener.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now megaisp-gps-listener

# c) Abrir el puerto 5027 TCP en el firewall
sudo ufw allow 5027/tcp

# d) Confirmar que escucha
ss -tlnp | grep 5027
```

## 2. Dar de alta el GPS en la UI (antes de conectarlo)

En `/flotas/{vehiculo}?tab=gps` (vehículo sin GPS):
1. Click **"Activar tracking GPS"**.
2. Marca = **Ruptela**, modelo, **IMEI** (15 dígitos, debe coincidir con el del dispositivo), SIM y operadora.
3. Guardar → se crea el `fleet_device` con `status=pending_first_connection` y se muestra la
   instrucción de apuntar el GPS a `GPS_LISTENER_PUBLIC_IP:5027`.

> Importante: el IMEI capturado en la UI debe ser EXACTAMENTE el del dispositivo. El listener
> vincula los pings al vehículo por IMEI. Si llega un IMEI no dado de alta, se registra como
> `unregistered` y NO guarda posiciones hasta vincularlo a un vehículo.

## 3. Configurar el dispositivo Ruptela

1. Descargar **Ruptela Configurator**: https://doc.ruptela.com/dl/configurator
2. Conectar el GPS por USB.
3. Server settings:
   - **Server IP:** la IP pública del servidor (la de `GPS_LISTENER_PUBLIC_IP`)
   - **Server Port:** `5027`
   - **Protocol:** `TCP`
   - **Send interval:** 60 s (recomendado, ajustable)
4. Guardar la configuración en el GPS.

## 4. Validar la primera conexión

```bash
tail -f /var/www/megaisp/storage/logs/gps-listener.log
```
Deberías ver algo como:
```
[2026-XX-XX HH:MM:SS] Conexión X.X.*.X: marca=ruptela
[2026-XX-XX HH:MM:SS] X.X.*.X: imei=3569*********09 records=N
[2026-XX-XX HH:MM:SS] X.X.*.X: device #N vehículo #M → N posiciones guardadas.
```
(IMEI e IP se enmascaran a propósito en el log.) El `status` del device pasa a `active` en la
primera conexión con posiciones válidas. La pestaña Tracking GPS empezará a mostrar el recorrido.

## 5. Si NO se conecta

- ¿La SIM tiene datos activos?
- ¿El firewall del servidor permite TCP 5027? (`ss -tlnp | grep 5027`)
- ¿La IP pública del .env / configurador es correcta?
- ¿El IMEI de la UI coincide con el del dispositivo? (si no, aparecerá como `unregistered` en el log)

## ⚠️ Supuestos del protocolo a verificar con el GPS real

El `RuptelaDriver` se implementó como **mínimo viable** self-consistent con el simulador
(la doc oficial requiere registro). Al llegar el primer ping real, **verificar** y ajustar si hace falta:
- **CRC16**: asumido CCITT (poly 0x1021). El parseo es tolerante (no descarta por CRC); endurecer una vez confirmado.
- **IMEI**: asumido 8 bytes big-endian uint64 (algunas fuentes citan BCD).
- **Layout de record y sección IO**, **command extendido (0x44/0x68)** y **divisores de altitud/ángulo**.
Buscar `⚠️VERIFY` en `app/Modules/Addons/Flotas/Services/Gps/Drivers/RuptelaDriver.php`.
