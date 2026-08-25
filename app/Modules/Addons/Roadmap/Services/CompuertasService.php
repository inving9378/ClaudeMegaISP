<?php

namespace App\Modules\Addons\Roadmap\Services;

use App\Modules\Addons\Roadmap\Console\CompuertasSondaCommand;
use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use App\Modules\Addons\Roadmap\Support\Compuerta;
use App\Modules\Addons\Roadmap\Support\ThomasVigilia;
use Illuminate\Support\Facades\DB;

/**
 * Tablero de compuertas de la Torre — motor de medición.
 *
 * Devuelve la cadena de activación del circuito en ORDEN: la primera compuerta en rojo
 * es la que contesta "DETENIDO POR: ...". El orden no es cosmético, es el orden real en
 * que el circuito se frena (cron → pausa → política → slots → items).
 *
 * DOS ORÍGENES, DECLARADOS SIEMPRE:
 *  - `bd` / `config`: se mide aquí mismo, en vivo, en cada request.
 *  - `so`: NO se puede medir desde php-fpm (`www-data` no entra a `/home/meganet`,
 *    que es `drwx------`). Sale del snapshot de `circuito:compuertas-sonda`, y la
 *    frescura de ese snapshot es a su vez una compuerta. Un tablero que afirmara
 *    estado del SO sin poder verlo repetiría el error del 24-ago.
 *
 * Ninguna medición lanza: si algo no se puede medir se dice, no se asume que pasa.
 */
class CompuertasService
{
    /** Un snapshot más viejo que esto ya no describe el presente. */
    private const SNAPSHOT_FRESCO_SEG = 180;

    public function __construct(
        private RoadmapCircuitoService $circuito,
        private TorreAutomationPolicy $politica,
    ) {
    }

    /** @return array{linea:string, detenido_por:?string, compuertas:array, snapshot:array} */
    public function tablero(): array
    {
        $so = $this->snapshot();

        $compuertas = [
            $this->cBaseDeDatos(),
            $this->cSnapshot($so),
            $this->cThomas(),
            $this->cCron($so),
            $this->cPausa(),
            $this->cEjecutorHuerfano($so),
            $this->cWorkers($so),
            $this->cNivelAutopilot(),
            $this->cTerminales($so),
            $this->cItemsDespachables(),
            $this->cEstaciones(),
            $this->cAgendados(),
            $this->cAuditor(),
            $this->cReservadosMuertos($so),
            $this->cCascadaErrores($so),
        ];

        // Punto 1 — por qué está gris cada control. Se resuelve en una sola pasada para
        // que ninguna fila pueda escaparse sin motivo escrito.
        //
        // Las que sí necesitan el sistema operativo van a 'sin_privilegio' (y ya traen el
        // comando exacto). Las que solo se MIDEN, pero para las que nunca se construyó un
        // control, van a 'no_implementado': se dice, no se disfraza de deshabilitado.
        $soloMedidas = ['items', 'estacion', 'cascada'];
        foreach ($compuertas as $c) {
            if ($c->acciones !== []) {
                [$ctl, $mot, $falta]  = $this->resolverControl($c->acciones);
                $c->control           = $ctl;
                $c->controlMotivo     = $mot;
                $c->permisoFaltante   = $falta;
                continue;
            }
            // Una fila en verde no tiene control gris: no hay nada que soltar. Etiquetarla
            // 'no implementado' sería ruido que compite con los grises que sí importan.
            if ($c->semaforo === 'verde') {
                $c->control = 'sin_necesidad';
                continue;
            }
            if (in_array($c->clave, $soloMedidas, true)) {
                $c->control       = 'no_implementado';
                $c->controlMotivo = 'Esta compuerta se mide, pero todavía no tiene un control propio en el panel. '
                    . 'El comando de al lado sirve para diagnosticarla a mano.';
                continue;
            }
            if ($c->comando !== null) {
                $c->control = 'sin_privilegio';
            }
        }

        $primerRojo = null;
        foreach ($compuertas as $c) {
            if ($c->bloquea()) {
                $primerRojo = $c;
                break;
            }
        }

        return [
            'linea'        => $primerRojo ? 'DETENIDO POR: ' . $primerRojo->nombre : 'CORRIENDO',
            'corriendo'    => $primerRojo === null,
            'detenido_por' => $primerRojo?->clave,
            'compuertas'   => array_map(fn (Compuerta $c) => $c->toArray(), $compuertas),
            'snapshot'     => [
                'disponible' => $so !== null,
                'medido_en'  => $so['medido_en'] ?? null,
                'edad_seg'   => $so ? max(0, time() - (int) ($so['medido_ts'] ?? 0)) : null,
            ],
            'medido_en'    => now()->toIso8601String(),
        ];
    }

