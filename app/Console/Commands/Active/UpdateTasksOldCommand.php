<?php

namespace App\Console\Commands\Active;

use App\Http\Repository\TaskRepository;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class UpdateTasksOldCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'update_task_old_command:process';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Actualiza la Fecha de las Tareas que ha pasado la fecha de ejecución';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        if (config('unificacion.interruptores.fechas_atrasadas_intactas_f2')) {
            // F2: lo atrasado conserva su fecha; reprogramar es una acción explícita del usuario.
            Log::info('Comando de Actualizar Tareas omitido: las fechas atrasadas se conservan (UNI-T F2)');
            return self::SUCCESS;
        }

        $taskRepository = new TaskRepository();

        $tasksToUpdate = $taskRepository->getTasksToUpdate();

        foreach ($tasksToUpdate as $task) {

            // Procesar el end_time
            $cleanedEndTime = preg_replace('/\(.*\)/', '', $task->end_time); // Elimina la parte entre paréntesis
            $endTime = Carbon::parse($cleanedEndTime);

            // Si es null o menor a la fecha actual, asignar la fecha de hoy
            if (!$endTime || $endTime->isPast()) {
                $endHour = $endTime ? $endTime->format('H:i') : '10:00'; // Mantener la hora o usar 10:00 si es null
                $endTime = Carbon::today()->setTimeFromTimeString($endHour); // Fecha de hoy, mantener la hora
            }

            // Procesar el start_time
            $cleanedStartTime = preg_replace('/\(.*\)/', '', $task->start_time); // Elimina la parte entre paréntesis
            $startTime = Carbon::parse($cleanedStartTime);

            // Si es null o menor a la fecha actual, asignar la fecha de hoy
            if (!$startTime || $startTime->isPast()) {
                $startHour = $startTime ? $startTime->format('H:i') : '10:00'; // Mantener la hora o usar 10:00 si es null
                $startTime = Carbon::today()->setTimeFromTimeString($startHour); // Fecha de hoy, mantener la hora
            }

            if (config('unificacion.interruptores.programacion_unica')) {
                // Misma forma oficial que el resto: start_date sigue al día de start_time.
                $task->fill(\App\Services\TaskScheduleService::fields($startTime, $endTime));
                $task->save();
                continue;
            }

            $task->start_time = $startTime;
            $task->end_time = $endTime;
            $task->save();
        }
        Log::info('Comando de Actualizar Tareas ejecutado Corrrectamente');
    }
}
