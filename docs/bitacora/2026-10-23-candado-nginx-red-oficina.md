## 2026-10-23 08:35 — El candado de nginx de dev le pedía contraseña a parte de la oficina

**Qué se veía:** desde una computadora de la oficina (192.168.104.251), al abrir el sistema de dev
salía la ventana del navegador pidiendo usuario y contraseña, y después la pantalla se quedaba en
blanco.

**Causa:** el candado de nginx (Seguridad V1.64, activado hoy a las 07:23) exenta de contraseña a
"la red de la oficina", pero la dio de alta como `192.168.105.0/24`. La red real del servidor es
`192.168.104.0/22` (de 192.168.104.0 a 192.168.107.255; `ip route` → `192.168.104.0/22 dev ens18`,
puerta de enlace 192.168.104.1). Toda la oficina fuera de 192.168.105.x quedó tratada como
internet.

**Verificado:**
- Con el candado puesto (copia de nginx en un puerto local, con su propia contraseña de prueba), el
  login y el dashboard cargan completos en Chromium: el sistema en sí no está roto.
- No se pudo reproducir la pantalla en blanco con la contraseña correcta de nginx; lo más probable
  es que en la ventana se escribiera el usuario del sistema (no el de nginx), que nginx rechaza.
  Con la corrección, desde la oficina la ventana ya no aparece.

**Cambio (rama `fix/candado-nginx-red-oficina`):** `deploy/nginx/dev-acceso/http-red-interna.conf`
pasa de `192.168.105.0/24` a `192.168.104.0/22`; el runbook `deploy/README-seguridad-repo.md` §2
lo explica y trae los comandos.

**Pendiente (requiere root, no lo puede hacer la sesión):**
```bash
sudo cp /var/www/megaisp-nginxlan/deploy/nginx/dev-acceso/http-red-interna.conf /etc/nginx/conf.d/megaisp-dev-red-interna.conf
sudo nginx -t && sudo systemctl reload nginx
```

**Abierto:** 192.168.100.x (desde donde también se ha entrado por SSH) no está en la red del
servidor; si es otra oficina o la VPN, hay que decidir si se agrega.
