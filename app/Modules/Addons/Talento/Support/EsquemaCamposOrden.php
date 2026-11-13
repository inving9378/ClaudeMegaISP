<?php

namespace App\Modules\Addons\Talento\Support;

/**
 * Esquema de campos de las plantillas de orden de Talento (item #1200).
 *
 * Lógica pura (sin BD ni contenedor) para que la compartan store() (Fase A), el modal (Fase B),
 * el editor de plantillas (Fase C) y la captura de cierre (Fase D, #1202), y se pruebe aislada.
 *
 * Un campo:
 *   key (slug único), label, type, required, when (creacion|cierre), options (seleccion y
 *   multi_seleccion), unit, help, sort_order, identificador (arma el título de la orden).
 *   Tipo `tabla`, además: columns [{key, label, type, unit, options}] y
 *     - filas fijas: rows_count + row_label_prefix ("Conector" → Conector 1..N), o
 *     - filas dinámicas: dynamic=true + max_rows.
 *   En esta fase `required` no se exige en tablas: solo se validan los tipos de cada celda.
 *
 * Los errores se devuelven como mapa {ruta: mensaje} (ruta = "datos.<key>" o
 * "datos.<key>.<fila>.<columna>"); quien llama decide cómo responder.
 */
final class EsquemaCamposOrden
{
    public const TIPOS = [
        'texto', 'texto_largo', 'numero', 'seleccion', 'multi_seleccion',
        'si_no', 'fecha', 'coordenadas', 'tabla',
    ];

    /** Tipos permitidos en las columnas de una tabla. */
    public const TIPOS_COLUMNA = ['texto', 'numero', 'seleccion', 'si_no', 'fecha', 'coordenadas'];

    /** Tipos que pueden marcarse como identificador del título. */
    public const TIPOS_IDENTIFICADOR = ['texto', 'numero', 'seleccion'];

    public const MOMENTO_CREACION = 'creacion';
    public const MOMENTO_CIERRE = 'cierre';
    public const MOMENTOS = [self::MOMENTO_CREACION, self::MOMENTO_CIERRE];

    public const MAX_FILAS_TABLA = 100;
    public const MAX_TEXTO = 255;
    public const MAX_TEXTO_LARGO = 5000;

    private const KEY_REGEX = '/^[a-z][a-z0-9_]{0,59}$/';

    // ── Esquema (definición de la plantilla) ────────────────────────────────────────────────

    /**
     * Normaliza y valida la definición de campos de una plantilla.
     *
     * @return array{fields: array<int, array>, errors: array<string, string>}
     */
    public static function normalizarEsquema(array $fields): array
    {
        $errors = [];
        $out = [];
        $keys = [];

        foreach (array_values($fields) as $i => $f) {
            $ruta = "fields.$i";
            if (! is_array($f)) {
                $errors[$ruta] = 'Cada campo debe ser un objeto.';
                continue;
            }

            $key = is_string($f['key'] ?? null) ? trim($f['key']) : '';
            $label = is_string($f['label'] ?? null) ? trim($f['label']) : '';
            $type = $f['type'] ?? null;
            $when = $f['when'] ?? self::MOMENTO_CREACION;

            if (! preg_match(self::KEY_REGEX, $key)) {
                $errors["$ruta.key"] = 'La clave debe empezar con letra y llevar solo minúsculas, números y guion bajo.';
            } elseif (isset($keys[$key])) {
                $errors["$ruta.key"] = "La clave «{$key}» está repetida.";
            }
            $keys[$key] = true;

            if ($label === '' || mb_strlen($label) > 120) {
                $errors["$ruta.label"] = 'La etiqueta es obligatoria (máximo 120 caracteres).';
            }
            if (! in_array($type, self::TIPOS, true)) {
                $errors["$ruta.type"] = 'Tipo de campo no válido.';
                continue;
            }
            if (! in_array($when, self::MOMENTOS, true)) {
                $errors["$ruta.when"] = 'El momento debe ser «creacion» o «cierre».';
            }

            $campo = [
                'key'        => $key,
                'label'      => $label,
                'type'       => $type,
                'required'   => self::bool($f['required'] ?? false),
                'when'       => $when,
                'sort_order' => isset($f['sort_order']) && is_numeric($f['sort_order']) ? (int) $f['sort_order'] : ($i + 1) * 10,
            ];

            $unit = self::textoOpcional($f['unit'] ?? null);
            if ($unit !== null) {
                if (mb_strlen($unit) > 20) {
                    $errors["$ruta.unit"] = 'La unidad admite máximo 20 caracteres.';
                }
                $campo['unit'] = $unit;
            }
            $help = self::textoOpcional($f['help'] ?? null);
            if ($help !== null) {
                if (mb_strlen($help) > 255) {
                    $errors["$ruta.help"] = 'La ayuda admite máximo 255 caracteres.';
                }
                $campo['help'] = $help;
            }

            if (in_array($type, ['seleccion', 'multi_seleccion'], true)) {
                [$opts, $err] = self::normalizarOpciones($f['options'] ?? null);
                if ($err) {
                    $errors["$ruta.options"] = $err;
                }
                $campo['options'] = $opts;
            }

            if (self::bool($f['identificador'] ?? false)) {
                if (! in_array($type, self::TIPOS_IDENTIFICADOR, true) || $when !== self::MOMENTO_CREACION) {
                    $errors["$ruta.identificador"] = 'Solo un campo de texto, número o selección que se pide al crear puede identificar la orden.';
                }
                $campo['identificador'] = true;
            }

            if ($type === 'tabla') {
                $campo = array_merge($campo, self::normalizarTabla($f, $ruta, $errors));
            }

            $out[] = $campo;
        }

        usort($out, fn ($a, $b) => $a['sort_order'] <=> $b['sort_order']);

        return ['fields' => $out, 'errors' => $errors];
    }

