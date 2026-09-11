<?php

namespace App\Modules\Addons\VoIP\Services;

use RuntimeException;

/**
 * Escribe claves en un archivo .env sin romperlo.
 *
 * El provisionador genera las credenciales de AMI y ARI y tiene que dejarlas en
 * el `.env` de MegaISP. Ese archivo es el que decide si la aplicación levanta:
 * corromperlo no degrada nada, **tumba el sistema entero**. Por eso esta clase
 * existe en vez de un `file_put_contents` con una expresión regular.
 *
 * Cinco garantías:
 *
 *  1. **Respaldo previo** con marca de tiempo, antes de tocar nada.
 *  2. **Escritura atómica**: se escribe a un temporal en el MISMO filesystem y se
 *     hace `rename`. Nunca se modifica en sitio — un corte a media escritura deja
 *     el `.env` truncado y la app no levanta. `rename` dentro del mismo sistema de
 *     archivos es atómico: o está el viejo o está el nuevo, nunca la mitad.
 *  3. **Validación posterior**: se relee, se confirma que las claves esperadas
 *     están con su valor y que **el resto quedó intacto**. Si algo no cuadra, se
 *     restaura el respaldo y se aborta.
 *  4. **Nunca duplica una clave**: si ya existe, se reemplaza su valor. Un `.env`
 *     con la misma clave dos veces toma la última, y ese es un bug imposible de
 *     ver leyendo por encima — todo *parece* correcto.
 *  5. **Preserva comentarios y orden**. Las claves nuevas se añaden al final; las
 *     existentes se sustituyen donde estaban.
 */
class EscritorEnv
{
    private string $ruta;
    private ?string $respaldo = null;

    /** @var string[] Avisos no fatales: duplicados encontrados, etc. */
    private array $avisos = [];

    public function __construct(string $ruta)
    {
        if (! is_file($ruta)) {
            throw new RuntimeException("No existe el archivo .env en {$ruta}.");
        }
        if (! is_writable($ruta)) {
            throw new RuntimeException("El archivo {$ruta} no es escribible.");
        }

        $this->ruta = $ruta;
    }

    /**
     * @param  array<string,string>  $claves  clave => valor
     * @return array{respaldo: string, escritas: string[], avisos: string[]}
     */
    public function escribir(array $claves): array
    {
        if ($claves === []) {
            return ['respaldo' => '', 'escritas' => [], 'avisos' => []];
        }

        $original = file_get_contents($this->ruta);
        if ($original === false) {
            throw new RuntimeException("No se pudo leer {$this->ruta}.");
        }

        // (1) Respaldo ANTES de tocar nada.
        $this->respaldo = $this->ruta . '.bak-' . date('Ymd-His');
        if (! copy($this->ruta, $this->respaldo)) {
            throw new RuntimeException("No se pudo crear el respaldo {$this->respaldo}. No se escribe nada.");
        }
        @chmod($this->respaldo, fileperms($this->ruta) & 0777);

        $nuevo = $this->aplicar($original, $claves);

        // (2) Escritura atómica.
        $this->escribirAtomico($nuevo);

        // (3) Validación posterior. Si falla, restaura y aborta.
        try {
            $this->validar($original, $claves);
        } catch (RuntimeException $e) {
            $this->restaurar();
            throw new RuntimeException(
                'La validación del .env falló tras escribir: ' . $e->getMessage()
                . ' Se restauró el respaldo ' . $this->respaldo . ' y no se cambió nada.'
            );
        }

        return [
            'respaldo' => $this->respaldo,
            'escritas' => array_keys($claves),
            'avisos'   => $this->avisos,
        ];
    }

    /**
     * (4) y (5): sustituye donde ya estaba, añade al final lo nuevo, y deja UNA
     * sola línea por clave. Comentarios y orden intactos.
     */
    private function aplicar(string $contenido, array $claves): string
    {
        // Se conserva el terminador de línea del archivo, no se impone uno.
        $eol    = str_contains($contenido, "\r\n") ? "\r\n" : "\n";
        $lineas = explode($eol, $contenido);

        $pendientes = $claves;
        $vistas     = [];
        $salida     = [];

        foreach ($lineas as $linea) {
            $clave = $this->claveDe($linea);

            if ($clave === null || ! array_key_exists($clave, $claves)) {
                $salida[] = $linea;          // comentario, línea vacía u otra clave
                continue;
            }

            if (isset($vistas[$clave])) {
                // Duplicado de una clave que vamos a escribir: se descarta esta
                // línea. Dejarla haría que el archivo tuviera la misma clave dos
                // veces, y .env se queda con la última.
                $this->avisos[] = "La clave {$clave} estaba duplicada en el .env; se dejó una sola línea.";
                continue;
            }

            $salida[] = $clave . '=' . $this->formatear($claves[$clave]);
            $vistas[$clave] = true;
            unset($pendientes[$clave]);
        }

        // Las que no existían van al final, sin alterar nada de arriba.
        if ($pendientes !== []) {
            if (end($salida) !== '') {
                $salida[] = '';
            }
            foreach ($pendientes as $c => $v) {
                $salida[] = $c . '=' . $this->formatear($v);
            }
        }

        return implode($eol, $salida);
    }

