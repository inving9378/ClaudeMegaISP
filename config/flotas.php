<?php

return [

    /*
    |--------------------------------------------------------------------------
    | OCR de documentos de vehículo (item #580, Fase 7)
    |--------------------------------------------------------------------------
    |
    | El OCR NO tiene cliente HTTP ni API key propios: se conecta al servicio
    | único de IA (tabla `ia_proveedores` / pantalla /ia/configuracion) a través
    | de IAAdaptadorFactory. Cambiar de proveedor o de modelo se hace ALLÁ, no
    | aquí.
    |
    */

    'ocr' => [

        // Kill switch. En false, la subida de documentos sigue funcionando
        // exactamente igual: simplemente no se ofrece la lectura por IA.
        'enabled' => env('FLOTAS_OCR_ENABLED', true),

        /*
         * Confianza mínima que debe traer la FECHA DE VENCIMIENTO para que el
         * documento NO se marque "revisar manualmente". Es el dato que alimenta
         * las alertas de vencimiento, así que es el que manda.
         * Valores: alta | media | baja.
         */
        'confianza_minima' => env('FLOTAS_OCR_CONFIANZA_MINIMA', 'media'),

        // Tope propio del OCR (el `store` sigue con su límite de 10 MB).
        'max_bytes' => 10 * 1024 * 1024,

        /*
         * PDF solo lo lee de forma nativa el adaptador de Claude (bloque
         * 'document'). Los adaptadores de OpenAI/Gemini mandan todo como imagen
         * y tronarían con un PDF → el servicio lo corta antes con un mensaje
         * claro en vez de reventar.
         */
        'mimes' => ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'],

        'max_tokens' => 1024,
    ],

];
