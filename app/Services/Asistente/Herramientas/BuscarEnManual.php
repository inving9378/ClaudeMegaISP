<?php

namespace App\Services\Asistente\Herramientas;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Busca en el Manual del sistema (manual_sections, versión vigente de cada sección). */
class BuscarEnManual extends Herramienta
{
    public function nombre(): string
    {
        return 'buscar_en_manual';
    }

    public function descripcion(): string
    {
        return 'Busca en el manual del sistema cómo funciona una pantalla o un proceso (qué es, cómo se entra, '
            . 'preguntas frecuentes). Úsalo cuando la guía no alcance.';
    }

    public function parametros(): array
    {
        return [
            'type'       => 'object',
            'properties' => ['texto' => ['type' => 'string', 'description' => 'Palabras clave, por ejemplo "registrar pago transferencia"']],
            'required'   => ['texto'],
        ];
    }

    public function ejecutar(User $user, array $args): array
    {
        $palabras = collect(preg_split('/\s+/', mb_strtolower((string) ($args['texto'] ?? ''))))
            ->map(fn ($p) => trim($p, " \t\n\r\0\x0B.,;:¿?¡!\"'()"))
            ->filter(fn ($p) => mb_strlen($p) >= 4)
            ->unique()->take(6)->values();
        if ($palabras->isEmpty()) {
            return ['error' => 'Dame al menos una palabra clave de 4 letras o más.'];
        }

        $vigentes = DB::table('manual_sections')->selectRaw('module_slug, MAX(version) AS v')->groupBy('module_slug');
        $q = DB::table('manual_sections as m')
            ->joinSub($vigentes, 'u', fn ($j) => $j->on('u.module_slug', '=', 'm.module_slug')->on('u.v', '=', 'm.version'))
            ->where(function ($w) use ($palabras) {
                foreach ($palabras as $p) {
                    $w->orWhere('m.content', 'like', "%{$p}%")->orWhere('m.title', 'like', "%{$p}%");
                }
            });

        $roles = $user->getRoleNames()->all();
        $esAdmin = $user->isAdmin() || $user->isSuperAdmin();

        $resultados = [];
        foreach ($q->limit(60)->get(['m.title', 'm.content', 'm.visible_roles']) as $s) {
            $visibles = json_decode((string) $s->visible_roles, true);
            if (!$esAdmin && is_array($visibles) && $visibles !== [] && !array_intersect($visibles, $roles)) {
                continue;
            }
            $texto = mb_strtolower($s->title . ' ' . $s->content);
            $puntos = $palabras->sum(fn ($p) => substr_count($texto, $p));
            $resultados[] = ['puntos' => $puntos, 'titulo' => $s->title, 'contenido' => $s->content, 'primera' => $palabras->first(fn ($p) => str_contains($texto, $p))];
        }
        usort($resultados, fn ($a, $b) => $b['puntos'] <=> $a['puntos']);

        $salida = [];
        foreach (array_slice($resultados, 0, 3) as $r) {
            $contenido = strip_tags($r['contenido']);
            $pos = $r['primera'] ? mb_stripos($contenido, $r['primera']) : 0;
            $inicio = max(0, ($pos === false ? 0 : $pos) - 300);
            $salida[] = ['seccion' => $r['titulo'], 'extracto' => trim(mb_substr($contenido, $inicio, 1500))];
        }

        return $salida
            ? ['resultados' => $salida, 'enlace_manual' => $this->enlace('manual')]
            : ['resultados' => [], 'nota' => 'No encontré nada en el manual con esas palabras.'];
    }
}
