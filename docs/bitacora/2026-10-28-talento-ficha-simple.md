## 2026-10-28 — Talento: ficha más simple

Lo que Irving señaló en su video sobre la ficha (Diana como Mostrador, Brandon como técnico):
«un espaciosote bien grande» arriba, «registra tu entrada… y tener esto a la mano», «¿qué es esto,
de dónde sale?» y «Descargar credencial» que no descargaba.

Qué se hizo (rama `feat/talento-ficha-simple`):
- **Espacio en blanco arriba:** las 30 pantallas de Talento traían su propio marco de página dentro
  del marco general, así que el margen de arriba salía doble (~180 px en vez de ~90). Se quitó el
  marco repetido; ahora el contenido empieza a la misma altura que en Flotas y el resto del sistema.
- **La propia ficha abre en «Mi día»** (antes abría en la última pestaña usada o en «Información»).
  `?tab=` sigue mandando (atajo del menú de usuario, campanita).
- **Entrada y salida en «Mi día»:** arriba va la misma tarjeta de «Asistencia» con el botón
  «Registrar entrada» / «Registrar salida». Antes «Mi día» solo decía «se registra en Asistencia».
  Asistencia la conserva.
- **Pestañas:** se arman desde una sola lista. A la vista quedan las del día a día (propia ficha: Mi
  día, Mi dinero, Información, Asistencia, Mis prospectos, Mi equipo, Documentos; viendo a otra
  persona: Información, Asistencia, Órdenes, Compensación, Liquidaciones, Penalizaciones,
  Documentos) y el resto queda en «Más». Debajo de la barra, una línea dice para qué sirve la pestaña
  abierta; en «Más», cada opción trae la suya. No cambió ningún permiso: se ve lo mismo que antes,
  solo acomodado.
- **Credencial:** «Descargar credencial» ahora baja el archivo (`credencial-<nombre>.pdf`) sin
  salir de la ficha (`/talento/mi-ficha/{id}/pdf?descargar=1`). Sin `descargar` se sigue abriendo en
  el navegador, como lo usa la app. «Editar foto y datos» ya estaba.

Probado en la vista previa de la rama, sin registrar entradas ni guardar nada:
- Mostrador (prueba_mostrador) y técnico (demo_tecnico_bajo): entran por «Talento» a su ficha, abre
  en «Mi día» con «Registrar entrada»; contenido a 90 px del borde (antes ~180); «Más» abre, marca la
  elegida, se cierra al elegir o al hacer clic fuera; la credencial se descarga (PDF de ~166 KB) y
  la página no cambia.
- Administrador (prueba_admin) viendo al técnico: abre en «Información», barra en una sola fila.
- Supervisor (demo_supervisor) viendo a su técnico con `?tab=mi_dia`: abre en «Información» («Mi día»
  es solo de uno mismo).
- Celular (390 px): «Más» abre a lo ancho de la pantalla.
- Liquidaciones, Órdenes y Flotas: el contenido empieza a 90 px.