    /** Lee el snapshot del SO. Devuelve null si no existe o no es legible. */
    private function snapshot(): ?array
    {
        $ruta = storage_path('app/' . CompuertasSondaCommand::SNAPSHOT);
        if (! is_readable($ruta)) {
            return null;
        }
        $j = json_decode((string) @file_get_contents($ruta), true);

        return is_array($j) ? $j : null;
    }

    // ------------------------------------------------------------------ compuertas

    /**
     * La primera de todas, y la que faltaba el 22-ago: si la BD no responde, el freno
     * de mano del circuito (`isPaused`, que lee `settings`) lanza excepción y el circuito
     * queda sin poder pausarse. Por eso encabeza la cadena.
     */
    private function cBaseDeDatos(): Compuerta
    {
        try {
            $faltan = [];
            foreach (['settings', 'roadmap_items', 'torre_config'] as $t) {
                if (! DB::getSchemaBuilder()->hasTable($t)) {
                    $faltan[] = $t;
                }
            }

            if ($faltan !== []) {
                return new Compuerta(
                    clave: 'bd', nombre: 'Base de datos del circuito', semaforo: 'rojo',
                    valor: 'faltan tablas: ' . implode(', ', $faltan), origen: 'bd',
                    porQue: 'Sin estas tablas el circuito no puede ni consultar su propio freno de mano, así que no puede detenerse solo.',
                    comando: 'php artisan migrate --force   # revisar antes: php artisan migrate:status',
                    quienPuede: 'Irving, desde consola en el servidor',
                control: 'sin_privilegio',
            );
            }

            return new Compuerta(
                clave: 'bd', nombre: 'Base de datos del circuito', semaforo: 'verde',
                valor: 'settings, roadmap_items y torre_config presentes', origen: 'bd',
            );
        } catch (\Throwable $e) {
            return new Compuerta(
                clave: 'bd', nombre: 'Base de datos del circuito', semaforo: 'rojo',
                valor: 'sin conexión', origen: 'bd',
                porQue: 'No responde: ' . mb_substr($e->getMessage(), 0, 120),
                comando: 'systemctl status mysql',
                quienPuede: 'Irving, con sudo en el servidor',
                control: 'sin_privilegio',
            );
        }
    }

    /** La frescura del snapshot es una compuerta: sin ella, todo lo del SO es un rumor. */
    private function cSnapshot(?array $so): Compuerta
    {
        $cmd = 'php artisan circuito:compuertas-sonda   # y revisar su línea en el crontab de meganet';

        if ($so === null) {
            return new Compuerta(
                clave: 'snapshot', nombre: 'Lectura del sistema operativo', semaforo: 'rojo',
                valor: 'no hay snapshot', origen: 'so',
                porQue: 'El panel corre como www-data y no puede mirar procesos, locks ni crontab; sin la sonda no sabe nada del SO.',
                comando: $cmd, quienPuede: 'Irving o el cron de meganet',
                control: 'sin_privilegio',
            );
        }

        $edad = max(0, time() - (int) ($so['medido_ts'] ?? 0));
        if ($edad > self::SNAPSHOT_FRESCO_SEG) {
            return new Compuerta(
                clave: 'snapshot', nombre: 'Lectura del sistema operativo', semaforo: 'rojo',
                valor: "última medición hace {$edad}s", origen: 'so',
                porQue: 'El snapshot está viejo: lo que se muestre del SO puede no ser el presente. La sonda no está corriendo.',
                comando: $cmd, quienPuede: 'Irving o el cron de meganet',
                control: 'sin_privilegio',
            );
        }

        return new Compuerta(
            clave: 'snapshot', nombre: 'Lectura del sistema operativo', semaforo: 'verde',
            valor: "medido hace {$edad}s", origen: 'so',
        );
    }