    private static function normalizarTabla(array $f, string $ruta, array &$errors): array
    {
        $cols = [];
        $colKeys = [];
        $columns = is_array($f['columns'] ?? null) ? array_values($f['columns']) : [];
        if (! $columns) {
            $errors["$ruta.columns"] = 'La tabla necesita al menos una columna.';
        }
        foreach ($columns as $j => $c) {
            $cr = "$ruta.columns.$j";
            if (! is_array($c)) {
                $errors[$cr] = 'Cada columna debe ser un objeto.';
                continue;
            }
            $ck = is_string($c['key'] ?? null) ? trim($c['key']) : '';
            $cl = is_string($c['label'] ?? null) ? trim($c['label']) : '';
            $ct = $c['type'] ?? 'texto';
            if (! preg_match(self::KEY_REGEX, $ck)) {
                $errors["$cr.key"] = 'La clave de la columna debe empezar con letra y llevar solo minúsculas, números y guion bajo.';
            } elseif (isset($colKeys[$ck])) {
                $errors["$cr.key"] = "La columna «{$ck}» está repetida.";
            }
            $colKeys[$ck] = true;
            if ($cl === '' || mb_strlen($cl) > 80) {
                $errors["$cr.label"] = 'La etiqueta de la columna es obligatoria (máximo 80 caracteres).';
            }
            if (! in_array($ct, self::TIPOS_COLUMNA, true)) {
                $errors["$cr.type"] = 'Tipo de columna no válido.';
            }
            $col = ['key' => $ck, 'label' => $cl, 'type' => $ct];
            $unit = self::textoOpcional($c['unit'] ?? null);
            if ($unit !== null) {
                $col['unit'] = mb_substr($unit, 0, 20);
            }
            if ($ct === 'seleccion') {
                [$opts, $err] = self::normalizarOpciones($c['options'] ?? null);
                if ($err) {
                    $errors["$cr.options"] = $err;
                }
                $col['options'] = $opts;
            }
            $cols[] = $col;
        }

        $extra = ['columns' => $cols];
        if (self::bool($f['dynamic'] ?? false)) {
            $max = (int) ($f['max_rows'] ?? 20);
            if ($max < 1 || $max > self::MAX_FILAS_TABLA) {
                $errors["$ruta.max_rows"] = 'Máximo de filas entre 1 y ' . self::MAX_FILAS_TABLA . '.';
            }
            $extra['dynamic'] = true;
            $extra['max_rows'] = $max;
        } else {
            $n = (int) ($f['rows_count'] ?? 0);
            if ($n < 1 || $n > self::MAX_FILAS_TABLA) {
                $errors["$ruta.rows_count"] = 'Indica cuántas filas fijas lleva la tabla (1 a ' . self::MAX_FILAS_TABLA . ') o márcala como dinámica.';
            }
            $extra['dynamic'] = false;
            $extra['rows_count'] = $n;
            $extra['row_label_prefix'] = mb_substr(self::textoOpcional($f['row_label_prefix'] ?? null) ?? 'Fila', 0, 40);
        }

        return $extra;
    }

