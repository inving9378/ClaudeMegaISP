<?php

namespace App\Modules\Addons\MegaFamilia\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\MegaFamilia\Models\ParentalAccount;
use App\Modules\Addons\MegaFamilia\Models\ParentalDevice;
use App\Modules\Addons\MegaFamilia\Models\ParentalProfile;
use App\Modules\Addons\MegaFamilia\Models\ParentalReward;
use App\Modules\Addons\MegaFamilia\Models\ParentalTask;
use App\Modules\Addons\MegaFamilia\Services\FcmService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TareasController extends Controller
{
    public function __construct(private FcmService $fcm)
    {
    }

    public function index()
    {
        return view('addon-megafamilia::tareas.index');
    }

    public function data(Request $request): JsonResponse
    {
        $q = ParentalTask::query()
            ->with('profile:id,name,profile_type,age,photo')
            ->when($request->profile_id, fn ($qq, $v) => $qq->where('profile_id', $v))
            ->when($request->status, fn ($qq, $v) => $qq->where('status', $v))
            ->orderByDesc('id');

        // Devolver paginado para tabla, y por estado para vista kanban.
        $list = $q->paginate((int) $request->input('per_page', 50));

        $byStatus = ParentalTask::query()
            ->with('profile:id,name,photo')
            ->orderByDesc('id')
            ->get()
            ->groupBy('status');

        return response()->json([
            'list'      => $list,
            'by_status' => $byStatus,
            'profiles'  => ParentalProfile::query()
                ->where('active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'photo', 'profile_type']),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'profile_id'    => 'required|exists:parental_profiles,id',
            'title'         => 'required|string|max:255',
            'description'   => 'nullable|string',
            'due_date'      => 'nullable|date',
            'priority'      => 'nullable|in:baja,media,alta',
            'reward_type'   => 'required|in:time_extra,app_unlock,points,badge',
            'reward_value'  => 'required|integer|min:0',
            'reward_detail' => 'nullable|string|max:255',
            'points'        => 'sometimes|integer|min:0',
        ]);
        $this->guardProfileAccess(ParentalProfile::findOrFail($data['profile_id']));
        $data['status']   = 'pending';
        $data['priority'] = $data['priority'] ?? 'media';
        $task = ParentalTask::create($data);

        $this->pushToProfileDevices($task->profile_id, 'Nueva tarea', $task->title, [
            'type'    => 'new_task',
            'task_id' => (string) $task->id,
        ]);

        return response()->json(['success' => true, 'task' => $task]);
    }

    public function approve(int $id): JsonResponse
    {
        $task = ParentalTask::findOrFail($id);
        $this->guardProfileAccess($task->profile);
        $task->update(['status' => 'approved', 'approved_at' => Carbon::now()]);

        ParentalReward::create([
            'profile_id'     => $task->profile_id,
            'type'           => $task->reward_type,
            'value'          => $task->reward_value,
            'detail'         => $task->reward_detail,
            'source_task_id' => $task->id,
            'granted_at'     => Carbon::now(),
            'expires_at'     => Carbon::now()->addDays(7),
        ]);

        $this->pushToProfileDevices(
            $task->profile_id,
            '¡Tarea aprobada!',
            'Tu recompensa: ' . $this->describeReward($task),
            ['type' => 'task_approved', 'task_id' => (string) $task->id],
        );

        return response()->json(['success' => true]);
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['reason' => 'sometimes|nullable|string|max:500']);

        $task = ParentalTask::findOrFail($id);
        $this->guardProfileAccess($task->profile);
        $task->update([
            'status' => 'rejected',
            'notes'  => $data['reason'] ?? null,
        ]);

        $body = 'Tarea rechazada' . (!empty($data['reason']) ? ': ' . $data['reason'] : '.');
        $this->pushToProfileDevices(
            $task->profile_id,
            'Tarea rechazada',
            $body,
            ['type' => 'task_rejected', 'task_id' => (string) $task->id],
        );

        return response()->json(['success' => true]);
    }

    public function destroy(int $id): JsonResponse
    {
        $task = ParentalTask::findOrFail($id);
        $this->guardProfileAccess($task->profile);
        if ($task->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Sólo se pueden eliminar tareas en estado pendiente.',
            ], 422);
        }
        $task->delete();
        return response()->json(['success' => true]);
    }

    private function describeReward(ParentalTask $task): string
    {
        return match ($task->reward_type) {
            'time_extra' => "{$task->reward_value} min extra",
            'app_unlock' => 'desbloqueo de ' . ($task->reward_detail ?: 'app'),
            'points'     => "{$task->reward_value} puntos",
            'badge'      => 'insignia ' . ($task->reward_detail ?: ''),
            default      => 'recompensa',
        };
    }

    private function accountForCurrentUser(): ?ParentalAccount
    {
        $userId = Auth::id();
        if (! $userId) return null;
        return ParentalAccount::where('user_id', $userId)->first();
    }

    /**
     * Mismo criterio que PerfilesController::guardOwnership: el cliente final solo
     * toca tareas de sus propios perfiles; sin cuenta parental propia (staff) se
     * exige megafamilia_admin en vez de dejar pasar sin verificación.
     */
    private function guardProfileAccess(ParentalProfile $profile): void
    {
        $account = $this->accountForCurrentUser();
        if (! $account) {
            abort_unless(Auth::user()->can('megafamilia_admin'), 403);
            return;
        }
        abort_unless($profile->account_id === $account->id, 403);
    }

    private function pushToProfileDevices(int $profileId, string $title, string $body, array $data = []): void
    {
        $tokens = ParentalDevice::where('profile_id', $profileId)
            ->whereNotNull('fcm_token')
            ->pluck('fcm_token')
            ->all();
        if (!$tokens) return;

        $this->fcm->send($tokens, $title, $body, $data);
    }
}
