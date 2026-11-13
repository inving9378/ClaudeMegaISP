# Marketing IA 360 — Plan por fases (APROBADO por Henry el 12-nov; Fase 0 en construcción — D1–D3 SÍ)

Dueño: **Henry** · Worktree: `/var/www/megaisp-henry-marketing` · Rama: `feat/marketing-360-equipo`
Equipo por fase (instrucción fija): `ingeniero-supervisor` + `agente-backend` + `agente-frontend` +
`agente-qa` en paralelo · Puerta: skill `pruebas-antes-de-avanzar` · Reporte completo → **alto hasta
el SÍ/NO de Henry** · Ningún commit sin su OK.

---

## 1. Lo que YA existe (inventario del 12-nov, base de dev = copia de prod del 10-nov)

### Tablas (33 `marketing_*`; todas ya traen `company_id`, default 1, sin filtro real)
| Tabla | Filas | Sirve para | Uso en el plan |
|---|---|---|---|
| `marketing_campaigns` | 0 | campaña: tipo, `budget`/`spent` **decimal(12,2)**, `currency` MXN, fechas, `status`, `config`/`goals` json, `external_id` | **Base de la campaña 360** → se le agregan columnas |
| `marketing_leads` | 50 | lead con `status` (new/contacted/qualified/negotiating/won/lost/archived), score IA, asignado | **Base del Kanban** → se le agrega `campaign_id` |
| `marketing_pipelines` / `_pipeline_stages` / `marketing_lead_pipeline` | 0 | embudo con etapas, color, orden, ganado/perdido, entrada/salida de etapa | **Columnas del Kanban** (se siembran las 5 etapas por empresa) |
| `marketing_lead_activities` | 80 | bitácora del lead (incluye `stage_changed`) | historial al mover tarjetas |
| `marketing_attributions` | 0 | lead ↔ campaña ↔ canal (first/last touch) | analítica por campaña |
| `marketing_generated_content` | 204 | copy/imagen/video/voz generados, `source_campaign_id`, costo IA | **Biblioteca de contenido** → columnas editoriales |
| `marketing_publications` | 0 | publicación por canal: `content_id`, `campaign_id`, `scheduled_for`, `status`, métricas, `ab_variant_tag` | **Calendario** (programado/publicado) |
| `marketing_publication_channels` | 6 | FB / IG / WA Status / email configurados | canales de la campaña |
| `marketing_multivariant_campaigns` | 3 | generador de variantes de video por nicho | se reutiliza para guiones de Reels |
| `marketing_content_templates` | 0 | plantillas de copy con tono | prompts base |
| `marketing_channels` | 6 | catálogo de canales (WA, FB, IG, email, SMS, voz) | canales de la campaña |
| `marketing_settings` | 34 | Brand Kit (logo, colores) | colores de la guía visual |

### Rutas y pantallas (139 rutas; ~19 pantallas Vue, ~5,400 líneas)
Dashboard `/marketing` · Leads (lista, detalle, formularios, editor) · Conversaciones WhatsApp ·
Video MVM (plantillas, generador, cola, comparador de voces) · Generador de campañas multivariante ·
Campañas (lista, detalle, aprobar/pausar/activar) · Generador de contenido (copy/imagen, aprobar/rechazar) ·
Publicación (tablero, canales, cola, publicar campaña, métricas) · Campañas piloto (correo A/B) · Brand Kit.

### Servicios centrales que se usan (no se duplican)
- **IA**: `IA::enviar('marketing.<función>', …)`; ya hay 6 claves `marketing.*` en `IA/config/modulos.php`.
- **WhatsApp**: el trabajo nuevo **no envía nada** ni se conecta a ninguna línea. Los mensajes de
  WhatsApp de las campañas **solo se generan como texto**. **Sofía no tiene nada que ver con Marketing**
  (es del módulo de Mapas y vive en Meta): no se usa, no se toca, no se conecta.
- **Meta**: integración `meta-business` del Hub (**inactiva en dev**). `MetaGraphApiService` hoy solo
  lee leads de formularios; **no lee gasto**.
- **Librerías ya instaladas**: ApexCharts, Chart.js, FullCalendar, Quasar. **No hace falta instalar nada.**

