<?php

namespace App\Http\Controllers\Module\Ticket;

use App\Http\Controllers\Controller;
use App\Http\HelpersModule\module\ticket\TicketsThreadDatatableHelper;
use App\Http\Requests\module\ticket\TicketThreadCreateRequest;
use App\Http\Traits\Models\Ticket\TicketTrait;
use App\Jobs\SendTicketNotificationJob;
use App\Models\Ticket;
use App\Http\Requests\module\ticket\TicketThreadUpdateRequest;
use App\Models\TicketThread;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Models\User;
use App\Notifications\TicketOpen;


class TicketThreadController extends Controller
{
    use  TicketTrait;

    private $helper;

    public function __construct(TicketsThreadDatatableHelper $helper)
    {
        $model = 'TicketThread';
        $this->data['url'] = 'meganet.module.tickets';
        $this->data['module'] = 'tickets';
        $this->data['model'] = 'App\Models\\' . $model;
        $this->data['group'] = 'tickets';
        $this->data['fields'] = config('constants.model.' . $model . '.FIELDS');
        $this->data['columns'] = ['data' => config('constants.model.' . $model . '.DATATABLE_FIELDS')];
        $this->helper = $helper;
    }


    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function update(TicketThreadUpdateRequest $request, $id)
    {
       // $this->validateFieldByRulesInTableFiledModules($this->data['module'],$request);
        $model = $this->data['model']::find($id);
        $model->updateFileUpload($request->file('file'));

        $input = defined($this->data['model'] . '::MULTIPLE_RELATIONS') ?
            $request->except(collect($this->data['model']::MULTIPLE_RELATIONS)->keys()->toArray()) :
            $request->all();
        $this->saveRelationMultipleIfExist($this->data['model'], $model, $request, 'sync');

        $ticket = Ticket::find($id);
        SendTicketNotificationJob::dispatchAfterResponse($ticket, $model);

        return $model->update($input);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(TicketThreadCreateRequest $request, $id)
    {
      //  $this->validateFieldByRulesInTableFiledModules($this->data['module'],$request);
        $file = $request->file;
        $request = collect($request->except(['file']));

        $input = defined($this->data['model'] . '::MULTIPLE_RELATIONS') ?
            $request->except(collect($this->data['model']::MULTIPLE_RELATIONS)->keys()->toArray()) :
            $request->all();

        $ticketThreadParent = TicketThread::where('ticket_id', $id)->whereNull('ticket_thread_id')->first();
        $reporter = $this->userAutenticated();
        $input['edited_by'] = $reporter->id;
        $input['ticket_id'] = $id;
        $input['ticket_thread_id'] = $ticketThreadParent->id;
        $input['client_id'] = Ticket::find($id)->client_id;

        $model = $this->data['model']::create($input)
            ->uploadFile($file);
        $this->saveRelationMultipleIfExist($this->data['model'], $model, $request);

        $ticket = Ticket::find($id);
        SendTicketNotificationJob::dispatchAfterResponse($ticket, $model);
        return $model;
    }

    public function getTicketThreadById($id)
    {
        $ticket = Ticket::find($id);
        return $ticket->ticket_thread()->get();
    }

    public function getParentTicketById($id)
    {
        $ticket = Ticket::find($id);
        return $ticket->ticket_thread()->with('ticket.files')->whereNull('ticket_thread_id')->first();
    }

    public function getChildTicketById($id)
    {
        $ticket = Ticket::find($id);
        return $ticket->ticket_thread()->with('file')->whereNotNull('ticket_thread_id')->get();
    }

}
