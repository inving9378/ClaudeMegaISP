<?php

/**
 * Catálogo de módulos que usan servicios externos → config('servicios_modulos').
 * Se configura en Integraciones → pestaña "Módulos de servicios" (tabla
 * servicio_asignaciones). Mismo patrón que Módulos IA, con una diferencia
 * decidida por Irving (2026-10-06): SIN asignación el módulo sigue como
 * siempre (no se apaga), y una migración deja asignado lo que funciona hoy.
 *
 * servicio: whatsapp → se elige una LÍNEA de WhatsApp (el proveedor lo define
 *           la línea: Evolution u oficial de Meta).
 *           correo | mapas | imagenes_stock | redes_sociales → se elige una
 *           integración del Hub cuyo proveedor da ese servicio.
 * via (solo whatsapp): 'linea'   = pasa por el gateway y queda en el historial
 *                                  de la línea; sin línea principal no sale.
 *                      'directo' = manda directo; sin asignación usa la línea
 *                                  principal o, si no hay, la instancia de Marketing.
 * defecto (solo correo): 'env' = servidor de correo del sistema (.env);
 *                        'email_settings' = Configuración → Correo.
 *
 * Solo están los avisos que el SISTEMA inicia. Las respuestas dentro de una
 * conversación salen siempre por la línea de esa conversación.
 * El Circuito CC (alarma de Jarvis) NO aparece aquí a propósito.
 *
 * Agregar un consumidor = sumar su clave aquí y usar Servicios::whatsapp() /
 * Servicios::slugWhatsApp() / Servicios::integracion() / Correo::mailer().
 */
