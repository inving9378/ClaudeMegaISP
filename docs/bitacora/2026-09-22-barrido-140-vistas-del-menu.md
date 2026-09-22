# Barrido completo de las 140 vistas del menú lateral

## 2026-09-22 11:30 — Pedido de Irving: verificar que ningún select quede "desplegado" + barrer todas las vistas

### Contexto

Tras corregir el bug de los desplegables (Estado/Municipio/Colonia) y los links
muertos de "editar" en 10+ módulos de catálogo, Irving pidió comprobarlo de verdad
en pantalla y luego recorrer TODAS las vistas del sistema reportando cualquier error.

### Método

Recorrí las **140 URLs únicas** que aparecen en el menú lateral (con sesión real,
usuario con permisos de DESARROLLADOR), cargando cada una y registrando: código de
respuesta del servidor y cualquier error de JavaScript en consola. De las 140, 6
tenían un problema real. Los 6 quedaron corregidos:

### 1. `/demo` — 404

El addon "Demo" (plantilla de ejemplo del sistema modular, pensada para
instalarse/desinstalarse solo al probar el kit) quedó **activada por accidente**
en la base de datos desde una prueba del 29 de mayo que nunca se revirtió. Su
propio manifiesto dice que debe estar inactiva por defecto, y su código real
**nunca tuvo página** para las rutas `/demo`/`/demo/items` que su menú anuncia
(solo un endpoint de API). Corregido: migración que la regresa a inactiva
(aditiva/reversible — reinstalarla para pruebas sigue siendo tan simple como
antes).

### 2. `/documentacion-corporativa` — 500

Al módulo le faltaba un dato base: ninguna "empresa" estaba dada de alta en su
catálogo interno, y el propio módulo se niega a funcionar sin eso (mensaje de
error explícito: "Corre el seeder del módulo"). Corría el seeder existente
(`EmpresaSeeder`, ya escrito, idempotente, sin inventar datos — solo usa lo que
ya existe en la configuración de la empresa o deja campos en blanco). Aplicado en
dev directamente (dato, no código).

### 3. `/empresa/manual` — pantalla completa en blanco

El manual general de la empresa (identidad, misión, políticas, reglamentos,
procedimientos — toda la documentación interna) nunca cargaba nada: un error de
JavaScript apenas al iniciar tumbaba la pantalla completa. Causa: la plantilla
usaba una técnica frágil para insertar 4 valores dentro de un bloque de
JavaScript "crudo" (activar/desactivar el modo `@verbatim` de Laravel varias
veces seguidas), y esa técnica fallaba — el navegador recibía literalmente el
texto interno que Laravel usa para procesarlo, en vez de los valores reales.
Reescrito de forma simple y robusta (los 4 valores se arman aparte, en PHP, y se
insertan de una sola vez). Verificado en pantalla: el manual completo carga con
todas sus secciones.

### 4. `/vendedores/dashboard` — tablero completo roto

El tablero principal de Vendedores (prospectos, ventas, medios de venta,
comparativa mensual, actividades, ranking, estado de prospectos) se rompía
ENTERO — nada cargaba — por una sola gráfica nueva ("Ranking de ventas por
vendedores", agregada esta semana). Esa gráfica en particular truena si intenta
dibujarse ANTES de que lleguen sus datos reales (un comportamiento conocido de
la librería de gráficas con ese tipo de barra en particular). Las demás gráficas
del mismo tablero ya tenían esa protección puesta; a esta se le olvidó. Agregada
la misma protección (esperar a tener datos antes de dibujar). De paso until
corregido un `id` de HTML duplicado que compartía sin querer con otra gráfica
del mismo tablero (copiado sin cambiar al construirla). Verificado en pantalla:
el tablero completo carga, ranking incluido, con datos reales.

### 5. `/scheduling/project` — equipos del proyecto no cargaban

Al crear/editar un proyecto, la lista de "equipos" disponibles nunca cargaba.
Causa: una llamada al servidor duplicada y mal escrita (usando el método
equivocado) que TRONABA antes de llegar a la llamada correcta que estaba
justo después — así que la correcta nunca se ejecutaba. Se veía como un
comentario "usar el endpoint directamente" seguido de la llamada correcta,
pero la llamada VIEJA/rota nunca se borró. Eliminada la llamada rota.

**Hallazgo aparte, NO corregido:** el campo "Categoría" del formulario de
proyecto nunca tuvo configurado de dónde sacar sus opciones (a diferencia de
"Tipo"/"Socios"/"Project Lead"/"Flujo de Trabajo", que sí la tienen). Confirmé
en la base de datos que **nadie ha podido guardarle un valor jamás** (0 filas
con ese campo lleno). No inventé una fuente de datos para no arriesgarme a
apuntarlo al modelo equivocado — queda para que Irving decida a qué debería
apuntar, o si el campo se retira.

### 6. `/marketing/publishing/campaign` — lista de campañas no cargaba

El listado de campañas para publicar nunca cargaba: un error de JavaScript
tumbaba la pantalla. Causa: el código esperaba que el servidor devolviera las
campañas en una forma (`{campaigns: [...]}` o una lista simple), pero el
servidor realmente las devuelve paginadas, forma estándar de Laravel
(`{data: [...], current_page: 1, ...}`). Corregido para leer la forma real.

### Verificación

Cada uno de los 6 se probó de forma aislada, con el código ya compilado
(`npm run dev`), confirmando código de respuesta 200 y consola limpia. Los 3
que rompían pantallas COMPLETAS (`/empresa/manual`, `/vendedores/dashboard`,
`/marketing/publishing/campaign`) se verificaron además con captura de pantalla
real, mostrando contenido completo donde antes no aparecía nada.

Del resto de las 140 vistas, ninguna tenía error real (más allá de un aviso
menor de JavaScript en 2 pantallas de catálogo, "undefined is not valid JSON",
que no bloquea la pantalla — queda anotado para revisar aparte, sin urgencia).
