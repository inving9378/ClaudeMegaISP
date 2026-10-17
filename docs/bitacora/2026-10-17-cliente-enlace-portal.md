## 2026-10-17 12:58 — Enlace «Portal del cliente» en el encabezado de la ficha

**Rama:** `cliente-enlace-portal` (worktree `/var/www/megaisp-cc`, base `main` 40ebd3ce). Sin item
del roadmap: prompt directo de Irving. Nivel A (visual y aditivo).

### Qué se hizo
- `resources/js/components/module/client/InformationClientCrud.vue`: en la línea inferior del
  encabezado (`.cic-subline`), después de «Alta {fecha}», un enlace «Portal del cliente» con icono
  `mdi-open-in-new`. Se muestra siempre (no depende de que haya fecha de alta).
- `href="/portal/login"` **relativo** (decisión de Irving): abre el portal del mismo servidor, igual
  en dev y en prod. No se usa `config('portalcliente.domain')` porque `portal.meganet.mx` aún no
  está publicado con SSL.
- `target="_blank" rel="noopener"` + `data-spa-skip` (spa-nav ya ignora links con `target`, el
  atributo es defensa extra, mismo patrón que el botón «Mi portal»).
- Estilos `.cic-portal-link`: índigo `#4f46e5` (hover `#4338ca` + subrayado), en `.cic-dark`
  `#a5b4fc` (hover `#c7d2fe`) — la misma paleta que la referencia MEG del encabezado.
  `white-space: nowrap` para que «Portal del cliente» no se parta en dos renglones.

### Hallazgo
`public/assets/css/bootstrap.min.css` trae `a{text-decoration:none!important}` global: ningún
`text-decoration: underline` normal funciona en un enlace del sistema. El hover usa
`underline !important` (gana por especificidad del selector scoped). Comentado en el CSS.

### Verificación (vista previa :8081, Chromium headless, usuario temporal ya borrado)
- `/cliente/editar/7737` claro y oscuro: el enlace aparece junto a «Alta 14 oct 2026», color índigo /
  índigo claro, subrayado al hover en ambos modos.
- Clic → pestaña nueva en `/portal/login` («Iniciar Sesión — Portal Meganet»); la ficha se queda
  en `/cliente/editar/7737`.
- Encabezado con 4 datos (plan, colonia, vendedor, alta; textos largos inyectados en el DOM porque
  7737 y 6406 solo traen «Alta») a 1440 px y 390 px: la línea se acomoda en renglones, el enlace queda
  pegado a «Alta», sin scroll horizontal.
- Observación aparte (preexistente, no de este cambio): Plan/colonia/vendedor no se pintan en esos
  clientes porque `optionLabel()` busca la etiqueta en `fieldsJson[...].options` y esas opciones no
  llegan cargadas.
- Usuario temporal 5033 (`prueba_portal_link_tmp`, rol DESARROLLADOR, password aleatorio) creado
  para la sesión de prueba y **borrado** al terminar con su rol y su archivo de sesión.

### Pendiente registrado
- Item **#553** (nivel C, `requiere_irving`, fuera del pool): «Entrar como este cliente»
  (impersonación). Toca autenticación; solo registro, no se implementa.

### Al mergear
Es frontend: tras el merge hay que correr `npm run prod` en `/var/www/megaisp` o el sitio real
seguirá sirviendo el bundle viejo.

## 2026-10-17 14:10 — Ajustes de Irving: cada cliente a su portal + pestañas tipo carpeta

Observaciones de Irving tras la primera entrega: (1) no veía el enlace — lo revisaba en el sitio
real (puerto 80) y la rama aún no estaba mergeada; (2) cada cliente debe ir a SU portal;
(3) debe abrir en pestaña aparte (ya lo hacía, `target="_blank"`); (4) las pestañas de la ficha se
ven como «banner» y deben verse como carpetas.

Decisiones (Irving): enlace = login del portal con el número de ese cliente ya escrito (no
impersonación, esa sigue en #553); pestañas = «carpeta con color» (activa índigo, texto blanco);
yo mergeo a `main` y compilo.

### Cambios
- `InformationClientCrud.vue`: `href` → `/portal/login?cliente={id}` (id de la ficha =
  `client_main_information.client_id`, que el login ya acepta como identificador).
- `PortalCliente/Controllers/AuthController.php::showLogin()`: lee `?cliente=` (solo dígitos, 1-10)
  y lo deja en `_old_input` con `session()->now()` (no sobrevive a la siguiente petición) → el campo
  «Número de cliente o Email» sale prellenado porque la vista ya usa `old('identificador')`.
  **No toca la vista** a propósito: otra sesión está rediseñando `auth/login.blade.php`
  (rama `fix/portal-cliente-configuracion`, 677 líneas) y su versión conserva el mismo
  `old('identificador')`, así que no chocan. Si el navegador ya tiene abierta la sesión de portal de
  OTRO cliente, muestra el login del cliente pedido en vez de mandar al portal equivocado; si es el
  mismo cliente, va directo a su panel como antes. La verificación de contraseña no cambia.
- `ClientCrud.vue`: pestañas tipo carpeta (borde, esquinas redondeadas arriba, gris las inactivas,
  índigo + blanco la activa, línea índigo de 3 px debajo; variante oscura). `align="left"` +
  `indicator-color="transparent"` (se quitó `active-color`).

### Hallazgo
Bootstrap `.row > * { width: 100%; flex-shrink: 0 }` alcanza a cada `q-tab` porque
`q-tabs__content` lleva la clase `row` → con `align="left"` cada pestaña medía 1043 px. Por eso la
ficha usaba `align="justify"` (y antes un `:style width: percentage` por pestaña). Fix: `width: auto`
en el `:deep(.q-tab)` scoped. Cualquier otro `q-tabs` que se quiera alinear a la izquierda tendrá
el mismo problema.

### Verificación (vista previa :8081, usuario temporal 5036 ya borrado)
- Pestañas en claro y oscuro (activa Información y activa Servicios) y a 390 px (se desplazan con
  flecha, sin romper el encabezado).
- Clic en «Portal del cliente» de 7737 → pestaña nueva `/portal/login?cliente=7737` con el campo
  identificador = `7737`; la ficha se queda en `/cliente/editar/7737`.
- `?cliente="><script>…` → campo vacío (se descarta todo lo que no sean dígitos).
- No probado: el caso «sesión de portal de otro cliente abierta» (requeriría la Contraseña WEB de un
  cliente real); queda cubierto por la comparación de `client_id` en `showLogin()`.
