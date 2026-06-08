<?php

namespace App\Modules\Addons\MegaFamilia\Services;

use App\Modules\Addons\MegaFamilia\Models\AppVersion;
use Carbon\Carbon;
use RuntimeException;

/**
 * Publica un APK Android en public/downloads y sincroniza el registro
 * activo en la tabla app_versions.
 *
 * Uso típico desde el comando artisan:
 *   php artisan megafamilia:deploy-apk
 *
 * El servicio lee la versión automáticamente del output-metadata.json
 * que genera Gradle junto al APK.
 */
class ApkDeployService
{
    const DEFAULT_SOURCE_DIR = '/var/www/megafamilia-rn/android/app/build/outputs/apk/release';
    const DEFAULT_SOURCE_APK = self::DEFAULT_SOURCE_DIR . '/app-release.apk';
    const METADATA_FILE      = self::DEFAULT_SOURCE_DIR . '/output-metadata.json';
    const DEST_DIR           = 'downloads';
    const PLATFORM           = 'android';

    /**
     * Despliega el APK.
     *
     * @param array{
     *   source?:    string,   // ruta al .apk (default: build de RN)
     *   version?:   string,   // forzar versionName (ej. "0.3.6")
     *   code?:      int,      // forzar versionCode  (ej. 9)
     *   changelog?: string,
     *   mandatory?: bool,
     * } $options
     *
     * @return array{version: AppVersion, bytes: int, dest: string, url: string}
     * @throws RuntimeException
     */
    public function deploy(array $options = []): array
    {
        $sourcePath = $options['source'] ?? self::DEFAULT_SOURCE_APK;

        $this->assertReadable($sourcePath);

        [$versionName, $versionCode] = $this->resolveVersion($sourcePath, $options);

        $filename = "megafamilia-v{$versionName}.apk";
        $destDir  = public_path(self::DEST_DIR);
        $destPath = $destDir . '/' . $filename;

        if (!is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }

        if (!copy($sourcePath, $destPath)) {
            throw new RuntimeException("No se pudo copiar el APK a {$destPath}");
        }

        $bytes    = filesize($destPath);
        $relPath  = self::DEST_DIR . '/' . $filename;

        // Desactiva cualquier versión previa activa de Android
        AppVersion::where('platform', self::PLATFORM)->update(['active' => false]);

        // Upsert por (platform, version_code)
        $record = AppVersion::updateOrCreate(
            [
                'platform'     => self::PLATFORM,
                'version_code' => $versionCode,
            ],
            [
                'version_name'  => $versionName,
                'file_path'     => $relPath,
                'file_size'     => $bytes,
                'changelog'     => $options['changelog'] ?? null,
                'is_mandatory'  => (bool) ($options['mandatory'] ?? false),
                'active'        => true,
                'released_at'   => Carbon::now(),
            ]
        );

        return [
            'version' => $record->fresh(),
            'bytes'   => $bytes,
            'dest'    => $destPath,
            'url'     => url('/api/megafamilia/mobile/download/' . $record->id),
        ];
    }

    /**
     * Lee versionName y versionCode desde output-metadata.json de Gradle
     * (generado automáticamente junto al APK release).
     * Si se pasan --version y --code explícitos, esos tienen prioridad.
     */
    private function resolveVersion(string $apkPath, array $options): array
    {
        // Opciones manuales tienen prioridad
        if (isset($options['version'], $options['code'])) {
            return [(string) $options['version'], (int) $options['code']];
        }

        // Busca output-metadata.json en el mismo directorio que el APK
        $metaPath = dirname($apkPath) . '/output-metadata.json';

        if (file_exists($metaPath)) {
            $meta = json_decode(file_get_contents($metaPath), true);
            $elem = $meta['elements'][0] ?? null;

            if ($elem && isset($elem['versionName'], $elem['versionCode'])) {
                return [(string) $elem['versionName'], (int) $elem['versionCode']];
            }
        }

        throw new RuntimeException(
            "No se pudo detectar la versión automáticamente.\n" .
            "Usa --version=X.Y.Z --code=N para indicarla manualmente."
        );
    }

    private function assertReadable(string $path): void
    {
        if (!file_exists($path)) {
            throw new RuntimeException("APK no encontrado: {$path}");
        }
        if (!is_readable($path)) {
            throw new RuntimeException("APK sin permisos de lectura: {$path}");
        }
    }
}
