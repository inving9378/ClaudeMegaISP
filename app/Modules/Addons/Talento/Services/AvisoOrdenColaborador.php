<?php

namespace App\Modules\Addons\Talento\Services;

use App\Models\Task;
use App\Models\User;
use App\Modules\Addons\Hub\Services\Servicios;
use App\Modules\Addons\Talento\Controllers\TalentoMobileApiController;
use App\Modules\Addons\WhatsAppAgent\Services\WhatsAppGateway;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Avisos al colaborador sobre SUS órdenes de trabajo: push a la app, campanita del sistema y
 * WhatsApp (gateway único de WhatsAppAgent, con su freno maestro). Antes vivía solo dentro de
 * TalentoWorkOrderController::store(); el item #1225 (editar/eliminar orden pendiente) necesita
 * los mismos tres canales para «se te asignó», «ya no la tienes» y «se eliminó».
 *
 * Cada canal está aislado: un fallo de campanita o WhatsApp no tumba la operación que avisa.
 */
class AvisoOrdenColaborador
{
    /** Títulos de la campanita cuando la orden ya no es de quien recibe el aviso (abrirAviso los reconoce). */
    public const TITULO_RETIRADA  = 'Orden de trabajo retirada';
    public const TITULO_ELIMINADA = 'Orden de trabajo eliminada';

    public function __construct(private WhatsAppGateway $whatsapp) {}

    /** Orden nueva o reasignada: mismo aviso (y mismos textos) que al crearla. */
    public function asignada(Task $task, User $usuario, string $tipoNombre, ?Carbon $inicio, string $contexto = 'al crear orden'): void
    {
        $cuando = self::cuando($inicio);

        TalentoMobileApiController::sendPushToUser(
            $usuario->id,
            'Nueva orden de trabajo',
            "Se te asignó: {$tipoNombre}{$cuando}",
            ['type' => 'ot_assigned', 'ot_id' => $task->id]
        );

        // Campanita del topbar / Portal de Colaborador: el mismo mecanismo de Scheduling para
        // "tarea asignada" (Task::sendNotifications → task_notifications + StandardNotification).
        try {
            $task->sendNotifications(null, collect([$usuario]));
        } catch (\Throwable $e) {
            Log::warning("Talento OT: fallo notificación en-app {$contexto}", [
                'task_id' => $task->id, 'error' => $e->getMessage(),
            ]);
        }

        $this->whatsApp($usuario, "Hola {$usuario->name}, se te asignó una nueva orden de trabajo: "
            . "{$tipoNombre}{$cuando}. Consulta el detalle en «Mi día» de Talento o en la app.", $task->id, $contexto);
    }

    /** La orden pasó a otra persona: se le avisa a quien la tenía. */
    public function retirada(Task $task, User $usuario, string $tipoNombre, ?Carbon $inicio): void
    {
        $cuando = self::cuando($inicio);

        TalentoMobileApiController::sendPushToUser(
            $usuario->id,
            self::TITULO_RETIRADA,
            "Ya no tienes asignada: {$tipoNombre}{$cuando}",
            ['type' => 'ot_unassigned', 'ot_id' => $task->id]
        );
        $this->campanita($task, $usuario, self::TITULO_RETIRADA, 'al retirar orden');
        $this->whatsApp($usuario, "Hola {$usuario->name}, la orden de trabajo {$tipoNombre}{$cuando} "
            . 'se asignó a otra persona; ya no está en tu «Mi día».', $task->id, 'al retirar orden');
    }

    /** La orden se eliminó antes de iniciarse: se le avisa a quien la tenía. */
    public function eliminada(Task $task, User $usuario, string $tipoNombre, ?Carbon $inicio): void
    {
        $cuando = self::cuando($inicio);

        TalentoMobileApiController::sendPushToUser(
            $usuario->id,
            self::TITULO_ELIMINADA,
            "Se eliminó: {$tipoNombre}{$cuando}",
            ['type' => 'ot_deleted', 'ot_id' => $task->id]
        );
        $this->campanita($task, $usuario, self::TITULO_ELIMINADA, 'al eliminar orden');
        $this->whatsApp($usuario, "Hola {$usuario->name}, la orden de trabajo {$tipoNombre}{$cuando} "
            . 'se eliminó; ya no está en tu «Mi día».', $task->id, 'al eliminar orden');
    }

