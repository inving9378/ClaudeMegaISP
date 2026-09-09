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
     * CSS de pantalla + impresion (item #9990666): @page controla SOLO la impresion/PDF; el
     * "look de hoja" en pantalla (contenedor centrado, sombra) vive en .documento-contenido y se
     * neutraliza en @media print para no duplicar el margen de @page.
     */
    public function printCss(): string
    {
        return <<<'CSS'
@page {
    size: letter;
    margin: 2.5cm 2cm;
}
html, body {
    margin: 0;
    padding: 0;
}
body {
    font-family: Georgia, "Times New Roman", serif;
    font-size: 12pt;
    color: #1a1a1a;
    line-height: 1.5;
    background: #e9e9e9;
}
.documento-contenido {
    max-width: 820px;
    margin: 2.5cm auto;
    padding: 2.5cm 2cm;
    background: #fff;
    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.15);
}
.documento-contenido h1,
.documento-contenido h2,
.documento-contenido h3 {
    font-family: Georgia, "Times New Roman", serif;
    font-weight: bold;
    color: #111;
    line-height: 1.3;
}
.documento-contenido h1 {
    font-size: 16pt;
    margin: 0 0 1.2em;
    letter-spacing: 0.5px;
}
.documento-contenido h2 {
    font-size: 13pt;
    margin: 1.5em 0 0.6em;
}
.documento-contenido h3 {
    font-size: 12pt;
    margin: 1.2em 0 0.5em;
}
.documento-contenido p {
    margin: 0 0 0.9em;
    text-align: justify;
}
.documento-contenido ul,
.documento-contenido ol {
    margin: 0 0 0.9em 1.5em;
    padding: 0;
}
.documento-contenido li {
    margin-bottom: 0.3em;
}
.documento-contenido strong,
.documento-contenido b {
    font-weight: bold;
    color: #000;
}
.documento-contenido table {
    width: 100%;
    border-collapse: collapse;
    margin: 1em 0;
}
.documento-contenido table th {
    background: #f0f0f0;
    font-weight: bold;
    text-align: left;
}
.documento-contenido table th,
.documento-contenido table td {
    border: 1px solid #ccc;
    padding: 6px 8px;
    font-size: 10.5pt;
}
.bloque-firma {
    page-break-inside: avoid;
    break-inside: avoid;
    margin-top: 2.5em;
}
.bloque-firma > div {
    padding-top: 0.5em;
}
.firma-imagen {
    display: block;
    max-height: 70px;
    margin: 0 auto 6px;
    border-bottom: 1px solid #333;
    padding-bottom: 6px;
}
.campo-faltante {
    /* item #9990645: linea en blanco discreta en el documento entregable, pulida (#9990666)
       como subrayado tenue en vez de underscores crudos. El resaltado vive en
       .campo-faltante-visible (modo admin). */
    color: #aaa;
    letter-spacing: 1px;
}
.firma-pendiente {
    /* Misma linea en blanco que .campo-faltante, sin marcar el documento como pendiente
       (item #9990654): una firma sin capturar no es un dato faltante del documento. */
    color: #aaa;
    letter-spacing: 1px;
}
.campo-faltante-visible {
    background: #fff3cd;
    color: #b02a37;
    border: 1px solid #b02a37;
    padding: 0 4px;
    font-weight: bold;
    font-size: 0.9em;
    letter-spacing: normal;
}
@media print {
    body {
        background: #fff;
    }
    .documento-contenido {
        max-width: none;
        margin: 0;
        padding: 0;
        box-shadow: none;
    }
    .campo-faltante-visible {
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
}
CSS;
    }

    /**
     * Item #9990666: el <style> queda CONGELADO dentro de rendered_html al generarse el
     * documento, asi que mejorar printCss() no reestiliza por si solo los ya generados. show()
     * llama esto para reinyectar el CSS ACTUAL sobre el cuerpo guardado al servir — reestiliza
     * todo al instante, sin tocar rendered_html en BD ni regenerar el documento.
     */
    public function reinjectCurrentCss(string $renderedHtml): string
    {
        $css = $this->printCss();

        $result = preg_replace_callback(
            '#<style>.*?</style>#s',
            fn () => '<style>' . $css . '</style>',
            $renderedHtml,
            1
        );

        return $result ?? $renderedHtml;
    }
}
