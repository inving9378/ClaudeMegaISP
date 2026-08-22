<?php

return [
    /*
     * Item #1018 — disciplina expansiva. Ninguna migración pendiente puede traer una
     * operación destructiva (dropColumn/dropTable/renameColumn/truncate/change() reductor)
     * salvo que el item ligado a la rama declare explícitamente `contraccion_de: V{n}` en su
     * spec y esa versión ya lleve esta cantidad de días aplicada en producción
     * (releases.aplicada_en_prod_at, item #1017). Sin ese campo poblado, la excepción nunca
     * aplica (fail-closed: no se inventa una fecha de madurez).
     */
    'min_dias_antes_de_contraccion' => (int) env('MIGRATION_GUARD_MIN_DIAS_CONTRACCION', 14),
];
