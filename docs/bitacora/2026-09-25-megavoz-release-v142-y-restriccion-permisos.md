## 2026-09-25 09:50 — MegaVoz: restricción de permisos, versión V1.42 publicada, runbook de producción

### Restricción de visibilidad (a pedido de David)

Los permisos de acción de VoIP (crear/editar/borrar/provisionar/probar troncales y
extensiones) ya estaban correctamente acotados a `super-administrator`/`DESARROLLADOR`
desde que se creó el módulo. El hueco: los `.view` (troncales/extensiones/grupos/
bot IA) y `voip.view` (el que abre el módulo en el sidebar) se habían quedado
repartidos ampliamente — Mostrador, Vendedor, TECNICO, Almacen, Administrador, etc.
podían entrar a VER (aunque no editar) extensiones, troncales y leads del bot IA.

Migración `2026_09_25_100000_restringe_voip_admin_a_super_admin_y_desarrollador.php`
(idempotente, portable) revoca esos 5 permisos de todo rol que no sea
`super-administrator`/`DESARROLLADOR`. Verificado: Irving (id=8) y David siguen con
acceso completo; un usuario de prueba sin esos roles queda sin `voip.troncales.view`.

El mini-teléfono (`MiTelefonoController`) **no usa ningún permiso `voip.*`** — resuelve
siempre por `auth()->id()` — así que cualquier staff con extensión asignada sigue
pudiendo llamar/contestar sin ver nada de la configuración interna.

### "Paquete instalable" — ya lo es, sin cambio de arquitectura

Investigado antes de tocar nada: VoIP **ya está construido sobre el sistema genérico
de addons instalables** (`ModuleLifecycleService`, tabla `module_registry`, pantalla
`/admin/modules` con Instalar/Actualizar/Desinstalar por módulo) — registrado ahí
desde el 2026-06-09. No hacía falta empaquetar nada nuevo: en producción se activa o
desactiva desde esa misma pantalla, como cualquier otro addon, sin que el despliegue
del código lo fuerce a estar activo.

Se subió `module.json` de 1.0.0 → **1.1.0** para reflejar el trabajo de esta sesión
(mini-teléfono con audio funcionando, TURN propio) de cara a esa pantalla de gestión.

### Versión publicada: V1.42-25.09.2026

Emitida vía el flujo real del sistema (`ReleaseController::store`, el mismo que usa el
botón "+ Nueva versión"), no a mano. Pipeline completo: respaldo de BD, chequeo de
secretos, `git_staging_gate`, commit, tag anotado, push a GitHub, GitHub Release.
`remote_deploy` se omitió (no hay `DEPLOY_REMOTE_URL` configurado en dev — el
mecanismo real de prod es "Buscar actualizaciones", documentado en el runbook).

Nota operativa: el primer intento falló en `git_staging_gate` por 2 archivos ajenos
sin commitear en el checkout compartido (`docs/maquetas/panorama-arbol.html` +
su bitácora, del sistema de adjuntos del roadmap, dejados a propósito sin commitear
por otro proceso). Se apartaron con `git stash` (reversible), se reintentó el pipeline,//
y se restauraron intactos al terminar — no se tocó ni se perdió nada de ese trabajo
ajeno.

### Runbook de producción

`deploy/README-megavoz-prod-turn-setup.md` — guía completa para Irving/David (dev y
prod no se alcanzan entre sí): instalar coturn con datos reales de prod, agregar el
`.env`, el bloque de dialplan gemelo WebRTC, activar el addon si no estaba instalado,
y checklist de verificación con dos redes distintas.

### Commits

- `4d7c8f68` — restricción de permisos VoIP a super-administrator/DESARROLLADOR
- `1d4bccac` — bump del addon a 1.1.0
- tag `V1.42-25.09.2026` — release publicado (GitHub)
- `bf63fa35` — runbook de producción
