## 2026-11-28 10:40 — Campañas: editar desde la lista, zona y caja con búsqueda, diálogo a toda la pantalla

Pedido de Irving: «en la lista de las campañas no puedo editar una campaña», «a las zonas y las cajas
agrégales búsqueda y anímalos», «reintentos y límites que se vean siempre, que no se desplieguen» y
«ánchame el modal al tamaño de la pantalla». Rama `campanas/editar-campana`.

### Editar
- Botón «Editar» (lápiz) en cada fila y en el pie del panel de llamadas; `PUT /cobranza/campanas/{id}`
  (`CampanaController::update`, mismo permiso `cobranza.manage` que crear y borrar, ya mapeado en
  `route_permission.php`).
- **Borrador:** cambia todo, también «A quién se le llama»; «Siguiente» guarda y sigue a revisar la lista.
- **Activa o en pausa:** sus llamadas ya están cargadas. Cambian el nombre, el mensaje, la voz, las fechas,
  el horario y los límites, que se aplican a las llamadas que faltan (`BlastCampanaJob` los vuelve a leer
  en cada pasada y la caché de audio va por texto y voz). «A quién se le llama» y el motivo se muestran
  fijos y el servidor los ignora si llegan. Una de corte ya activada pide además el permiso de corte.
- **Terminada:** no se edita (422); se duplica.
- Las mismas reglas que el alta (`reglasCampana` y `prepararCampana` compartidas): sin «Solo los que
  deben» la nota no puede ir vacía ni llevar variables (con el valor que ya tiene la campaña si está activada).
- Si en otra pestaña la activaron o terminó mientras se editaba, no se pisa: 422 con aviso
  (`lockForUpdate` y se compara el estado).

### Zona y caja con búsqueda
- `CampanasSelector.vue` (nuevo): combobox de ARIA 1.2 con buscador.
  - Busca sin acentos ni mayúsculas por nombre, clave (D1Z54), «distrito 2» o «wifi».
  - Resalta lo buscado, y el distrito se queda arriba al recorrer sus zonas.
  - Teclado: escribir sobre el campo ya busca; ↑ ↓ RePág AvPág para moverse; Enter elige; Esc cierra
    solo el selector, no el diálogo.
  - Se abre hacia arriba si abajo no cabe en el diálogo.
- La lista se despliega con un solo gesto (crece, se aclara y se enfoca; expo-out).
  - Con «reducir movimiento» solo aparece.
- Lo usan el alta, editar y «Revisar a quién se le llama» (`CampanasAudiencia`).

### Diálogo y formulario
- El diálogo «ancho» ocupa toda la pantalla con 16 px de margen (alta, editar y revisar).
- El formulario va en columnas según el ancho:
  - desde 1400 px, tres: mensaje · cuándo y límites · a quién;
  - de 900 a 1399 px, dos, con «a quién» a la derecha;
  - en celular, una.
- «Reintentos y límites» es una sección siempre abierta (antes un desplegable); el cuerpo del diálogo
  hace scroll.

### Pruebas
- Punta a punta con el código de la rama por el kernel HTTP completo (`CheckRoutePermission` incluido),
  con la base de dev en una transacción que se deshizo y `Bus::fake`.
  - El gestor fue un rol temporal con solo ver y gestionar campañas, sin rol de administrador. «Solo
    lectura» no sirve: no puede guardar nada.
  - Alta 201; borrador editado con todo; 422 con {saldo} sin deudores, sin estados y sin nombre.
  - Activa: el motivo y la audiencia nuevos se ignoran, y la nota, el horario y el intento vacío (se
    queda el que tenía) sí cambian; 422 con {nombre} y con la nota vacía.
  - En pausa: editada. Terminada: 422. Corte activada sin permiso de corte: 403.
  - Usuario sin `cobranza.manage`: 403. Campaña que no existe: 404.
- Pantalla en una página de prueba con datos inventados (webpack de dev + playwright).
  - Fotos a 1440, 1100 y 390 px, en claro y oscuro: alta, editar borrador, editar activa, lista y revisar.
  - Búsqueda de zona («21», «wifi»), caja de la zona elegida y teclado: elige con Enter, y Esc deja el
    diálogo abierto con el foco en el campo.
  - «Guardar cambios» manda el PUT a la campaña correcta.
- Pruebas que ya había: AudienciaCampanasTest (8), VozCampanasTest (10), BlasterCampanasTest (44),
  HistorialCampanasTest (6).

## 2026-11-28 11:20 — Revisar a quién se le llama: sin «Adeudo vencido de la lista»

