<?php

namespace App\Modules\Addons\Marketing\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Marketing\Controllers\Concerns\RespondeRetirada;
use App\Modules\Addons\Marketing\Models\Campaign;
use App\Modules\Addons\Marketing\Support\EmpresaActiva;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Generador de copy/imagen «legado». Fase 1 (F1-3): RETIRADO — guardaba en `campaign_contents` y leía
 * `marketing_templates`, tablas que no existen (500). Se conserva la validación (campaña de otra marca →
 * 422) y después responde 410. NINGUNA llamada a IA ni HTTP: ya no se inyectan AIContentService ni
 * ImageGeneratorService. Ver docs/marketing/fase-1-paquetes.md §2.5.
 */
class ContentGeneratorController extends Controller
{
    use RespondeRetirada;

    public function generateCopy(Request $request): JsonResponse
    {
        $request->validate([
            'campaign_id' => ['required', 'integer', $this->campanaDeLaEmpresa()],
            // Antes llevaba `exists:marketing_templates,id`: esa tabla no existe y la regla misma daba 500.
            'template_id' => 'required|integer',
        ]);

        return $this->retirada();
    }

    public function generateImage(Request $request): JsonResponse
    {
        $request->validate([
            'campaign_id'   => ['required', 'integer', $this->campanaDeLaEmpresa()],
            'custom_prompt' => 'nullable|string|max:500',
        ]);

        return $this->retirada();
    }

    public function approve(int $id): JsonResponse
    {
        return $this->retirada();
    }

    public function reject(int $id): JsonResponse
    {
        return $this->retirada();
    }

    public function byCampaign(int $campaignId): JsonResponse
    {
        Campaign::findOrFail($campaignId);

        return $this->retirada();
    }

    private function campanaDeLaEmpresa(): \Illuminate\Validation\Rules\Exists
    {
        return Rule::exists('marketing_campaigns', 'id')->where('company_id', app(EmpresaActiva::class)->id());
    }
}
