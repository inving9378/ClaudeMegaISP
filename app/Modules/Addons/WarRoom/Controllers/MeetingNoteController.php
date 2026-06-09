<?php

namespace App\Modules\Addons\WarRoom\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\WarRoom\Models\Meeting;
use App\Modules\Addons\WarRoom\Models\MeetingNote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeetingNoteController extends Controller
{
    public function index(Meeting $meeting): JsonResponse
    {
        $notes = $meeting->notes()
            ->with('author:id,name')
            ->orderBy('created_at')
            ->get();

        return response()->json($notes);
    }

    public function store(Request $request, Meeting $meeting): JsonResponse
    {
        $request->validate([
            'kind'       => 'required|in:pregunta,respuesta,nota',
            'body'       => 'required|string|max:2000',
            'section_id' => 'nullable|integer|exists:warroom_meeting_sections,id',
        ]);

        $note = $meeting->notes()->create([
            'section_id' => $request->section_id,
            'kind'       => $request->kind,
            'body'       => $request->body,
            'created_by' => auth()->id(),
        ]);

        return response()->json($note->load('author:id,name'), 201);
    }
}