    private function cCron(?array $so): Compuerta
    {
        if ($so === null) {
            return $this->sinMedir('cron', 'Cron del ejecutor', 'crontab -l | grep deploy/circuito', 'Irving, como usuario meganet');
        }

        $act = (int) ($so['cron']['activas'] ?? 0);
        $pau = (int) ($so['cron']['pausadas'] ?? 0);

        if ($act === 0) {
            return new Compuerta(
                clave: 'cron', nombre: 'Cron del ejecutor', semaforo: 'rojo',
                valor: "0 líneas activas ({$pau} comentadas)", origen: 'so',
                porQue: $pau > 0
                    ? 'Las líneas del circuito están comentadas en el crontab: alguien lo pausó a propósito.'
                    : 'No hay ninguna línea del circuito en el crontab: el ejecutor no se dispara nunca.',
                comando: "crontab -e   # descomentar las líneas 'PAUSADO-...' de deploy/circuito",
                quienPuede: 'Irving, como usuario meganet (el panel corre como www-data y no puede tocar ese crontab)',
                control: 'sin_privilegio',
            );
        }

        return new Compuerta(
            clave: 'cron', nombre: 'Cron del ejecutor', semaforo: 'verde',
            valor: "{$act} líneas activas", origen: 'so',
        );
    }

    private function cPausa(): Compuerta
    {
        try {
            $pausado = $this->circuito->isPaused();
        } catch (\Throwable $e) {
            return new Compuerta(
                clave: 'pausa', nombre: 'Freno de mano del circuito', semaforo: 'rojo',
                valor: 'ilegible', origen: 'bd',
                porQue: 'La bandera vive en la tabla settings y no se pudo leer, así que el circuito tampoco puede consultarla.',
                comando: 'php artisan tinker --execute=\'DB::table("settings")->where("key","circuito_pausado")->value("value");\'',
                quienPuede: 'Irving, desde consola',
                control: 'sin_privilegio',
            );
        }

        if ($pausado) {
            $acc = [[
                    'clave'       => 'reanudar',
                    'etiqueta'    => 'Quitar el freno',
                    'peligrosa'   => true,
                    'confirmar'   => 'El circuito volverá a lanzar vueltas automáticas en cuanto el cron esté activo. '
                        . 'Cada vuelta ejecuta un agente con permisos de escritura sobre el repositorio.',
                'permiso'     => 'circuito.pause',
            ]];
            [$ctl, $mot, $falta] = $this->resolverControl($acc);

            return new Compuerta(
                clave: 'pausa', nombre: 'Freno de mano del circuito', semaforo: 'rojo',
                valor: 'PAUSADO', origen: 'bd',
                porQue: 'El kill switch está puesto: el scheduler no lanza ninguna vuelta.',
                acciones: $acc, control: $ctl, controlMotivo: $mot, permisoFaltante: $falta,
            );
        }

        $acc = [[
            'clave'     => 'pausar',
            'etiqueta'  => 'Poner el freno',
            'peligrosa' => false,
            'confirmar' => 'El circuito dejará de lanzar vueltas nuevas. Las vueltas en curso siguen hasta terminar.',
            'permiso'   => 'circuito.pause',
        ]];
        [$ctl, $mot, $falta] = $this->resolverControl($acc);

        return new Compuerta(
            clave: 'pausa', nombre: 'Freno de mano del circuito', semaforo: 'verde',
            valor: 'suelto', origen: 'bd',
            acciones: $acc, control: $ctl, controlMotivo: $mot, permisoFaltante: $falta,
        );
    }

    /**
     * La compuerta que no existía el 22-ago. Una vuelta reparentada a init sobrevive a
     * pausar el cron, y corrió 1d21h lanzando un agente cada 3.7 s.
     */
    private function cEjecutorHuerfano(?array $so): Compuerta
    {
        if ($so === null) {
            return $this->sinMedir('ejecutor', 'Ejecutor huérfano o colgado', "ps -eo pid,ppid,etimes,cmd | grep '[v]uelta.sh'", 'Irving, en el servidor');
        }

        $e         = $so['ejecutor'] ?? [];
        $huerfanas = (int) ($e['huerfanas'] ?? 0);
        $maxSeg    = (int) ($e['mas_vieja_seg'] ?? 0);
        $timeout   = (int) ($e['timeout_nominal'] ?? 600);
        $agentes   = (int) ($e['agentes_claude'] ?? 0);
        $pids      = implode(' ', $e['pids_huerfanos'] ?? []);

        if ($huerfanas > 0) {
            return new Compuerta(
                clave: 'ejecutor', nombre: 'Ejecutor huérfano o colgado', semaforo: 'rojo',
                valor: "{$huerfanas} vuelta(s) huérfana(s) (PPID=1), la más vieja {$maxSeg}s, {$agentes} agentes vivos",
                origen: 'so',
                porQue: 'Una vuelta reparentada a init ya no depende del cron: pausar el circuito NO la detiene, y sigue lanzando agentes.',
                comando: "kill {$pids}   # verificar después: ps -eo pid,ppid,cmd | grep '[v]uelta.sh'",
                quienPuede: 'Irving, como usuario meganet',
                control: 'sin_privilegio',
            );
        }

        if ($maxSeg > $timeout * 2) {
            return new Compuerta(
                clave: 'ejecutor', nombre: 'Ejecutor huérfano o colgado', semaforo: 'ambar',
                valor: "vuelta viva de {$maxSeg}s (timeout nominal {$timeout}s)", origen: 'so',
                porQue: 'Una vuelta lleva más del doble de su timeout: el timeout aplica al agente hijo, no al bucle padre.',
                comando: "ps -eo pid,ppid,etimes,cmd | grep '[v]uelta.sh'",
                quienPuede: 'Irving, como usuario meganet',
                control: 'sin_privilegio',
            );
        }

        return new Compuerta(
            clave: 'ejecutor', nombre: 'Ejecutor huérfano o colgado', semaforo: 'verde',
            valor: ($e['vueltas'] ?? 0) . ' vuelta(s) viva(s), ninguna huérfana', origen: 'so',
        );
    }

