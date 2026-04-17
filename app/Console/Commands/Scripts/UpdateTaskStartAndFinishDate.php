<?php

namespace App\Console\Commands\Scripts;

use App\Models\Task;
use Illuminate\Console\Command;
use Carbon\Carbon;
use DateTime;
use Exception;

class UpdateTaskStartAndFinishDate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:update-task-start-and-finish-date';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update start and finish times of tasks to specific times within the day';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $tasks = Task::all();
        foreach ($tasks as $task) {
            $task->update([
                'start_time' => $this->updateStartTime($task->start_time),
                'end_time' => $this->updateEndTime($task->start_time),
            ]);
        }
    }

    /**
     * Set the time to 00:00:00 while keeping the original date.
     */
    public function updateStartTime($date)
    {
        $date = $this->parseDate($date);
        try {
            return $date->setTime($date->hour, 0, 0);
        } catch (Exception $e) {
            dd($date);
        }
    }

    /**
     * Set the time to 23:59:59 while keeping the original date.
     */
    public function updateEndTime($date)
    {
        $date = $this->parseDate($date);
        try {
            return $date->setTime($date->hour, 59, 59);
        } catch (Exception $e) {
            dd($date);
        }
    }

    /**
     * Parse the date from various formats to a Carbon instance.
     */
    protected function parseDate($date)
    {
        try {
            // Si $date ya es una instancia de Carbon o DateTime, retorna directamente
            if ($date instanceof Carbon || $date instanceof \DateTime) {
                return Carbon::instance($date);
            }

            // Si $date es un número, asumir que es un timestamp UNIX
            if (is_numeric($date)) {
                return Carbon::createFromTimestamp($date);
            }

            // Si $date es una cadena de texto en el formato 'Y-m-d H:i:s'
            if (is_string($date) && preg_match('/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/', $date)) {
                return Carbon::createFromFormat('Y-m-d H:i:s', $date);
            }

            // Si $date es una cadena en formato ISO 8601 con milisegundos y zona horaria 'Z'
            if (is_string($date) && preg_match('/\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}Z/', $date)) {
                return Carbon::createFromFormat('Y-m-d\TH:i:s.v\Z', $date, 'UTC');
            }

            // Si $date es una cadena con formato de texto largo en inglés y con zona horaria
            if (is_string($date)) {
                // Limpiar el texto de la zona horaria
                $cleanedDate = preg_replace('/\sGMT[-+]\d{4}.*$/', '', $date);
                $date = Carbon::createFromFormat('D M d Y H:i:s', $cleanedDate);

                if ($date !== false) {
                    return $date;
                }
            }

            // Si $date es una cadena de texto en otro formato, intentar parsearla
            return Carbon::parse($date);
        } catch (Exception $e) {
            dd($date);
            // En caso de error, retornar la fecha actual como una instancia de Carbon
            return Carbon::now();
        }
    }
}
