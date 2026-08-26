═══════════════════════════════════════════════════════
ACTUALIZACIÓN DEL CHECKLIST MEGAFAMILIA (item Roadmap #19)
Fecha: 2026-08-26 · Fuente: docs/megafamilia-checklist-clasificado-2026-07-15.md
Generado por: Circuito CC (wt-5)
═══════════════════════════════════════════════════════

Este documento NO reemplaza el brief del 2026-07-15 — lo actualiza con lo que cambió desde
entonces. Motivo: el item #19 fue aprobado para ejecutarse ahora, pero investigar mostró que
su alcance sigue siendo demasiado grande y cross-repo para una sola vuelta del circuito.

── QUÉ CAMBIÓ DESDE EL BRIEF DE JULIO ────────────────────

1. **#29 "Flujo de registro del hijo desde la app del padre" → COMPLETADO** (commit `a87004aa`,
   integrado 2026-08-26 el mismo día de esta vuelta). `ApiController.php` ahora tiene
   `POST /profiles/{id}/invite` (genera `ParentalDevice` con `link_token`, expira 15 min) y
   `POST /devices/link` (público, consume el token, emite Sanctum sobre el padre). Esto
   desbloquea PARCIALMENTE la sección 🔴 del checklist de julio — el vínculo padre↔hijo YA
   existe técnicamente.

2. **#32 "Panel del Padre para gestionar permisos del hijo" sigue en `requiere_irving`, SIN
   código.** La mayoría de las filas 1.8–1.23 y 2.1–2.5 del checklist de julio siguen
   bloqueadas por ESTE ítem específico (no por #29, que ya cerró). No es algo que yo pueda
   destrabar — es una decisión de diseño pendiente de Irving.

3. **Motor de servicios contratables — confirmado que existe** (`app/Models/Contratable/*`,
   `ContratableCatalogController.php:25` ya registra `megafamilia`), tal como ya lo había
   detectado el brief de julio. Sigue sin conectarse a la ACTIVACIÓN real: `MarketplaceController
   ::activarMegafamilia` → `ParentalAccountActivationService::activate()` crea un
   `ParentalAccount` con plan Demo gratuito, sin crear `ClientContratableSubscription` ni tocar
   facturación. Cobrar de verdad por MegaFamilia sigue pendiente de decisión de negocio (dinero
   → frontera dura, no es un cambio que se haga sin decisión explícita).

4. **Descubrimiento nuevo — la app móvil vive en un repo separado fuera de este circuito:**
   `/var/www/megafamilia-rn` (React Native, multi-rol: cliente/conductor/embajador/hijo/técnico),
   con solo 3 commits (`df81f2e` inicial, `9b6051d` reestructura, `715c98c` tickets). El login y
   los tickets (checklist 1.1 y 1.6) YA están cableados ahí contra la API existente de megaisp.
   Este repo NO es parte del worktree/mandato de la circuito on-box de megaisp — su avance no lo
   puede reportar ni ejecutar este ejecutor.

5. **La alerta de seguridad de julio (login en texto plano sin `Hash::check()`) ya no aplica**:
   `ApiController.php:135` usa `PasswordService::check()` (bcrypt-aware) para el login de la app.
   Cerrada por la migración general de auth a bcrypt (ver memoria `project_auth_bcrypt_migration`).

── HUECOS VERIFICADOS QUE SIGUEN SIN CONSTRUIRSE (backend megaisp, 🟢 no dependen de #32) ──

Verificado por grep en este repo, no supuesto:
- **OTP de login**: solo existe el toggle `require_otp_on_login` (`ConfiguracionController.php:107,134`),
  sin flujo real detrás (sin endpoints `/auth/otp/send`/`/verify`, sin proveedor SMS/WhatsApp).
- **Facturas PDF/XML (CFDI)**: cero endpoints en Portal Cliente (`grep` sobre
  `app/Modules/Addons/PortalCliente/Controllers/` no encontró nada). Ya anotado en CLAUDE.md
  como "Portal: CFDI timbrado — pendiente".
- **Push notifications (FCM)**: no existe tabla ni endpoint de `device_token`/`notifications/token`
  fuera de Talento (que tiene su propio FCM interno, de otro dominio).

Cada uno de estos 3 huecos es multi-día y toca una integración externa o dinero/compliance →
se registraron como sub-items (#625 OTP, #626 CFDI, #627 push FCM) colgando de #19 en vez de
picarlos a medias en esta vuelta.

── CONCLUSIÓN ────────────────────────────────────────────
El item #19 sigue siendo un PARAGUAS: no se cierra hoy. Lo que sí avanzó esta vuelta es la
descomposición que el brief de julio dejó pendiente como "siguiente paso" (nunca ejecutada).
Los bloqueadores nombrados en el título original ("infra Padre-Hijo" y "motor de servicios")
ya no son binarios — están parcialmente resueltos — pero el trabajo restante real vive en:
(a) una decisión de Irving sobre #32, (b) un repo separado (megafamilia-rn) fuera de este
circuito, y (c) 3 features backend nuevas con su propio nivel de riesgo (#625/#626/#627).
