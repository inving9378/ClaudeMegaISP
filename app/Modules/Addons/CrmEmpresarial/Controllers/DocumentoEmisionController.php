<?php

namespace App\Modules\Addons\CrmEmpresarial\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\CrmEmpresarial\Models\DocumentoEmision;
use Illuminate\Support\Facades\Storage;

/** Sirve las emisiones de documento (solo lectura). Permiso: crm-empresarial.expediente.view. */
class DocumentoEmisionController extends Controller
{
    private const DISK = 'local';

    public function show(string $uuid)
    {
        $this->autoriza();
        return response()->json(DocumentoEmision::where('uuid', $uuid)->firstOrFail());
    }

    public function descargar(string $uuid, string $formato)
    {
        $this->autoriza();
        abort_unless(in_array($formato, ['docx', 'pdf'], true), 404);

        $e = DocumentoEmision::where('uuid', $uuid)->firstOrFail();
        $path = $e->{$formato . '_path'};
        abort_if(! $path || ! Storage::disk(self::DISK)->exists($path), 404);

        // Integridad: lo servido debe ser lo emitido.
        $sha = hash_file('sha256', Storage::disk(self::DISK)->path($path));
        abort_unless(hash_equals((string) $e->{$formato . '_sha256'}, $sha), 409, 'El archivo no coincide con su huella de emisión.');

        return Storage::disk(self::DISK)->download($path, $e->folio . '.' . $formato);
    }

    private function autoriza(): void
    {
        abort_unless(auth()->user()?->can('crm-empresarial.expediente.view'), 403);
    }
}
