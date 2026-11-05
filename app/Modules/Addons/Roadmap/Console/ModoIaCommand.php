<?php

namespace App\Modules\Addons\Roadmap\Console;

use App\Models\User;
use App\Modules\Addons\Roadmap\Support\ModoIa;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * #1116 — Estado y edición del modo de IA del supervisor. Ver estado: sin opciones.
 * Editar exige `--usuario=<login_user|email>` con rol super-administrator; todo queda en historial.
 */
class ModoIaCommand extends Command
{
    protected $signature = 'circuito:ia-modo
        {--usuario= : quien edita (solo super-administrator)}
        {--activar= : on|off — interruptor del control por modo/tope}
        {--base= : modo fuera de las reglas: economico|normal}
        {--tope= : presupuesto diario en MXN (0 = solo medir)}
        {--regla=* : "dia(1-7),HH:MM,HH:MM,modo" — reemplaza TODAS las reglas; repetir la opción}
        {--sin-reglas : borra todas las reglas}';

    protected $description = 'Modo de IA del supervisor por día/hora y presupuesto diario en MXN (#1116)';

    public function handle(): int
    {
        $edita = $this->option('activar') !== null || $this->option('base') !== null || $this->option('tope') !== null
            || $this->option('regla') || $this->option('sin-reglas');

        if ($edita) {
            $u = User::where('login_user', $this->option('usuario'))->orWhere('email', $this->option('usuario'))->first();
            if (! $u || ! $u->hasRole('super-administrator')) {
                $this->error('Solo un super-administrator puede editar (--usuario=...).');

                return self::FAILURE;
            }
            $quien = (string) $u->login_user;

            if (($a = $this->option('activar')) !== null) {
                ModoIa::fijar('activo', $a === 'on' ? '1' : '0', $quien);
            }
            if (($b = $this->option('base')) !== null) {
                if (! in_array($b, [ModoIa::ECONOMICO, ModoIa::NORMAL], true)) {
                    $this->error('--base: economico|normal');

                    return self::FAILURE;
                }
                ModoIa::fijar('modo_base', $b, $quien);
            }
            if (($t = $this->option('tope')) !== null) {
                ModoIa::fijar('presupuesto_diario_mxn', (string) max(0, (float) $t), $quien);
            }
            if ($this->option('sin-reglas')) {
                ModoIa::fijarReglas([], $quien);
            } elseif ($this->option('regla')) {
                $reglas = [];
                foreach ($this->option('regla') as $txt) {
                    $p = array_map('trim', explode(',', $txt));
                    if (count($p) !== 4 || ! preg_match('/^[1-7]$/', $p[0]) || ! preg_match('/^\d{2}:\d{2}$/', $p[1]) || ! preg_match('/^\d{2}:\d{2}$/', $p[2])
                        || $p[1] >= $p[2] || ! in_array($p[3], [ModoIa::ECONOMICO, ModoIa::NORMAL], true)) {
                        $this->error("Regla inválida: {$txt}");

                        return self::FAILURE;
                    }
                    $reglas[] = [(int) $p[0], $p[1] . ':00', $p[2] . ':00', $p[3]];
                }
                ModoIa::fijarReglas($reglas, $quien);
            }
        }

        $hoy = Carbon::now(ModoIa::ZONA);
        $this->line('Control activo: ' . (ModoIa::ajuste('activo', '0') === '1' ? 'SÍ' : 'no (el supervisor no cambia)'));
        $this->line('Modo efectivo ahora: ' . (ModoIa::modoEfectivo() ?? '(sin control)') . ' · base: ' . ModoIa::ajuste('modo_base', ModoIa::NORMAL));
        $tope = ModoIa::presupuestoDiario();
        $this->line(sprintf('Gasto hoy: %.2f MXN · tope diario: %s%s', ModoIa::gastoMxn($hoy->toDateString(), null),
            $tope > 0 ? number_format($tope, 2) . ' MXN' : 'sin tope (solo mide)', ModoIa::topeAlcanzado() ? ' · TOPE ALCANZADO' : ''));
        $this->line(sprintf('Gasto del mes: %.2f MXN', ModoIa::gastoMxn($hoy->copy()->startOfMonth()->toDateString(), $hoy->toDateString())));
        foreach (ModoIa::ultimoModeloPorPieza() as $pieza => $modelo) {
            $this->line("  {$pieza}: {$modelo}");
        }

        return self::SUCCESS;
    }
}
