<?php

namespace App\Models\Asistente;

use Illuminate\Database\Eloquent\Model;

/**
 * Archivo del chat de JARVIS: lo que el usuario adjunta (foto, PDF, Excel, Word, video, audio) o lo
 * que JARVIS genera (Excel de resultados). Los bytes están en `carpeta` (disco local) y se borran
 * solos al vencer; la fila se conserva con estado `borrado`.
 */
class Adjunto extends Model
{
    public const SUBIENDO = 'subiendo';
    public const LISTO    = 'listo';
    public const ERROR    = 'error';
    public const BORRADO  = 'borrado';

    protected $table = 'asistente_adjuntos';

    protected $guarded = [];

    protected $casts = [
        'meta'       => 'array',
        'expira_at'  => 'datetime',
        'borrado_at' => 'datetime',
    ];

    public function disponible(): bool
    {
        return $this->estado === self::LISTO && $this->borrado_at === null;
    }

    public function ruta(string $archivo = ''): string
    {
        return storage_path('app/' . $this->carpeta . ($archivo !== '' ? '/' . $archivo : ''));
    }

    public function original(): string
    {
        return $this->ruta('original' . ($this->extension ? '.' . $this->extension : ''));
    }

    public function meta(string $clave, mixed $default = null): mixed
    {
        return data_get($this->meta ?? [], $clave, $default);
    }

    /** Lo que ve el chat: nombre, tipo, tamaño y si todavía se puede abrir. */
    public function paraPantalla(): array
    {
        return [
            'id'        => $this->id,
            'nombre'    => $this->nombre,
            'tipo'      => $this->tipo,
            'mime'      => $this->mime,
            'bytes'     => (int) $this->bytes,
            'estado'    => $this->disponible() ? 'listo' : $this->estado,
            'resumen'   => $this->meta('resumen'),
            'origen'    => $this->origen,
            'miniatura' => $this->disponible() && in_array($this->tipo, ['imagen', 'video'], true)
                ? '/ia/asistente/adjuntos/' . $this->id . '/ver?miniatura=1' : null,
            'enlace'    => $this->disponible() ? '/ia/asistente/adjuntos/' . $this->id . '/ver' : null,
            'descarga'  => $this->disponible() ? '/ia/asistente/adjuntos/' . $this->id . '/descargar' : null,
            'expira_at' => $this->expira_at?->toIso8601String(),
        ];
    }
}
