<?php

namespace App\Modules\Addons\Talento\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Talento\Models\TalentoRoadmapItem;
use Illuminate\Http\Request;

class TalentoRoadmapController extends Controller
{
    public function index()
    {
        $this->authorize('talento.roadmap.view');
        return view('addon-talento::talento.roadmap');
    }

    public function data()
    {
        $this->authorize('talento.roadmap.view');
        return response()->json(TalentoRoadmapItem::orderBy('phase')->get());
    }

    public function update(Request $request, $id)
    {
        $this->authorize('talento.roadmap.manage');

        $item = TalentoRoadmapItem::findOrFail($id);

        $data = $request->validate([
            'status'      => 'sometimes|in:backlog,in_progress,done',
            'target_date' => 'nullable|date',
            'notes'       => 'nullable|string',
            'scope'       => 'nullable|string',
        ]);

        // Enforce only one in_progress at a time
        if (($data['status'] ?? null) === 'in_progress') {
            TalentoRoadmapItem::where('status', 'in_progress')
                ->where('id', '!=', $id)
                ->update(['status' => 'backlog']);
        }

        $item->update($data);

        return response()->json($item);
    }
}
