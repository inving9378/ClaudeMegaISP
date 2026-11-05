<?php

// L2 Talento: ciclo de revisión de garantías (abierta → en revisión → resuelta) con causa, responsabilidad y
// resolución. Ninguna garantía produce efecto económico sin resolución registrada. Apagado = comportamiento actual.
return [
    'garantia_l2' => (bool) env('UNIFICACION_GARANTIA_L2', false),
];
