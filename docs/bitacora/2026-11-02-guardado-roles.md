## 2026-11-02 — Administración → Roles: guardar ya no borra lo que la ventana no mostró

Pedido de David: preparar en una rama el blindaje del guardado de roles, para que Irving lo revise
(es código de permisos: decide él). **Rama `fix/guardado-roles-sin-borrar`, sin mezclar a main.**

**El problema.** La ventana de permisos de un rol mandaba solo las casillas marcadas y el servidor
dejaba el rol exactamente así: quitaba todo lo que no viniera. Los permisos de Talento (y de todo
módulo nuevo) no tienen pestaña propia: salen en «Otros / Sin categorizar», que la ventana carga
aparte. Si esa carga fallaba, la pestaña no aparecía y no avisaba; guardar así, o antes de que
cargara, le quitaba al rol todos esos permisos de golpe y sin dejar rastro. Es lo que muy
probablemente le pasó al rol Mostrador la noche del 28 al 29-oct (perdió sus 17 permisos de
Talento, ver `2026-10-30-talento-pendientes.md`). Además, reabrir el mismo rol no volvía a pedir
sus permisos (solo al cambiar de rol), y si los pedía y fallaban, las casillas se quedaban como las
del rol anterior.

**Servidor** (`PermissionController::update`):
- La ventana manda también `shown` (los permisos que mostró) y `loaded` (los que tenía el rol al
  abrirla). Sin ellos responde 422 «Vuelve a cargar la página…» (una pestaña abierta con la
  versión anterior no puede guardar).
- Solo se quita lo que se mostró y quedó desmarcado; el alcance Propios/Todos solo se toca en los
  permisos mostrados.
- Si el rol cambió desde que se abrió la ventana (`loaded` distinto de lo actual), no guarda nada
  y responde 409 con «Los permisos de este rol cambiaron… vuelve a abrirla».
- Cada guardado con cambios queda en `activity_log` (`log_name = permisos`): quién, qué rol y qué
  se agregó y quitó.

**Ventana** (`PermissionAssignmentModal.vue`):
- Pide los permisos del rol cada vez que se abre; «Guardar» dice «Cargando…» y está deshabilitado
  hasta tenerlos (y no se puede mandar dos veces).
- Si la pestaña «Otros» no carga, avisa arriba que sus permisos se quedan como están, y vuelve a
  intentarlo al reabrir.
- Antes de quitar permisos muestra cuáles (nombre y clave) y pide «Sí, quitarlos» / «Cancelar».
- Los errores del servidor se muestran con su mensaje (antes, siempre «Hubo un error al actulizar»).

**Probado.**
- `tests/Feature/Core/Security/GuardadoPermisosRolTest.php` (6 pruebas, DatabaseTransactions sobre
  `megaisp_test`): lo no mostrado no se quita; agregar y quitar normal; rol cambiado → 409 sin
  cambios; sin `shown` → 422 sin cambios; registro en activity_log con quién y qué; el alcance de un
  permiso no mostrado se conserva. Con el controlador anterior fallan 5 de 6 (la de agregar y
  quitar normal pasa en los dos).
- En el navegador, vista previa de la rama (`php artisan serve` desde el worktree, `npm run prod`)
  como `prueba_admin`, con un rol temporal `zz_prueba_guardado_roles` (2 permisos de Dashboard y 2
  de Talento, sin usuarios): «Cargando…» al abrir; desmarcar uno → «Se va a quitar 1 permiso» con
  su nombre; Cancelar no cambia nada; confirmar lo quita y queda el registro; con la pestaña «Otros»
  fallando (simulada) sale el aviso y guardar no toca los de Talento; con una lista vieja del rol
  (simulada) → «cambiaron desde que abriste la ventana», sin cambios. Sin errores en la consola.
- Al terminar se borraron el rol temporal, sus permisos, su registro y la sesión de prueba.

**Mezclado a main el mismo 2-nov** a pedido de David («arréglalo ya»), con `npm run prod` en el
checkout principal. Irving debe saberlo antes de que vaya a producción (es código de permisos), aunque
solo protege: no da ni quita acceso a nadie.
La ruta de permisos por usuario (`update-permission-for-user`) sigue apagada, no se tocó.

Quién: sesión de David (Claude Code).