    /** @return array{0: array<int,string>, 1: ?string} */
    private static function normalizarOpciones($options): array
    {
        if (! is_array($options)) {
            return [[], 'Agrega al menos una opción.'];
        }
        $opts = [];
        foreach ($options as $o) {
            if (! is_scalar($o)) {
                return [$opts, 'Las opciones deben ser textos.'];
            }
            $o = trim((string) $o);
            if ($o !== '' && ! in_array($o, $opts, true)) {
                $opts[] = $o;
            }
        }

        return [$opts, $opts ? null : 'Agrega al menos una opción.'];
    }

    /**
     * Checklist de materiales: acepta textos sueltos o {label, cantidad?, unidad?}.
     *
     * @return array{items: array<int, array{label: string, cantidad: ?float, unidad: ?string}>, errors: array<string,string>}
     */
    public static function normalizarChecklist(?array $checklist): array
    {
        $items = [];
        $errors = [];
        foreach (array_values($checklist ?? []) as $i => $c) {
            $raw = is_array($c) ? $c : ['label' => $c];
            $label = is_scalar($raw['label'] ?? null) ? trim((string) $raw['label']) : '';
            if ($label === '') {
                continue; // renglones vacíos del editor se ignoran
            }
            if (mb_strlen($label) > 255) {
                $errors["checklist.$i.label"] = 'Cada material admite máximo 255 caracteres.';
            }
            $cantidad = $raw['cantidad'] ?? null;
            if ($cantidad === '' || $cantidad === null) {
                $cantidad = null;
            } elseif (! is_numeric($cantidad) || (float) $cantidad < 0) {
                $errors["checklist.$i.cantidad"] = 'La cantidad debe ser un número mayor o igual a cero.';
                $cantidad = null;
            } else {
                $cantidad = (float) $cantidad;
            }
            $unidad = self::textoOpcional($raw['unidad'] ?? null);
            if ($unidad !== null && mb_strlen($unidad) > 30) {
                $errors["checklist.$i.unidad"] = 'La unidad admite máximo 30 caracteres.';
            }
            $items[] = ['label' => $label, 'cantidad' => $cantidad, 'unidad' => $unidad];
        }

        return ['items' => $items, 'errors' => $errors];
    }

    // ── Datos capturados ────────────────────────────────────────────────────────────────────

    /** Campos que se capturan en el momento indicado, en su orden. */
    public static function camposDe(array $fields, string $momento): array
    {
        return array_values(array_filter($fields, fn ($f) => ($f['when'] ?? self::MOMENTO_CREACION) === $momento));
    }

    /**
     * Valida y normaliza los valores capturados para un momento (creación o cierre).
     * Llaves desconocidas, o de otro momento, se rechazan. Con $exigirObligatorios = false (el técnico
     * guarda su avance antes de cerrar, #1202) un obligatorio vacío no es error; se exige al completar.
     *
     * @return array{values: array<string, mixed>, errors: array<string, string>}
     */
    public static function validarDatos(array $fields, array $datos, string $momento = self::MOMENTO_CREACION, bool $exigirObligatorios = true): array
    {
        $errors = [];
        $values = [];
        $porKey = [];
        foreach ($fields as $f) {
            $porKey[$f['key']] = $f;
        }

        foreach (array_keys($datos) as $k) {
            if (! isset($porKey[$k])) {
                $errors["datos.$k"] = 'Campo desconocido para esta plantilla.';
            } elseif (($porKey[$k]['when'] ?? self::MOMENTO_CREACION) !== $momento) {
                $errors["datos.$k"] = $momento === self::MOMENTO_CREACION
                    ? 'Este campo lo captura el técnico al cerrar la orden.'
                    : 'Este campo se captura al crear la orden.';
            }
        }

        foreach (self::camposDe($fields, $momento) as $f) {
            $k = $f['key'];
            $raw = $datos[$k] ?? null;

            if ($f['type'] === 'tabla') {
                $filas = self::validarTabla($f, $raw, "datos.$k", $errors);
                if ($filas) {
                    $values[$k] = $filas;
                }
                continue;
            }

            if (self::vacio($raw)) {
                if (! empty($f['required']) && $exigirObligatorios) {
                    $errors["datos.$k"] = 'Este campo es obligatorio.';
                }
                continue;
            }

            [$v, $err] = self::normalizarValor($f, $raw);
            if ($err !== null) {
                $errors["datos.$k"] = $err;
                continue;
            }
            if (! self::vacio($v)) {
                $values[$k] = $v;
            } elseif (! empty($f['required']) && $exigirObligatorios) {
                $errors["datos.$k"] = 'Este campo es obligatorio.';
            }
        }

        return ['values' => $values, 'errors' => $errors];
    }