return [
    // ── WhatsApp ────────────────────────────────────────────────────────────
    'pagos.pago_recibido' => ['nombre' => 'Aviso «pago recibido» (transferencia SPEI)', 'grupo' => 'Pagos', 'servicio' => 'whatsapp', 'via' => 'linea', 'listo' => true],
    'portalpago.resumen_diario' => ['nombre' => 'Resumen diario de pagos recurrentes (interno)', 'grupo' => 'Pagos', 'servicio' => 'whatsapp', 'via' => 'directo', 'listo' => true],
    'domiciliacion.liga' => ['nombre' => 'Liga para domiciliar la tarjeta', 'grupo' => 'Domiciliación', 'servicio' => 'whatsapp', 'via' => 'linea', 'listo' => true],
    'domiciliacion.avisos_cobro' => ['nombre' => 'Avisos de cobro automático (cobrado, intento fallido, fallo final)', 'grupo' => 'Domiciliación', 'servicio' => 'whatsapp', 'via' => 'linea', 'listo' => true],
    'facturacion.pausas' => ['nombre' => 'Avisos de pausa de facturación', 'grupo' => 'Facturación', 'servicio' => 'whatsapp', 'via' => 'linea', 'listo' => true],
    'facturacion.recordatorio' => ['nombre' => 'Recordatorio de facturación (cliente sin correo)', 'grupo' => 'Facturación', 'servicio' => 'whatsapp', 'via' => 'linea', 'listo' => true],
    'talento.recordatorio_documentos' => ['nombre' => 'Recordatorio de documentos pendientes al personal', 'grupo' => 'Talento', 'servicio' => 'whatsapp', 'via' => 'linea', 'listo' => true],
    'talento.ot_asignada' => ['nombre' => 'Aviso de orden de trabajo asignada al técnico', 'grupo' => 'Talento', 'servicio' => 'whatsapp', 'via' => 'linea', 'listo' => true],
    'embajadores.compartir' => ['nombre' => 'Invitaciones que comparten los embajadores', 'grupo' => 'Embajadores', 'servicio' => 'whatsapp', 'via' => 'linea', 'listo' => true],
    'referidos.avisos' => ['nombre' => 'Avisos del programa de referidos (comisiones, premios…)', 'grupo' => 'Embajadores', 'servicio' => 'whatsapp', 'via' => 'directo', 'listo' => true],
    'warroom.minutas' => ['nombre' => 'Minuta de la junta a los asistentes', 'grupo' => 'WarRoom', 'servicio' => 'whatsapp', 'via' => 'directo', 'listo' => true],
    'warroom.acciones' => ['nombre' => 'Aviso de acción asignada', 'grupo' => 'WarRoom', 'servicio' => 'whatsapp', 'via' => 'directo', 'listo' => true],
    'flotas.geocercas' => ['nombre' => 'Alertas de geocercas', 'grupo' => 'Flotas', 'servicio' => 'whatsapp', 'via' => 'directo', 'listo' => true],
    'flotas.documentos' => ['nombre' => 'Vencimiento de documentos de vehículos', 'grupo' => 'Flotas', 'servicio' => 'whatsapp', 'via' => 'directo', 'listo' => true],
    'flotas.suscripciones' => ['nombre' => 'Vencimiento de la suscripción de Flotas', 'grupo' => 'Flotas', 'servicio' => 'whatsapp', 'via' => 'directo', 'listo' => true],

    // ── Correo ──────────────────────────────────────────────────────────────
    'facturacion.correo_recibos' => ['nombre' => 'Prefacturas, recibos y avisos de facturación', 'grupo' => 'Facturación', 'servicio' => 'correo', 'defecto' => 'env', 'listo' => true],
    'facturacion.correos_sistema' => ['nombre' => 'Proformas, pagos y recordatorios enviados desde Mensajes', 'grupo' => 'Facturación', 'servicio' => 'correo', 'defecto' => 'email_settings', 'listo' => true],
    'crm.correo' => ['nombre' => 'Notificaciones del CRM por correo', 'grupo' => 'CRM', 'servicio' => 'correo', 'defecto' => 'env', 'listo' => true],
    'tickets.correo' => ['nombre' => 'Avisos de tickets por correo', 'grupo' => 'Tickets', 'servicio' => 'correo', 'defecto' => 'env', 'listo' => true],
    'domiciliacion.correo' => ['nombre' => 'Correos de domiciliación (liga y avisos de cobro)', 'grupo' => 'Domiciliación', 'servicio' => 'correo', 'defecto' => 'env', 'listo' => true],
    'portal.correo_recibo' => ['nombre' => 'Recibo de pago del portal de clientes', 'grupo' => 'Pagos', 'servicio' => 'correo', 'defecto' => 'env', 'listo' => true],
    'portalpago.resumen_correo' => ['nombre' => 'Resumen diario de pagos recurrentes por correo (interno)', 'grupo' => 'Pagos', 'servicio' => 'correo', 'defecto' => 'env', 'listo' => true],
    'evaluador.correo' => ['nombre' => 'Resultado del evaluador empresarial', 'grupo' => 'Ventas', 'servicio' => 'correo', 'defecto' => 'env', 'listo' => true],
    'marketing.correo_masivo' => ['nombre' => 'Campañas de correo (publicación y piloto)', 'grupo' => 'Marketing', 'servicio' => 'correo', 'defecto' => 'env', 'listo' => true],
    'flotas.correo' => ['nombre' => 'Alertas de Flotas por correo', 'grupo' => 'Flotas', 'servicio' => 'correo', 'defecto' => 'env', 'listo' => true],
    'talento.correo_vencimientos' => ['nombre' => 'Vencimiento de licencias y credenciales del personal', 'grupo' => 'Talento', 'servicio' => 'correo', 'defecto' => 'env', 'listo' => true],

    // ── Otros servicios ─────────────────────────────────────────────────────
    'mapas.google' => ['nombre' => 'Mapas de Google (satélite, ubicaciones, MegaFamilia)', 'grupo' => 'Mapas', 'servicio' => 'mapas', 'listo' => true],
    'marketing.broll' => ['nombre' => 'Videos de stock para los videos (Pexels)', 'grupo' => 'Marketing', 'servicio' => 'imagenes_stock', 'listo' => true],
    'marketing.meta' => ['nombre' => 'Conexión con Facebook e Instagram (publicar y renovar permisos)', 'grupo' => 'Marketing', 'servicio' => 'redes_sociales', 'listo' => true],
];
