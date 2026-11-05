<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * "Asignado a" en dos pasos, rol y después una persona activa de ese rol (2026-10-22): tareas de
 * Agenda (nueva, editar, plantilla y la de la ficha del cliente) y tickets (nuevo y detalle). Solo
 * cambia el tipo del campo en el catálogo de formularios; los módulos se resuelven por nombre
 * (los ids no coinciden entre dev y prod). down() regresa cada campo a su tipo anterior.
 */
return new class extends Migration
{
    private const NUEVO = 'select-rol-usuario';

    // módulo => tipo que tenía
    private const CAMPOS = [
        'Task'          => 'select-group-team',
        'TaskRight'     => 'select-group-team',
        'TemplateTask'  => 'select-group-team',
        'Ticket'        => 'select-component',
        'TicketDetails' => 'select-component',
    ];

    public function up(): void
    {
        $tipo = $this->tipo(self::NUEVO, true);
        foreach (array_keys(self::CAMPOS) as $modulo) {
            $this->cambiar($modulo, $tipo);
        }
    }

    public function down(): void
    {
        foreach (self::CAMPOS as $modulo => $anterior) {
            $tipo = $this->tipo($anterior, false);
            if ($tipo) {
                $this->cambiar($modulo, $tipo);
            }
        }
    }

    private function tipo(string $nombre, bool $crear): ?int
    {
        $id = DB::table('field_types')->where('name', $nombre)->value('id');
        if (! $id && $crear) {
            $id = DB::table('field_types')->insertGetId(['name' => $nombre, 'created_at' => now(), 'updated_at' => now()]);
        }

        return $id ? (int) $id : null;
    }

    private function cambiar(string $modulo, int $tipo): void
    {
        $moduloId = DB::table('modules')->where('name', $modulo)->value('id');
        if (! $moduloId) {
            return;
        }
        DB::table('field_modules')
            ->where('module_id', $moduloId)
            ->where('name', 'assigned_to')
            ->update(['type' => (string) $tipo, 'updated_at' => now()]);
    }
};
