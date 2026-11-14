<?php

namespace App\Modules\Addons\Marketing\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Marketing\Controllers\Concerns\RespondeRetirada;
use App\Modules\Addons\Marketing\Models\Lead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Leads «legado» (`/marketing/leads-legacy/**`). Fase 1 (F1-3): RETIRADO — usaba columnas que
 * `marketing_leads` no tiene (`campaign_id` del modelo legado, `contact_name`, `channel`…) y respondía
 * 500. La API vigente de leads es `/api/marketing/leads`. Con `{id}` se conserva el findOrFail con scope
 * (id de otra marca → 404) y después responde 410. Ver docs/marketing/fase-1-paquetes.md §2.5.
 */
class LeadController extends Controller
{
    use RespondeRetirada;

    public function table(Request $request): JsonResponse
    {
        return $this->retirada();
    }

    public function show(int $id): JsonResponse
    {
        Lead::findOrFail($id);

        return $this->retirada();
    }

    public function qualify(int $id): JsonResponse
    {
        Lead::findOrFail($id);

        return $this->retirada();
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        Lead::findOrFail($id);

        return $this->retirada();
    }

    public function assign(Request $request, int $id): JsonResponse
    {
        Lead::findOrFail($id);

        return $this->retirada();
    }
}