    /** #1202: etiquetas de los campos obligatorios de un momento que siguen vacíos (tablas incluidas). */
    public static function obligatoriosVacios(array $fields, array $values, string $momento): array
    {
        $faltan = [];
        foreach (self::camposDe($fields, $momento) as $f) {
            if (! empty($f['required']) && self::vacio($values[$f['key']] ?? null)) {
                $faltan[] = (string) ($f['label'] ?? $f['key']);
            }
        }

        return $faltan;
    }

    /** @return array<int, array> filas normalizadas ([] si la tabla quedó vacía) */
    private static function validarTabla(array $f, $raw, string $ruta, array &$errors): array
    {
        if (self::vacio($raw)) {
            return [];
        }
        if (! is_array($raw) || ! array_is_list($raw)) {
            $errors[$ruta] = 'La tabla debe ser una lista de filas.';
            return [];
        }
        $limite = ! empty($f['dynamic']) ? (int) ($f['max_rows'] ?? 0) : (int) ($f['rows_count'] ?? 0);
        if (count($raw) > $limite) {
            $errors[$ruta] = "La tabla admite máximo {$limite} filas.";
            return [];
        }

        $cols = [];
        foreach ($f['columns'] ?? [] as $c) {
            $cols[$c['key']] = $c;
        }

        $filas = [];
        foreach ($raw as $i => $fila) {
            if (self::vacio($fila)) {
                $filas[$i] = [];
                continue;
            }
            if (! is_array($fila) || array_is_list($fila)) {
                $errors["$ruta.$i"] = 'Cada fila debe ser un objeto con sus columnas.';
                continue;
            }
            $norm = [];
            foreach ($fila as $ck => $cv) {
                if (! isset($cols[$ck])) {
                    $errors["$ruta.$i.$ck"] = 'Columna desconocida.';
                    continue;
                }
                if (self::vacio($cv)) {
                    continue;
                }
                [$v, $err] = self::normalizarValor($cols[$ck], $cv);
                if ($err !== null) {
                    $errors["$ruta.$i.$ck"] = $err;
                } elseif (! self::vacio($v)) {
                    $norm[$ck] = $v;
                }
            }
            $filas[$i] = $norm;
        }

        if (! empty($f['dynamic'])) {
            // Dinámicas: la posición no importa; las filas vacías se descartan.
            return array_values(array_filter($filas, fn ($r) => $r !== []));
        }

        // Fijas: la posición sí importa (Conector 1, 2…); solo se recortan las vacías del final.
        while ($filas && end($filas) === []) {
            array_pop($filas);
        }

        return array_values($filas);
    }

