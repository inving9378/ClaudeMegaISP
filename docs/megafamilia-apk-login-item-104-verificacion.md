# Verificación del item #104 — Probar APK MegaFamilia end-to-end (primer login post-fix)

El item pedía validar que el fix de login (auto-create de fila `users` + `PasswordService`,
commit `90e99032`, item #251 F2) funciona end-to-end con una cuenta de cliente real: login →
dashboard → servicio → facturas → tickets.

**Reinterpretación aprobada por Irving** (respuestas registradas en `preguntas` del item,
2026-08-28 15:12): el flujo original pedía probar en un teléfono físico contra PROD, lo cual
choca con el candado duro de este circuito (solo DEV, nunca prod). Irving eligió:

- **q1** — cuenta de prueba/interna designada por Irving (no cliente real de producción).
- **q2** — backend de **DEV**, no PROD.
- **q3** — checklist **mínimo del fix**: login, carga de perfil/plan, ver estado de servicio,
  cerrar sesión (no el checklist amplio con pagos/tickets/notificaciones).
- **q4** — reporte dual (técnico + coloquial) con tabla de pasos.

La app móvil (React Native, `megafamilia-rn`) consume `app/Modules/Addons/MegaFamilia/routes.php`
→ `api/megafamilia/*`. Como no hay emulador Android disponible en esta caja on-box, el checklist
se ejecutó **contra los mismos endpoints REST que consume la APK**, con `curl` desde este
worktree hacia el servidor de DEV (`http://192.168.105.11`) — valida exactamente la misma
lógica de servidor que ejercita el login físico, sin necesitar el dispositivo.

## Cuenta usada

`client_main_information.id=3` (`client_id=27`, `user="27"`, nombre "PRUEBAS 2") — cliente de
prueba **ya existente en la BD de DEV** (no creado para esta verificación), sin fila `users`
correspondiente antes de esta prueba → escenario real de **primer login** (dispara el auto-create,
que es justo lo que pide el item). Tiene `internet_service` vinculado (plan `Basico_50MB`), sin
`fecha_suspension` → estado `activo`, así que perfil/servicio devuelven datos reales, no el
placeholder de "sin servicio".

## Checklist ejecutado (2026-08-28)

| # | Paso | Esperado | Obtenido |
|---|------|----------|----------|
| 1 | `POST /api/megafamilia/auth/login` con `email="27"` + password plano de CMI | Sin fila `users` previa → auto-create (`autoCreateUserFromCmi`) + token Sanctum | `200 success:true`, `user.id=4860` (nuevo), `role:"cliente"`, `client.id=27` |
| 2 | Verificar fila `users` creada | `login_user="27"`, `client_id="27"`, password **bcrypt** (no base64 legacy) | `login_user=27 client_id=27`, `password_hash_prefix=$2y$` → confirma `PasswordService::make()` |
| 3 | `GET /api/megafamilia/profile` (Bearer token) | Nombre/teléfono/dirección desde CMI | `200`, `name:"PRUEBAS 2 . ."`, `phone:"5540679039"`, `address` con la calle real del cliente |
| 4 | `GET /api/megafamilia/account` (plan) | Plan + estado del contrato | `200`, `plan_name:"Basico_50MB"`, `estado:"activo"`, `contract_number:"MGI-000027"` |
| 5 | `GET /api/megafamilia/servicio` | Mismo resumen que ve el dashboard de la app | `200`, `plan_name:"Basico_50MB"`, `estado:"activo"`, `saldo:0`, `alerta_suspension:false` |
| 6 | Re-login (2do login, misma cuenta) | Debe reusar la fila `users` ya creada, NO duplicarla | `200 success:true`, `user.id=4860` (mismo id que el paso 1) → sin duplicado |
| 7 | Cerrar sesión (revocar tokens Sanctum de la cuenta) | Simula "logout" — la APK no tiene endpoint `/auth/logout` dedicado, cierra sesión descartando el token client-side; se valida revocando server-side | `tokens_revocados=2` |
| 8 | `GET /api/megafamilia/profile` con el token ya revocado | Debe rechazar con `401` | `http_code=401` ✅ |

**Los 8 pasos pasaron.** El fix (`ClientPasswordService` con prioridad a `password_hash` +
upgrade-on-login + auto-create de `users` en el primer login) funciona correctamente contra un
cliente real de la BD de DEV.

## Fuera de alcance de esta vuelta (documentado, no bloqueante)

- **Facturas/tickets** (parte del checklist "amplio", q3 explícitamente NO eligió ese alcance) —
  no se probaron aquí.
- **Prueba física en el APK real** (pantalla por pantalla, capturas del dispositivo) — sigue
  pendiente de que Irving la haga en su propio teléfono si quiere el smoke visual completo
  (opción A del brief de des-trabe de Opus, 2026-08-26); esta vuelta cubre la capa de servidor,
  que es donde vivía el bug original.
- No se creó ni se borró ningún dato de cliente real: la cuenta usada (`client_id=27`,
  "PRUEBAS 2") ya era un registro de prueba preexistente en DEV: la fila `users` auto-creada
  (`id=4860`) queda igual que quedaría con cualquier cliente real que hiciera su primer login —
  es el comportamiento correcto del sistema, no un residuo de la prueba.
