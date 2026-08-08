# Candidatos a preferencia — pendientes de revisión batch (item #351)

> Loop de aprendizaje del [perfil de decisiones de Irving](perfil-decisiones-irving.md) (Opción 2,
> semi-automática con revisión batch — recomendada en el brief C del item #351 y aprobada por Irving
> al soltar el item al pool). Cada vez que Irving decide un item de su bandeja (`POST
> /api/roadmap/circuito/decidir`), el sistema APPEND-EA aquí un candidato crudo — **NO** lo inlinea
> solo en `perfil-decisiones-irving.md`. Irving revisa esta lista en lote (p.ej. semanal) y decide
> a mano qué mover al perfil vivo.

## Reglas (no negociables)
1. **Solo Irving edita `perfil-decisiones-irving.md`.** Este archivo es un buzón de candidatos, nunca
   se auto-inlinea al perfil.
2. **Frontera dura (dinero/seguridad/prod/negocio) NUNCA se infiere automáticamente** — ver siempre
   ese archivo directamente para esos casos; aquí solo se registra el crudo trazable.
3. **Umbral sugerido:** antes de promover un candidato a preferencia real, buscar ≥3 decisiones
   consistentes sobre el mismo patrón (mismo módulo/categoría/tipo de riesgo). 1-2 decisiones sueltas
   son ruido, no regla.
4. **Trazable:** cada candidato cita el item `#N` de origen (para poder ir a leer el contexto completo).
5. **Versionado en git** (este archivo vive en el repo) → cualquier promoción o poda es reversible.

## Candidatos capturados

<!-- CIRCUITO:APPEND-ANCHOR — no editar esta línea, el capturador automático agrega debajo -->
### #547 — APROBÓ — 2026-08-08T10:24:16-06:00
- Título: Fix: items "terminados, esperan merge" que no avanzan (auto-merge al decidir)
- Módulo: Sin clasificar · Nivel de riesgo: C
- Por: irving:admin

### #545 — APROBÓ — 2026-08-08T10:03:35-06:00
- Título: Memoria de preferencias de Irving (el circuito decide "como Irving")
- Módulo: Sin clasificar · Nivel de riesgo: C
- Por: irving:admin

### #544 — APROBÓ — 2026-08-08T10:03:28-06:00
- Título: FASE 2: Tabla de clasificación real (verde/rojo) del auto-merge
- Módulo: Sin clasificar · Nivel de riesgo: C
- Por: irving:admin

### #465 — APROBÓ — 2026-08-08T10:02:56-06:00
- Título: Los items que llegan a “Tu Bandeja”
- Módulo: Sin clasificar · Nivel de riesgo: C
- Por: irving:admin
- Opción elegida: 412f3e5fff5ee0f9

### #171 — APROBÓ — 2026-08-08T09:57:49-06:00
- Título: #9 Fase 2: Chat IA ejecuta acciones (tool-calling)
- Módulo: IA · Nivel de riesgo: C
- Por: irving:admin
- Opción elegida: 4ed2aa819cb14096

### #545 — APROBÓ — 2026-08-08T09:55:45-06:00
- Título: Memoria de preferencias de Irving (el circuito decide "como Irving")
- Módulo: Sin clasificar · Nivel de riesgo: C
- Por: irving:admin

### #544 — APROBÓ — 2026-08-08T09:55:40-06:00
- Título: FASE 2: Tabla de clasificación real (verde/rojo) del auto-merge
- Módulo: Sin clasificar · Nivel de riesgo: C
- Por: irving:admin

### #542 — APROBÓ — 2026-08-08T09:51:38-06:00
- Título: los permisos editados en un rol no se reflejan bien en el usuario
- Módulo: Sin clasificar · Nivel de riesgo: C
- Por: irving:admin
- Opción elegida: 5cf393c79f01339c

