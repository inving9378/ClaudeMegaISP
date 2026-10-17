## 2026-10-17 12:07 — Rediseño premium de la pantalla de acceso del Portal Cliente

**Rama:** `fix/portal-cliente-rediseno-login` (sin mergear). **Alcance:** solo presentación de
`/portal/login`.

### Qué cambió
- `app/Modules/Addons/PortalCliente/views/auth/login.blade.php` reescrita (único archivo de código):
  - Composición de dos áreas (≈60/40): identidad a la izquierda (logo, «Gracias por ser parte de
    Meganet», texto de agradecimiento, 4 tarjetas informativas de servicios sin enlaces) y tarjeta de
    acceso con efecto glassmorphism a la derecha.
  - Formulario: mismos campos (`identificador`, `contrasena`), misma ruta (`portal.login.post`), mismo
    `@csrf`, mismos enlaces (`portal.registro`, `portal.recuperar`). Se agregan iconos, `label for`,
    `autocomplete` (`username` / `current-password`), botón accesible de mostrar/ocultar contraseña,
    bloqueo de doble envío con estado «Ingresando…» (se libera al volver con «Atrás»).
  - Alertas: `session('success')`, `session('info')` y `$errors`. **`info` antes no se mostraba**: el
    middleware `AuthPortal` ya mandaba «Inicia sesión para acceder al portal.» y la vista vieja lo
    ignoraba.
  - Iconos en SVG en línea (sin dependencias nuevas). Tipografía Inter de Google Fonts (la misma que ya
    usa `layouts/portal.blade.php`).
  - Responsivo: 2 columnas ≥1024 px; ajuste para laptops de poca altura (1366×768); una columna en
    tablet; en teléfono el formulario completo cabe en la primera pantalla y los servicios quedan en
    2×2 compacto (icono + título).
  - Animaciones de entrada discretas, desactivadas con `prefers-reduced-motion` y cuando la página
    vuelve con un aviso o error (el mensaje se ve de inmediato).
- Logo: sigue saliendo de `LogoEmpresa::url()` (información de la empresa, `company_information.url_logo`,
  con el logo oficial del repo como respaldo).

### Imagen de la fachada — dónde colocarla
No existe en el proyecto ninguna imagen de fachada autorizada; no se usó ninguna externa. El fondo
es un degradado nocturno con retícula. Para activar la fachada **basta con colocar el archivo**
(sin tocar código):

```
public/images/portal/fachada-meganet.webp   (o .jpg)
```

La vista lo detecta, lo pone de fondo con un degradado azul encima para legibilidad (más opaco en
tablet/teléfono) y le agrega `?v=` con la fecha del archivo. Verificado con una imagen provisional
que se borró al terminar. Recomendado: ≥1920 px de ancho, WebP, < 400 KB. Debe ser un recurso con
derechos de uso; es ilustrativo, no se presenta como foto de las instalaciones.

### Lo que NO se tocó
Controlador `AuthController`, rutas, middleware, guard, validaciones, CSRF, BD, migraciones, vistas de
registro/recuperación, layout interno del portal y panel administrativo.

### Pruebas
- **Visuales** (servidor local de la rama contra la BD de dev, solo lectura): 1920×1080, 1366×768,
  768×1024, 390×844, 360×800 — sin scroll horizontal; `backdrop-filter` activo.
- **Funcionales** (servidor local de la rama contra `megaisp_test`, con un cliente temporal creado y
  **borrado** al terminar; ninguna cuenta real): 23/23 OK — aviso de ruta protegida, validación del
  navegador y del servidor, `aria-invalid`, credenciales incorrectas (conserva el identificador), ver/
  ocultar contraseña (ratón y teclado), autofocus, orden de tabulación, bloqueo de doble envío, login
  correcto con Enter → `/portal`, cerrar sesión → «Sesión cerrada correctamente.», recuperar (teléfono
  incorrecto y correcto → login con «Contraseña actualizada»), login con la contraseña recuperada,
  crear cuenta (incorrecto y correcto → dentro), con sesión `/portal/login` → dashboard.
- `prefers-reduced-motion: reduce` → animaciones y transiciones a ~0.
- `php artisan test tests/Feature/Portal/AuthTest.php`: **11 fallan / 5 pasan, idéntico antes y
  después** del cambio. Las 11 fallas son preexistentes: `PortalTestCase::crearClientePortal()` inserta
  `clients.created_by = 1` y en `megaisp_test` no existe `users.id = 1` (FK). Queda como deuda aparte.

### Hallazgo aparte
La contraseña de prueba documentada en `CLAUDE.md` para el cliente 6810 ya no coincide con la de la
ficha. No se cambió (es una cuenta de la BD de dev); por eso las pruebas de inicio de sesión se
hicieron en `megaisp_test`.
