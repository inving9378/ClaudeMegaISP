<?php

namespace App\Modules\Addons\DocumentacionCorporativa\Services;

use App\Modules\Addons\DocumentacionCorporativa\Models\DcConcepto;
use App\Modules\Addons\DocumentacionCorporativa\Models\DcDocumento;
use App\Modules\Addons\DocumentacionCorporativa\Models\DcDocumentoVersion;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Guarda el archivo en disco PRIVADO y refleja el resultado en
 * `dc_documentos`/`dc_documento_versiones`.
 *
 * Subir con `documento_id` de un documento YA existente del mismo concepto =
 * SIEMPRE versión nueva (nunca sobrescribe el archivo anterior). Subir sin
 * `documento_id` = documento nuevo — un concepto puede tener varios (ej.
 * varios contratos con contrapartes distintas).
 */
class DocumentoService
{
    public function __construct(private CompletitudService $completitud)
    {
    }

    public function subir(
        DcConcepto $concepto,
        int $empresaId,
        UploadedFile $archivo,
        array $datos,
        int $userId,
        ?DcDocumento $documentoExistente = null
    ): DcDocumento {
        $uuid            = (string) Str::uuid();
        $extension       = mb_strtolower($archivo->getClientOriginalExtension() ?: 'bin');
        $directorio      = $this->directorioDestino($empresaId, $concepto);
        $nombreArchivo   = "{$uuid}.{$extension}";
        $hash            = hash_file('sha256', $archivo->getRealPath());
        $bytes           = $archivo->getSize();
        $mime            = $archivo->getMimeType() ?: 'application/octet-stream';
        $nombreOriginal  = $archivo->getClientOriginalName();

        $rutaGuardada = Storage::disk('local')->putFileAs($directorio, $archivo, $nombreArchivo);

        if ($rutaGuardada === false) {
            throw new RuntimeException('No se pudo guardar el archivo en disco.');
        }

        try {
            $documento = DB::transaction(function () use (
                $documentoExistente, $concepto, $empresaId, $uuid, $rutaGuardada,
                $mime, $bytes, $hash, $nombreOriginal, $datos, $userId
            ) {
                if ($documentoExistente) {
                    return $this->agregarVersion(
                        $documentoExistente, $empresaId, $uuid, $rutaGuardada,
                        $mime, $bytes, $hash, $nombreOriginal, $datos, $userId
                    );
                }

                return $this->crearDocumento(
                    $concepto, $empresaId, $uuid, $rutaGuardada,
                    $mime, $bytes, $hash, $nombreOriginal, $datos, $userId
                );
            });
        } catch (\Throwable $e) {
            // La transacción no llegó a puerto: el archivo huérfano no se queda.
            Storage::disk('local')->delete($rutaGuardada);
            throw $e;
        }

        $this->completitud->invalidar($empresaId);

        return $documento;
    }

    private function crearDocumento(
        DcConcepto $concepto,
        int $empresaId,
        string $uuid,
        string $rutaGuardada,
        string $mime,
        int $bytes,
        string $hash,
        string $nombreOriginal,
        array $datos,
        int $userId
    ): DcDocumento {
        $documento = DcDocumento::create([
            'empresa_id'      => $empresaId,
            'concepto_id'     => $concepto->id,
            'titulo'          => $datos['titulo'] ?? $nombreOriginal,
            'archivo_uuid'    => $uuid,
            'ruta_archivo'    => $rutaGuardada,
            'archivo_nombre_original' => $nombreOriginal,
            'mime'            => $mime,
            'bytes'           => $bytes,
            'hash'            => $hash,
            'version_actual'  => 1,
            'vigencia_inicio' => $datos['vigencia_inicio'] ?? null,
            'vigencia_fin'    => $datos['vigencia_fin'] ?? null,
            'folio'           => $datos['folio'] ?? null,
            'contraparte'     => $datos['contraparte'] ?? null,
            'confidencialidad' => $datos['confidencialidad'] ?? 'interna',
            'notas'           => $datos['notas'] ?? null,
            'subido_por'      => $userId,
        ]);

        DcDocumentoVersion::create([
            'empresa_id'      => $empresaId,
            'documento_id'    => $documento->id,
            'version'         => 1,
            'archivo_uuid'    => $uuid,
            'ruta_archivo'    => $rutaGuardada,
            'archivo_nombre_original' => $nombreOriginal,
            'mime'            => $mime,
            'bytes'           => $bytes,
            'hash'            => $hash,
            'subido_por'      => $userId,
            'nota_cambio'     => $datos['nota_cambio'] ?? null,
            'created_at'      => now(),
        ]);

        return $documento;
    }

    private function agregarVersion(
        DcDocumento $documento,
        int $empresaId,
        string $uuid,
        string $rutaGuardada,
        string $mime,
        int $bytes,
        string $hash,
        string $nombreOriginal,
        array $datos,
        int $userId
    ): DcDocumento {
        $version = $documento->version_actual + 1;

        DcDocumentoVersion::create([
            'empresa_id'      => $empresaId,
            'documento_id'    => $documento->id,
            'version'         => $version,
            'archivo_uuid'    => $uuid,
            'ruta_archivo'    => $rutaGuardada,
            'archivo_nombre_original' => $nombreOriginal,
            'mime'            => $mime,
            'bytes'           => $bytes,
            'hash'            => $hash,
            'subido_por'      => $userId,
            'nota_cambio'     => $datos['nota_cambio'] ?? null,
            'created_at'      => now(),
        ]);

        $documento->fill([
            'archivo_uuid'    => $uuid,
            'ruta_archivo'    => $rutaGuardada,
            'archivo_nombre_original' => $nombreOriginal,
            'mime'            => $mime,
            'bytes'           => $bytes,
            'hash'            => $hash,
            'version_actual'  => $version,
            'subido_por'      => $userId,
        ]);

        // Metadatos opcionales: sólo se tocan si vienen en el payload de ESTA
        // subida — una versión nueva no debe borrar folio/contraparte/vigencia
        // que ya estaban capturados si el usuario no los volvió a mandar.
        foreach (['titulo', 'vigencia_inicio', 'vigencia_fin', 'folio', 'contraparte', 'confidencialidad', 'notas'] as $campo) {
            if (array_key_exists($campo, $datos) && $datos[$campo] !== null) {
                $documento->{$campo} = $datos[$campo];
            }
        }

        $documento->save();

        return $documento;
    }

    private function directorioDestino(int $empresaId, DcConcepto $concepto): string
    {
        $apartadoClave = mb_strtolower($concepto->apartado?->clave ?? 'sin-apartado');
        $conceptoSlug  = $concepto->slug ?: Str::slug($concepto->nombre);

        return "documentacion_corporativa/{$empresaId}/{$apartadoClave}/{$conceptoSlug}";
    }
}
