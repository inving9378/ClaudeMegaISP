<?php

namespace App\Modules\Addons\Talento\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Talento\Models\TalentoColaborador;
use App\Modules\Addons\Talento\Models\TalentoFund;
use App\Modules\Addons\Talento\Models\TalentoLedgerEntry;
use App\Modules\Addons\Talento\Models\TalentoLoan;
use App\Modules\Addons\Talento\Services\AttendanceService;
use App\Modules\Addons\Talento\Services\LiquidationService;
use App\Modules\Addons\Talento\Services\OrdenTrabajoUnifiedService;
use App\Modules\Addons\Talento\Services\SignatureService;
use App\Modules\Addons\Talento\Support\Actor;
use App\Modules\Addons\Talento\Support\PayWeek;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;

/**
 * Portal Técnico Web — shell PWA del módulo Talento.
 *
 * Versión web (PWA) de la app de campo. Reutiliza la MISMA capa de servicios
 * que /talento/api (sesión Medussa en vez de token Sanctum). Este controlador
 * solo sirve el shell + assets PWA + preferencia de tema; las pantallas
 * (Mi día, OT+evidencia, Proyectos) se cablean en los sub-pasos 2–4 delegando
 * en los servicios existentes.
 */
class PortalTecnicoController extends Controller
{
    /** Cache-busting de los assets estáticos del portal (subir al cambiar app.js/portal.css). */
    private const ASSET_VER = '10';

    /** Shell del portal (SPA Quasar de una sola página con nav inferior). */
    public function index(Request $request)
    {
        $actor       = $this->currentActor($request);
        $colaborador = $actor->talento();
        $theme       = $this->currentTheme($request);

        return view('addon-talento::portal.shell', [
            'assetVer'    => self::ASSET_VER,
            'theme'       => $theme,
            'sections'    => $actor->sections(),
            'colaborador' => $colaborador ? [
                'id'     => $colaborador->id,
                // El nombre vive en el user vinculado; talento_colaboradores no tiene
                // columna de nombre. Fallback a nombre/apellido por si existieran a futuro.
                'nombre' => $colaborador->user?->name
                    ?: (trim(($colaborador->nombre ?? '') . ' ' . ($colaborador->apellido ?? '')) ?: null),
                'tipo'   => $colaborador->tipo ?? $colaborador->role_type ?? 'technician',
                'email'  => $colaborador->user?->email,
            ] : null,
        ]);
    }

    /** Manifest PWA (servido dinámico para fijar scope/start_url del portal). */
    public function manifest()
    {
        $manifest = [
            'name'             => 'Talento Meganet — Campo',
            'short_name'       => 'Talento Campo',
            'description'      => 'Portal técnico de campo: check-in, órdenes de trabajo, evidencia y proyectos.',
            'start_url'        => '/talento/portal',
            'scope'            => '/talento/portal/',
            'display'          => 'standalone',
            'orientation'      => 'portrait',
            'background_color' => '#0d9488',
            'theme_color'      => '#0d9488',
            'lang'             => 'es',
            'icons'            => [
                [
                    'src'   => '/talento-portal/icons/icon-192.png',
                    'sizes' => '192x192',
                    'type'  => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src'   => '/talento-portal/icons/icon-512.png',
                    'sizes' => '512x512',
                    'type'  => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src'   => '/talento-portal/icons/icon-maskable-512.png',
                    'sizes' => '512x512',
                    'type'  => 'image/png',
                    'purpose' => 'maskable',
                ],
            ],
        ];

        return Response::make(json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 200, [
            'Content-Type' => 'application/manifest+json; charset=utf-8',
        ]);
    }

