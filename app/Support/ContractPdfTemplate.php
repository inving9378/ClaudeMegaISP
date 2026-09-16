<?php

namespace App\Support;

/**
 * Envuelve el HTML de un contrato/documento en un documento HTML con una hoja de estilos
 * base compatible con dompdf, para que el PDF salga formateado y legible.
 *
 * dompdf (barryvdh/laravel-dompdf) SÓLO aplica el CSS que viene inline o en un <style>
 * DENTRO del HTML que recibe — no carga las hojas de estilo de la app. Antes se le pasaba
 * el contenido del contrato sin estilos, por eso los PDF (preview y generado) salían planos.
 *
 * IMPORTANTE (dompdf): CSS clásico solamente — NADA de flexbox, grid ni variables CSS.
 * La fuente por defecto de dompdf es "DejaVu Sans" (empaquetada), segura sin instalar nada.
 *
 * Seguro para plantillas que YA traen su propio formato: si el contenido ya es un documento
 * completo (`<html>`) o trae su propio `<style>`, se devuelve TAL CUAL (no se pisa ni se
 * duplica el estilo). Solo se envuelve el HTML "suelto" del editor.
 */
class ContractPdfTemplate
{
    public static function wrap(?string $html): string
    {
        $html = (string) $html;

        // Respetar plantillas que ya definen su propio documento/estilos.
        if (stripos($html, '<html') !== false || stripos($html, '<style') !== false) {
            return $html;
        }

        $css = self::baseCss();

        return '<!DOCTYPE html><html><head><meta charset="utf-8"><style>' . $css . '</style></head>'
            . '<body><div class="doc">' . $html . '</div></body></html>';
    }

    private static function baseCss(): string
    {
        return <<<'CSS'
@page { margin: 2.2cm 2cm; }
* { box-sizing: border-box; }
body {
    font-family: "DejaVu Sans", Arial, sans-serif;
    font-size: 12px;
    color: #1f2937;
    line-height: 1.55;
}
.doc { width: 100%; }
h1, h2, h3, h4, h5 {
    color: #111827;
    margin: 0 0 8px;
    line-height: 1.25;
    font-weight: 700;
}
h1 { font-size: 20px; }
h2 { font-size: 17px; }
h3 { font-size: 15px; }
h4 { font-size: 13px; }
p { margin: 0 0 10px; text-align: justify; }
strong, b { font-weight: 700; }
em, i { font-style: italic; }
ul, ol { margin: 0 0 10px; padding-left: 20px; }
li { margin: 0 0 4px; }
table { width: 100%; border-collapse: collapse; margin: 0 0 12px; }
th, td {
    border: 1px solid #cbd5e1;
    padding: 6px 8px;
    text-align: left;
    vertical-align: top;
    font-size: 11px;
}
th { background: #f1f5f9; font-weight: 700; }
img { max-width: 100%; }
hr { border: none; border-top: 1px solid #cbd5e1; margin: 14px 0; }
a { color: #0d9488; text-decoration: none; }
.text-center { text-align: center; }
.text-right { text-align: right; }
.text-left { text-align: left; }
.text-justify { text-align: justify; }
CSS;
    }
}
