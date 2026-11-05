<?php

namespace App\Modules\Addons\CrmEmpresarial\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\CrmEmpresarial\Models\CrmExpediente;
use App\Modules\Addons\CrmEmpresarial\Services\ConversionEmpresarialService;
use Illuminate\Http\Request;

/** Item #563: checklist, duplicados y conversión de un expediente empresarial a cliente administrativo. */
class ConversionController extends Controller
{
    public function estado(Request $r, int $expediente, ConversionEmpresarialService $svc)
    {
        $this->can($r);
        $e = CrmExpediente::findOrFail($expediente);

        return response()->json(['checklist' => $svc->checklist($e), 'duplicados' => $svc->duplicados($e)]);
    }

    public function convertir(Request $r, int $expediente, ConversionEmpresarialService $svc)
    {
        $this->can($r);
        $res = $svc->convertir(CrmExpediente::findOrFail($expediente), $r->except(['confirmar_duplicados']), $r->user()->id, $r->boolean('confirmar_duplicados'));

        return response()->json($res, 201);
    }

    private function can(Request $r): void
    {
        abort_unless($r->user()?->can('crm-empresarial.conversion.manage'), 403);
    }
}
