<?php

namespace App\Modules\Addons\PortalCliente\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Test anti-IDOR para el Portal Cliente.
 * Verifica que el cliente A NO puede leer datos del cliente B.
 * Solo lectura, sin modificar datos reales.
 *
 * Uso: php artisan portal:test-idor
 */
class AntiIdorTestCommand extends Command
{
    protected $signature = 'portal:test-idor';
    protected $description = 'Verifica aislamiento multi-tenant del portal (anti-IDOR). Sin efectos persistentes (las pruebas de escritura se revierten).';

    public function handle(): int
    {
        $this->info('🔒 Test anti-IDOR — Portal Cliente');
        $this->newLine();

        $passed  = 0;
        $failed  = 0;
        $results = [];

        // ── Seleccionar dos clientes reales con datos ──────────────────────
        $clientes = DB::table('client_main_information as m')
            ->join('clients as c', 'c.id', 'm.client_id')
            ->whereNotNull('m.portal_password')
            ->limit(2)
            ->pluck('m.client_id')
            ->toArray();

        // Login del portal usa `password` (plano), no portal_password: si hay pocos con portal_password, cualquier par de CMI sirve para el aislamiento.
        if (count($clientes) < 2) {
            $clientes = DB::table('client_main_information as m')
                ->join('clients as c', 'c.id', 'm.client_id')
                ->limit(2)
                ->pluck('m.client_id')
                ->toArray();
        }

        if (count($clientes) < 2) {
            $this->warn('No hay suficientes clientes con portal_password para el test. Necesitas al menos 2.');
            return Command::FAILURE;
        }

        [$clientA, $clientB] = $clientes;
        $this->line("  Cliente A: #{$clientA}   Cliente B: #{$clientB}");
        $this->newLine();

        // ── TEST 1: Facturas ───────────────────────────────────────────────
        $facturaBCliente = DB::table('client_invoices')
            ->where('client_id', $clientB)
            ->first(['id', 'client_id']);

        if ($facturaBCliente) {
            // Simular que A pide una factura de B
            $fetched = DB::table('client_invoices')
                ->where('id', $facturaBCliente->id)
                ->where('client_id', $clientA) // scoping correcto
                ->first();

            $ok = $fetched === null; // A NO debe ver la factura de B
            $this->testResult('Facturas: A no puede ver factura de B', $ok, $results, $passed, $failed);
        } else {
            $this->line('  [SKIP] Sin facturas en cliente B para probar.');
        }

        // ── TEST 2: Pagos ──────────────────────────────────────────────────
        $pagoB = DB::table('payments')
            ->where('paymentable_id', $clientB)
            ->whereIn('paymentable_type', ['App\\Models\\Client', 'App\\Modules\\Core\\Clientes\\Models\\Client'])
            ->first(['id', 'paymentable_id']);

        if ($pagoB) {
            // A consulta con su ID — no debe aparecer el pago de B
            $fetched = DB::table('payments')
                ->where('paymentable_id', $clientA) // scoping correcto
                ->where('id', $pagoB->id)
                ->first();

            $ok = $fetched === null;
            $this->testResult('Pagos: A no puede ver pago de B', $ok, $results, $passed, $failed);
        } else {
            $this->line('  [SKIP] Sin pagos en cliente B para probar.');
        }

        // ── TEST 3: Tickets ────────────────────────────────────────────────
        $ticketB = DB::table('tickets')
            ->where('customer_lead', $clientB)
            ->first(['id', 'customer_lead']);

        if ($ticketB) {
            $fetched = DB::table('tickets')
                ->where('id', $ticketB->id)
                ->where('customer_lead', $clientA) // scoping correcto
                ->first();

            $ok = $fetched === null;
            $this->testResult('Tickets: A no puede ver ticket de B', $ok, $results, $passed, $failed);
        } else {
            $this->line('  [SKIP] Sin tickets en cliente B para probar.');
        }

        // ── TEST 4: CLABE ──────────────────────────────────────────────────
        $clabeB = DB::table('payment_clabes')
            ->where('client_id', $clientB)
            ->first(['id', 'client_id']);

        if ($clabeB) {
            $fetched = DB::table('payment_clabes')
                ->where('id', $clabeB->id)
                ->where('client_id', $clientA) // scoping correcto
                ->first();

            $ok = $fetched === null;
            $this->testResult('CLABE: A no puede ver CLABE de B', $ok, $results, $passed, $failed);
        } else {
            $this->line('  [SKIP] Sin CLABE en cliente B para probar.');
        }

        // ── TEST 5: Consumo ────────────────────────────────────────────────
        $consumoB = DB::table('internet_consumptions')
            ->where('client_name', 'Meganet__' . $clientB)
            ->first(['id', 'client_name']);

        if ($consumoB) {
            $fetched = DB::table('internet_consumptions')
                ->where('id', $consumoB->id)
                ->where('client_name', 'Meganet__' . $clientA) // scoping correcto
                ->first();

            $ok = $fetched === null;
            $this->testResult('Consumo: A no puede ver consumo de B', $ok, $results, $passed, $failed);
        } else {
            $this->line('  [SKIP] Sin consumo en cliente B para probar.');
        }

        // ── TEST 6: MegaFamilia parental_accounts ──────────────────────────
        $userId_B_user = DB::table('client_main_information')->where('client_id', $clientB)->value('user');
        $userId_B = $userId_B_user ? DB::table('users')->where('login_user', $userId_B_user)->value('id') : null;
        $userId_A_user = DB::table('client_main_information')->where('client_id', $clientA)->value('user');
        $userId_A = $userId_A_user ? DB::table('users')->where('login_user', $userId_A_user)->value('id') : null;

        $paB = $userId_B ? DB::table('parental_accounts')->where('user_id', $userId_B)->first() : null;

        if ($paB && $userId_A) {
            // A consulta con su user_id — no debe ver cuenta de B
            $fetched = DB::table('parental_accounts')
                ->where('id', $paB->id)
                ->where('user_id', $userId_A)
                ->first();

            $ok = $fetched === null;
            $this->testResult('MegaFamilia: A no puede ver cuenta parental de B', $ok, $results, $passed, $failed);
        } else {
            $this->line('  [SKIP] Sin parental_account en cliente B para probar.');
        }

        // ── TEST: Flotas (#621) — unidades, documentos, mantenimientos, combustible ──
        // Datos efímeros de B dentro de una transacción que SIEMPRE se revierte.
        DB::beginTransaction();
        try {
            $vB = \App\Modules\Addons\Flotas\Models\FleetVehicle::create(['client_id' => $clientB, 'brand' => 'IDOR', 'model' => 'Test']);
            $dB = \App\Modules\Addons\Flotas\Models\FleetDocument::create(['vehicle_id' => $vB->id, 'document_type' => 'other']);
            $mB = \App\Modules\Addons\Flotas\Models\FleetMaintenance::create(['vehicle_id' => $vB->id, 'type' => 'preventive', 'service_date' => now()->toDateString()]);
            $fB = \App\Modules\Addons\Flotas\Models\FleetFuelLog::create(['vehicle_id' => $vB->id, 'refuel_date' => now()->toDateString(), 'liters' => 1, 'cost' => 1]);

            $this->testResult('Flotas: A no puede ver/editar/borrar unidad de B',
                \App\Modules\Addons\Flotas\Models\FleetVehicle::forClient($clientA)->find($vB->id) === null, $results, $passed, $failed);
            $this->testResult('Flotas: A no puede ver documento (ni su archivo) de B',
                \App\Modules\Addons\Flotas\Models\FleetDocument::forClient($clientA)->find($dB->id) === null, $results, $passed, $failed);
            $this->testResult('Flotas: A no puede ver mantenimiento de B',
                \App\Modules\Addons\Flotas\Models\FleetMaintenance::forClient($clientA)->find($mB->id) === null, $results, $passed, $failed);
            $this->testResult('Flotas: A no puede ver combustible de B',
                \App\Modules\Addons\Flotas\Models\FleetFuelLog::forClient($clientA)->find($fB->id) === null, $results, $passed, $failed);
            $this->testResult('Flotas: B sí ve lo suyo (control positivo)',
                \App\Modules\Addons\Flotas\Models\FleetVehicle::forClient($clientB)->find($vB->id) !== null, $results, $passed, $failed);

            // GPS (#628): la unidad de B no se alcanza con forClient(A) → no se puede vincular/quitar GPS ni ver su IMEI.
            $dev = \App\Modules\Addons\Flotas\Models\FleetDevice::create(['vehicle_id' => $vB->id, 'imei' => 'IDOR' . random_int(100000, 999999), 'brand' => 'ruptela', 'status' => 'pending_first_connection']);
            $this->testResult('Flotas GPS: A no alcanza la unidad de B para vincular/quitar GPS',
                \App\Modules\Addons\Flotas\Models\FleetVehicle::forClient($clientA)->find($vB->id) === null, $results, $passed, $failed);
            $this->testResult('Flotas GPS: A no ve el IMEI/dispositivo de B',
                \App\Modules\Addons\Flotas\Models\FleetDevice::forClient($clientA)->find($dev->id) === null
                && \App\Modules\Addons\Flotas\Models\FleetDevice::forClient($clientB)->find($dev->id) !== null, $results, $passed, $failed);

            // Posiciones, mapa y recorridos (#636): la unidad de B y la interna de Meganet no aparecen para A.
            $vB->update(['has_gps' => true]);
            $vInt = \App\Modules\Addons\Flotas\Models\FleetVehicle::create(['client_id' => null, 'brand' => 'IDOR', 'model' => 'Interna', 'has_gps' => true]);
            foreach ([$vB, $vInt] as $vx) {
                \App\Modules\Addons\Flotas\Models\FleetPosition::create([
                    'vehicle_id' => $vx->id, 'device_id' => $dev->id, 'lat' => 19.43, 'lng' => -99.13, 'speed' => 10,
                    'recorded_at' => now(), 'received_at' => now(),
                ]);
            }
            $posSvc = app(\App\Modules\Addons\Flotas\Services\FleetPositionService::class);
            $idsA   = $posSvc->getCurrentPositions($clientA)->pluck('vehicle_id');
            $this->testResult('Flotas mapa: A no ve la posición de la unidad de B',
                ! $idsA->contains($vB->id), $results, $passed, $failed);
            $this->testResult('Flotas mapa: A no ve unidades internas de Meganet',
                ! $idsA->contains($vInt->id), $results, $passed, $failed);
            $this->testResult('Flotas mapa: B sí ve su propia posición',
                $posSvc->getCurrentPositions($clientB)->pluck('vehicle_id')->contains($vB->id), $results, $passed, $failed);
            $this->testResult('Flotas recorridos: A no alcanza (forClient) la unidad de B ni la interna',
                \App\Modules\Addons\Flotas\Models\FleetVehicle::forClient($clientA)->find($vB->id) === null
                && \App\Modules\Addons\Flotas\Models\FleetVehicle::forClient($clientA)->find($vInt->id) === null, $results, $passed, $failed);

            // ── Geocercas (#637) ──
            $gB   = \App\Modules\Addons\Flotas\Models\FleetGeofence::create(['client_id' => $clientB, 'name' => 'IDOR B', 'type' => 'both', 'polygon' => [[0, 0], [0, 1], [1, 1]]]);
            $gInt = \App\Modules\Addons\Flotas\Models\FleetGeofence::create(['client_id' => null, 'name' => 'IDOR interna', 'type' => 'both', 'polygon' => [[0, 0], [0, 1], [1, 1]]]);
            $this->testResult('Flotas geocercas: A no ve/edita/borra geocerca de B',
                \App\Modules\Addons\Flotas\Models\FleetGeofence::forClient($clientA)->find($gB->id) === null
                && \App\Modules\Addons\Flotas\Models\FleetGeofence::forClient($clientB)->find($gB->id) !== null, $results, $passed, $failed);
            $this->testResult('Flotas geocercas: A no ve geocercas internas de Meganet',
                \App\Modules\Addons\Flotas\Models\FleetGeofence::forClient($clientA)->find($gInt->id) === null, $results, $passed, $failed);

            // ── Reglas y alertas (#644/#645) ──
            $mkRule = fn ($cid) => \App\Modules\Addons\Flotas\Models\FleetGeofenceRule::create([
                'name' => 'IDOR regla', 'tipo' => 'geocerca', 'client_id' => $cid, 'user_id' => null, 'origen' => 'cliente',
                'channels' => ['email'], 'active' => true, 'vehicle_ids' => [], 'geofence_ids' => [], 'event_types' => [], 'days_of_week' => [],
            ]);
            $rB = $mkRule($clientB);
            $own = fn ($cid) => \App\Modules\Addons\Flotas\Models\FleetGeofenceRule::where('origen', 'cliente')->where('client_id', $cid);
            $this->testResult('Flotas reglas: A no ve/edita/borra regla de B',
                $own($clientA)->find($rB->id) === null && $own($clientB)->find($rB->id) !== null, $results, $passed, $failed);
            $mkEv = fn ($v) => \App\Modules\Addons\Flotas\Models\FleetGeofenceEvent::create([
                'vehicle_id' => $v->id, 'geofence_id' => $gB->id, 'event_type' => 'exit', 'occurred_at' => now(), 'created_at' => now(),
            ]);
            $eB = $mkEv($vB); $eInt = $mkEv($vInt);
            $evA = \App\Modules\Addons\Flotas\Models\FleetGeofenceEvent::forClient($clientA);
            $this->testResult('Flotas alertas: A no ve alertas de B ni de la flota interna',
                (clone $evA)->find($eB->id) === null && (clone $evA)->find($eInt->id) === null, $results, $passed, $failed);
            $this->testResult('Flotas alertas: B sí ve su propia alerta y no la interna',
                \App\Modules\Addons\Flotas\Models\FleetGeofenceEvent::forClient($clientB)->find($eB->id) !== null
                && \App\Modules\Addons\Flotas\Models\FleetGeofenceEvent::forClient($clientB)->find($eInt->id) === null, $results, $passed, $failed);

            // ── #652: rutas espejo /portal/flotas/api/* — HTTP real por el kernel ──
            $this->flotasPortalHttp($clientA, $clientB, $vB, $vInt, $gB, $dB, $mB, $fB, $results, $passed, $failed);
            // Contactos de alerta y bitácora (#672)
            $cB = \App\Modules\Addons\Flotas\Models\FleetClientAlertContact::create(['client_id' => $clientB, 'nombre' => 'IDOR contacto']);
            $dlB = \App\Modules\Addons\Flotas\Models\FleetClientAlertDelivery::create(['client_id' => $clientB, 'channel' => 'email', 'status' => 'sent']);
            $this->testResult('Flotas contactos: A no ve/edita/borra contacto de B (ni su bitácora)',
                \App\Modules\Addons\Flotas\Models\FleetClientAlertContact::forClient($clientA)->find($cB->id) === null
                && \App\Modules\Addons\Flotas\Models\FleetClientAlertContact::forClient($clientB)->find($cB->id) !== null
                && \App\Modules\Addons\Flotas\Models\FleetClientAlertDelivery::where('client_id', $clientA)->find($dlB->id) === null, $results, $passed, $failed);
            // ── #639: el panel interno /flotas NO incluye datos de clientes; "Flotas de clientes" sí (con permiso) ──
            $this->flotasPanelInterno($vB, $vInt, $gB, $results, $passed, $failed);
        } catch (\Throwable $e) {
            $this->testResult('Flotas: prueba no pudo ejecutarse (' . $e->getMessage() . ')', false, $results, $passed, $failed);
        } finally {
            DB::rollBack();
        }

        $this->newLine();
        $this->info("Resultado: {$passed} ✅ / {$failed} ❌ (total " . ($passed + $failed) . " tests ejecutados)");

        if ($failed > 0) {
            $this->error('⚠️ TESTS FALLIDOS — revisar aislamiento antes de desplegar en producción.');
        } else {
            $this->info('✅ Todos los tests anti-IDOR pasaron. El aislamiento por cliente está funcionando.');
        }

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * #652: pega de verdad (kernel HTTP) a las rutas espejo del portal con la sesión del cliente A
     * y comprueba que no alcanza nada de B ni de Meganet, que el panel no acepta la sesión del
     * portal y que lo exclusivo de Meganet no está expuesto. Corre dentro de la transacción del caller.
     */
    private function flotasPortalHttp(int $a, int $b, $vB, $vInt, $gB, $dB, $mB, $fB, array &$results, int &$p, int &$f): void
    {
        $guard = \Illuminate\Support\Facades\Auth::guard('cliente');
        $cmiA  = \App\Modules\Addons\PortalCliente\Models\PortalClient::where('client_id', $a)->first();
        $cmiB  = \App\Modules\Addons\PortalCliente\Models\PortalClient::where('client_id', $b)->first();
        if (! $cmiA || ! $cmiB) {
            $this->line('  [SKIP] Flotas HTTP: sin PortalClient para A y B.');
            return;
        }

        foreach ([$a, $b] as $cid) {
            \App\Modules\Addons\Flotas\Models\FleetSubscription::create(['client_id' => $cid, 'plan' => 'basico', 'status' => 'active']);
        }

        $kernel = app(\Illuminate\Contracts\Http\Kernel::class);
        $env    = app()['env'];
        app()['env'] = 'testing'; // solo para saltar la verificación CSRF dentro de este proceso de prueba
        $call = function (string $method, string $uri, $cmi = null) use ($kernel, $guard) {
            \Illuminate\Support\Facades\Auth::forgetGuards();
            $guard = \Illuminate\Support\Facades\Auth::guard('cliente');
            $cmi ? $guard->setUser($cmi) : null;
            $req = \Illuminate\Http\Request::create($uri, $method, [], [], [], ['HTTP_ACCEPT' => 'application/json']);

            return $kernel->handle($req)->getStatusCode();
        };

        try {
            $base = '/portal/flotas/api';
            $ajenos = [
                ['GET', "$base/vehiculos/{$vB->id}"], ['PATCH', "$base/vehiculos/{$vB->id}"], ['DELETE', "$base/vehiculos/{$vB->id}"],
                ['GET', "$base/vehiculos/{$vInt->id}"], ['GET', "$base/vehiculos/{$vB->id}/gps"], ['GET', "$base/vehiculos/{$vB->id}/gps/history"],
                ['GET', "$base/geocercas/{$gB->id}"], ['PUT', "$base/geocercas/{$gB->id}"], ['DELETE', "$base/geocercas/{$gB->id}"],
                ['GET', "$base/documentos/{$dB->id}"], ['DELETE', "$base/documentos/{$dB->id}"], ['GET', "$base/documentos/{$dB->id}/descargar"],
                ['GET', "$base/mantenimientos/{$mB->id}"], ['DELETE', "$base/mantenimientos/{$mB->id}"],
            ];
            $fugas = [];
            foreach ($ajenos as [$m, $u]) {
                $code = $call($m, $u, $cmiA);
                if (! in_array($code, [403, 404, 422], true)) {
                    $fugas[] = "$m $u→$code";
                }
            }
            $this->testResult('Flotas portal HTTP: A no alcanza por id unidad/geocerca/documento/mantenimiento de B ni internas'
                . ($fugas ? ' [' . implode(', ', $fugas) . ']' : ''), ! $fugas, $results, $p, $f);

            $this->testResult('Flotas portal HTTP: B sí ve su unidad (control positivo)',
                $call('GET', "$base/vehiculos/{$vB->id}", $cmiB) === 200, $results, $p, $f);

            $nofuga = true;
            \Illuminate\Support\Facades\Auth::forgetGuards();
            $guard = \Illuminate\Support\Facades\Auth::guard('cliente');
            $guard->setUser($cmiA);
            $json = $kernel->handle(\Illuminate\Http\Request::create("$base/vehiculos", 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/json']))->getContent();
            $nofuga = ! str_contains($json, '"id":' . $vB->id . ',') && ! str_contains($json, '"id":' . $vInt->id . ',');
            $this->testResult('Flotas portal HTTP: el listado de A no incluye unidades de B ni internas', $nofuga, $results, $p, $f);

            $this->testResult('Flotas portal HTTP: sin sesión de portal → 401/302, no datos',
                in_array($call('GET', "$base/vehiculos"), [401, 302], true), $results, $p, $f);

            $this->testResult('Flotas panel: /flotas/api no acepta la sesión del portal (#634)',
                ! in_array($call('GET', "/flotas/api/vehiculos/{$vB->id}", $cmiA), [200], true)
                && ! in_array($call('GET', '/flotas/api/vehiculos', $cmiA), [200], true), $results, $p, $f);

            $ocultas = array_map(fn ($u) => $call('GET', "$base/$u", $cmiA),
                ['proveedores', 'suscripciones/dashboard', 'comparativo', 'clientes-flota', 'asignaciones']);
            $this->testResult('Flotas portal HTTP: proveedores/suscripción/comparativo/clientes-flota NO existen en el portal',
                collect($ocultas)->every(fn ($c) => $c === 404), $results, $p, $f);

            // #676 (F6b): Mis operadores y asignaciones — A no ve/edita/borra los de B; no asigna a unidad ajena.
            $opB   = \App\Modules\Addons\Flotas\Models\FleetClientOperator::create(['client_id' => $b, 'nombre' => 'IDOR operador B']);
            $asB   = \App\Modules\Addons\Flotas\Models\FleetAssignment::create(['vehicle_id' => $vB->id, 'user_id' => null, 'operator_name' => 'IDOR asignado B', 'client_operator_id' => $opB->id, 'since' => now()->toDateString()]);
            $callJ = function (string $method, string $uri, array $data, $cmi) use ($kernel) {
                \Illuminate\Support\Facades\Auth::forgetGuards();
                \Illuminate\Support\Facades\Auth::guard('cliente')->setUser($cmi);
                $resp = $kernel->handle(\Illuminate\Http\Request::create($uri, $method, $data, [], [], ['HTTP_ACCEPT' => 'application/json']));

                return [$resp->getStatusCode(), $resp->getContent()];
            };
            $fugasOp = [];
            foreach ([['PUT', "$base/operadores/{$opB->id}", ['nombre' => 'X']], ['DELETE', "$base/operadores/{$opB->id}", []],
                      ['GET', "$base/vehiculos/{$vB->id}/asignaciones", []],
                      ['POST', "$base/vehiculos/{$vB->id}/asignaciones", ['operator_name' => 'Intruso', 'since' => now()->toDateString()]],
                      ['POST', "$base/vehiculos/{$vInt->id}/asignaciones", ['operator_name' => 'Intruso', 'since' => now()->toDateString()]]] as [$m, $u, $d]) {
                [$code] = $callJ($m, $u, $d, $cmiA);
                if (! in_array($code, [403, 404, 422], true)) {
                    $fugasOp[] = "$m $u→$code";
                }
            }
            [, $listA] = $callJ('GET', "$base/operadores", [], $cmiA);
            $this->testResult('Flotas operadores: A no ve/edita/borra operadores ni asignaciones de B ni de Meganet, ni asigna a unidad ajena'
                . ($fugasOp ? ' [' . implode(', ', $fugasOp) . ']' : ''),
                ! $fugasOp && ! str_contains($listA, 'IDOR operador')
                && \App\Modules\Addons\Flotas\Models\FleetClientOperator::find($opB->id) !== null
                && \App\Modules\Addons\Flotas\Models\FleetAssignment::where('vehicle_id', $vB->id)->count() === 1, $results, $p, $f);

            // A tiene unidad propia: no puede usar client_operator_id ajeno, ni user_id; sí puede con el suyo.
            $vA = \App\Modules\Addons\Flotas\Models\FleetVehicle::create(['client_id' => $a, 'brand' => 'IDOR', 'model' => 'A']);
            [$cAjeno] = $callJ('POST', "$base/vehiculos/{$vA->id}/asignaciones", ['client_operator_id' => $opB->id, 'since' => now()->toDateString()], $cmiA);
            [$cAmbos] = $callJ('POST', "$base/vehiculos/{$vA->id}/asignaciones", ['client_operator_id' => $opB->id, 'operator_name' => 'x', 'since' => now()->toDateString()], $cmiA);
            $this->testResult('Flotas operadores: A no asigna con client_operator_id ajeno ni con ambos/ninguno',
                in_array($cAjeno, [404, 422], true) && $cAmbos === 422
                && \App\Modules\Addons\Flotas\Models\FleetAssignment::where('vehicle_id', $vA->id)->count() === 0, $results, $p, $f);

            [$cNom] = $callJ('POST', "$base/vehiculos/{$vA->id}/asignaciones", ['operator_name' => 'Chofer Libre', 'user_id' => 1, 'since' => now()->toDateString()], $cmiA);
            [$cOp, $bodyOp] = $callJ('POST', "$base/operadores", ['nombre' => 'IDOR propio A'], $cmiA);
            $propioId = json_decode($bodyOp, true)['operador']['id'] ?? 0;
            [$cAsig] = $callJ('POST', "$base/vehiculos/{$vA->id}/asignaciones", ['client_operator_id' => $propioId, 'since' => now()->addDay()->toDateString()], $cmiA);
            $filas = \App\Modules\Addons\Flotas\Models\FleetAssignment::where('vehicle_id', $vA->id)->orderBy('id')->get();
            $this->testResult('Flotas operadores: A (control positivo) crea operador y asigna; user_id nunca; la previa se cierra',
                $cNom === 201 && $cOp === 201 && $cAsig === 201 && $filas->count() === 2
                && $filas->every(fn ($x) => $x->user_id === null)
                && $filas[0]->until !== null && $filas[1]->operator_name === 'IDOR propio A'
                && \App\Modules\Addons\Flotas\Models\FleetClientOperator::where('client_id', $a)->find($propioId)?->client_id === $a, $results, $p, $f);

            // #664: bitácora de notificaciones — A no ve las de B ni las de la flota interna; B sí ve las suyas.
            $uid = \App\Models\User::query()->value('id');
            $mkLog = function ($v, string $dest) use ($gB, $uid) {
                $ev = \App\Modules\Addons\Flotas\Models\FleetGeofenceEvent::create([
                    'vehicle_id' => $v->id, 'geofence_id' => $gB->id, 'event_type' => 'enter', 'occurred_at' => now(), 'created_at' => now(),
                ]);
                return \App\Modules\Addons\Flotas\Models\FleetNotificationLog::create([
                    'event_id' => $ev->id, 'user_id' => $uid, 'channel' => 'email', 'destination' => $dest, 'status' => 'sent', 'created_at' => now(),
                ])->id;
            };
            $logB   = $mkLog($vB, 'idor-b@notif.test');
            $logInt = $mkLog($vInt, 'idor-interna@notif.test');
            // Los ids de la bitácora que devuelve el portal (el destinatario ya no viaja, ver #664).
            $idsLog = fn (string $json) => collect(json_decode($json, true)['items'] ?? [])->pluck('id')->map(fn ($i) => (int) $i)->all();
            $body = function ($cmi) use ($kernel, $base) {
                \Illuminate\Support\Facades\Auth::forgetGuards();
                \Illuminate\Support\Facades\Auth::guard('cliente')->setUser($cmi);

                return $kernel->handle(\Illuminate\Http\Request::create("$base/notificaciones-log", 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/json']))->getContent();
            };
            $jA = $body($cmiA);
            $jB = $body($cmiB);
            $this->testResult('Flotas portal HTTP: bitácora de notificaciones de A no incluye las de B ni internas',
                ! in_array($logB, $idsLog($jA), true) && ! in_array($logInt, $idsLog($jA), true), $results, $p, $f);
            $this->testResult('Flotas portal HTTP: bitácora de notificaciones de B incluye las suyas (control positivo)',
                in_array($logB, $idsLog($jB), true), $results, $p, $f);
            $this->testResult('Flotas portal HTTP: la bitácora del portal no expone destinatario ni errores internos (#664)',
                ! str_contains($jB, 'idor-b@notif.test') && collect(json_decode($jB, true)['items'] ?? [])->every(fn ($i) => $i['destination'] === null && $i['user'] === null && $i['error_message'] === null), $results, $p, $f);
            $this->testResult('Flotas portal HTTP: reenvío de notificaciones no existe en el portal',
                $call('POST', "$base/notificaciones-log/1/resend", $cmiA) === 404 || $call('POST', "$base/notificaciones-log/1/resend", $cmiA) === 405, $results, $p, $f);

            // #665: reglas por HTTP — A no ve/edita/borra/activa la regla de B; B sí la ve.
            $rHttp = \App\Modules\Addons\Flotas\Models\FleetGeofenceRule::create([
                'name' => 'IDOR regla http', 'tipo' => 'geocerca', 'client_id' => $b, 'user_id' => null, 'origen' => 'cliente',
                'channels' => ['email'], 'active' => true, 'vehicle_ids' => [], 'geofence_ids' => [], 'event_types' => [], 'days_of_week' => [],
            ]);
            $ajenasR = [['PUT', "$base/reglas/{$rHttp->id}"], ['DELETE', "$base/reglas/{$rHttp->id}"], ['POST', "$base/reglas/{$rHttp->id}/toggle"]];
            $fugasR = [];
            foreach ($ajenasR as [$m, $u]) {
                $code = $call($m, $u, $cmiA);
                if (! in_array($code, [403, 404, 422], true)) {
                    $fugasR[] = "$m $u→$code";
                }
            }
            $rHttp->refresh();
            $this->testResult('Flotas portal HTTP: A no edita/borra/activa la regla de B' . ($fugasR ? ' [' . implode(', ', $fugasR) . ']' : ''),
                ! $fugasR && $rHttp->active && \App\Modules\Addons\Flotas\Models\FleetGeofenceRule::find($rHttp->id) !== null, $results, $p, $f);
            $listaR = function ($cmi) use ($kernel, $base) {
                \Illuminate\Support\Facades\Auth::forgetGuards();
                \Illuminate\Support\Facades\Auth::guard('cliente')->setUser($cmi);

                return $kernel->handle(\Illuminate\Http\Request::create("$base/reglas", 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/json']))->getContent();
            };
            $this->testResult('Flotas portal HTTP: lista de reglas de A no incluye la de B; B sí la ve',
                ! str_contains($listaR($cmiA), 'IDOR regla http') && str_contains($listaR($cmiB), 'IDOR regla http'), $results, $p, $f);
            $rHttp->forceDelete();

            // #967: páginas del portal (no solo /flotas/api): la ficha ajena no se abre y los shells no cargan datos de B.
            $pg = '/portal/flotas';
            $htmlA = function (string $uri) use ($kernel, $cmiA) {
                \Illuminate\Support\Facades\Auth::forgetGuards();
                \Illuminate\Support\Facades\Auth::guard('cliente')->setUser($cmiA);
                $res = $kernel->handle(\Illuminate\Http\Request::create($uri, 'GET'));

                return [$res->getStatusCode(), (string) $res->getContent()];
            };
            [$cFichaB] = $htmlA("$pg/{$vB->id}");
            [$cFichaI] = $htmlA("$pg/{$vInt->id}");
            $this->testResult("Flotas portal páginas: A no abre la ficha /flotas/{id} de B ni de la flota interna (404)",
                $cFichaB === 404 && $cFichaI === 404, $results, $p, $f);
            $fugaPg = [];
            foreach (['/mapa', '/geocercas', "/geocercas/{$gB->id}", "/geocercas/{$gB->id}/editar", '/reglas', '/contactos', '/alertas'] as $suf) {
                [$code, $html] = $htmlA($pg . $suf);
                if ($code >= 500 || str_contains($html, (string) ($gB->name ?? '\0')) && $gB->name !== '' || str_contains($html, '/flotas/api/')) {
                    $fugaPg[] = "$suf→$code";
                }
            }
            $this->testResult('Flotas portal páginas: mapa/geocercas/reglas/contactos/alertas no filtran datos de B ni apuntan al /flotas/api del panel' . ($fugaPg ? ' [' . implode(', ', $fugaPg) . ']' : ''),
                ! $fugaPg, $results, $p, $f);
            $existe = [];
            foreach (['/proveedores', '/comparativo', '/suscripcion', '/clientes-flota'] as $suf) {
                foreach (["$pg$suf", "$pg/api$suf"] as $u) {
                    if ($call('GET', $u, $cmiA) === 200) {
                        $existe[] = $u;
                    }
                }
            }
            $this->testResult('Flotas portal páginas: Proveedores/Comparativo/Suscripción/Clientes-flota no existen en el portal' . ($existe ? ' [' . implode(', ', $existe) . ']' : ''),
                ! $existe, $results, $p, $f);

            \App\Modules\Addons\Flotas\Models\FleetSubscription::where('client_id', $a)->update(['status' => 'cancelled']);
            $this->testResult('Flotas portal HTTP: suscripción cancelada → 403 aunque haya sesión',
                $call('GET', "$base/vehiculos", $cmiA) === 403, $results, $p, $f);
        } finally {
            app()['env'] = $env;
            \Illuminate\Support\Facades\Auth::forgetGuards();
        }
    }

    /** #639 — el panel interno solo ve lo interno; la sección "Flotas de clientes" exige permiso y sí ve lo de clientes. */
    private function flotasPanelInterno($vB, $vInt, $gB, array &$results, int &$p, int &$f): void
    {
        $admin = \App\Models\User::role('DESARROLLADOR')->first();
        $sinPermiso = \App\Models\User::whereDoesntHave('roles')->whereDoesntHave('permissions')->first();
        if (! $admin) {
            $this->line('  [SKIP] Flotas panel: no hay usuario DESARROLLADOR.');
            return;
        }
        $kernel = app(\Illuminate\Contracts\Http\Kernel::class);
        $env = app()['env'];
        app()['env'] = 'testing';
        $get = function (string $uri, $user) use ($kernel) {
            \Illuminate\Support\Facades\Auth::forgetGuards();
            \Illuminate\Support\Facades\Auth::guard('web')->setUser($user);
            $res = $kernel->handle(\Illuminate\Http\Request::create($uri, 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/json']));

            return [$res->getStatusCode(), $res->getContent()];
        };
        try {
            $leak = [];
            foreach (['/flotas/api/vehiculos', '/flotas/api/vehiculos/data/dashboard', '/flotas/api/gps/flota', '/flotas/api/geocercas'] as $u) {
                [$code, $body] = $get($u, $admin);
                if ($code === 200 && (str_contains($body, '"id":' . $vB->id . ',') || str_contains($body, 'IDOR B'))) {
                    $leak[] = $u;
                }
            }
            $this->testResult('Flotas panel: /flotas no incluye unidades ni geocercas de clientes' . ($leak ? ' [' . implode(', ', $leak) . ']' : ''),
                ! $leak, $results, $p, $f);

            [$c1, $b1] = $get('/flotas/api/clientes-flota', $admin);
            [$c2, $b2] = $get('/flotas/api/clientes-flota/' . $vB->client_id, $admin);
            $this->testResult('Flotas de clientes: con permiso lista al cliente y muestra su unidad, sin unidades internas',
                $c1 === 200 && str_contains($b1, '"client_id":' . $vB->client_id)
                && $c2 === 200 && str_contains($b2, '"id":' . $vB->id) && ! str_contains($b2, '"id":' . $vInt->id . ','), $results, $p, $f);

            if ($sinPermiso) {
                [$code] = $get('/flotas/clientes', $sinPermiso);
                $this->testResult('Flotas de clientes: sin permiso no accede (403)', in_array($code, [401, 403, 302], true), $results, $p, $f);
            }
        } finally {
            app()['env'] = $env;
            \Illuminate\Support\Facades\Auth::forgetGuards();
        }
    }

    private function testResult(string $name, bool $passed, array &$results, int &$p, int &$f): void
    {
        $icon = $passed ? '✅' : '❌';
        $this->line("  {$icon} {$name}");
        $results[] = ['test' => $name, 'passed' => $passed];
        $passed ? $p++ : $f++;
    }
}
