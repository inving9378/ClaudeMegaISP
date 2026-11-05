<?php

// D5a Talento: MegaFamilia (PUT tecnico/ordenes/{id}) no pone Done a una orden de Talento (tasks.talento_type_id)
// directamente: pasa por OrdenTrabajoUnifiedService::completar() y su compuerta de evidencias y dBm.
// Apagado = comportamiento actual (cierra sin compuerta).
return ['ordenes_d5a' => (bool) env('UNIFICACION_ORDENES_D5A', false)];
