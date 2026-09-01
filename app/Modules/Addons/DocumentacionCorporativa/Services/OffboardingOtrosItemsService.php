<?php

namespace App\Modules\Addons\DocumentacionCorporativa\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Checklist de los 6 ítems fijos de offboarding sin tabla propia (item
 * roadmap #839, Fase 5d-2a). Diseño aprobado por Irving: ítems fijos en esta
 * constante + estado persistido como JSON en una columna de `users`
 * (`offboarding_otros_items`) — sin tabla de catálogo ni tabla pivote.
 *
 * Alcance acotado a propósito (q2 aprobada): solo registro manual
 * (completado/fecha/responsable/notas + evidencia OPCIONAL). Ninguna acción
 * automática (revocar VPN, deshabilitar correo, etc.) — eso es
 * `requiere_irving` y queda fuera de esta fase.
 *
 * Usa `DB::table('users')` en vez de el modelo Eloquent `User` para no
 * tocar `App\Models\User` (fillable/casts) por esta columna de un solo addon.
 */
class OffboardingOtrosItemsService
{
    public const ITEMS = [
        'correo'       => 'Correo electrónico',
        'vpn'          => 'Acceso VPN',
        'whatsapp'     => 'WhatsApp corporativo',
        'equipo'       => 'Equipo asignado',
        'respaldo'     => 'Respaldo de información',
        'finiquito_rh' => 'Finiquito / RH',
    ];

    private const DISK = 'local';

    /** Los 6 ítems fijos con su estado actual (default: pendiente, sin tocar). */
    public function listar(int $userId): array
    {
        User::query()->findOrFail($userId, ['id']);

        $estado = $this->leerEstado($userId);

        return $this->itemsConEstado($estado);
    }

    /** Marca un ítem completado/pendiente + notas + evidencia opcional. */
    public function marcar(int $userId, string $itemClave, array $datos, ?UploadedFile $evidencia): array
    {
        abort_unless(array_key_exists($itemClave, self::ITEMS), 422, 'Ítem de offboarding desconocido.');

        User::query()->findOrFail($userId, ['id']);

        $estado = $this->leerEstado($userId);
        $actual = $estado[$itemClave] ?? [];

        $completado = (bool) ($datos['completado'] ?? false);

        $rutaEvidencia = $actual['evidencia_path'] ?? null;
        if ($evidencia) {
            $rutaEvidencia = $this->guardarEvidencia($userId, $itemClave, $evidencia);
        }

        $estado[$itemClave] = [
            'completado'          => $completado,
            'fecha'               => $completado ? ($datos['fecha'] ?? now()->toDateString()) : null,
            'responsable_user_id' => $completado ? (int) ($datos['responsable_user_id'] ?? auth()->id()) : null,
            'notas'               => $datos['notas'] ?? null,
            'evidencia_path'      => $rutaEvidencia,
        ];

        $this->guardarEstado($userId, $estado);

        return $this->itemsConEstado($estado)[$itemClave];
    }

    private function itemsConEstado(array $estado): array
    {
        $items = [];

        foreach (self::ITEMS as $clave => $etiqueta) {
            $fila = $estado[$clave] ?? [];

            $items[$clave] = [
                'clave'               => $clave,
                'etiqueta'            => $etiqueta,
                'completado'          => (bool) ($fila['completado'] ?? false),
                'fecha'               => $fila['fecha'] ?? null,
                'responsable_user_id' => $fila['responsable_user_id'] ?? null,
                'notas'               => $fila['notas'] ?? null,
                'evidencia_path'      => $fila['evidencia_path'] ?? null,
            ];
        }

        return $items;
    }

    private function leerEstado(int $userId): array
    {
        $json = DB::table('users')->where('id', $userId)->value('offboarding_otros_items');

        if (! $json) {
            return [];
        }

        return json_decode($json, true) ?: [];
    }

    private function guardarEstado(int $userId, array $estado): void
    {
        DB::table('users')->where('id', $userId)->update([
            'offboarding_otros_items' => json_encode($estado, JSON_UNESCAPED_UNICODE),
        ]);
    }

    private function guardarEvidencia(int $userId, string $itemClave, UploadedFile $evidencia): string
    {
        $extension = mb_strtolower($evidencia->getClientOriginalExtension() ?: 'bin');
        $nombre    = Str::uuid() . ".{$extension}";
        $directorio = "documentacion-corporativa/offboarding/{$userId}";

        $ruta = Storage::disk(self::DISK)->putFileAs($directorio, $evidencia, $nombre);

        if ($ruta === false) {
            throw new RuntimeException('No se pudo guardar la evidencia en disco.');
        }

        return $ruta;
    }
}
