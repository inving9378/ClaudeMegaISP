<?php

/**
 * Catálogo de módulos que usan IA → config('ia_modulos').
 * Se configura en Integraciones → pestaña "Módulos IA": cada clave apunta a una
 * integración de IA del Hub + modelo (tabla ia_asignaciones). Cada grupo puede
 * tener una IA GENERAL (clave "grupo:<slug>") que usan las funciones sin IA
 * propia. Sin propia ni general la función no usa IA (decisión de Irving
 * 2026-10-02: no hay respaldo implícito).
 *
 * grupo:    el módulo: agrupa en la pantalla y comparte la IA general.
 * requiere: capacidades que el proveedor debe tener (imagenes, pdf, herramientas, voz).
 * modelos:  (opcional) sugerencias de modelo propias del módulo; si no, las del
 *           protocolo. Los de voz usan modelos de audio, no de chat.
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
    'marketing.agente_whatsapp' => ['nombre' => 'Agente de WhatsApp con herramientas (Evolution)', 'grupo' => 'Marketing', 'requiere' => ['herramientas'], 'listo' => true],
    'marketing.contenido' => ['nombre' => 'Copys de anuncios y prompts de imagen', 'grupo' => 'Marketing', 'requiere' => [], 'listo' => true],
    'marketing.lead_scoring' => ['nombre' => 'Puntuar leads', 'grupo' => 'Marketing', 'requiere' => [], 'listo' => true],
    'marketing.lead_calificacion' => ['nombre' => 'Calificar lead por conversación', 'grupo' => 'Marketing', 'requiere' => [], 'listo' => true],
    'marketing.director_creativo' => ['nombre' => 'Briefs de video por nicho', 'grupo' => 'Marketing', 'requiere' => [], 'listo' => true],
    'voip.bot_voz' => ['nombre' => 'Bot de voz «María»', 'grupo' => 'VoIP', 'requiere' => [], 'listo' => true],
    'pagos.comprobantes' => ['nombre' => 'Leer comprobantes de pago (foto y PDF)', 'grupo' => 'Pagos', 'requiere' => ['imagenes', 'pdf'], 'listo' => true],
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
    'smartimport.conflictos' => ['nombre' => 'Sugerir cómo resolver conflictos al importar', 'grupo' => 'Sistema', 'requiere' => [], 'listo' => true],

    // Voz (API de audio de OpenAI). La llave se lee en cada llamada vía IA::credencialesPara().
    'voip.transcripcion' => ['nombre' => 'Oído del bot «María» (voz a texto)', 'grupo' => 'VoIP', 'requiere' => ['voz'], 'modelos' => ['whisper-1'], 'listo' => true],
    'voip.voz' => ['nombre' => 'Voz del bot «María» (texto a voz)', 'grupo' => 'VoIP', 'requiere' => ['voz'], 'modelos' => ['tts-1', 'tts-1-hd', 'gpt-4o-mini-tts'], 'listo' => true],
    'marketing.voz_videos' => ['nombre' => 'Voz de los videos (texto a voz)', 'grupo' => 'Marketing', 'requiere' => ['voz'], 'modelos' => ['tts-1', 'tts-1-hd', 'gpt-4o-mini-tts'], 'listo' => true],
    'cobranza.voz' => ['nombre' => 'Voz de las llamadas de cobranza (texto a voz)', 'grupo' => 'Cobranza', 'requiere' => ['voz'], 'modelos' => ['tts-1', 'tts-1-hd', 'gpt-4o-mini-tts'], 'listo' => true],
];
