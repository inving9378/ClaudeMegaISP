<?php

namespace App\Modules\Addons\CobranzaBlaster\Console;

use App\Modules\Addons\CobranzaBlaster\Models\CobranzaCampana;
use App\Modules\Addons\CobranzaBlaster\Models\CobranzaLlamada;
use App\Modules\Addons\CobranzaBlaster\Models\CobranzaLlamadaEvento;
use App\Modules\Addons\CobranzaBlaster\Models\VoipConfiguracion;
use App\Modules\Addons\CobranzaBlaster\Services\AmiConnectionService;
use App\Modules\Addons\CobranzaBlaster\Services\CobranzaTtsService;
use App\Modules\Core\Clientes\Models\Client;
use Illuminate\Console\Command;

/**
 * Item roadmap #67 — llamada de validación end-to-end SIN tocar morosos reales.
 *
 * Origina UNA llamada real por la troncal Servnet a un número controlado
 * (celular/extensión de Irving, nunca un cliente), reusando el MISMO camino que
 * usa BlastCampanaJob (TTS -> AmiConnectionService::originate -> cobranza_llamadas
 * + cobranza_llamada_eventos), para que la prueba valide de verdad la integración
 * SIP/AMI/listener y quede registrada en BD como cualquier llamada real.
 *
 * Aislamiento a propósito: la campaña de prueba SIEMPRE queda en estado
 * 'borrador', así que el cron `cobranza:blast-activas` (que solo procesa
 * campañas 'activa') jamás la toca. `client_id` solo satisface el FK de
 * `cobranza_llamadas` — el número que timbra es el que se pasa por argumento,
 * no el teléfono del cliente elegido.
 */
class LlamadaPruebaCommand extends Command
{
    protected $signature = 'cobranza:llamada-prueba
        {telefono : Número a marcar (SOLO un número controlado, nunca un cliente real)}
        {--client_id= : client_id existente para satisfacer el FK (no se marca su teléfono); por defecto toma el primero que exista}
        {--nombre=Prueba de validación : Nombre que se anuncia en el log de la llamada}';

    protected $description = 'Origina UNA llamada real de prueba (item #67) para validar el despliegue de CobranzaBlaster sin tocar morosos reales';

    public function handle(AmiConnectionService $ami, CobranzaTtsService $tts): int
    {
        $telefono = preg_replace('/\D/', '', (string) $this->argument('telefono'));

        if (strlen($telefono) < 10) {
            $this->error("Teléfono inválido: '{$this->argument('telefono')}' (se esperan al menos 10 dígitos).");
            return self::FAILURE;
        }

        $config = VoipConfiguracion::first();
        if (!$config || !$config->activa || $config->estado !== 'activa') {
            $this->error('La troncal Servnet no está configurada/activa en /cobranza/voip. Configúrala antes de probar.');
            return self::FAILURE;
        }
        if (!$config->callerid_nombre && !$config->callerid_numero) {
            $this->error('VoipConfiguracion sin callerid_nombre/callerid_numero: el originate se abortará (#277). Configura el CallerID en /cobranza/voip.');
            return self::FAILURE;
        }

        $clientId = $this->option('client_id') ? (int) $this->option('client_id') : Client::query()->value('id');
        if (!$clientId) {
            $this->error('No hay ningún cliente en la BD para satisfacer el FK client_id de cobranza_llamadas.');
            return self::FAILURE;
        }

        $campana = CobranzaCampana::firstOrCreate(
            ['nombre' => 'PRUEBA-VALIDACION-ITEM-67'],
            [
                'estado'                 => 'borrador', // nunca 'activa': el cron cobranza:blast-activas la ignora
                'hora_inicio'            => '00:00:00',
                'hora_fin'               => '23:59:59',
                'max_intentos'           => 1,
                'minutos_entre_intentos' => 1,
                'dias_vencimiento'       => 0,
                'notas'                  => 'Campaña de prueba interna (item roadmap #67). Solo la usa cobranza:llamada-prueba. NO activar ni cargar morosos aquí.',
            ]
        );

        $llamada = CobranzaLlamada::create([
            'campana_id'    => $campana->id,
            'client_id'     => $clientId,
            'telefono'      => $telefono,
            'estado'        => 'pendiente',
            'monto_vencido' => 0,
        ]);

        $nombre  = (string) $this->option('nombre');
        $mensaje = 'Esta es una llamada de prueba del sistema de cobranza de Meganet Telecomunicaciones. '
            . 'Si escucha este mensaje con claridad, la integración con la troncal Servnet quedó validada '
            . 'correctamente. Gracias.';

        $this->info("Generando audio de prueba (TTS)…");
        $audioPath = $tts->generateAudioCached('prueba_validacion_item_67', $mensaje);

        if (!$audioPath) {
            $this->error('Falló la generación de audio TTS (ver storage/logs). No se origina la llamada.');
            return self::FAILURE;
        }

        if (!$ami->connect()) {
            $this->error('No se pudo conectar al AMI de Asterisk (AMI_HOST/AMI_PORT/AMI_USERNAME/AMI_SECRET en .env).');
            return self::FAILURE;
        }

        $this->info("Originando llamada de prueba #{$llamada->id} -> {$telefono}…");
        $result = $ami->originate($telefono, $audioPath, $llamada->id, $nombre);

        $llamada->update([
            'estado'            => $result['success'] ? 'marcando' : 'fallida',
            'ultimo_intento_at' => now(),
            'intentos'          => 1,
            'ami_channel'       => $result['channel'] ?? null,
            'ami_uniqueid'      => $result['uniqueid'] ?? null,
        ]);

        CobranzaLlamadaEvento::create([
            'llamada_id'  => $llamada->id,
            'evento'      => 'Originate',
            'payload'     => $result,
            'ocurrido_at' => now(),
        ]);

        $ami->disconnect();

        if (!$result['success']) {
            $this->error('AMI Originate falló. Revisa storage/logs/laravel.log y "sip show peers"/PJSIP en Asterisk.');
            $this->line("cobranza_llamadas.id = {$llamada->id} (estado=fallida)");
            return self::FAILURE;
        }

        $this->info('Originate aceptado por Asterisk. Debe timbrar en unos segundos.');
        $this->line("cobranza_llamadas.id = {$llamada->id} (uniqueid={$result['uniqueid']})");
        $this->line('Verifica en paralelo: tail -f storage/logs/laravel.log  y  el estado de "cobranza:ami-listener" (supervisor).');
        $this->line('El estado de esta llamada se actualizará solo si el daemon cobranza:ami-listener está corriendo.');

        return self::SUCCESS;
    }
}
