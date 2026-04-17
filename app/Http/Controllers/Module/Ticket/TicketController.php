<?php

namespace App\Http\Controllers\Module\Ticket;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Ticket;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Http\HelpersModule\module\ticket\TicketsDatatableHelper;
use App\Http\Requests\module\ticket\TicketCreateRequest;
use App\Http\Requests\module\ticket\TicketUpdateRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use App\Jobs\SendTicketNotificationJob;
use App\Notifications\TicketOpen;

class TicketController extends Controller
{

    private $helper;

    public function __construct(TicketsDatatableHelper $helper)
    {
        $model = 'Ticket';
        $this->data['url'] = 'meganet.module.tickets';
        $this->data['module'] = 'Ticket';
        $this->data['model'] = 'App\Models\\' . $model;
        $this->data['group'] = 'tickets';
        $this->data['fields'] = config('constants.model.' . $model . '.FIELDS');
        $this->data['columns'] = ['data' => config('constants.model.' . $model . '.DATATABLE_FIELDS')];
        $this->helper = $helper;
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function ver($id)
    {
        $this->data['notifications'] = $this->userNotification();
        $this->includeLibraryDinamic($this->data['model']);
        $this->data['id'] = $id;

        return view($this->data['url'] . '.ver', $this->data);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function opened($clientId = null)
    {
        $this->data['notifications'] = $this->userNotification();
        $this->includeLibraryDinamic($this->data['model']);
        $filters[] = ["estado" => 'Nuevo'];
        if ($clientId) {
            $filters[] = ["customer_lead" => $clientId];
        }

        $this->data['filters'] = json_encode($filters);
        return view($this->data['url'] . '.opened', $this->data);
    }

    public function closed($clientId = null)
    {
        $this->data['notifications'] = $this->userNotification();
        $this->includeLibraryDinamic($this->data['model']);
        $filters[] = ["estado" => 'Cerrado'];
        if ($clientId) {
            $filters[] = ["customer_lead" => $clientId];
        }

        $this->data['filters'] = json_encode($filters);
        return view($this->data['url'] . '.closed', $this->data);
    }

    public function trash()
    {
        $this->data['notifications'] = $this->userNotification();
        $this->includeLibraryDinamic($this->data['model']);
        return view($this->data['url'] . '.trash', $this->data);
    }

    public function setStatusTicketById($id, Request $request)
    {
        $ticket = $this->data['model']::find($id);
        return $ticket->update(['estado' => $request->statusTicket]);
    }

    public function notificationsReadMarked($notificationId)
    {
        $user = User::find(Auth::user()->id);
        $idTiket = $user->notifications->where('id', $notificationId)->first()->data[0]['id'];
        foreach ($user->unreadNotifications->where('id', $notificationId) as $notification) {
            $notification->markAsRead();
        }
        return redirect('/tickets/ver/' . $idTiket);
    }

    public function getUserDataTicketById($id)
    {
        $ticket = $this->data['model']::find($id);
        $clientId = $ticket->customer_lead;
        $clientData = Client::where('id', $clientId)->with('client_main_information')->first();
        if ($clientData) return [
            'name' => $clientData->client_main_information->client_name_with_fathers_names,
            'email' => $clientData->client_main_information->email,
            'phone' => $clientData->client_main_information->phone,
            'id' => $clientData->id,
        ];
        return [];
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create($clientId = null)
    {
        $this->data['clientId'] = $clientId;
        $this->includeLibraryDinamic($this->data['model']);
        return view($this->data['url'] . '.add', $this->data);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(TicketCreateRequest $request)
    {
        $this->validateFieldByRulesInTableFiledModules($this->data['module'],$request);
        $attachments = collect($request)->filter(function ($value, $key) {
            return Str::contains($key, ['attachments']);
        });
        $request = collect($request)->filter(function ($value, $key) {
            return !Str::contains($key, ['attachments']);
        });

        $input = defined($this->data['model'] . '::MULTIPLE_RELATIONS') ?
            $request->except(collect($this->data['model']::MULTIPLE_RELATIONS)->keys()->toArray()) :
            $request->all();

        if (isset($input['hidden'])) $input['hidden'] = ($input['hidden'] == 'true');

        $reporter = $this->userAutenticated();
        $rol = $this->userAutenticated()->getRoleNames()->toArray();

        $input['customer_lead'] = $input['customer_lead'] ?? 0;
        $input['reporter_id'] = $reporter->id;
        $input['reporter'] = $reporter->name;
        $input['reporter_type'] = implode(", ", $rol);
        $input['date_time'] = Carbon::parse($input['date_time'])->format('Y-m-d\TH:i');



        $imputTicketThread =
            [
                'edited_by' => $reporter->id,
                'client_id' => $input['customer_lead'],
                'message' => $input['message'] ?? NULL,
                'hidden' => false,
            ];

        unset($input['message']);


        $model = $this->data['model']::create($input)
            ->uploadManyFiles($attachments)
            ->ticketCreateTicketThread($imputTicketThread);

        $this->saveRelationMultipleIfExist($this->data['model'], $model, $request);

        //TODO revisar arriba si esta dando error
        SendTicketNotificationJob::dispatchAfterResponse($model, $model->ticket_thread()->first());
        return ['id' => $model->id];
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    public function success($id)
    {
        return redirect('/tickets/ver/' . $id);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $this->includeLibraryDinamic($this->data['model']);

        $model = $this->data['model']::findOrFail($id);
        $this->data['fields'] = collect(
            $this->includeFields($this->data['model'], collect($this->data['fields'])->toArray(), $model)
        )->toJson();
        $this->data['id'] = $id;

        return view($this->data['url'] . '.edit', $this->data);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function update(TicketUpdateRequest $request, $id)
    {
        $this->validateFieldByRulesInTableFiledModules($this->data['module'],$request);
        $attachments = collect($request)->filter(function ($value, $key) {
            return Str::contains($key, ['attachments']);
        });
        $request = collect($request)->filter(function ($value, $key) {
            return !Str::contains($key, ['attachments']);
        });

        $model = $this->data['model']::find($id);
        $input = defined($this->data['model'] . '::MULTIPLE_RELATIONS') ?
            $request->except(collect($this->data['model']::MULTIPLE_RELATIONS)->keys()->toArray()) :
            $request->all();
        $this->saveRelationMultipleIfExist($this->data['model'], $model, $request, 'sync');

        $asigned = $model->assigned_to;
        $user = User::find($asigned);
        if ($user) $user->notify(new TicketOpen($model, $model->ticket_thread));

        return $model->update($input);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $this->data['model']::findOrFail($id)->delete();
        return redirect()->back()->with('message', $this->data['module'] . ' Eliminado Correctamente');
    }

    public function table(Request $request)
    {
        return $this->helper->fetch_datatable_data($request, $this->data['model']);
    }

    public function getTicketById($id)
    {
        return Ticket::find($id);
    }

    public function getTimeLapsed($id)
    {
        $created = Ticket::find($id);
        $time = Carbon::parse($created->created_at)->diffForHumans();
        return ['time' => $time];
    }

    public function getDataClient($clientId)
    {
        return Client::findOrFail($clientId)->load('client_main_information');
    }
}
