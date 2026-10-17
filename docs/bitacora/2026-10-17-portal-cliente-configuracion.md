## 2026-10-17 13:20 — Configuración → Portal Cliente (presentación administrable de las pantallas de acceso)

**Rama:** `fix/portal-cliente-configuracion`, construida encima de `fix/portal-cliente-rediseno-login`
(`6510402a`). Sin mergear y sin desplegar.

### Qué se construyó
Un panel en **Configuración → Portal Cliente** (`/configuracion/portal-cliente`) para cambiar sin
tocar código la presentación de las tres pantallas de acceso del portal (iniciar sesión, crear
cuenta, recuperar contraseña). Pestañas:

1. **Apariencia** — fotografía de escritorio, imagen móvil, imagen de respaldo; posición
   horizontal/vertical, intensidad del degradado, oscurecimiento y transparencia; recorte aproximado
   en escritorio y móvil.
2. **Identidad corporativa** — logo oficial del sistema (predeterminado) o logo propio del portal,
   título, subtítulo, eslogan, 5 colores, estilo de tarjetas y del formulario.
3. **Mensajes** — título, palabra destacada, descripción, tamaño, alineación y visibilidad.
4. **Tarjetas de servicios** — las 4 tarjetas: título, descripción, ícono (biblioteca local de 17),
   color (8), visible/oculta y orden. Siguen siendo informativas (sin enlaces).
5. **Pantallas de acceso** — título/subtítulo de crear cuenta y recuperar, y si cada pantalla
   muestra el mensaje de agradecimiento y las tarjetas.
6. **Vista previa y publicación** — vista previa en escritorio/tablet/móvil de cualquiera de las 3
   pantallas con el borrador, lo publicado o una versión del historial (misma plantilla que el
   portal); publicar con confirmación y nota; historial con restauración.

Flujo: **Editar → Guardar borrador → Vista previa → Publicar**. El borrador nunca llega a los
clientes. Candado optimista por `revision`: si otro administrador guardó/publicó/descartó antes,
409 y se pide recargar (no se sobrescribe). Publicar invalida la caché de lo publicado.

### Datos y permisos
- Migraciones aditivas (`app/Modules/Addons/PortalCliente/migrations/`):
  - `2026_10_17_130000_create_portal_cliente_apariencia_tables` — `portal_cliente_apariencia_versiones`
    (estado borrador/publicada/historial, config JSON, revisión, resumen de cambios, quién/cuándo; índices
    únicos que garantizan un solo borrador y una sola publicada) y `portal_cliente_apariencia_imagenes`
    (metadatos; el binario va al disco privado, nunca a la BD).
  - `2026_10_17_130100_create_portal_cliente_apariencia_permissions` — `portal-cliente.apariencia.ver`,
    `.editar`, `.publicar`, asignados SOLO a super-administrator y DESARROLLADOR. No terminan en `.view`,
    así que el auto-reparto de permisos de lectura no los toma.
- **Aplicadas solo en `megaisp_test`** (con `--path`, una por una). **NO se corrieron en la BD de dev
  ni en producción.** Al mergear, correrán con el `migrate` normal.
- Bitácora de acciones: `activity_log` con `log_name = 'portal_cliente_apariencia'` (publicar,
  restaurar, descartar), además del historial de versiones (publicador, fecha, resumen de cambios, nota).

### Seguridad
- Rutas administrativas con `web` + `auth` + `can:` (y el controlador lo repite). Lectura, edición y
  publicación son permisos distintos.
- Imágenes: extensión permitida + MIME real (finfo) + que coincidan + decodificable + medidas mínimas +
  tope de 36 MP; se **re-codifican a WebP** con nombre UUID generado por el servidor (descarta EXIF y
  cualquier contenido incrustado). Sin SVG. Se guardan en el disco privado `local`.
- El público solo puede descargar imágenes de la versión **publicada** (`/portal/apariencia/imagen/{uuid}`,
  404 para borradores); el panel las ve por `/configuracion/portal-cliente/imagenes/{uuid}`.
