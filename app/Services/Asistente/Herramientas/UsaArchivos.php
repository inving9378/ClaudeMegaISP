<?php

namespace App\Services\Asistente\Herramientas;

use App\Models\Asistente\Adjunto;
use App\Models\Asistente\Conversacion;
use App\Models\User;
use App\Services\Asistente\Datos\ConsultaRechazada;

/** Archivos del propio usuario que una herramienta puede usar. */
trait UsaArchivos
{
    /** @return array<int, Adjunto> */
    protected function hojasDe(User $user, mixed $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', array_filter((array) $ids, 'is_numeric'))));
        if (!$ids) {
            return [];
        }
        $adjuntos = Adjunto::whereIn('id', $ids)->where('user_id', $user->id)->get()->keyBy('id');
        $hojas = [];
        foreach ($ids as $id) {
            $a = $adjuntos[$id] ?? null;
            if (!$a) {
                throw new ConsultaRechazada("No encuentro el archivo #{$id} entre los que te mandó este usuario.");
            }
            if (!$a->disponible()) {
                throw new ConsultaRechazada("El archivo #{$id} «{$a->nombre}» ya se borró (los archivos se borran solos). Pide que lo vuelvan a mandar.");
            }
            if ($a->tipo !== 'hoja') {
                throw new ConsultaRechazada("El archivo #{$id} no es un Excel o CSV; para leerlo usa leer_archivo.");
            }
            $hojas[] = $a;
        }

        return $hojas;
    }

    protected function conversacionDe(User $user): ?Conversacion
    {
        return Conversacion::where('user_id', $user->id)->where('activa', true)->latest('id')->first();
    }
}
