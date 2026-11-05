<?php

// D6 Talento: la medición (dBm) es obligatoria para completar cuando el tipo de orden la exige;
// subirEvidencia valida estado y que el tipo esté en la matriz del tipo de orden; FieldMediaService
// guarda evidence_type_id (mapeo type -> tipo de evidencia) para que cuente en la matriz.
// Apagado = comportamiento actual.
return ['evidencias_d6' => (bool) env('UNIFICACION_EVIDENCIAS_D6', false)];
