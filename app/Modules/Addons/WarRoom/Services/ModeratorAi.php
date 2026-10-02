<?php

namespace App\Modules\Addons\WarRoom\Services;

use App\Modules\Addons\IA\Services\IA;
use App\Modules\Addons\WarRoom\Models\Meeting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ModeratorAi
{
    public function getSuggestion(Meeting $meeting): ?string
    {
        if (! ($meeting->settings['ai_suggestions'] ?? true)) {
            return null;
        }

        $section = $meeting->currentSection();
        if (! $section || $section->status !== 'in_progress' || ! $section->started_at) {
            return null;
        }

        $elapsed = now()->diffInSeconds($section->started_at);
        $planned = max($section->time_planned_seconds, 1);
        $ratio   = $elapsed / $planned;

        if ($ratio < 0.80) {
            return null;
        }

        // Clave de cache agrupa por % completado en bloques de 5%
        $bucket   = floor($ratio * 20); // cambia cada 5%
        $cacheKey = "warroom.suggestion.{$meeting->id}.{$section->id}.{$bucket}";

        return Cache::remember($cacheKey, 60, fn () => $this->generate($section, $ratio, $meeting));
    }

    private function generate($section, float $ratio, Meeting $meeting): string
    {
        if ($ratio >= 1.0) {
            $excess = round(($ratio - 1) * 100);
            return "Sección excedida en {$excess}%. Cierra la discusión y documenta pendientes como tarea.";
        }

        try {
            $recentTasks = $meeting->actionItems()
                ->where('section_key', $section->section_key)
                ->latest()
                ->take(3)
                ->pluck('description')
                ->implode('. ');

            $prompt = "Junta operativa de un ISP en México. Sección: '{$section->section_key}'. "
                    . "Tiempo utilizado: " . round($ratio * 100) . "% del asignado. "
                    . ($recentTasks ? "Tareas creadas: {$recentTasks}. " : '')
                    . "Sugiere UNA acción concreta para el moderador (máx 20 palabras, español neutral). "
                    . "Objetivo: que la junta avance sin atascarse.";

            // IA asignada en Integraciones → Módulos IA. Sin asignación (o si falla) se usa
            // la sugerencia fija: la junta nunca se queda esperando a la IA (timeout 8s, sin reintentos).
            $r = IA::enviar('warroom.moderador', $prompt, [], null, [], [
                'max_tokens' => 80, 'timeout' => 8, 'reintentos' => 0,
            ]);

            return trim($r['texto']) ?: $this->fallback($ratio);
        } catch (\Throwable $e) {
            Log::debug('ModeratorAi fallback', ['err' => $e->getMessage()]);
            return $this->fallback($ratio);
        }
    }

    private function fallback(float $ratio): string
    {
        $pct = round($ratio * 100);
        return "Sección al {$pct}% del tiempo asignado. Considera cerrar y avanzar a la siguiente.";
    }
}
