<?php

return [
    // Voz OpenAI TTS usada por CobranzaTtsService para generar el audio del
    // blaster de cobranza. La API key de OpenAI se resuelve por el Hub de
    // integraciones (UsesApiIntegration), no por aquí — ver item #273.
    'blaster_tts_voice' => env('BLASTER_TTS_VOICE', 'nova'),
];
