═══════════════════════════════════════════════════════
BRIEF DE CLASIFICACIÓN — CHECKLIST MEGAFAMILIA (item Roadmap #19)
Fecha: 2026-07-15
Fuente: docs/megafamilia-auditoria-2026-05-26.md
Generado por: Circuito CC (respuesta a q2 del brief de decisión — "el circuito genera
un brief con el checklist clasificado y Irving solo valida la clasificación")
═══════════════════════════════════════════════════════

── PROPÓSITO ────────────────────────────────────────────
Este documento clasifica cada punto pendiente (⚠️ parcial / ❌ faltante) de la auditoría
de MegaFamilia en dos grupos, para que Irving VALIDE la clasificación (no la ejecución):

  🟢 DESBLOQUEADO   — no depende de la infra Padre-Hijo (#29/#32). Podría desglosarse en
                       items de Hoja de Ruta y ejecutarse ya, sujeto a su propio nivel de
                       riesgo (A/B/C) individual.
  🔴 BLOQUEADO       — depende de que exista el vínculo/perfil padre↔hijo (#29 y/o #32).
                       No tiene sentido ejecutarlo antes porque no hay "hijo" contra qué
                       operar.

Actualización de un supuesto previo: el "motor de servicios contratables" (segundo
bloqueador nombrado en el item #19 original) **ya está resuelto** — existe
`app/Models/Contratable/ContratableService.php` + `ContratableCatalogController.php`
(migración `2026_06_01_000001`), MegaFamilia ya está registrado como contratable. Por
eso este brief solo evalúa contra el bloqueador que sigue vivo: **infra Padre-Hijo**
(items #29 "Flujo de registro del hijo desde la app del padre" — requiere_irving/pending
— y #32 "Panel del Padre para gestionar permisos del hijo" — aprobado_irving/pending,
sin código todavía).

NO se creó ningún item nuevo de Hoja de Ruta todavía. Ese desglose (q1, Opción 2
elegida por Irving) es el PASO SIGUENTE, una vez Irving valide o corrija esta
clasificación.

═══════════════════════════════════════════════════════
── SECCIÓN 1: APP PADRE ─────────────────────────────────
═══════════════════════════════════════════════════════

🟢 1.1 Login/autenticación (email+password) — falta login por # cliente/teléfono
        No depende de Padre-Hijo, es auth del padre contra su propia cuenta ISP.
        ⚠️ Incluye la ALERTA DE SEGURIDAD ya detectada (comparación de password en texto
        plano, sin Hash::check()) — se recomienda tratarla aparte con prioridad alta,
        independiente de este checklist (es un bug de seguridad, no una feature nueva).

🟢 1.2 Dashboard principal — falta % consumo, alerta suspensión, saldo separado
        Datos ISP del propio padre (plan, saldo, servicio). No depende del hijo.

🟢 1.3 Pagos — falta descarga PDF, recordatorios, pago con tarjeta/Mercado Pago
        Módulo de pagos del padre sobre su propia cuenta. Nota: pago con tarjeta toca
        dinero → clasificar su propio nivel de riesgo (probablemente B/C) al desglosar.

🟢 1.4 Facturas — falta PDF, XML/CFDI, solicitar factura fiscal, RFC
        Igual que pagos: cuenta propia del padre, sin relación con el hijo.

🟢 1.5 Consumos y velocidad — pantalla completa faltante
        No depende de Padre-Hijo — depende de OTRA infra (integración real Mikrotik/
        RADIUS, tabla `parental_reports` no es la misma cosa). Desbloqueado respecto a
        Padre-Hijo, pero tiene su propia dependencia técnica que hay que resolver aparte.

🟢 1.6 Tickets de soporte — falta adjuntar foto, ver respuestas técnico, calificar
        Soporte del padre como cliente ISP. Sin relación con el hijo.

🟢 1.7 Notificaciones push (FCM) — completamente faltante
        Es infra TRANSVERSAL requerida por casi todo el resto (incluido Padre-Hijo:
        alertas de solicitud hijo→padre). No depende de Padre-Hijo — al contrario, buena
        parte de Padre-Hijo depende de esto. Es candidato de ALTA prioridad para
        desbloquear en paralelo (la propia auditoría original ya lo marca como
        "PRIORIDAD INMEDIATA").

🔴 1.8 Control parental — activación y planes — falta flujo de activación/T&C/licencia
        Es la puerta de entrada al vínculo padre-hijo (activar el plan es el primer paso
        antes de poder crear/vincular un hijo). Acoplado a #32.

🔴 1.9 Perfiles de hijos — falta foto, nivel escolar, horarios, editar/eliminar
        Gestión directa de perfiles de hijo = exactamente el alcance de #32.

🔴 1.10 Vinculación de dispositivos (QR/código) — pantalla completa faltante
        Es literalmente el alcance de #29 ("Flujo de registro del hijo desde la app del
        padre").

🔴 1.11 Dashboard familiar — falta estado conexión, alertas, badges, datos reales apps
        Vista consolidada sobre hijos ya vinculados — depende de que existan.

🔴 1.12 Control de tiempo — falta límite por app, por categoría, horarios, tiempo extra real
        Reglas sobre un dispositivo/perfil de hijo ya vinculado.

🔴 1.13 Bloqueo de aplicaciones — completamente faltante
        Depende de perfil de hijo + Accessibility Service (riesgo técnico alto aparte).

🔴 1.14 Categorías de aplicaciones — completamente faltante
        Depende de 1.13 (bloqueo de apps) y de perfil de hijo.

🔴 1.15 Solicitudes de desbloqueo (flujo padre) — falta pantalla dedicada en la app
        Responder solicitudes de UN hijo vinculado.

🔴 1.16 Control web/filtros — completamente faltante
        Depende de perfil de hijo (a quién se le filtra) + infra de red aparte.

🔴 1.17 Listas blanca/negra de sitios — completamente faltante
        Depende de 1.16.

🔴 1.18 Reportes de navegación — completamente faltante
        Depende de que exista tráfico filtrado de un hijo vinculado (1.16).

🔴 1.19 Ubicación del hijo — falta mapa visual, permisos GPS
        Ubicación de UN dispositivo de hijo ya vinculado.

🔴 1.20 Zonas seguras (geocercas) en app padre — completamente faltante
        El admin YA tiene `MegaFamiliaGeofences.vue` + tabla `parental_geofences`; falta
        exponerlo en la app móvil sobre un hijo/dispositivo vinculado. Depende del vínculo.

🔴 1.21 Botón de ayuda del hijo (SOS) — completamente faltante
        Vive en la app HIJO, solo tiene sentido con un hijo vinculado.

🟡 1.22 Sistema de tareas (flujo padre) — MIXTO
        Crear/aprobar tareas YA funciona desde el panel ADMIN WEB (no depende de la app
        móvil ni del vínculo formal app-a-app). Lo que falta es "crear tareas desde la
        app MÓVIL del padre" y "aprobar con foto desde el móvil" — eso sí depende de
        perfil de hijo vinculado. → la mitad admin-web ya está DESBLOQUEADA (y hecha);
        la mitad app-móvil es 🔴 BLOQUEADA.

🔴 1.23 Gamificación — falta medallas dinámicas, niveles, ranking, premios canjeables
        Puntos/rachas por hijo específico — depende de perfil de hijo vinculado.

═══════════════════════════════════════════════════════
── SECCIÓN 2: APP HIJO ──────────────────────────────────
═══════════════════════════════════════════════════════
Las 5 funciones de la app hijo son, por definición, posteriores al vínculo con un padre
— TODAS 🔴 BLOQUEADAS por #29/#32:

🔴 2.1 Vinculación por QR o código (= #29 directamente)
🔴 2.2 Pantalla principal del hijo (dashboard hijo)
🔴 2.3 Pantalla de bloqueo
🔴 2.4 Solicitudes (hijo → padre)
🔴 2.5 Protección de desinstalación

═══════════════════════════════════════════════════════
── SECCIÓN 3: MÓDULO ADMIN MEGAISP ──────────────────────
═══════════════════════════════════════════════════════
✅ Ya 100% completo (3.1–3.5) + 11 extras (3.6). No requiere clasificación — nada
pendiente aquí.

═══════════════════════════════════════════════════════
── SECCIÓN 4: API CENTRAL — ENDPOINTS FALTANTES ─────────
═══════════════════════════════════════════════════════

🟢 POST /auth/otp/send, /auth/otp/verify, login por # cliente/teléfono
        Auth del padre. Integración externa (Twilio/WhatsApp Business/mailer) — nivel de
        riesgo propio (B) al desglosar, pero NO depende de Padre-Hijo.

🟢 GET /facturas/{id}/pdf, /facturas/{id}/xml, POST /facturas/request, PUT /account/fiscal
        Cuenta propia del padre.

🟢 GET /consumo/stats, /consumo/devices, /consumo/speed
        Depende de integración Mikrotik/RADIUS real, no de Padre-Hijo.

🟢 POST /tickets/{id}/attachment, /tickets/{id}/rate
        Soporte del padre.

🟢 GET /notifications, POST /notifications/token
        Infra FCM transversal (ver 1.7).

🔴 GET/POST /profiles/{id}/apps, /profiles/{id}/apps/{pkg}, /profiles/{id}/web-rules,
    /profiles/{id}/categories, /profiles/{id}/schedule, /profiles/{id}/time-extra
        Todos operan sobre UN perfil de hijo ya vinculado.

🔴 GET/POST /geofences
        Zonas seguras de un hijo/dispositivo vinculado.

🔴 POST /profiles/{id}/sos
        App hijo, requiere vínculo.

🔴 POST /profiles/{id}/tasks (crear desde app padre), PUT /tasks/{id}/validate (con foto)
        Depende de perfil de hijo vinculado (la vía admin-web ya existe, ver 1.22).

═══════════════════════════════════════════════════════
── SECCIÓN 5: BASE DE DATOS ─────────────────────────────
═══════════════════════════════════════════════════════

🟡 Tabla `parental_reports` (historial de navegación) — FALTANTE
        Crearla es una migración aditiva trivial y NO depende de Padre-Hijo en sí misma.
        Pero es inútil aislada: solo tiene datos que guardar una vez exista el control
        web (1.16, que SÍ es 🔴). Clasificación: técnicamente desbloqueada, de bajo valor
        ejecutarla sola — mejor agruparla con el filtrado web cuando ese bloque avance.

═══════════════════════════════════════════════════════
── SECCIÓN 6: INFRAESTRUCTURA Y PERMISOS ────────────────
═══════════════════════════════════════════════════════

🟢 Firebase (google-services.json) + firebase_messaging/firebase_core
        Infra transversal (ver 1.7). No depende de Padre-Hijo.

🟢 CAMERA, READ_MEDIA_IMAGES (para fotos de tickets / comprobantes de pago)
        Ya se usan en pagos; falta declarar/extender a tickets — no depende del hijo.

🔴 CAMERA/mobile_scanner para vinculación QR, ACCESS_FINE_LOCATION, geolocator,
    google_maps_flutter, PACKAGE_USAGE_STATS, BIND_ACCESSIBILITY_SERVICE,
    BIND_DEVICE_ADMIN, RECEIVE_BOOT_COMPLETED, VPN/DNS local
        Todos existen específicamente para vincular/rastrear/controlar al HIJO — sin
        vínculo no hay nada que rastrear ni controlar. 🔴 Bloqueados.

🟢 OTP SMS/WhatsApp/email (Twilio u otro)
        Auth del padre, ver 1.1/4.

═══════════════════════════════════════════════════════
── RESUMEN PARA VALIDACIÓN DE IRVING ────────────────────
═══════════════════════════════════════════════════════

🟢 DESBLOQUEADO (no depende de #29/#32) — candidatos a desglosar en items ya:
   1.1 login, 1.2 dashboard, 1.3 pagos, 1.4 facturas, 1.5 consumos, 1.6 tickets,
   1.7 push FCM (transversal, PRIORITARIO), 1.22 (mitad admin-web, YA HECHA),
   API: otp/facturas-pdf-xml/consumo/tickets-attach-rate/notifications,
   Infra: Firebase, CAMERA/READ_MEDIA_IMAGES, OTP.
   + seguridad aparte: fix Hash::check() en login (bug ya detectado, prioridad alta).

🔴 BLOQUEADO por infra Padre-Hijo (#29/#32) — no ejecutar hasta que avancen:
   1.8 a 1.21, 1.23 (app padre — control sobre el hijo), 2.1 a 2.5 (toda la app hijo),
   API de profiles/apps/web-rules/categories/schedule/time-extra/geofences/sos/
   tasks-móvil, Infra de vinculación/rastreo/control del dispositivo hijo.

🟡 Casos mixtos/de bajo valor aislado:
   1.22 (crear tareas desde MÓVIL del padre queda bloqueado; la vía admin-web ya
   funciona), tabla `parental_reports` (aditiva pero sin uso hasta 1.16).

── SIGUIENTE PASO (no ejecutado en este brief) ──────────
Con esta clasificación validada por Irving, el paso siguiente (q1, Opción 2 elegida)
es desglosar el bloque 🟢 en items independientes de la Hoja de Ruta — cada uno con su
propio nivel_riesgo individual (varios tocan integraciones externas o dinero → B/C,
no automáticos). La prioridad relativa frente a los bloqueadores (Padre-Hijo vs.
MegaFamilia) la fija Irving directamente en la Hoja de Ruta (q3, Opción 3 elegida) —
no es algo que este brief decida.

═══════════════════════════════════════════════════════
Generado por Circuito CC — item Roadmap #19 — 2026-07-15
═══════════════════════════════════════════════════════