    /**
     * Service worker del app-shell. Servido en /talento/portal/sw.js para que
     * el scope efectivo sea /talento/portal/. El outbox offline (IndexedDB)
     * llega en el Sub-paso 5; por ahora solo precachea el shell y sirve
     * network-first con fallback a caché para navegaciones sin señal.
     */
    public function serviceWorker()
    {
        $ver = self::ASSET_VER;
        $js = <<<JS
// Talento Portal Técnico — Service Worker (app-shell). Generado por PortalTecnicoController.
const CACHE = 'talento-portal-shell-v{$ver}';
const SHELL = [
  '/talento/portal',
  '/talento-portal/portal.css?v={$ver}',
  '/talento-portal/app.js?v={$ver}',
  '/plugins/quasar/js/vue.global.prod.js',
  '/plugins/quasar/js/quasar.umd.prod.js',
  '/plugins/quasar/css/quasar.prod.css',
  '/plugins/quasar/css/material-icons.css',
  '/talento-portal/icons/icon-192.png',
];

self.addEventListener('install', (e) => {
  e.waitUntil(caches.open(CACHE).then((c) => c.addAll(SHELL)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (e) => {
  e.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k)))
    ).then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (e) => {
  const req = e.request;
  if (req.method !== 'GET') return; // nunca cachear POST/PUT (check-in, evidencia)

  const url = new URL(req.url);
  if (url.origin !== self.location.origin) return; // solo mismo origen

  // No cachear llamadas de datos del portal ni el manifest/sw
  if (url.pathname.startsWith('/talento/portal/api')) return;

  // Navegaciones: network-first, fallback al shell cacheado (offline).
  if (req.mode === 'navigate') {
    e.respondWith(
      fetch(req).catch(() => caches.match('/talento/portal').then((r) => r || caches.match(req)))
    );
    return;
  }

  // Assets estáticos del shell: cache-first con revalidación de red.
  e.respondWith(
    caches.match(req).then((cached) => {
      const network = fetch(req).then((res) => {
        if (res && res.status === 200 && res.type === 'basic') {
          const copy = res.clone();
          caches.open(CACHE).then((c) => c.put(req, copy));
        }
        return res;
      }).catch(() => cached);
      return cached || network;
    })
  );
});
JS;

        return Response::make($js, 200, [
            'Content-Type'            => 'application/javascript; charset=utf-8',
            'Service-Worker-Allowed'  => '/talento/portal/',
            'Cache-Control'           => 'no-cache',
        ]);
    }

    /** Persiste el tema (light/dark) por usuario. */
    public function saveTheme(Request $request)
    {
        $data = $request->validate([
            'theme' => 'required|in:light,dark',
        ]);

        DB::table('talento_portal_preferences')->updateOrInsert(
            ['user_id' => $request->user()->id],
            ['theme' => $data['theme'], 'updated_at' => now(), 'created_at' => now()]
        );

        return response()->json(['ok' => true, 'theme' => $data['theme']]);
    }

    // ── Mi día: asistencia (check-in / checkout geocercado) ────────────────────

    /**
     * Estado de asistencia de hoy. Misma consulta que el API móvil (asistenciaHoy):
     * último registro del colaborador con check_in_at de hoy.
     */
    public function asistenciaHoy(Request $request)
    {
        $colaborador = $this->resolveColaborador($request);
        if (! $colaborador) {
            return response()->json(['message' => 'Sin perfil de colaborador activo.'], 403);
        }

        // Prioriza un turno ABIERTO (cualquier fecha): AttendanceService::checkIn bloquea
        // sobre cualquier asistencia abierta sin importar el día, así que la UI debe
        // reflejar ese mismo estado (caso "olvidó cerrar el turno de ayer").
        $abierta = DB::table('talento_attendances')
            ->where('colaborador_id', $colaborador->id)
            ->whereNull('check_out_at')
            ->orderByDesc('id')
            ->first();

        // Si no hay turno abierto, muestra el último registro de HOY (turno ya cerrado hoy).
        $registro = $abierta ?: DB::table('talento_attendances')
            ->where('colaborador_id', $colaborador->id)
            ->whereDate('check_in_at', now()->toDateString())
            ->orderByDesc('id')
            ->first();

        if (! $registro) {
            return response()->json(['check_in_at' => null, 'check_out_at' => null]);
        }

        $staleDay = $abierta && substr((string) $registro->check_in_at, 0, 10) !== now()->toDateString();

        return response()->json([
            'id'           => $registro->id,
            'check_in_at'  => $registro->check_in_at,
            'check_out_at' => $registro->check_out_at,
            'geocerca'     => $this->siteName($registro->check_in_site_id),
            'flagged'      => (bool) $registro->check_in_flagged,
            'flag_reason'  => $registro->check_in_flag_reason,
            'stale_day'    => $staleDay,
        ]);
    }

