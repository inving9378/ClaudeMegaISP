<?php

namespace App\Modules\Addons\Talento\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Seller;
use App\Models\User;
use App\Modules\Addons\Talento\Models\TalentoColaborador;
use App\Modules\Addons\Talento\Models\TalentoRoleDepartment;
use App\Modules\Addons\Talento\Support\Actor;
// TalentoEmployeeDocumentController y TalentoAcademyController están en este
// MISMO namespace (Controllers) — sin use, se referencian por nombre corto.
use Illuminate\Http\Request;

class TalentoColaboradorController extends Controller
{
    /**
     * David (28-sep, corregido el mismo día): 3 comportamientos al entrar a
     * Talento, según quién es:
     *   1. talento.employees.view (admin/DESARROLLADOR/Mostrador) → listado
     *      COMPLETO, sin cambios.
     *   2. Supervisor (tiene subordinados directos vía
     *      talento_colaboradores.supervisor_id, sin el permiso de arriba) →
     *      MISMO listado, pero data() lo filtra solo a sus subordinados —
     *      "el supervisor debe poder controlar lo que hacen los
     *      trabajadores a su cargo".
     *   3. Cualquier otro colaborador con ficha propia → redirige derecho a
     *      ELLA (no tiene a nadie que listar).
     *   4. Sin nada de lo anterior (fallback raro, sin datos que mostrar de
     *      todos modos) → cae al listado.
     */
    public function index()
    {
        $soloMiEquipo = false;

        if (! auth()->user()->can('talento.employees.view')) {
            $miPropioColaborador = Actor::for(auth()->user())->talento();
            $esSupervisor = $miPropioColaborador && $miPropioColaborador->subordinados()->exists();

            if ($miPropioColaborador && ! $esSupervisor) {
                return redirect("/talento/colaborador/{$miPropioColaborador->id}");
            }
            // Si es supervisor, cae al listado, pero apuntado a /mi-equipo.
            $soloMiEquipo = $esSupervisor;
        }

        return view('addon-talento::talento.index', ['soloMiEquipo' => $soloMiEquipo]);
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
        $esPropia = $miPropioColaborador && (string) $miPropioColaborador->id === (string) $id;
        $usarEndpointPropio = $esPropia || $esSuSupervisor || auth()->user()->can('talento.work_orders.manage');

        // David (28-sep), corrigiendo la pasada anterior: "las que tienen que
        // ver con partes técnicas... que las vean los técnicos, LO DEMÁS LO
        // DEBE VER TODO EL MUNDO" — o sea, la ficha PROPIA de un colaborador
        // debe mostrarle SUS PROPIOS datos (compensación, liquidaciones,
        // asistencia, etc.) aunque el rol de ese colaborador (ej. TECNICO) no
        // tenga otorgado el permiso de STAFF correspondiente — esos permisos
        // son para que un admin vea a CUALQUIERA, no deberían ser requisito
        // para verse a uno mismo. Mismo criterio que ya se usa para decidir
        // el endpoint (arriba): uno mismo, su supervisor directo, o quien
        // gestiona en general.
        $tieneAccesoAmplio = $esPropia || $esSuSupervisor || auth()->user()->can('talento.employees.view');

        $permisos = [
            'ordenes'         => auth()->user()->can('talento.work_orders.view') || $tieneAccesoAmplio,
            // Distinción ver-lo-mío vs gestionar-de-todos (David, 28-sep):
            // un técnico viendo SU PROPIA ficha ya tiene sus órdenes dentro de
            // "Mi trabajo (Portal)" — esta pestaña admin (con crear/validar)
            // solo se le muestra si además gestiona órdenes O es su supervisor
            // directo. A propósito NO lleva $tieneAccesoAmplio (verse a uno
            // mismo no debe dar de gratis el botón "Nueva orden").
            'ordenes_manage'  => auth()->user()->can('talento.work_orders.manage') || $esSuSupervisor,
            'compensacion'    => auth()->user()->can('talento.compensation.view') || $tieneAccesoAmplio,
            // David (28-sep): "solo el superior o superiores deberían poder
            // asignarle reglas, no uno mismo" — mismo criterio que
            // ordenes_manage, a propósito SIN $tieneAccesoAmplio (verse a
            // uno mismo no da de gratis la capacidad de asignarse una regla).
            'compensacion_manage' => auth()->user()->can('talento.compensation.manage') || $esSuSupervisor,
            'liquidaciones'   => auth()->user()->can('talento.liquidation.view') || $tieneAccesoAmplio,
            'asistencia'      => auth()->user()->can('talento.attendance.view') || $tieneAccesoAmplio,
            // Las 4 "técnicas" también llevan $tieneAccesoAmplio — el filtro
            // real de "solo técnicos" ya lo impone esTecnico() en el
            // frontend (depende del colaborador VISTO, no de quién mira), no
            // hace falta duplicarlo aquí.
            'campo'           => auth()->user()->can('talento.work_orders.view') || $tieneAccesoAmplio,
            // David (30-sep), corrigiendo el criterio de ayer solo para
            // esto: la evidencia fotográfica la sube quien ESTUVO en el
            // sitio — el propio técnico, no su supervisor ("un supervisor
            // no debe tener que ir al lugar a comprobar que sea verdad").
            // A propósito CON $tieneAccesoAmplio (a diferencia de
            // ordenes_manage/rutas_manage): subir evidencia es
            // autoservicio, el anti-fraude real ya vive en
            // FieldMediaService (marca de agua + distancia GPS al
            // domicilio del cliente), no en excluir a quien la capturó.
            'campo_evidencia_manage' => auth()->user()->can('talento.work_orders.manage') || $tieneAccesoAmplio,
            'cajas'           => auth()->user()->can('talento.caja.view') || $tieneAccesoAmplio,
            'rutas'           => auth()->user()->can('talento.routes.view') || $tieneAccesoAmplio,
            // David (28-sep): "esa ruta debería hacerla el superior o
            // superiores" — mismo criterio que ordenes_manage, a propósito
            // SIN $tieneAccesoAmplio (verse a uno mismo no da de gratis la
            // capacidad de armarse su propia ruta).
            'rutas_manage'    => auth()->user()->can('talento.routes.manage') || $esSuSupervisor,
            'calidad'         => auth()->user()->can('talento.quality.view') || $tieneAccesoAmplio,
            // Validar una inspección es revisión INDEPENDIENTE — a
            // propósito SIN $tieneAccesoAmplio (quien inspeccionó no
            // valida su propio trabajo, mismo criterio que
            // esSupervisorDeInspeccion() en el controller).
            'calidad_validar' => auth()->user()->can('talento.quality.manage') || $esSuSupervisor,
            // Catálogo de estándares (crear/editar/imagen) es política de
            // empresa, no gestión de equipo — a propósito SOLO admin
            // (talento.quality.manage), sin excepción de supervisor.
            'calidad_estandares_manage' => auth()->user()->can('talento.quality.manage'),
            'proyectos'       => auth()->user()->can('talento.projects.view') || $tieneAccesoAmplio,
            // David (28-sep): "el proyecto lo crea un superior... quita el
            // botón Nuevo proyecto y déjalo solo para los superiores" — es
            // una acción GLOBAL (no hay un colaborador_id puntual al crear
            // un proyecto), así que el criterio es "ser supervisor de
            // alguien" (igual que compensacion.rules()/cajas settings), NO
            // $esSuSupervisor (que es respecto al colaborador de ESTA
            // ficha) ni $tieneAccesoAmplio (verse a uno mismo no da de
            // gratis el botón).
            'proyectos_manage' => auth()->user()->can('talento.projects.manage')
                || (bool) ($miPropioColaborador?->subordinados()->exists()),
            'penalizaciones'  => auth()->user()->can('talento.penalties.view') || $tieneAccesoAmplio,
            // David (28-sep): "los técnicos no pueden penalizarse, eso no lo
            // haría nadie ahí, solo debe mostrarse las penalizaciones... el
            // superior es el que penaliza, no ellos mismos, en caso de tener
            // subordinados sí lo pueden hacer" — mismo criterio que
            // ordenes_manage/rutas_manage, a propósito SIN $tieneAccesoAmplio
            // (verse a uno mismo no da de gratis la capacidad de
            // penalizarse — y de hecho es estructuralmente imposible, ver
            // TalentoPenaltyController::applyPenalty()).
            'penalizaciones_manage' => auth()->user()->can('talento.penalties.manage') || $esSuSupervisor,
            'credenciales'    => auth()->user()->can('talento.credentials.view') || $tieneAccesoAmplio,
            // David (28-sep): "la pestaña de por colaborador debe salirle
            // solo a los superiores" — a diferencia del resto de los
            // *_manage, a propósito SIN $esSuSupervisor implícito en
            // "verse a uno mismo": aquí NI SIQUIERA uno mismo entra (el
            // colaborador no gestiona su propia credencial/fondo, se
            // entera por correo automático) — solo admin/DESARROLLADOR o
            // ser supervisor de ALGUIEN. Mismo criterio que
            // TalentoCredentialController::esSuperiorDe().
            'credenciales_manage' => auth()->user()->can('talento.credentials.view')
                || auth()->user()->can('talento.funds.view')
                || auth()->user()->can('talento.employees.view')
                || (bool) ($miPropioColaborador?->subordinados()->exists()),
            'finiquito'       => auth()->user()->can('talento.loans.view') || $tieneAccesoAmplio,
            // Préstamos: uno mismo puede VER su saldo (mismo criterio que
            // ordenes_manage) — registrar/autorizar un préstamo SÍ es
            // gestión, a propósito SIN $tieneAccesoAmplio.
            'prestamos_manage' => auth()->user()->can('talento.loans.manage')
                || (bool) ($miPropioColaborador?->subordinados()->exists()),
            // Finiquito: a diferencia del resto, SIN excepción de
            // autoservicio ni siquiera para ver (calcular/cerrar tu propio
            // finiquito no tiene sentido estando activo) — mismo criterio
            // que credenciales_manage.
            'settlement_manage' => auth()->user()->can('talento.settlement.view')
                || auth()->user()->can('talento.liquidation.view')
                || (bool) ($miPropioColaborador?->subordinados()->exists()),
            'custodia'        => auth()->user()->can('talento.custody.view') || $tieneAccesoAmplio,
            // David (28-sep): "a no ser que sea supervisor o los otros
            // roles... quita el buscador de colaboradores" — el
            // buscador/grilla es una capacidad GLOBAL (no atada al
            // colaborador de ESTA ficha), mismo criterio que
            // proyectos_manage/credenciales_manage. NO se usa
            // talento.custody.view aquí (a propósito): ese permiso lo
            // tiene también TECNICO directo (ver
            // TalentoCustodiaController::puedeVerCustodiaDe()) y por eso
            // NO sirve para distinguir "staff que puede buscar a
            // cualquiera" de "técnico viendo solo lo suyo".
            'custodia_buscador' => auth()->user()->can('talento.employees.view')
                || (bool) ($miPropioColaborador?->subordinados()->exists()),
            'dispositivos'    => auth()->user()->can('talento.devices.view') || $tieneAccesoAmplio,
            // David (28-sep): "en dispositivos vinculados sigue el buscar
            // colaborador" — mismo criterio que custodia_buscador/
            // embajadores_buscador: talento.employees.view o CUALQUIER
            // supervisor, NO talento.devices.view (esa también la tiene
            // TECNICO directo, no distingue).
            'dispositivos_buscador' => auth()->user()->can('talento.employees.view')
                || (bool) ($miPropioColaborador?->subordinados()->exists()),
            'roles_multiples' => auth()->user()->can('talento.embajadores.view') || $tieneAccesoAmplio,
            // David (28-sep): "oculta el buscador aquí también, igual que
            // en Custodia" — mismo criterio que custodia_buscador:
            // talento.employees.view o CUALQUIER supervisor, NO
            // talento.embajadores.view (esa también la tiene TECNICO
            // directo, no distingue).
            'embajadores_buscador' => auth()->user()->can('talento.employees.view')
                || (bool) ($miPropioColaborador?->subordinados()->exists()),
            // Nuevas (item 1b, 28-sep): expediente/paquetes de documentos y
            // academia — antes solo pantallas globales, ahora también
            // pestañas de la ficha propia.
            'documentos'      => auth()->user()->can('talento.expediente.view') || $tieneAccesoAmplio,
            // David (29-sep), corrigiendo el criterio anterior: "fíjate en
            // Vendedores como está hecho" — ahí (staff viendo el expediente
            // de un vendedor) el botón "Completar documento" y los
            // recuadros de firma se muestran sin carve-outs. La ficha
            // propia debe verse EXACTAMENTE igual — mismo componente
            // (TalentoExpedienteDocumentos.vue), así que este flag AHORA SÍ
            // incluye $esPropia (uno mismo puede firmar cualquier slot de
            // SU PROPIO documento y completar SUS propios huecos, igual
            // que staff podría hacerlo por él). Ver
            // TalentoEmployeeDocumentController::puedeGestionarDocumentosDe().
            'documentos_gestionar' => auth()->user()->can('talento.expediente.documentos.gestionar')
                || auth()->user()->can('talento.work_orders.manage')
                || $esSuSupervisor
                || $esPropia,
            'academia'        => auth()->user()->can('talento.academy.view') || $tieneAccesoAmplio,
            // David (29-sep), verificando "Información": el botón "Gestión
            // de acceso (Administradores)" (link directo a /administracion/
            // user/{id}/editar) se mostraba SIEMPRE, sin gate — un técnico
            // viendo su propia ficha lo veía pero al hacer clic caía en
            // 403/redirect silencioso.
            //
            // CORRECCIÓN (29-sep, pedido explícito de David tras el primer
            // fix): "si es solo para administradores, quítalo de ahí y
            // déjalo específicamente para los superiores y desarrollador"
            // — confirmado que quiere acceso REAL (no solo visibilidad):
            // el supervisor directo puede editar la cuenta de sistema de
            // su subordinado (contraseña/contacto/rol), igual que un
            // admin, SOLO para su(s) subordinado(s) — nunca un usuario
            // ajeno. A propósito SIN $esPropia (verse a uno mismo no da
            // acceso a editar la propia cuenta desde aquí — es la misma
            // pantalla admin, con su propio candado real en
            // UserController::puedeGestionarUsuario()/
            // esSupervisorTalentoDe(), que valida el objetivo exacto).
            'informacion_gestion_acceso' => auth()->user()->can('user_edit_user') || $esSuSupervisor,
        ];

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
            // David (29-sep): "Academia — debe poder entrar a los cursos y
            // empezarlos ahí mismo". A diferencia de Documentos, TalentoAcademia
            // .vue (catálogo completo: ver curso, tomar examen) es SIEMPRE
            // self-scoped por auth()->id() en el backend — no tiene forma de
            // apuntarlo al colaborador de la ficha. Solo tiene sentido montarlo
            // cuando quien mira ES literalmente ese colaborador (nunca su
            // supervisor: "tomar el examen por él" sería falsificarlo, mismo
            // criterio que mostrarPortal) — señal aparte de esPropia de arriba,
            // que también es true para el supervisor.
            'esUnoMismoLiteral' => $esPropia,
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
        $colaborador = $this->resolverColaboradorAutoservicio($id);

        $colaborador->load(['user', 'supervisor.user', 'subordinados.user', 'puesto']);

        if ($colaborador->user) {
            $colaborador->user->role_names = $colaborador->user->getRoleNames();
        }

        $this->hideExpedienteFields($colaborador);

        return response()->json($colaborador);
    }

