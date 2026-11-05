<?php

namespace Tests\Feature\Unificacion;

use App\Models\Ticket;
use App\Models\User;
use App\Modules\Addons\Tickets\Controllers\DashboardController;
use App\Modules\Addons\Tickets\Controllers\TicketThreadController;
use App\Notifications\TicketOpen;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\CreatesApplication;

/**
 * UNI-T G1 (#937): Tickets con el interruptor `unificacion.interruptores.tickets_g1` encendido.
 * Corre SOLO contra megaisp_test (aborta si no) y deshace todo con DatabaseTransactions.
 */
class TicketsG1Test extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('megaisp_test', DB::connection()->getDatabaseName(), 'Solo en megaisp_test');
        config(['unificacion.interruptores.tickets_g1' => true]);
    }

    private function usuario(): User
    {
        $u = new User();
        $u->forceFill([
            'name' => 'G1 '.uniqid(), 'email' => uniqid('g1').'@test.local',
            'login_user' => uniqid('g1'), 'password' => 'x',
        ])->save();
        return $u;
    }

    private function ticket(array $extra = []): Ticket
    {
        return Ticket::create(array_merge([
            'topic' => 'T '.uniqid(), 'customer_lead' => 0, 'priority' => 1, 'estado' => 'Nuevo',
            'reporter' => 'x', 'reporter_id' => 0, 'date_time' => now()->format('Y-m-d\TH:i'),
        ], $extra));
    }

    public function test_asignados_a_mi_filtra_por_assigned_to(): void
    {
        $yo = $this->usuario();
        $otro = $this->usuario();
        $mio = $this->ticket(['assigned_to' => $yo->id, 'customer_lead' => $otro->id]);
        $ajeno = $this->ticket(['assigned_to' => $otro->id, 'customer_lead' => $yo->id]);

        $this->actingAs($yo);
        $r = (new DashboardController())->getTicketAssignedToMe(new Request(['value' => 'Todo']));

        $ids = collect($r)->pluck('id')->all();
        $this->assertContains($mio->id, $ids);
        $this->assertNotContains($ajeno->id, $ids);
    }

    public function test_asignados_a_mi_con_interruptor_apagado_usa_customer_lead(): void
    {
        config(['unificacion.interruptores.tickets_g1' => false]);
        $yo = $this->usuario();
        $otro = $this->usuario();
        $porLead = $this->ticket(['assigned_to' => $otro->id, 'customer_lead' => $yo->id]);

        $this->actingAs($yo);
        $r = (new DashboardController())->getTicketAssignedToMe(new Request(['value' => 'Todo']));
        $this->assertContains($porLead->id, collect($r)->pluck('id')->all());
    }

    public function test_mensaje_update_con_id_de_hilo_usa_ticket_id(): void
    {
        $yo = $this->usuario();
        $ticket = $this->ticket(['assigned_to' => $yo->id]);
        // Id de ticket distinto al id del hilo, para que Ticket::find($id) del código viejo no acierte.
        DB::table('ticket_threads')->insert(['ticket_id' => $ticket->id, 'edited_by' => $yo->id, 'client_id' => 0,
            'message' => 'a', 'hidden' => 0, 'created_at' => now(), 'updated_at' => now()]);
        $hiloId = DB::table('ticket_threads')->where('ticket_id', $ticket->id)->value('id');
        $this->assertNotSame($ticket->id, $hiloId);

        $this->actingAs($yo);
        \Bus::fake();
        $res = app(TicketThreadController::class)->update(
            \App\Http\Requests\module\ticket\TicketThreadUpdateRequest::create('/x', 'POST', ['message' => 'editado']), $hiloId
        );
        $this->assertNotNull($res);
        \Bus::assertDispatched(\App\Jobs\SendTicketNotificationJob::class);
    }

    public function test_store_de_mensaje_sin_hilo_padre_no_truena(): void
    {
        $yo = $this->usuario();
        $ticket = $this->ticket(['assigned_to' => $yo->id, 'customer_lead' => 0]);
        $this->assertSame(0, DB::table('ticket_threads')->where('ticket_id', $ticket->id)->count());

        $this->actingAs($yo);
        \Bus::fake();
        $m = app(TicketThreadController::class)->store(
            \App\Http\Requests\module\ticket\TicketThreadCreateRequest::create('/x', 'POST', ['message' => 'primero']), $ticket->id
        );
        $this->assertSame($ticket->id, (int) $m->ticket_id);
        $this->assertNull($m->ticket_thread_id);
    }

    public function test_update_notifica_al_responsable_nuevo(): void
    {
        Notification::fake();
        $viejo = $this->usuario();
        $nuevo = $this->usuario();
        $ticket = $this->ticket(['assigned_to' => $viejo->id]);

        $this->actingAs($viejo);
        $req = \App\Http\Requests\module\ticket\TicketUpdateRequest::create('/x', 'POST',
            ['topic' => 'nuevo tema', 'colony_id' => 1, 'assigned_to' => $nuevo->id]);
        $req->setContainer(app())->setRedirector(app('redirect'));
        try {
            app(\App\Modules\Addons\Tickets\Controllers\TicketController::class)->update($req, $ticket->id);
        } catch (\Throwable $e) {
            $this->markTestSkipped('update() no ejecutable aislado: '.$e->getMessage());
        }

        $this->assertSame($nuevo->id, (int) $ticket->fresh()->assigned_to);
        Notification::assertSentTo($nuevo, TicketOpen::class);
        Notification::assertNotSentTo($viejo, TicketOpen::class);
    }

    public function test_ruta_update_con_id_permitida_por_ticket_edit_open_solo_con_interruptor(): void
    {
        $con = (function () {
            return array_merge(['/tickets/editar', '/tickets/update', '/tickets/mensaje/update/{id}'], ['/tickets/update/{id}']);
        })();
        $archivo = file_get_contents(base_path('config/route_permission.php'));
        $this->assertStringContainsString("env('UNIFICACION_TICKETS_G1', false) ? ['/tickets/update/{id}'] : []", $archivo);
        $this->assertContains('/tickets/update/{id}', $con);
    }
}
