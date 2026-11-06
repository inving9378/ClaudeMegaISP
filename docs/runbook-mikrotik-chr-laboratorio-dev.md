# Runbook — MikroTik CHR de laboratorio en Proxmox para DEV (item #1160)

Objetivo: tener un router de pruebas para «Probar lectura» y el colector de monitoreo de
dedicados (#988) sin tocar routers reales. **Se ejecuta a mano en el Proxmox** (esta máquina
de dev no tiene `qm`/`pvesh`); el repo solo documenta el procedimiento.

## 1. Crear la VM (en el host Proxmox)
1. Descargar `chr-<versión>.img.zip` (RouterOS Cloud Hosted Router, estable) de mikrotik.com y descomprimir.
2. `qm create <VMID> --name chr-lab --memory 256 --cores 1 --net0 virtio,bridge=<bridge-lab>`
3. `qm importdisk <VMID> chr-<versión>.img <storage>` y asociarlo como `scsi0`/`virtio0`; orden de arranque en ese disco.
4. Red: bridge **aislado de producción** (VLAN de prueba, p. ej. etiqueta dedicada de laboratorio). Una IP fija alcanzable desde dev (.11).
5. Iniciar, entrar por consola (`admin`, sin contraseña) y fijar contraseña de admin nueva.

## 2. Usuario de solo lectura
```
/user group add name=lectura policy=read,api,test,!local,!telnet,!ssh,!ftp,!reboot,!write,!policy,!winbox,!password,!web,!sniff,!sensitive,!romon
/user add name=megaisp_ro group=lectura password=<SECRET> address=<IP-de-dev>/32
/ip service set api address=<IP-de-dev>/32
/ip service disable telnet,ftp,www,ssh
```
(ver también `docs/runbook-mikrotik-restriccion-api-address.md`).

## 3. Credenciales en el `.env` de dev (NUNCA en archivos versionados)
```
MIKROTIK_DEV_HOST=<IP-del-CHR>
MIKROTIK_DEV_PORT=8728
MIKROTIK_DEV_USER=megaisp_ro
MIKROTIK_DEV_PASSWORD=<SECRET>
```
Tras editar `.env`: verificar `ls -la .env` (grupo `www-data`) y correr
`php artisan config:clear && php artisan route:clear && php artisan queue:restart`.

## 4. Verificación
- Desde dev: `nc -vz <IP-del-CHR> 8728` abre; con otro host rechaza.
- En la ficha del cliente de prueba: «Probar lectura» devuelve datos del CHR.
- Escritura con `megaisp_ro` debe fallar (confirma solo-lectura).

Nota: ningún código lee hoy `MIKROTIK_DEV_*`; son las variables reservadas para ese laboratorio.
