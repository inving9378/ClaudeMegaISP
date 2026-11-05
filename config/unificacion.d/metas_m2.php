<?php

// M2 Talento: metas versionadas en talento_metas (una por concepto). Apagado = nada las consulta;
// no cambia reglas económicas ni pagos.
return ['metas_m2' => (bool) env('UNIFICACION_METAS_M2', false)];
