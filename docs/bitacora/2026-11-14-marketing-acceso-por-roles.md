## 2026-11-14 — Marketing: acceso por rol, no por nombre

Pedido de Irving en sesión: «henry dijo que el acceso es solo a henry y de irving pero yo siempre entro como
superadministrador así que me gustaría que esa regla no la pongas por nombres sino por roles, y pueden tener el
permiso para verla completa, superadministrador, administrador, desarrollador y mostrador». A la pregunta de alcance:
«Todo, igual que super-admin»; y que vaya en la V1.91.

### Qué cambia
- **Entran a Marketing, con todo:** super-administrator, Administrador, DESARROLLADOR y Mostrador
  (`config/marketing.php` → `roles_con_acceso`). No necesitan estar en la lista del `.env` ni tener fila en el equipo.
- `ResolverEmpresaActiva`: si el usuario (activo) tiene uno de esos roles, entra como admin de Meganet. Si no, sigue la
  regla de la Fase 1 (marca + fila admin en Meganet), que queda solo como camino OPCIONAL para alguien sin esos roles.
- `EquipoMarketing::puedeEntrar()` (menú lateral) usa la misma regla.
- Migración nueva `2026_11_14_120000_da_permisos_de_marketing_a_roles_con_acceso`: da a esos 4 ROLES todos los permisos
  de Marketing que existan (`EquipoMarketing::permisos()`, nunca `manage-integrations`). Aditiva (`givePermissionTo`),
  idempotente; anota en `activity_log` (event `permisos_por_rol`) qué par rol→permiso dio y `down()` quita solo eso.
- Mensaje de «sin acceso»: ahora nombra los 4 roles.
- La migración `2026_11_13_100200` solo cambia su aviso cuando la lista está vacía (ya no dice «nadie entra»).
- `MARKETING_EQUIPO_LOGINS` ya **no es obligatoria** en prod (docs de entrega y pasos de producción actualizados).

### Pruebas
- Nueva `tests/Feature/Marketing/AccesoPorRolesTest.php`: los 4 roles entran sin fila; entran sin la marca; Vendedor
  sin fila → 403; usuario inactivo con rol → 401; la migración da los permisos a los 4 roles, no toca Vendedor, es
  idempotente y su `down()` quita solo lo que dio.
- `MarketingTestCase`: las pruebas de Fases 0–2 que usan los escenarios de empresas/equipo corren con el acceso por rol
  apagado (prueban el camino opcional por equipo y la separación entre empresas; sus usuarios llevan
  super-administrator solo para tener permisos).
- Reescritas a la regla nueva: `AccesoNegadoPorDefectoTest` y `AccesoSinRutaPermisoBackendTest` (super-administrador y
  DESARROLLADOR sin fila ahora entran). Texto del mensaje actualizado en `MatrizRolesTest` y `CapaEmpresaMiddlewareTest`.

### Para Henry
Su rama `feat/marketing-360-equipo` (Fase 3 sin commit) toca los mismos archivos de acceso: al traer `main` debe
quedarse con esta regla por rol.
