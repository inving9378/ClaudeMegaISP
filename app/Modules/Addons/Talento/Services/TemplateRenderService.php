<?php

namespace App\Modules\Addons\Talento\Services;

use Illuminate\Support\Facades\Storage;

/**
 * Motor de plantillas del Expediente RH (item #200 — Hijo B). Generico y reutilizable por los
 * Hijos C (conversion de las 11 plantillas .docx) y D (paquetes por puesto).
 *
 * Sintaxis soportada en `content` (HTML):
 * - Variable escalar por dot-notation: {{ruta.al.dato}} (resuelta via data_get()).
 * - Lista repetible (para tablas dinamicas: herramientas, EPP, vehiculo...):
 *     {{#each ruta.a.la.lista}}
 *       ... {{item.campo}} ... {{index}} ...
 *     {{/each}}
 *   Dentro del bloque, "item" es el elemento actual (array/objeto o escalar) e "index" el
 *   numero de fila (1-based). No soporta bloques anidados (no lo requiere ningun caso real).
 *
 * Un campo sin dato disponible (data_get devuelve null, o la ruta resuelve a un array/objeto
 * en vez de un escalar) SIEMPRE lleva la clase .campo-faltante (asi EmployeeDocumentPackageService
 * puede detectarlo y marcar el documento status=pendiente), pero lo que se VE depende del modo
 * (item #9990645, decision de Irving/David — antes se mostraba "[FALTA: ruta]" siempre, se sentia
 * ruidoso en el documento entregable):
 * - $mostrarFaltantes=false (default, documento entregable): una linea en blanco para llenar a
 *   mano, sin resaltado.
 * - $mostrarFaltantes=true (modo admin, opcional): el texto "[FALTA: ruta]" con resaltado rojo —
 *   comportamiento original, para quien necesite ver que falta capturar.
 */
class TemplateRenderService
{
    private const MISSING_CLASS = 'campo-faltante';
    private const MISSING_VISIBLE_CLASS = 'campo-faltante-visible';
    private const BLANK_FILL = '____________________';

    public function renderContent(string $content, array $data, bool $mostrarFaltantes = false): string
    {
        $content = $this->renderEachBlocks($content, $data, $mostrarFaltantes);

        return $this->renderVariables($content, $data, $mostrarFaltantes);
    }

    /**
     * Documento HTML completo listo para abrir/imprimir desde el navegador (nunca PDF).
     */
    public function renderDocument(string $content, array $data, ?string $title = null, bool $mostrarFaltantes = false): string
    {
        $body = $this->renderContent($content, $data, $mostrarFaltantes);
        $safeTitle = e($title ?? 'Documento');
        $css = $this->printCss();

        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>{$safeTitle}</title>
<style>{$css}</style>
</head>
<body>
<div class="documento-contenido">
{$body}
</div>
</body>
</html>
HTML;
    }

    private function renderEachBlocks(string $content, array $data, bool $mostrarFaltantes): string
    {
        $result = preg_replace_callback(
            '/\{\{#each\s+([a-zA-Z0-9_.]+)\}\}(.*?)\{\{\/each\}\}/s',
            function (array $m) use ($data, $mostrarFaltantes) {
                $items = data_get($data, trim($m[1]));
                if (!is_array($items)) {
                    return '';
                }

                $rendered = '';
                $index = 1;
                foreach ($items as $item) {
                    $rendered .= $this->renderVariables($m[2], ['item' => $item, 'index' => $index], $mostrarFaltantes);
                    $index++;
                }

                return $rendered;
            },
            $content
        );

        return $result ?? $content;
    }

    private function renderVariables(string $content, array $data, bool $mostrarFaltantes): string
    {
        $result = preg_replace_callback(
            '/\{\{\s*([a-zA-Z0-9_.]+)\s*\}\}/',
            function (array $m) use ($data, $mostrarFaltantes) {
                $path = $m[1];

                if (str_starts_with($path, 'firma.')) {
                    return $this->renderFirma($path, $data);
                }

                $value = data_get($data, $path);

                if ($value === null || is_array($value) || is_object($value)) {
                    return $this->missingMarker($path, $mostrarFaltantes);
                }

                return e($value);
            },
            $content
        );

        return $result ?? $content;
    }

    /**
     * Item #9990654 (fase 3b+4a de #9990650). $data['firma'][slot] es
     * ['signature_path' => ...|null] (armado por EmployeeDocumentPackageService). Si hay
     * signature_path real, lo incrusta como <img> data-URI (documento HTML autocontenido, sin
     * depender de una ruta autenticada). Si el slot no está firmado, deja el mismo BLANK_FILL
     * que missingMarker() usa para el resto del renderer, pero SIN la clase campo-faltante: una
     * firma pendiente no es un dato faltante del documento (no debe forzar status=pendiente).
     */
    private function renderFirma(string $path, array $data): string
    {
        $signaturePath = data_get($data, $path . '.signature_path');

        if (!$signaturePath || !Storage::disk('local')->exists($signaturePath)) {
            return '<span class="firma-pendiente">' . self::BLANK_FILL . '</span>';
        }

        $contents = Storage::disk('local')->get($signaturePath);
        $mimeType = Storage::disk('local')->mimeType($signaturePath) ?: 'image/png';
        $base64   = base64_encode($contents);

        return '<img src="data:' . $mimeType . ';base64,' . $base64 . '" alt="Firma" class="firma-imagen">';
    }

    private function missingMarker(string $path, bool $mostrarFaltantes): string
    {
        if ($mostrarFaltantes) {
            $class = self::MISSING_CLASS . ' ' . self::MISSING_VISIBLE_CLASS;

            return '<span class="' . $class . '" data-campo="' . e($path) . '">[FALTA: ' . e($path) . ']</span>';
        }

        return '<span class="' . self::MISSING_CLASS . '" data-campo="' . e($path) . '">' . self::BLANK_FILL . '</span>';
    }

    /**
     * CSS de impresion real: margenes con @page y page-break-inside:avoid en los bloques de
     * firma (clase .bloque-firma, a usar por las plantillas convertidas en el Hijo C).
     */
    public function printCss(): string
    {
        return <<<'CSS'
@page {
    size: letter;
    margin: 2.5cm 2cm;
}
body {
    font-family: "Times New Roman", Georgia, serif;
    font-size: 12pt;
    color: #111;
    line-height: 1.5;
}
.documento-contenido table {
    width: 100%;
    border-collapse: collapse;
    margin: 0.5em 0;
}
.documento-contenido table th,
.documento-contenido table td {
    border: 1px solid #333;
    padding: 4px 6px;
    font-size: 10.5pt;
}
.bloque-firma {
    page-break-inside: avoid;
    break-inside: avoid;
    margin-top: 2em;
}
.campo-faltante {
    /* Sin estilo por default: en el documento entregable es una linea en blanco discreta
       (item #9990645). El resaltado vive en .campo-faltante-visible (modo admin). */
}
.firma-imagen {
    height: 60px;
    vertical-align: middle;
}
.firma-pendiente {
    /* Misma linea en blanco que .campo-faltante, sin marcar el documento como pendiente
       (item #9990654): una firma sin capturar no es un dato faltante del documento. */
}
.campo-faltante-visible {
    background: #fff3cd;
    color: #b02a37;
    border: 1px solid #b02a37;
    padding: 0 4px;
    font-weight: bold;
    font-size: 0.9em;
}
@media print {
    .campo-faltante-visible {
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
}
CSS;
    }
}
