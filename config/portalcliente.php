<?php

return [
    // Dominio dedicado del Portal Cliente (item roadmap #144). En DEV se prueba
    // vía /etc/hosts (sin DNS público); en PROD será el registro A real.
    // Item #553: minutos que dura «Entrar como este cliente» antes de caducar.
    'impersonacion_minutos' => (int) env('PORTAL_IMPERSONACION_MINUTOS', 30),

    'domain' => env('PORTAL_CLIENTE_DOMAIN', 'portal.meganet.mx'),

    // Item #473: recuperar contraseña con código por WhatsApp. Apagado, sigue el flujo por teléfono de
    // siempre. Encender solo con WhatsApp confirmado en producción (línea activa y WHATSAPP_SENDER_ENABLED):
    // el POST de un solo paso deja de cambiar contraseñas y, sin envío real, nadie podría recuperarla.
    'recuperar_otp' => env('PORTAL_RECUPERAR_OTP', false),

    // Configuración → Portal Cliente (presentación administrable de las pantallas de acceso).
    // Aviso de privacidad del rastreo en Mis Vehículos (#636). TEXTO PROVISIONAL: debe revisarlo un abogado.
    // Al cambiar el texto, subir 'version' para que los clientes lo vuelvan a aceptar.
    'flotas_privacidad' => [
        'version' => '1',
        'texto'   => 'Al usar el mapa y los recorridos de Mis Vehículos declaras que eres responsable de informar a las '
                   . 'personas que conducen o usan tus unidades que dichas unidades cuentan con rastreo GPS, y de contar '
                   . 'con su consentimiento cuando la ley lo requiera. Meganet solo te muestra la ubicación de TUS unidades.',
    ],

    'apariencia' => [
        // Disco PRIVADO donde se guardan las imágenes re-codificadas; se sirven solo por rutas
        // controladas (público: solo lo publicado; admin: con permiso).
        'disco' => env('PORTAL_CLIENTE_APARIENCIA_DISCO', 'local'),
        'directorio' => 'portal-cliente/apariencia',
        'peso_maximo_kb' => 10240,
        'pixeles_maximos' => 36_000_000,
        'calidad_webp' => 82,
        // Una imagen subida que ninguna versión llegó a usar se borra pasado este tiempo.
        'gracia_huerfanas_horas' => 24,
    ],
];
