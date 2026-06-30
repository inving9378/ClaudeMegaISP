<?php

namespace App\Modules\Addons\Payments\View\Composers;

use App\Modules\Addons\Payments\Models\ReconciliationTicket;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

/**
 * Comparte con el listado de clientes el conteo de tickets de conciliación
 * abiertos, para el banner persistente que pidió Irving. Gateado por el
 * permiso reconciliation_view: quien no lo tiene ve el banner en 0 (oculto).
 *
 * Schema::hasTable evita reventar si la vista se renderiza antes de migrar.
 */
class ReconciliationBannerComposer
{
    public function compose(View $view): void
    {
        $count = 0;

        $user = auth()->user();
        if ($user && $user->can('reconciliation_view') && Schema::hasTable('reconciliation_tickets')) {
            $count = ReconciliationTicket::open()->count();
        }

        $view->with('openReconciliationCount', $count);
    }
}