### Lo que NO existe
- Concepto de **empresa** para Marketing (no hay tabla de empresas; `company_information` es la ficha
  fiscal de Meganet, no sirve como "empresas").
- Usuario ↔ empresa ↔ rol (admin / marketing / ventas).
- Filtro por empresa en consultas (todo usa `company_id = 1` fijo o sin filtrar).
- Lectura de gasto/tope de Meta Ads y aviso al 80 %.
- Estrategia 360, guiones de Reels con escenas, copies A/B por campaña, calendario editorial, Kanban.

---

## 2. Fases

> Cada fase: supervisor planea (contrato + 3 paquetes sin archivos compartidos) → backend, frontend y QA
> en paralelo → supervisor une y revisa → QA corre la skill e intenta romperlo → supervisor aprueba →
> **reporte completo a Henry → alto**.

### Fase 0 — Cimientos: multiempresa, roles y guía visual
**Backend**
- Tabla nueva `marketing_empresas` (id, nombre, slug, colores, activo) — sembrar id 1 = Meganet
  (coincide con el `company_id = 1` que ya tienen todas las filas, **cero datos migrados**).
- Tabla nueva `marketing_empresa_usuario` (company_id, user_id, rol: `admin`/`marketing`/`ventas`).
- Trait de alcance por empresa (scope global) aplicado a los modelos de Marketing; `company_id` sale del
  usuario autenticado; id de otra empresa → 404. Selector de empresa activa si el usuario tiene varias.
- Permisos del módulo (`module.json`) + matriz por rol; asignación aditiva (`givePermissionTo`).
**Frontend**
- `docs/marketing/guia-visual.md` + kit de componentes del módulo (tarjeta KPI, tarjeta de panel,
  tabla ejecutiva, encabezado de pantalla, estado vacío, insignia de estado, formato MXN).
- Pantalla "Empresas y equipo" (solo admin de marketing).
**QA**: matriz empresa A/B y roles sobre **todos** los endpoints existentes del módulo.

### Fase 1 — Rediseño ejecutivo de TODAS las pantallas actuales
Mismo funcionamiento, nueva cara (KPIs arriba, tarjetas limpias, tablas ordenadas, gráficas claras,
jerarquía y espaciado), colores del sistema / marca Meganet, `<style scoped>`, responsive a 375 px.
| Pantalla | Acción |
|---|---|
| Dashboard `/marketing` | **Rehacer**: KPIs (leads del mes, conversión, gasto MXN, costo por lead), embudo, campañas activas |
| Leads (lista + detalle) | Ajustar: tabla ejecutiva, filtros, ficha con timeline |
| Formularios de leads + editor | Ajustar |
| Conversaciones WhatsApp | Ajustar (bandeja tipo panel, sin cambiar lógica del bot) |
| Campañas (lista + detalle) | Ajustar (se amplía en Fase 2) |
| Generador multivariante / contenido | Ajustar |
| Video MVM (plantillas, generador, cola, voces) | Ajustar |
| Publicación (tablero, canales, cola, publicar) | Ajustar |
| Campañas piloto · Brand Kit | Ajustar |
| Menú del módulo (`module.json`) | Reordenar entradas (sin tocar el sidebar general) |
**Backend**: solo endpoints de KPIs del dashboard (ya filtrados por empresa). **QA**: regresión visual y
funcional de cada pantalla (abre, guarda, filtra, sin errores de consola, 1440/375 px).

### Fase 2 — Constructor de campañas 360 con IA
- **Columnas nuevas en `marketing_campaigns`**: `objetivo`, `publico` (json), `oferta`, `canales` (json),
  `estrategia` (json), `estrategia_generada_at`. Presupuesto = `budget` (ya decimal MXN), fechas = las existentes.
- Asistente por pasos: objetivo → público → oferta → canales → presupuesto MXN y fechas → **la IA genera**:
  estrategia, guiones de Reels por escenas, copies FB/IG con variantes A/B, mensajes de WhatsApp,
  variantes de anuncio. Todo editable y regenerable por pieza.
- Las piezas se guardan en `marketing_generated_content` (ver Fase 3) ligadas a la campaña; los guiones
  de Reels pueden mandarse a Video MVM.
