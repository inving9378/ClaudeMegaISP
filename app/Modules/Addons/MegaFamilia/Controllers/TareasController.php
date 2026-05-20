<?php

namespace App\Modules\Addons\MegaFamilia\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\MegaFamilia\Models\ParentalReward;
use App\Modules\Addons\MegaFamilia\Models\ParentalTask;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TareasController extends Controller
{
    public function index()
    {
        return view('addon-megafamilia::tareas.index');
    }

    public function data(Request $request): JsonResponse
    {
        $q = ParentalTask::with('profile:id,name')
            ->when($request->profile_id, fn ($qq, $v) => $qq->where('profile_id', $v))
            ->when($request->status, fn ($qq, $v) => $qq->where('status', $v))
            ->orderByDesc('id');
        return response()->json($q->paginate(25));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'profile_id' => 'required|exists:parental_profiles,id',
            'title' => 'required|string',
            'description' => 'nullable|string',
            'reward_type' => 'required|in:time_extra,app_unlock,points,badge',
            'reward_value' => 'required|integer|min:0',
            'reward_detail' => 'nullable|string',
            'points' => 'sometimes|integer|min:0',
        ]);
        $data['status'] = 'pending';
        $task = ParentalTask::create($data);
        return response()->json(['success' => true, 'task' => $task]);
    }

    public function approve(int $id): JsonResponse
    {
        $task = ParentalTask::findOrFail($id);
        $task->update(['status' => 'approved', 'approved_at' => now()]);

        ParentalReward::create([
            'profile_id' => $task->profile_id,
            'type' => $task->reward_type,
            'value' => $task->reward_value,
            'detail' => $task->reward_detail,
            'source_task_id' => $task->id,
            'granted_at' => now(),
            'expires_at' => now()->addDays(7),
        ]);

        return response()->json(['success' => true]);
    }

    public function reject(int $id): JsonResponse
    {
        $task = ParentalTask::findOrFail($id);
        $task->update(['status' => 'rejected']);
        return response()->json(['success' => true]);
    }
}
