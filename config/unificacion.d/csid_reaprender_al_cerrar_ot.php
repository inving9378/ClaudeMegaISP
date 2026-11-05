<?php

// #1144 (#576 Fase 2b): al completar una OT de cambio de equipo, re-aprende la MAC PPPoE del cliente. Apagado por defecto.
return ['csid_reaprender_al_cerrar_ot' => (bool) env('UNIFICACION_CSID_REAPRENDER_AL_CERRAR_OT', false)];
