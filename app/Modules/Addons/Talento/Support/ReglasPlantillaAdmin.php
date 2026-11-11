<?php

namespace App\Modules\Addons\Talento\Support;

use Illuminate\Support\Str;

/**
 * Item #1200 Fase C — reglas de la pantalla «Plantillas de orden», ENCIMA del validador de la Fase A.
 *
 * EsquemaCamposOrden sigue siendo la única fuente de verdad del formato de los campos: aquí solo se
 * generan las claves a partir de la etiqueta (y se conservan las de los campos que ya existían, para
 * que renombrar no cambie la clave) y se agregan los topes del editor. Identificadores del título:
 * los de la Fase A (varios, de texto, número o selección, solo entre los campos «al crear»).
 */
class ReglasPlantillaAdmin
{
    public const MAX_CAMPOS = 40;
    public const MAX_ETIQUETA = 80;
    public const MAX_UNIDAD = 12;
    public const MAX_AYUDA = 200;
    public const MIN_OPCIONES = 2;
    public const MAX_OPCIONES = 30;
    public const MAX_CLAVE = 40;
    public const MAX_MATERIALES = 100;

    /** Clave desde la etiqueta: minúsculas, sin acentos, snake_case, hasta 40 caracteres y única. */
    public static function claveDesde(string $etiqueta, array $usadas, string $prefijo = 'campo'): string
    {
        $base = Str::of(Str::ascii($etiqueta))->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->value();
        if (! preg_match('/^[a-z]/', $base)) {
            $base = $prefijo . ($base !== '' ? '_' . $base : '');
        }
        $base = rtrim(substr($base, 0, self::MAX_CLAVE), '_');

        $clave = $base;
        for ($n = 2; in_array($clave, $usadas, true); $n++) {
            $sufijo = '_' . $n;
            $clave = rtrim(substr($base, 0, self::MAX_CLAVE - strlen($sufijo)), '_') . $sufijo;
        }

        return $clave;
    }

    /**
     * Pone la clave a cada campo (y a cada columna de tabla). Un campo cuya clave ya existía en la
     * plantilla guardada la conserva; los nuevos la reciben de su etiqueta. Lo que mande el
     * navegador como clave de un campo nuevo se ignora.
     *
     * @param  array  $campos     lo que manda el editor
     * @param  array  $guardados  fields de la plantilla en la base ([] si es nueva)
     */
    public static function asignarClaves(array $campos, array $guardados): array
    {
        $previos = [];
        foreach ($guardados as $g) {
            if (is_array($g) && isset($g['key'])) {
                $previos[$g['key']] = $g;
            }
        }

        $usadas = [];
        $salida = [];
        foreach (array_values($campos) as $c) {
            if (! is_array($c)) {
                $salida[] = $c;
                continue;
            }
            $clave = is_string($c['key'] ?? null) ? $c['key'] : '';
            if (! isset($previos[$clave]) || in_array($clave, $usadas, true)) {
                $clave = self::claveDesde((string) ($c['label'] ?? ''), array_merge($usadas, self::clavesPrevias($campos, $previos)));
            }
            $usadas[] = $clave;
            $c['key'] = $clave;

            if (($c['type'] ?? null) === 'tabla') {
                $c['columns'] = self::asignarClavesColumnas(
                    is_array($c['columns'] ?? null) ? $c['columns'] : [],
                    $previos[$clave]['columns'] ?? []
                );
            }
            $salida[] = $c;
        }

        return $salida;
    }

    /**
     * Dos campos que dicen ser el mismo campo guardado (misma clave) son un error del editor: se
     * rechaza en lugar de renombrar uno en silencio.
     *
     * @return array<string, string>
     */
    public static function clavesRepetidas(array $campos, array $guardados): array
    {
        $existentes = array_flip(array_filter(array_map(fn ($g) => is_array($g) ? ($g['key'] ?? null) : null, $guardados)));
        $vistas = [];
        $errores = [];
        foreach (array_values($campos) as $i => $c) {
            $k = is_array($c) && is_string($c['key'] ?? null) ? $c['key'] : null;
            if ($k === null || ! isset($existentes[$k])) {
                continue;
            }
            if (isset($vistas[$k])) {
                $errores["fields.$i.key"] = "La clave «{$k}» está repetida: ese campo ya existe en la plantilla.";
            }
            $vistas[$k] = true;
        }

        return $errores;
    }

    /** Claves que siguen presentes en el editor y ya existían: no se pueden dar a un campo nuevo. */
    private static function clavesPrevias(array $campos, array $previos): array
    {
        $out = [];
        foreach ($campos as $c) {
            if (is_array($c) && is_string($c['key'] ?? null) && isset($previos[$c['key']])) {
                $out[] = $c['key'];
            }
        }

        return $out;
    }

    private static function asignarClavesColumnas(array $columnas, array $guardadas): array
    {
        $previas = [];
        foreach ($guardadas as $g) {
            if (is_array($g) && isset($g['key'])) {
                $previas[$g['key']] = true;
            }
        }
        $reservadas = [];
        foreach ($columnas as $c) {
            if (is_array($c) && is_string($c['key'] ?? null) && isset($previas[$c['key']])) {
                $reservadas[] = $c['key'];
            }
        }

        $usadas = [];
        $salida = [];
        foreach (array_values($columnas) as $c) {
            if (! is_array($c)) {
                $salida[] = $c;
                continue;
            }
            $clave = is_string($c['key'] ?? null) ? $c['key'] : '';
            if (! isset($previas[$clave]) || in_array($clave, $usadas, true)) {
                $clave = self::claveDesde((string) ($c['label'] ?? ''), array_merge($usadas, $reservadas), 'columna');
            }
            $usadas[] = $clave;
            $c['key'] = $clave;
            $salida[] = $c;
        }

        return $salida;
    }

