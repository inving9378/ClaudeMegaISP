<?php

namespace App\Modules\Addons\VoIP\Services;

use App\Modules\Addons\VoIP\Models\Extension;
use App\Modules\Addons\VoIP\Models\GrupoTimbrado;
use App\Modules\Addons\VoIP\Models\Troncal;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Deja Asterisk igual a lo que dice MegaISP (extensiones, troncales, colas y plan de
 * marcado) y, donde lo único bueno lo tiene Asterisk, al revés: las contraseñas que la
 * app ya no puede leer (cifradas con otra APP_KEY) se recuperan de Asterisk, que es lo
 * que tienen configurado los teléfonos.
 *
 * revisar(false) solo lista lo que haría; revisar(true) lo hace. Nunca borra una
 * extensión de Asterisk que no exista en MegaISP (algún teléfono la puede estar usando):
 * la reporta. Una troncal vieja solo se quita si otra de MegaISP tomó sus credenciales.
 *
 * Escribe el archivo de registraciones de troncales: hay que correrlo como el usuario
 * del servidor web (desde la pantalla) o como root.
 */
class SincronizadorAsterisk
{
    private array $tareas = [];
    private array $pendientes = [];
    private array $fallas = [];

    public function __construct(
        private AsteriskProvisioningService $prov,
        private DialplanGeneratorService $dialplan,
    ) {
    }

    /** @return array{aplicado: bool, tareas: list<string>, pendientes: list<string>, fallas: list<string>} */
    public function revisar(bool $aplicar): array
    {
        $this->tareas = $this->pendientes = $this->fallas = [];

        $ast       = DB::connection('asterisk_rt');
        $endpoints = $ast->table('ps_endpoints')->get()->keyBy('id');
        $auths     = $ast->table('ps_auths')->get()->keyBy('id');
        $aors      = $ast->table('ps_aors')->pluck('id')->flip();

        $this->extensiones($aplicar, $endpoints, $auths, $aors);
        $adoptadas = $this->troncales($aplicar, $endpoints, $auths);
        $this->sobrantes($aplicar, $endpoints, $adoptadas);
        $this->colasYPlan($aplicar);

        return ['aplicado' => $aplicar, 'tareas' => $this->tareas, 'pendientes' => $this->pendientes, 'fallas' => $this->fallas];
    }

    // ── Extensiones ────────────────────────────────────────────────────────────

    private function extensiones(bool $aplicar, Collection $endpoints, Collection $auths, Collection $aors): void
    {
        foreach (Extension::orderBy('numero')->get() as $ext) {
            $id    = $ext->endpointId();
            $ep    = $endpoints[$id] ?? null;
            $pwAst = self::contrasena($auths["{$id}_auth"] ?? null);
            $plano = $ext->secret_plain;
            $num   = $ext->numero;

            if (! $ext->activo) {
                if ($ep) {
                    $this->hacer($aplicar, "{$num}: está inactiva en MegaISP; quitarla de Asterisk",
                        fn () => $this->prov->desprovisionarExtension($ext));
                }
                continue;
            }

            if ($plano === null && $pwAst !== null) {
                $this->hacer($aplicar, "{$num}: recuperar en MegaISP la contraseña que tiene Asterisk",
                    fn () => self::guardarSecreto('voip_extensiones', $ext->id, $pwAst));
                $plano = $pwAst;
            } elseif ($plano !== null && $pwAst !== null && ! hash_equals($pwAst, $plano)) {
                $this->pendientes[] = "{$num}: la contraseña de MegaISP y la de Asterisk no coinciden. "
                    . 'Ponle en Extensiones la que tiene configurada el teléfono.';
                continue;
            }

            $falta = ! $ep || ! isset($aors[$id]) || ! isset($auths["{$id}_auth"]);
            if ($falta) {
                if ($plano === null) {
                    // No existe en Asterisk: ningún teléfono la puede estar usando.
                    $this->hacer($aplicar, "{$num}: no existe en Asterisk; darla de alta con contraseña nueva", function () use ($ext) {
                        self::guardarSecreto('voip_extensiones', $ext->id, Str::password(16, symbols: false));
                        $this->prov->provisionarExtension($ext->fresh());
                    });
                } else {
                    $this->hacer($aplicar, "{$num}: no existe en Asterisk; darla de alta",
                        fn () => $this->prov->provisionarExtension($ext->fresh()));
                }
                continue;
            }

            if ($pwAst === null && $plano === null) {
                $this->pendientes[] = "{$num}: no tiene contraseña ni en MegaISP ni en Asterisk. "
                    . 'Ponle una nueva en Extensiones y la misma en el teléfono.';
                continue;
            }

            $diferencias = self::diferencias($this->prov->datosEndpoint($ext), (array) $ep);
            if ($pwAst === null) {
                $diferencias[] = 'contraseña';
            }
            if ($diferencias) {
                $this->hacer($aplicar, "{$num}: actualizar en Asterisk (" . implode(', ', $diferencias) . ')',
                    fn () => $this->prov->provisionarExtension($ext->fresh()));
            } elseif (! $ext->provisionado_at) {
                $this->hacer($aplicar, "{$num}: ya está en Asterisk; marcarla como provisionada",
                    fn () => $ext->update(['provisionado_at' => now()->toDateTimeString()]));
            }
        }
    }

