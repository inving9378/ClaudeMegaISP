<?php

namespace App\Modules\Addons\Roadmap\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Configuración de la Torre — fila ÚNICA (singleton).
 *
 * No se lee directo: se lee por `TorreConfigService::get()`, que la cachea e invalida al guardar.
 * Leerla a pelo desde varios sitios sería reabrir el mismo problema que esta tabla viene a cerrar.
 */
class TorreConfig extends Model
{
    protected $table = 'torre_config';

    /** Niveles válidos del TECHO GLOBAL, de menos a más automático. El orden importa. */
    public const NIVELES = ['manual', 'estandar', 'asistido', 'autonomo'];

    /**
     * Techo de `nivel_riesgo` que cada nivel de automatización permite aprobar A LA MÁQUINA.
     * `null` = la máquina no aprueba nada; todo va a Irving.
     *
     * Es la traducción entre el vocabulario del panel (que Irving lee) y el del circuito
     * (`nivel_riesgo` A/B/C). Vive aquí y en ningún otro lado.
     */
    public const TECHO_POR_NIVEL = [
        'manual'   => null,
        'estandar' => 'A',
        'asistido' => 'B',
        'autonomo' => 'C',
    ];

    /** Modos de la válvula de contexto. `ablandar` nunca deja pasar; `apagar` es el de antes. */
    public const VALVULA_MODOS = ['ablandar', 'apagar'];

    protected $fillable = [
        'nivel_automatizacion',
        'autopilot_max_nivel',
        'auditor_activo',
        'auditor_max_por_corrida',
        'auditor_cooldown_min',
        'auditor_slots_libres_min',
        'auditor_gasto_reintento_min',
        'auditor_gasto_reintento_activo',
        'paralelo_mismo_modulo',
        'valvula_activa',
        'valvula_modo',
        'valvula_guarda_termino',
        'valvula_guarda_razon',
        'jarvis_icono',
        'mencion_retiene_categorias',
    ];

    protected $casts = [
        'auditor_activo'          => 'boolean',
        'auditor_max_por_corrida' => 'integer',
        'auditor_cooldown_min'    => 'integer',
        'auditor_slots_libres_min' => 'integer',
        'auditor_gasto_reintento_min' => 'integer',
        'auditor_gasto_reintento_activo' => 'boolean',
        'paralelo_mismo_modulo'   => 'integer',
        'valvula_activa'          => 'boolean',
        'valvula_guarda_termino'  => 'boolean',
        'valvula_guarda_razon'    => 'boolean',
        'mencion_retiene_categorias' => 'array',
    ];

    /**
     * Sub-techo del autopilot. `null` en la columna = **lo gobierna `config/circuito.php`**, que es
     * el estado de fábrica; sólo cuando Irving mueve la perilla en pantalla la columna manda.
     *
     * Se resuelve aquí y no en el llamador para que no haya dos lugares decidiendo qué gana.
     */
    public function autopilotMaxNivel(): string
    {
        $col = strtoupper((string) $this->autopilot_max_nivel);
        if (in_array($col, ['A', 'B', 'C'], true)) {
            return $col;
        }

        $cfg = strtoupper((string) config('circuito.autopilot.max_nivel', 'B'));

        return in_array($cfg, ['A', 'B', 'C'], true) ? $cfg : 'B';
    }

    /** De dónde salió el sub-techo vigente — la pantalla lo muestra junto al valor. */
    public function autopilotMaxNivelFuente(): string
    {
        return in_array(strtoupper((string) $this->autopilot_max_nivel), ['A', 'B', 'C'], true)
            ? 'tabla torre_config (lo fijaste en pantalla)'
            : 'config/circuito.php → circuito.autopilot.max_nivel';
    }

    /**
     * Cuántas terminales pueden trabajar el MISMO módulo a la vez. `null` en la columna = **lo
     * gobierna `config/circuito.php`** (estado de fábrica, 1 = comportamiento histórico); sólo
     * cuando Irving mueve la perilla en pantalla la columna manda. Rango sano 1-6 (tope de
     * terminales del circuito); fuera de rango se sanea al default de config.
     *
     * Se resuelve aquí y no en el llamador para que no haya dos lugares decidiendo qué gana —
     * mismo patrón que `autopilotMaxNivel()`.
     */
    public function paraleloMismoModulo(): int
    {
        $col = (int) $this->paralelo_mismo_modulo;
        if ($this->paralelo_mismo_modulo !== null && $col >= 1 && $col <= 6) {
            return $col;
        }

        return max(1, (int) config('circuito.paralelo_mismo_modulo', 1));
    }

    /** De dónde salió el tope vigente — la pantalla lo muestra junto al valor. */
    public function paraleloMismoModuloFuente(): string
    {
        $col = (int) $this->paralelo_mismo_modulo;

        return ($this->paralelo_mismo_modulo !== null && $col >= 1 && $col <= 6)
            ? 'tabla torre_config (lo fijaste en pantalla)'
            : 'config/circuito.php → circuito.paralelo_mismo_modulo';
    }

    /** El modo vigente de la válvula, saneado. */
    public function valvulaModo(): string
    {
        return in_array($this->valvula_modo, self::VALVULA_MODOS, true) ? $this->valvula_modo : 'ablandar';
    }

    /**
     * Item #9990256 — categorías que retienen un item aunque la válvula lo haya sellado como MERA
     * MENCIÓN (ver `Support\MencionFrontera::retiene()`). `null` en la columna = **lo gobierna
     * `config/circuito.php`** (estado de fábrica); sólo cuando Irving toca la perilla en pantalla
     * la columna manda — incluido guardar `[]`, que es la decisión EXPLÍCITA de que ninguna
     * categoría retiene una mención. Mismo patrón que `autopilotMaxNivel()`/`paraleloMismoModulo()`.
     *
     * FALLA-SEGURA: si la columna trae algo que no es un array (fila vieja, dato corrupto), se
     * ignora y cae al default de config — nunca se interpreta basura como «lista vacía», que
     * abriría la frontera sola.
     */
    public function mencionRetieneCategorias(): array
    {
        if (is_array($this->mencion_retiene_categorias)) {
            return array_values(array_filter(array_map('strval', $this->mencion_retiene_categorias)));
        }

        return (array) config(
            'circuito.mencion_retiene_categorias',
            \App\Modules\Addons\Roadmap\Support\MencionFrontera::RETIENEN_POR_DEFECTO
        );
    }

    /** De dónde salió la lista vigente — la pantalla lo muestra junto al valor. */
    public function mencionRetieneCategoriasFuente(): string
    {
        return is_array($this->mencion_retiene_categorias)
            ? 'tabla torre_config (lo fijaste en pantalla)'
            : 'config/circuito.php → circuito.mencion_retiene_categorias';
    }

    /**
     * El techo de `nivel_riesgo` (A|B|C|null) que implica el nivel de automatización vigente.
     *
     * ⚠️ `array_key_exists` y NO `??`: el `null` de `manual` es un valor LEGÍTIMO del mapa («la
     * máquina no aprueba nada»), y `??` no lo distingue de «la clave no existe» — con `??` el modo
     * `manual` caía al fallback `'A'` y seguía aprobando items nivel A. Encontrado al verificar la
     * semántica del override el 2026-08-19, antes de cablearlo.
     *
     * Un valor desconocido en la columna cae a `'A'`, el más restrictivo que sigue siendo útil.
     */
    public function techoGlobal(): ?string
    {
        return array_key_exists($this->nivel_automatizacion, self::TECHO_POR_NIVEL)
            ? self::TECHO_POR_NIVEL[$this->nivel_automatizacion]
            : 'A';
    }
}
