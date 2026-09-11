<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Candado de regresión del alta de MAPA DE RED.
 *
 * El módulo estuvo desplegado y completamente invisible porque faltaban dos registros en
 * la base: la fila de `module_registry` (sin ella `ModuleRegistry::getMenu()` no lo
 * devuelve) y el permiso `mapa_red_view` (sin él el sidebar lo filtra y
 * `CheckRoutePermission` bloquea `/mapa-red`). Este test amarra las tres fuentes que
 * tienen que decir lo mismo: module.json, la migración de alta y config/route_permission.php.
 *
 * Análisis estático, sin arrancar la app ni tocar MySQL (mismo patrón que
 * CircuitoHardeningConfigShapeTest).
 */
class MapaRedRegistroYPermisosTest extends TestCase
{
    private const MIGRACION = '/../../app/Modules/Addons/MapaRed/migrations/'
        .'2026_09_10_120000_seed_mapa_red_permissions_and_registry.php';

    private function moduleJson(): array
    {
        $ruta = __DIR__.'/../../app/Modules/Addons/MapaRed/module.json';
        $this->assertFileExists($ruta);

        $json = json_decode((string) file_get_contents($ruta), true);
        $this->assertIsArray($json, 'module.json de MapaRed no es JSON válido.');

        return $json;
    }

    private function fuenteMigracion(): string
    {
        $ruta = __DIR__.self::MIGRACION;
        $this->assertFileExists($ruta, 'Falta la migración que registra el módulo MAPA DE RED.');

        return (string) file_get_contents($ruta);
    }

    /** Nombres declarados en el bloque PERMISOS de la migración. */
    private function permisosDeLaMigracion(): array
    {
        $fuente = $this->fuenteMigracion();

        $partes = explode('PERMISOS = [', $fuente, 2);
        $this->assertCount(2, $partes, 'La migración ya no declara un bloque PERMISOS.');

        $bloque = explode('];', $partes[1], 2)[0];
        preg_match_all("/'([^']+)'\s*=>/", $bloque, $m);

        return $m[1];
    }

    public function test_la_migracion_crea_todos_los_permisos_del_module_json(): void
    {
        $declarados = array_column($this->moduleJson()['permissions'], 'name');
        $creados    = $this->permisosDeLaMigracion();

        $this->assertNotEmpty($declarados);
        $this->assertSame(
            $declarados,
            $creados,
            'module.json y la migración de alta se desincronizaron: un permiso declarado que '
            .'nadie crea deja la funcionalidad inalcanzable, igual que antes de esta migración.'
        );
    }

    public function test_el_permiso_del_menu_es_uno_de_los_creados(): void
    {
        $menu = $this->moduleJson()['menu'][0]['permission'] ?? null;

        $this->assertNotNull($menu, 'El menú de module.json debe declarar su permiso.');
        $this->assertContains(
            $menu,
            $this->permisosDeLaMigracion(),
            'El sidebar filtra por este permiso: si la migración no lo crea, el módulo no se ve.'
        );
    }

    public function test_el_permiso_de_ruta_coincide_exacto_con_route_permission(): void
    {
        $rp = (string) file_get_contents(__DIR__.'/../../config/route_permission.php');

        // CheckRoutePermission compara isset($permissions[$key]) contra los permisos del
        // usuario: la key debe ser EXACTAMENTE el nombre del permiso Spatie.
        $this->assertStringContainsString("'mapa_red_view' => [", $rp);
        $this->assertContains('mapa_red_view', $this->permisosDeLaMigracion());
    }

    public function test_la_migracion_da_de_alta_el_slug_del_module_json(): void
    {
        $json   = $this->moduleJson();
        $fuente = $this->fuenteMigracion();

        $this->assertStringContainsString("'".$json['slug']."'", $fuente);
        $this->assertStringContainsString('module_registry', $fuente);
        $this->assertStringContainsString(
            "'".$json['version']."'",
            $fuente,
            'installed_version debe seguir a la versión declarada en module.json.'
        );
    }

    public function test_es_reversible(): void
    {
        $fuente = $this->fuenteMigracion();

        $this->assertStringContainsString('public function down(): void', $fuente);

        $down = explode('public function down(): void', $fuente)[1] ?? '';
        $this->assertNotSame('', $down);

        // Solo el CÓDIGO: la migración menciona el permiso ajeno en un comentario a
        // propósito (para explicar por qué NO lo toca), y eso no debe hacer fallar nada.
        $codigo = (string) preg_replace('~//[^\n]*|/\*.*?\*/~s', '', $down);

        $this->assertStringContainsString(
            'array_keys(self::PERMISOS)',
            $codigo,
            'down() debe borrar exactamente los permisos que crea up(), ni más ni menos.'
        );
        $this->assertStringNotContainsString(
            'mapared.cobertura_declarada.manage',
            $codigo,
            'down() no debe borrar el permiso que crea otra migración (2026_09_08_010100).'
        );
    }
}
