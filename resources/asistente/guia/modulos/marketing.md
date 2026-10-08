<!-- Generada por `php artisan asistente:aprender marketing` desde docs/modulos/marketing.md, el menú y las pantallas de alta · huella 68c1d3f5bb2d2cbd7539650ede84136b1a636c8d · 2026-10-08. No editar a mano: corregir la documentación del módulo o agregar una nota en resources/asistente/entrenamiento.json y regenerar. -->
# Marketing
**En pocas palabras:** Módulo automatizado para la gestión de campañas de marketing.

## Para qué sirve
- Captura y calificación de leads provenientes de anuncios y formularios.
- Atención a prospectos a través de un agente conversacional en WhatsApp.
- Generación de contenido publicitario y videos a través de IA.
- Publicación multicanal y medición de resultados sin intervención manual.

## Pantallas
- [Marketing › Video](/marketing/video-templates) — Generar y gestionar plantillas de video.
- [Marketing › Campaña IA](/marketing/campaigns/generate) — Crear campañas automatizadas con IA.
- [Marketing › Voces TTS](/marketing/voice-comparator) — Configuración y comparación de voces para TTS.
- [Marketing › Publicador](/marketing/publishing) — Configurar y gestionar publicaciones multicanal.
- [Marketing › Publicar](/marketing/publishing/campaign) — Publicar campañas en diferentes canales.
- [Marketing › Piloto A/B](/marketing/pilot-campaigns) — Probar variantes de campañas publicitarias.
- [Marketing › Cola](/marketing/publishing/queue) — Ver el estado de las publicaciones en cola.
- [Marketing › Canales](/marketing/publishing/setup) — Administrar canales de publicación.

## Cómo se hace
### Alta de lead
1. Se hace en [Marketing › Campaña IA](/marketing/campaigns/generate).

### Alta de campaña
1. Se hace en [Marketing › Campaña IA](/marketing/campaigns/generate).

### Alta de contenido
1. Se hace en [Marketing › Video](/marketing/video-templates).

### Alta de piloto A/B
1. Se hace en [Marketing › Piloto A/B](/marketing/pilot-campaigns).

### Alta de plan de publicación
1. Se hace en [Marketing › Publicar](/marketing/publishing/campaign).

## Qué datos pide
### Alta de lead
- **Obligatorios:** Source, Full name
- **Opcionales:** (ninguno)

### Alta de campaña
- **Obligatorios:** Nombre, Slug, Assign to source, Fields schema
- **Opcionales:** (ninguno)

### Alta de contenido
- **Obligatorios:** Nombre, Campaign type, Plan name, Plan speed, Plan price, Phone cta
- **Opcionales:** (ninguno)

### Alta de piloto A/B
- **Obligatorios:** Nombre, Variant a subject, Variant a body, Variant b subject, Variant b body, Recipients
- **Opcionales:** (ninguno)

### Alta de plan de publicación
- **Obligatorios:** Título, Channel, Start date, End date
- **Opcionales:** (ninguno)

## Quién lo usa
Este módulo es utilizado por el equipo de mercadotecnia de Meganet para optimizar la generación y gestión de leads, así como para automatizar las publicaciones en redes sociales.

## Con JARVIS
Por ahora JARVIS te explica cómo hacerlo y te da el enlace a la pantalla.