    /**
     * Topes del editor sobre lo que manda el navegador (ya con claves). Las rutas de error son las
     * mismas que usa la Fase A (fields.N.label, checklist.N…), para pintarlas junto al campo.
     *
     * @return array<string, string>
     */
    public static function topes(array $campos, array $materiales): array
    {
        $errores = [];
        if (count($campos) > self::MAX_CAMPOS) {
            $errores['fields'] = 'Máximo ' . self::MAX_CAMPOS . ' campos por plantilla.';
        }

        foreach (array_values($campos) as $i => $c) {
            if (! is_array($c)) {
                continue;
            }
            $ruta = "fields.$i";
            if (mb_strlen(trim((string) ($c['label'] ?? ''))) > self::MAX_ETIQUETA) {
                $errores["$ruta.label"] = 'La etiqueta admite máximo ' . self::MAX_ETIQUETA . ' caracteres.';
            }
            if (mb_strlen(trim((string) ($c['unit'] ?? ''))) > self::MAX_UNIDAD) {
                $errores["$ruta.unit"] = 'La unidad admite máximo ' . self::MAX_UNIDAD . ' caracteres.';
            }
            if (mb_strlen(trim((string) ($c['help'] ?? ''))) > self::MAX_AYUDA) {
                $errores["$ruta.help"] = 'La ayuda admite máximo ' . self::MAX_AYUDA . ' caracteres.';
            }
            if (in_array($c['type'] ?? null, ['seleccion', 'multi_seleccion'], true)) {
                $error = self::errorOpciones($c['options'] ?? null);
                if ($error) {
                    $errores["$ruta.options"] = $error;
                }
            }
            if (($c['type'] ?? null) === 'tabla' && is_array($c['columns'] ?? null)) {
                foreach (array_values($c['columns']) as $j => $col) {
                    if (! is_array($col)) {
                        continue;
                    }
                    if (mb_strlen(trim((string) ($col['label'] ?? ''))) > self::MAX_ETIQUETA) {
                        $errores["$ruta.columns.$j.label"] = 'La etiqueta admite máximo ' . self::MAX_ETIQUETA . ' caracteres.';
                    }
                    if (mb_strlen(trim((string) ($col['unit'] ?? ''))) > self::MAX_UNIDAD) {
                        $errores["$ruta.columns.$j.unit"] = 'La unidad admite máximo ' . self::MAX_UNIDAD . ' caracteres.';
                    }
                    if (($col['type'] ?? null) === 'seleccion') {
                        $error = self::errorOpciones($col['options'] ?? null);
                        if ($error) {
                            $errores["$ruta.columns.$j.options"] = $error;
                        }
                    }
                }
            }
        }

        if (count($materiales) > self::MAX_MATERIALES) {
            $errores['checklist'] = 'Máximo ' . self::MAX_MATERIALES . ' materiales por plantilla.';
        }

        return $errores;
    }

    /** Entre 2 y 30 opciones, sin repetir (sin distinguir mayúsculas ni espacios de más). */
    private static function errorOpciones($opciones): ?string
    {
        if (! is_array($opciones)) {
            return 'Agrega entre ' . self::MIN_OPCIONES . ' y ' . self::MAX_OPCIONES . ' opciones.';
        }
        $vistas = [];
        foreach ($opciones as $o) {
            if (! is_scalar($o)) {
                return 'Las opciones deben ser textos.';
            }
            $t = trim((string) $o);
            if ($t === '') {
                continue;
            }
            $comparable = mb_strtolower(preg_replace('/\s+/u', ' ', $t));
            if (isset($vistas[$comparable])) {
                return "La opción «{$t}» está repetida.";
            }
            $vistas[$comparable] = true;
        }
        $n = count($vistas);
        if ($n < self::MIN_OPCIONES || $n > self::MAX_OPCIONES) {
            return 'Agrega entre ' . self::MIN_OPCIONES . ' y ' . self::MAX_OPCIONES . ' opciones.';
        }

        return null;
    }

    /**
     * Pipeline completo del editor: claves → topes → validador de la Fase A (formato) → materiales.
     *
     * @return array{fields: array, checklist: array, errors: array<string,string>}
     */
    public static function normalizar(array $campos, array $materiales, array $guardados = []): array
    {
        $errores = self::clavesRepetidas($campos, $guardados);
        $campos = self::asignarClaves($campos, $guardados);
        $errores += self::topes($campos, $materiales);

        $esquema = EsquemaCamposOrden::normalizarEsquema($campos);
        $lista = EsquemaCamposOrden::normalizarChecklist($materiales);

        // Los topes del editor tienen prioridad en el mensaje cuando los dos marcan el mismo campo.
        $errores = $errores + $esquema['errors'] + $lista['errors'];

        return ['fields' => $esquema['fields'], 'checklist' => $lista['items'], 'errors' => $errores];
    }
}