    /** #1286: el supervisor pidió fotos de corrección en una orden ya terminada. */
    public function correccionPedida(Task $task, User $usuario, string $tipoNombre, string $motivo): void
    {
        TalentoMobileApiController::sendPushToUser(
            $usuario->id,
            'Corrección pedida en una orden',
            "Orden #{$task->id} ({$tipoNombre}): {$motivo}",
            ['type' => 'ot_correccion', 'ot_id' => $task->id]
        );
        $this->campanita($task, $usuario, 'Corrección pedida en una orden', 'al pedir corrección');
        $this->whatsApp($usuario, "Hola {$usuario->name}, tu supervisor pidió una corrección en la orden #{$task->id} "
            . "({$tipoNombre}): {$motivo}. Abre la orden en la app, sube las fotos de la corrección y envíala.", $task->id, 'al pedir corrección');
    }

    /** #1286: el supervisor revisó la orden terminada y se la devolvió con una nota. */
    public function devuelta(Task $task, User $usuario, string $tipoNombre, string $motivo): void
    {
        TalentoMobileApiController::sendPushToUser(
            $usuario->id,
            'Tu supervisor te devolvió una orden',
            "Orden #{$task->id} ({$tipoNombre}): {$motivo}",
            ['type' => 'ot_devuelta', 'ot_id' => $task->id]
        );
        $this->campanita($task, $usuario, 'Tu supervisor te devolvió una orden', 'al devolver orden');
        $this->whatsApp($usuario, "Hola {$usuario->name}, tu supervisor te devolvió la orden #{$task->id} "
            . "({$tipoNombre}): {$motivo}. Está otra vez en tu «Mi día»: corrígela y vuelve a completarla.", $task->id, 'al devolver orden');
    }

    /** " para el d/m" o " para el d/m H:i" (sin hora la orden queda a las 00:00: el aviso dice solo el día). */
    public static function cuando(?Carbon $inicio): string
    {
        return $inicio
            ? ' para el ' . $inicio->format($inicio->format('H:i') === '00:00' ? 'd/m' : 'd/m H:i')
            : '';
    }

    /**
     * MX 10 dígitos → prefijo 521 (mismo formato que espera whatsapp_conversations.contact_number —
     * ver SendReferralShareListener::normalizeNumber, WhatsAppAgent). null si no hay teléfono o no
     * es un patrón MX reconocible (mejor no mandar que mandar a un número mal formado).
     */
    public static function normalizeMxWhatsappNumber(?string $phone): ?string
    {
        if (! $phone) {
            return null;
        }
        $digits = preg_replace('/\D/', '', $phone) ?? '';
        if (strlen($digits) === 10) {
            return '521' . $digits;
        }
        if (strlen($digits) === 12 && str_starts_with($digits, '52')) {
            return '521' . substr($digits, 2);
        }
        if (strlen($digits) === 13 && str_starts_with($digits, '521')) {
            return $digits;
        }
        return null;
    }

    private function campanita(Task $task, User $usuario, string $titulo, string $contexto): void
    {
        try {
            $task->sendNotifications(null, collect([$usuario]), $titulo);
        } catch (\Throwable $e) {
            Log::warning("Talento OT: fallo notificación en-app {$contexto}", [
                'task_id' => $task->id, 'error' => $e->getMessage(),
            ]);
        }
    }

    private function whatsApp(User $usuario, string $texto, int $taskId, string $contexto): void
    {
        $phone = self::normalizeMxWhatsappNumber($usuario->phone ?? null);
        if (! $phone) {
            return;
        }
        try {
            $this->whatsapp->sendText(Servicios::slugWhatsApp('talento.ot_asignada'), $phone, $texto);
        } catch (\Throwable $e) {
            Log::warning("Talento OT: fallo notificación WhatsApp {$contexto}", [
                'task_id' => $taskId, 'error' => $e->getMessage(),
            ]);
        }
    }
}
