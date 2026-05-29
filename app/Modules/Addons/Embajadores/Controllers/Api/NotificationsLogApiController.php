<?php

namespace App\Modules\Addons\Embajadores\Controllers\Api;

use App\Models\Referrals\ReferralNotificationLog;
use App\Modules\Core\Clientes\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class NotificationsLogApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $client = $request->user();
        if (!($client instanceof Client)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $logs = ReferralNotificationLog::where('client_id', $client->id)
            ->where('created_at', '>=', now()->subDays(30))
            ->orderByDesc('sent_at')
            ->paginate(20);

        return response()->json($logs);
    }
}
