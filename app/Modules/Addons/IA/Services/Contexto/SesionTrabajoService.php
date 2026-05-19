<?php

namespace App\Modules\Addons\IA\Services\Contexto;

use App\Modules\Addons\IA\Models\IASesionTrabajo;
use Illuminate\Support\Carbon;

class SesionTrabajoService
{
    /**
     * Si no hay sesión abierta para el usuario, abre una nueva.
     */
    public function abrirSiHaceFalta(?int $userId, ?string $proveedor = null): IASesionTrabajo
    {
        $abierta = IASesionTrabajo::where('user_id', $userId)
            ->whereNull('fin_sesion')
            ->orderByDesc('id')
            ->first();

        if ($abierta) {
            return $abierta;
        }

        return IASesionTrabajo::create([
            'inicio_sesion' => Carbon::now(),
            'proveedor_ia_usado' => $proveedor,
            'user_id' => $userId,
            'created_by' => $userId,
        ]);
    }

    public function cerrar(IASesionTrabajo $sesion, ?string $resumen = null): IASesionTrabajo
    {
        $sesion->update([
            'fin_sesion' => Carbon::now(),
            'resumen' => $resumen ?: $sesion->resumen,
            'updated_by' => auth()->id(),
        ]);
        return $sesion->fresh();
    }

    public function registrarArchivo(IASesionTrabajo $sesion, string $archivo): void
    {
        $lista = $sesion->archivos_modificados ?? [];
        if (!in_array($archivo, $lista, true)) {
            $lista[] = $archivo;
            $sesion->update(['archivos_modificados' => $lista]);
        }
    }

    public function registrarPrompt(IASesionTrabajo $sesion, string $prompt): void
    {
        $lista = $sesion->prompts_destacados ?? [];
        $lista[] = ['prompt' => $prompt, 'at' => Carbon::now()->toIso8601String()];
        // Solo conservamos los últimos 20 destacados
        $lista = array_slice($lista, -20);
        $sesion->update(['prompts_destacados' => $lista]);
    }

    /**
     * Últimas N sesiones del usuario (incluyendo la actual si está abierta).
     *
     * @return array<int, IASesionTrabajo>
     */
    public function ultimas(?int $userId, int $n = 5): array
    {
        return IASesionTrabajo::where('user_id', $userId)
            ->orderByDesc('id')
            ->limit($n)
            ->get()
            ->all();
    }
}
