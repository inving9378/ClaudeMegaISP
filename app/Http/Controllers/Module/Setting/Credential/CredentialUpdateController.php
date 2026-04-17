<?php

namespace App\Http\Controllers\Module\Setting\Credential;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Credential;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image;

class CredentialUpdateController extends Controller
{
    public function changeImageCredential()
    {
        return view('meganet.module.setting.credential.update-credential-image');
    }

    public function changeLogoCredential()
    {
        return view('meganet.module.setting.credential.update-logo-image');
    }

    public function getFrontalImagePath() {
        $credential = Credential::where('type', 'frontal')->first();
    
        if ($credential) {
            return response()->json($credential);
        }
    
        return response()->json(null);
    }
    
    public function getBackImagePath() {
        $credential = Credential::where('type', 'reverso')->first();
    
        if ($credential) {
            return response()->json($credential);
        }
    
        return response()->json(null);
    }
    
    public function getLogoImagePath() {
        $credential = Credential::where('type', 'logo')->first();
    
        if ($credential) {
            return response()->json($credential);
        }
    
        return response()->json(null);
    }

    public function upload(Request $request) {

        if($request->file('image')) {
            $file = $request->file('image');
            $file_name = Str::uuid() . "." . $file->extension();
    
            $file_server = Image::make($file);
            $file_path = public_path('credencial') . '/' . $file_name;
            $file_server->save($file_path);
        }
    
        $credential = Credential::firstWhere('type', $request->type);
    
        if (!$credential) {
            $credential = new Credential;
        }
    
        $credential->name = $file_name;
        $credential->path = '/credential/' . $file_name;
        $credential->type = $request->type;
    
        $credential->save();
    
        return response()->json(['status' => 200, 'message' => 'Imagen actualizada correctamente']);
    }
}