    /**
     * Progreso académico + certificaciones — mismo criterio de acceso que
     * miFichaDocumentos() de arriba, para la pestaña "Academia".
     */
    public function miFichaAcademia(string $id)
    {
        $colaborador = $this->resolverColaboradorAutoservicio($id);

        $academyController = app(TalentoAcademyController::class);

        return response()->json([
            'progress' => json_decode($academyController->progressForColaborador($colaborador->id)->getContent()),
            'certifications' => json_decode($academyController->certificationsForColaborador($colaborador->id)->getContent()),
        ]);
    }

    /**
     * Resuelve — o aborta 403/404 — el colaborador que el usuario logueado
     * puede ver en autoservicio: él mismo, su subordinado directo
     * (talento_colaboradores.supervisor_id), o cualquiera si gestiona
     * órdenes en general (talento.work_orders.manage — admin/DESARROLLADOR/
     * Mostrador). Punto único de esta regla — la usan miFicha(),
     * miFichaDocumentos() y miFichaAcademia() para no repetirla 3 veces.
     */
    private function resolverColaboradorAutoservicio(?string $id): TalentoColaborador
    {
        $miPropioColaborador = Actor::for(auth()->user())->talento();

        if ($id !== null && $miPropioColaborador === null && auth()->user()->can('talento.work_orders.manage')) {
            // Quien gestiona órdenes en general (ej. Mostrador) no
            // necesariamente tiene su propio registro de colaborador —
            // Diana no es "un talento", pero sí puede crearle órdenes a uno.
            $colaborador = TalentoColaborador::find($id);
            abort_if(! $colaborador, 404);

            return $colaborador;
        }

        if ($id === null || (string) $id === (string) ($miPropioColaborador->id ?? null)) {
            abort_if(! $miPropioColaborador, 404, 'No tienes un perfil de colaborador activo.');

            return $miPropioColaborador;
        }

        $colaborador = $miPropioColaborador->subordinados()->where('id', $id)->first()
            ?? (auth()->user()->can('talento.work_orders.manage') ? TalentoColaborador::find($id) : null);
        abort_if(! $colaborador, 403, 'No tienes permiso para ver a este colaborador.');

        return $colaborador;
    }

