<?php

namespace App\Modules\Addons\Embajadores\Controllers;

use App\Http\Controllers\Controller;

class VideoController extends Controller
{
    public function index()
    {
        $videoUrl   = asset('storage/embajadores/video.mp4');
        $videoExists = file_exists(public_path('storage/embajadores/video.mp4'));
        $videoSize  = $videoExists ? round(filesize(public_path('storage/embajadores/video.mp4')) / 1048576, 1) : 0;

        return view('addon-embajadores::video.index', compact('videoUrl', 'videoExists', 'videoSize'));
    }
}