    private function cWorkers(?array $so): Compuerta
    {
        $cmd = 'sudo supervisorctl status megaisp-deploy-worker megaisp-queue-worker-1 megaisp-queue-worker-2';
        if ($so === null) {
            return $this->sinMedir('workers', 'Workers de supervisor', $cmd, 'Irving, con sudo');
        }

        $w      = $so['workers'] ?? [];
        $vivos  = (int) ($w['procesos_vivos'] ?? 0);
        $esper  = (int) ($w['esperados'] ?? 3);

        if ($vivos === 0) {
            return new Compuerta(
                clave: 'workers', nombre: 'Workers de supervisor', semaforo: 'rojo',
                valor: "0 de {$esper} procesos vivos", origen: 'so',
                porQue: 'Sin workers no se procesa ninguna cola: ni deploy, ni cobranza, ni los jobs del circuito.',
                comando: 'sudo supervisorctl start megaisp-deploy-worker megaisp-queue-worker-1 megaisp-queue-worker-2',
                quienPuede: 'Irving, con sudo (el panel corre como www-data y no puede hablar con supervisor)',
                control: 'sin_privilegio',
            );
        }

        if ($vivos < $esper) {
            return new Compuerta(
                clave: 'workers', nombre: 'Workers de supervisor', semaforo: 'ambar',
                valor: "{$vivos} de {$esper} procesos vivos", origen: 'so',
                porQue: 'Faltan workers: algunas colas avanzan y otras no.',
                comando: $cmd, quienPuede: 'Irving, con sudo',
                control: 'sin_privilegio',
            );
        }

        // Regla 5: lo que manda es el proceso vivo, no lo que declare supervisor.
        $nota = ($w['ctl_legible'] ?? false) ? '' : ' (supervisorctl no legible desde la web; el dato es el conteo de procesos reales)';

        return new Compuerta(
            clave: 'workers', nombre: 'Workers de supervisor', semaforo: 'verde',
            valor: "{$vivos} de {$esper} procesos vivos{$nota}", origen: 'so',
        );
    }

    private function cNivelAutopilot(): Compuerta
    {
        try {
            $nivel = $this->politica->nivelAutomatizacion();
            $base  = $this->politica->politicaBase();
        } catch (\Throwable $e) {
            return $this->sinMedir('nivel', 'Nivel del autopilot', 'php artisan tinker --execute=\'dump(app(App\Modules\Addons\Roadmap\Services\TorreAutomationPolicy::class)->panorama());\'', 'Irving');
        }

        $acciones = [[
            'clave'     => 'nivel',
            'etiqueta'  => 'Cambiar nivel',
            'peligrosa' => true,
            'confirmar' => 'Subir el nivel amplía qué items puede tomar el circuito sin pedir permiso. '
                . 'Los items de nivel C (dinero, seguridad, infraestructura) nunca se automatizan.',
            'permiso'   => 'torre.config.edit',
            'opciones'  => ['manual', 'asistido', 'autonomo'],
        ]];

        if ($base === null) {
            return new Compuerta(
                clave: 'nivel', nombre: 'Nivel del autopilot', semaforo: 'rojo',
                valor: $nivel . ' (sin política base)', origen: 'bd',
                porQue: 'En modo manual el circuito no despacha ningún item por su cuenta: todo espera decisión humana.',
                acciones: $acciones,
            );
        }

        // Punto 4 — verde solo si el techo es A. La regla del circuito es que únicamente
        // el nivel A puede aprobarse solo; con el techo en B o C el circuito despacha por
        // su cuenta cosas que deberían pasar por una persona. Eso no es "todo bien": pasa,
        // pero con advertencia. Pintarlo verde daba a entender lo contrario.
        $soloA = $base === 'A';

        return new Compuerta(
            clave: 'nivel', nombre: 'Nivel del autopilot', semaforo: $soloA ? 'verde' : 'ambar',
            valor: "{$nivel} — despacha hasta nivel {$base}", origen: 'bd',
            porQue: $soloA
                ? null
                : "El techo está en {$base}, pero solo el nivel A puede aprobarse solo. "
                    . "Con este techo el circuito toma por su cuenta items de nivel {$base} que deberían pasar por Irving.",
            acciones: $acciones,
        );
    }

