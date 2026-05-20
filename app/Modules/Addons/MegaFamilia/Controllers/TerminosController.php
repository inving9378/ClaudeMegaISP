<?php

namespace App\Modules\Addons\MegaFamilia\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\MegaFamilia\Models\ParentalAccount;
use Illuminate\Http\JsonResponse;

class TerminosController extends Controller
{
    public function index()
    {
        return view('addon-megafamilia::terminos.index');
    }

    public function data(): JsonResponse
    {
        $accepted = ParentalAccount::whereNotNull('terms_accepted_at')
            ->with('client:id,name,email')
            ->orderByDesc('terms_accepted_at')
            ->paginate(25);

        return response()->json([
            'accepted' => $accepted,
            'pending_count' => ParentalAccount::whereNull('terms_accepted_at')->count(),
        ]);
    }
}
