<?php

namespace App\Modules\Addons\CrmEmpresarial\Services;

use ZipArchive;

/**
 * Item #605 (CRM-E 06 · Fase 6): valida un DOCX de plantilla al subirlo.
 *
 * Detecta (1) {{ }} partidos entre runs de Word, que el sustituidor no ve, y
 * (2) variables fuera del registro central. Solo lee el ZIP; no ejecuta nada.
 */
class ValidadorPlantillaDocx
{
    private const PARTES = '#^word/(document|header\d*|footer\d*)\.xml$#';

    public function __construct(private RegistroVariablesDocumento $registro = new RegistroVariablesDocumento())
    {
    }

    /**
     * @return array{valido:bool, errores:array<int,array{tipo:string,detalle:string}>, variables:array<int,string>}
     */
    public function validar(string $ruta): array
    {
        $errores = [];
        $variables = [];
        $zip = new ZipArchive();
        if (! is_file($ruta) || $zip->open($ruta) !== true) {
            return $this->resultado([['tipo' => 'archivo_invalido', 'detalle' => 'No es un DOCX (zip) legible.']], []);
        }

        $hayDocumento = false;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $nombre = $zip->getNameIndex($i);
            if (! preg_match(self::PARTES, $nombre)) {
                continue;
            }
            $hayDocumento = $hayDocumento || $nombre === 'word/document.xml';
            foreach ($this->parrafos((string) $zip->getFromIndex($i)) as $runs) {
                $this->revisarTexto($runs, $errores, $variables);
            }
        }
        $zip->close();

        if (! $hayDocumento) {
            $errores[] = ['tipo' => 'archivo_invalido', 'detalle' => 'Falta word/document.xml: no es un DOCX de Word.'];
        }

        return $this->resultado($errores, $variables);
    }

    /** Runs (w:t) de cada párrafo. */
    private function parrafos(string $xml): array
    {
        $dom = new \DOMDocument();
        // Sin LIBXML_NOENT/DTD: no se resuelven entidades externas.
        if ($xml === '' || ! @$dom->loadXML($xml, LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR)) {
            return [];
        }
        $out = [];
        foreach ($dom->getElementsByTagName('p') as $p) {
            $runs = [];
            foreach ($p->getElementsByTagName('t') as $t) {
                $runs[] = $t->textContent;
            }
            if ($runs !== []) {
                $out[] = $runs;
            }
        }

        return $out;
    }

    private function revisarTexto(array $runs, array &$errores, array &$variables): void
    {
        $texto = implode('', $runs);
        $enRuns = [];
        foreach ($runs as $r) {
            $enRuns += array_flip($this->registro->encontrar($r));
        }
        foreach ($this->registro->encontrar($texto) as $nombre) {
            $variables[$nombre] = $nombre;
            if (! isset($enRuns[$nombre])) {
                $errores[] = ['tipo' => 'placeholder_partido', 'detalle' => "La variable {{{$nombre}}} está partida entre varios fragmentos de Word; reescríbela de una sola vez."];
            }
            if (! $this->registro->existe($nombre)) {
                $errores[] = ['tipo' => 'variable_desconocida', 'detalle' => "La variable {{{$nombre}}} no existe en el registro."];
            }
        }
        // Llaves mal formadas (incluye las que Word partió y quedaron con espacios/símbolos raros).
        $sinValidas = preg_replace('/\{\{\s*[a-z_]+\.[a-z_]+\s*\}\}/', '', $texto);
        if (preg_match('/\{\{|\}\}|\{\s*\{|\}\s*\}/', $sinValidas)) {
            $errores[] = ['tipo' => 'placeholder_malformado', 'detalle' => 'Llaves {{ }} incompletas o con formato inválido en: "'.mb_substr(trim($sinValidas), 0, 80).'"'];
        }
    }

    private function resultado(array $errores, array $variables): array
    {
        return ['valido' => $errores === [], 'errores' => $errores, 'variables' => array_values($variables)];
    }
}
