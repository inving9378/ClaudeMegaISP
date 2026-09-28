<?php

namespace App\Modules\Addons\Talento\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Seller;
use App\Models\User;
use App\Modules\Addons\Talento\Models\TalentoColaborador;
use App\Modules\Addons\Talento\Models\TalentoRoleDepartment;
use App\Modules\Addons\Talento\Support\Actor;
use Illuminate\Http\Request;

class TalentoColaboradorController extends Controller
{
    /**
     * David (28-sep): quien NO tiene visibilidad de roster completo
     * (talento.employees.view — hoy solo admin/DESARROLLADOR) no debería
     * aterrizar en el listado de "Colaboradores" al entrar a Talento — va
     * derecho a SU PROPIA ficha (las pestañas que le aplican a él). El
     * listado completo queda solo para quien de verdad necesita ver/buscar
     * a todos.
     */
    public function index()
    {
        if (! auth()->user()->can('talento.employees.view')) {
            $miPropioColaborador = Actor::for(auth()->user())->talento();
            if ($miPropioColaborador) {
                return redirect("/talento/colaborador/{$miPropioColaborador->id}");
            }
        }

        return view('addon-talento::talento.index');
    }

    /**
     * Ficha por pestañas de UN colaborador (reestructuración a como está
     * Vendedores: dashboard + ficha — David, 2026-09-28). Las flags de
     * permiso del VIEWER se resuelven aquí server-side (mismo criterio que
     * @can en el resto del proyecto) y se pasan a Vue ya calculadas, en vez
     * de depender de un store de permisos en el cliente solo para decidir
     * qué pestaña mostrar.
     */
    public function ficha($id)
    {
        $this->authorize('talento.view');

        // "Supervisor", tal como lo definió David (28-sep): NO es un rol de
        // Spatie — es la relación 1:1 talento_colaboradores.supervisor_id que
        // ya existe y se asigna al crear/editar un colaborador. El supervisor
        // DIRECTO de este colaborador puede crear/gestionar SUS órdenes,
        // aunque no tenga el permiso general talento.work_orders.manage.
        $miPropioColaborador = Actor::for(auth()->user())->talento();
        $colaboradorDeLaFicha = TalentoColaborador::find($id);
        $esSuSupervisor = $miPropioColaborador
            && $colaboradorDeLaFicha
            && (int) $colaboradorDeLaFicha->supervisor_id === (int) $miPropioColaborador->id;

        $permisos = [
            'ordenes'         => auth()->user()->can('talento.work_orders.view'),
            // Distinción ver-lo-mío vs gestionar-de-todos (David, 28-sep):
            // un técnico viendo SU PROPIA ficha ya tiene sus órdenes dentro de
            // "Mi trabajo (Portal)" — esta pestaña admin (con crear/validar)
            // solo se le muestra si además gestiona órdenes O es su supervisor
            // directo.
            'ordenes_manage'  => auth()->user()->can('talento.work_orders.manage') || $esSuSupervisor,
            'compensacion'    => auth()->user()->can('talento.compensation.view'),
            'liquidaciones'   => auth()->user()->can('talento.liquidation.view'),
            'asistencia'      => auth()->user()->can('talento.attendance.view'),
            'campo'           => auth()->user()->can('talento.work_orders.view'),
            'cajas'           => auth()->user()->can('talento.caja.view'),
            'rutas'           => auth()->user()->can('talento.routes.view'),
            'calidad'         => auth()->user()->can('talento.quality.view'),
            'proyectos'       => auth()->user()->can('talento.projects.view'),
            'penalizaciones'  => auth()->user()->can('talento.penalties.view'),
            'credenciales'    => auth()->user()->can('talento.credentials.view'),
            'finiquito'       => auth()->user()->can('talento.loans.view'),
            'custodia'        => auth()->user()->can('talento.custody.view'),
            'dispositivos'    => auth()->user()->can('talento.devices.view'),
            'roles_multiples' => auth()->user()->can('talento.embajadores.view'),
        ];

        // ¿Es la ficha de uno mismo? Un colaborador SIN talento.employees.view
        // (roster completo — staff, no técnicos) igual necesita poder abrir SU
        // PROPIA ficha, su supervisor directo necesita poder abrir la de SU
        // subordinado, y quien gestiona órdenes en general (admin/DESARROLLADOR
        // o, desde el 28-sep, el rol Mostrador — David: "Diana puede crear
        // órdenes/flujo para un técnico") necesita poder abrir la de
        // CUALQUIERA sin que eso implique verle el roster completo. Ninguno de
        // los tres casos requiere talento.employees.view.
        // /talento/api/colaboradores/{id} SÍ exige esa permission a nivel de
        // ruta (check_route_permission), así que el frontend usa un endpoint
        // aparte (miFicha(), por Actor) cuando alguno de los tres aplica.
        // ($miPropioColaborador y $esSuSupervisor ya se resolvieron arriba.)
        $esPropia = $miPropioColaborador && (string) $miPropioColaborador->id === (string) $id;
        $usarEndpointPropio = $esPropia || $esSuSupervisor || auth()->user()->can('talento.work_orders.manage');

        // "Mi trabajo (Portal)" — SOLO para uno mismo, JAMÁS para el
        // supervisor viendo a su subordinado: el Portal (completar OT,
        // evidencia, firma) es autoservicio self-scoped por Actor — que el
        // supervisor actuara ahí "por" su subordinado equivaldría a
        // falsificar su firma/evidencia. Se reusa el Portal TAL CUAL (iframe)
        // en vez de duplicar su lógica — ver CLAUDE.md "SERVICIOS COMPARTIDOS
        // ÚNICOS".
        $mostrarPortal = $esPropia && auth()->user()->can('portal.colaborador');

        return view('addon-talento::talento.ficha', [
            'id' => $id,
            'permisos' => $permisos,
            'esPropia' => $usarEndpointPropio,
            'mostrarPortal' => $mostrarPortal,
        ]);
    }

