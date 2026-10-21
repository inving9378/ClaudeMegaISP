## 2026-10-21 13:59 — Talento: se retiran los niveles y el catálogo de puestos

Pedido: "borralas, ya eso esta implementado en administracion, para que vas a tener tablas
duplicadas". Una pantalla de prueba (cuenta `prueba_admin`, super-administrador, solo dev) se creó
para revisar las pantallas señaladas.

### Qué eran y de dónde salieron

- **Niveles** (`/talento/niveles`): Fase 7b del plan de 9 fases de Talento, hecho de una sola vez
  la noche del 3 al 4 de junio (commit `58c202bc`, misma noche que la sobreproducción). Cuatro
  niveles con sueldo (Junior $3,800, Técnico $5,200, Senior $7,000, Expert $9,500) y certificados
  requeridos; "promover" cambiaba el sueldo base del colaborador; "gating" bloqueaba tipos de
  orden o actividades por nivel. No hay registro de quién definió los niveles ni los sueldos.
  Uso real: 0 colaboradores con nivel, 0 promociones, 0 tipos con nivel requerido, 0
  certificados emitidos.
- **Catálogo de puestos** (`/talento/puestos`): item #923 del Circuito (3–4 sep), aprobado con la
  cuenta CARLOS, porque el texto libre `job_title` estaba vacío y el selector de paquetes de
  documentos salía sin opciones. Uso real: 0 de 35 colaboradores con puesto; un paquete de
  documentos para "Vendedor" (17 sep).

### Qué se hizo (dos ramas, sin mezclar)

- `fix/talento-quita-niveles` (`e4781770`): fuera pantalla, API, servicio, modelos, seeder,
  revisión de nivel en órdenes y reportes de proyecto, columna "Nivel" del escalafón, tablero de
  equipo y War Room, permisos `talento.levels.*` y la página del manual. El permiso
  `talento.levels.view` también abría `/talento/escalafon` a los técnicos, pero sin datos (la API
  pide `talento.escalafon.view`): veían la página vacía, ya no.
- `fix/talento-quita-puestos` (`e7d562b8`, `62d81a83`): el puesto de cada persona es su rol de
  Administración (`TalentoColaborador::puestoNombre()`/`rolesDePuesto()`; Vendedor solo cuando no
  hay otro rol, mismo criterio que `talento:sync-colaboradores`). Lo usan la ficha, el carnet, la
  app, los contratos y Documentación corporativa. Paquetes de documentos por rol; quien tiene
  varios roles recibe los de todos. Antes se buscaban por `job_title` (vacío), así que nunca se
  generó ningún paquete: con esto empiezan a generarse al dar de alta (y al asignar equipo o
  vehículo, que ya lo disparaban). Fuera pantalla, API, modelo, permiso
  `talento.puestos.manage` y el campo Puesto del formulario de la ficha.

Migraciones (corren al mezclar): `2026_10_21_120000_drop_talento_levels` y
`2026_10_21_120100_drop_talento_puestos`. Borran tablas y columnas en `up()`, a pedido explícito;
`down()` recrea la estructura.

### Respaldo de las filas que se borran (dev; copia de prod del 15-oct)

- `talento_levels`: (1,'Junior',1,{"count":1},3800), (2,'Técnico',2,{"count":2},5200),
  (3,'Senior',3,{"count":3},7000), (4,'Expert',4,{"count":5},9500). `talento_level_assignments`
  vacía.
- `talento_puestos`: (1,'Vendedor'), (2,'Técnico'), (3,'Mostrador'), (4,'Almacén'), todos activos.
- `talento_puesto_document_templates`: (1,'Vendedor',puesto_id 1,template 12 "Convenio Individual
  de Comisiones"). La migración la deja como `puesto='Vendedor'` (rol Vendedor).
- `job_title`, `puesto_id`, `level_id` y `required_level_id`: vacíos en todas las filas.

### Pruebas (contra una copia de la base, `megaisp_dryrun`, ya borrada)

La base de dev no se tocó. Migraciones: aplican, se deshacen (vuelven 3 tablas y 7 columnas) y
vuelven a aplicar. Navegador y API en la copia: 27 de 27 (las dos "fallas" del reporte eran solo
el logo, que no carga en el servidor de prueba). Por rol (`v_roles`): 94 de 98; las 4 restantes
no dependen de este cambio (una evaluación temporal ya borrada y tres montos esperados de antes
del ajuste de la regla de prueba de hoy).

### Al mezclar a main

1. Mezclar `fix/talento-quita-niveles` y luego `fix/talento-quita-puestos`. Conflictos conocidos:
   en `module-sidebar/talento.blade.php` se quitan los dos enlaces; en `docs/modulos/talento.md`
   queda la línea de Identidad sin `level_id` y con la nota del puesto.
2. `php artisan migrate` en dev, `permission:cache-reset` en los dos checkouts y
   `npm run prod` en el principal.
3. `php artisan asistente:aprender talento` y commitear la guía (todavía menciona Niveles).
4. Si después se mezcla `feat/talento-api-app-21oct`: en `TalentoMobileEquipoController::
   informacion()` el puesto queda `$col->puestoNombre()`.
