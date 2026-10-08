<?php

namespace App\Services\Asistente\Entrenamiento;

use App\Models\Asistente\Accion;
use App\Models\User;
use App\Services\Asistente\AsistenteService;
use App\Services\Asistente\ConocimientoAsistente;
use Illuminate\Support\Facades\DB;

/**
 * Corre una prueba de resources/asistente/pruebas.json contra JARVIS de verdad (la IA asignada,
 * sus herramientas, su memoria) dentro de una transacción que se deshace al final: ni la plática,
 * ni los cambios que prepare, ni lo que anote quedan guardados.
 */
class Evaluador
{
    public function __construct(private ConocimientoAsistente $conocimiento)
    {
    }

    /**
     * @return array{id:string, tema:string, ok:bool, fallas:string[], avisos:string[], herramientas:string[], accion:?string, respuesta:string, segundos:float}
     */
    public function correr(array $prueba, User $usuario): array
    {
        $inicio = microtime(true);
        $respuesta = '';
        $herramientas = [];
        $accion = null;
        $error = null;

        $anterior = auth()->user();
        DB::beginTransaction();
        try {
            auth()->setUser($usuario);
            $servicio = app(AsistenteService::class);
            $conversacion = $servicio->nuevaConversacion($usuario);
            foreach ((array) ($prueba['turnos'] ?? []) as $turno) {
                $r = $servicio->responder($usuario, (string) $turno, $prueba['pantalla'] ?? '/');
                $respuesta = (string) ($r['mensaje'] ?? '');
            }
            $herramientas = DB::table('asistente_mensajes')->where('conversacion_id', $conversacion->id)
                ->where('rol', 'herramienta')->orderBy('id')->pluck('herramienta')->filter()->values()->all();
            $accion = Accion::where('conversacion_id', $conversacion->id)->where('estado', Accion::PENDIENTE)
                ->latest('id')->value('herramienta');
            $sustituciones = $this->sustituciones($prueba);
        } catch (\Throwable $e) {
            $error = $e->getMessage();
            $sustituciones = [];
        } finally {
            DB::rollBack();
            $anterior ? auth()->setUser($anterior) : auth()->forgetUser();
        }

        [$fallas, $avisos] = $error !== null
            ? [["Tronó: {$error}"], []]
            : $this->revisar($prueba, $respuesta, $herramientas, $accion, $sustituciones);

        return [
            'id'           => (string) ($prueba['id'] ?? '?'),
            'tema'         => (string) ($prueba['tema'] ?? 'General'),
            'ok'           => $fallas === [],
            'fallas'       => $fallas,
            'avisos'       => $avisos,
            'herramientas' => $herramientas,
            'accion'       => $accion,
            'respuesta'    => $respuesta,
            'segundos'     => round(microtime(true) - $inicio, 1),
        ];
    }

    /** @return array{0:string[],1:string[]} fallas y avisos */
    private function revisar(array $p, string $respuesta, array $herramientas, ?string $accion, array $sust): array
    {
        $fallas = [];
        $avisos = [];
        $texto = mb_strtolower($respuesta);
        // Pedir datos cuenta como pregunta aunque no lleve "?" ("Para darlo de alta me falta: 1)...").
        $pregunta = str_contains($respuesta, '?')
            || (bool) preg_match('/\b(necesito|me falta|me faltan|dime|ind[ií]came|proporci[oó]name|comp[aá]rteme|p[aá]same)\b/u', mb_strtolower($respuesta));

        if (trim($respuesta) === '' || str_contains($respuesta, 'Me tomó demasiados pasos')) {
            $fallas[] = 'No dio una respuesta.';
        }
        if (!empty($p['usa_alguna']) && !array_intersect($p['usa_alguna'], $herramientas) && !(!empty($p['o_pregunta']) && $pregunta)) {
            $fallas[] = 'Debía usar ' . implode(' o ', $p['usa_alguna']) . '; usó: ' . ($herramientas ? implode(', ', array_unique($herramientas)) : 'ninguna');
        }
        if (!empty($p['no_usa']) && ($mal = array_intersect($p['no_usa'], $herramientas))) {
            $fallas[] = 'No debía usar ' . implode(', ', array_unique($mal));
        }
        if (!empty($p['debe_preguntar']) && (!$pregunta || $accion)) {
            $fallas[] = $accion ? "Debía preguntar y preparó un cambio ({$accion})." : 'Debía terminar preguntando lo que falta.';
        }
        if (!empty($p['accion']) && $accion !== $p['accion']) {
            $fallas[] = "Debía dejar preparado {$p['accion']}; quedó: " . ($accion ?? 'nada');
        }
        if (!empty($p['sin_accion']) && $accion) {
            $fallas[] = "No debía preparar ningún cambio y preparó {$accion}.";
        }
        if (!empty($p['contiene_alguno']) && !array_filter($p['contiene_alguno'], fn ($x) => str_contains($texto, mb_strtolower(strtr($x, $sust))))) {
            $fallas[] = 'La respuesta debía mencionar: ' . implode(' o ', $p['contiene_alguno']);
        }
        foreach ((array) ($p['contiene_todos'] ?? []) as $x) {
            if (!str_contains($texto, mb_strtolower(strtr($x, $sust)))) {
                $fallas[] = "La respuesta debía mencionar: {$x}";
            }
        }
        foreach ((array) ($p['no_contiene'] ?? []) as $x) {
            $x = strtr($x, $sust);
            if ($x !== '' && !str_contains($x, '{{') && str_contains($texto, mb_strtolower($x))) {
                $fallas[] = "La respuesta no debía decir: {$x}";
            }
        }

        // Reglas que valen para todas: sin lenguaje técnico y solo enlaces internos.
        if (preg_match('#https?://#i', $respuesta)) {
            $fallas[] = 'Puso un enlace con dominio (debe ser /ruta).';
        }
        $tecnicas = array_filter($this->tecnicas(), fn ($w) => preg_match('/(?<!\p{L})' . preg_quote($w, '/') . '(?!\p{L})/u', $texto));
        if ($tecnicas) {
            $fallas[] = 'Usó lenguaje técnico: ' . implode(', ', $tecnicas);
        }
        if (preg_match('/algún dato|algun dato|más información|mas informacion/u', $texto) && $pregunta) {
            $avisos[] = 'Pidió datos de forma vaga ("algún dato"/"más información").';
        }
        if (substr_count(trim($respuesta), "\n") > 14) {
            $avisos[] = 'Respuesta larga (' . (substr_count(trim($respuesta), "\n") + 1) . ' renglones).';
        }

        return [$fallas, $avisos];
    }

    private function tecnicas(): array
    {
        return ['endpoint', 'base de datos', 'tabla', 'json', 'api', 'query', 'sql', 'controlador', 'laravel', 'php', 'backend', 'frontend', 'migración'];
    }

    /** {{password_cliente:N}} → la contraseña real de ese cliente (para comprobar que no la suelta). */
    private function sustituciones(array $p): array
    {
        $s = [];
        foreach (array_merge((array) ($p['no_contiene'] ?? []), (array) ($p['contiene_alguno'] ?? []), (array) ($p['contiene_todos'] ?? [])) as $x) {
            if (preg_match_all('/\{\{password_cliente:(\d+)\}\}/', $x, $m)) {
                foreach ($m[1] as $id) {
                    $s["{{password_cliente:{$id}}}"] = (string) DB::table('client_main_information')->where('client_id', $id)->value('password');
                }
            }
        }

        return $s;
    }
}