    /**
     * Ficha propia, de un subordinado directo, o de CUALQUIERA si quien pide
     * gestiona órdenes en general (talento.work_orders.manage — admin/
     * DESARROLLADOR/Mostrador) — ninguno de los tres exige
     * talento.employees.view (esa es visibilidad de ROSTER completo, un
     * permiso más amplio que "puedo crear/ver órdenes de este colaborador").
     * Mismo shape de respuesta que show(), para que el frontend
     * (TalentoColaboradorFicha.vue) pueda usar cualquiera de los dos
     * endpoints según lo que decidió ficha().
     */
    public function miFicha(?string $id = null)
    {
        $miPropioColaborador = Actor::for(auth()->user())->talento();

        if ($id !== null && $miPropioColaborador === null && auth()->user()->can('talento.work_orders.manage')) {
            // Quien gestiona órdenes en general (ej. Mostrador) no
            // necesariamente tiene su propio registro de colaborador —
            // Diana no es "un talento", pero sí puede crearle órdenes a uno.
            $colaborador = TalentoColaborador::find($id);
            abort_if(! $colaborador, 404);
        } elseif ($id === null || (string) $id === (string) ($miPropioColaborador->id ?? null)) {
            abort_if(! $miPropioColaborador, 404, 'No tienes un perfil de colaborador activo.');
            $colaborador = $miPropioColaborador;
        } else {
            $colaborador = $miPropioColaborador->subordinados()->where('id', $id)->first()
                ?? (auth()->user()->can('talento.work_orders.manage') ? TalentoColaborador::find($id) : null);
            abort_if(! $colaborador, 403, 'No tienes permiso para ver a este colaborador.');
        }

        $colaborador->load(['user', 'supervisor.user', 'subordinados.user', 'puesto']);

        if ($colaborador->user) {
            $colaborador->user->role_names = $colaborador->user->getRoleNames();
        }

        $this->hideExpedienteFields($colaborador);

        return response()->json($colaborador);
    }

    public function data(Request $request)
    {
        $this->authorize('talento.view');

        $q = TalentoColaborador::with(['user', 'supervisor.user', 'puesto'])
            ->when($request->search, fn($q, $s) =>
                $q->whereHas('user', fn($u) => $u->where('name', 'like', "%$s%")->orWhere('email', 'like', "%$s%"))
            )
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->when($request->type,   fn($q, $t) => $q->where('type', $t))
            ->when($request->department, fn($q, $d) => $q->where('department', $d))
            ->orderBy('id', 'desc')
            ->paginate($request->per_page ?? 25);

        // Enrich each user with their Spatie roles (read-only) for the list
        $q->each(function ($col) {
            if ($col->user) {
                $col->user->role_names = $col->user->getRoleNames();
            }
        });

        $this->hideExpedienteFields($q->getCollection());

        return response()->json($q);
    }

    public function store(Request $request)
    {
        $this->authorize('talento.manage');

        $data = $request->validate([
            'user_id'       => 'required|exists:users,id|unique:talento_colaboradores,user_id',
            'type'          => 'required|in:interno,externo',
            'department'    => 'nullable|string|max:100',
            'supervisor_id' => 'nullable|exists:talento_colaboradores,id',
            'hire_date'     => 'nullable|date',
            'status'        => 'required|in:active,inactive,suspended',
            'base_salary'   => 'nullable|numeric|min:0',
            'notes'         => 'nullable|string',
            ...$this->expedienteValidationRules(),
        ]);

        $colaborador = TalentoColaborador::create($data);

        return response()->json($colaborador->load('user'), 201);
    }

    public function show($id)
    {
        $this->authorize('talento.view');

        $colaborador = TalentoColaborador::with(['user', 'supervisor.user', 'subordinados.user', 'puesto'])
            ->findOrFail($id);

        // Enrich with Spatie roles (read-only display — roles are managed in Administradores)
        if ($colaborador->user) {
            $colaborador->user->role_names = $colaborador->user->getRoleNames();
        }

        $this->hideExpedienteFields($colaborador);

        return response()->json($colaborador);
    }

