<?php

namespace App\Modules\Addons\MegaFamilia\Controllers;

use App\Http\Controllers\Controller;
use App\Models\BillingNotification;
use App\Models\Client;
use App\Modules\Core\Clientes\Models\Client as ClientModel;
use App\Models\ClientMainInformation;
use App\Models\File;
use App\Models\ObservationTask;
use App\Models\Task;
use App\Models\TaskClosure;
use App\Models\Ticket;
use App\Models\TicketThread;
use App\Models\User;
use App\Modules\Addons\MegaFamilia\Models\ParentalAccount;
use App\Modules\Addons\MegaFamilia\Models\ParentalDevice;
use App\Modules\Addons\MegaFamilia\Models\ParentalLocation;
use App\Modules\Addons\MegaFamilia\Models\ParentalProfile;
use App\Modules\Addons\MegaFamilia\Models\ParentalRequest;
use App\Modules\Addons\MegaFamilia\Models\ParentalRule;
use App\Modules\Addons\MegaFamilia\Models\ParentalTask;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * API mobile (padres + dispositivos hijos). Todas las rutas excepto login
 * usan `auth:sanctum`. El modelo `App\Models\User` ya tiene
 * `Laravel\Sanctum\HasApiTokens` (verificado), así que `$user->createToken()`
 * funciona out of the box.
 */
class ApiController extends Controller
{
    // ---- OTA --------------------------------------------------------------

    /**
     * Endpoint público que el cliente móvil consulta al abrir para
     * decidir si tiene que descargar una versión nueva. Versión y URL
     * vienen de la config persistida en `api_mobile_config`; si no hay
     * fila, caen a defaults razonables.
     *
     * El sha256 del APK se calcula al vuelo cuando el archivo está
     * disponible localmente — sirve para que la app valide la descarga.
     */
    public function appVersion(): JsonResponse
    {
        // Fuente de verdad: tabla `app_versions` poblada desde la UI admin.
        // Fallback al legacy ApiMobileConfig sólo si no hay versión activa.
        $latest = \App\Modules\Addons\MegaFamilia\Models\AppVersion::query()
            ->where('platform', 'android')
            ->where('active', true)
            ->orderByDesc('version_code')
            ->first();

        if ($latest) {
            $apkPath    = public_path($latest->file_path);
            $sha256     = is_file($apkPath) ? hash_file('sha256', $apkPath) : null;
            $builtAt    = is_file($apkPath) ? date('c', filemtime($apkPath)) : null;
            $clientCode = (int) request()->query('version_code', 0);
            $hasUpdate  = $latest->version_code > $clientCode;

            return response()->json([
                'has_update'   => $hasUpdate,
                'version'      => $latest->version_name,
                'version_name' => $latest->version_name,
                'version_code' => $latest->version_code,
                'apk_url'      => url('/api/megafamilia/mobile/download/' . $latest->id),
                'download_url' => url('/api/megafamilia/mobile/download/' . $latest->id),
                'sha256'       => $sha256,
                'size'         => (int) $latest->file_size,
                'file_size'    => (int) $latest->file_size,
                'built_at'     => $builtAt,
                'released_at'  => optional($latest->released_at)->toDateString(),
                'mandatory'    => (bool) $latest->is_mandatory,
                'is_mandatory' => (bool) $latest->is_mandatory,
                'release_notes'=> $latest->changelog,
                'changelog'    => $latest->changelog,
            ]);
        }

        // Fallback legacy — sin versión activa en app_versions
        $config = \App\Modules\Core\Configuracion\Models\ApiMobileConfig::getAll();

        if (empty($config['apk_url'])) {
            return response()->json([
                'has_update' => false,
                'message'    => 'No hay versión activa disponible.',
            ]);
        }

        return response()->json([
            'has_update'    => false,
            'version'       => $config['app_version'] ?? '0.2.0',
            'apk_url'       => $config['apk_url'],
            'download_url'  => $config['apk_url'],
            'mandatory'     => false,
            'release_notes' => $config['release_notes'] ?? null,
        ]);
    }