    /**
     * Check-in geocercado. Delega en AttendanceService::checkIn (validación de
     * geocerca en servidor + flag por precisión > 100 m / fuera de cerca, sin
     * bloquear). El navegador solo manda lat/lng/accuracy_m.
     */
    public function checkin(Request $request)
    {
        $data = $request->validate([
            'lat'      => 'required|numeric',
            'lng'      => 'required|numeric',
            'accuracy' => 'nullable|numeric',
            'wo_id'    => 'nullable|integer',
        ]);

        $colaborador = $this->resolveColaborador($request);
        if (! $colaborador) {
            return response()->json(['message' => 'Sin perfil de colaborador activo.'], 403);
        }

        $res = app(AttendanceService::class)->checkIn(
            $colaborador->id,
            (float) $data['lat'],
            (float) $data['lng'],
            isset($data['accuracy']) ? (float) $data['accuracy'] : null,
            $data['wo_id'] ?? null
        );

        $att = $res['attendance'] ?? null;
        if ($att && ! $att->relationLoaded('site')) {
            $att->load('site');
        }

        return response()->json([
            'status'      => $res['status'],                       // ok | flagged | already_open
            'check_in_at' => $att?->check_in_at,
            'geocerca'    => $att?->site?->name,
            'flagged'     => (bool) ($att?->check_in_flagged),
            'flag_reason' => $att?->check_in_flag_reason,
        ], $res['status'] === 'already_open' ? 200 : 201);
    }

    /** Checkout del registro abierto de hoy. Delega en AttendanceService::checkOut. */
    public function checkout(Request $request)
    {
        $data = $request->validate([
            'lat' => 'nullable|numeric',
            'lng' => 'nullable|numeric',
        ]);

        $colaborador = $this->resolveColaborador($request);
        if (! $colaborador) {
            return response()->json(['message' => 'Sin perfil de colaborador activo.'], 403);
        }

        $res = app(AttendanceService::class)->checkOut(
            $colaborador->id,
            isset($data['lat']) ? (float) $data['lat'] : null,
            isset($data['lng']) ? (float) $data['lng'] : null
        );

        if ($res['status'] === 'no_open_attendance') {
            return response()->json(['message' => 'Sin check-in activo para cerrar.'], 422);
        }

        return response()->json([
            'status'       => 'ok',
            'check_out_at' => $res['attendance']?->check_out_at,
        ]);
    }

    // ── Mi día: OTs del día ────────────────────────────────────────────────────

    /**
     * OTs del día del colaborador. Delega en OrdenTrabajoUnifiedService::summaryForHoy,
     * que mezcla talento_work_orders + tasks. Cada tarjeta trae el discriminador de
     * origen ('origen' => work_order|task) para que el detalle (Sub-paso 3) opere
     * contra la tabla correcta y no dé 404.
     */
    public function otsHoy(Request $request)
    {
        $colaborador = $this->resolveColaborador($request);
        if (! $colaborador) {
            return response()->json(['message' => 'Sin perfil de colaborador activo.'], 403);
        }

        $ots = app(OrdenTrabajoUnifiedService::class)->summaryForHoy($colaborador->id);

        return response()->json(['data' => $ots]);
    }

    // ── Detalle de OT ───────────────────────────────────────────────────────

