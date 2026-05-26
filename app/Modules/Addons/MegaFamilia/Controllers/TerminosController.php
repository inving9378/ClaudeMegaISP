<?php

namespace App\Modules\Addons\MegaFamilia\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\MegaFamilia\Models\ParentalAccount;
use App\Modules\Addons\MegaFamilia\Models\ParentalConsent;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TerminosController extends Controller
{
    public function index()
    {
        return view('addon-megafamilia::terminos.index');
    }

    public function data(): JsonResponse
    {
        $current = ParentalConsent::query()
            ->where('is_draft', false)
            ->whereNotNull('published_at')
            ->orderByDesc('version_number')
            ->first();

        $draft = ParentalConsent::query()->where('is_draft', true)
            ->orderByDesc('version_number')->first();

        $history = ParentalConsent::query()
            ->orderByDesc('version_number')
            ->get(['id', 'version_number', 'is_draft', 'require_reacceptance', 'published_at', 'notes', 'updated_at']);

        $stats = [
            'total_acceptances'       => ParentalAccount::whereNotNull('terms_accepted_at')->count(),
            'current_version_accept'  => $current
                ? ParentalAccount::where('terms_version_accepted', $current->version_number)->count()
                : 0,
            'pending_acceptance'      => ParentalAccount::query()->where(function ($q) use ($current) {
                $q->whereNull('terms_accepted_at');
                if ($current) {
                    $q->orWhere('terms_version_accepted', '<', $current->version_number)
                      ->orWhereNull('terms_version_accepted');
                }
            })->count(),
        ];

        return response()->json([
            'current' => $current,
            'draft'   => $draft,
            'history' => $history,
            'stats'   => $stats,
        ]);
    }

    public function show(int $version): JsonResponse
    {
        $c = ParentalConsent::where('version_number', $version)->firstOrFail();
        return response()->json($c);
    }

    /**
     * Publica una nueva versión: incrementa version_number, marca is_draft=false,
     * y opcionalmente activa require_reacceptance.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'content'              => 'required|string',
            'notes'                => 'sometimes|nullable|string|max:1000',
            'require_reacceptance' => 'sometimes|boolean',
        ]);

        $next = ((int) ParentalConsent::max('version_number')) + 1;

        $consent = ParentalConsent::create([
            'version_number'       => $next,
            'content'              => $data['content'],
            'notes'                => $data['notes'] ?? null,
            'require_reacceptance' => (bool) ($data['require_reacceptance'] ?? false),
            'is_draft'             => false,
            'published_at'         => Carbon::now(),
        ]);

        if (!empty($data['require_reacceptance'])) {
            ParentalAccount::query()->update([
                'terms_accepted_at' => null,
                'terms_ip'          => null,
                'terms_version_accepted' => null,
            ]);
        }

        return response()->json(['success' => true, 'consent' => $consent]);
    }

    /**
     * Guarda un borrador (sin publicar). Si ya hay uno, lo sobrescribe.
     */
    public function draft(Request $request): JsonResponse
    {
        $data = $request->validate([
            'content' => 'required|string',
            'notes'   => 'sometimes|nullable|string|max:1000',
        ]);

        $draft = ParentalConsent::query()->where('is_draft', true)->first();
        if ($draft) {
            $draft->update([
                'content' => $data['content'],
                'notes'   => $data['notes'] ?? $draft->notes,
            ]);
        } else {
            $next = ((int) ParentalConsent::max('version_number')) + 1;
            $draft = ParentalConsent::create([
                'version_number' => $next,
                'content'        => $data['content'],
                'notes'          => $data['notes'] ?? null,
                'is_draft'       => true,
            ]);
        }

        return response()->json(['success' => true, 'draft' => $draft]);
    }

    public function history(): JsonResponse
    {
        $rows = ParentalConsent::query()
            ->orderByDesc('version_number')
            ->get();
        return response()->json(['versions' => $rows]);
    }

    public function acceptances(Request $request): JsonResponse
    {
        $q = ParentalAccount::query()
            ->whereNotNull('terms_accepted_at')
            ->with('user:id,name,email')
            ->when($request->search, function ($qq, $v) {
                $qq->whereHas('user', fn ($u) => $u->where('name', 'like', "%{$v}%")
                    ->orWhere('email', 'like', "%{$v}%"));
            })
            ->when($request->version, fn ($qq, $v) => $qq->where('terms_version_accepted', $v))
            ->orderByDesc('terms_accepted_at');

        $list = $q->paginate(25);

        // Project just the relevant fields to keep payload small
        $list->getCollection()->transform(fn ($a) => [
            'id'                => $a->id,
            'user'              => $a->user ? ['id' => $a->user->id, 'name' => $a->user->name, 'email' => $a->user->email] : null,
            'version_accepted'  => $a->terms_version_accepted,
            'accepted_at'       => $a->terms_accepted_at,
            'ip'                => $a->terms_ip,
        ]);

        return response()->json($list);
    }
}
