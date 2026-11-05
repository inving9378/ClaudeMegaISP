<?php

namespace App\Services;

use Carbon\Carbon;

/**
 * Fuente única de fecha/hora de una task (#691).
 *
 * Oficial: start_date (Y-m-d) + start_time/end_time como datetime completo (Y-m-d H:i:s),
 * que es lo que lee el calendario. Los lectores toleran el formato viejo (start_time = solo hora).
 */
class TaskScheduleService
{
    /** Carbon del inicio, tolerante a start_time solo-hora ("16:00[:00]") o datetime. */
    public static function start(?string $startDate, ?string $startTime): ?Carbon
    {
        $startTime = $startTime !== null ? trim($startTime) : null;

        if ($startTime && preg_match('/^\d{4}-\d{2}-\d{2}/', $startTime)) {
            return Carbon::parse($startTime);
        }
        if (!$startDate) {
            return null;
        }
        $day = Carbon::parse($startDate)->toDateString();

        return Carbon::parse($day . ' ' . ($startTime ?: '00:00:00'));
    }

    /** Campos a guardar para un inicio (y fin opcional) coherentes. */
    public static function fields(Carbon $start, ?Carbon $end = null): array
    {
        return [
            'start_date' => $start->toDateString(),
            'start_time' => $start->format('Y-m-d H:i:s'),
            'end_time'   => $end?->format('Y-m-d H:i:s'),
        ];
    }

    /** Zona de negocio de la programación (la de la app; la hora del servidor ya no manda). */
    public static function zona(): string
    {
        try {
            return config('app.timezone') ?: 'America/Mexico_City';
        } catch (\Throwable $e) {
            return 'America/Mexico_City';
        }
    }

    /**
     * Calcula inicio/fin de una tarea a partir de la duración estimada ("H:i") y deja start_date,
     * start_time y end_time coherentes. Alta: arranca en la hora actual de negocio del día elegido.
     * Edición: conserva la hora de inicio recibida.
     */
    public static function conDuracion(array $input, ?string $duracion, bool $esEdicion, ?Carbon $ahora = null): array
    {
        if (!$duracion || empty($input['start_time'])) {
            return $input;
        }
        [$h, $m] = array_pad(explode(':', $duracion), 2, 0);
        if ($esEdicion) {
            $inicio = Carbon::parse($input['start_time'], self::zona());
        } else {
            $ahora = $ahora ?: Carbon::now(self::zona());
            $inicio = Carbon::parse($input['start_time'], self::zona())->setTime($ahora->hour, 0, 0);
        }
        $fin = $inicio->copy()->addHours((int) $h)->addMinutes((int) $m);

        return array_merge($input, self::fields($inicio, $fin));
    }

    /**
     * Normaliza un input con start_date y/o start_time (aunque sea solo "HH:MM") a la forma oficial.
     * Sin start_date ni start_time no toca nada; end_time solo se reformatea si viene.
     */
    public static function normalizar(array $input): array
    {
        $inicio = self::start($input['start_date'] ?? null, $input['start_time'] ?? null);
        if (!$inicio) {
            return $input;
        }
        $fin = !empty($input['end_time']) ? Carbon::parse(preg_replace('/\s*\([^)]*\)/', '', (string) $input['end_time'])) : null;
        $campos = self::fields($inicio, $fin);
        if (!$fin) {
            unset($campos['end_time']);
        }

        return array_merge($input, $campos);
    }

    /** Lectura única para el calendario: [inicio, fin] como Carbon (fin = inicio si falta). */
    public static function ventana(?string $startDate, ?string $startTime, ?string $endTime): array
    {
        $inicio = self::start($startDate, preg_replace('/\s*\([^)]*\)/', '', (string) $startTime) ?: null);
        $fin = $endTime ? Carbon::parse(preg_replace('/\s*\([^)]*\)/', '', $endTime)) : null;

        return [$inicio, $fin ?: $inicio];
    }
}
