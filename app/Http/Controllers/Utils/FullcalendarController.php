<?php

namespace App\Http\Controllers\Utils;

use App\Http\Controllers\Controller;
use App\Modules\Core\Configuracion\Repositories\DefaultValueRepository;
use App\Http\Repository\ModuleRepository;
use App\Models\Task;
use App\Services\Billing\FechasGraciaService;
use App\Services\TaskService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class FullcalendarController extends Controller
{
    public function getBillingConfiguration(Request $request)
    {
        $events = [];
        $billingDatePost = $request->postData["billing_date"];
        $billingExpirationPost = $request->postData["billing_expiration"];
        $gracePeriodPost = $request->postData["grace_period"];
        // Misma regla que BillingExpirationService::adjustBillingDate: el día de
        // facturación se limita a los días del mes (31 en un mes de 30 = último día)
        // y la expiración se suma en días; la gracia corre desde la fecha de corte.
        $base = $billingDatePost
            ? FechasGraciaService::diaFacturacion(Carbon::now()->year, Carbon::now()->month, (int) $billingDatePost)
            : null;
        if ($base) {
            $billingDate = $base->toDateString();
            $events[] = [
                'title' => 'Dia de facturación',
                'start' => $billingDate,
                'end' => $billingDate,
                'color' => '#c7e4f3'
            ];
        }
        $corte = $base ? $base->copy()->addDays((int) $billingExpirationPost) : null;
        if ($billingExpirationPost && $corte) {
            $billingExpiration = $corte->toDateString();
            $events[] = [
                'title' => 'Dia de Expiracion del servicio',
                'start' => $billingExpiration,
                'end' => $billingExpiration,
                'color' => '#f8f0c7'
            ];
        }
        // Gracia 0 también se pinta (fin de gracia = día de corte).
        if ($gracePeriodPost !== null && $gracePeriodPost !== '' && $corte) {
            $gracePeriod = FechasGraciaService::finGracia($corte, (int) $gracePeriodPost)->toDateString();
            $events[] = [
                'title' => 'Fin del Periodo de Gracia',
                'start' => $gracePeriod,
                'end' => $gracePeriod,
                'color' => '#ecd0d0'
            ];
        }
        return $events;
    }

    public function getTaskEvents(Request $request)
    {
        $filters = $request->all();

        $events = [];
        $serviceTask = new TaskService();

        $tasks = $serviceTask->getTasksToEvents($filters);

        $moduleRepository = new ModuleRepository();
        $module = $moduleRepository->getModuleByName("FiltersTaskCalendar");
        $initialView = null;
        if ($module) {
            $moduleId = $module->id;
            $defaultValueRepository = new DefaultValueRepository();
            $defaultInitialView = $defaultValueRepository->getDefaultValueFilteredByModuleIdAndField($moduleId, "initialView");
            if ($defaultInitialView) {
                $initialView = $defaultInitialView->value;
            }
        }



        foreach ($tasks as $task) {
            $start = $this->limpiaFechas($task->start_time);
            $end = $this->limpiaFechas($task->end_time);

            // Convertir las fechas a objetos Carbon directamente
            if (config('unificacion.interruptores.programacion_unica')) {
                [$timeStart, $timeEnd] = \App\Services\TaskScheduleService::ventana($task->start_date, $task->start_time, $task->end_time);
                if (!$timeStart) {
                    continue;
                }
                $start = $timeStart->format('Y-m-d H:i:s');
                $end = $timeEnd->format('Y-m-d H:i:s');
            } else {
                $timeStart = Carbon::parse($start);
                $timeEnd = Carbon::parse($end);
            }

            $timeDifference = $this->getTimeDifference($timeStart, $timeEnd);

            $events[] = [
                'title' => $task->title,
                'start' =>  Carbon::parse($start)->format('Y-m-d\TH:i:s'), // Incluye la hora en el formato ISO 8601
                'end' =>  Carbon::parse($end)->format('Y-m-d\TH:i:s'), // Incluye la hora en el formato ISO 8601
                'color' => '#ecd0d0',
                'status' => $task->status,
                'priority' => $task->priority,
                // Item #1158: una orden de Talento se mueve de día con motivo (Talento › Órdenes o «Mi día»),
                // no arrastrándola aquí.
                'es_orden_talento' => (bool) $task->talento_type_id,
                'id' => $task->id,
                'address' => $task->address,
                'time' => $timeStart->format("H:i") . ' - ' . $timeEnd->format("H:i") . ' (' . $timeDifference . ')',
                'timeStart' => $timeStart->format("H:i"),
                'task_color' => $task->task_color,
                'color_assigned' => $task->color_task_assigned,
                'color_status' => $this->getColor($task, 'status'),
                'color_priority' => $this->getColor($task, 'priority'),
                'client_main_information_id' => $task->client_main_information_id,
                'user_or_team_name' => $task->user_or_team_name,
                'classNames' => 'a-event event-content-task id-item-' . $task->id,
                'initialView' => $initialView
            ];
        }
        return $events;
    }

    public function getColor($task, $field)
    {
        $color = '';
        if (isset(Task::TASK_COLOR[$field][$task->$field])) {
            $color = Task::TASK_COLOR[$field][$task->$field];
        }

        return $color;
    }

    public function getTimeDifference($timeStart, $timeEnd)
    {
        // Calcular la diferencia total en minutos entre los dos objetos Carbon
        $totalMinutes = $timeStart->diffInMinutes($timeEnd);

        // Convertir los minutos totales a horas y minutos restantes
        $hours = intdiv($totalMinutes, 60); // Obtener las horas completas
        $minutes = $totalMinutes % 60; // Obtener los minutos restantes

        // Construir la cadena con la diferencia de tiempo
        $timeDifference = '';

        if ($hours > 0) {
            $timeDifference .= $hours . 'h '; // Agrega las horas si existen
        }

        if ($minutes > 0) {
            $timeDifference .= $minutes . 'min'; // Agrega los minutos si existen
        }

        return $timeDifference;
    }

    public function limpiaFechas($fechaOriginal)
    {
        $fechaLimpiada = preg_replace('/\s*\([^)]*\)/', '', $fechaOriginal);
        return $fechaLimpiada;
    }
}