    /** Nombre de la clave de una línea `CLAVE=valor`, o null si no lo es. */
    private function claveDe(string $linea): ?string
    {
        $t = ltrim($linea);

        // Un comentario no es una clave, aunque contenga un '='.
        if ($t === '' || str_starts_with($t, '#')) {
            return null;
        }

        $pos = strpos($t, '=');
        if ($pos === false || $pos === 0) {
            return null;
        }

        $clave = rtrim(substr($t, 0, $pos));

        return preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $clave) === 1 ? $clave : null;
    }

    /** Entrecomilla si el valor lo necesita, para que dotenv lo lea entero. */
    private function formatear(string $valor): string
    {
        if ($valor === '') {
            return '';
        }

        if (preg_match('/[\s"\'#$]/', $valor) === 1) {
            return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $valor) . '"';
        }

        return $valor;
    }

    /** (2) Temporal en el MISMO directorio → rename atómico. */
    private function escribirAtomico(string $contenido): void
    {
        $dir  = dirname($this->ruta);
        $temp = tempnam($dir, '.env.tmp-');

        if ($temp === false) {
            throw new RuntimeException("No se pudo crear el temporal en {$dir}.");
        }

        try {
            if (file_put_contents($temp, $contenido, LOCK_EX) === false) {
                throw new RuntimeException("No se pudo escribir el temporal {$temp}.");
            }

            // Los permisos del original mandan: el .env suele ir en 640 y el
            // temporal nace en 600. Sin esto, el rename bajaría los permisos.
            @chmod($temp, fileperms($this->ruta) & 0777);
            $stat = @stat($this->ruta);
            if ($stat !== false) {
                @chown($temp, $stat['uid']);
                @chgrp($temp, $stat['gid']);
            }

            if (! rename($temp, $this->ruta)) {
                throw new RuntimeException("No se pudo renombrar {$temp} sobre {$this->ruta}.");
            }
        } catch (RuntimeException $e) {
            @unlink($temp);
            throw $e;
        }
    }

    /** (3) Releer y comprobar: las claves nuevas están, y el resto no se movió. */
    private function validar(string $original, array $claves): void
    {
        $releido = @file_get_contents($this->ruta);

        if ($releido === false || trim($releido) === '') {
            throw new RuntimeException('el archivo quedó vacío o ilegible');
        }

        // Las claves esperadas, con su valor.
        foreach ($claves as $clave => $valor) {
            $encontrados = 0;
            foreach (explode("\n", str_replace("\r\n", "\n", $releido)) as $linea) {
                if ($this->claveDe($linea) === $clave) {
                    $encontrados++;
                }
            }

            if ($encontrados === 0) {
                throw new RuntimeException("la clave {$clave} no quedó escrita");
            }
            if ($encontrados > 1) {
                throw new RuntimeException("la clave {$clave} quedó {$encontrados} veces");
            }
        }

        // El resto intacto: toda clave que existía y no tocamos sigue con su valor.
        $antes   = $this->mapa($original);
        $despues = $this->mapa($releido);

        foreach ($antes as $clave => $valor) {
            if (array_key_exists($clave, $claves)) {
                continue;   // esta sí la cambiamos a propósito
            }
            if (! array_key_exists($clave, $despues)) {
                throw new RuntimeException("se perdió la clave {$clave}, que no se pidió tocar");
            }
            if ($despues[$clave] !== $valor) {
                throw new RuntimeException("cambió el valor de {$clave}, que no se pidió tocar");
            }
        }
    }

    /** @return array<string,string> */
    private function mapa(string $contenido): array
    {
        $m = [];
        foreach (explode("\n", str_replace("\r\n", "\n", $contenido)) as $linea) {
            $clave = $this->claveDe($linea);
            if ($clave !== null) {
                $m[$clave] = substr(ltrim($linea), strpos(ltrim($linea), '=') + 1);
            }
        }

        return $m;
    }

    private function restaurar(): void
    {
        if ($this->respaldo && is_file($this->respaldo)) {
            @copy($this->respaldo, $this->ruta);
        }
    }
}
