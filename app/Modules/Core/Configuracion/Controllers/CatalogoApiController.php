<?php

namespace App\Modules\Core\Configuracion\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Configuracion\Services\CatalogoApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class CatalogoApiController extends Controller
{
    protected CatalogoApiService $catalog;

    public function __construct(CatalogoApiService $catalog)
    {
        $this->catalog = $catalog;
        $this->middleware('permission:configuracion_admin');
    }

    // GET /api/configuracion/api/catalog
    public function catalog(): JsonResponse
    {
        return response()->json([
            'modules' => $this->catalog->getAllEndpoints(),
            'scopes'  => $this->catalog->getAllScopes(),
        ]);
    }

    // GET /api/configuracion/api/keys
    public function index(): JsonResponse
    {
        $tokens = PersonalAccessToken::with('tokenable')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($t) => [
                'id'           => $t->id,
                'name'         => $t->name,
                'abilities'    => $t->abilities,
                'last_used_at' => $t->last_used_at?->toDateTimeString(),
                'expires_at'   => $t->expires_at?->toDateTimeString(),
                'created_at'   => $t->created_at->toDateTimeString(),
                'owner'        => $t->tokenable?->email ?? '—',
            ]);

        return response()->json(['keys' => $tokens]);
    }

    // POST /api/configuracion/api/keys
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name'       => 'required|string|max:100',
            'abilities'  => 'required|array|min:1',
            'abilities.*'=> 'string',
            'expires_in_days' => 'nullable|integer|min:1|max:3650',
        ]);

        $expiresAt = $request->filled('expires_in_days')
            ? now()->addDays($request->expires_in_days)
            : null;

        $token = auth()->user()->createToken(
            $request->name,
            $request->abilities,
            $expiresAt
        );

        return response()->json([
            'success'    => true,
            'plain_text' => $token->plainTextToken,
            'token'      => [
                'id'        => $token->accessToken->id,
                'name'      => $token->accessToken->name,
                'abilities' => $token->accessToken->abilities,
                'expires_at'=> $token->accessToken->expires_at?->toDateTimeString(),
                'created_at'=> $token->accessToken->created_at->toDateTimeString(),
            ],
            'warning' => 'Copia este token ahora. No se mostrará de nuevo.',
        ]);
    }

    // DELETE /api/configuracion/api/keys/{id}
    public function destroy(int $id): JsonResponse
    {
        $token = PersonalAccessToken::findOrFail($id);
        $token->delete();

        return response()->json(['success' => true]);
    }
}
