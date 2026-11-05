## 2026-10-29 — «Mi credencial» de la app funciona desde fuera de la oficina

**Qué pasaba.** Desde fuera de la oficina, la app se conecta bien (`/talento/api` no pide la
contraseña de nginx), pero la tarjeta «Mi credencial» salía sin fondo, sin logo y sin foto, y
«Descargar credencial» abría el navegador pidiendo usuario y contraseña. Esas imágenes y el PDF
viven en direcciones que el candado de dev (V1.64) protege desde fuera.

**Qué se cambió (pedido de David).**
- La foto de la credencial en la app ya no es la dirección de `/storage` o `/perfiles`: el servidor
  manda un enlace firmado que vence en 1 hora (`/talento/colaborador/{id}/foto-firmada`, middleware
  `signed`, mismo orden de búsqueda que `User::url_photography`). Así no se abren las fotos del
  personal a internet.
- `deploy/nginx/dev-acceso/server-excepciones.conf`: sin contraseña de nginx quedan solo
  `/talento/colaborador/{id}/credencial-firmada` y `/foto-firmada` (los protege la firma),
  `/credencial/` (plantillas del gafete) y los dos respaldos fijos `/images/gafete-front.png` y
  `/images/logo_meganet.jpg`. Nada más.
- No se liberaron el logo de la pantalla de entrada (`/storage/logo_meganet/…`) ni las imágenes de
  los cursos (`/storage/…`); desde fuera siguen sin cargar.

**Probado (vista previa de la rama).** Enlace firmado → 200 `image/jpeg`; sin firma, con la firma
de otro enlace o con otro id → 403. Los tres casos de foto (disco `public`, `public/perfiles`, sin
archivo → avatar) resuelven al archivo correcto; se probó con archivos temporales y una transacción
deshecha, todo borrado. Token de prueba borrado.

**Aplicar en el servidor (requiere root; lo corre quien tenga sudo):**

```bash
sudo cp /etc/nginx/snippets/megaisp-dev-excepciones.conf /root/megaisp-dev-excepciones.conf.antes-2026-10-29
sudo cp /var/www/megaisp/deploy/nginx/dev-acceso/server-excepciones.conf /etc/nginx/snippets/megaisp-dev-excepciones.conf
sudo nginx -t && sudo systemctl reload nginx
```

**Deshacer:** restaurar la copia de `/root` y `sudo nginx -t && sudo systemctl reload nginx`.

## 2026-10-29 13:40 — La foto y el PDF pasan por /talento/api (sin esperar a nginx)

**Qué pasaba.** Los comandos de arriba no se habían corrido, así que desde fuera la tarjeta salía
como un recuadro azul con el nombre, sin fondo, sin logo y sin foto, y el PDF pedía contraseña.

**Qué se cambió (pedido de David).** Las dos rutas firmadas se movieron a
`/talento/api/colaborador/{id}/foto-firmada` y `/talento/api/colaborador/{id}/credencial-firmada`.
nginx ya deja pasar `/talento/api` sin su contraseña, así que funcionan desde fuera sin tocar el
servidor; la protección sigue siendo la firma. No hace falta APK nueva: la app recibe esos enlaces
del servidor. La línea de nginx para las rutas firmadas sale de `server-excepciones.conf`; quedan
solo `/credencial/` y las dos imágenes fijas, que la app pide por dirección fija.

**Probado (vista previa de la rama, cuenta prueba_mostrador).** Foto → 200 `image/jpeg`; PDF → 200
`application/pdf`; firma alterada, otro id o sin firma → 403. Token de prueba borrado.

**Sigue pendiente de root:** los comandos de arriba, solo para el fondo y el logo del gafete.
