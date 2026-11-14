<?php

namespace Tests\Feature\Marketing\Fase2\Qa\Concerns;

use App\Models\Marketing\Asset;
use App\Modules\Addons\Marketing\Services\Video\AssetLibraryService;

/** Biblioteca de assets que registra qué tipo/categoría se pide (la música de F2-7 se busca con type = music). */
class AssetsEspia extends AssetLibraryService
{
    /** @var array<int, array{type:string, category:string}> */
    public array $pedidos = [];

    public function getRandom(string $type, string $category, int $companyId = 1): ?Asset
    {
        $this->pedidos[] = ['type' => $type, 'category' => $category];

        return parent::getRandom($type, $category, $companyId);
    }
}