    // ---- AUTH -------------------------------------------------------------

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            // Acepta email real O login_user (username del cliente web).
            'email'       => 'required|string',
            'password'    => 'required|string',
            'device_name' => 'sometimes|string',
        ]);

        $input = $data['email'];

        // ── RUTA 1: staff ISP (conductor, técnico) ───────────────────────────
        // Solo aplica si el input tiene formato de email Y el usuario encontrado
        // tiene un rol de staff — evita interceptar clientes cuyos emails
        // coinciden con cuentas de sistema (ej. admin@... reutilizado como CMI).
        $staffRoles = ['conductor', 'TECNICO', 'TECNICO_INSTALADOR', 'TECNICO_PLANTA'];

        $staffUser = null;
        if (filter_var($input, FILTER_VALIDATE_EMAIL)) {
            $staffUser = User::where('email', $input)
                ->whereHas('roles', fn ($q) => $q->whereIn('name', $staffRoles))
                ->first();
        }

        if ($staffUser) {
            if (! \App\Services\Security\PasswordService::check($data['password'], $staffUser->password)) {
                return response()->json(['success' => false, 'error' => 'Credenciales inválidas'], 401);
            }
            // Upgrade-on-login: re-hashea base64 legacy a bcrypt.
            if (\App\Services\Security\PasswordService::needsRehash($staffUser->password)) {
                $staffUser->password = \App\Services\Security\PasswordService::make($data['password']);
                $staffUser->saveQuietly();
            }
            return $this->issueToken($staffUser, null, $data['device_name'] ?? 'mobile');
        }

        // ── RUTA 2: cliente final ────────────────────────────────────────────
        // Acepta email CMI o login_user (campo "Usuario Web" del panel admin).
        $cmi = ClientMainInformation::where('email', $input)
            ->orWhere('user', $input)
            ->first();

        if (! $cmi) {
            return response()->json(['success' => false, 'error' => 'Credenciales inválidas'], 401);
        }

        $user = User::where('login_user', $cmi->user)->first();

        if ($user) {
            // Cliente con users row: verifica bcrypt/base64 (creados por seeder o migración).
            // Fallback a plain de CMI para clientes cuyo bcrypt no coincide pero sí su web password.
            $passwordOk = \App\Services\Security\PasswordService::check($data['password'], $user->password)
                || ($data['password'] === $cmi->password);

            if (! $passwordOk) {
                return response()->json(['success' => false, 'error' => 'Credenciales inválidas'], 401);
            }

            return $this->issueToken($user, $cmi, $data['device_name'] ?? 'mobile');
        }

        // Sin users row: verificar plain antes de auto-crear (evita crear filas con password incorrecto).
        if (empty($cmi->password) || $data['password'] !== $cmi->password) {
            return response()->json(['success' => false, 'error' => 'Credenciales inválidas'], 401);
        }

        // Primer login exitoso de un cliente sin users row → crear la fila automáticamente.
        try {
            $user = $this->autoCreateUserFromCmi($cmi, $data['password']);
        } catch (\Throwable $e) {
            \Log::error('megafamilia_auto_create_failed', ['cmi_id' => $cmi->id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'error'   => 'No se pudo crear el usuario. Contacte al administrador.',
                'code'    => 'auto_create_failed',
            ], 500);
        }

        return $this->issueToken($user, $cmi, $data['device_name'] ?? 'mobile');
    }

    private function autoCreateUserFromCmi(ClientMainInformation $cmi, string $plainPassword): User
    {
        return DB::transaction(function () use ($cmi, $plainPassword) {
            // Defensa ante race condition: re-verificar dentro de la transacción.
            $existing = User::where('login_user', $cmi->user)->lockForUpdate()->first();
            if ($existing) {
                return $existing;
            }

            // Patrón observado en clientes existentes (diagnóstico 2026-06-03):
            // - login_user = cmi.user
            // - email      = cmi.email
            // - name       = cmi.name  (solo primer nombre)
            // - client_id  = (string) cmi.client_id
            // - Sin rol Spatie — resolveRoleForLogin() devuelve 'cliente' por defecto
            // client_id no está en $fillable — se asigna directamente tras create().
            $user = User::create([
                'login_user' => $cmi->user,
                'email'      => $cmi->email,
                'name'       => $cmi->name,
                'password'   => Hash::make($plainPassword),
            ]);
            $user->client_id = (string) $cmi->client_id;
            $user->saveQuietly();

            return $user;
        });
    }

    private function issueToken(User $user, ?ClientMainInformation $cmi, string $deviceName): JsonResponse
    {
        // Si no llegó CMI (ruta staff), intentar resolverlo igual para el payload.
        if (! $cmi) {
            $cmi = ClientMainInformation::where('user', $user->login_user)->first();
        }

        $client = $cmi ? Client::find($cmi->client_id) : null;
        $role   = $this->resolveRoleForLogin($user);
        $token  = $user->createToken($deviceName)->plainTextToken;

        return response()->json([
            'success' => true,
            'token'   => $token,
            'role'    => $role,
            'user'    => [
                'id'        => $user->id,
                'name'      => $cmi
                    ? trim(($cmi->name ?? '') . ' ' . ($cmi->father_last_name ?? ''))
                    : $user->name,
                'email'     => $cmi?->email ?? $user->email,
                'client_id' => $cmi?->client_id ?? $user->client_id,
                'role'      => $role,
            ],
            'client'  => $client ? $client->only('id', 'active') : null,
        ]);
    }

    /**
     * Prioridad de rol para la respuesta mobile:
     * conductor > tecnico > hijo/nino/... > cliente/padre > default(cliente)
     * Los roles admin se mapean a 'cliente' porque se redirigen a la web.
     */
    private function resolveRoleForLogin(User $user): string
    {
        $priority = ['conductor', 'TECNICO', 'tecnico', 'nino', 'preadolescente', 'adolescente', 'hijo', 'padre', 'DESARROLLADOR'];
        foreach ($priority as $r) {
            if ($user->hasRole($r)) {
                return strtolower($r);
            }
        }
        return 'cliente';
    }

    // ---- CLIENT DASHBOARD (mobile home) ----------------------------------

    /**
     * Resumen del servicio ISP del usuario autenticado. Si el usuario no
     * tiene cliente vinculado, regresa un placeholder en estado "sin servicio"
     * en lugar de 404 — el dashboard móvil necesita algo que renderizar.
     */
    public function servicio(): JsonResponse
    {
        $client = $this->resolveClientForCurrentUser();
        if ($client) {
            $client->load(['internet_service.internet', 'client_main_information']);
        }

        if (! $client) {
            return response()->json([
                'plan_name' => 'Sin servicio activo',
                'speed' => '—',
                'estado' => 'sin_servicio',
                'next_payment_date' => null,
                'consumo_gb' => null,
                'consumo_limite' => null,
                'contract_number' => null,
                'address' => null,
            ]);
        }

        $service = $client->internet_service->first();
        $internet = optional(optional($service)->internet);
        $info = optional($client->client_main_information);

        // Mismo query que PortalCliente\Controllers\DashboardController::index() (fuente
        // única de saldo). NO usar $client->balance() (morphOne): el legacy guarda
        // balanceable_type='App\Models\Client' (proxy), pero $client aquí es la instancia
        // real App\Modules\Core\Clientes\Models\Client, cuyo getMorphClass() no matchea.
        $saldo = DB::table('balances')
            ->where('balanceable_id', $client->id)
            ->where('balanceable_type', 'App\\Models\\Client')
            ->value('amount') ?? 0;

        return response()->json([
            'plan_name' => $internet->service_name ?? $internet->title ?? 'Plan ISP',
            'speed' => $this->formatSpeed($internet->download_speed),
            'estado' => $client->fecha_suspension ? 'suspendido' : 'activo',
            'next_payment_date' => $client->fecha_pago,
            'consumo_gb' => null,
            'consumo_limite' => null,
            'contract_number' => 'MGI-' . str_pad((string) $client->id, 6, '0', STR_PAD_LEFT),
            'address' => trim((string) ($info->address ?? '')) ?: null,
            // Saldo como campo separado (antes solo venía fusionado en el módulo de
            // facturas) + alerta de suspensión como booleano dedicado, ambos leídos de
            // datos ya existentes, sin lógica de umbral/cálculo nueva (item roadmap #490,
            // Opción C del brief: reusar fuente única, sin recalcular nada).
            'saldo' => (float) $saldo,
            'alerta_suspension' => (bool) $client->fecha_suspension,
        ]);
    }

    /**
     * Tickets del usuario autenticado. La tabla `tickets` usa `reporter_id`
     * (polymorphic con `reporter_type`) — para usuarios web es
     * App\Models\User. Si no hay tickets, regresa array vacío (la UI ya
     * maneja el caso "Aún no has creado tickets").
     */
    public function tickets(): JsonResponse
    {
        $userId = Auth::id();

        $rows = Ticket::where('reporter_id', $userId)
            ->where('reporter_type', User::class)
            ->orderByDesc('id')
            ->limit(50)
            ->get(['id', 'topic', 'estado', 'group', 'source', 'created_at']);

        return response()->json($rows->map(fn ($t) => $this->ticketToJson($t)));
    }

    /**
     * Crea un ticket desde la app móvil. Categoría libre (vienen valores
     * como "Internet lento" que no caben en el enum `group`), así que la
     * guardamos en `source`. La descripción larga se persiste como primer
     * mensaje del hilo.
     */
    public function storeTicket(Request $request): JsonResponse
    {
        $data = $request->validate([
            'subject' => 'required|string|max:255',
            'category' => 'nullable|string|max:255',
            'description' => 'nullable|string',
        ]);

        $user = Auth::user();

        $t = new Ticket();
        $t->topic = '[MegaFamilia] ' . $data['subject'];
        $t->estado = 'Nuevo';
        $t->group = 'Cualquier';
        $t->type = 'Pregunta';
        $t->source = $data['category'] ?? null;
        $t->reporter = $user->name;
        $t->reporter_id = $user->id;
        $t->reporter_type = User::class;
        $t->customer_lead = $this->resolveClientIspId($user);
        $t->save();

        if (! empty($data['description'])) {
            TicketThread::create([
                'ticket_id' => $t->id,
                'edited_by' => $user->id,
                'client_id' => optional(Client::where('user_id', $user->id)->first())->id ?? 0,
                'message' => $data['description'],
                'hidden' => false,
            ]);
        }

        return response()->json($this->ticketToJson($t), 201);
    }

    private function ticketToJson($t): array
    {
        return [
            'id' => $t->id,
            'number' => 'T-' . str_pad((string) $t->id, 6, '0', STR_PAD_LEFT),
            'subject' => $t->topic ?? '',
            'status' => $t->estado ?? 'Nuevo',
            'date' => $t->created_at,
            'category' => $t->source ?? $t->group,
        ];
    }

    // ---- FACTURAS --------------------------------------------------------

    /**
     * Facturas del cliente autenticado. Si el usuario no tiene cliente
     * ligado o no tiene facturas, devuelve 3 demo realistas para que la
     * pantalla nunca quede vacía.
     */
    public function facturas(): JsonResponse
    {
        $client = $this->resolveClientForCurrentUser();
        $rows = $client
            ? \DB::table('invoices')
                ->where('client_id', $client->id)
                ->orderByDesc('id')
                ->limit(24)
                ->get(['id', 'number', 'due_date', 'total', 'status'])
            : collect();

        if ($rows->isEmpty()) {
            return response()->json(
                config('megafamilia.financial_demo_enabled') ? $this->demoFacturas() : []
            );
        }

        return response()->json($rows->map(function ($r) {
            return [
                'id' => $r->id,
                'number' => $r->number ?: ('F-' . str_pad((string) $r->id, 6, '0', STR_PAD_LEFT)),
                'date' => $r->due_date,
                'amount' => (float) $r->total,
                'status' => $this->mapInvoiceStatus($r->status),
                'demo' => false,
            ];
        }));
    }

    private function mapInvoiceStatus(?string $s): string
    {
        return match ($s) {
            'paid' => 'pagada',
            'overdue' => 'vencida',
            'cancelled' => 'cancelada',
            'partially_paid' => 'parcial',
            default => 'pendiente',
        };
    }

    private function demoFacturas(): array
    {
        $now = now();
        return [
            ['id' => 1, 'number' => 'F-2026-00128', 'date' => $now->copy()->subDays(5)->toDateString(),
             'amount' => 450.00, 'status' => 'pendiente', 'demo' => true],
            ['id' => 2, 'number' => 'F-2026-00118', 'date' => $now->copy()->subDays(35)->toDateString(),
             'amount' => 450.00, 'status' => 'pagada', 'demo' => true],
            ['id' => 3, 'number' => 'F-2026-00109', 'date' => $now->copy()->subDays(65)->toDateString(),
             'amount' => 450.00, 'status' => 'pagada', 'demo' => true],
        ];
    }

    // ---- PAGOS -----------------------------------------------------------

    /**
     * Historial de pagos del cliente autenticado, derivado de las
     * facturas marcadas como pagadas (no hay tabla `payments` canónica
     * en este schema — `payment_id` en `invoices` apunta a la transacción
     * pero el detalle queda fuera del alcance v0.2). Si no hay registros,
     * regresa pagos demo.
     */
    public function pagos(): JsonResponse
    {
        $client = $this->resolveClientForCurrentUser();
        $rows = $client
            ? \DB::table('payments')
                ->where('paymentable_id', $client->id)
                ->whereIn('paymentable_type', ['App\\Models\\Client', 'App\\Modules\\Core\\Clientes\\Models\\Client'])
                ->orderByDesc('date')
                ->limit(10)
                ->get(['id', 'date', 'amount', 'comment'])
            : collect();

        if ($rows->isEmpty()) {
            return response()->json(
                config('megafamilia.financial_demo_enabled') ? $this->demoPagos() : []
            );
        }

        return response()->json($rows->map(function ($r) {
            return [
                'id' => $r->id,
                'date' => $r->date,
                'amount' => (float) $r->amount,
                'method' => $r->comment ?: 'Transferencia',
                'demo' => false,
            ];
        }));
    }

    private function demoPagos(): array
    {
        $now = now();
        return [
            ['id' => 1, 'date' => $now->copy()->subDays(35)->toDateString(),
             'amount' => 450.00, 'method' => 'Transferencia bancaria', 'demo' => true],
            ['id' => 2, 'date' => $now->copy()->subDays(65)->toDateString(),
             'amount' => 450.00, 'method' => 'OXXO', 'demo' => true],
            ['id' => 3, 'date' => $now->copy()->subDays(95)->toDateString(),
             'amount' => 450.00, 'method' => 'Tarjeta de crédito', 'demo' => true],
        ];
    }

    /**
     * v0.2 no tiene integración con pasarela ni persiste la intención de pago
     * (el docblock original prometía un `payment_promise` que nunca se creaba
     * — item roadmap #255). Responder éxito sin registrar nada podía
     * confundirse con un cobro real, así que el endpoint queda deshabilitado
     * hasta que se implemente persistencia real (decisión pendiente con
     * Irving: payment_promise vs reported_payment).
     */
    public function crearPago(Request $request): JsonResponse
    {
        $request->validate([
            'invoice_id' => 'nullable|integer',
            'amount' => 'required|numeric|min:0',
            'method' => 'required|string|in:tarjeta,spei,oxxo,efectivo',
            'reference' => 'nullable|string|max:64',
        ]);

        return response()->json([
            'success' => false,
            'error' => 'El pago desde la app aún no está disponible. Realiza tu pago por los canales habituales; el equipo lo confirmará manualmente.',
            'code' => 'not_implemented',
        ], 501);
    }

    /**
     * Descarga el PDF del recibo de un pago ya realizado. Reusa el PDF que
     * `BillingDocumentService::generarTicketYRecibo` ya generó al confirmarse
     * el pago (vía `PaymentBillingObserver`) — no genera nada nuevo, solo lo
     * sirve, escopado al cliente autenticado (anti-IDOR: item roadmap #491).
     */
    public function pagoPdf(int $id)
    {
        $client = $this->resolveClientForCurrentUser();
        if (! $client) {
            return response()->json(['error' => 'Cuenta sin cliente ISP asociado.'], 404);
        }

        $notif = BillingNotification::query()
            ->where('client_id', $client->id)
            ->where('payment_id', $id)
            ->where('document_type', BillingNotification::TYPE_RECIBO)
            ->whereNotNull('pdf_path')
            ->latest('id')
            ->first();

        if (! $notif) {
            return response()->json(['error' => 'No hay un PDF disponible para este pago.'], 404);
        }

        $disk = config('billing.pdf_disk', 'local');
        if (! Storage::disk($disk)->exists($notif->pdf_path)) {
            return response()->json(['error' => 'El archivo PDF ya no está disponible.'], 404);
        }

        return Storage::disk($disk)->download($notif->pdf_path, "recibo-{$id}.pdf");
    }

    // ---- ACCOUNT / PROFILE (ISP cliente) ---------------------------------

    /**
     * Datos consolidados del cliente ISP autenticado: nombre, contacto,
     * contrato, plan, estado de servicio y próximo pago. Si el usuario no
     * tiene un registro `clients` ligado, devuelve datos demo realistas
     * para que la pantalla no quede en blanco.
     */
    public function account(): JsonResponse
    {
        $user = Auth::user();
        $client = $this->resolveClientForCurrentUser();
        if ($client) {
            $client->load(['client_main_information', 'internet_service.internet']);
        }

        if (! $client) {
            return response()->json(
                config('megafamilia.financial_demo_enabled') ? $this->demoAccount($user) : $this->emptyAccount($user)
            );
        }

        $info = $client->client_main_information;
        $service = $client->internet_service->first();
        $internet = optional(optional($service)->internet);

        $fullName = trim(implode(' ', array_filter([
            $info->name ?? null,
            $info->father_last_name ?? null,
            $info->mother_last_name ?? null,
        ]))) ?: $user->name;

        return response()->json([
            'name' => $fullName,
            'email' => ($info && $info->email) ? $info->email : $user->email,
            'phone' => $info->phone ?? '—',
            'contract_number' => 'MGI-' . str_pad((string) $client->id, 6, '0', STR_PAD_LEFT),
            'plan_name' => $internet->service_name ?? $internet->title ?? 'Plan ISP',
            'speed' => $this->formatSpeed($internet->download_speed),
            'estado' => $client->fecha_suspension ? 'suspendido' : 'activo',
            'next_payment_date' => $client->fecha_pago ?: null,
            'balance' => 0,
            'consumo_gb' => null,
            'consumo_limite' => null,
            'address' => trim((string) ($info->address ?? '')) ?: null,
            'demo' => false,
        ]);
    }

    /**
     * Perfil del usuario (nombre, email, teléfono, dirección). Para que
     * la app móvil siempre tenga algo que renderizar, los datos del
     * `clients`/`client_main_information` ligado tienen prioridad sobre
     * los del modelo `users`. Cae a demo si no hay nada.
     */
    public function profile(): JsonResponse
    {
        $user = Auth::user();
        $client = $this->resolveClientForCurrentUser();
        if ($client) {
            $client->load('client_main_information');
        }
        $info = optional(optional($client)->client_main_information);

        $name = trim(implode(' ', array_filter([
            $info->name ?? $user->name,
            $info->father_last_name ?? $user->father_last_name ?? null,
            $info->mother_last_name ?? $user->mother_last_name ?? null,
        ]))) ?: $user->name;

        return response()->json([
            'name' => $name,
            'email' => $info->email ?? $user->email,
            'phone' => $info->phone ?? $user->phone ?? '—',
            'address' => $info->address ?? $user->address ?? null,
            'role' => strtolower((string) ($user->getRoleNames()->first() ?? '')),
            'avatar_url' => $user->photography ?: null,
            'demo' => $client === null,
        ]);
    }

    private function demoAccount(User $u): array
    {
        return [
            'name' => $u->name,
            'email' => $u->email,
            'phone' => '744-555-2200',
            'contract_number' => 'MGI-DEMO-001',
            'plan_name' => 'Internet Hogar 200',
            'speed' => '200 Mbps',
            'estado' => 'activo',
            'next_payment_date' => now()->addDays(8)->toDateString(),
            'balance' => 450.00,
            'consumo_gb' => 187.5,
            'consumo_limite' => 500,
            'address' => 'Av. Reforma 123, Col. Centro, Acapulco',
            'demo' => true,
        ];
    }

    /** Estado real (sin datos inventados) cuando el usuario no tiene cliente ISP ligado. */
    private function emptyAccount(User $u): array
    {
        return [
            'name' => $u->name,
            'email' => $u->email,
            'phone' => $u->phone ?? '—',
            'contract_number' => null,
            'plan_name' => 'Sin servicio activo',
            'speed' => '—',
            'estado' => 'sin_servicio',
            'next_payment_date' => null,
            'balance' => null,
            'consumo_gb' => null,
            'consumo_limite' => null,
            'address' => null,
            'demo' => false,
        ];
    }

    // ---- ACCOUNT / PROFILES (parental) -----------------------------------

    public function parentalAccount(): JsonResponse
    {
        $account = $this->requireAccount();
        return response()->json($account->load(['plan', 'profiles', 'license']));
    }

    public function profiles(): JsonResponse
    {
        $account = $this->requireAccount();
        return response()->json(
            $account->profiles()->withCount('devices')->get()
                ->map(fn ($p) => [
                    'id'           => $p->id,
                    'name'         => $p->name,
                    'age'          => $p->age,
                    'school_level' => $p->school_level,
                    'profile_type' => $p->profile_type,
                    'devices_count'=> $p->devices_count,
                    'active'       => $p->active,
                ])
        );
    }

    public function profileDetail(int $id): JsonResponse
    {
        $profile = $this->requireProfile($id);
        $rules   = $profile->rules;

        return response()->json([
            'id'                  => $profile->id,
            'name'                => $profile->name,
            'age'                 => $profile->age,
            'school_level'        => $profile->school_level,
            'profile_type'        => $profile->profile_type,
            'time_minutes_today'  => 0,                                                  // tracking de dispositivo pendiente
            'time_limit_minutes'  => $rules->daily_limit_minutes ?? 180,
            'internet_paused'     => $rules ? (bool) $rules->internet_paused : false,
            'apps'                => [],                                                 // app-usage tracking pendiente
        ]);
    }

    public function storeProfile(Request $request): JsonResponse
    {
        $account = $this->requireAccount();
        $data = $request->validate([
            'name' => 'required|string',
            'age' => 'nullable|integer|min:0|max:25',
            'school_level' => 'nullable|in:primaria,secundaria,preparatoria',
            'profile_type' => 'nullable|in:nino,preadolescente,adolescente',
        ]);
        $data['account_id'] = $account->id;
        return response()->json(ParentalProfile::create($data), 201);
    }

    // ---- DEVICES ---------------------------------------------------------

    public function profileDevices(int $id): JsonResponse
    {
        $profile = $this->requireProfile($id);
        return response()->json($profile->devices);
    }

    public function deviceRules(int $id): JsonResponse
    {
        $device = $this->requireDevice($id);
        $rules = ParentalRule::firstOrCreate(['profile_id' => $device->profile_id]);
        return response()->json($rules);
    }

    public function updateDeviceRules(Request $request, int $id): JsonResponse
    {
        $device = $this->requireDevice($id);
        $rules = ParentalRule::firstOrCreate(['profile_id' => $device->profile_id]);
        $rules->update($request->only([
            'daily_limit_minutes', 'weekend_limit_minutes',
            'bedtime_start', 'bedtime_end',
            'school_start', 'school_end',
            'internet_paused',
        ]));
        return response()->json(['success' => true, 'rules' => $rules]);
    }

    // ---- TASKS / REQUESTS ------------------------------------------------

    public function profileTasks(int $id): JsonResponse
    {
        $profile = $this->requireProfile($id);
        return response()->json($profile->tasks()->orderByDesc('id')->get());
    }

    public function completeTask(Request $request, int $id): JsonResponse
    {
        $account = $this->requireAccount();
        $task = ParentalTask::where('id', $id)
            ->whereHas('profile', fn ($q) => $q->where('account_id', $account->id))
            ->first();
        abort_unless($task, 404);
        $task->update([
            'status' => 'completed',
            'completed_at' => now(),
            'photo_proof' => $request->input('photo_proof'),
        ]);
        return response()->json(['success' => true]);
    }

    public function storeRequest(Request $request): JsonResponse
    {
        $data = $request->validate([
            'profile_id' => 'required|exists:parental_profiles,id',
            'device_id' => 'nullable|exists:parental_devices,id',
            'type' => 'required|in:time_extra,app_unlock,web_unlock',
            'detail' => 'nullable|string',
            'message' => 'nullable|string',
        ]);
        // Anti-IDOR: el perfil (y el dispositivo, si se envía) deben pertenecer
        // a la cuenta del usuario autenticado; un id ajeno devuelve 404.
        $this->requireProfile((int) $data['profile_id']);
        if (! empty($data['device_id'])) {
            $this->requireDevice((int) $data['device_id']);
        }
        $data['status'] = 'pending';
        $data['expires_at'] = now()->addHours(2);
        return response()->json(ParentalRequest::create($data), 201);
    }

    public function pendingRequests(): JsonResponse
    {
        $account = $this->requireAccount();
        $rows = ParentalRequest::whereIn('profile_id', $account->profiles()->pluck('id'))
            ->where('status', 'pending')
            ->orderByDesc('id')->get();
        return response()->json($rows);
    }

    public function respondRequest(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['status' => 'required|in:approved,rejected']);
        // Anti-IDOR: la solicitud debe pertenecer a un perfil de la cuenta autenticada.
        $account = $this->requireAccount();
        $r = ParentalRequest::where('id', $id)
            ->whereHas('profile', fn ($q) => $q->where('account_id', $account->id))
            ->first();
        abort_unless($r, 404);
        $r->update(['status' => $data['status'], 'responded_at' => now()]);
        return response()->json(['success' => true]);
    }

    // ---- LOCATIONS -------------------------------------------------------

    public function reportLocation(Request $request): JsonResponse
    {
        $data = $request->validate([
            'device_id' => 'required|exists:parental_devices,id',
            'lat' => 'required|numeric',
            'lng' => 'required|numeric',
            'accuracy' => 'nullable|integer',
            'battery' => 'nullable|integer|min:0|max:100',
        ]);
        // Anti-IDOR: el dispositivo debe pertenecer a la cuenta autenticada.
        $this->requireDevice((int) $data['device_id']);
        $data['recorded_at'] = now();
        ParentalLocation::create($data);

        ParentalDevice::where('id', $data['device_id'])->update([
            'last_seen_at' => now(),
            'status' => 'online',
            'battery_level' => $data['battery'] ?? null,
        ]);

        return response()->json(['success' => true]);
    }

    public function profileLocation(int $id): JsonResponse
    {
        $profile = $this->requireProfile($id);
        $deviceIds = $profile->devices()->pluck('id');
        $latest = ParentalLocation::whereIn('device_id', $deviceIds)
            ->orderByDesc('recorded_at')
            ->limit($deviceIds->count())
            ->get()
            ->unique('device_id');
        return response()->json($latest->values());
    }

    // ---- TECNICO ---------------------------------------------------------

    /**
     * Órdenes (tasks) asignadas al técnico autenticado. La asignación está
     * en la tabla pivot `task_user`. Mapea los estados del modelo Task a los
     * que espera la app: ToDo→pendiente, InProgress→en_proceso, Done→completada.
     */
    public function tecnicoOrdenes(): JsonResponse
    {
        $userId = Auth::id();

        $tasks = \App\Models\Task::whereHas('users', fn ($q) => $q->where('users.id', $userId))
            ->whereNotIn('status', ['Archivado'])
            ->with(['client_main_information', 'closure'])
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        return response()->json($tasks->map(fn ($t) => $this->taskToOrden($t)));
    }

    /**
     * Actualiza/cierra una orden desde la app del técnico. Persiste:
     *   - status   → enum de Task (pendiente/en_proceso/completada)
     *   - notes    → ObservationTask
     *   - photo    → archivo multipart (campo `photo`) guardado como File
     *                polimórfico de la tarea (visible en el visor de archivos)
     *   - signature→ imagen base64 (data URI o crudo) guardada como PNG + File
     *   - latitude/longitude/gps_accuracy → geolocalización del cierre
     * Los datos de cierre (GPS + paths firma/foto) viven en `task_closures`
     * (una fila por tarea, upsert). Solo el técnico asignado puede actualizar.
     */
    public function updateTecnicoOrden(Request $request, int $id): JsonResponse
    {
        $task = Task::whereHas(
            'users', fn ($q) => $q->where('users.id', Auth::id())
        )->findOrFail($id);

        $data = $request->validate([
            'status'       => 'sometimes|string',
            'notes'        => 'sometimes|nullable|string',
            'photo'        => 'sometimes|file|image|max:10240',
            'signature'    => 'sometimes|nullable|string',
            'latitude'     => 'sometimes|nullable|numeric|between:-90,90',
            'longitude'    => 'sometimes|nullable|numeric|between:-180,180',
            'gps_accuracy' => 'sometimes|nullable|numeric|min:0',
        ]);

        if (isset($data['status'])) {
            $task->status = $this->mapOrdenStatusToTask($data['status']);
            $task->save();
        }

        if (! empty($data['notes'])) {
            ObservationTask::create([
                'task_id'    => $task->id,
                'created_by' => Auth::id(),
                'observation'=> $data['notes'],
            ]);
        }

        $directory = 'task/' . $task->id . '/closure';
        $photoPath = null;
        $signaturePath = null;

        // --- Foto del cierre (multipart) -> File polimórfico ---------------
        // Se guarda en el disco `public` vía Storage (maneja la creación de
        // directorios con permisos correctos para www-data, a diferencia del
        // mkdir manual de uploadImage); queda servible por URL `/storage/...`.
        if ($request->hasFile('photo')) {
            $photo  = $request->file('photo');
            $ext    = strtolower($photo->getClientOriginalExtension() ?: 'jpg');
            $stored = $photo->storeAs(
                'uploads/' . $directory,
                'foto_' . time() . '_' . Str::random(6) . '.' . $ext,
                'public'
            );
            if ($stored) {
                // Path relativo (no URL absoluta): el cliente antepone su propia
                // base URL (el host por el que entró). Evita atar el path a un
                // APP_URL/IP fijo — clave para acceso multi-host y producto SaaS.
                $photoPath = '/storage/' . ltrim($stored, '/');
                $task->files()->create([
                    'name'          => 'Foto de cierre - ' . $photo->getClientOriginalName(),
                    'type'          => $photo->getClientMimeType() ?: 'image/jpeg',
                    'path'          => $photoPath,
                    'size'          => $photo->getSize() ?? 0,
                    'preview'       => 0,
                    'fileable_type' => Task::class,
                ]);
            }
        }

        // --- Firma (base64) -> PNG en disco public + File ------------------
        if (! empty($data['signature'])) {
            $signaturePath = $this->storeSignature($data['signature'], $directory);
            if ($signaturePath) {
                $task->files()->create([
                    'name'          => 'Firma de cierre',
                    'type'          => 'image/png',
                    'path'          => $signaturePath,
                    'size'          => 0,
                    'preview'       => 0,
                    'fileable_type' => Task::class,
                ]);
            }
        }

        // --- Cierre: GPS + metadatos (una fila por tarea) ------------------
        $hasGps     = array_key_exists('latitude', $data) && array_key_exists('longitude', $data)
                      && $data['latitude'] !== null && $data['longitude'] !== null;
        $isClosing  = isset($data['status']) && $task->status === 'Done';

        if ($hasGps || $photoPath || $signaturePath || $isClosing || ! empty($data['notes'])) {
            $closure = $task->closure ?: new TaskClosure(['task_id' => $task->id]);
            $closure->closed_by = Auth::id();
            if ($hasGps) {
                $closure->latitude     = $data['latitude'];
                $closure->longitude    = $data['longitude'];
                $closure->gps_accuracy = $data['gps_accuracy'] ?? $closure->gps_accuracy;
            }
            if (! empty($data['notes']))   $closure->notes = $data['notes'];
            if ($photoPath)                $closure->photo_path = $photoPath;
            if ($signaturePath)            $closure->signature_path = $signaturePath;
            if ($isClosing || ! $closure->closed_at) $closure->closed_at = now();
            $closure->save();
        }

        return response()->json([
            'success' => true,
            'orden'   => $this->taskToOrden($task->fresh()),
            'closure' => optional($task->fresh()->closure)->only([
                'latitude', 'longitude', 'gps_accuracy', 'notes',
                'signature_path', 'photo_path', 'closed_at',
            ]),
        ]);
    }

    /**
     * Decodifica una firma en base64 (acepta data URI `data:image/png;base64,`
     * o base64 crudo) y la guarda como PNG en el disco public. Devuelve el
     * path público (`/storage/...`) o null si la decodificación falla.
     */
    private function storeSignature(string $signature, string $directory): ?string
    {
        if (preg_match('/^data:image\/(\w+);base64,/', $signature, $m)) {
            $signature = substr($signature, strpos($signature, ',') + 1);
        }
        $signature = str_replace(' ', '+', $signature);
        $binary = base64_decode($signature, true);
        if ($binary === false || $binary === '') {
            return null;
        }

        $relative = "uploads/{$directory}/firma_" . time() . '_' . Str::random(6) . '.png';
        \Illuminate\Support\Facades\Storage::disk('public')->put($relative, $binary);

        // Path relativo (ver nota en updateTecnicoOrden): el cliente le antepone
        // su base URL. Servible en {host}/storage/uploads/task/{id}/closure/...
        return '/storage/' . ltrim($relative, '/');
    }

    private function taskToOrden(\App\Models\Task $t): array
    {
        $info = optional($t->client_main_information);
        $type = $this->inferOrdenType($t->title ?? '');

        $scheduledAt = null;
        if ($t->start_date) {
            $dt = $t->start_date . ($t->start_time ? ' ' . $t->start_time : '');
            $scheduledAt = \Carbon\Carbon::parse($dt)->toIso8601String();
        }

        return [
            'id'          => $t->id,
            'number'      => 'OT-' . str_pad((string) $t->id, 4, '0', STR_PAD_LEFT),
            'type'        => $type,
            'client_name' => $info->name ? trim($info->name . ' ' . ($info->father_last_name ?? '')) : 'Cliente',
            'client_phone'=> $info->phone ?? null,
            'address'     => $t->address ?? ($info->address ?? ''),
            'status'      => $this->mapTaskStatusToOrden($t->status ?? 'ToDo'),
            'scheduled_at'=> $scheduledAt,
            'notes'       => optional($t->latestNote)->observation ?? null,
            'plan_name'   => null,
            'closed'      => $t->relationLoaded('closure') ? (bool) $t->closure : (bool) $t->closure()->exists(),
            'closed_at'   => optional($t->closure)->closed_at?->toIso8601String(),
        ];
    }

    private function inferOrdenType(string $title): string
    {
        $lower = mb_strtolower($title);
        if (str_contains($lower, 'instalac')) return 'Instalación';
        if (str_contains($lower, 'repar'))    return 'Reparación';
        if (str_contains($lower, 'manteni'))  return 'Mantenimiento';
        return $title ?: 'Instalación';
    }

    private function mapTaskStatusToOrden(string $status): string
    {
        return match ($status) {
            'InProgress', 'inprogress' => 'en_proceso',
            'Done', 'done'             => 'completada',
            default                    => 'pendiente',
        };
    }

    private function mapOrdenStatusToTask(string $status): string
    {
        return match (strtolower($status)) {
            'en_proceso', 'inprogress' => 'InProgress',
            'completada', 'done'       => 'Done',
            default                    => 'ToDo',
        };
    }

    // ---- HIJO ------------------------------------------------------------

    /**
     * Tareas visibles para el usuario hijo. Busca la cuenta parental del
     * usuario autenticado; si no existe (cuando el hijo aún no tiene cuenta
     * propia vinculada), regresa array vacío para que la UI muestre estado
     * "Sin tareas" en lugar de error.
     */
    public function hijoTareas(): JsonResponse
    {
        $account = ParentalAccount::where('user_id', Auth::id())->first();
        if (! $account) {
            return response()->json([]);
        }

        $profileIds = $account->profiles()->pluck('id');
        $tasks = ParentalTask::whereIn('profile_id', $profileIds)
            ->whereNotIn('status', ['rejected'])
            ->orderByDesc('id')
            ->get(['id', 'title', 'description', 'reward_type', 'reward_value',
                   'reward_detail', 'points', 'status']);

        return response()->json($tasks);
    }

    // ---- helpers ---------------------------------------------------------

    /** Convierte Kbps a string legible: 102400 → "100 Mbps". */
    private function formatSpeed(?int $kbps): string
    {
        if (! $kbps) return '—';
        $mbps = (int) round($kbps / 1024);
        return $mbps . ' Mbps';
    }

    /**
     * Resuelve el Client del usuario autenticado. La FK directa `clients.user_id`
     * sólo existe para ~1 registro; el flujo correcto es CMI.user → login_user.
     */
    private function resolveClientForCurrentUser(): ?ClientModel
    {
        $client = Client::where('user_id', Auth::id())->first();
        if ($client) return $client;

        $loginUser = optional(Auth::user())->login_user;
        if (! $loginUser) return null;

        $cmi = ClientMainInformation::where('user', $loginUser)->first();
        if (! $cmi) return null;

        return Client::find($cmi->client_id);
    }

    private function requireAccount(): ParentalAccount
    {
        $account = ParentalAccount::where('user_id', Auth::id())->first();
        abort_if(! $account, 404, 'No hay cuenta MegaFamilia asociada al usuario');
        return $account;
    }

    private function requireProfile(int $id): ParentalProfile
    {
        $account = $this->requireAccount();
        $profile = ParentalProfile::where('account_id', $account->id)->where('id', $id)->first();
        abort_unless($profile, 404);
        return $profile;
    }

    /**
     * Resuelve un dispositivo SOLO si pertenece a un perfil de la cuenta del
     * usuario autenticado. Evita IDOR: un device_id ajeno devuelve 404.
     */
    private function requireDevice(int $id): ParentalDevice
    {
        $account = $this->requireAccount();
        $device = ParentalDevice::where('id', $id)
            ->whereHas('profile', fn ($q) => $q->where('account_id', $account->id))
            ->first();
        abort_unless($device, 404);
        return $device;
    }

    // ---- EMBAJADORES --------------------------------------------------------

    public function embajadorRed(): JsonResponse
    {
        $client = $this->resolveClientForCurrentUser();
        if (! $client) return response()->json(['nodes' => [], 'total' => 0]);

        $rows = DB::table('referrals as r')
            ->join('clients as c', 'c.id', '=', 'r.referred_client_id')
            ->leftJoin('users as u', 'u.id', '=', 'c.user_id')
            ->where('r.chain_path', 'LIKE', '/' . $client->id . '/%')
            ->select([
                'r.referred_client_id as client_id',
                'r.embajador_id as parent_id',
                'r.chain_depth as depth',
                'r.status',
                'r.referred_threshold_covered_at',
                DB::raw("TRIM(CONCAT(COALESCE(u.name,''), ' ', COALESCE(u.father_last_name,''), ' ', COALESCE(u.mother_last_name,''))) as name"),
            ])
            ->get();

        $nodes = $rows->map(function ($row) {
            $status = $row->status === 'active'
                ? ($row->referred_threshold_covered_at ? 'active' : 'pending_threshold')
                : 'cancelled';
            return [
                'client_id' => $row->client_id,
                'parent_id' => $row->parent_id,
                'name'      => trim($row->name) ?: 'Cliente #' . $row->client_id,
                'depth'     => (int) $row->depth,
                'status'    => $status,
            ];
        });

        return response()->json(['nodes' => $nodes, 'total' => $nodes->count()]);
    }

    public function embajadorProspectos(Request $request): JsonResponse
    {
        $client = $this->resolveClientForCurrentUser();
        if (! $client) return response()->json(['data' => [], 'last_page' => 1, 'total' => 0]);

        $q = DB::table('referral_prospects')->where('embajador_id', $client->id);

        if ($search = $request->input('search')) {
            $q->where(function ($w) use ($search) {
                $w->where('name', 'LIKE', "%$search%")
                  ->orWhere('phone', 'LIKE', "%$search%")
                  ->orWhere('email', 'LIKE', "%$search%");
            });
        }
        if ($status = $request->input('status')) $q->where('status', $status);
        if ($source = $request->input('source')) $q->where('source', $source);

        $paginated = $q->orderByDesc('created_at')
            ->paginate((int) $request->input('per_page', 20));

        $data = collect($paginated->items())
            ->map(fn($r) => $this->formatProspecto($r, $client->id));

        return response()->json([
            'data'      => $data,
            'total'     => $paginated->total(),
            'last_page' => $paginated->lastPage(),
        ]);
    }

    public function embajadorGetProspecto(int $id): JsonResponse
    {
        $client = $this->resolveClientForCurrentUser();
        $prospect = DB::table('referral_prospects')
            ->where('id', $id)->where('embajador_id', $client?->id)->first();
        abort_unless($prospect, 404);

        $followups = DB::table('prospect_followups')
            ->where('prospect_id', $id)->orderByDesc('created_at')->get()->all();

        return response()->json($this->formatProspecto($prospect, $client->id, $followups));
    }

    public function embajadorStoreProspecto(Request $request): JsonResponse
    {
        $client = $this->resolveClientForCurrentUser();
        abort_unless($client, 422, 'No hay cliente asociado');

        $data = $request->validate([
            'name'    => 'required|string|max:200',
            'phone'   => 'required|string|max:30',
            'email'   => 'nullable|email|max:200',
            'address' => 'nullable|string|max:300',
            'source'  => 'required|string|in:manual,contact,qr,link,whatsapp',
            'notes'   => 'nullable|string|max:1000',
        ]);

        $id = DB::table('referral_prospects')->insertGetId(array_merge($data, [
            'embajador_id' => $client->id,
            'status'       => 'new',
            'created_at'   => now(),
            'updated_at'   => now(),
        ]));

        $prospect = DB::table('referral_prospects')->where('id', $id)->first();
        return response()->json($this->formatProspecto($prospect, $client->id), 201);
    }

    public function embajadorUpdateProspecto(Request $request, int $id): JsonResponse
    {
        $client = $this->resolveClientForCurrentUser();
        $prospect = DB::table('referral_prospects')
            ->where('id', $id)->where('embajador_id', $client?->id)->first();
        abort_unless($prospect, 404);

        $data = $request->validate([
            'name'    => 'sometimes|string|max:200',
            'phone'   => 'sometimes|string|max:30',
            'email'   => 'nullable|email|max:200',
            'address' => 'nullable|string|max:300',
            'source'  => 'sometimes|string|max:50',
            'status'  => 'sometimes|string|in:new,contacted,interested,quoted,converted,lost',
            'notes'   => 'nullable|string|max:1000',
        ]);

        DB::table('referral_prospects')->where('id', $id)
            ->update(array_merge($data, ['updated_at' => now()]));
        $updated = DB::table('referral_prospects')->where('id', $id)->first();
        return response()->json($this->formatProspecto($updated, $client->id));
    }

    public function embajadorDeleteProspecto(int $id): JsonResponse
    {
        $client = $this->resolveClientForCurrentUser();
        $deleted = DB::table('referral_prospects')
            ->where('id', $id)->where('embajador_id', $client?->id)->delete();
        abort_unless($deleted, 404);
        return response()->json(['success' => true]);
    }

    public function embajadorFollowups(int $id): JsonResponse
    {
        $client = $this->resolveClientForCurrentUser();
        $prospect = DB::table('referral_prospects')
            ->where('id', $id)->where('embajador_id', $client?->id)->first();
        abort_unless($prospect, 404);

        $rows = DB::table('prospect_followups')
            ->where('prospect_id', $id)->orderByDesc('created_at')->get();

        return response()->json($rows->map(fn($r) => $this->formatFollowup($r)));
    }

    public function embajadorAddFollowup(Request $request, int $id): JsonResponse
    {
        $client = $this->resolveClientForCurrentUser();
        $prospect = DB::table('referral_prospects')
            ->where('id', $id)->where('embajador_id', $client?->id)->first();
        abort_unless($prospect, 404);

        $data = $request->validate([
            'action'           => 'required|string|in:call,whatsapp,visit,sms,email,note',
            'notes'            => 'required|string|max:1000',
            'next_action_date' => 'nullable|date',
        ]);

        $fid = DB::table('prospect_followups')->insertGetId(array_merge($data, [
            'prospect_id'  => $id,
            'embajador_id' => $client->id,
            'created_at'   => now(),
        ]));

        DB::table('referral_prospects')->where('id', $id)
            ->update(['last_contact_at' => now(), 'updated_at' => now()]);

        $followup = DB::table('prospect_followups')->where('id', $fid)->first();
        return response()->json($this->formatFollowup($followup), 201);
    }

    public function embajadorImportProspectos(Request $request): JsonResponse
    {
        $client = $this->resolveClientForCurrentUser();
        abort_unless($client, 422, 'No hay cliente asociado');

        $contacts = $request->input('contacts', []);
        $created  = 0;
        $skipped  = 0;

        foreach ($contacts as $c) {
            $phone = preg_replace('/\D/', '', $c['phone'] ?? '');
            if (empty($phone) || empty($c['name'])) { $skipped++; continue; }

            $exists = DB::table('referral_prospects')
                ->where('embajador_id', $client->id)->where('phone', $phone)->exists();
            if ($exists) { $skipped++; continue; }

            DB::table('referral_prospects')->insert([
                'embajador_id' => $client->id,
                'name'         => substr($c['name'], 0, 200),
                'phone'        => $phone,
                'email'        => isset($c['email']) ? substr($c['email'], 0, 200) : null,
                'source'       => 'contact',
                'status'       => 'new',
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
            $created++;
        }

        return response()->json(['created' => $created, 'skipped' => $skipped]);
    }

    public function embajadorRecompensas(Request $request): JsonResponse
    {
        $client = $this->resolveClientForCurrentUser();
        if (! $client) return response()->json(['data' => [], 'summary' => (object) []]);

        $paginated = DB::table('referral_rewards as rw')
            ->join('referrals as r', 'r.id', '=', 'rw.referral_id')
            ->join('clients as c', 'c.id', '=', 'r.referred_client_id')
            ->leftJoin('users as u', 'u.id', '=', 'c.user_id')
            ->where('rw.embajador_id', $client->id)
            ->select([
                'rw.id', 'rw.status',
                'rw.plan_value_snapshot as plan_value',
                'rw.available_at', 'rw.applied_at', 'rw.expires_at',
                DB::raw("TRIM(CONCAT(COALESCE(u.name,''), ' ', COALESCE(u.father_last_name,''))) as referido_name"),
            ])
            ->orderByDesc('rw.created_at')
            ->paginate((int) $request->input('per_page', 20));

        $summary = DB::table('referral_rewards')
            ->where('embajador_id', $client->id)
            ->select('status', DB::raw('count(*) as cnt'))
            ->groupBy('status')
            ->pluck('cnt', 'status')
            ->toArray();

        return response()->json([
            'data'    => $paginated->items(),
            'summary' => $summary ?: (object) [],
        ]);
    }

    public function embajadorAplicarRecompensa(int $id): JsonResponse
    {
        $client = $this->resolveClientForCurrentUser();
        $reward  = DB::table('referral_rewards')
            ->where('id', $id)->where('embajador_id', $client?->id)->first();
        abort_unless($reward, 404);
        abort_if($reward->status !== 'available', 422, 'La recompensa no está disponible');

        DB::table('referral_rewards')->where('id', $id)->update([
            'status'     => 'applied',
            'applied_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['success' => true]);
    }

    public function embajadorComisiones(): JsonResponse
    {
        $client = $this->resolveClientForCurrentUser();
        if (! $client) return response()->json(['data' => [], 'grand_total' => 0, 'plan_type' => '']);

        $profile = DB::table('client_referral_profiles')
            ->where('client_id', $client->id)->first();

        $rows = DB::table('referral_commissions')
            ->where('beneficiary_id', $client->id)
            ->select([
                'period_year', 'period_month', 'status',
                DB::raw('SUM(commission_amount) as total'),
                DB::raw('COUNT(*) as count'),
            ])
            ->groupBy('period_year', 'period_month', 'status')
            ->orderByDesc('period_year')->orderByDesc('period_month')
            ->get();

        $periods = [];
        foreach ($rows as $row) {
            $key = $row->period_year . '-' . $row->period_month;
            if (! isset($periods[$key])) {
                $periods[$key] = [
                    'period_year'  => (int) $row->period_year,
                    'period_month' => (int) $row->period_month,
                    'total'        => 0.0,
                    'count'        => 0,
                    'by_status'    => [],
                ];
            }
            $periods[$key]['total']                   += (float) $row->total;
            $periods[$key]['count']                   += (int) $row->count;
            $periods[$key]['by_status'][$row->status]  = (float) $row->total;
        }

        $grandTotal = array_sum(array_column(array_values($periods), 'total'));

        return response()->json([
            'data'        => array_values($periods),
            'grand_total' => round($grandTotal, 2),
            'plan_type'   => optional($profile)->plan_type ?? 'multilevel',
        ]);
    }

    public function embajadorNotificationsLog(Request $request): JsonResponse
    {
        $client = $this->resolveClientForCurrentUser();
        if (! $client) return response()->json(['data' => [], 'last_page' => 1]);

        $paginated = DB::table('referral_notification_logs')
            ->where('client_id', $client->id)
            ->orderByDesc('sent_at')
            ->paginate((int) $request->input('per_page', 20));

        return response()->json([
            'data'      => $paginated->items(),
            'last_page' => $paginated->lastPage(),
        ]);
    }

    public function embajadorShareMasivo(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'shared'  => count($request->input('contacts', [])),
        ]);
    }

    private function formatProspecto(object $r, int $clientId, array $followups = []): array
    {
        $followupsCount = empty($followups)
            ? DB::table('prospect_followups')->where('prospect_id', $r->id)->count()
            : count($followups);

        return [
            'id'              => $r->id,
            'name'            => $r->name,
            'phone'           => $r->phone,
            'email'           => $r->email,
            'address'         => $r->address,
            'source'          => $r->source,
            'status'          => $r->status,
            'notes'           => $r->notes,
            'converted_at'    => $r->converted_at,
            'last_contact_at' => $r->last_contact_at,
            'created_at'      => $r->created_at,
            'followups_count' => $followupsCount,
            'followups'       => array_map(fn($f) => $this->formatFollowup($f), $followups),
        ];
    }

    private function formatFollowup(object $r): array
    {
        return [
            'id'               => $r->id,
            'action'           => $r->action,
            'notes'            => $r->notes,
            'next_action_date' => $r->next_action_date ?? null,
            'created_at'       => $r->created_at,
        ];
    }

    /** Resuelve el client_id ISP a partir del usuario MegaFamilia. */
    private function resolveClientIspId(User $user): ?int
    {
        // 1. FK directa en parental_accounts
        $clientId = ParentalAccount::where('user_id', $user->id)->value('client_isp_id');
        if ($clientId) return (int) $clientId;

        // 2. clients.user_id (algunos clientes ISP tienen user creado)
        $clientId = Client::where('user_id', $user->id)->value('id');
        if ($clientId) return (int) $clientId;

        // 3. Coincidencia por email
        return ClientMainInformation::where('email', $user->email)->value('client_id');
    }
}
