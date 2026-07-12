<?php

namespace Tests\Feature;

use App\Modules\Core\ModuleManager\Services\ModuleManagerService;
use Tests\TestCase;

/**
 * Item #247: $sidebarHardcoded/$sidebarSuppressed en sidebar.blade.php son listas
 * de slugs mantenidas a mano para deduplicar contra el loop dinámico de
 * ModuleRegistry::getMenu(). Si un módulo se borra o cambia su slug sin
 * actualizar esas listas, el ítem se duplica (aparece hardcodeado Y por el
 * loop) o desaparece en silencio hasta que alguien lo note en producción.
 * Este test detecta esa referencia stale antes del deploy.
 */
class SidebarDedupeIntegrityTest extends TestCase
{
    public function test_sidebar_dedupe_lists_reference_only_existing_module_slugs(): void
    {
        $bladePath = app_path('Modules/Core/Layout/views/sidebar.blade.php');
        $this->assertFileExists($bladePath);
        $source = file_get_contents($bladePath);

        $knownSlugs = collect(ModuleManagerService::instance()->manifests())
            ->pluck('slug')
            ->filter()
            ->values()
            ->all();

        foreach (['sidebarHardcoded', 'sidebarSuppressed'] as $varName) {
            $found = preg_match('/\$' . $varName . '\s*=\s*\[(.*?)\];/s', $source, $matches);
            $this->assertSame(
                1,
                $found,
                "No se encontró \$$varName en sidebar.blade.php — revisar si el patrón de deduplicación cambió."
            );

            preg_match_all("/'([^']+)'/", $matches[1], $slugMatches);
            $referencedSlugs = $slugMatches[1];
            $this->assertNotEmpty($referencedSlugs, "\$$varName está vacío o no se pudo parsear.");

            $stale = array_diff($referencedSlugs, $knownSlugs);
            $this->assertEmpty(
                $stale,
                "\$$varName referencia slugs que ya no existen en ningún module.json: "
                . implode(', ', $stale)
                . '. Ese módulo se duplicará o desaparecerá del sidebar (item #247) hasta corregir la lista.'
            );
        }
    }
}
