# Seguridad del repositorio y de dev (V1.64, 2026-10-22)

El repositorio de GitHub es público. Esta guía cubre tres piezas: el candado que impide subir
datos o credenciales, la contraseña de nginx para dev y el orden para volver privado el repo.

## 1. Candado pre-commit (ya activo en dev)

`.githooks/pre-commit` bloquea un commit que agregue volcados (`*.sql`), exportaciones (`*.csv`,
`*.xlsx`), respaldos (`*.bak`, `*.dump`), llaves privadas, `.env` o líneas con forma de
credencial. Las excepciones legítimas van en `.githooks/permitidos.txt`.

El repo apunta sus hooks a `/var/www/megaisp/.git/hooks` (compartido por todos los worktrees).
Ahí hay un envoltorio que corre la copia de main del candado:

```bash
cat > /var/www/megaisp/.git/hooks/pre-commit <<'SH'
#!/usr/bin/env bash
# Envoltorio (no versionado): corre el candado versionado de la copia de main.
for h in /var/www/megaisp/.githooks/pre-commit "$(git rev-parse --show-toplevel)/.githooks/pre-commit"; do
    [ -x "$h" ] && exec "$h" "$@"
done
exit 0
SH
chmod +x /var/www/megaisp/.git/hooks/pre-commit
```

Lo que salió del repo en V1.64 está en `storage/app/cuarentena/` del servidor de dev (no se
versiona, permisos 700). En prod esos archivos se borran solos al aplicar la versión.

## 2. Contraseña de nginx para dev (requiere root)

Desde la red de la oficina (192.168.105.0/24), el propio servidor y sus contenedores se entra
igual que hoy. Desde fuera, nginx pide usuario y contraseña. Quedan sin contraseña de nginx las
rutas que usan máquinas de fuera y ya se autentican solas: `/mcp` y `/api/roadmap-externo`
(Torre/Cowork), webhooks (WhatsApp, Evolution, SPEI, OpenPay), `/talento/api` (app de campo),
`/downloads/` (APK) y el reto de Let's Encrypt.

```bash
# 1) Usuario y contraseña (se pide la contraseña; no se escribe en el comando)
sudo htpasswd -B -c /etc/nginx/megaisp-dev.htpasswd equipo      # paquete apache2-utils
sudo chown root:www-data /etc/nginx/megaisp-dev.htpasswd && sudo chmod 640 /etc/nginx/megaisp-dev.htpasswd

# 2) Archivos de configuración
sudo cp deploy/nginx/dev-acceso/http-red-interna.conf   /etc/nginx/conf.d/megaisp-dev-red-interna.conf
sudo cp deploy/nginx/dev-acceso/server-auth.conf        /etc/nginx/snippets/megaisp-dev-auth.conf
sudo cp deploy/nginx/dev-acceso/server-excepciones.conf /etc/nginx/snippets/megaisp-dev-excepciones.conf

# 3) En CADA server{} de dev (megaisp-dev-tls.conf y megaisp.conf, el de default_server),
#    agregar ANTES de «location /»:
#        include /etc/nginx/snippets/megaisp-dev-auth.conf;
#        include /etc/nginx/snippets/megaisp-dev-excepciones.conf;

# 4) Probar y recargar
sudo cp /etc/nginx/sites-available/megaisp-dev-tls.conf /root/megaisp-dev-tls.conf.antes-v164
sudo cp /etc/nginx/sites-available/megaisp.conf         /root/megaisp.conf.antes-v164
sudo nginx -t && sudo systemctl reload nginx
```

**Verificar** desde fuera de la oficina (datos móviles):
- `https://dev.meganett.com.mx/` pide usuario y contraseña;
- `https://dev.meganett.com.mx/csv/…` también;
- `https://dev.meganett.com.mx/talento/api/health` responde sin pedirla.

Desde la oficina, todo igual que antes.

**Deshacer:** quitar los dos `include`, restaurar las copias de `/root` y `sudo nginx -t &&
sudo systemctl reload nginx`.

## 3. Volver privado el repositorio, en este orden

1. **Prod aplica la V1.64** (o una posterior) mientras el repo sigue público.
2. **Se crea un token** fine-grained de GitHub: solo este repositorio, permiso *Contents: Read*.
3. **Se carga el token en prod:** Integraciones → «GitHub (actualizaciones)». Validar.
4. **Se hace privado el repo en GitHub.**
5. **En prod, «Buscar actualizaciones» debe responder** «Estás al día». Si dice que el
   repositorio es privado y falta el token, el paso 3 no quedó.

Al revés, prod deja de ver las versiones. Lo que ya se publicó sigue en las copias que existan;
por eso las credenciales que estuvieron en el código se cambian de todos modos.
