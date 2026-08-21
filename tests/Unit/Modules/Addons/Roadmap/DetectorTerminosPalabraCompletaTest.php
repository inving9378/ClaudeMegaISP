<?php

namespace Tests\Unit\Modules\Addons\Roadmap;

use App\Modules\Addons\Roadmap\Support\DetectorTerminos;
use PHPUnit\Framework\TestCase; // TestCase PURO de PHPUnit: NO bootea Laravel, NO toca BD.

/**
 * CANDADO #865 — «palabra completa, no substring» (lección de #338, CONTEXTO-MEGAISP.md §8.4-quinquies).
 *
 * La lección se documentó DOS VECES sin quedar blindada: `RevisorService::enAlcance()` seguía
 * usando `Str::contains` crudo sobre las 40 palabras del denylist mucho después de que
 * `ThomasService::apareceComoPalabra()` ya la aplicaba con palabra completa. Un aprendizaje
 * documentado sobre un defecto vivo no es un aprendizaje: es un recordatorio de que ahí sigue.
 *
 * Irving migró `enAlcance()`/`triarNivelNull()`/`categoriaFronteraDura()` a la definición ÚNICA
 * (`DetectorTerminos`, commit 5fab2f88) directo en main, antes de que este item llegara a
 * ejecución. Lo que faltaba del plan aprobado (#865, opción 1: "tests unitarios con los casos
 * falsos positivos históricos") es este archivo — sin él, la próxima persona puede reintroducir
 * `Str::contains` en el denylist y nada lo para.
 *
 * Casos reales, no inventados:
 *   · «cola» dentro de «colaborador» — el incidente que documenta el §8.4-quinquies (Portal
 *     Colaborador cayendo en el módulo del circuito porque 'cola' es de verdad un término vivo en
 *     `config('circuito.clasificador.reglas')`).
 *   · «cargo» (denylist de dinero, `config('circuito.revisor.alcance.denylist')`) dentro de
 *     «encargo» — mismo patrón, distinto término, para no depender de un solo caso.
 *   · `deploy/circuito/npm-build.sh` — el falso positivo medido en el propio commit del fix
 *     (50 de 163 items en 30 días), resuelto por `limpiar()` y no por el matcher de palabra.
 *
 * ⚠️ HALLAZGO al escribir este candado (registrado como #904, resuelto aquí abajo): `enAlcance()`
 * llamaba `DetectorTerminos::dispara($heno, $kw)` SIN el 3er argumento para las 40 palabras del
 * denylist → todas corrían con `$palabraCompleta = false` (el modo flex para términos "largos e
 * inequívocos"). Términos cortos/ambiguos como `secret` heredaban ese mismo modo y sí disparaban
 * como prefijo de una palabra real no relacionada (`secret` → `secretaria`/`secretario`,
 * verificado). Es la MISMA clase de bug que este archivo documenta para 'cola'.
 *
 * #904 — Fix: `config('circuito.revisor.alcance.denylist_word')` (nueva lista, junto a la
 * `denylist` flex existente) separa los cortos/ambiguos con match de palabra completa, igual que
 * `RevisorService::TRIAJE_C_PLAIN`/`TRIAJE_C_WORD` ya hacía para el triaje de nivel null.
 * Auditados los ~35 términos contra el diccionario es_ES (aspell): `secret` colisionaba con 133
 * palabras reales (secretaria/secretario/secretaría/secretariado/secretismo/secretor…) y `precio`
 * con 11 (precioso/preciosa/preciosidad/preciosismo…) — ambos movidos a `denylist_word`, con sus
 * flexiones legítimas (secreto/secreta/secretos/secretas, precios) enumeradas a mano para no
 * perder cobertura real. Candidatos vistos ('cargo', 'saldo', 'pago') se verificaron con casos
 * reales y NO tienen colisión práctica → se quedaron en modo flex, sin mover de más.
 */
class DetectorTerminosPalabraCompletaTest extends TestCase
{
    /**
     * 'cola' es corto/ambiguo (igual que 'iva'/'rol'/'prod' en `RevisorService::TRIAJE_C_WORD`):
     * necesita `$palabraCompleta = true` (ancla también el final) para no comerse el resto de una
     * palabra más larga. Con `false` (el flex de términos largos como 'facturación'), `\bcola\w*`
     * SÍ se come 'borador' y dispara igual — por eso el histórico usa el modo estricto, no el
     * default. Ver hallazgo relacionado registrado como sub-item (denylist de `enAlcance()` no
     * distingue cortos/ambiguos de largos y usa `false` para los 40, sin excepción).
     */
    public function test_cola_no_dispara_por_ser_substring_de_colaborador(): void
    {
        $heno = mb_strtolower('Portal de Colaborador — Fase A Mi material');

        $this->assertFalse(DetectorTerminos::dispara($heno, 'cola', true),
            "'cola' disparó dentro de 'colaborador': volvió el bug de substring que documenta #338.");
    }