- Claves IA nuevas: `marketing.campana_360` (estrategia) y `marketing.piezas_campana` (piezas).
- Los textos de WhatsApp **solo se generan** como contenido; nunca se envían (ni por Sofía ni por ninguna línea).

### Fase 3 — Biblioteca y calendario de contenido
- **Columnas nuevas en `marketing_generated_content`**: `pieza` (reel_guion, copy_fb, copy_ig,
  mensaje_whatsapp, variante_anuncio…), `variante` (A/B), `estado_editorial`
  (borrador/aprobado/programado/publicado), `aprobado_por`, `aprobado_at`, `programado_para`,
  `publication_id`.
- Biblioteca (filtros por campaña, canal, estado, variante) + calendario FullCalendar (arrastrar para
  reprogramar). "Programar" crea la fila en `marketing_publications` (la cola de Publicación que ya existe);
  "publicado" se sincroniza desde ahí. Video MVM: un guion aprobado abre el generador con el guion cargado.

### Fase 4 — CRM Kanban de leads
- **Columna nueva en `marketing_leads`**: `campaign_id` (además de `marketing_attributions`).
- Sembrar por empresa el embudo con 5 etapas (nuevo, contactado, cotizado, cliente, perdido) en las
  tablas de pipeline que ya existen; mover tarjeta = cambio de etapa + `status` equivalente + actividad
  `stage_changed`. Perdido pide motivo (`lost_reason` ya existe).
- Rol `ventas`: ve y mueve sus leads asignados; `marketing`/`admin`: todos los de su empresa.

### Fase 5 — Meta Ads (solo lectura) + analítica por campaña
- Lectura **GET** del tope y gasto de **toda la cuenta publicitaria** (`spend_cap` / `amount_spent`; el tope
  de Irving es para todas las campañas juntas) y del gasto por campaña (`insights.spend`) para analítica,
  con la credencial del Hub; **nunca** se modifica nada en Meta. En dev se deja todo listo y se reporta qué
  permiso falta para activar la lectura.
- **Columnas nuevas en `marketing_campaigns`**: `meta_gasto_mxn`, `meta_leido_at` (gasto por campaña).
  El tope y gasto de la cuenta van en `marketing_empresas` (cuenta publicitaria por marca):
  `meta_ad_account_id`, `meta_gasto_cuenta_mxn`, `meta_tope_cuenta_mxn`, `meta_moneda`, `meta_leido_at`, `aviso_80_at`. Tabla nueva pequeña `marketing_campana_gasto_diario`
  (para la gráfica de gasto en el tiempo; no existe nada equivalente).
- Lectura programada desde el `ModuleServiceProvider` del módulo (sin tocar `Kernel.php`).
- Aviso al 80 % del tope **de la cuenta**: una vez por ciclo, en pantalla + notificación interna de LM para Henry. Nada por WhatsApp.
- Analítica por campaña: gasto vs tope, leads, costo por lead y por cliente (MXN), conversión por etapa,
  rendimiento de variantes A/B, publicaciones y engagement.

---

## 3. Archivos que se tocarían (alcance)
Dentro: `app/Modules/Addons/Marketing/**`, `resources/js/components/module/marketing/**`,
`tests/Feature/Marketing/**`, `docs/marketing/**`, `docs/bitacora/**`.
**Excepciones que necesitan OK de Henry** (ver preguntas).

## 4. Decisiones de Henry (12-nov)
1. Autorizados fuera de la carpeta del módulo: `resources/js/components/module/marketing/**`, una línea en
   `resources/js/app.js`, `app/Models/Marketing/**`, `app/Modules/Addons/IA/config/modulos.php` (solo `marketing.*`).
   Irving autorizó tocar **solo** lo de Marketing.
2. Empresa = marcas del grupo Meganet; una persona puede estar en varias.
3. Sofía no tiene relación con Marketing; WhatsApp solo como texto generado.
4. Tope de Meta = toda la cuenta publicitaria; solo lectura.
5. Aviso 80 %: en pantalla + notificación interna para Henry.
6. Sin pcov: cobertura = tabla pieza nueva → prueba.
7. Roles y permisos autorizados por Irving solo para Marketing. Lead «cliente» no crea cliente en facturación.
8. Al sistema de Meganet se le llama **LM**.