    /** @return array{0: mixed, 1: ?string} [valor normalizado, error] */
    private static function normalizarValor(array $f, $raw): array
    {
        switch ($f['type']) {
            case 'texto':
            case 'texto_largo':
                if (! is_scalar($raw) || is_bool($raw)) {
                    return [null, 'Debe ser texto.'];
                }
                $v = trim((string) $raw);
                $max = $f['type'] === 'texto' ? self::MAX_TEXTO : self::MAX_TEXTO_LARGO;
                return mb_strlen($v) > $max ? [null, "Máximo {$max} caracteres."] : [$v, null];

            case 'numero':
                if (is_bool($raw) || ! is_scalar($raw)) {
                    return [null, 'Debe ser un número.'];
                }
                $s = str_replace(',', '.', trim((string) $raw));
                if (! is_numeric($s) || ! is_finite((float) $s)) {
                    return [null, 'Debe ser un número.'];
                }
                $n = $s + 0;
                return [is_float($n) && floor($n) == $n && abs($n) < PHP_INT_MAX ? (int) $n : $n, null];

            case 'seleccion':
                $v = is_scalar($raw) ? trim((string) $raw) : null;
                return in_array($v, $f['options'] ?? [], true) ? [$v, null] : [null, 'Elige una de las opciones.'];

            case 'multi_seleccion':
                if (! is_array($raw)) {
                    return [null, 'Debe ser una lista de opciones.'];
                }
                $elegidas = [];
                foreach ($raw as $o) {
                    $o = is_scalar($o) ? trim((string) $o) : null;
                    if (! in_array($o, $f['options'] ?? [], true)) {
                        return [null, 'Una de las opciones elegidas no es válida.'];
                    }
                    $elegidas[$o] = true;
                }
                // En el orden de las opciones de la plantilla, sin repetidos.
                return [array_values(array_filter($f['options'], fn ($o) => isset($elegidas[$o]))), null];

            case 'si_no':
                if (is_bool($raw)) {
                    return [$raw, null];
                }
                $s = mb_strtolower(trim((string) (is_scalar($raw) ? $raw : '')));
                if (in_array($s, ['1', 'true', 'si', 'sí'], true)) {
                    return [true, null];
                }
                if (in_array($s, ['0', 'false', 'no'], true)) {
                    return [false, null];
                }
                return [null, 'Debe ser sí o no.'];

            case 'fecha':
                $s = is_string($raw) ? trim($raw) : '';
                if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $s, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
                    return [$s, null];
                }
                return [null, 'Fecha no válida (AAAA-MM-DD).'];

            case 'coordenadas':
                $s = is_string($raw) ? trim($raw) : '';
                if (preg_match('/^(-?\d{1,3}(?:\.\d+)?)\s*,\s*(-?\d{1,3}(?:\.\d+)?)$/', $s, $m)) {
                    $lat = (float) $m[1];
                    $lng = (float) $m[2];
                    if ($lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180) {
                        return [$m[1] . ',' . $m[2], null];
                    }
                }
                return [null, 'Usa el formato «latitud,longitud», por ejemplo 19.432608,-99.133209.'];
        }