    private function cTerminales(?array $so): Compuerta
    {
        if ($so === null) {
            return $this->sinMedir('terminales', 'Terminales libres', 'ls /home/meganet/circuito/wt-*.lock', 'Irving, como usuario meganet');
        }

        $s      = $so['slots'] ?? [];
        $libres = count($s['libres'] ?? []);
        $total  = (int) ($s['total'] ?? 0);

        if ($libres === 0 && $total > 0) {
            return new Compuerta(
                clave: 'terminales', nombre: 'Terminales libres', semaforo: 'rojo',
                valor: "0 de {$total} libres", origen: 'so',
                porQue: 'Todos los worktrees tienen su flock tomado: no hay dónde lanzar una vuelta nueva.',
                comando: "ps -eo pid,etimes,cmd | grep '[v]uelta.sh'   # ver qué las ocupa antes de liberar",
                quienPuede: 'Irving, como usuario meganet',
                control: 'sin_privilegio',
            );
        }

        if ($s['scheduler_tomado'] ?? false) {
            return new Compuerta(
                clave: 'terminales', nombre: 'Terminales libres', semaforo: 'ambar',
                valor: "{$libres} de {$total} libres, scheduler.lock tomado", origen: 'so',
                porQue: 'El lock del scheduler está tomado: si no hay scheduler vivo, quedó huérfano y bloquea las corridas.',
                comando: 'ls -l /home/meganet/circuito/scheduler.lock',
                quienPuede: 'Irving, como usuario meganet',
                control: 'sin_privilegio',
            );
        }

        return new Compuerta(
            clave: 'terminales', nombre: 'Terminales libres', semaforo: 'verde',
            valor: "{$libres} de {$total} libres", origen: 'so',
        );
    }

    private function cItemsDespachables(): Compuerta
    {
        try {
            $n = RoadmapItem::query()->despachable()->count();
        } catch (\Throwable $e) {
            return $this->sinMedir('items', 'Items despachables', 'php artisan circuito:flags', 'Irving');
        }

        if ($n === 0) {
            return new Compuerta(
                clave: 'items', nombre: 'Items despachables', semaforo: 'ambar',
                valor: '0 items listos', origen: 'bd',
                porQue: 'No hay trabajo elegible: o todo está aprobado y hecho, o los frenos por item lo retienen (ver las filas de estación y agendados).',
                comando: 'php artisan circuito:flags',
                quienPuede: 'Cualquiera con acceso a la Torre',
                control: 'sin_privilegio',
            );
        }

        return new Compuerta(
            clave: 'items', nombre: 'Items despachables', semaforo: 'verde',
            valor: "{$n} items listos para tomar", origen: 'bd',
        );
    }

    private function cEstaciones(): Compuerta
    {
        try {
            $conteo = [];
            foreach (RoadmapItem::query()->whereNull('archivado_at')->get(['id', 'status', 'estado_aprobacion', 'branch', 'archivado_at']) as $i) {
                $e = $i->estacion;
                $conteo[$e] = ($conteo[$e] ?? 0) + 1;
            }
            arsort($conteo);
            $txt = implode(' · ', array_map(fn ($k, $v) => "{$k}: {$v}", array_keys($conteo), $conteo));
        } catch (\Throwable $e) {
            return $this->sinMedir('estacion', 'Estación de los items', 'php artisan circuito:flags', 'Irving');
        }

        $enIntegracion = $conteo['integracion'] ?? 0;

        return new Compuerta(
            clave: 'estacion', nombre: 'Estación de los items', semaforo: $enIntegracion > 10 ? 'ambar' : 'verde',
            valor: $txt ?: 'sin items', origen: 'bd',
            porQue: $enIntegracion > 10
                ? "Hay {$enIntegracion} items esperando integración: el circuito no auto-mergea, los merges se disparan desde la UI."
                : null,
            comando: $enIntegracion > 10 ? 'Revisar la pestaña Integración de la Torre' : null,
            quienPuede: $enIntegracion > 10 ? 'Irving, desde la Torre' : null,
                control: 'sin_privilegio',
            );
    }