    public function update(Request $request, $id)
    {
        $this->authorize('talento.manage');

        $colaborador = TalentoColaborador::findOrFail($id);

        $data = $request->validate([
            'type'          => 'sometimes|in:interno,externo',
            'department'    => 'nullable|string|max:100',
            'supervisor_id' => 'nullable|exists:talento_colaboradores,id',
            'hire_date'     => 'nullable|date',
            'status'        => 'sometimes|in:active,inactive,suspended',
            'base_salary'   => 'nullable|numeric|min:0',
            'notes'         => 'nullable|string',
            ...$this->expedienteValidationRules(),
        ]);

        // Prevent self-referencing supervisor
        if (isset($data['supervisor_id']) && $data['supervisor_id'] == $colaborador->id) {
            return response()->json(['error' => 'Un colaborador no puede ser su propio supervisor.'], 422);
        }

        $colaborador->update($data);

        return response()->json($colaborador->load('user', 'supervisor.user'));
    }

    public function destroy($id)
    {
        $this->authorize('talento.manage');

        $colaborador = TalentoColaborador::findOrFail($id);
        $colaborador->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * Reglas de validación de los campos del expediente RH (item #199 — Hijo A).
     */
    private function expedienteValidationRules(): array
    {
        return [
            'birth_date'              => 'nullable|date',
            'curp'                    => 'nullable|string|max:18',
            'nss'                     => 'nullable|string|max:11',
            'emergency_contact_name'  => 'nullable|string|max:150',
            'emergency_contact_phone' => 'nullable|string|max:20',
            'job_title'               => 'nullable|string|max:100',
            'puesto_id'               => 'nullable|exists:talento_puestos,id',
            'relation_type'           => 'nullable|in:indeterminada,determinada,obra',
            'relation_end_date'       => 'nullable|date',
            'pay_frequency'           => 'nullable|in:semanal,quincenal,mensual',
            'work_location'           => 'nullable|string|max:150',
            'shift_start'             => 'nullable|date_format:H:i',
            'shift_end'               => 'nullable|date_format:H:i',
            'work_days'               => 'nullable|string|max:100',
        ];
    }

    /**
     * RFC y domicilio completo (item #199) viven en `users`, no en `talento_colaboradores` —
     * se ocultan aparte porque el `user` cargado por relación no pasa por EXPEDIENTE_FIELDS.
     */
    private const USER_EXPEDIENTE_FIELDS = [
        'rfc', 'address', 'city_municipality', 'state_country', 'code_postal', 'colony',
    ];

    /**
     * CURP, RFC, NSS, salario y domicilio son datos personales sensibles (item #199): ocultarlos
     * de la respuesta a quien no tenga 'talento.expediente.view', permiso propio y distinto del
     * de ver la ficha normal ('talento.view'). Acepta un Model o una Collection de Eloquent.
     */
    private function hideExpedienteFields($items): void
    {
        if (auth()->user()?->can('talento.expediente.view')) {
            return;
        }

        $items->makeHidden(TalentoColaborador::EXPEDIENTE_FIELDS);

        if ($items instanceof \Illuminate\Support\Collection) {
            $items->each(fn ($item) => $item->user?->makeHidden(self::USER_EXPEDIENTE_FIELDS));
        } else {
            $items->user?->makeHidden(self::USER_EXPEDIENTE_FIELDS);
        }
    }

    /**
     * Item roadmap #9990360 (Hijo E3 de #203). Resuelve el `talento_colaboradores.id` de un
     * Vendedor para el card "Documentos" de su ficha (InformationSeller.vue); si el vendedor
     * no tiene expediente RH todavía (caso vendedor externo), lo crea "ligero" al vuelo
     * (decisión ya tomada por Irving en #203, q3). Idempotente vía el UNIQUE existente en
     * `talento_colaboradores.user_id` — llamadas repetidas devuelven siempre la misma fila.
     */
    public function expedienteVendedor($sellerId)
    {
        $this->authorize('talento.expediente.documentos.ver');

        $seller = Seller::findOrFail($sellerId);

        $colaborador = TalentoColaborador::firstOrCreate(
            ['user_id' => $seller->user_id],
            ['type' => 'externo', 'categoria_externo' => 'vendedor_externo']
        );

        return response()->json(['id' => $colaborador->id]);
    }

    public function roleDepartments()
    {
        $this->authorize('talento.manage');

        return response()->json(
            TalentoRoleDepartment::pluck('department', 'role_name')
        );
    }

    public function usersDisponibles(Request $request)
    {
        $this->authorize('talento.manage');

        $existingUserIds = TalentoColaborador::pluck('user_id');

        $users = User::whereNotIn('id', $existingUserIds)
            ->when($request->search, fn($q, $s) =>
                $q->where('name', 'like', "%$s%")->orWhere('email', 'like', "%$s%")
            )
            ->with('roles:name')
            ->select('id', 'name', 'email')
            ->limit(30)
            ->get()
            ->map(fn($u) => array_merge(
                $u->only('id', 'name', 'email'),
                ['role_names' => $u->roles->pluck('name')->values()->all()]
            ));

        return response()->json($users);
    }
}