    /**
     * Detalle de una OT del colaborador (por {origen, id}). Delega en
     * OrdenTrabajoUnifiedService::detail (que auto-resuelve WO/task y ya scopea por
     * colaborador). Devuelve la OT, la evidencia existente, el checklist de evidencia
     * requerida (con flags de dBm/firma y estado subido) y el estado de firmas.
     */
    public function otDetalle(Request $request, string $origen, int $id)
    {
        $colaborador = $this->resolveColaborador($request);
        if (! $colaborador) {
            return response()->json(['message' => 'Sin perfil de colaborador activo.'], 403);
        }

        $detalle = app(OrdenTrabajoUnifiedService::class)->detail($id, $colaborador->id);
        if (! $detalle) {
            return response()->json(['message' => 'OT no encontrada.'], 404);
        }

        $origenReal   = $detalle['origen']; // work_order | task (autoridad del servicio)
        $subidos      = collect($detalle['evidencias'])->pluck('type_id')->map(fn ($v) => (int) $v)->unique();
        $firmas       = app(SignatureService::class)->signerTypesFor($origenReal, $id);
        $hayFirmaClie = in_array('client', $firmas, true);

        // Handoff a activaciones (E2): existe fila en talento_work_order_activations.
        $handoff = DB::table('talento_work_order_activations')
            ->where($origenReal === 'task' ? 'tarea_id' : 'work_order_id', $id)
            ->exists();

        // Flags por tipo de evidencia requerido (dBm / firma / justificación / varias).
        $reqIds = collect($detalle['evidencias_requeridas'])->pluck('id')->map(fn ($v) => (int) $v)->all();
        $flags  = DB::table('talento_evidence_types')
            ->whereIn('id', $reqIds ?: [0])
            ->get(['id', 'es_lectura_dbm', 'es_firma', 'permite_varias', 'requiere_justificacion'])
            ->keyBy('id');

        $checklist = collect($detalle['evidencias_requeridas'])->map(function ($r) use ($subidos, $flags, $hayFirmaClie) {
            $id  = (int) $r->id;
            $f   = $flags[$id] ?? null;
            $esFirma = (bool) ($f->es_firma ?? $r->es_firma ?? false);
            // Un requisito de firma se satisface con la firma del cliente (signature-pad)
            // o con media de ese tipo; el resto, sólo con media subida.
            $subido = $subidos->contains($id) || ($esFirma && $hayFirmaClie);
            return [
                'evidence_type_id'      => $id,
                'nombre'                => $r->nombre,
                'es_firma'              => $esFirma,
                'es_lectura_dbm'        => (bool) ($f->es_lectura_dbm ?? false),
                'permite_varias'        => (bool) ($f->permite_varias ?? false),
                'requiere_justificacion'=> (bool) ($f->requiere_justificacion ?? false),
                'subido'                => $subido,
            ];
        })->values();

        return response()->json([
            'ot' => [
                'id'           => $detalle['id'],
                'folio'        => $detalle['folio'],
                'origen'       => $origenReal,
                'tipo'         => $detalle['tipo'],
                'status'       => $detalle['status'],
                'cliente'      => $detalle['cliente'],
                'direccion'    => $detalle['direccion'],
                'telefono'     => $detalle['telefono'],
                'notas'        => $detalle['notas'] ?? null,
                'nota_tecnico' => $detalle['nota_tecnico'] ?? null,
                'scheduled_at' => $detalle['scheduled_at'] ?? null,
            ],
            'evidencias' => $detalle['evidencias'],
            'checklist'  => $checklist,
            'firmas'     => [
                'technician' => in_array('technician', $firmas, true),
                'client'     => $hayFirmaClie,
            ],
            'handoff'    => $handoff,
        ]);
    }

