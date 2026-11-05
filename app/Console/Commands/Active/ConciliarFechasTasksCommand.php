<?php

namespace App\Console\Commands\Active;

use App\Models\Task;
use App\Services\TaskScheduleService;
use Illuminate\Console\Command;

/** Conciliación opcional (#691): start_time solo-hora o start_date distinto del día de start_time. */
class ConciliarFechasTasksCommand extends Command
{
    protected $signature = 'tasks:conciliar-fechas {--dry-run : Solo cuenta y lista, no escribe}';
    protected $description = 'Unifica start_date/start_time de tasks con formato viejo o fechas inconsistentes';

    public function handle(): int
    {
        $dry = $this->option('dry-run');
        $n = 0;
        Task::whereNotNull('start_date')->whereNotNull('start_time')->orderBy('id')->chunkById(500, function ($tasks) use (&$n, $dry) {
            foreach ($tasks as $t) {
                $start = TaskScheduleService::start($t->start_date, $t->start_time);
                $nuevo = $start->format('Y-m-d H:i:s');
                $dateOk = $start->toDateString() === substr((string) $t->start_date, 0, 10);
                if ($nuevo === $t->start_time && $dateOk) {
                    continue;
                }
                $n++;
                $this->line("#{$t->id}: {$t->start_date} | {$t->start_time} → {$start->toDateString()} | {$nuevo}");
                if (!$dry) {
                    Task::whereKey($t->id)->update(['start_date' => $start->toDateString(), 'start_time' => $nuevo]);
                }
            }
        });
        $this->info(($dry ? '[dry-run] ' : '') . "Inconsistentes: {$n}");
        return self::SUCCESS;
    }
}
