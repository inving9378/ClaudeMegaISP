<?php

namespace App\Http\Controllers\Module\Git;

use App\Http\Controllers\Controller;
use App\Services\Git\GitService;

class GitController extends Controller
{

    public function getTags()
    {
        if (!auth()->user()->isAdmin()) {
            return response()->json(['error' => 'No tienes permiso para acceder a esta ruta.'], 403);
        }
        $gitService = new GitService();
        $tags = $gitService->getTags();
        return response()->json($tags);
    }
}
