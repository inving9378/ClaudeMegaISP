<?php

namespace App\Modules\Addons\IA\Services\Contexto;

use Symfony\Component\Process\Process;

class GitContextoService
{
    public function __construct(protected ?string $repoPath = null)
    {
        $this->repoPath = $repoPath ?: base_path();
    }

    public function obtenerContexto(): array
    {
        return [
            'rama_actual' => $this->ramaActual(),
            'ramas' => $this->listarRamas(),
            'ultimo_tag' => $this->ultimoTag(),
            'commits_recientes' => $this->commitsRecientes(20),
            'archivos_modificados_recientes' => $this->archivosModificadosRecientes(7),
            'status' => $this->status(),
        ];
    }

    public function ramaActual(): ?string
    {
        return $this->run(['git', 'rev-parse', '--abbrev-ref', 'HEAD']);
    }

    public function listarRamas(): array
    {
        $out = $this->run(['git', 'branch', '--list', '--format=%(refname:short)']);
        return $out ? array_values(array_filter(array_map('trim', explode("\n", $out)))) : [];
    }

    public function ultimoTag(): ?string
    {
        return $this->run(['git', 'describe', '--tags', '--abbrev=0']);
    }

    /**
     * @return array<int, array{hash:string, autor:string, fecha:string, mensaje:string}>
     */
    public function commitsRecientes(int $cantidad = 20): array
    {
        $sep = '|||SEP|||';
        $fmt = "%h{$sep}%an{$sep}%ad{$sep}%s";
        $out = $this->run(['git', 'log', '-n', (string) $cantidad, "--pretty=format:{$fmt}", '--date=iso']);
        if (!$out) return [];

        $lineas = explode("\n", $out);
        $commits = [];
        foreach ($lineas as $linea) {
            $partes = explode($sep, $linea);
            if (count($partes) !== 4) continue;
            $commits[] = [
                'hash' => $partes[0],
                'autor' => $partes[1],
                'fecha' => $partes[2],
                'mensaje' => $partes[3],
            ];
        }
        return $commits;
    }

    public function archivosModificadosRecientes(int $dias = 7): array
    {
        $out = $this->run([
            'git', 'log', '--since=' . $dias . '.days.ago', '--name-only',
            '--pretty=format:',
        ]);
        if (!$out) return [];
        $archivos = array_unique(array_filter(array_map('trim', explode("\n", $out))));
        sort($archivos);
        return array_values($archivos);
    }

    public function status(): array
    {
        $out = $this->run(['git', 'status', '--porcelain']);
        if (!$out) return [];
        $lineas = explode("\n", $out);
        return array_values(array_filter(array_map('trim', $lineas)));
    }

    protected function run(array $cmd): ?string
    {
        try {
            $proc = new Process($cmd, $this->repoPath);
            $proc->setTimeout(15);
            $proc->run();
            if (!$proc->isSuccessful()) {
                return null;
            }
            return trim($proc->getOutput()) ?: null;
        } catch (\Throwable) {
            return null;
        }
    }
}
