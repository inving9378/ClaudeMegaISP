<?php

namespace App\Http\Repository;

use App\Models\Ticket;
use Illuminate\Support\Facades\DB;

class TicketRepository
{
    protected $client;
    protected $model;

    public function __construct()
    {
        $this->model = Ticket::query();
    }

    public function count()
    {
        return $this->model->count();
    }

    // GETTERS

    public function getNewTicketsByDateRangeAndStatus($startDate, $endDate, $estado)
    {
        return $this->model->whereBetween('created_at', [$startDate, $endDate])->filteredByStatus($estado)->count();
    }

    public function getAssignedTicketsByDateRangeAndStatus($startDate, $endDate, $estado)
    {
        $ticketAsigneds = Ticket::select(DB::raw('count(*) as count, assigned_to'))
            ->with('assign')
            ->where('estado', $estado)
            ->whereBetween('tickets.created_at', [$startDate, $endDate])
            ->groupBy('assigned_to')
            ->get();

        return $ticketAsigneds;
    }



    // SETTERS




}
