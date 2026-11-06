## 2026-10-07 — Conexión automática Administradores → Talento

Pedido de Irving, tras verificar (sesión previa) que crear un usuario en Administración nunca lo
llevaba a Talento, y que el único puente (`talento:sync-colaboradores`) no corría solo ni cubría
a cualquier rol — solo técnicos/Mostrador, y a mano.

### Qué se construyó

- `TalentoColaboradorSyncService::sync(User)` nuevo — única fuente de verdad de "¿este usuario
  debe tener fila en `talento_colaboradores`, con qué department?", extraída 1:1 de la lógica que
  antes vivía inline en `SyncColaboradoresCommand` (mismas guardas: roles de sistema, login de
  prueba protegido, respeto a soft-deletes).
- `SyncColaboradoresCommand` refactorizado para usar este mismo servicio en su Fase 1 (alta/
  update) — evita que el comando periódico y el gancho en vivo diverjan con el tiempo. **La Fase
  2 (inactivar colaboradores sin rol de puesto) no se tocó**, sigue siendo manual.
- Gancho en `UserController::store()`/`update()`, justo después de `DB::commit()` y antes del
  `return` de éxito: llama al servicio, best-effort (try/catch propio, nunca bloquea el alta si
  falla). Crear la fila dispara solo, vía el `TalentoColaboradorObserver` ya existente, la
  sincronización de permisos/documentos/extensión SIP — nada de eso se duplicó.
- Es **puramente aditivo**: nunca desactiva a nadie automáticamente.

### Verificado

Transacción+rollback con datos sintéticos: usuario con rol TECNICO → crea fila + dispara permiso
`portal.colaborador` (cascada del Observer, confirmada real); usuario con rol
`super-administrator` → no crea nada; usuario con rol `Vendedor` (no es rol de puesto) → no crea
nada; correr `sync()` dos veces sobre el mismo usuario → idempotente (segunda vez: "sin cambios").
Prueba end-to-end simulando el flujo real del controller (crear usuario → `sync()` → confirmar que
aparece en la query real que usa `TalentoColaboradores.vue`) → aparece.

### ⚠️ Hallazgo real al correr el backfill (decisión de Irving: sí sincronizar a los ya existentes)

El backfill en sí (Fase 1) no sorprendió: los 5 técnicos/Mostrador reales en dev ya tenían su fila
en Talento de antes (0 altas nuevas, 1 actualizado). Pero el mismo comando trae una **Fase 2** ya
existente (no escrita ni tocada en este cambio) que **desactiva** cualquier colaborador activo en
Talento cuyo rol actual ya no sea de puesto — y como el comando **nunca se había corrido antes**
(no está en el cron, nadie lo había ejecutado a mano), correrlo por primera vez aplicó esa regla
en retroactivo: **desactivó a 7 personas reales** (Vendedores, no técnicos) que tenían su ficha
activa en Talento — incluido **Brandon** (user 4429), colaborador real ya validado en producción
usando el Portal de Colaborador en una sesión anterior (ver sección "Fase 0" de este mismo
CLAUDE.md). Esto no estaba cubierto por lo que se le había explicado a Irving al pedir su
aprobación del backfill (se describió solo el lado de altas, no el de la Fase 2).

**Resuelto en el momento:** se reportó de inmediato, con tabla de los 7 afectados + su estado de
cuenta real, sin revertir unilateralmente (no estaba claro si la regla de Fase 2 es la correcta o
incompleta). Irving pidió reactivarlos mientras decide la regla — hecho, verificado que el permiso
`portal.colaborador` directo de Brandon se restauró solo (vía el mismo Observer, al volver
`status` a `active`).

### Pendiente

- **Decisión de Irving**: ¿la Fase 2 (desactivar a cualquiera sin rol de puesto) es la regla
  correcta, o debería excluir a Vendedores con una fila de Talento ya existente/legítima? Hasta
  que se decida, **no volver a correr `talento:sync-colaboradores` completo** sin tener esto claro
  — Fase 1 (el backfill) es segura de repetir, Fase 2 no.
- Confirmar en el navegador que crear un usuario Técnico desde Administración lo muestra en el
  listado de Talento sin pasos manuales (lo verificado hasta ahora es equivalente end-to-end por
  tinker, no un clic real en la UI).