    /**
     * Listado de "mi equipo" — self-scoped, SOLO los subordinados DIRECTOS
     * del que pide (talento_colaboradores.supervisor_id) — sin exigir
     * talento.employees.view (roster completo). "El supervisor debe poder
     * controlar lo que hacen los trabajadores a su cargo" (David, 28-sep),
     * pero nada más — es un candado de seguridad, no un filtro opcional:
     * el where('supervisor_id', ...) es SIEMPRE forzado, ignorando
     * cualquier otro id que alguien intente mandar.
     */
    public function miEquipo(Request $request)
    {
        $this->authorize('talento.view');

        $miPropioColaborador = Actor::for(auth()->user())->talento();
        abort_if(! $miPropioColaborador, 404, 'No tienes un perfil de colaborador activo.');

        $q = TalentoColaborador::with(['user', 'supervisor.user', 'puesto'])
            ->where('supervisor_id', $miPropioColaborador->id)
            ->when($request->search, fn($q, $s) =>
                $q->whereHas('user', fn($u) => $u->where('name', 'like', "%$s%")->orWhere('email', 'like', "%$s%"))
            )
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->when($request->type,   fn($q, $t) => $q->where('type', $t))
            ->when($request->department, fn($q, $d) => $q->where('department', $d))
            ->orderBy('id', 'desc')
            ->paginate($request->per_page ?? 25);

        $q->each(function ($col) {
            if ($col->user) {
                $col->user->role_names = $col->user->getRoleNames();
            }
        });

        $this->hideExpedienteFields($q->getCollection());

        return response()->json($q);
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
