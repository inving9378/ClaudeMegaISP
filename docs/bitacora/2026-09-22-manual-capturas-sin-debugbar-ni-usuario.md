# Manual de usuario: capturas re-tomadas sin la barra de depuración ni el nombre de usuario

## 2026-09-22 14:30 — Ajuste de Irving sobre las capturas ya entregadas

Las 29 capturas del capítulo Talento mostraban dos cosas que no debían salir: la barra
de depuración de Laravel (Debugbar, franja inferior con Messages/Queries/Models/etc.,
solo para desarrollo) y, en el header, el nombre/avatar de la cuenta con la que se tomó
la captura.

Se volvieron a tomar las 29 capturas con Playwright, esta vez ocultando ambas cosas
antes de disparar el screenshot:
- Debugbar oculta vía CSS (`.phpdebugbar { display:none }` — el primer intento falló
  porque se buscó por `#phpdebugbar`, un id que no existe; es una clase, no un id).
- El botón de usuario del header admin (`#page-header-user-dropdown`, avatar+nombre+
  flecha) oculto igual.
- En el Portal de Colaborador, el nombre/correo de la cuenta de prueba reemplazado por
  un texto genérico ("Ejemplo"/"colaborador@meganet.mx") en cualquier parte de la
  pantalla donde aparecía (header, barra lateral, tarjeta de bienvenida).
- De paso se desactivaron transiciones/animaciones CSS antes de cada captura — al
  ocultar la Debugbar el layout se movía, y una captura salió con un icono de tema a
  medio animar; sin transiciones queda una imagen limpia siempre.

Mismo archivo, mismo nombre — las 29 imágenes en `public/images/manual/talento/` se
sobrescribieron in-place, sin tocar el contenido de las secciones (el HTML de cada
sección ya las referenciaba por ruta, no hace falta re-publicar nada en la BD).

### Verificación
Playwright: 0 notas de rol visibles (ya corregido antes), 29 imágenes cargando sin
ninguna rota, 0 errores de consola, PDF 200 con las capturas nuevas embebidas.
Revisión visual de varias capturas (admin y Portal): sin Debugbar, sin nombre/avatar de
usuario, sin artefactos de animación.
