# Perfil de decisiones de Irving — para el revisor/decisor del Circuito CC

> Perfil VIVO e inlineado en el prompt del revisor (#338) y del decisor. Objetivo: que decidan **a
> gusto de Irving** → menos escalaciones de cosas que él ya resolvería igual (menos ruido en su
> bandeja), propuestas más alineadas. **NO relaja la frontera dura** (dinero/seguridad/permisos/
> prod/negocio SIEMPRE van a Irving). Editable por Irving. Sin secretos. Aditivo.

## Quién es
Irving — dueño/arquitecto de Meganet (ISP). Decide en el chat; CC ejecuta. **No programador**, muy
visual: prefiere **tablas**, realismo honesto, reportes coloquiales y directos ("dímelo derecho").
Valora que le adviertan riesgos y límites (p.ej. capacidad del box) con franqueza, no optimismo vacío.

## Guardrails (reglas duras — el ejecutor SIEMPRE las respeta; el revisor las asume)
- **SOLO dev** (esta máquina). PROD es otra máquina: NUNCA tocarla, ni su BD, ni pushear a origin.
- Git: **add selectivo** archivo por archivo (NUNCA `add -A`), un commit por sub-paso, en español.
- **NUNCA `migrate:fresh`** (solo migraciones aditivas/incrementales). **NUNCA `config:cache`** en
  este repo (rompe `env()` runtime → IA/WhatsApp NULL); warm-up = `config:clear + route:clear + queue:restart`.
- Cada cambio en **su rama** + **verificación de regresión** antes de integrar; conflicto/fallo → revertir + escalar.
- Secretos SOLO en `.env`; en docs/bitácoras solo placeholders.
- Reportar el hash del commit **después** de commitear (no anticipado).

## Preferencias (para AUTORIZAR con más soltura lo que a Irving no le interesa revisar)
- **Auto-merge ON** por default (lo verificado se integra solo); toggle claro ON/OFF.
- **Arranque conservador → ampliar con confianza**: empezar restrictivo y soltar de a poco lo claramente seguro.
- **Modelo escalonado**: Sonnet para lo rutinario, Opus solo para lo difícil/borderline y los briefs C.
- **Paralelo N=6** en el box de dev (4 cores/17GB) con semáforo de builds; pool continuo (slots llenos sin valles).
- Estética: **azul cielo `#7dd3fc`** (NO rosa). Voz natural (TTS). Reportes **duales y coloquiales**.
- Le gusta que "la lista se vacíe sola" con varias terminales visibles; que el sistema sea auditable y reversible.

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
