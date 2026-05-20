<?php

namespace App\Console\Commands\Active;

use App\Modules\Core\Configuracion\Models\ApiMobileConfig;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

/**
 * Build → copia → publica el APK de MegaFamilia en una sola corrida.
 *
 * Compila con `flutter build apk --release --no-shrink` desde
 * `mobile/megafamilia`, copia la salida a `public/apk/` con un nombre
 * versionado (lee `version` de pubspec.yaml) **y** actualiza un alias
 * `megafamilia.apk` para compatibilidad con clientes viejos. Persiste
 * en `api_mobile_config`: `app_version`, `apk_url` (con cache-buster
 * `?v=<timestamp>`) y `release_notes`.
 *
 * El endpoint `GET /api/megafamilia/app-version` lee esos campos —
 * cualquier app v0.2+ instalada va a detectar la actualización
 * automáticamente en su próximo arranque.
 */
class PublishMegaFamiliaApkCommand extends Command
{
    protected $signature = 'megafamilia:publish-apk
                            {--skip-build : Saltar `flutter build` y solo publicar el APK existente}
                            {--notes= : Release notes que aparecerán en el diálogo de update}';

    protected $description = 'Build + deploy del APK MegaFamilia + bump de versión en BD (OTA)';

    private const PROJECT_DIR = '/var/www/megaisp/mobile/megafamilia';
    private const BUILD_APK = '/var/www/megaisp/mobile/megafamilia/build/app/outputs/flutter-apk/app-release.apk';
    private const PUBLIC_DIR = '/var/www/megaisp/public/apk';

    public function handle(): int
    {
        $version = $this->readPubspecVersion();
        if (! $version) {
            $this->error('No pude leer `version:` de pubspec.yaml');
            return self::FAILURE;
        }
        $this->info("Versión declarada en pubspec.yaml: {$version}");

        if (! $this->option('skip-build')) {
            if (! $this->runFlutterBuild()) {
                return self::FAILURE;
            }
        } else {
            $this->warn('Saltando build (--skip-build)');
        }

        if (! is_file(self::BUILD_APK)) {
            $this->error('No existe el APK construido: ' . self::BUILD_APK);
            return self::FAILURE;
        }

        if (! is_dir(self::PUBLIC_DIR)) {
            mkdir(self::PUBLIC_DIR, 0775, true);
        }

        $versionedName = "megafamilia-v{$version}.apk";
        $versionedPath = self::PUBLIC_DIR . '/' . $versionedName;
        $aliasPath = self::PUBLIC_DIR . '/megafamilia.apk';

        if (! copy(self::BUILD_APK, $versionedPath)) {
            $this->error("No pude copiar a {$versionedPath}");
            return self::FAILURE;
        }
        chmod($versionedPath, 0644);

        // Alias `megafamilia.apk` apunta siempre a la última build.
        if (file_exists($aliasPath)) {
            @unlink($aliasPath);
        }
        copy($versionedPath, $aliasPath);
        chmod($aliasPath, 0644);

        $sha = hash_file('sha256', $versionedPath);
        $size = filesize($versionedPath);

        // URL canónica = nombre versionado. Si quieres distribuir el
        // alias estable, cambia esta línea a `megafamilia.apk?v=…`.
        $url = 'http://192.168.105.11/apk/' . $versionedName;

        $bulk = [
            'app_version' => $version,
            'apk_url' => $url,
        ];
        if ($notes = $this->option('notes')) {
            $bulk['release_notes'] = $notes;
        }
        ApiMobileConfig::setBulk($bulk);

        $this->newLine();
        $this->info('═══ APK publicada ═══');
        $this->table(['campo', 'valor'], [
            ['versión', $version],
            ['archivo', $versionedName],
            ['tamaño', number_format($size / 1024 / 1024, 2) . ' MB (' . $size . ' bytes)'],
            ['sha256', $sha],
            ['url', $url],
            ['alias', 'http://192.168.105.11/apk/megafamilia.apk'],
            ['endpoint', 'GET /api/megafamilia/app-version'],
        ]);

        return self::SUCCESS;
    }

    private function readPubspecVersion(): ?string
    {
        $pubspec = self::PROJECT_DIR . '/pubspec.yaml';
        if (! is_file($pubspec)) {
            return null;
        }
        foreach (file($pubspec) as $line) {
            if (preg_match('/^version:\s*(\S+)/', $line, $m)) {
                // Strip "+build" suffix → solo "x.y.z"
                return explode('+', $m[1])[0];
            }
        }
        return null;
    }

    private function runFlutterBuild(): bool
    {
        $this->info('Ejecutando: flutter build apk --release --no-shrink (esto tarda ~3 min)');

        $process = new Process(
            ['flutter', 'build', 'apk', '--release', '--no-shrink'],
            self::PROJECT_DIR,
            [
                'PATH' => getenv('PATH') . ':/opt/flutter/bin',
                'ANDROID_SDK_ROOT' => '/opt/android-sdk',
                'HOME' => getenv('HOME') ?: '/home/meganet',
            ]
        );
        $process->setTimeout(600);
        $process->run(function ($_, $buf) {
            // Stream output de gradle al usuario sin retención.
            $this->getOutput()->write($buf);
        });

        if (! $process->isSuccessful()) {
            $this->error('flutter build falló (exit code ' . $process->getExitCode() . ')');
            return false;
        }
        return true;
    }
}