    /**
     * E2 — Aceptar OT y handoff a activaciones (diferido).
     *
     * NO invoca FieldFlowService::accept (state-machine WO-only). Con la OT completada
     * y AMBAS firmas (técnico + cliente) verificadas por tarea_id/work_order_id, registra
     * la fila en talento_work_order_activations como artefacto de handoff y deja la OT
     * "en espera de activación". NO dispara confirmActivation (le toca a activaciones).
     * Idempotente: no duplica la fila de activación.
     */
    public function aceptarOt(Request $request, string $origen, int $id)
    {
        $colaborador = $this->resolveColaborador($request);
        if (! $colaborador) {
            return response()->json(['message' => 'Sin perfil de colaborador activo.'], 403);
        }

        $detalle = app(OrdenTrabajoUnifiedService::class)->detail($id, $colaborador->id);
        if (! $detalle) {
            return response()->json(['message' => 'OT no encontrada.'], 404);
        }
        if ($detalle['status'] !== 'completed') {
            return response()->json(['message' => 'La OT debe estar completada para aceptar.', 'status_code' => 422], 422);
        }

        $origenReal = $detalle['origen'];
        $firmas     = app(SignatureService::class)->signerTypesFor($origenReal, $id);
        if (! in_array('technician', $firmas, true)) {
            return response()->json(['message' => 'Falta la firma del técnico.'], 422);
        }
        if (! in_array('client', $firmas, true)) {
            return response()->json(['message' => 'Falta la firma del cliente.'], 422);
        }

        $fkCol = $origenReal === 'task' ? 'tarea_id' : 'work_order_id';
        $exists = DB::table('talento_work_order_activations')->where($fkCol, $id)->exists();
        if (! $exists) {
            DB::table('talento_work_order_activations')->insert([
                $fkCol         => $id,
                'requested_at' => now(),
                'created_at'   => now(),
            ]);
        }

        return response()->json([
            'success' => true,
            'handoff' => true,
            'message' => 'OT completada y firmada — en espera de activación.',
        ]);
    }

    // ── Iniciar / Completar ─────────────────────────────────────────────────

    /** Inicia la OT (pending → in_progress). Delega en el servicio. */
    public function iniciarOt(Request $request, string $origen, int $id)
    {
        $colaborador = $this->resolveColaborador($request);
        if (! $colaborador) {
            return response()->json(['message' => 'Sin perfil de colaborador activo.'], 403);
        }
        $res = app(OrdenTrabajoUnifiedService::class)->iniciar($id, $colaborador->id);
        return response()->json($res, $res['success'] ? 200 : ($res['status_code'] ?? 422));
    }

    /**
     * Completa la OT. El servicio gatea evidencia obligatoria + umbral dBm; si faltan,
     * responde 422 con el mensaje y la lista de faltantes (no cierra la OT).
     */
    public function completarOt(Request $request, string $origen, int $id)
    {
        $data = $request->validate(['nota_tecnico' => 'nullable|string|max:2000']);
        $colaborador = $this->resolveColaborador($request);
        if (! $colaborador) {
            return response()->json(['message' => 'Sin perfil de colaborador activo.'], 403);
        }
        $res = app(OrdenTrabajoUnifiedService::class)->completar($id, $colaborador->id, $data);
        return response()->json($res, $res['success'] ? 200 : ($res['status_code'] ?? 422));
    }

    // ── Evidencia anti-fraude ─────────────────────────────────────────────────

