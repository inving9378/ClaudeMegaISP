<?php

namespace App\Modules\Addons\Roadmap\Services;

use App\Models\User;
use App\Modules\Addons\Roadmap\Support\IaSupervisor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * MODOS DE IA DEL SUPERVISOR (#1115). Ahorro (default) = sin override: manda Módulos IA / config.
 * Normal y Potente reemplazan el modelo por pieza con una tabla editable en BD. «Potente 2 h»
 * guarda el modo previo y vence solo (perezoso: se evalúa al leer, sin depender del scheduler).
 */
class IaModosService
{
    public const MODOS = ['ahorro', 'normal', 'potente'];
    public const HORAS_POTENTE = 2;

    /** Modelos que se pueden elegir (hay que verificarlos con una llamada real antes). */
    public const CANDIDATOS = ['claude-haiku-5-5', 'claude-sonnet-5-5', 'claude-opus-4-7', 'claude-opus-5-5'];

    /** Tabla de fábrica de Normal y Potente. Ahorro no tiene: usa el respaldo actual. */
    public const DEFAULTS = [
        'normal' => [
            'circuito.valvula' => 'claude-haiku-5-5', 'circuito.revisor' => 'claude-sonnet-5-5',
            'circuito.segunda_opinion' => 'claude-sonnet-5-5', 'circuito.briefs' => 'claude-sonnet-5-5',
            'circuito.seguridad' => 'claude-sonnet-5-5', 'circuito.destrabe' => 'claude-sonnet-5-5',
        ],
        'potente' => [
            'circuito.valvula' => 'claude-sonnet-5-5', 'circuito.revisor' => 'claude-opus-4-7',
            'circuito.segunda_opinion' => 'claude-opus-4-7', 'circuito.briefs' => 'claude-opus-4-7',
            'circuito.seguridad' => 'claude-opus-4-7', 'circuito.destrabe' => 'claude-opus-4-7',
        ],
    ];

    public function disponible(): bool
    {
        return Schema::hasTable('circuito_ia_config');
    }

    private function fila(): object
    {
        $f = DB::table('circuito_ia_config')->first();
        if (! $f) {
            $id = DB::table('circuito_ia_config')->insertGetId(['modo' => 'ahorro', 'created_at' => now(), 'updated_at' => now()]);
            $f = DB::table('circuito_ia_config')->find($id);
        }

        return $f;
    }

    /** Modo vigente; si «Potente 2 h» venció, vuelve al previo y lo deja en el historial. */
    public function modoActivo(): string
    {
        $f = $this->fila();
        if ($f->modo === 'potente' && $f->potente_hasta && now()->gt($f->potente_hasta)) {
            $previo = in_array($f->modo_previo, self::MODOS, true) ? $f->modo_previo : 'ahorro';
            DB::table('circuito_ia_config')->where('id', $f->id)
                ->update(['modo' => $previo, 'modo_previo' => null, 'potente_hasta' => null, 'updated_at' => now()]);
            $this->registrar('expira', null, null, 'potente', $previo, 'Venció «Potente 2 h»');

            return $previo;
        }

        return $f->modo;
    }

    /** Modelo que impone el modo para la pieza, o null (Ahorro / pieza sin regla) = no override. */
    public function modeloPara(string $pieza): ?string
    {
        if (! $this->disponible()) {
            return null;
        }
        $modo = $this->modoActivo();
        if ($modo === 'ahorro') {
            return null;
        }
        $tabla = $this->tabla($modo);

        return $tabla[$pieza] ?? null;
    }

    /** @return array<string,string> pieza => modelo del modo (fábrica + ediciones). */
    public function tabla(string $modo): array
    {
        $ed = (array) (json_decode($this->fila()->piezas ?? 'null', true)[$modo] ?? []);

        return $ed + (self::DEFAULTS[$modo] ?? []);
    }

    public function fijarModo(string $modo, ?User $u): void
    {
        abort_unless(in_array($modo, self::MODOS, true), 422, 'Modo inválido.');
        $f = $this->fila();
        $antes = $this->modoActivo();
        DB::table('circuito_ia_config')->where('id', $f->id)
            ->update(['modo' => $modo, 'modo_previo' => null, 'potente_hasta' => null, 'updated_at' => now()]);
        $this->registrar('modo', $u, null, $antes, $modo);
    }

    public function potenteDosHoras(?User $u): void
    {
        $f = $this->fila();
        $antes = $this->modoActivo();
        $previo = $antes === 'potente' ? ($f->modo_previo ?: 'ahorro') : $antes;
        DB::table('circuito_ia_config')->where('id', $f->id)->update([
            'modo' => 'potente', 'modo_previo' => $previo,
            'potente_hasta' => now()->addHours(self::HORAS_POTENTE), 'updated_at' => now(),
        ]);
        $this->registrar('potente', $u, null, $antes, 'potente', 'Potente por ' . self::HORAS_POTENTE . ' h; luego vuelve a ' . $previo);
    }

    public function fijarPieza(string $modo, string $pieza, string $modelo, ?User $u): void
    {
        abort_unless(in_array($modo, ['normal', 'potente'], true), 422, 'Ahorro no se edita: usa Módulos IA.');
        abort_unless(isset(IaSupervisor::PIEZAS[$pieza]), 422, 'Pieza desconocida.');
        abort_unless($this->estaVerificado($modelo), 422, 'Ese modelo no está verificado con una llamada real.');
        $f = $this->fila();
        $antes = $this->tabla($modo)[$pieza] ?? null;
        $piezas = (array) json_decode($f->piezas ?? 'null', true);
        $piezas[$modo][$pieza] = $modelo;
        DB::table('circuito_ia_config')->where('id', $f->id)->update(['piezas' => json_encode($piezas), 'updated_at' => now()]);
        $this->registrar('pieza', $u, $pieza, $antes, $modelo, "modo {$modo}");
    }

    public function estaVerificado(string $modelo): bool
    {
        $v = (array) json_decode($this->fila()->verificados ?? 'null', true);

        return in_array($modelo, self::CANDIDATOS, true) && (bool) ($v[$modelo]['ok'] ?? false);
    }

    /** Llamada real mínima al modelo; guarda el resultado en la lista verificada. */
    public function verificar(string $modelo, ?User $u): array
    {
        abort_unless(in_array($modelo, self::CANDIDATOS, true), 422, 'Modelo fuera de la lista.');
        try {
            $r = (new \App\Modules\Addons\Marketing\Services\ClaudeApiClient())->messages(
                IaSupervisor::parametros($modelo, ['max_tokens' => 16, 'messages' => [['role' => 'user', 'content' => 'Responde: ok']]]),
                ['pieza' => 'circuito.verificacion']
            );
            IaSupervisor::texto($r);
            $res = ['ok' => true, 'at' => now()->toIso8601String()];
        } catch (\Throwable $e) {
            $res = ['ok' => false, 'at' => now()->toIso8601String(), 'error' => mb_substr($e->getMessage(), 0, 200)];
        }
        $f = $this->fila();
        $v = (array) json_decode($f->verificados ?? 'null', true);
        $v[$modelo] = $res;
        DB::table('circuito_ia_config')->where('id', $f->id)->update(['verificados' => json_encode($v), 'updated_at' => now()]);
        $this->registrar('verificacion', $u, null, null, $modelo, $res['ok'] ? 'ok' : ($res['error'] ?? 'falló'));

        return $res;
    }

    public function registrarFallback(string $pieza, string $fallido, string $vuelveA, string $motivo): void
    {
        $this->registrar('fallback', null, $pieza, $fallido, $vuelveA, $motivo);
    }

    public function registrar(string $tipo, ?User $u, ?string $pieza, ?string $antes, ?string $despues, ?string $detalle = null): void
    {
        DB::table('circuito_ia_cambios')->insert([
            'tipo' => $tipo, 'user_id' => $u?->id, 'pieza' => $pieza,
            'antes' => $antes, 'despues' => $despues,
            'detalle' => $detalle ? mb_substr($detalle, 0, 500) : null, 'created_at' => now(),
        ]);
    }

    /** Foto para la pantalla. `banner` = último fallback de las últimas 24 h. */
    public function estado(): array
    {
        $f = $this->fila();
        $modo = $this->modoActivo();
        $f = $this->fila();
        $fb = DB::table('circuito_ia_cambios')->where('tipo', 'fallback')->where('created_at', '>=', now()->subDay())->orderByDesc('id')->first();

        return [
            'modo' => $modo,
            'potente_hasta' => $f->potente_hasta,
            'modo_previo' => $f->modo_previo,
            'piezas' => array_keys(IaSupervisor::PIEZAS),
            'tablas' => ['normal' => $this->tabla('normal'), 'potente' => $this->tabla('potente')],
            'candidatos' => self::CANDIDATOS,
            'verificados' => (object) (json_decode($f->verificados ?? 'null', true) ?? []),
            'banner' => $fb ? "La pieza {$fb->pieza} no pudo usar {$fb->antes} y volvió a {$fb->despues} ({$fb->detalle})." : null,
            'historial' => DB::table('circuito_ia_cambios')->orderByDesc('id')->limit(30)->get(),
        ];
    }
}
