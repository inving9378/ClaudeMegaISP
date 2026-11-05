<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** #489: agrega «Desinstalado» a las opciones del estado del cliente (field_modules «estado», módulo Client). */
return new class extends Migration {
    private const CLAVE = 'Desinstalado';
    private const TEXTO = 'Desinstalado (equipo retirado, no se factura ni se reactiva al pagar)';

    private function filas()
    {
        return DB::table('field_modules')->where('name', 'estado')->where('type', '22')
            ->where('options', 'like', '%Cancelado%');
    }

    public function up(): void
    {
        foreach ($this->filas()->get() as $f) {
            $o = json_decode($f->options, true);
            if (is_array($o) && ! isset($o[self::CLAVE])) {
                $o[self::CLAVE] = self::TEXTO;
                DB::table('field_modules')->where('id', $f->id)->update(['options' => json_encode($o, JSON_UNESCAPED_UNICODE)]);
            }
        }
    }

    public function down(): void
    {
        foreach ($this->filas()->get() as $f) {
            $o = json_decode($f->options, true);
            if (is_array($o) && isset($o[self::CLAVE])) {
                unset($o[self::CLAVE]);
                DB::table('field_modules')->where('id', $f->id)->update(['options' => json_encode($o, JSON_UNESCAPED_UNICODE)]);
            }
        }
    }
};
