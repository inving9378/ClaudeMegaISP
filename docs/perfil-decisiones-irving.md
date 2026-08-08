# Perfil de decisiones de Irving — para el revisor/decisor del Circuito CC

> Perfil VIVO e inlineado en el prompt del revisor (#338) y del decisor. Objetivo: que decidan **a
> gusto de Irving** → menos escalaciones de cosas que él ya resolvería igual (menos ruido en su
> bandeja), propuestas más alineadas. **NO relaja la frontera dura** (dinero/seguridad/permisos/
> prod/negocio SIEMPRE van a Irving). Editable por Irving. Sin secretos. Aditivo.

## Quién es
Irving — dueño/arquitecto de Meganet (ISP). Decide en el chat; CC ejecuta. **No programador**, muy
visual: prefiere **tablas**, realismo honesto, reportes coloquiales y directos ("dímelo derecho").
Valora que le adviertan riesgos y límites (p.ej. capacidad del box) con franqueza, no optimismo vacío.
Respuestas y commits **siempre en español**.

## Negocio y contexto
- **Meganet** = la empresa ISP. **MegaISP** = la plataforma completa. **Medussa** = SOLO el módulo de
  facturación/documentos — no confundir los tres nombres.
- Prohibido decir "piramidal" (módulo Embajadores/referidos): usar "cascada" o "multinivel de activaciones".
- Entregar cada módulo **COMPLETO** (lógica + vistas + modelos + migraciones), sin placeholders ni TODOs
  colgados. Nada de sobre-ingeniería: lo mínimo que resuelve el objetivo real (ver `reglas-operacion-circuito.md`).

## Guardrails (reglas duras — el ejecutor SIEMPRE las respeta; el revisor las asume)
- **SOLO dev** (esta máquina). PROD es otra máquina: NUNCA tocarla, ni su BD, ni pushear a origin.
- Git: **add selectivo** archivo por archivo (NUNCA `add -A`), un commit por sub-paso, en español.
- **NUNCA `migrate:fresh`** (solo migraciones aditivas/incrementales). **NUNCA `config:cache`** en
  este repo (rompe `env()` runtime → IA/WhatsApp NULL); warm-up = `config:clear + route:clear + queue:restart`.
- Cada cambio en **su rama** + **verificación de regresión** antes de integrar; conflicto/fallo → revertir + escalar.
- Secretos SOLO en `.env`; en docs/bitácoras solo placeholders.
- Reportar el hash del commit **después** de commitear (no anticipado).

## Preferencias (para AUTORIZAR con más soltura lo que a Irving no le interesa revisar)
- **Estilo de decisión (lo más importante):** ante opciones, elegir la RECOMENDADA por la IA y proceder
  — Irving casi siempre la aprueba; preguntarle solo cuando de verdad hay ambigüedad o toca la frontera
  dura. Preguntar de más frena el circuito sin ganar seguridad real.
- **Auto-merge ON** por default (lo verificado se integra solo); toggle claro ON/OFF.
- **Arranque conservador → ampliar con confianza**: empezar restrictivo y soltar de a poco lo claramente seguro.
- **Costo sobre potencia**: modelo económico por defecto (Sonnet); Opus solo si se pide explícitamente o
  el problema lo exige (difícil/borderline, briefs C).
- **Reversibilidad ante todo**: migraciones ADITIVAS con `down()`; cada cambio en su rama + `merge_commit`;
  preferir lo que se pueda deshacer barato sobre lo que no.
- **Autonomía**: construir por defecto, vetar después — minimizar autorizaciones sin abrir la frontera dura.
- **Paralelo N=6** en el box de dev (4 cores/17GB) con semáforo de builds; pool continuo (slots llenos sin valles).
- Estética: **azul cielo `#7dd3fc`** (NO rosa). Voz natural (TTS). Reportes **duales y coloquiales**.
- Le gusta que "la lista se vacíe sola" con varias terminales visibles; que el sistema sea auditable y reversible.

## Estética / frontend
- Esencia tipo **Splynx**: layout limpio, sidebar de módulos, top bar de acceso rápido, KPI cards.
- **Light por defecto + toggle de dark** (arriba a la derecha), preferencia guardada por admin.
- Stack estándar: **Vue 3 + Quasar UMD sobre Blade**. Excepción conocida: **Flotas** usa Bootstrap 5 + Leaflet
  (decisión ya tomada, no "corregir" a Quasar sin que Irving lo pida).

## Convenciones técnicas (resumen — fuente completa: skill `megaisp-conventions`)
- Permisos Spatie nuevos pasan por `PermissionSyncService` (`app/Modules/Core/Security/Services/`).
- `@can()` de Blade NO funciona en este proyecto: usar `@if(auth()->user()->can('permiso'))`.
- Login por `login_user` (**NO** `email`).
- Fechas legacy (`payment_date`/`document_date`) son VARCHAR `DD/MM/YYYY`: usar `STR_TO_DATE`, nunca comparar como string.
- Git: `add` SELECTIVO archivo por archivo (nunca `-A`/`.`), un commit por sub-paso, en español.
- Caché: ver Guardrails arriba (`config:clear`+`route:clear`+`queue:restart`; **nunca `config:cache`
  ni `migrate:fresh`** en este repo).

## Qué AUTORIZAR sin molestarlo (técnico, aditivo, reversible, bajo riesgo)
Bugs de null/NPE y guards defensivos; rutas/enlaces muertos (404) repuntados a la ruta real; typos,
labels, textos, iconos; refactors LOCALES acotados; endpoints de estadística/lectura; código muerto
acotado; correcciones de UI/tema NO sensibles; consistencia de configs no sensibles. Si es claramente
esto y NO toca la frontera dura → **autoriza** (aunque el alcance sea moderado).

## FRONTERA DURA — SIEMPRE a Irving (con cualquier modelo; el perfil NO la relaja)
- **Dinero / cobros**: pagos, facturación, saldos, conciliación, OpenPay/SPEI/CLABE, nómina, comisiones, precios/tarifas.
- **Seguridad / permisos / auth**: roles, permisos (Spatie), login, contraseñas/credenciales, tokens/secrets, IDOR, cifrado.
- **Producción / despliegue**: cualquier cosa de prod, deploy, `.env`, pipeline de release.
- **Datos destructivos**: drop/truncate/borrado masivo/migrate:fresh.
- **Negocio / estrategia / arquitectura / multi-tenant**: decisiones de diseño o de negocio = de Irving.
Ante duda REAL en la frontera → **escala** (mejor un falso escalado que soltar algo sensible).

## Decisiones pasadas + razón (patrón reusable)
- **Merge del circuito lo hace meganet, no www-data** (www-data no puede escribir `.git`) → arquitectura
  "meganet escribe / www-data lee". Razón: seguridad de permisos sin abrir el working tree.
- **Aislamiento por worktree** del ejecutor (nunca tocar `/var/www/megaisp`). Razón: no pisar el trabajo de Irving.
- **Conflicto de merge → aborta, main intacto, escala a la bandeja + error visible** (nunca silencioso).
- **Deuda/decisión diferida → registrar en la Hoja de Ruta de inmediato** (no perderla).
- Cuando algo no es seguro/claro, **prefiere que se lo escalen** con un brief claro antes que ejecutarlo a ciegas.

## Aprende de la bandeja (crece con el tiempo)
Cada **aprobación/rechazo** de Irving (con su comentario) es una nueva preferencia → se agrega aquí →
el revisor predice mejor su respuesta y escala menos lo que él ya aprobaría.

**Loop de captura (item #351, activo):** cada decisión de Irving sobre un item de su bandeja
(`POST /api/roadmap/circuito/decidir`) se registra automáticamente como candidato crudo en
[`docs/pendientes-perfil-irving.md`](pendientes-perfil-irving.md). Ese archivo NO se auto-inlinea
aquí — Irving lo revisa en lote y decide a mano qué mover a este perfil. La frontera dura nunca se
infiere automáticamente por este loop.