        return [null, 'Tipo de campo no válido.'];
    }

    // ── Presentación (título y descripción de la tarea) ─────────────────────────────────────

    /**
     * Título de la orden: "[Tipo] " + título de la plantilla (si no repite el tipo) + los
     * campos identificadores capturados ("Zona 3 · Caja 12").
     */
    public static function titulo(string $tipo, ?string $tituloPlantilla, array $fields, array $values): string
    {
        $partes = [];
        $tp = trim((string) $tituloPlantilla);
        if ($tp !== '' && self::comparable($tp) !== self::comparable($tipo)) {
            $partes[] = $tp;
        }
        foreach ($fields as $f) {
            if (empty($f['identificador']) || ! array_key_exists($f['key'], $values)) {
                continue;
            }
            $v = self::formatearValor($f, $values[$f['key']]);
            if ($v === null || $v === '') {
                continue;
            }
            // Si capturaron "Zona 3" en el campo Zona, no se repite la etiqueta.
            $partes[] = str_starts_with(self::comparable($v), self::comparable($f['label'])) ? $v : $f['label'] . ' ' . $v;
        }

        $titulo = '[' . $tipo . '] ' . ($partes ? implode(' · ', $partes) : 'Nueva orden');

        return mb_substr($titulo, 0, 255);
    }

    /**
     * Descripción legible para la app y el portal, que solo muestran tasks.description:
     * un renglón "Etiqueta: valor" por campo capturado, las instrucciones de la plantilla, los
     * materiales, qué se captura al cerrar y las notas del usuario.
     */
    public static function descripcion(array $fields, array $values, ?string $descripcionPlantilla, array $checklist, ?string $notas): string
    {
        $bloques = [];

        $renglones = [];
        foreach ($fields as $f) {
            if (! array_key_exists($f['key'], $values)) {
                continue;
            }
            if ($f['type'] === 'tabla') {
                $filas = self::renglonesTabla($f, $values[$f['key']]);
                if ($filas) {
                    $renglones[] = $f['label'] . ':';
                    foreach ($filas as $r) {
                        $renglones[] = '  ' . $r;
                    }
                }
                continue;
            }
            $v = self::formatearValor($f, $values[$f['key']]);
            if ($v !== null && $v !== '') {
                $renglones[] = $f['label'] . ': ' . $v;
            }
        }
        if ($renglones) {
            $bloques[] = implode("\n", $renglones);
        }

        $desc = trim((string) $descripcionPlantilla);
        if ($desc !== '') {
            $bloques[] = $desc;
        }

        if ($checklist) {
            $mat = ['MATERIALES:'];
            foreach ($checklist as $c) {
                $linea = '- ' . $c['label'];
                if (($c['cantidad'] ?? null) !== null) {
                    $linea .= ': ' . self::numero($c['cantidad']) . (! empty($c['unidad']) ? ' ' . $c['unidad'] : '');
                }
                $mat[] = $linea;
            }
            $bloques[] = implode("\n", $mat);
        }

        $cierre = array_map(
            fn ($f) => $f['label'] . (! empty($f['unit']) ? ' (' . $f['unit'] . ')' : ''),
            self::camposDe($fields, self::MOMENTO_CIERRE)
        );
        if ($cierre) {
            $bloques[] = 'AL CERRAR SE CAPTURA: ' . implode(', ', $cierre);
        }

        $notas = trim((string) $notas);
        if ($notas !== '') {
            $bloques[] = "NOTAS:\n" . $notas;
        }

        return implode("\n\n", $bloques);
    }

    /** @return array<int, string> un renglón por fila con datos */
    private static function renglonesTabla(array $f, $filas): array
    {
        if (! is_array($filas)) {
            return [];
        }
        $out = [];
        foreach (array_values($filas) as $i => $fila) {
            if (! is_array($fila) || $fila === []) {
                continue;
            }
            $celdas = [];
            foreach ($f['columns'] ?? [] as $c) {
                if (! array_key_exists($c['key'], $fila)) {
                    continue;
                }
                $v = self::formatearValor($c, $fila[$c['key']]);
                if ($v !== null && $v !== '') {
                    $celdas[] = $c['label'] . ': ' . $v;
                }
            }
            if ($celdas) {
                $prefijo = ! empty($f['dynamic']) ? 'Fila' : ($f['row_label_prefix'] ?? 'Fila');
                $out[] = $prefijo . ' ' . ($i + 1) . ': ' . implode(' · ', $celdas);
            }
        }

        return $out;
    }

    /**
     * Las notas del usuario dentro de una descripción armada por descripcion() (el bloque «NOTAS:»
     * del final), o null si no tiene. Sirve para editar las notas sin tocar el resto del texto.
     */
    public static function notasDe(?string $descripcion): ?string
    {
        $d = (string) $descripcion;
        if (str_starts_with($d, "NOTAS:\n")) {
            $notas = substr($d, 7);
        } elseif (($p = strrpos($d, "\n\nNOTAS:\n")) !== false) {
            $notas = substr($d, $p + 9);
        } else {
            return null;
        }
        $notas = trim($notas);

        return $notas === '' ? null : $notas;
    }

    /** Valor de un campo como texto ("Sí", "-20.5 dBm", "09/11/2026"…). */
    public static function formatearValor(array $f, $v): ?string
    {
        if ($v === null) {
            return null;
        }
        switch ($f['type']) {
            case 'si_no':
                return $v ? 'Sí' : 'No';
            case 'numero':
                return self::numero($v) . (! empty($f['unit']) ? ' ' . $f['unit'] : '');
            case 'multi_seleccion':
                return is_array($v) ? implode(', ', $v) : (string) $v;
            case 'fecha':
                return preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', (string) $v, $m) ? "{$m[3]}/{$m[2]}/{$m[1]}" : (string) $v;
            case 'tabla':
                return null;
            default:
                return is_scalar($v) ? (string) $v : null;
        }
    }

    // ── Utilidades ──────────────────────────────────────────────────────────────────────────

    private static function numero($n): string
    {
        if (! is_numeric($n)) {
            return (string) $n;
        }
        $f = (float) $n;
        if (floor($f) == $f && abs($f) < 1e15) {
            return (string) (int) $f;
        }

        return rtrim(rtrim(sprintf('%.6F', $f), '0'), '.');
    }

    private static function vacio($v): bool
    {
        return $v === null || $v === [] || (is_string($v) && trim($v) === '');
    }

    private static function bool($v): bool
    {
        return $v === true || $v === 1 || $v === '1' || $v === 'true';
    }

    private static function textoOpcional($v): ?string
    {
        if (! is_scalar($v) || is_bool($v)) {
            return null;
        }
        $v = trim((string) $v);

        return $v === '' ? null : $v;
    }

    private static function comparable(string $s): string
    {
        $s = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $s)));

        return strtr($s, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u']);
    }
}
