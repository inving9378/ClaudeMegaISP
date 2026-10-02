<?php

/**
 * Catálogo de módulos que usan IA → config('ia_modulos').
 * Se configura en Integraciones → pestaña "Módulos IA": cada clave apunta a una
 * integración de IA del Hub + modelo (tabla ia_asignaciones). SIN asignación el
 * módulo no usa IA (decisión de Irving 2026-10-02: no hay respaldo implícito).
 *
 * grupo:    agrupa en la pantalla.
 * requiere: capacidades que el proveedor debe tener (imagenes, pdf, herramientas).
 * listo:    true cuando el código del módulo ya consulta esta asignación vía
 *           IA::enviar(). Mientras sea false la pantalla avisa que el módulo aún
 *           usa su conexión anterior.
 *
 * Agregar un consumidor nuevo = sumar su clave aquí y llamar IA::enviar('<clave>', ...).
 * El Circuito CC NO aparece aquí a propósito: usa su propio CLI.
 */
return [
    'whatsapp.soporte' => ['nombre' => 'Bot de soporte, cobranza y atención', 'grupo' => 'WhatsApp', 'requiere' => [], 'listo' => true],
    'whatsapp.ventas' => ['nombre' => 'Agente de ventas a prospectos', 'grupo' => 'WhatsApp', 'requiere' => [], 'listo' => true],
    'marketing.agente_whatsapp' => ['nombre' => 'Agente de WhatsApp con herramientas (Evolution)', 'grupo' => 'Marketing', 'requiere' => ['herramientas'], 'listo' => false],
    'marketing.contenido' => ['nombre' => 'Copys de anuncios y prompts de imagen', 'grupo' => 'Marketing', 'requiere' => [], 'listo' => true],
    'marketing.lead_scoring' => ['nombre' => 'Puntuar leads', 'grupo' => 'Marketing', 'requiere' => [], 'listo' => true],
    'marketing.lead_calificacion' => ['nombre' => 'Calificar lead por conversación', 'grupo' => 'Marketing', 'requiere' => [], 'listo' => true],
    'marketing.director_creativo' => ['nombre' => 'Briefs de video por nicho', 'grupo' => 'Marketing', 'requiere' => [], 'listo' => true],
    'voip.bot_voz' => ['nombre' => 'Bot de voz «María»', 'grupo' => 'VoIP', 'requiere' => [], 'listo' => true],
    'pagos.comprobantes' => ['nombre' => 'Leer comprobantes de pago (foto y PDF)', 'grupo' => 'Pagos', 'requiere' => ['imagenes', 'pdf'], 'listo' => false],
    'flotas.ocr' => ['nombre' => 'Leer documentos de vehículos (foto y PDF)', 'grupo' => 'Flotas', 'requiere' => ['imagenes', 'pdf'], 'listo' => true],
    'talento.inspeccion_caja' => ['nombre' => 'Inspección de caja por foto', 'grupo' => 'Talento', 'requiere' => ['imagenes'], 'listo' => true],
    'talento.lectura_serie' => ['nombre' => 'Validar evidencias de la OT (serie/MAC e INE)', 'grupo' => 'Talento', 'requiere' => ['imagenes'], 'listo' => true],
    'warroom.moderador' => ['nombre' => 'Sugerencias al moderador', 'grupo' => 'WarRoom', 'requiere' => [], 'listo' => true],
    'warroom.insights' => ['nombre' => 'Insights de indicadores', 'grupo' => 'WarRoom', 'requiere' => [], 'listo' => true],
    'releases.changelog' => ['nombre' => 'Changelog de versiones', 'grupo' => 'Sistema', 'requiere' => [], 'listo' => true],
    'manual.generador' => ['nombre' => 'Generador del manual', 'grupo' => 'Sistema', 'requiere' => [], 'listo' => true],
    'asistente.chat_flotante' => ['nombre' => 'Asistente flotante', 'grupo' => 'Sistema', 'requiere' => [], 'listo' => true],
    'ia.memoria' => ['nombre' => 'Memoria del chat IA (extraer hechos)', 'grupo' => 'Sistema', 'requiere' => [], 'listo' => true],
    'devtools.chat' => ['nombre' => 'Chat de DevTools', 'grupo' => 'Sistema', 'requiere' => ['imagenes'], 'listo' => true],
    'modulos.plan_migracion' => ['nombre' => 'Plan de migración de módulos', 'grupo' => 'Sistema', 'requiere' => [], 'listo' => true],
];
