<?php

namespace App\Services\Release;

use HTMLPurifier;
use HTMLPurifier_Config;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;

/**
 * #9991208 — Render SANEADO en el backend de las notas de release. El Vue recibe HTML ya limpio y
 * lo pinta con v-html; nunca se renderiza texto libre sin pasar por aquí (son notas generadas por
 * IA o escritas en un editor: v-html sin sanear es XSS).
 *
 *  - markdown → CommonMark (GFM) con `html_input => 'strip'` y `allow_unsafe_links => false`.
 *  - html     → HTMLPurifier con lista blanca de etiquetas de texto (lo que produce input-editor).
 */
class ReleaseNotesRenderer
{
    private ?MarkdownConverter $markdown = null;
    private ?HTMLPurifier $purifier = null;

    public function render(?string $texto, ?string $formato): string
    {
        $texto = (string) $texto;
        if (trim($texto) === '') {
            return '';
        }

        return ($formato === 'html') ? $this->html($texto) : $this->markdown($texto);
    }

    public function markdown(string $md): string
    {
        if ($this->markdown === null) {
            $env = new Environment([
                'html_input'         => 'strip',
                'allow_unsafe_links' => false,
                'max_nesting_level'  => 20,
            ]);
            $env->addExtension(new CommonMarkCoreExtension());
            $env->addExtension(new GithubFlavoredMarkdownExtension());
            $this->markdown = new MarkdownConverter($env);
        }

        return (string) $this->markdown->convert($this->limpiarGenerador($md));
    }

    public function html(string $html): string
    {
        if ($this->purifier === null) {
            $config = HTMLPurifier_Config::createDefault();
            $config->set('HTML.Allowed', 'p,br,ul,ol,li,strong,b,em,i,u,s,a[href|title],h1,h2,h3,h4,h5,h6,code,pre,blockquote,hr,span,table,thead,tbody,tr,th,td');
            $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true]);
            $config->set('Attr.AllowedFrameTargets', []);
            $config->set('AutoFormat.RemoveEmpty', true);
            $config->set('Cache.DefinitionImpl', null);
            $this->purifier = new HTMLPurifier($config);
        }

        return $this->purifier->purify($html);
    }

    /**
     * Ruido del generador de notas: el guion duplicado (`- -**Foo**`) y el encabezado
     * `### Mejoras en esta versión` que ya dice el título del bloque en la UI. Se aplica al
     * renderizar (protege lo guardado antes) y al guardar (ReleaseController::store).
     */
    public function limpiarGenerador(string $md): string
    {
        $md = preg_replace('/^([ \t]*)-[ \t]+-[ \t]*(?=\S)/mu', '$1- ', $md) ?? $md;
        $md = preg_replace('/^[ \t]*#{1,6}[ \t]*Mejoras (en|de) esta versi[oó]n[ \t]*\r?\n?/miu', '', $md) ?? $md;

        return trim($md);
    }
}
