<?php

namespace App\Modules\Addons\Talento\Support;

use Illuminate\Http\Request;

/**
 * Item #9990807 — punto único de "decodificar y validar la imagen de una firma", extraído del
 * cuerpo de TalentoEmployeeDocumentController::sign() (que se deja intacto, ya aprobado) para
 * que el endpoint self-scoped del Portal de Colaborador (PortalTecnicoController::firmarDocumento)
 * use la MISMA validación (tamaño/mime/base64) sin duplicar la lógica sensible de seguridad.
 */
class SignatureImageInput
{
    public const MAX_BYTES = 2 * 1024 * 1024; // 2MB

    private const MIME_EXT = ['image/png' => 'png', 'image/jpeg' => 'jpeg', 'image/webp' => 'webp'];

    /** @return array{binario:string, extension:string, metodo:string} */
    public static function resolve(Request $request): array
    {
        if ($request->hasFile('signature_file')) {
            $request->validate([
                'signature_file' => 'required|image|mimes:png,jpg,jpeg,webp|max:2048',
            ]);

            $file = $request->file('signature_file');
            $binario = file_get_contents($file->getRealPath());
            $extension = $file->getClientOriginalExtension() ?: 'png';
            $extension = $extension === 'jpg' ? 'jpeg' : $extension;
            $metodo = 'uploaded';
        } else {
            $request->validate(['signature' => 'required|string']);

            $data = $request->input('signature');
            $extension = 'png';
            if (str_starts_with($data, 'data:')) {
                if (!preg_match('/^data:(image\/(?:png|jpe?g|webp));base64,/', $data, $m)) {
                    abort(422, 'Formato de firma no soportado (usa PNG, JPEG o WEBP).');
                }
                $extension = self::MIME_EXT[$m[1]] ?? 'png';
                $data = preg_replace('/^data:[^,]+,/', '', $data);
            }

            $binario = base64_decode($data, true);
            if ($binario === false || $binario === '') {
                abort(422, 'No se pudo decodificar la firma.');
            }
            if (@getimagesizefromstring($binario) === false) {
                abort(422, 'La imagen de la firma no es válida.');
            }
            $metodo = 'drawn';
        }

        if (strlen($binario) > self::MAX_BYTES) {
            abort(422, 'La firma excede el tamaño máximo permitido (2MB).');
        }

        return ['binario' => $binario, 'extension' => $extension, 'metodo' => $metodo];
    }
}
