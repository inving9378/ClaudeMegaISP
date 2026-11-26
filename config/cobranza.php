<?php

return [
    // Voz OpenAI TTS usada por CobranzaTtsService para generar el audio del
    // blaster de cobranza. La API key de OpenAI se resuelve por el Hub de
    // integraciones (UsesApiIntegration), no por aquí — ver item #273.
    'blaster_tts_voice' => env('BLASTER_TTS_VOICE', 'nova'),

    // Carpeta donde CobranzaTtsService escribe los WAV generados (debe existir
    // en el servidor y ser legible por Asterisk vía Playback/Background). Item
    // roadmap #9990714 (despersonalización VoIP): antes era una ruta fija en
    // el código; ahora cada instalación puede apuntar a su propia carpeta de
    // sonidos sin tocar código.
    'blaster_audio_base_path' => env('COBRANZA_AUDIO_BASE_PATH', '/var/lib/asterisk/sounds/cobranza/'),

    // Canales simultáneos de una campaña que no fijó los suyos (25-nov). Antes salían
    // hasta 50 llamadas a la vez y la troncal rechazaba las que no cabían.
    'canales_por_defecto' => (int) env('COBRANZA_CANALES_POR_DEFECTO', 10),
];
