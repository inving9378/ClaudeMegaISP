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
