# Manual de usuario con visibilidad por rol — arranca con Talento

## 2026-09-22 12:30 — Pedido de Irving: manual de cómo funciona cada pantalla, estilo Torre, exportable a PDF, filtrado por rol

### Contexto

Irving pidió que exista un manual de usuario explicando pantalla por pantalla cómo
funciona el sistema, empezando por Talento, con la apariencia de la Torre de Control
y que se pueda exportar a PDF. En una segunda vuelta aclaró: reusar el "Manual General
de la Empresa" ya existente (`/empresa/manual`) en vez de construir uno nuevo desde
cero, y que cada rol solo vea, dentro del manual, las pantallas que realmente le
corresponden (ejemplo suyo: si un técnico solo puede ver "mapa", que en el manual
solo aparezca esa parte para él).

### Qué había antes

`/empresa/manual` ya existía (item #838): capítulos → secciones → versiones, editable
desde el navegador con publicar/borrador, exportable a PDF (dompdf) con membrete de la
empresa. Pero era **de todo o nada**: solo `super-administrator`/`DESARROLLADOR` podían
siquiera abrirlo, y quien lo abría veía el documento completo, sin distinción de rol. Su
estilo visual era propio (azul), no el de la Torre de Control.

### Qué se agregó

**Visibilidad por sección, no solo por documento completo.** Cada sección ahora puede
llevar una lista de roles (`visible_roles`, columna nueva). Sin restricción marcada = se
ve igual que antes (nada cambia para el contenido ya existente de políticas de empresa).
Con roles marcados, solo esos roles (y quien administra el sistema, que siempre ve todo
para poder editar) ven esa sección — el resto del documento se las salta como si no
existieran, capítulos incluidos si terminan sin ninguna sección visible para ese rol.
Se agregó también una opción "Solo administración" para las pantallas que de verdad son
exclusivas de quien gestiona el sistema (compensación, liquidaciones, roadmap...), para
no tener que elegir entre "todos" o "nadie".

**El manual se abrió al resto del staff.** Antes solo 2 roles podían entrar; ahora
cualquier rol operativo (técnicos, vendedores, mostrador, almacén, contabilidad,
supervisión, conductor) puede abrir el manual y ve exactamente lo que le toca según lo
de arriba. Crear/editar/publicar contenido sigue siendo exclusivo de quien administra el
sistema — nadie más puede tocar el documento, solo leerlo.

**Se re-vistió con la paleta de la Torre de Control.** El acento cambió de azul a verde
azulado (el mismo teal de la Torre), las tarjetas quedaron con las esquinas redondeadas
y la sombra que usa el resto de las pantallas "Torre", y los botones con el mismo look.
El modo oscuro del manual ya existía (interruptor propio en la pantalla) — se ajustó
para usar los mismos colores oscuros que la Torre.

**Se llenó con el contenido real de Talento — 30 secciones**, una por cada pantalla del
módulo (panel de administración completo + las 6 secciones del Portal de Colaborador:
Mi día, Mi dinero, Mi material, Mis prospectos, Mis documentos, Perfil), explicando en
español simple para qué sirve cada una y qué se puede hacer ahí. Cada sección quedó
etiquetada con el rol real que hoy tiene acceso a esa pantalla en el sistema — así el
manual no promete algo distinto de lo que el sistema realmente deja hacer.

### Verificación

- `php -l` limpio en los 3 archivos PHP tocados (modelo, controlador, seeder).
- El JavaScript de la pantalla (extraído y corrido con `node --check`, mismo método que
  se usó para el bug de `/empresa/manual` del barrido anterior) sin errores de sintaxis.
- Pendiente: correr las migraciones + el seeder en dev tras mergear a `main`, y
  verificación visual en navegador (claro/oscuro, exportar PDF, y entrar como un rol NO
  administrador para confirmar que solo ve lo suyo).

### Próximos módulos

Este mismo capítulo→sección→visibilidad-por-rol queda listo para repetirse con
cualquier otro módulo cuando Irving lo pida — no hace falta construir nada nuevo, solo
escribir el contenido de las pantallas de ese módulo.
