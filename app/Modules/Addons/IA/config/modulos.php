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
 * respaldo: (opcional) sin IA asignada la función NO se apaga: sigue con el primero
 *           de `modelos` (su «ahorro por defecto»). Solo el supervisor del Circuito.
 *
 * Agregar un consumidor nuevo = sumar su clave aquí y llamar IA::enviar('<clave>', ...).
 *
 * El Circuito CC (#1076, decisión de Irving 2026-10-30): este es el ÚNICO lugar donde
 * se elige el modelo de cada pieza del supervisor (válvula, revisor, briefs…). Esas
 * piezas llaman directo a Anthropic (ClaudeApiClient resuelve la llave como siempre)
 * y de aquí solo toman el modelo con IA::modeloPara(). Sin asignación usan el ahorro
 * por defecto de config/circuito.php → ia.modelos. Las terminales del circuito (el CLI
 * `claude`) siguen fuera: se autentican aparte.
 */

// Ahorro por defecto de la pieza primero (lo que usa sin asignación) y luego las alternativas.
$circuito = static fn (string $pieza): array => array_values(array_unique(array_filter([
    config("circuito.ia.modelos.{$pieza}"),
    'claude-haiku-5-5', 'claude-sonnet-5-5', 'claude-opus-4-7', 'claude-opus-5-5',
])));

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
    'asistente.chat_flotante' => ['nombre' => 'JARVIS — asistente flotante', 'grupo' => 'Sistema', 'requiere' => ['herramientas'], 'listo' => true],
    'asistente.memoria' => ['nombre' => 'JARVIS — resumir y recordar las conversaciones', 'grupo' => 'Sistema', 'requiere' => [], 'listo' => true],
    'asistente.entrenamiento' => ['nombre' => 'JARVIS — escribir su guía del sistema (asistente:aprender)', 'grupo' => 'Sistema', 'requiere' => [], 'listo' => true],
    'ia.memoria' => ['nombre' => 'Memoria del chat IA (extraer hechos)', 'grupo' => 'Sistema', 'requiere' => [], 'listo' => true],
    'devtools.chat' => ['nombre' => 'Chat de DevTools', 'grupo' => 'Sistema', 'requiere' => ['imagenes'], 'listo' => true],
    'modulos.plan_migracion' => ['nombre' => 'Plan de migración de módulos', 'grupo' => 'Sistema', 'requiere' => [], 'listo' => true],
    'smartimport.conflictos' => ['nombre' => 'Sugerir cómo resolver conflictos al importar', 'grupo' => 'Sistema', 'requiere' => [], 'listo' => true],

    // Supervisor del Circuito CC (#1076). Cada llamada queda etiquetada con su clave en el log
    // del canal `claude`; `php artisan circuito:gasto-ia` suma el costo por pieza.
    'circuito.valvula' => ['nombre' => 'Válvula de contexto: ¿el item toca la frontera o solo la menciona?', 'grupo' => 'Circuito', 'requiere' => ['anthropic'], 'modelos' => $circuito('valvula'), 'respaldo' => true, 'listo' => true],
    'circuito.revisor' => ['nombre' => 'Revisor de rutina: autoriza o escala a Irving', 'grupo' => 'Circuito', 'requiere' => ['anthropic'], 'modelos' => $circuito('revisor'), 'respaldo' => true, 'listo' => true],
    'circuito.segunda_opinion' => ['nombre' => 'Segunda opinión cuando el modelo barato duda (revisor y briefs)', 'grupo' => 'Circuito', 'requiere' => ['anthropic'], 'modelos' => $circuito('segunda_opinion'), 'respaldo' => true, 'listo' => true],
    'circuito.briefs' => ['nombre' => 'Briefs de decisión para Irving (preguntas y opciones)', 'grupo' => 'Circuito', 'requiere' => ['anthropic'], 'modelos' => $circuito('briefs'), 'respaldo' => true, 'listo' => true],
    'circuito.seguridad' => ['nombre' => 'Auditor de riesgo (seguridad y dinero)', 'grupo' => 'Circuito', 'requiere' => ['anthropic'], 'modelos' => $circuito('seguridad'), 'respaldo' => true, 'listo' => true],
    'circuito.destrabe' => ['nombre' => 'Des-trabe de la bandeja', 'grupo' => 'Circuito', 'requiere' => ['anthropic'], 'modelos' => $circuito('destrabe'), 'respaldo' => true, 'listo' => true],
    'circuito.resumen' => ['nombre' => 'Resumen en llano del ticket (manual)', 'grupo' => 'Circuito', 'requiere' => ['anthropic'], 'modelos' => $circuito('resumen'), 'respaldo' => true, 'listo' => true],
    'circuito.asesor_cobranza' => ['nombre' => 'Consejo asesor de cobranza (manual)', 'grupo' => 'Circuito', 'requiere' => ['anthropic'], 'modelos' => $circuito('asesor_cobranza'), 'respaldo' => true, 'listo' => true],
    'circuito.jarvis_chat' => ['nombre' => 'Jarvis de la Torre: chat con herramientas de solo lectura', 'grupo' => 'Circuito', 'requiere' => ['herramientas'], 'modelos' => $circuito('jarvis_chat'), 'respaldo' => true, 'listo' => true],

    // Voz (API de audio de OpenAI). La llave se lee en cada llamada vía IA::credencialesPara().
    'voip.transcripcion' => ['nombre' => 'Oído del bot «María» (voz a texto)', 'grupo' => 'VoIP', 'requiere' => ['voz'], 'modelos' => ['whisper-1'], 'listo' => true],
    'voip.voz' => ['nombre' => 'Voz del bot «María» (texto a voz)', 'grupo' => 'VoIP', 'requiere' => ['voz'], 'modelos' => ['tts-1', 'tts-1-hd', 'gpt-4o-mini-tts'], 'listo' => true],
    'marketing.voz_videos' => ['nombre' => 'Voz de los videos (texto a voz)', 'grupo' => 'Marketing', 'requiere' => ['voz'], 'modelos' => ['tts-1', 'tts-1-hd', 'gpt-4o-mini-tts'], 'listo' => true],
    'cobranza.voz' => ['nombre' => 'Voz de las llamadas de cobranza (texto a voz)', 'grupo' => 'Cobranza', 'requiere' => ['voz'], 'modelos' => ['tts-1', 'tts-1-hd', 'gpt-4o-mini-tts'], 'listo' => true],
];