    // ── Troncales ──────────────────────────────────────────────────────────────

    /** @return list<string> endpoints de troncales viejas cuyas credenciales adoptó una de MegaISP */
    private function troncales(bool $aplicar, Collection $endpoints, Collection $auths): array
    {
        $huerfanas = $endpoints->keys()
            ->filter(fn ($id) => preg_match('/^trunk_(\d+)$/', $id, $m) && ! Troncal::whereKey($m[1])->exists())
            ->values();
        $adoptadas = [];

        foreach (Troncal::orderBy('id')->get() as $t) {
            $id    = $t->endpointId();
            $ep    = $endpoints[$id] ?? null;
            $plano = $t->secret_plain;

            if ($t->tipo === 'registro' && $plano === null) {
                $pw = self::contrasena($auths["{$id}_auth"] ?? null);
                $de = $id;
                if ($pw === null) {
                    foreach ($huerfanas as $vieja) {
                        $auth = $auths["{$vieja}_auth"] ?? null;
                        if ($auth && $auth->username === $t->usuario && self::contrasena($auth) !== null && $this->mismoHost($vieja, $t)) {
                            $pw = self::contrasena($auth);
                            $de = $vieja;
                            $adoptadas[] = $vieja;
                            break;
                        }
                    }
                }
                if ($pw !== null) {
                    $this->hacer($aplicar, "Troncal «{$t->nombre}»: recuperar su contraseña desde Asterisk ({$de})",
                        fn () => self::guardarSecreto('voip_troncales', $t->id, $pw));
                } else {
                    $this->pendientes[] = "Troncal «{$t->nombre}»: su contraseña no se puede leer y Asterisk no la tiene. Vuelve a capturarla en Troncales.";
                    continue;
                }
            }

            if (! $t->activo) {
                if ($ep) {
                    $this->hacer($aplicar, "Troncal «{$t->nombre}»: está inactiva; quitarla de Asterisk",
                        fn () => $this->prov->desprovisionar($t));
                }
                continue;
            }

            if (! $ep) {
                $this->hacer($aplicar, "Troncal «{$t->nombre}»: no existe en Asterisk; darla de alta",
                    fn () => $this->prov->provisionar($t->fresh()));
            } elseif (! $t->provisionado_at) {
                $this->hacer($aplicar, "Troncal «{$t->nombre}»: ya está en Asterisk; marcarla como provisionada",
                    fn () => $t->update(['provisionado_at' => now()->toDateTimeString()]));
            }
        }

        return $adoptadas;
    }

    private function mismoHost(string $endpoint, Troncal $t): bool
    {
        $match = (string) DB::connection('asterisk_rt')->table('ps_endpoint_id_ips')->where('endpoint', $endpoint)->value('match');
        $ips   = filter_var($t->host, FILTER_VALIDATE_IP) ? [$t->host] : (gethostbynamel((string) $t->host) ?: []);

        foreach ($ips as $ip) {
            if (str_contains($match, $ip)) {
                return true;
            }
        }

        return false;
    }

