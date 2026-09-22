# Fix — Links "editar" rotos (500) en 10+ pantallas de catálogos/administración

## 2026-09-22 10:15 — Barrido pedido por Irving: corregir links rotos antes de seguir probando

### Contexto

Al verificar el fix de los desplegables, encontré que visitar directamente el link de
"editar" de Municipio/Colonia daba un error 500 real del servidor (no relacionado con
los desplegables). Irving pidió corregir los links y luego revisar pantalla por
pantalla.

### Causa raíz (afecta 10 módulos, no solo Municipio/Colonia)

10 módulos de catálogo/administración (Municipios, Colonias, Vendedores, Equipos,
Plantillas de lista de verificación, Servicios en address list, Nomenclaturas,
Plantillas de tarea, Flujos de trabajo, Plantillas de documento) manejan alta/edición
con un **modal sobre la misma lista** — nunca tuvieron una pantalla propia de "editar".
Pero el sistema de rutas SÍ tenía registrado un link `/editar/{id}` para los 10,
apuntando a un método `edit()` que **nunca existió** en la clase de la que todos
heredan — quedó ahí desde antes de que esos módulos pasaran al patrón de modal.
Visitar cualquiera de esos 10 links a mano tronaba con error 500.

Until ahora nadie lo había reportado porque ningún botón de la interfaz real apunta
a esos links — solo se puede llegar escribiendo la URL a mano. Aun así, se corrigió
en el lugar compartido de donde heredan los 10 (un solo cambio cubre los 10 y
cualquier módulo futuro que use el mismo patrón), para que si alguien llega ahí
(un link viejo, un favorito guardado) lo mande a la lista real en vez de tronar.

**Casi rompo algo real al hacer este arreglo:** uno de los 10 módulos ("Reglas de
comisión" del catálogo interno) sí tenía su PROPIA pantalla de edición ya
funcionando, con una forma distinta de recibir los datos. Mi primer intento chocó con
esa pantalla y la hubiera dejado inutilizable. Lo detecté de inmediato al volver a
probar todo antes de dar por cerrado el cambio, y lo corregí sin afectarla —
verificado que sigue funcionando exactamente igual que antes.

### 3 módulos más con el mismo problema, pero por su cuenta

Estado, Ubicación y Sucursal (catálogos de administración, hermanos de Municipio/
Colonia) tenían el mismo problema pero por una ruta de código distinta (no comparten
la clase de arriba). Se corrigieron uno por uno con el mismo criterio: si alguien
llega ahí, mandarlo a la lista real.

### Verificado

Los 13 links (`municipio`, `colonia`, `estado`, `ubicacion`, `sucursal` + Tickets +
"reglas de comisión" que resultó ser un caso aparte, ver abajo) probados uno por uno
con datos reales antes y después: los 12 que se podían arreglar con este patrón ahora
llevan a la pantalla correcta en vez de tronar; ninguno de los módulos ya
funcionando (Reglas de comisión con su pantalla propia, y los 10 con modal) perdió
funcionalidad.

### Hallazgo aparte, NO corregido — pantalla completa nunca conectada

Al revisar "Reglas de comisión" until el fondo encontré algo distinto: la pantalla
de editar una regla de comisión **SÍ está construida** (con todo y su formulario en
Vue), pero **nunca se conectó a ninguna página real del sistema** — no hay ningún
menú ni botón en TODO el sistema que lleve ahí. El único lugar donde aparece un link
"editar" hacia esa pantalla es DENTRO de la propia pantalla de lista de reglas de
comisión — pero esa lista, a su vez, tampoco está conectada a ningún menú. Es una
función completa que se construyó pero se quedó sin puerta de entrada. No es un link
roto que se pueda arreglar con una redirección — haría falta decidir si esta función
se activa (y dónde va en el menú) o se da de baja. Queda documentado para que Irving
decida, no se tocó.