    private function cAgendados(): Compuerta
    {
        try {
            $n = RoadmapItem::query()->whereNotNull('agendado_para')->where('agendado_para', '>', now())->count();
            $prox = RoadmapItem::query()->whereNotNull('agendado_para')->where('agendado_para', '>', now())
                ->orderBy('agendado_para')->value('agendado_para');
        } catch (\Throwable $e) {
            return $this->sinMedir('agendados', 'Items agendados a futuro', 'php artisan circuito:reactivar-agendados --dry', 'Irving');
        }

        if ($n === 0) {
            return new Compuerta(
                clave: 'agendados', nombre: 'Items agendados a futuro', semaforo: 'verde',
                valor: 'ninguno diferido', origen: 'bd',
            );
        }

        return new Compuerta(
            clave: 'agendados', nombre: 'Items agendados a futuro', semaforo: 'ambar',
            valor: "{$n} diferidos, el próximo el " . ($prox ? substr((string) $prox, 0, 10) : '—'), origen: 'bd',
            porQue: 'Estos items existen pero no se despachan hasta su fecha; no cuentan como trabajo disponible.',
            acciones: [[
                'clave'     => 'reactivar_agendados',
                'etiqueta'  => 'Reactivar los vencidos',
                'peligrosa' => true,
                'confirmar' => 'Los items cuya fecha ya pasó volverán a la cola y podrán ser tomados por el circuito.',
                'permiso'   => 'torre.config.edit',
            ]],
        );
    }

    private function cAuditor(): Compuerta
    {
        try {
            $cfg = DB::table('torre_config')->first();
        } catch (\Throwable $e) {
            return $this->sinMedir('auditor', 'Auditor: cooldown y debounce', 'php artisan circuito:auditor --dry', 'Irving');
        }

        $activo   = (bool) ($cfg->auditor_activo ?? false);
        $cooldown = (int) ($cfg->auditor_cooldown_min ?? 0);
        $cap      = (int) ($cfg->auditor_max_por_corrida ?? 0);

        if (! $activo) {
            return new Compuerta(
                clave: 'auditor', nombre: 'Auditor: cooldown y debounce', semaforo: 'ambar',
                valor: 'apagado', origen: 'bd',
                porQue: 'El auditor no revisa la cola: los items no se re-triagean ni se detectan bucles.',
                acciones: [[
                    'clave'     => 'auditor_encender',
                    'etiqueta'  => 'Encender el auditor',
                    'peligrosa' => true,
                    'confirmar' => 'El auditor volverá a recorrer la cola y podrá reclasificar items automáticamente.',
                    'permiso'   => 'torre.config.edit',
                ]],
            );
        }

        return new Compuerta(
            clave: 'auditor', nombre: 'Auditor: cooldown y debounce', semaforo: 'verde',
            valor: "activo · cooldown {$cooldown} min · máx {$cap} por corrida", origen: 'bd',
        );
    }

