<?php

namespace App\Modules\Addons\MegaFamilia\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Client;
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
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * API mobile (padres + dispositivos hijos). Todas las rutas excepto login
 * usan `auth:sanctum`. El modelo `App\Models\User` ya tiene
 * `Laravel\Sanctum\HasApiTokens` (verificado), así que `$user->createToken()`
 * funciona out of the box.
 */
class ApiController extends Controller
{
    // ---- AUTH -------------------------------------------------------------

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'device_name' => 'sometimes|string',
        ]);

        $user = User::where('email', $data['email'])->first();
        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return response()->json(['success' => false, 'error' => 'Credenciales inválidas'], 401);
        }

        if (! method_exists($user, 'createToken')) {
            return response()->json([
                'success' => false,
                'error' => 'Sanctum no está habilitado en el modelo User (falta HasApiTokens trait).',
            ], 500);
        }

        $token = $user->createToken($data['device_name'] ?? 'mobile')->plainTextToken;

        $role = strtolower((string) ($user->getRoleNames()->first() ?? ''));

        return response()->json([
            'success' => true,
            'token' => $token,
            'role' => $role,
            'user' => array_merge(
                $user->only('id', 'name', 'email'),
                ['role' => $role]
            ),
        ]);
    }

    // ---- CLIENT DASHBOARD (mobile home) ----------------------------------

    /**
     * Resumen del servicio ISP del usuario autenticado. Si el usuario no
     * tiene cliente vinculado, regresa un placeholder en estado "sin servicio"
     * en lugar de 404 — el dashboard móvil necesita algo que renderizar.
     */
    public function servicio(): JsonResponse
    {
        $userId = Auth::id();
        $client = Client::where('user_id', $userId)
            ->with(['internet_service.internet', 'client_main_information'])
            ->first();

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

        return response()->json([
            'plan_name' => $internet->service_name ?? $internet->title ?? 'Plan ISP',
            'speed' => $internet->download_speed ? ($internet->download_speed . ' Mbps') : '—',
            'estado' => $client->fecha_suspension ? 'suspendido' : 'activo',
            'next_payment_date' => $client->fecha_pago,
            'consumo_gb' => null,
            'consumo_limite' => null,
            'contract_number' => 'MGI-' . str_pad((string) $client->id, 6, '0', STR_PAD_LEFT),
            'address' => trim((string) ($info->address ?? '')) ?: null,
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
        $t->topic = $data['subject'];
        $t->estado = 'Nuevo';
        $t->group = 'Cualquier';
        $t->type = 'Pregunta';
        $t->source = $data['category'] ?? null;
        $t->reporter = $user->name;
        $t->reporter_id = $user->id;
        $t->reporter_type = User::class;
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
        $client = Client::where('user_id', $user->id)
            ->with(['client_main_information', 'internet_service.internet'])
            ->first();

        if (! $client) {
            return response()->json($this->demoAccount($user));
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
            'speed' => $internet->download_speed ? ($internet->download_speed . ' Mbps') : '—',
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
        $client = Client::where('user_id', $user->id)
            ->with('client_main_information')
            ->first();
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

    // ---- ACCOUNT / PROFILES (parental) -----------------------------------

    public function parentalAccount(): JsonResponse
    {
        $account = $this->requireAccount();
        return response()->json($account->load(['plan', 'profiles', 'license']));
    }

    public function profiles(): JsonResponse
    {
        $account = $this->requireAccount();
        return response()->json($account->profiles()->with('devices')->get());
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

    /**
     * Liga un dispositivo a un perfil usando un token de un solo uso
     * (generado al escanear el QR en el dispositivo hijo).
     */
    public function linkDevice(Request $request): JsonResponse
    {
        $data = $request->validate([
            'link_token' => 'required|string|max:64',
            'name' => 'sometimes|string',
            'model' => 'sometimes|string',
            'os' => 'sometimes|in:android,ios',
            'os_version' => 'sometimes|string',
            'app_version' => 'sometimes|string',
            'fcm_token' => 'sometimes|string',
        ]);

        $device = ParentalDevice::where('link_token', $data['link_token'])->first();
        if (! $device) {
            return response()->json(['success' => false, 'error' => 'Token de vinculación inválido'], 404);
        }
        if ($device->linked_at) {
            return response()->json(['success' => false, 'error' => 'Token ya usado'], 409);
        }

        $device->update(array_merge($data, [
            'linked_at' => now(),
            'last_seen_at' => now(),
            'status' => 'online',
        ]));

        return response()->json(['success' => true, 'device' => $device]);
    }

    public function deviceRules(int $id): JsonResponse
    {
        $device = ParentalDevice::findOrFail($id);
        $rules = ParentalRule::firstOrCreate(['profile_id' => $device->profile_id]);
        return response()->json($rules);
    }

    public function updateDeviceRules(Request $request, int $id): JsonResponse
    {
        $device = ParentalDevice::findOrFail($id);
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
        $task = ParentalTask::findOrFail($id);
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
        $r = ParentalRequest::findOrFail($id);
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

    // ---- helpers ---------------------------------------------------------

    private function requireAccount(): ParentalAccount
    {
        $account = ParentalAccount::where('client_id', Auth::id())->first();
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
}