Pedido de Irving: quitar el texto «Adeudo vencido de la lista: $…» del resumen de la revisión (con «Solo los que
deben»). Se quitó junto con su cálculo (`montoALlamar`, sin otro uso). El saldo de cada cliente en la tabla se queda.
Revisado en la página de prueba: el resumen queda con cuántos reciben la llamada y sus avisos.

## 2026-11-28 12:30 — Revisar a quién se le llama: la misma tabla que el listado de clientes

Pedido de Irving: «que en la activación de la campaña salgan las tablas como en la lista de clientes: filtros,
buscar, ordenar y todo eso». `CampanasRevisionTabla.vue` (nuevo) reemplaza la tabla propia de la revisión.
- q-table con `tabla-listado` y el mismo marcado que `DatatableClient`:
  - título «Lista de clientes», filtro de Estado, buscador `dt-search` y menú de 3 puntos (`dt-actions-toggle`);
  - columnas ordenables y redimensionables (`v-table-resizable`);
  - fila coloreada por estado (Subrayado/Relleno, la preferencia global), badge de estado (`clientStatusMeta`);
  - leyenda de estados y la misma paginación (`pagination-btn`).
- Menú de 3 puntos:
  - ver columnas (se recuerdan en el navegador);
  - llamar o quitar a todos los que se ven;
  - estilo de estado;
  - exportar a Excel lo que se ve, con la columna «Se le llama».
- Vistas «Todos / Se les llama / Quitados»; la casilla del encabezado marca o quita a todos los que se ven (no
  solo la página). Columnas: ID (abre la ficha), Cliente, Teléfono, Estado, Zona, Caja y Saldo vencido (con
  «Solo los que deben»).
- La lista sigue llegando completa del servidor (la misma de antes); la tabla filtra, ordena y pagina aquí.
  Lo que se manda al activar no cambia.
- `CampanasDialogo`: con un diálogo abierto, los menús de Quasar (van en `<body>`, z-index 6000) suben a
  10100; Esc con un menú abierto cierra solo el menú.
- Bootstrap 5 (`.row > * { width: 100% }`) choca con los campos de Quasar con adornos: la flecha del selector
  se llevaba el ancho («E…»). Se corrige solo dentro de esta tabla. El buscador va sin ícono, como el del
  listado de clientes. Sin «All» en «Elementos por página» (Quasar no está en español en la app).
- Revisado en la página de prueba (con Quasar como en app.js), a 1440, 1100 y 390 px, en claro, oscuro y
  relleno: orden por saldo, búsqueda, filtro de estado, menús encima del diálogo, Esc, quitar a todos,
  vista de quitados y sin resultados.

## 2026-11-28 14:10 — La tabla de la revisión, rehecha con el diseño de Campañas (impeccable)

Irving: «eso está horrible, aplícale impeccable». La q-table del listado de clientes no encajaba en el diálogo
(otra tipografía, botones de Quasar, campos rotos por Bootstrap, menús que había que subir de capa).
`CampanasRevisionTabla.vue` se reescribió sin Quasar, con el mismo vocabulario de la lista de campañas:
- Barra:
  - buscador con ícono y botón para borrar;
  - «Todos / Se les llama / Quitados» con su número;
  - «Columnas» (panel con casillas; Cliente siempre se ve) y «Exportar».
- Fila de filtros: Estado como fichas con el punto de color del estado (verde, rojo y ámbar, los del sistema)
  y su número; «Mostrando X de Y».
- Tabla:
  - encabezado pegajoso al bajar, al ras del borde del diálogo (`--cpd-pad-top` en `CampanasDialogo`);
  - orden con íconos en cada columna (el saldo empieza de mayor a menor);
  - estado como píldora con punto, saldo a la derecha con cifras alineadas;
  - al quitado se le apaga la fila y lleva «No se le llama».
- Pie como la lista de campañas: rango, «Por página» (25/50/100/250, se recuerda) y anterior/siguiente.
- Angosta (menos de 720 px de tabla): una tarjeta por cliente, con «Todos los que se ven» y «Ordenar».
- Lo que hace no cambió: misma lista, mismas casillas, exportar lo que se ve con «Se le llama».
- Detalle medido: con la casilla a ancho fijo («1%» o en píxeles), la línea del encabezado pegajoso de esa
  celda quedaba un píxel desfasada. Con su ancho natural no.
- Revisado a 1440, 1100 y 390 px, en claro y oscuro:
  - orden, columnas, filtro de estado, búsqueda y Esc (cierra solo el panel);
  - quitar a todos, página 2, vista de quitados y sin resultados;
  - encabezado al bajar.
