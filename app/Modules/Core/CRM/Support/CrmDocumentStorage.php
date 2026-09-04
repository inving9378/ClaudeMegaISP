<?php

namespace App\Modules\Core\CRM\Support;

use Illuminate\Support\Facades\Log;

/**
 * Salvaguarda compartida (#81/#169) para borrar el archivo físico de un
 * documento CRM por su `files.path` exacto.
 *
 * La usan tanto el command crm:purge-orphan-documents como CrmObserver, para
 * que la regla de seguridad viva en UN solo lugar.
 *
 * Reglas:
 *  - Resuelve "/storage/..." -> storage/app/public/... (igual que el symlink).
 *  - SOLO borra si es un archivo regular (is_file); NUNCA un directorio.
 *  - El realpath debe quedar DENTRO de storage/app/public (anti path-traversal).
 *  - NUNCA borra una carpeta client/{id}/ completa: esos ids se solapan con
 *    documentos de clientes reales vivos (DocumentClient). Solo unlink puntual.
 */
class CrmDocumentStorage
{
    public static function deleteFileSafely(?string $path): bool
    {
        $real = self::resolveExistingFile($path);
        if ($real === null) {
            return false;
        }

        return @unlink($real);
    }

    /**
     * Solo-lectura (#9990084): true si `path` resuelve a un archivo regular
     * existente dentro de storage/app/public. Reusa la MISMA resolución que
     * deleteFileSafely(), sin tocar nada.
     */
    public static function fileExists(?string $path): bool
    {
        return self::resolveExistingFile($path) !== null;
    }

    /** Resuelve `files.path` a un realpath dentro de storage/app/public, o null si no aplica. */
    private static function resolveExistingFile(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        $rel  = preg_replace('#^/?storage/#', '', $path);
        $abs  = storage_path('app/public/' . $rel);
        $base = realpath(storage_path('app/public'));
        $real = realpath($abs);

        if ($real === false || $base === false) {
            return null; // no existe / no resoluble
        }
        if (strpos($real, $base . DIRECTORY_SEPARATOR) !== 0) {
            Log::warning("[CrmDocumentStorage] saltado (fuera de storage/app/public): {$path}");
            return null;
        }
        if (!is_file($real)) {
            // Directorio o no-archivo -> jamás tocar (evita borrar carpetas client/{id}).
            Log::warning("[CrmDocumentStorage] saltado (no es archivo regular): {$path}");
            return null;
        }

        return $real;
    }
}
