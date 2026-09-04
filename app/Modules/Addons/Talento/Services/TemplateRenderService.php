<?php

namespace App\Modules\Addons\Talento\Services;

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
 * en vez de un escalar) se marca VISIBLEMENTE con .campo-faltante — nunca se imprime en blanco
 * de forma disimulada (regla dura del item).
 */
class TemplateRenderService
{
    private const MISSING_CLASS = 'campo-faltante';

    public function renderContent(string $content, array $data): string
    {
        $content = $this->renderEachBlocks($content, $data);

        return $this->renderVariables($content, $data);
    }

    /**
     * Documento HTML completo listo para abrir/imprimir desde el navegador (nunca PDF).
     */
    public function renderDocument(string $content, array $data, ?string $title = null): string
    {
        $body = $this->renderContent($content, $data);
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

    private function renderEachBlocks(string $content, array $data): string
    {
        $result = preg_replace_callback(
            '/\{\{#each\s+([a-zA-Z0-9_.]+)\}\}(.*?)\{\{\/each\}\}/s',
            function (array $m) use ($data) {
                $items = data_get($data, trim($m[1]));
                if (!is_array($items)) {
                    return '';
                }

                $rendered = '';
                $index = 1;
                foreach ($items as $item) {
                    $rendered .= $this->renderVariables($m[2], ['item' => $item, 'index' => $index]);
                    $index++;
                }

                return $rendered;
            },
            $content
        );

        return $result ?? $content;
    }

    private function renderVariables(string $content, array $data): string
    {
        $result = preg_replace_callback(
            '/\{\{\s*([a-zA-Z0-9_.]+)\s*\}\}/',
            function (array $m) use ($data) {
                $path = $m[1];
                $value = data_get($data, $path);

                if ($value === null || is_array($value) || is_object($value)) {
                    return $this->missingMarker($path);
                }

                return e($value);
            },
            $content
        );

        return $result ?? $content;
    }

    private function missingMarker(string $path): string
    {
        return '<span class="' . self::MISSING_CLASS . '" data-campo="' . e($path) . '">[FALTA: ' . e($path) . ']</span>';
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
    background: #fff3cd;
    color: #b02a37;
    border: 1px solid #b02a37;
    padding: 0 4px;
    font-weight: bold;
    font-size: 0.9em;
}
@media print {
    .campo-faltante {
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
}
CSS;
    }
}
