## 2026-11-10 17:26 — Mapa de Red: en el site no se podían conectar los puertos (OLT ↔ organizador)

**Reporte:** en el Mapa de Red, dentro de un site con rack + OLT + organizador, no había forma de
conectar un puerto con otro como en el módulo Mapas.

**Causa:** los modelos espejo del Mapa de Red (MR-06a-1: `MapaRedDevicePort`, `MapaRedFiber`,
`MapaRedDevice`) se copiaron sin los campos calculados que sí tienen los originales
(`MapDevicePort`, `MapFiber`, `MapDevice`). La pantalla de conexiones (`useMapConnections.js`,
compartida por los dos módulos) depende de ellos:

- sin `element_id`, al picar el segundo puerto la pantalla lo tomaba por el mismo puerto
  (`undefined === undefined`) y solo lo desmarcaba: nunca se armaba la conexión;
- sin `active` en el equipo, el organizador salía sin puertos hasta cambiarle la vista;
- sin `element_id` las conexiones ya guardadas (499 en el site 9688) no se dibujaban;
- además el composable comparaba contra las clases del módulo Mapas (`App\Models\MapFiber`,
  `MapDevicePort`, `MapLayer`), así que las fibras y troncales del Mapa de Red no se reconocían.

**Arreglo (rama `fix/mapared-site-conectar-puertos`):**

- `MapaRedDevicePort`: `selected`, `model_type`, `element_id`, `device_type` (como el original);
  el equipo va oculto en el JSON (`$hidden = ['device']`), la pantalla usa `device_type`.
  No se copiaron `dbs` ni el nombre del cliente: el Mapa de Red ya muestra potencia por su cuenta.
- `MapaRedFiber`: `selected`, `model_type` (sin `disable`/`accept_connection`: nadie los lee y el
  segundo hace una consulta por hilo).
- `MapaRedDevice`: `active = 'in'`.
- `LayerConfig`: liga cada puerto con su equipo (copia sin relaciones) y precarga el equipo de los
  puertos de las conexiones → +2 consultas en total en vez de una por puerto.
- `ConnectionsController::store`: la zona de la OLT solo se escribe si llega; antes, conectar un
  puerto wan/entrada de la OLT (sin zona) daba 500 *después* de guardar la conexión.
- `useMapConnections.js`: `isFiber`/`getConnectionType`/`isOlt` aceptan las clases de ambos módulos;
  nuevo `isLayerModel()` usado por las 4 pantallas de configuración del Mapa de Red.

**Verificación (dev, sin escribir en la base):**

- API del site 9688: puertos con `element_id`/`model_type`/`device_type`, organizador con `active`;
  las 499 conexiones guardadas coinciden con el `element_id` nuevo de sus puertos (0 diferencias).
  Consultas 459 → 461; respuesta 2.0 MB → 2.5 MB.
- Guardado en transacción revertida: organizador ↔ OLT wan (sin zona) 200 sin aviso; PON con zona
  200 y la zona se escribe; 499 conexiones al final.
- Navegador (worktree compilado, servido aparte, usuario temporal ya borrado): 408 puertos de
  organizador, 118 de OLT y 72 de switch con identificador; 158 líneas dibujadas; picar OLT y luego
  organizador manda el POST correcto (interceptado, no se guardó); un puerto PON abre el diálogo de Zona.

**Pendiente / fuera de alcance:** el módulo Mapas viejo (`Geo\ConnectionsController::store`) tiene
el mismo `data['zone']` sobre null; `routesByLayer` hace una consulta de cortes por hilo (432 en este
site), ya venía así.
