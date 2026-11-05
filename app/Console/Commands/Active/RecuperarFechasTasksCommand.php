<?php

namespace App\Console\Commands\Active;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * UNI-T F3 (#889): recupera start_time/end_time reescritos el 2026-10-14 desde una tabla de respaldo
 * (restaurada aparte, nunca sobre `megaisp`) y deja start_date coherente con start_time.
 * No toca validated_at, estados ni updated_at.
 */
class RecuperarFechasTasksCommand extends Command
{
    protected $signature = 'tasks:recuperar-fechas
        {--origen=megaisp_dryrun.uni_tasks_pre : tabla (db.tabla) con tasks anteriores a la reescritura}
        {--dia-reescritura=2026-10-14 : día al que se llevaron las fechas}
        {--respaldo= : CSV de respaldo de columnas de fecha (aplicar lo escribe; revertir lo lee)}
        {--aplicar : escribe (sin esto es simulación)}
        {--revertir : restaura las columnas de fecha desde --respaldo}';
    protected $description = 'Recupera fechas de tasks reescritas y concilia start_date con start_time (UNI-T F3)';

    public function handle(): int
    {
        return $this->option('revertir') ? $this->revertir() : $this->recuperar();
    }

    private function recuperar(): int
    {
        $origen = $this->option('origen');
        if (!preg_match('/^\w+\.\w+$/', $origen)) {
            $this->error('--origen debe ser db.tabla');
            return self::FAILURE;
        }
        $dia = $this->option('dia-reescritura');
        $aplicar = $this->option('aplicar');
        $cambios = [];
        $omitidos = [];

        $viejas = DB::table($origen)->get(['id', 'start_time', 'end_time'])->keyBy('id');
        DB::table('tasks')->orderBy('id')->get(['id', 'start_date', 'start_time', 'end_time'])->each(function ($t) use ($viejas, $dia, &$cambios, &$omitidos) {
            $nuevo = ['start_date' => $t->start_date, 'start_time' => $t->start_time, 'end_time' => $t->end_time];
            $v = $viejas[$t->id] ?? null;
            // Recuperar solo si el valor actual es el de la reescritura (mismo día, misma hora del día).
            if ($v && $v->start_time && strlen($v->start_time) > 8 && $t->start_time && substr($t->start_time, 0, 10) === $dia && $v->start_time !== $t->start_time) {
                if (substr($v->start_time, 11) === substr($t->start_time, 11)) {
                    $nuevo['start_time'] = $v->start_time;
                    if ($t->end_time && $v->end_time && substr($t->end_time, 0, 10) === $dia && substr($v->end_time, 11) === substr($t->end_time, 11)) {
                        $nuevo['end_time'] = $v->end_time;
                    }
                } else {
                    $omitidos[] = $t->id; // editada después de la reescritura: se respeta
                }
            }
            // Hora suelta + start_date → datetime completo (misma regla que TaskScheduleService::start).
            if ($nuevo['start_time'] && strlen($nuevo['start_time']) <= 8 && $t->start_date) {
                $nuevo['start_time'] = substr($t->start_date, 0, 10) . ' ' . $nuevo['start_time'];
            }
            // start_date siempre coherente con el día de start_time.
            if ($nuevo['start_time'] && strlen($nuevo['start_time']) > 8) {
                $nuevo['start_date'] = substr($nuevo['start_time'], 0, 10);
            }
            if ($nuevo !== ['start_date' => $t->start_date, 'start_time' => $t->start_time, 'end_time' => $t->end_time]) {
                $cambios[$t->id] = [$t, $nuevo];
            }
        });

        $recup = count(array_filter($cambios, fn ($c) => $c[1]['start_time'] !== $c[0]->start_time && strlen($c[0]->start_time ?? '') > 8));
        $this->info(($aplicar ? '' : '[simulación] ') . 'Filas a cambiar: ' . count($cambios) . " (fechas recuperadas del respaldo: {$recup}; editadas después y respetadas: " . count($omitidos) . ')');
        if ($omitidos) {
            $this->line('Respetadas: ' . implode(',', $omitidos));
        }
        if (!$aplicar) {
            return self::SUCCESS;
        }

        $csv = $this->option('respaldo');
        if (!$csv) {
            $this->error('--respaldo es obligatorio para aplicar');
            return self::FAILURE;
        }
        $fh = fopen($csv, 'w');
        foreach (DB::table('tasks')->orderBy('id')->get(['id', 'start_date', 'start_time', 'end_time']) as $r) {
            fputcsv($fh, [$r->id, $r->start_date, $r->start_time, $r->end_time]);
        }
        fclose($fh);
        foreach ($cambios as $id => [$t, $nuevo]) {
            DB::table('tasks')->where('id', $id)->update($nuevo);
        }
        $this->info("Aplicado. Respaldo de columnas de fecha: {$csv}");
        return self::SUCCESS;
    }

    private function revertir(): int
    {
        $csv = $this->option('respaldo');
        if (!$csv || !is_file($csv)) {
            $this->error('--respaldo debe apuntar al CSV generado al aplicar');
            return self::FAILURE;
        }
        $n = 0;
        $fh = fopen($csv, 'r');
        while (($r = fgetcsv($fh)) !== false) {
            $n += DB::table('tasks')->where('id', $r[0])->update([
                'start_date' => $r[1] === '' ? null : $r[1],
                'start_time' => $r[2] === '' ? null : $r[2],
                'end_time' => $r[3] === '' ? null : $r[3],
            ]);
        }
        fclose($fh);
        $this->info("Revertidas {$n} filas.");
        return self::SUCCESS;
    }
}