    // ── Lo que Asterisk tiene y MegaISP no ─────────────────────────────────────

    private function sobrantes(bool $aplicar, Collection $endpoints, array $adoptadas): void
    {
        $extensiones = Extension::get()->map->endpointId()->flip();
        $troncales   = Troncal::get()->map->endpointId()->flip();

        foreach ($endpoints->keys() as $id) {
            if (isset($extensiones[$id]) || isset($troncales[$id])) {
                continue;
            }
            if (preg_match('/^trunk_(\d+)$/', $id, $m)) {
                if (in_array($id, $adoptadas, true)) {
                    $this->hacer($aplicar, "Quitar de Asterisk la troncal vieja {$id} (la reemplaza la de MegaISP con sus mismas credenciales)", function () use ($m) {
                        $vieja = new Troncal(['nombre' => "trunk_{$m[1]}"]);
                        $vieja->id = (int) $m[1];
                        $this->prov->desprovisionar($vieja);
                    });
                } else {
                    $this->pendientes[] = "Asterisk tiene la troncal {$id} y MegaISP no. Revísala antes de quitarla.";
                }
                continue;
            }
            $this->pendientes[] = "Asterisk tiene la extensión {$id} y MegaISP no. No se borra por si algún teléfono la usa; dala de alta en Extensiones o pide que se quite.";
        }
    }

    // ── Colas y plan de marcado ────────────────────────────────────────────────

    private function colasYPlan(bool $aplicar): void
    {
        $ast = DB::connection('asterisk_rt');

        foreach (GrupoTimbrado::with('extensiones')->where('activo', true)->where('es_cola', true)->get() as $g) {
            $esperado = $g->extensiones->map(fn ($e) => "PJSIP/{$e->numero}")->sort()->values()->all();
            $actual   = $ast->table('queue_members')->where('queue_name', $g->nombreCola())->pluck('interface')->sort()->values()->all();
            $nombres  = $ast->table('queue_members')->where('queue_name', $g->nombreCola())->pluck('membername', 'interface');
            $malNombre = $g->extensiones->contains(fn ($e) => ($nombres["PJSIP/{$e->numero}"] ?? null) !== $e->nombre);

            if ($esperado !== $actual || $malNombre) {
                $this->hacer($aplicar, "Cola «{$g->nombre}»: poner en Asterisk los mismos miembros que en MegaISP",
                    fn () => $this->prov->provisionarCola($g->fresh('extensiones')));
            }
        }

        $this->hacer($aplicar, 'Volver a generar y cargar el plan de marcado (grupos, troncales y llamadas a la calle)',
            fn () => $this->dialplan->regenerar());
    }

    // ── Ayudantes ──────────────────────────────────────────────────────────────

    private function hacer(bool $aplicar, string $texto, callable $accion): void
    {
        if (! $aplicar) {
            $this->tareas[] = $texto;

            return;
        }
        try {
            $accion();
            $this->tareas[] = $texto;
        } catch (\Throwable $e) {
            $this->fallas[] = "{$texto} — falló: {$e->getMessage()}";
        }
    }

    private static function contrasena(?object $auth): ?string
    {
        $pw = $auth?->password;

        return $pw === null || $pw === '' ? null : (string) $pw;
    }

    private static function guardarSecreto(string $tabla, int $id, string $plano): void
    {
        // Directo a la tabla: sin pasar por observers ni re-provisionar en medio.
        DB::table($tabla)->where('id', $id)->update(['secret' => Crypt::encryptString($plano)]);
    }

    /**
     * Columnas en las que ps_endpoints no tiene lo que la extensión pide. Null y cadena
     * vacía cuentan igual (Asterisk guarda vacío lo que no se configuró).
     *
     * @return list<string>
     */
    public static function diferencias(array $esperado, array $actual): array
    {
        $dif = [];
        foreach ($esperado as $columna => $valor) {
            $a = $actual[$columna] ?? null;
            $a = $a === '' ? null : $a;
            $v = $valor === '' ? null : $valor;
            if ((string) $a !== (string) $v) {
                $dif[] = $columna;
            }
        }

        return $dif;
    }
}