    /**
     * Sube evidencia capturada en vivo. El servidor QUEMA la marca de agua (GD:
     * técnico + timestamp de servidor + coords + precisión) sobre la imagen antes de
     * persistirla, sella server_captured_at y delega en subirEvidencia con
     * source='portal_web'. El GPS llega como campos del form (no EXIF). El watermark
     * del cliente es sólo preview; la evidencia sale watermarkeada del servidor.
     */
    public function subirEvidenciaOt(Request $request, string $origen, int $id)
    {
        $data = $request->validate([
            'foto'             => 'required|image|max:8192',
            'evidence_type_id' => 'required|integer',
            'lat'              => 'required|numeric|between:-90,90',
            'lng'              => 'required|numeric|between:-180,180',
            'accuracy'         => 'nullable|numeric|min:0',
            'potencia_dbm'     => 'nullable|numeric|between:-60,10',
            'justificacion'    => 'nullable|string|max:1000',
            'client_uuid'      => 'nullable|string|max:36',
            'is_mock_location' => 'nullable|boolean',
        ]);

        $colaborador = $this->resolveColaborador($request);
        if (! $colaborador) {
            return response()->json(['message' => 'Sin perfil de colaborador activo.'], 403);
        }

        $file = $request->file('foto');

        // Marca de agua autoritativa del servidor (GD). No bloquea si la imagen no es
        // decodificable; el resto del anti-fraude (cámara viva, GPS de form, timestamp
        // de servidor) sigue vigente.
        $tecnico = $colaborador->user?->name ?: ('Colaborador #' . $colaborador->id);
        $acc     = isset($data['accuracy']) ? round((float) $data['accuracy']) : null;
        $this->quemarWatermark($file->getRealPath(), [
            'Talento Meganet - ' . $tecnico,
            now()->format('Y-m-d H:i:s') . ' (servidor)',
            'GPS ' . round((float) $data['lat'], 6) . ', ' . round((float) $data['lng'], 6)
                . ($acc !== null ? ' +-' . $acc . 'm' : ''),
        ]);

        $payload = [
            'evidence_type_id' => (int) $data['evidence_type_id'],
            'lat'              => (float) $data['lat'],
            'lng'              => (float) $data['lng'],
            'accuracy'         => $data['accuracy'] ?? null,
            'potencia_dbm'     => $data['potencia_dbm'] ?? null,
            'justificacion'    => $data['justificacion'] ?? null,
            'client_uuid'      => $data['client_uuid'] ?? null,
            'is_mock_location' => (bool) ($data['is_mock_location'] ?? false),
            'source'           => 'portal_web',
        ];

        $res = app(OrdenTrabajoUnifiedService::class)
            ->subirEvidencia($id, $colaborador->id, $payload, $file, (int) $request->user()->id);

        return response()->json($res, $res['success'] ? 201 : ($res['status_code'] ?? 422));
    }

    // ── Firmas (técnico / cliente) ────────────────────────────────────────────