    /**
     * Items marcados como tomados por una terminal que ya no existe. Es el estado en el
     * que quedan las cosas cuando una vuelta muere sin soltar lo suyo.
     */
    private function cReservadosMuertos(?array $so): Compuerta
    {
        try {
            $reservados = RoadmapItem::query()
                ->whereNotNull('worker_sid')
                ->where('estado_aprobacion', 'en_progreso')
                ->get(['id', 'worker_sid', 'updated_at']);
        } catch (\Throwable $e) {
            return $this->sinMedir('reservados', 'Items reservados por terminales muertas', 'php artisan circuito:reap-stuck --minutes=25', 'Irving');
        }

        if ($reservados->isEmpty()) {
            return new Compuerta(
                clave: 'reservados', nombre: 'Items reservados por terminales muertas', semaforo: 'verde',
                valor: 'ninguno reservado', origen: 'bd',
            );
        }

        // Cruce con el SO: un sid reservado cuyo slot está LIBRE es una terminal muerta.
        $ocupados = $so['slots']['ocupados'] ?? null;
        $muertos  = [];
        foreach ($reservados as $r) {
            if ($ocupados !== null && ! in_array($r->worker_sid, $ocupados, true)) {
                $muertos[] = $r;
            }
        }

        if ($ocupados === null) {
            return new Compuerta(
                clave: 'reservados', nombre: 'Items reservados por terminales muertas', semaforo: 'ambar',
                valor: $reservados->count() . ' reservados (sin snapshot no se sabe si viven)', origen: 'bd',
                porQue: 'Hay items marcados en progreso, pero sin lectura del SO no se puede saber si su terminal sigue viva.',
                comando: 'php artisan circuito:compuertas-sonda', quienPuede: 'Irving o el cron de meganet',
                control: 'sin_privilegio',
            );
        }

        if ($muertos !== []) {
            return new Compuerta(
                clave: 'reservados', nombre: 'Items reservados por terminales muertas', semaforo: 'rojo',
                valor: count($muertos) . ' item(s) atrapados: #' . implode(', #', array_column($muertos, 'id')),
                origen: 'bd+so',
                porQue: 'Su terminal ya no existe (el slot está libre) pero el item sigue marcado en progreso: nadie más lo puede tomar.',
                acciones: [[
                    'clave'     => 'soltar_items',
                    'etiqueta'  => 'Soltar estos items',
                    'peligrosa' => true,
                    'confirmar' => 'Los items volverán a la cola con worker_sid vacío y podrán ser tomados por otra terminal. '
                        . 'Si su rama tiene trabajo sin commitear, ese trabajo NO se pierde pero queda sin dueño.',
                    'permiso'   => 'torre.config.edit',
                    'ids'       => array_column($muertos, 'id'),
                ]],
            );
        }

        return new Compuerta(
            clave: 'reservados', nombre: 'Items reservados por terminales muertas', semaforo: 'verde',
            valor: $reservados->count() . ' en progreso, todos con terminal viva', origen: 'bd+so',
        );
    }

    /**
     * INTERRUPTOR DE HOMBRE MUERTO DEL VIGILANTE.
     *
     * Thomas no puede compartir destino con lo que vigila: si se muere, tiene que notarse aquí y
     * no en la ausencia de avisos. El 24-ago su vuelta quedó colgada del cron del scheduler, que
     * es una de las nueve líneas comentadas del crontab — estuvo un día entero sin latir sin que
     * ninguna pantalla lo dijera. Un supervisor muerto en silencio convierte el silencio en falsa
     * calma, y ése es el peor modo de fallo de todos.
     *
     * Se lee de ARCHIVO (`storage/app/circuito/thomas/latido.json`), no de la base: si la base es
     * el problema, preguntarle a la base si el vigilante vive es la pregunta equivocada.
     */
    private function cThomas(): Compuerta
    {
        $cmd = 'php artisan circuito:thomas-vigilar --print   # y revisar su línea propia en el crontab de meganet';
        $edad = ThomasVigilia::edadSeg();

        if ($edad === null) {
            return new Compuerta(
                clave: 'thomas', nombre: 'Vigilancia de Thomas', semaforo: 'rojo',
                valor: 'nunca ha medido', origen: 'so',
                porQue: 'No existe latido de la vigilia: o nunca arrancó su cron, o no puede escribir su estado. '
                    . 'Nadie está mirando disco, memoria, logs ni procesos.',
                comando: $cmd, quienPuede: 'Irving o el cron de meganet',
                control: 'sin_privilegio',
            );
        }

        $umbral = ThomasVigilia::umbralLatidoSeg();
        $estado = ThomasVigilia::estado();
        $modo   = (string) ($estado['modo'] ?? 'desconocido');
        $alertas = count($estado['alertas'] ?? []);

        if ($edad > $umbral) {
            return new Compuerta(
                clave: 'thomas', nombre: 'Vigilancia de Thomas', semaforo: 'rojo',
                valor: "midió hace {$edad}s (umbral {$umbral}s)", origen: 'so',
                porQue: 'El latido del vigilante envejeció: lo que se muestre de disco, memoria y procesos '
                    . 'puede no ser el presente. Se murió o no está corriendo su cron.',
                comando: $cmd, quienPuede: 'Irving o el cron de meganet',
                control: 'sin_privilegio',
            );
        }

        // Modo mínimo NO es rojo: el vigilante está vivo y midiendo, sólo que sin base. Pintarlo
        // rojo escondería la fila de la base, que es la que de verdad bloquea.
        if ($modo === 'minimo') {
            return new Compuerta(
                clave: 'thomas', nombre: 'Vigilancia de Thomas', semaforo: 'ambar',
                valor: "midió hace {$edad}s · MODO MÍNIMO (la base no responde)", origen: 'so',
                porQue: 'Thomas está midiendo desde archivo porque MySQL no contesta. Lo que reporte del '
                    . 'sistema es real; lo que sepa de items, no.',
                comando: $cmd, quienPuede: 'Irving',
                control: 'sin_privilegio',
            );
        }

        return new Compuerta(
            clave: 'thomas', nombre: 'Vigilancia de Thomas', semaforo: 'verde',
            valor: "midió hace {$edad}s · {$alertas} alerta(s)", origen: 'so',
        );
    }

