<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase; // TestCase PURO: NO bootea Laravel, NO toca BD.

/**
 * CANDADO 2026-08-28 — TODO NOMBRE `fa-*` TIENE QUE EXISTIR EN EL CSS QUE SE SIRVE.
 *
 * Un icono con nombre inválido no falla: se pinta un hueco. No hay error en consola, no hay
 * excepción, no hay log. Sólo un botón vacío que nadie relaciona con un nombre mal escrito —
 * el mismo modo de fallo silencioso que ya mordió con `fa-file-text-o` (nombre FA4 validado
 * contra `node_modules` en vez de contra el CSS servido; ver CLAUDE.md).
 *
 * El barrido del 28-ago encontró 14: once nombres de **Font Awesome 6** en un build de FA5
 * (`fa-magnifying-glass`, `fa-circle-check`, `fa-triangle-exclamation`…) y tres de **Feather**
 * en `module.json` (`activity`, `trending-up`, `shield`). Ninguno lo reportó nadie: se veían
 * como cuadros vacíos en VoIP, Talento, MegaFamilia, ModuleManager y Jarvis.
 *
 * LA FUENTE DE VERDAD ES EL CSS QUE NGINX SIRVE, no `package.json` ni `node_modules`:
 * `public/assets/css/icons.min.css` (Font Awesome 5 Free), importado desde `head.blade.php`.
 */
class IconosFontAwesomeExistenTest extends TestCase
{
    /** Sufijos y modificadores de FA que no son nombres de icono. */
    private const MODIFICADORES = [
        'fw', 'lg', 'xs', 'sm', 'spin', 'pulse', 'stack', 'border', 'inverse',
        'ul', 'li', 'rotate', 'flip', 'pull', 'stack-1x', 'stack-2x',
    ];

    private function raiz(): string
    {
        return dirname(__DIR__, 2);
    }

    private function css(): string
    {
        $f = $this->raiz() . '/public/assets/css/icons.min.css';
        $this->assertFileExists($f, 'No está el CSS de iconos que sirve nginx: se movió o no se compiló.');

        return file_get_contents($f);
    }

    private function existe(string $css, string $nombre): bool
    {
        return (bool) preg_match('/\.fa-' . preg_quote($nombre, '/') . ':before\{content:/', $css);
    }

    private function esModificador(string $n): bool
    {
        return in_array($n, self::MODIFICADORES, true)
            || (bool) preg_match('/^\d/', $n)              // fa-1x, fa-2x…
            || (bool) preg_match('/^(rotate|flip|pull|stack)-/', $n);
    }

    /** Los `fa-*` escritos a mano en plantillas Blade y componentes Vue. */
    public function test_los_iconos_de_blades_y_vue_existen_en_el_css_servido(): void
    {
        $css   = $this->css();
        $rotos = [];

        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->raiz()));
        foreach ($it as $f) {
            $ruta = $f->getPathname();
            if (! preg_match('/\.(vue|blade\.php)$/', $ruta)) {
                continue;
            }
            if (preg_match('#/(node_modules|vendor|public)/#', $ruta)) {
                continue;
            }

            $src = file_get_contents($ruta);
            if (! preg_match_all('/\bfa-([a-z][a-z0-9-]{1,})\b/', $src, $m)) {
                continue;
            }

            foreach (array_unique($m[1]) as $n) {
                if ($this->esModificador($n) || $this->existe($css, $n)) {
                    continue;
                }
                $rotos[] = 'fa-' . $n . '  →  ' . str_replace($this->raiz() . '/', '', $ruta);
            }
        }

        $this->assertSame([], $rotos, "Iconos que NO existen en el Font Awesome 5 que se sirve.\n"
            . "Se pintan como un hueco, sin error. Casi siempre es un nombre de FA6 o de otra\n"
            . "librería: fa-magnifying-glass→fa-search, fa-circle-check→fa-check-circle,\n"
            . "fa-triangle-exclamation→fa-exclamation-triangle.\n  " . implode("\n  ", $rotos));
    }

    /**
     * Los iconos de `module.json`, CADA BLOQUE CONTRA LA LIBRERÍA QUE DE VERDAD LO PINTA.
     *
     * ⚠️ NO SON TODOS FONT AWESOME, y confundirlos es el error que este test estuvo a punto de
     * cometer: su primera versión marcó como rotos nueve iconos que estaban perfectos.
     *
     *   · `config_sections[].icon` y `admin_cards[].icon` → los pinta ModuleConfigPanel.vue
     *     como `fa fa-fw fa-{icon}`  →  se validan contra el CSS de Font Awesome 5.
     *   · `menu[].icon` → lo pinta sidebar.blade.php como `<i data-feather="{icon}">`
     *     →  se valida contra el sprite de FEATHER (`feather-sprite.svg`), que es otra
     *        librería con otros nombres: ahí `settings`, `activity` o `trending-up` son
     *        correctos, y `fa-cog` no existiría.
     *
     * La lección de fondo es la de CLAUDE.md: validar contra lo que RENDERIZA, nunca contra
     * la librería que uno supone.
     */
    public function test_los_iconos_de_los_module_json_existen_en_su_libreria(): void
    {
        $css     = $this->css();
        $sprite  = $this->raiz() . '/public/assets/libs/feather-icons/feather-sprite.svg';
        $this->assertFileExists($sprite, 'No está el sprite de Feather: el sidebar dinámico lo usa.');
        $feather = file_get_contents($sprite);

        $rotos = [];

        foreach (glob($this->raiz() . '/app/Modules/*/*/module.json') as $f) {
            $j = json_decode(file_get_contents($f), true);
            if (! is_array($j)) {
                continue;
            }
            $mod = basename(dirname($f));

            // Font Awesome: lo que pinta el panel de configuración.
            foreach (['config_sections', 'admin_cards'] as $bloque) {
                foreach (($j[$bloque] ?? []) as $s) {
                    $ic = $s['icon'] ?? null;
                    if (! is_string($ic) || $ic === '' || $this->existe($css, $ic)) {
                        continue;
                    }
                    $rotos[] = "{$mod}/{$bloque}: «{$ic}» no existe en Font Awesome 5";
                }
            }

            // Feather: lo que pinta el sidebar dinámico.
            foreach (($j['menu'] ?? []) as $m) {
                $ic = $m['icon'] ?? null;
                if (! is_string($ic) || $ic === '' || str_contains($feather, 'id="' . $ic . '"')) {
                    continue;
                }
                $rotos[] = "{$mod}/menu: «{$ic}» no existe en Feather";
            }
        }

        $this->assertSame([], $rotos, "Iconos que no existen en la librería que los pinta.\n"
            . "Se ven como un hueco, sin error ni log.\n"
            . "  config_sections/admin_cards → Font Awesome 5 (fa fa-{icon})\n"
            . "  menu                        → Feather (data-feather)\n  "
            . implode("\n  ", $rotos));
    }
}
