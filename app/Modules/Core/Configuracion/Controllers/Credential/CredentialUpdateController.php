<?php

namespace App\Modules\Core\Configuracion\Controllers\Credential;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Credential;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image;

class CredentialUpdateController extends Controller
{
    public function changeImageCredential()
    {
        return view('core-configuracion::credential.update-credential-image');
    }

    public function changeLogoCredential()
    {
        return view('core-configuracion::credential.update-logo-image');
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

    /** Carpeta canónica de credenciales (coincide con el guardado físico y con la URL que sirve el frontend: /credencial/{name}). */
    const CREDENTIAL_DIR = 'credencial';

    public function upload(Request $request) {

        $request->validate([
            'image' => 'required|image',
            'type'  => 'required|string',
        ]);

        $file = $request->file('image');
        $file_name = Str::uuid() . "." . $file->extension();

        $dir = public_path(self::CREDENTIAL_DIR);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $file_server = Image::make($file);
        $file_path = $dir . '/' . $file_name;
        $file_server->save($file_path);

        $credential = Credential::firstWhere('type', $request->type);

        if (!$credential) {
            $credential = new Credential;
        }

        $credential->name = $file_name;
        $credential->path = '/' . self::CREDENTIAL_DIR . '/' . $file_name;
        $credential->type = $request->type;

        $credential->save();

        return response()->json(['status' => 200, 'message' => 'Imagen actualizada correctamente']);
    }
}