- Textos: solo texto plano (se quitan caracteres de control y marcas bidi), escapados en Blade. Colores
  `#RRGGBB` validados; íconos y colores de tarjeta solo del catálogo del servidor.
- Limpieza: una imagen se borra solo si ninguna versión (borrador, publicada ni historial) la usa y
  pasaron 24 h desde que se subió.

### Cambio fuera del módulo (aditivo, opt-in)
`AdminPanelController::configSections()`: nueva bandera `strict_permission` en `config_sections`. Si
una sección la declara, se oculta a quien no tenga su `permission`. Sin la bandera, todo sigue igual
(antes el bloque «Configuración de Módulos» mostraba todas las secciones de addons a cualquiera).

### Pruebas
- `tests/Feature/Portal/AparienciaPortalTest.php` (nuevo, megaisp_test con rollback y disco falso): ver resultado abajo.
- `tests/Feature/Portal/AuthTest.php`: igual que antes del cambio (5 bien / 11 mal). Las 11 fallas
  son las mismas de siempre y por la misma causa verificada: `PortalTestCase::crearClientePortal()`
  inserta `clients.created_by = 1` y en `megaisp_test` no existe `users.id = 1`
  (`clients_created_by_foreign`). No relacionado con este trabajo.

### Resultados finales
- `AparienciaPortalTest`: **17/17 (161 verificaciones)** — acceso por rol (invitado, sin permiso,
  solo lectura, editor sin publicar, tarjeta de Configuración solo para autorizados), imágenes válidas
  JPG/PNG/WebP re-codificadas, 7 archivos peligrosos/inválidos rechazados (SVG, PHP disfrazado, PNG con
  nombre .jpg, GIF, GIF con nombre .jpg, muy pequeña, HTML), textos escapados (script, img onerror,
  marca bidi), valores fuera de catálogo rechazados (CSS inyectado en un color, ícono arbitrario,
  números fuera de rango, imagen inexistente, tarjeta repetida), orden y visibilidad de tarjetas,
  borrador invisible + vista previa, conflicto entre administradores (409), publicar + historial +
  caché invalidada + bitácora + no publicar sin cambios, descartar, restaurar conservando imágenes,
  imagen pública solo si está publicada + limpieza de huérfanas, diseño de fábrica sin configuración.
- `AuthTest`: 5/16, **idéntico al estado inicial** (mismas 5 bien, mismas 11 mal por la misma FK).
- Recorrido en navegador (servidor local de la rama contra `megaisp_test`, usuarios temporales):
  **21/21**. Batería de inicio de sesión del rediseño: **22/22** (corrección: en el reporte del
  rediseño se dijo «23/23», la batería tiene 22 comprobaciones).
- Errores encontrados y corregidos en el camino: el `.row > *` del Bootstrap global rompía las piezas
  internas de Quasar en el panel (se neutraliza solo dentro del panel); el atributo `data-enviando`
  se usaba a la vez como texto y como bandera y el botón mostraba «1» (separado en
  `data-texto-enviando` + `data-pl-enviando`); la Debugbar aparecía dentro de la vista previa.

### Limpieza
Borrados de `megaisp_test`: versiones, imágenes, archivos, 3 usuarios temporales y sus roles/permisos,
el cliente temporal, las filas de `activity_log` del recorrido y las sesiones del worktree. Se dejaron
en `megaisp_test` las dos tablas nuevas y los 3 permisos (esquema), para poder volver a correr
`AparienciaPortalTest`.

### Pendiente
- Fotografía de fachada real y autorizada: se sube desde la pestaña Apariencia (no hay ninguna en el
  proyecto; las pruebas usaron una imagen sintética que no se guardó).
- Mergear y desplegar: decisión del responsable. Al mergear hay que correr `migrate` (2 migraciones
  aditivas) y `npm run prod` en el checkout principal.