    public function test_cola_si_dispara_como_palabra_independiente(): void
    {
        $heno = mb_strtolower('la cola de mensajes del listener se atoró');

        $this->assertTrue(DetectorTerminos::dispara($heno, 'cola', true),
            "'cola' no disparó en un uso real de la palabra: el matcher quedó demasiado estricto.");
    }

    public function test_termino_del_denylist_no_dispara_por_substring_en_otra_palabra(): void
    {
        $heno = mb_strtolower('atender el encargo del cliente en mostrador');

        $this->assertFalse(DetectorTerminos::dispara($heno, 'cargo'),
            "'cargo' (denylist dinero) disparó dentro de 'encargo': mismo patrón de #338 con otro término.");
    }

    public function test_termino_del_denylist_si_dispara_en_uso_real(): void
    {
        $heno = mb_strtolower('se aplicó un cargo extra en la tarjeta del cliente');

        $this->assertTrue(DetectorTerminos::dispara($heno, 'cargo'),
            "'cargo' no disparó en un uso real de dinero: el pre-filtro de alcance dejaría pasar frontera dura.");
    }

    public function test_limpiar_quita_la_ruta_de_la_herramienta_para_que_deploy_no_dispare(): void
    {
        $texto = "Verificación:\n  bash deploy/circuito/npm-build.sh\nEl resto del item no toca despliegue.";
        $heno  = mb_strtolower(DetectorTerminos::limpiar($texto));

        $this->assertFalse(DetectorTerminos::dispara($heno, 'deploy'),
            "'deploy' disparó por la ruta de la propia herramienta del circuito (deploy/circuito/npm-build.sh), "
            . 'el falso positivo medido en 50 de 163 items que motivó limpiar().');
    }

    public function test_negacion_cercana_exime_el_termino(): void
    {
        $heno = mb_strtolower('Este cambio no toca dinero ni permisos, solo texto de un botón.');

        $this->assertFalse(DetectorTerminos::dispara($heno, 'dinero'),
            "'dinero' disparó pese a la negación explícita ('no toca dinero'): #844 dejó de aplicar.");
    }

    public function test_mencion_sin_negacion_si_dispara(): void
    {
        $heno = mb_strtolower('Hay que ajustar el cálculo de dinero pendiente por cobrar.');

        $this->assertTrue(DetectorTerminos::dispara($heno, 'dinero'),
            "'dinero' no disparó sin negación cerca: la ventana de negación se volvió demasiado ancha.");
    }

    /**
     * #904 — caso reportado: 'secret' (denylist de seguridad) como prefijo de 'secretaria', un
     * puesto de oficina sin relación con credenciales/secretos técnicos.
     */
    public function test_secret_no_dispara_por_ser_prefijo_de_secretaria(): void
    {
        $heno = mb_strtolower('Corregir el formulario de datos de la secretaria de recepción');

        $this->assertFalse(DetectorTerminos::dispara($heno, 'secret', true),
            "'secret' disparó dentro de 'secretaria': el mismo bug de #338/#865 con este término.");
    }

    /**
     * #904 — la flexión legítima ('secreto'/'secreta') se enumera a mano en
     * `denylist_word` (en vez de dejar que el modo flex la adivine) para no perder cobertura real
     * al mover 'secret' a match de palabra completa.
     */
    public function test_secreto_si_dispara_como_palabra_independiente(): void
    {
        $heno = mb_strtolower('Hay que guardar la clave secreta del cliente cifrada');

        $this->assertTrue(DetectorTerminos::dispara($heno, 'secreta', true),
            "'secreta' no disparó en un uso real de seguridad: se perdió cobertura al mover el término.");
    }

    /**
     * #904 — segundo caso encontrado en la auditoría contra diccionario es_ES: 'precio' (denylist
     * de dinero) como prefijo de 'precioso', un adjetivo común sin relación con tarifas/precios.
     */
    public function test_precio_no_dispara_por_ser_prefijo_de_precioso(): void
    {
        $heno = mb_strtolower('El diseño del nuevo dashboard quedó precioso');

        $this->assertFalse(DetectorTerminos::dispara($heno, 'precio', true),
            "'precio' disparó dentro de 'precioso': el mismo bug de #338/#865 con este término.");
    }

    /** #904 — 'precios' (plural) sigue disparando en un uso real de tarifas. */
    public function test_precios_si_dispara_en_uso_real(): void
    {
        $heno = mb_strtolower('Hay que actualizar los precios de los planes de Internet');

        $this->assertTrue(DetectorTerminos::dispara($heno, 'precios', true),
            "'precios' no disparó en un uso real de tarifas: se perdió cobertura al mover el término.");
    }
}