    /**
     * Guarda la firma (técnico o cliente) por {origen, id} vía SignatureService, que
     * escribe en talento_work_order_signatures por tarea_id/work_order_id. El origen
     * autoritativo lo resuelve el servicio (findLight, que además scopea por colaborador
     * → ownership). Re-firmar sobreescribe (UNIQUE por signer_type).
     */
    public function guardarFirma(Request $request, string $origen, int $id)
    {
        $data = $request->validate([
            'signer_type'    => 'required|in:technician,client',
            'signature_data' => 'required|string',
            'lat'            => 'nullable|numeric|between:-90,90',
            'lng'            => 'nullable|numeric|between:-180,180',
        ]);

        $colaborador = $this->resolveColaborador($request);
        if (! $colaborador) {
            return response()->json(['message' => 'Sin perfil de colaborador activo.'], 403);
        }

        $light = app(OrdenTrabajoUnifiedService::class)->findLight($id, $colaborador->id);
        if (! $light) {
            return response()->json(['message' => 'OT no encontrada.'], 404);
        }
        $origenReal = $light->is_task ? 'task' : 'work_order';

        app(SignatureService::class)->store(
            $origenReal,
            $id,
            $data['signer_type'],
            $data['signature_data'],
            isset($data['lat']) ? (float) $data['lat'] : null,
            isset($data['lng']) ? (float) $data['lng'] : null,
            null,
            (int) $request->user()->id
        );

        return response()->json(['ok' => true, 'signer_type' => $data['signer_type']], 201);
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    /** Quema una barra semitransparente con líneas de texto al pie de la imagen (GD). */
    private function quemarWatermark(string $path, array $lineas): void
    {
        if (! function_exists('imagecreatefromjpeg')) {
            return;
        }
        $img = @imagecreatefromjpeg($path) ?: @imagecreatefrompng($path);
        if (! $img) {
            return; // no bloquea el flujo si no se puede decodificar
        }

        $w = imagesx($img);
        $h = imagesy($img);
        $lineH = 18;
        $barH  = $lineH * count($lineas) + 12;

        $overlay = imagecreatetruecolor($w, $barH);
        $black   = imagecolorallocate($overlay, 0, 0, 0);
        imagefilledrectangle($overlay, 0, 0, $w, $barH, $black);
        imagecopymerge($img, $overlay, 0, max(0, $h - $barH), 0, 0, $w, $barH, 50);

        $white = imagecolorallocate($img, 255, 255, 255);
        $y = $h - $barH + 6;
        foreach ($lineas as $ln) {
            // La fuente built-in es Latin-1; transliteramos acentos para no ensuciar.
            $txt = @iconv('UTF-8', 'ASCII//TRANSLIT', (string) $ln) ?: (string) $ln;
            imagestring($img, 3, 8, $y, $txt, $white);
            $y += $lineH;
        }

        imagejpeg($img, $path, 88);
        imagedestroy($img);
        imagedestroy($overlay);
    }

    private function siteName(?int $siteId): ?string
    {
        if (! $siteId) {
            return null;
        }
        return DB::table('talento_work_sites')->where('id', $siteId)->value('name');
    }

    // ── "Mi dinero" (Bloque 2) — wrappers GET self-scoped, SOLO LECTURA ─────────────────
    // Resuelven el colaborador por la sesión (auth), derivan la ventana con PayWeek (la MISMA
    // con la que se le paga) y delegan en el backend existente. Cero cálculo/agregación en el
    // front: los subtotales, el neto y "pagado" se calculan/agrupan aquí (server-side).

    /** Ventana PayWeek del período pedido (?period_start=YYYY-MM-DD); default = semana vigente. */
    private function dineroWindow(Request $request): array
    {
        $ps = $request->query('period_start');
        return $ps
            ? PayWeek::boundsFor(Carbon::parse($ps)->copy()->addDay())
            : PayWeek::current();
    }

    /** 1. Cuenta corriente: ledger del período AGRUPADO POR CONCEPTO + neto (server-side). */
    public function dineroCuenta(Request $request)
    {
        $col = $this->resolveColaborador($request);
        if (! $col) return response()->json(['error' => 'Sin perfil de colaborador activo.'], 403);
        $w = $this->dineroWindow($request);

        $rows = TalentoLedgerEntry::where('colaborador_id', $col->id)
            ->where('period_start', $w['period_start'])
            ->where('period_end', $w['period_end'])
            ->selectRaw('concept, type, SUM(amount) AS total, COUNT(*) AS n')
            ->groupBy('concept', 'type')
            ->get();

        $credito = round((float) $rows->where('type', 'credit')->sum('total'), 2);
        $debito  = round((float) $rows->where('type', 'debit')->sum('total'), 2);

        return response()->json([
            'period_start'  => $w['period_start'],
            'period_end'    => $w['period_end'],
            'regime'        => $w['regime'],
            'conceptos'     => $rows->map(fn ($r) => [
                'concepto' => $r->concept,
                'tipo'     => $r->type,               // credit | debit
                'subtotal' => round((float) $r->total, 2),
                'n'        => (int) $r->n,
            ])->values(),
            'total_credito' => $credito,
            'total_debito'  => $debito,
            'neto'          => round($credito - $debito, 2),
        ]);
    }

    /** 2. Desglose: cuota, valor por unidad, unidades, sobreproducción (reusa LiquidationService::breakdown). */
    public function dineroDesglose(Request $request)
    {
        $col = $this->resolveColaborador($request);
        if (! $col) return response()->json(['error' => 'Sin perfil de colaborador activo.'], 403);
        $w = $this->dineroWindow($request);

        return response()->json(app(LiquidationService::class)->breakdown($col->id, $w));
    }

    /** 3. Fondo de ahorro: acumulado, objetivo, aporte semanal, authorized, progreso (reusa modelo/accessors). */
    public function dineroFondo(Request $request)
    {
        $col = $this->resolveColaborador($request);
        if (! $col) return response()->json(['error' => 'Sin perfil de colaborador activo.'], 403);
        $w = $this->dineroWindow($request);

        $fondos = TalentoFund::where('colaborador_id', $col->id)->get()->map(fn ($f) => [
            'purpose'          => $f->purpose,
            'accumulated'      => round((float) $f->accumulated, 2),
            'target'           => round((float) $f->target_amount, 2),
            'weekly_deduction' => round((float) $f->weekly_deduction, 2),
            'authorized'       => (bool) $f->authorized,
            'status'           => $f->status,
            'progress_pct'     => $f->progressPct(),
            'weeks_remaining'  => $f->weeksRemaining(),
        ])->values();

        // Aporte del período (asientos fund_contribution del ledger en esta ventana).
        $aportePeriodo = round((float) TalentoLedgerEntry::where('colaborador_id', $col->id)
            ->where('concept', 'fund_contribution')
            ->where('period_start', $w['period_start'])->where('period_end', $w['period_end'])
            ->sum('amount'), 2);

        return response()->json([
            'period_start'   => $w['period_start'],
            'period_end'     => $w['period_end'],
            'fondos'         => $fondos,
            'aporte_periodo' => $aportePeriodo,
            'naturaleza'     => 'ahorro recuperable, no penalización',
        ]);
    }

    /** 4. Préstamos (SOLO LECTURA): original, saldo, pagado (server-side), repago semanal. */
    public function dineroPrestamos(Request $request)
    {
        $col = $this->resolveColaborador($request);
        if (! $col) return response()->json(['error' => 'Sin perfil de colaborador activo.'], 403);
        $w = $this->dineroWindow($request);

        $prestamos = TalentoLoan::where('colaborador_id', $col->id)->get()->map(fn ($l) => [
            'reason'           => $l->reason,
            'amount'           => round((float) $l->amount, 2),                          // original
            'balance'          => round((float) $l->balance, 2),                         // saldo pendiente
            'paid'             => round((float) $l->amount - (float) $l->balance, 2),    // PAGADO (server-side)
            'repayment_weekly' => round((float) $l->repayment_weekly, 2),
            'status'           => $l->status,
            'weeks_remaining'  => $l->weeksRemaining(),
        ])->values();

        // Repago del período (asientos loan_repayment del ledger en esta ventana).
        $repagoPeriodo = round((float) TalentoLedgerEntry::where('colaborador_id', $col->id)
            ->where('concept', 'loan_repayment')
            ->where('period_start', $w['period_start'])->where('period_end', $w['period_end'])
            ->sum('amount'), 2);

        return response()->json([
            'period_start'   => $w['period_start'],
            'period_end'     => $w['period_end'],
            'prestamos'      => $prestamos,
            'repago_periodo' => $repagoPeriodo,
            'solo_consulta'  => true,
        ]);
    }

    /** Actor del portal — resuelto y cacheado UNA vez por request (punto único de identidad). */
    private ?Actor $portalActor = null;

    private function currentActor(Request $request): Actor
    {
        return $this->portalActor ??= Actor::for($request->user());
    }

    /**
     * Colaborador Talento del usuario autenticado. Repuntado al Actor (DRY): los ~16 call-sites de
     * los Bloques 1-2 siguen usando resolveColaborador() sin cambios; el resolver es el Actor.
     */
    private function resolveColaborador(Request $request): ?TalentoColaborador
    {
        return $this->currentActor($request)->talento();
    }

    private function currentTheme(Request $request): string
    {
        $theme = DB::table('talento_portal_preferences')
            ->where('user_id', $request->user()->id)
            ->value('theme');

        return in_array($theme, ['light', 'dark'], true) ? $theme : 'light';
    }
}