### #541 — APROBÓ — 2026-08-08T09:51:28-06:00
- Título: Buscador con IA en Panorama (reemplaza/retoma #539)
- Módulo: Sin clasificar · Nivel de riesgo: C
- Por: irving:admin
- Opción elegida: 92d1fa3933bdf928

### #102 — APROBÓ — 2026-08-08T09:51:12-06:00
- Título: Mapa overview multi-vehículo en sección Mis Flotas de la APK
- Módulo: Flotas · Nivel de riesgo: C
- Por: irving:admin
- Opción elegida: f964b022a4c4ca26

### #538 — APROBÓ — 2026-08-08T09:50:58-06:00
- Título: Item Hoja de Ruta — nivel_riesgo: B — estado: requiere_irving (Reemplaza/expande el item previo de "auto-.view en sidebar")
- Módulo: Sin clasificar · Nivel de riesgo: C
- Por: irving:admin
- Opción elegida: 63166401a6fd66fa

### #543 — APROBÓ — 2026-08-08T09:50:32-06:00
- Título: Política de autonomía del Circuito CC: "construir por defecto, vetar después"
- Módulo: Sin clasificar · Nivel de riesgo: C
- Por: irving:admin

### #537 — APROBÓ — 2026-08-08T09:49:20-06:00
- Título: Reemplaza/expande el item previo de "auto-.view en sidebar"
- Módulo: Sin clasificar · Nivel de riesgo: C
- Por: irving:admin
- Opción elegida: 9fb78849c0eca68e

### #534 — APROBÓ — 2026-08-08T09:48:41-06:00
- Título: Guardrail: ninguna migracion debe poder correr en dev sin quedar commiteada en main (raiz que repite el incidente)
- Módulo: Core / Release · Nivel de riesgo: B
- Por: irving:admin
- Opción elegida: f439c0f82292cb63

### #465 — APROBÓ — 2026-08-08T09:48:12-06:00
- Título: Los items que llegan a “Tu Bandeja”
- Módulo: Sin clasificar · Nivel de riesgo: C
- Por: irving:admin
- Opción elegida: 412f3e5fff5ee0f9

### #536 — APROBÓ — 2026-08-08T09:47:22-06:00
- Título: Título: Regresión visual global — sidebar en blanco/negro + colores y subrayado de estado en Listado de Clientes ya no se aplican
- Módulo: Sin clasificar · Nivel de riesgo: C
- Por: irving:admin
- Opción elegida: 02afe21a38b57824

### #541 — APROBÓ — 2026-08-08T09:33:44-06:00
- Título: Buscador con IA en Panorama (reemplaza/retoma #539)
- Módulo: Sin clasificar · Nivel de riesgo: C
- Por: irving:admin
- Opción elegida: 92d1fa3933bdf928

### #171 — APROBÓ — 2026-08-07T19:27:58-06:00
- Título: #9 Fase 2: Chat IA ejecuta acciones (tool-calling)
- Módulo: IA · Nivel de riesgo: C
- Por: irving:admin
- Opción elegida: 4ed2aa819cb14096

### #527 — APROBÓ — 2026-08-07T19:27:09-06:00
- Título: # Ítem madre — Auditoría integral del sistema (generadora de items)
- Módulo: Sin clasificar · Nivel de riesgo: C
- Por: irving:admin
- Opción elegida: 68e3cdcfa4f837e9

### #480 — APROBÓ — 2026-08-07T19:21:24-06:00
- Título: boton agregar
- Módulo: Sin clasificar · Nivel de riesgo: B
- Por: irving:admin
- Opción elegida: 53d038c32700780b

### #465 — APROBÓ — 2026-08-07T19:20:13-06:00
- Título: Los items que llegan a “Tu Bandeja”
- Módulo: Sin clasificar · Nivel de riesgo: C
- Por: irving:admin
- Opción elegida: 34c7711ea6d84af9

### #539 — APROBÓ — 2026-08-07T19:19:18-06:00
- Título: integrar ia
- Módulo: Sin clasificar · Nivel de riesgo: B
- Por: irving:admin

### #538 — APROBÓ — 2026-08-07T19:19:15-06:00
- Título: Item Hoja de Ruta — nivel_riesgo: B — estado: requiere_irving (Reemplaza/expande el item previo de "auto-.view en sidebar")
- Módulo: Sin clasificar · Nivel de riesgo: C
- Por: irving:admin

### #537 — APROBÓ — 2026-08-07T19:19:09-06:00
- Título: Reemplaza/expande el item previo de "auto-.view en sidebar"
- Módulo: Sin clasificar · Nivel de riesgo: C
- Por: irving:admin

### #536 — APROBÓ — 2026-08-07T19:19:02-06:00
- Título: Título: Regresión visual global — sidebar en blanco/negro + colores y subrayado de estado en Listado de Clientes ya no se aplican
- Módulo: Sin clasificar · Nivel de riesgo: C
- Por: irving:admin
- Opción elegida: f7bf948811eebb3e

### #528 — APROBÓ — 2026-08-07T19:18:42-06:00
- Título: # Ítem madre v2 — Driver de auditoría AUTO-ENCADENADO (hasta completar el sistema)
- Módulo: Sin clasificar · Nivel de riesgo: C
- Por: irving:admin
- Opción elegida: 089cb8acfc85a515

### #479 — APROBÓ — 2026-08-07T16:06:11-06:00
- Título: temporizador
- Módulo: Sin clasificar · Nivel de riesgo: B
- Por: irving:admin

### #485 — APROBÓ — 2026-08-07T16:05:58-06:00
- Título: [Asesor·Cobranza] Segmentar las 229 facturas en dos grupos según monto individual: (a) facturas >$1,000 MXN — ofrecer convenio …
- Módulo: Cobranza / Consejo asesor · Nivel de riesgo: C
- Por: irving:admin

### #537 — APROBÓ — 2026-08-07T16:05:49-06:00
- Título: Reemplaza/expande el item previo de "auto-.view en sidebar"
- Módulo: Sin clasificar · Nivel de riesgo: C
- Por: irving:admin

### #536 — APROBÓ — 2026-08-07T16:05:42-06:00
- Título: Título: Regresión visual global — sidebar en blanco/negro + colores y subrayado de estado en Listado de Clientes ya no se aplican
- Módulo: Sin clasificar · Nivel de riesgo: C
- Por: irving:admin
- Opción elegida: f7bf948811eebb3e

### #528 — APROBÓ — 2026-08-07T16:05:33-06:00
- Título: # Ítem madre v2 — Driver de auditoría AUTO-ENCADENADO (hasta completar el sistema)
- Módulo: Sin clasificar · Nivel de riesgo: C
- Por: irving:admin
- Opción elegida: 089cb8acfc85a515

### #465 — APROBÓ — 2026-08-07T16:05:26-06:00
- Título: Los items que llegan a “Tu Bandeja”
- Módulo: Sin clasificar · Nivel de riesgo: C
- Por: irving:admin
- Opción elegida: 412f3e5fff5ee0f9

### #495 — APROBÓ — 2026-08-07T16:03:18-06:00
- Título: MegaFamilia — Notificaciones push (FCM), infra transversal PRIORITARIA
- Módulo: MegaFamilia · Nivel de riesgo: B
- Por: irving:admin

### #489 — APROBÓ — 2026-08-07T16:03:07-06:00
- Título: MegaFamilia app padre — Login por # cliente/teléfono + OTP (incluye fix seguridad)
- Módulo: MegaFamilia · Nivel de riesgo: C
- Por: irving:admin
- Opción elegida: 3e7a4987d392fbc4

### #533 — APROBÓ — 2026-08-07T16:03:02-06:00
- Título: Reconciliar las 48 migraciones fantasma (aplicadas en dev, sin archivo en main) — pendiente de fondo de #531
- Módulo: Core / Release · Nivel de riesgo: B
- Por: irving:admin
- Opción elegida: 862210990c2f76ff

### #171 — APROBÓ — 2026-08-07T16:02:48-06:00
- Título: #9 Fase 2: Chat IA ejecuta acciones (tool-calling)
- Módulo: IA · Nivel de riesgo: C
- Por: irving:admin
- Opción elegida: 5814199004e7f8b1

### #243 — APROBÓ — 2026-08-07T16:02:32-06:00
- Título: Push FCM usa API legacy desmantelada (comentario dice v1 pero envía a /fcm/send con key=)
- Módulo: Talento · Nivel de riesgo: B
- Por: irving:admin