    /** 268.896 excepciones en dos días sin que nadie se enterara. */
    private function cCascadaErrores(?array $so): Compuerta
    {
        if ($so === null) {
            return $this->sinMedir('cascada', 'Cascada de errores', 'tail -f storage/logs/laravel.log', 'Irving');
        }

        $l      = $so['logs'] ?? [];
        $errMin = (int) ($l['errores_ult_min'] ?? 0);
        $bytes  = (int) ($l['laravel_log_bytes'] ?? 0);
        $mb     = round($bytes / 1048576, 1);

        if ($errMin > 60) {
            return new Compuerta(
                clave: 'cascada', nombre: 'Cascada de errores', semaforo: 'rojo',
                valor: "{$errMin} errores en el último minuto · log {$mb} MB", origen: 'so',
                porQue: 'El sistema está fallando en bucle. A este ritmo el log crece sin control y el error real queda enterrado.',
                comando: 'tail -50 storage/logs/laravel.log',
                quienPuede: 'Irving, en el servidor',
                control: 'sin_privilegio',
            );
        }

        if ($mb > 500) {
            return new Compuerta(
                clave: 'cascada', nombre: 'Cascada de errores', semaforo: 'ambar',
                valor: "log de {$mb} MB · {$errMin} errores/min · disco " . ($l['disco_uso_pct'] ?? '?'),
                origen: 'so',
                porQue: 'El log pasó de medio giga sin rotación: llegó a 1.7 GB durante el incidente del 22-ago.',
                comando: ': > storage/logs/laravel.log   # preservar antes lo que sirva de evidencia',
                quienPuede: 'Irving, en el servidor',
                control: 'sin_privilegio',
            );
        }

        return new Compuerta(
            clave: 'cascada', nombre: 'Cascada de errores', semaforo: 'verde',
            valor: "{$errMin} errores/min · log {$mb} MB · disco " . ($l['disco_uso_pct'] ?? '?'), origen: 'so',
        );
    }


    /**
     * Marca cada acción como usable o no, y devuelve el estado de control de la fila.
     * Punto 1 del entregable: un control gris tiene que decir POR QUÉ está gris —
     * si es falta de privilegio del proceso, falta de un permiso concreto, o que
     * simplemente no está hecho.
     *
     * @param  array<int,array>  $acciones  se modifican en sitio
     * @return array{0:string,1:?string,2:?string}  [control, motivo, permisoFaltante]
     */
    private function resolverControl(array &$acciones): array
    {
        $u = auth()->user();
        $faltante = null;

        foreach ($acciones as &$a) {
            $permiso = $a['permiso'] ?? null;
            $puede   = $permiso === null || ($u !== null && $u->can($permiso));
            $a['disponible'] = $puede;
            $a['motivo']     = $puede ? null : "Te falta el permiso `{$permiso}`.";
            if (! $puede && $faltante === null) {
                $faltante = $permiso;
            }
        }
        unset($a);

        if ($acciones === []) {
            return ['no_implementado', null, null];
        }

        $hayUsable = false;
        foreach ($acciones as $a) {
            if ($a['disponible']) {
                $hayUsable = true;
                break;
            }
        }

        return $hayUsable ? ['disponible', null, null] : ['sin_permiso', null, $faltante];
    }

    /** @return array{0:string,1:?string,2:?string} fila que solo se puede tocar fuera del panel. */
    private function controlSinPrivilegio(): array
    {
        return ['sin_privilegio', null, null];
    }

    /** Fila para lo que no se pudo medir: ámbar, nunca verde, y siempre con salida. */
    private function sinMedir(string $clave, string $nombre, string $comando, string $quien): Compuerta
    {
        return new Compuerta(
            clave: $clave, nombre: $nombre, semaforo: 'ambar',
            valor: 'no medible desde el panel', origen: 'so',
            porQue: 'El panel corre como www-data y no tiene visibilidad sobre esto. Se muestra el comando para verificarlo a mano.',
            comando: $comando, quienPuede: $quien,
                control: 'sin_privilegio',
            );
    }
}
