## 2026-11-28 15:30 — Campañas/Blaster: configuración de la troncal rehecha y grupo de timbrado con búsqueda y alta ahí mismo

Pedido de Irving: el formulario de Configuración de Campañas/Blaster «bien bonito como la creación de campañas»; el
desplegable del grupo de timbrado animado, con búsqueda y con «crear grupo de timbrado» desde ahí; crearlo en un
diálogo grande y fácil, y que al crearlo quede elegido. Rama `campanas/config-voip`.

### Pantalla (`CobranzaVoipConfig.vue`, reescrita con el diseño de Campañas)
- Cabecera «Troncal de Campañas» con el estado (Activa / Sin aplicar / Sin configurar / Con error).
- Secciones:
  - Conexión con Servnet (servidor, puerto, usuario, contraseña con ver/ocultar y «Sin cambios», From User y
    From Domain opcionales);
  - Cómo aparece al llamar;
  - Llamadas que entran.
- El grupo elegido se explica en palabras: «2 extensiones · Una por una, en orden · 15 s». Sin elegir dice cuál
  es «el primer grupo activo», y sin grupos avisa con «Crear uno».
- Barra de guardar fija abajo con «Cambios sin guardar» / «Todo guardado», «Probar conexión» y «Guardar y aplicar».
  Al salir con cambios, el navegador pregunta.
- Lado:
  - el registro con Servnet (Sin probar / Registrada / Sin registro);
  - En Asterisk, estado e IPs;
  - el detalle técnico plegado;
  - los avisos de guardar.
- Lo que se manda al guardar no cambió (sin contraseña nueva se conserva la guardada).

### Grupo de timbrado
- `CampanasSelector` (el de zona y caja) ahora acepta:
  - un detalle por opción (segunda línea);
  - una acción al final, «Crear grupo de timbrado», que con búsqueda dice «Crear grupo de timbrado «lo
    buscado»» y abre el alta con ese nombre.
  - La acción solo sale con `voip.grupos.create` y `voip.grupos.view`, que pasa la vista.
- `CampanasGrupoNuevo.vue` (nuevo), en el diálogo a toda la pantalla:
  - nombre;
  - cómo suena (Todas a la vez / Una por una, en orden / Una por una, rotando);
  - cuánto suena (5 s a 2 min);
  - si nadie contesta (Colgar / Buzón de voz / Volver a sonar);
  - cola con espera;
  - quién contesta: extensiones con búsqueda, «Elegir las N que se ven», si está conectada y su lugar en el
    orden (se eligen en el orden en que suenan).
- Usa el alta de VoIP (`POST /voip/grupos-timbrado`), con su validación, cola y plan de marcado; no hay alta
  nueva. Nombre repetido: «Ya hay un grupo con ese nombre».
- Al crearlo se recarga la lista, queda elegido, se ilumina con «Nuevo» y la barra marca «Cambios sin guardar»:
  falta «Guardar y aplicar» para que las llamadas suenen ahí.
- Backend: `VoipConfiguracionController::gruposTimbrado()` devuelve también estrategia, cola, segundos y cuántas
  extensiones (sigue en orden de id: «el primer grupo activo» es el del plan de marcado).

### Pruebas
- Página de prueba con datos inventados (webpack de dev + playwright), a 1440 y 390 px, en claro y oscuro:
  - buscar grupo; crear desde la búsqueda (nombre prellenado, en orden, 3 extensiones);
  - el POST lleva los miembros en el orden elegido; queda elegido y luego se guarda con ese grupo;
  - nombre repetido, Esc cierra el diálogo, sin permiso no sale la acción;
  - troncal nueva sin grupos, probar conexión, cambios sin guardar.
- La lista de grupos contra la base de dev (solo lectura): trae estrategia, cola, segundos y extensiones.
- Zona y caja del alta de campañas con el selector cambiado: buscar, caja y teclado, igual que antes.
