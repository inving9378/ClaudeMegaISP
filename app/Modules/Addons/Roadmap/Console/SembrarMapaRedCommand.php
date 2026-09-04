<?php

namespace App\Modules\Addons\Roadmap\Console;

use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use Illuminate\Console\Command;

/**
 * Siembra de los 29 items del módulo MAPA DE RED (MR-00 … MR-28).
 *
 * IDEMPOTENTE: dedupe por `title` exacto. Re-ejecutar no duplica; los items que ya existen se
 * reportan como omitidos y no se tocan (salvo el enlace al paraguas, que se repara si falta).
 *
 * DECISIONES DE IRVING APLICADAS EN ESTA SIEMBRA (2026-09-03):
 *
 *  1. Los 29 nacen `estado_aprobacion = aprobado_irving` (salvo MR-27/MR-28 en `requiere_irving`)
 *     PERO con `excluir_pool_automatico = true`. Motivo: `aprobado_irving` es estado ELEGIBLE para
 *     el pool (ver `RoadmapItem::sqlElegibleParaPool()`) y el circuito NO está pausado — sin el
 *     freno, la Torre repartiría los 29 sola, en paralelo y sin respetar la secuencia (MR-05
 *     copiaría datos antes de que MR-02 hiciera el respaldo). El documento de origen es explícito:
 *     "No ejecutar ninguno de los items. La ejecución arranca en una sesión aparte con MR-01."
 *     Para arrancar: liberar el flag de MR-01 desde la Torre, item por item, en orden.
 *     El freno además protege a MR-00 del bucle reap de paraguas (#738/#745/#830/#816/#818/#848/#878).
 *
 *  2. Se siembran los 29 que el cuerpo del documento define (el encabezado decía 28 por error de
 *     conteo). `requiere_irving` va en MR-27 y MR-28, que son los que el listado marca así.
 *
 *  3. Cuatro referencias cruzadas venían con off-by-one de un renumerado previo y se CORRIGIERON
 *     al sembrar: MR-11 (MR-15→MR-16), MR-12 (MR-18→MR-19), MR-13 (MR-17→MR-18) y MR-14
 *     (MR-16→MR-17, MR-20→MR-21).
 *
 * MAPEO DE CAMPOS (el documento de origen usa nombres que no existen en la tabla):
 *   `prompt_para_claude` → `prompt` · `item_padre` → `origen_item_id` · `tipo` → NO EXISTE
 *   (los items de respuesta se marcan por título `[RESPUESTA]` + `origen_item_id`).
 */
class SembrarMapaRedCommand extends Command
{
    protected $signature = 'roadmap:sembrar-mapa-red
                            {--dry-run : Imprime el plan sin escribir nada}';

    protected $description = 'Siembra idempotente de los 29 items del módulo MAPA DE RED (MR-00 a MR-28).';

    /**
     * Bloque obligatorio al final del `prompt` de LOS 29 items.
     *
     * Versión vigente entregada por Irving (2026-09-03), que corrige la del documento original:
     * ésta ya NO instruye `tipo='respuesta'` (esa columna no existe en `roadmap_items`; el item de
     * respuesta se marca por título `[RESPUESTA]` + `origen_item_id`), fija `nivel_riesgo` mínimo
     * `B` con su motivo —un `A` puede quedar `aprobado_claude` y saltarse al supervisor— y deja
     * `excluir_pool_automatico` sujeto a la política vigente del pool.
     *
     * `sincronizaCanal()` la propaga: re-ejecutar el comando reemplaza el bloque en los items ya
     * sembrados sin tocar el cuerpo de su `prompt`.
     */
    private const CANAL_RESPUESTA = <<<'TXT'
## Canal de respuesta (obligatorio)

Antes de marcar este item como `completado`, evalúa tu propio reporte. Si
contiene ALGUNA de estas cinco cosas, crea un item de respuesta:

1. Pregunta abierta que no pudiste resolver sin criterio humano.
2. Decisión que no te toca (alcance, arquitectura, negocio).
3. Hallazgo fuera de alcance (algo roto que no era parte de este item).
4. Desviación: hiciste algo distinto a lo pedido y hay que ratificarlo.
5. Riesgo asumido o trabajo a medias con un supuesto por confirmar.

Si nada de eso aplica, NO crees nada. Un cierre limpio no genera respuesta.

El item de respuesta nace con título `[RESPUESTA] {título de este item} —
{resumen en ≤10 palabras}`, `origen_item_id`={id de este item},
`estado_aprobacion='requiere_irving'`, `nivel_riesgo` mínimo `B` (nunca `A`:
un item A puede quedar `aprobado_claude` y saltarse al supervisor), y
`excluir_pool_automatico` según la política vigente del pool.
Máximo UNO por item origen: varias preguntas se consolidan en una lista.
Cuerpo: Contexto · Qué se hizo · Qué NO se hizo y por qué · La decisión
pendiente · Opciones con recomendación · Qué se bloquea · Qué validar con
screenshot. La recomendación es obligatoria.
TXT;

    public function handle(): int
    {
        $dry  = (bool) $this->option('dry-run');
        $defs = $this->definiciones();

        if (count($defs) !== 32) {
            $this->error('Definiciones incompletas: se esperaban 32, hay ' . count($defs) . '.');
            return self::FAILURE;
        }

        $this->info($dry ? '── DRY-RUN: no se escribe nada ──' : '── SIEMBRA MAPA DE RED ──');

        $padreId  = null;
        $pos      = (int) RoadmapItem::max('position');
        $filas    = [];
        $creados  = 0;
        $omitidos = 0;

        foreach ($defs as $d) {
            $existente = RoadmapItem::where('title', $d['title'])->first();

            if ($existente) {
                // Idempotencia: no se re-crea ni se pisa el cuerpo del prompt. Sólo dos reparaciones
                // acotadas: el enlace al paraguas (si una corrida anterior murió entre MR-00 y sus
                // hijos) y el bloque de canal de respuesta (para poder corregirlo en los 29 de una
                // pasada, sin tocar nada más de su texto).
                if ($d['codigo'] === 'MR-00') {
                    $padreId = (int) $existente->id;
                }

                $nota = 'ya existía';

                if (! $dry) {
                    if ($d['codigo'] !== 'MR-00' && $padreId && ! $existente->origen_item_id) {
                        $existente->origen_item_id = $padreId;
                        $nota = 'enlace reparado';
                    }
                    // Se sincroniza sobre el texto QUE ESTÁ EN BD, no sobre la definición: así una
                    // edición manual del cuerpo sobrevive y sólo se normaliza el canal.
                    $sinc = $this->sincronizaCanal((string) $existente->prompt);
                    if ($sinc !== (string) $existente->prompt) {
                        $existente->prompt = $sinc;
                        $nota = $nota === 'ya existía' ? 'canal actualizado' : $nota . ' + canal';
                    }
                    if ($existente->isDirty()) {
                        $existente->save();
                    }
                }

                $omitidos++;
                $filas[] = [$existente->id, $d['codigo'], $this->corta($d['title']), $d['nivel'],
                            $existente->estado_aprobacion, $existente->origen_item_id ?? '—', $nota];
                continue;
            }

            if ($dry) {
                $filas[] = ['(nuevo)', $d['codigo'], $this->corta($d['title']), $d['nivel'],
                            $d['estado'], $d['codigo'] === 'MR-00' ? 'null' : '(MR-00)', 'se crearía'];
                $creados++;
                continue;
            }

            $item = RoadmapItem::create([
                'title'               => $d['title'],
                'description'         => $d['description'],
                'prompt'              => $this->sincronizaCanal($d['prompt']),
                'modulo'              => $d['modulo'],
                'nivel_riesgo'        => $d['nivel'],
                'nivel_riesgo_origen' => 'interno',
                'estado_aprobacion'   => $d['estado'],
                'priority'            => $d['priority'],
                'status'              => 'pending',
                'origen_item_id'      => $d['codigo'] === 'MR-00' ? null : $padreId,
                // Freno de despacho: la ejecución la libera Irving item por item (decisión 1).
                'excluir_pool_automatico' => true,
                'position'            => ++$pos,
            ]);

            if ($d['codigo'] === 'MR-00') {
                $padreId = (int) $item->id;
            }

            $creados++;
            $filas[] = [$item->id, $d['codigo'], $this->corta($d['title']), $d['nivel'],
                        $item->estado_aprobacion, $item->origen_item_id ?? '—', 'creado'];
        }

        $this->newLine();
        $this->table(['id', 'código', 'título', 'nivel', 'estado', 'padre', ''], $filas);

        // Parches a items ya sembrados (D31/D32 en MR-00, rúbrica en MR-27, reversión en MR-28).
        $parches = $this->aplicaParches($dry);
        $this->newLine();
        $this->line('<comment>Parches a items ya sembrados</comment>');
        $this->table(['id', 'código', 'qué', 'resultado'], $parches);

        $aplicados = count(array_filter($parches, fn ($f) => $f[3] === 'aplicado'));

        $this->newLine();
        $this->line("Creados: <info>{$creados}</info>   Omitidos (ya existían): <comment>{$omitidos}</comment>   Total definido: " . count($defs));
        $this->line("Parches aplicados: <info>{$aplicados}</info> de " . count($parches));

        if (! $dry) {
            $this->warn('Los ' . count($defs) . ' quedaron con excluir_pool_automatico=true: la Torre NO los despachará.');
            $this->warn('Para arrancar, liberar MR-01 desde la Torre. La ejecución es una sesión aparte.');
        }

        return self::SUCCESS;
    }

    private function corta(string $t, int $n = 52): string
    {
        return mb_strlen($t) > $n ? mb_substr($t, 0, $n - 1) . '…' : $t;
    }

    /**
     * Deja el `prompt` terminando en la versión VIGENTE del canal de respuesta.
     *
     * Corta cualquier bloque de canal previo por su encabezado y pega el actual, así que es
     * idempotente y sirve tanto para construir el prompt de un item nuevo como para actualizar el
     * de uno ya sembrado sin tocar el cuerpo de arriba.
     */
    private function sincronizaCanal(string $prompt): string
    {
        return $this->cuerpoSinCanal($prompt) . "\n\n" . self::CANAL_RESPUESTA;
    }

    /**
     * El `prompt` sin su bloque de canal: todo lo que hay antes del encabezado. Sirve para agregar
     * texto AL CUERPO sin que quede debajo del canal (que siempre va al final).
     */
    private function cuerpoSinCanal(string $prompt): string
    {
        $marca = '## Canal de respuesta (obligatorio)';
        if (($pos = mb_strpos($prompt, $marca)) !== false) {
            $prompt = mb_substr($prompt, 0, $pos);
        }

        return rtrim($prompt);
    }

    /**
     * PARCHES a items YA sembrados, para cerrar huecos sin re-sembrar ni tocar su alcance.
     *
     * Cada parche declara la `marca` que prueba que ya está aplicado: si el campo la contiene, no
     * se escribe. Por eso la segunda corrida del comando no toca nada (DoD de idempotencia).
     */
    private function aplicaParches(bool $dry): array
    {
        $filas = [];

        foreach ($this->parches() as $p) {
            $item = RoadmapItem::find($p['id']);

            if (! $item) {
                $filas[] = [$p['id'], $p['codigo'], $p['que'], 'FALTA el item'];
                continue;
            }

            $actual = (string) $item->{$p['campo']};

            if (mb_strpos($actual, $p['marca']) !== false) {
                $filas[] = [$item->id, $p['codigo'], $p['que'], 'ya aplicado'];
                continue;
            }

            if ($dry) {
                $filas[] = [$item->id, $p['codigo'], $p['que'], 'se aplicaría'];
                continue;
            }

            if ($p['campo'] === 'prompt') {
                // Al cuerpo, nunca debajo del canal: el canal se corta y se repega al final.
                $item->prompt = $this->sincronizaCanal(
                    $this->cuerpoSinCanal($actual) . "\n\n" . rtrim($p['texto'])
                );
            } else {
                $item->{$p['campo']} = rtrim($actual) . "\n\n" . rtrim($p['texto']);
            }

            $item->save();
            $filas[] = [$item->id, $p['codigo'], $p['que'], 'aplicado'];
        }

        return $filas;
    }

    /**
     * Las 29 definiciones, EN ORDEN DE EJECUCIÓN (el orden del documento es la secuencia).
     */
    private function definiciones(): array
    {
        $D = $this->tablaDecisiones();

        return [

        // ───────────────────────── FASE 0 — Salvamento y verdad de partida ─────────────────────────

        [
            'codigo' => 'MR-00', 'nivel' => 'B', 'priority' => 'alta',
            'modulo' => 'Mapa de Red', 'estado' => 'aprobado_irving',
            'title'  => 'MR-00 — Épica: módulo MAPA DE RED (convivencia con Mapas, migración y corte)',
            'description' => <<<TXT
Item paraguas. Construir un módulo nuevo "MAPA DE RED" que convive con el módulo "Mapas" actual sin
degradarlo, partiendo de una copia de sus datos, agregándole el modelo de planta que hoy falta
(puerto, hilo, empalme, splitter con pérdidas, enlace de servicio), motor de trazo y presupuesto
óptico, y una navegación que reemplace el árbol de 7 niveles. Al final se comparan los dos y se
retira el perdedor.

{$D}
TXT,
            'prompt' => <<<'TXT'
No se ejecuta. Es el contenedor de MR-01 a MR-28.

Cualquier terminal que tome un hijo debe leer primero este item completo, en especial la tabla
D1–D30 de su `description`, y NO volver a preguntar nada que ya esté resuelto ahí.

⚠️ Este item es un PARAGUAS: no tiene trabajo propio. No lo reclames para "hacerlo". Cierra solo,
por el hook de cascada del modelo (`RoadmapItem::saved`), cuando el último de sus sub-items cierre.
Reclamarlo para ejecutarlo es el bucle reap ya documentado siete veces en CLAUDE.md
(#738, #745, #830, #816, #818, #848, #878).
TXT,
        ],

        [
            'codigo' => 'MR-01', 'nivel' => 'A', 'priority' => 'alta',
            'modulo' => 'Mapas', 'estado' => 'aprobado_irving',
            'title'  => 'MR-01 — Mapas: auditoría READ-ONLY del modelo actual (Paso 0 del rediseño)',
            'description' => 'Auditoría de solo lectura del módulo Mapas. Cero escrituras: no tocar código, esquema ni datos.',
            'prompt' => <<<'TXT'
Entregá en `comentarios_claude` estos 8 puntos con evidencia (nombres reales de tabla, conteos, rutas):

1. Inventario de tablas del módulo con conteo de filas y el diagrama de relaciones real (FKs
   declaradas + las implícitas por convención).
2. ¿Existen entidades de puerto y de hilo, o la capacidad se guarda como texto/contador? Citar
   tabla y columna.
3. De las 204 rutas de `route:list`, cuáles se invocan desde el frontend y cuáles quedaron muertas
   (grep en Blade/Vue).
4. Conteo por tipo de elemento (pack, cupboard, junction_box, source, splitter, equipo activo/pasivo)
   y cuántos tienen padre nulo o inexistente.
5. Cómo se relaciona hoy un cliente con una caja: ¿campo directo, tabla pivote, o solo texto?
6. Profundidad máxima real del árbol de carpetas y cuántas carpetas tienen un solo hijo.
7. Qué permisos gatean realmente cada grupo de rutas y cuáles caen al bloqueo admin-only por no
   tener permiso propio.
8. Peso en disco de las tablas del módulo y cuántos elementos tienen lat/lng nulos o en (0,0).

**DoD:** los 8 puntos respondidos, cero escrituras.
TXT,
        ],

        [
            'codigo' => 'MR-02', 'nivel' => 'A', 'priority' => 'alta',
            'modulo' => 'Mapas', 'estado' => 'aprobado_irving',
            'title'  => 'MR-02 — Respaldo verificado de las tablas de Mapas + prueba de restauración',
            'description' => 'Antes de copiar nada, tener un respaldo del que sepamos que se puede volver. Recordar el incidente del 25-ago: la BD de dev quedó en 0 tablas.',
            'prompt' => <<<'TXT'
1) `mysqldump` **solo de las tablas del módulo Mapas** (no la BD completa) a
   `/var/backups/mysql/mapas-YYYYMMDD-HHMM.sql.gz`. No inventes un esquema de backup paralelo; usá
   esa ruta que ya existe.
2) Restaurá ese dump en la base `megaisp_restore` (ya existe) y verificá con conteos fila por fila
   que coinciden con el origen.
3) Documentá en el reporte el comando exacto de restauración de emergencia.

**DoD:** dump creado, restauración probada en `megaisp_restore`, conteos idénticos, comando de
vuelta documentado. **Prohibido** tocar `megaisp` (dev) más allá de lectura.
TXT,
        ],

        // ──────────────── FASE 1 — Andamiaje del módulo nuevo (paridad, sin mejoras) ────────────────

        [
            'codigo' => 'MR-03', 'nivel' => 'B', 'priority' => 'alta',
            'modulo' => 'Mapa de Red', 'estado' => 'aprobado_irving',
            'title'  => 'MR-03 — Crear el módulo MapaRed (esqueleto, registro, sidebar, permisos)',
            'description' => 'Alta del módulo nuevo siguiendo el contrato de módulos v0.9. El módulo "Mapas" no se toca.',
            'prompt' => <<<'TXT'
Crear `app/Modules/Addons/MapaRed` con `module.json` completo (incluidos `api_endpoints` declarados
desde el día uno, ≥30% de cobertura real), registro en `module_registry`, entrada de sidebar con
label **"MAPA DE RED"**, y permisos con prefijo `mapa_red_`.

Asignar los permisos nuevos a `super-administrator` y `DESARROLLADOR` y correr
`php artisan permissions:sync-roles`. Blade con `@if(auth()->user()->can('...'))`, nunca `@can()`.
Cerrar con `view:clear && config:clear && route:clear` y warm-up `view:cache && config:cache`.

**DoD:** ambos módulos visibles en el sidebar; entrar a `/mapa-red` da una pantalla vacía pero viva;
`/mapas` sigue funcionando idéntico.
**Screenshot a validar:** sidebar con las dos entradas.
TXT,
        ],

        [
            'codigo' => 'MR-04', 'nivel' => 'B', 'priority' => 'alta',
            'modulo' => 'Mapa de Red', 'estado' => 'aprobado_irving',
            'title'  => 'MR-04 — Esquema espejo mapared_* (migración aditiva)',
            'description' => 'Crear las tablas del módulo nuevo como espejo de las de Mapas, con el prefijo mapared_, más las columnas de geometría de D8 y empresa_id nullable de D30.',
            'prompt' => <<<'TXT'
Migraciones **aditivas** que creen `mapared_*` replicando la estructura auditada en MR-01, agregando:
`lat`/`lng` DECIMAL(10,7), `geom_json` LONGTEXT, `bbox_*`, `empresa_id` nullable, `origen_legacy_id`
(para trazabilidad con el registro viejo) y timestamps.

Índices por `(lat,lng)` y por `origen_legacy_id`.

**Nunca** `ALTER` ni `DROP` sobre tablas del módulo viejo.

**DoD:** `php artisan migrate` corre limpio, las tablas existen vacías, `mapas` intacto (verificar
con conteos antes/después).
TXT,
        ],

        [
            'codigo' => 'MR-05', 'nivel' => 'B', 'priority' => 'alta',
            'modulo' => 'Mapa de Red', 'estado' => 'aprobado_irving',
            'title'  => 'MR-05 — Comando de copia de datos legacy → mapared_* (idempotente, con dry-run)',
            'description' => 'Traer los datos actuales al módulo nuevo sin tocar el origen.',
            'prompt' => <<<'TXT'
Crear `php artisan mapared:importar-legacy {--dry-run} {--zona=} {--truncar}`.

Reglas: lectura pura del módulo viejo; escritura solo en `mapared_*`; idempotente por
`origen_legacy_id` (re-ejecutar no duplica); `--dry-run` imprime el plan sin escribir; `--truncar`
solo vacía tablas `mapared_*` y jamás las del viejo.

El reporte final debe listar: filas leídas, insertadas, actualizadas, **omitidas y por qué**
(lat/lng nula, padre inexistente, tipo desconocido).

**DoD:** dry-run sobre toda la base y corrida real; conteos del viejo y del nuevo coinciden salvo
las omitidas, que quedan listadas una por una.
TXT,
        ],

        [
            'codigo' => 'MR-06', 'nivel' => 'B', 'priority' => 'alta',
            'modulo' => 'Mapa de Red', 'estado' => 'aprobado_irving',
            'title'  => 'MR-06 — Paridad funcional 1:1 de la pantalla actual en MAPA DE RED',
            'description' => 'El módulo nuevo debe hacer, de arranque, exactamente lo mismo que el viejo. Todavía sin mejoras.',
            'prompt' => <<<'TXT'
Portar controladores, vistas y JS del mapa actual al módulo nuevo apuntando a `mapared_*` y a
Leaflet (D7).

Mantener por ahora el árbol y los botones tal cual, aunque los vayamos a reemplazar: primero
paridad, después mejora. Compilar con `npm run prod`.

**DoD:** checklist punto por punto de que cada acción del módulo viejo existe y funciona en el nuevo
con los mismos datos.
**Screenshot a validar:** las dos pantallas lado a lado mostrando el mismo troncal de Tultitlán.
TXT,
        ],

        [
            'codigo' => 'MR-07', 'nivel' => 'A', 'priority' => 'alta',
            'modulo' => 'Mapa de Red', 'estado' => 'aprobado_irving',
            'title'  => 'MR-07 — Prueba de no regresión del módulo Mapas viejo',
            'description' => 'Certificar que después de MR-03 a MR-06 el módulo viejo quedó exactamente igual.',
            'prompt' => <<<'TXT'
Comparar contra el respaldo de MR-02: conteos por tabla, checksum de esquema (`SHOW CREATE TABLE`),
`route:list` filtrado por `/mapas`, y permisos existentes.

Cualquier diferencia se reporta y **se revierte**.

**DoD:** cero diferencias, o diferencias revertidas y documentadas.
TXT,
        ],

        // ───────────────────── FASE 2 — El modelo de datos que hoy falta ─────────────────────

        [
            'codigo' => 'MR-08', 'nivel' => 'B', 'priority' => 'alta',
            'modulo' => 'Mapa de Red', 'estado' => 'aprobado_irving',
            'title'  => 'MR-08 — Catálogos: tipos de cable, tipos de splitter, tipos de caja, conectores',
            'description' => 'Los catálogos son la base de todo lo demás (pérdidas, colores, capacidades). Patrón tomado de AdminOLT/SmartOLT: primero se configuran tipos, después se dibuja.',
            'prompt' => <<<'TXT'
Tablas:

- `mapared_tipo_cable` (nombre, fabricante, número de hilos, hilos por buffer, esquema de color
  EIA/TIA-598 o ABNT NBR 14771 según D12, atenuación dB/km por ventana, precio, unidad de medida)
- `mapared_tipo_splitter` (nombre, fabricante, balanceado/desbalanceado, ratio, número de puertos,
  pérdida dB según D13/D14, tipo de conector, precio)
- `mapared_tipo_caja` (nombre, capacidad de puertos, capacidad de fusiones, IP/IK)
- `mapared_tipo_conector` (nombre, pérdida dB)

Sembrar con los valores de D13, D14 y D15. CRUD completo con permisos `mapa_red_catalogo_*`.

**DoD:** los cuatro catálogos con CRUD y semilla cargada; las pérdidas son editables desde la UI,
no constantes en código.
TXT,
        ],

        [
            'codigo' => 'MR-09', 'nivel' => 'B', 'priority' => 'media',
            'modulo' => 'Mapa de Red', 'estado' => 'aprobado_irving',
            'title'  => 'MR-09 — Entidad cable con estructura interna (buffers e hilos)',
            'description' => 'El cable deja de ser una línea dibujada y pasa a tener estructura interna instanciada: buffers e hilos con su color.',
            'prompt' => <<<'TXT'
`mapared_cables`: tipo de cable (FK a catálogo), geometría como GeoJSON LineString en `geom_json`,
longitud calculada en metros, holgura/slack, estado (planeado / en construcción / activo /
retirado), proyecto, zona.

Al guardar un cable, **auto-instanciar** sus buffers e hilos según el tipo (patrón blueprint de
netbox-fms): un cable de 96 hilos crea 96 filas de hilo con su color de buffer y de hilo según el
esquema del catálogo.

**DoD:** crear un cable FO96 en Tultitlán genera 8 buffers × 12 hilos con colores correctos;
borrarlo los borra en cascada; el cálculo de longitud coincide con la distancia real de la polilínea
±2%.
TXT,
        ],

        [
            'codigo' => 'MR-10', 'nivel' => 'B', 'priority' => 'media',
            'modulo' => 'Mapa de Red', 'estado' => 'aprobado_irving',
            'title'  => 'MR-10 — Entidad puerto (OLT PON, splitter in/out, NAP, ODF, ONT)',
            'description' => 'El puerto como entidad de primera clase, polimórfica, para poder contar ocupación real en una sola query.',
            'prompt' => <<<'TXT'
`mapared_puertos` polimórfica: `puertable_type`/`puertable_id` (elemento dueño), número, rol
(`pon`, `splitter_in`, `splitter_out`, `nap_salida`, `odf`, `ont`), estado (`libre`, `ocupado`,
`reservado`, `dañado`), etiqueta.

Para OLT usar el patrón Frame/Slot/Port como lo maneja MultiOLT.

**DoD:** una NAP con splitter 1:8 tiene 8 puertos de salida y 1 de entrada creados automáticamente;
el conteo libre/ocupado es consultable en una sola query.
TXT,
        ],

        [
            'codigo' => 'MR-11', 'nivel' => 'B', 'priority' => 'media',
            'modulo' => 'Mapa de Red', 'estado' => 'aprobado_irving',
            'title'  => 'MR-11 — Entidad hilo (strand) de primera clase',
            'description' => 'El hilo deja de ser un número en un campo y pasa a ser fila propia con estado, para que un hilo dañado se refleje en el trazo.',
            'prompt' => <<<'TXT'
`mapared_hilos`: cable (FK), buffer, número, color, estado (`libre`, `asignado`, `dañado`,
`reservado`), y observaciones. Índice por `(cable_id, buffer, numero)`. Vista de ocupación por cable.

**DoD:** listar los 96 hilos de un troncal con su color y estado; marcar uno como dañado se refleja
en el trazo de MR-16.

> Nota de siembra: el documento original decía "el trazo de MR-15"; MR-15 es el backfill y el trazo
> es MR-16. Corregido al sembrar (off-by-one de un renumerado previo).
TXT,
        ],

        [
            'codigo' => 'MR-12', 'nivel' => 'B', 'priority' => 'media',
            'modulo' => 'Mapa de Red', 'estado' => 'aprobado_irving',
            'title'  => 'MR-12 — Entidad empalme (unión de hilos) y bandejas',
            'description' => 'Es el "thread union" de AdminOLT: unión de hilos Rack→NAP, NAP→NAP y Splitter→NAP.',
            'prompt' => <<<'TXT'
`mapared_empalmes`: hilo A, hilo B (o hilo → puerto de splitter), elemento contenedor
(mufa/NAP/rack), bandeja, posición, tipo (`fusión`, `mecánico`, `conectorizado`), pérdida dB
(default del catálogo, editable por empalme), fecha y técnico.

Validaciones duras: un hilo no puede estar en dos empalmes activos; no se puede empalmar un hilo
consigo mismo; el contenedor debe existir.

UI: doble clic en un marcador tipo Rack, Mufa o NAP abre el panel de unión de hilos con las dos
columnas de hilos disponibles.

**DoD:** unir hilo 3 del troncal con la entrada del splitter de una NAP real de Tultitlán y ver la
unión reflejada en la carta de empalme de MR-19.

> Nota de siembra: el documento original decía "carta de empalme de MR-18"; MR-18 es el presupuesto
> óptico y la carta de empalme es MR-19. Corregido al sembrar.
TXT,
        ],

        [
            'codigo' => 'MR-13', 'nivel' => 'B', 'priority' => 'media',
            'modulo' => 'Mapa de Red', 'estado' => 'aprobado_irving',
            'title'  => 'MR-13 — Splitter como objeto con ratio, nivel, pérdida y ubicación',
            'description' => 'El splitter deja de ser un texto y pasa a ser objeto con ratio, nivel y pérdida heredada del catálogo, soportando cascada nivel 1 → nivel 2.',
            'prompt' => <<<'TXT'
`mapared_splitters`: tipo (FK catálogo), nivel (1 o 2), elemento contenedor (NAP, mufa o rack),
puerto de entrada, puertos de salida auto-generados según el ratio, pérdida heredada del catálogo y
sobreescribible. Soportar cascada nivel 1 → nivel 2.

**DoD:** modelar una cadena real 1:8 (nivel 1, en rack) → 1:8 (nivel 2, en NAP) y que el presupuesto
óptico de MR-18 sume 11+11 dB más las fusiones y los km.

> Nota de siembra: el documento original decía "el presupuesto óptico de MR-17"; MR-17 es el trazo
> inverso de impacto y el presupuesto óptico es MR-18. Corregido al sembrar.
TXT,
        ],

        [
            'codigo' => 'MR-14', 'nivel' => 'B', 'priority' => 'media',
            'modulo' => 'Mapa de Red', 'estado' => 'aprobado_irving',
            'title'  => 'MR-14 — enlace_de_servicio: cliente/ONT ↔ puerto de NAP ↔ hilo',
            'description' => 'Es la costura entre la planta y el negocio: sin esto no hay impacto por MRR ni semáforo de salud.',
            'prompt' => <<<'TXT'
`mapared_enlaces_servicio`: cliente (resuelto **por nombre/número de contrato, nunca por ID**, según
D19), ONT (serie), puerto de NAP, hilo del drop, fecha de alta, estado.

Es la costura entre la planta y el negocio: sin esto no hay impacto por MRR (MR-17) ni semáforo de
salud (MR-21).

**DoD:** al menos 20 clientes reales de Tultitlán enlazados y visibles al abrir su NAP.

> Nota de siembra: el documento original citaba MR-16 y MR-20; el impacto por MRR es MR-17 y el
> semáforo de salud es MR-21. Corregido al sembrar.
TXT,
        ],

        [
            'codigo' => 'MR-15', 'nivel' => 'B', 'priority' => 'media',
            'modulo' => 'Mapa de Red', 'estado' => 'aprobado_irving',
            'title'  => 'MR-15 — Backfill: mapear los datos copiados al modelo nuevo',
            'description' => 'Los datos de MR-05 llegaron con la forma vieja. Acá se convierten al modelo nuevo hasta donde los datos alcancen.',
            'prompt' => <<<'TXT'
Comando `php artisan mapared:backfill {--dry-run} {--zona=}`: deducir tipo de cable por el nombre
(`FO96`, `FO288`, `FO24`), instanciar hilos, crear puertos para las cajas con capacidad conocida, y
**dejar explícitamente en un reporte todo lo que no se pudo deducir**.

Prohibido inventar datos: lo que no se sabe queda nulo y listado.

**DoD:** reporte con tres columnas — convertido / parcial / no convertible — y el porcentaje de
cobertura por zona. Si Tultitlán queda por debajo del 80% convertido, crear item de respuesta.
TXT,
        ],

        // ──────────── FASE 3 — El motor (lo que ninguno de los baratos tiene) ────────────

        [
            'codigo' => 'MR-16', 'nivel' => 'B', 'priority' => 'media',
            'modulo' => 'Mapa de Red', 'estado' => 'aprobado_irving',
            'title'  => 'MR-16 — Grafo de red y trazo extremo a extremo OLT → ONT',
            'description' => 'Servicio de grafo dirigido sobre puertos, hilos y empalmes: dado un puerto PON o un cliente, devolver la ruta completa en orden.',
            'prompt' => <<<'TXT'
Servicio de grafo dirigido sobre puertos, hilos y empalmes, con pathfinding tipo DAG.

API: dado un puerto PON de OLT o un cliente, devolver la ruta completa (elementos, cables, hilos,
empalmes, splitters) en orden.

Debe tolerar ciclos y rutas incompletas sin colgarse: si la ruta se corta, devolver hasta dónde
llegó y por qué. Cachear por elemento con invalidación al editar empalmes.

**DoD:** desde un cliente real de Tultitlán, el trazo devuelve la cadena completa hasta el puerto
PON, y se dibuja resaltada en el mapa.
TXT,
        ],

        [
            'codigo' => 'MR-17', 'nivel' => 'B', 'priority' => 'media',
            'modulo' => 'Mapa de Red', 'estado' => 'aprobado_irving',
            'title'  => 'MR-17 — Trazo inverso de impacto (clientes y MRR afectados)',
            'description' => 'La pregunta de oro del NOC: "si corto aquí, ¿a quién dejo sin servicio y cuánto dinero es?". Ninguna plataforma barata del mercado la responde con la dimensión comercial.',
            'prompt' => <<<'TXT'
Dado cualquier elemento (cable, hilo, mufa, NAP, splitter, puerto PON), devolver: lista de clientes
aguas abajo, conteo, y **MRR afectado** sumando el plan de cada cliente.

Botón "¿Quién depende de esto?" en la ficha del elemento. Excluir clientes cancelados.

**DoD:** cortar virtualmente el troncal `T-TULTITLAN-FO96-1` devuelve la lista de clientes y el monto
mensual en riesgo.
**Screenshot a validar:** ese panel de impacto.
TXT,
        ],

        [
            'codigo' => 'MR-18', 'nivel' => 'B', 'priority' => 'media',
            'modulo' => 'Mapa de Red', 'estado' => 'aprobado_irving',
            'title'  => 'MR-18 — Presupuesto óptico automático desde el trazo',
            'description' => 'Acumular pérdidas sobre la ruta y contrastar el presupuesto calculado contra el RX real que reporta MultiOLT.',
            'prompt' => <<<'TXT'
Sobre la ruta de MR-16, acumular: atenuación por km según ventana, pérdida por splitter (D13/D14),
por fusión, por conector (D15).

Mostrar el desglose elemento por elemento y el total, comparado contra la potencia de transmisión de
la OLT y el umbral de sensibilidad de la ONT. Marcar en rojo si el enlace no cierra.

Comparar el calculado contra el RX real que reporta MultiOLT y mostrar la diferencia.

**DoD:** para 10 clientes reales, la diferencia entre el presupuesto calculado y el RX real de
MultiOLT es menor a 3 dB en al menos 7 de ellos; los otros 3 quedan explicados en el reporte.
TXT,
        ],

        [
            'codigo' => 'MR-19', 'nivel' => 'B', 'priority' => 'media',
            'modulo' => 'Mapa de Red', 'estado' => 'aprobado_irving',
            'title'  => 'MR-19 — Carta de empalme por caja (vista + PDF)',
            'description' => 'Diagrama por elemento contenedor, bandeja por bandeja, exportable a PDF para que el técnico lo lleve impreso.',
            'prompt' => <<<'TXT'
Diagrama por elemento contenedor mostrando bandeja por bandeja qué hilo entra, con qué se fusiona y
a dónde sale, con los colores del esquema del catálogo. Exportable a PDF para que el técnico lo
lleve impreso.

**DoD:** generar la carta de empalme de una mufa real de Tultitlán y que un técnico pueda seguirla
sin explicación adicional.
TXT,
        ],

        [
            'codigo' => 'MR-20', 'nivel' => 'B', 'priority' => 'media',
            'modulo' => 'Mapa de Red', 'estado' => 'aprobado_irving',
            'title'  => 'MR-20 — Ocupación de puertos y semáforo de NAP en el mapa',
            'description' => 'Color del marcador según ocupación, con la escala de D16, para que el mapa se lea de un golpe de vista.',
            'prompt' => <<<'TXT'
Color del marcador según ocupación, con la escala de D16 (gris 0–50, amarillo 51–70, naranja 71–99,
rojo 100).

Filtro "solo NAPs con puertos libres". Al pasar el cursor: puertos usados / totales.

**DoD:** el mapa de Tultitlán se lee de un golpe de vista; el filtro de puertos libres devuelve
exactamente las NAPs con capacidad.
TXT,
        ],

        [
            'codigo' => 'MR-21', 'nivel' => 'B', 'priority' => 'media',
            'modulo' => 'Mapa de Red', 'estado' => 'aprobado_irving',
            'title'  => 'MR-21 — Mapa de estado de red (salud) integrado con MultiOLT',
            'description' => 'Semáforo de salud por NAP alimentado por MultiOLT (D18: no crear lector de OLT propio).',
            'prompt' => <<<'TXT'
Consumir MultiOLT (D18, no crear lector propio) para: promedio de RX por NAP, semáforo de salud
según D17, y un dashboard al hacer clic en la NAP con potencia promedio de la caja, ONUs activas,
ONUs offline, ONUs con señal baja, y la lista de ONUs con nombre, serie, estado, señal, puerto de
NAP, Tx, Rx y OLT.

**DoD:** una NAP con una ONU offline real se ve en amarillo o rojo y su dashboard lo explica.
**Screenshot a validar:** el mapa de estado y un dashboard de NAP.
TXT,
        ],

        // ─────────────────────── FASE 4 — La navegación entendible ───────────────────────

        [
            'codigo' => 'MR-22', 'nivel' => 'B', 'priority' => 'media',
            'modulo' => 'Mapa de Red', 'estado' => 'aprobado_irving',
            'title'  => 'MR-22 — Nueva navegación: buscador, filtros por capa, clustering, render por zoom',
            'description' => 'Este item es el que resuelve el dolor original: un árbol de 7 niveles con "TRONCAL" dentro de "TRONCAL".',
            'prompt' => <<<'TXT'
Reemplazar el árbol como navegación primaria por:

(a) buscador global que acepte nombre de elemento, nombre de cliente, serie de ONT o dirección y
    vuele a la ubicación;
(b) panel de capas encendibles (OLT, troncales, mufas, NAPs, drops, clientes, postes, cobertura);
(c) clustering de marcadores;
(d) render dependiente de zoom — drops y clientes solo aparecen a partir de cierto nivel.

El árbol se conserva pero derivado y de máximo 3 niveles (D22).

**DoD:** con las 6 zonas cargadas, el mapa abre en menos de 3 segundos y encontrar una NAP por
nombre toma menos de 5 segundos.
TXT,
        ],

        [
            'codigo' => 'MR-23', 'nivel' => 'B', 'priority' => 'media',
            'modulo' => 'Mapa de Red', 'estado' => 'aprobado_irving',
            'title'  => 'MR-23 — Ficha lateral del elemento (reemplaza los 5 iconos por fila)',
            'description' => 'Todas las acciones de un elemento viven en un panel lateral, no repartidas en iconos por renglón.',
            'prompt' => <<<'TXT'
Un clic en cualquier elemento abre un panel lateral con: identificación, ubicación, tipo, puertos
ocupados/libres, empalmes, clientes colgados, fotos, historial de cambios, y las acciones (editar,
mover, borrar, trazar, ver impacto).

Todas las acciones viven ahí, no repartidas en iconos por renglón.

**DoD:** ninguna acción del módulo requiere volver al árbol.
TXT,
        ],

        [
            'codigo' => 'MR-24', 'nivel' => 'B', 'priority' => 'media',
            'modulo' => 'Mapa de Red', 'estado' => 'aprobado_irving',
            'title'  => 'MR-24 — Alta de elementos en 3 pasos con snap y nomenclatura automática',
            'description' => 'Dar de alta una NAP debe tomar menos de 30 segundos, sin escribir el nombre a mano.',
            'prompt' => <<<'TXT'
Modo dibujo: clic en el mapa con enganche (snap) al cable o elemento más cercano dentro de 15 m;
formulario mínimo de tipo + splitter; guardar.

Zona, proyecto y nombre se heredan y se generan según D23 y D24. Igual para cables: dibujar
polilínea con snap a los extremos.

**DoD:** dar de alta una NAP nueva completa toma menos de 30 segundos y queda correctamente nombrada
y colgada del troncal correcto sin escribir el nombre a mano.
**Screenshot a validar:** el alta de una NAP de principio a fin.
TXT,
        ],

        // ───────────────────── FASE 5 — Interoperabilidad y cobertura ─────────────────────

        [
            'codigo' => 'MR-25', 'nivel' => 'B', 'priority' => 'media',
            'modulo' => 'Mapa de Red', 'estado' => 'aprobado_irving',
            'title'  => 'MR-25 — Importador KML/KMZ + GeoJSON + CSV y exportadores',
            'description' => 'Doble propósito: cargar lo que hoy vive en Google Earth, y ser el gancho para migrar redes de otros sistemas hacia MegaISP (importante para Medussa).',
            'prompt' => <<<'TXT'
Importador con: previsualización antes de escribir, mapeo de campos, detección de duplicados por
proximidad (<5 m) y nombre, y reporte de lo omitido.

Formatos por prioridad D20 (KML/KMZ, GeoJSON, CSV; DXF/SHP puede quedar para una ronda posterior y
en ese caso se registra como item nuevo).

Exportadores según D21: KML, GeoJSON, carta de empalme PDF y BOM en XLSX con el precio de los
catálogos.

**DoD:** importar un KMZ real exportado del Google Earth de Meganet y que los elementos caigan en su
lugar con su tipo correcto.
TXT,
        ],

        [
            'codigo' => 'MR-26', 'nivel' => 'B', 'priority' => 'media',
            'modulo' => 'Mapa de Red', 'estado' => 'aprobado_irving',
            'title'  => 'MR-26 — Cobertura comercial derivada de la infraestructura',
            'description' => 'El polígono de cobertura vendible se genera solo, como unión de los radios de drop de las NAPs con puertos libres.',
            'prompt' => <<<'TXT'
Generar automáticamente el polígono de cobertura vendible como la unión de los radios de drop
(parámetro configurable, default 200 m) de las NAPs **con puertos libres**. Recalcular cuando cambia
la ocupación.

Exponer un endpoint de consulta por coordenada o dirección: "¿hay cobertura aquí?" → sí/no + NAP más
cercana + puertos libres + distancia.

Guardar también, como capa importable, los sectores inalámbricos (azimut, apertura, alcance, altura)
sin construir simulador (D29).

**DoD:** consultar una dirección real de Tultitlán responde correctamente; ocupar el último puerto de
una NAP encoge el polígono.
TXT,
        ],

        // ───────────────────────── FASE 6 — Comparación y corte ─────────────────────────

        [
            'codigo' => 'MR-27', 'nivel' => 'B', 'priority' => 'media',
            'modulo' => 'Mapa de Red', 'estado' => 'requiere_irving',
            'title'  => 'MR-27 — Comparativa formal Mapas vs MAPA DE RED y decisión de corte',
            'description' => 'El punto donde se decide cuál gana, con datos y no por gusto (D26).',
            'prompt' => <<<'TXT'
Producir un cuadro comparativo con evidencia: conteo de elementos en cada módulo, funciones que solo
existen en uno, tiempos de carga medidos, elementos que no se pudieron migrar, y el resultado de la
prueba de trazo OLT→ONT en la zona piloto de Tultitlán.

**No** borrar nada.

Cerrar el item con una recomendación explícita y esperar la decisión de Irving.

**DoD:** cuadro entregado + recomendación + decisión de Irving registrada en el item.
TXT,
        ],

        [
            'codigo' => 'MR-28', 'nivel' => 'C', 'priority' => 'media',
            'modulo' => 'Mapa de Red', 'estado' => 'requiere_irving',
            'title'  => 'MR-28 — Retiro del módulo perdedor en dos tiempos',
            'description' => 'Solo se ejecuta con la decisión de MR-27 tomada por Irving y escrita en el item.',
            'prompt' => <<<'TXT'
⚠️ Solo se ejecuta con la decisión de MR-27 tomada por Irving y escrita en el item.

**Tiempo 1:** ocultar el módulo perdedor del sidebar y marcarlo como deshabilitado en
`module_registry` con `keep_data:true` (los permisos Spatie NO se eliminan). Esperar **14 días** de
convivencia sin incidencias.

**Tiempo 2:** con respaldo fresco verificado (repetir MR-02 sobre el perdedor) y confirmación
explícita de Irving en el item, eliminar tablas y código.

Si en cualquier momento aparece una función que solo existía en el perdedor, se aborta el retiro y
se registra item nuevo.

**DoD:** módulo perdedor retirado, respaldo verificado archivado, sidebar limpio, cero rutas 404,
`route:clear` + warm-up ejecutados.
TXT,
        ],

        // ───────── CIERRE DE SIEMBRA (2026-09-03) — rúbrica, contingencia y seguimiento ─────────
        //
        // No son "fase 7": son los tres huecos que quedaban abiertos para que nada se decida
        // después. MR-29 congela CÓMO se compara antes de comparar; MR-30 escribe qué se hace si
        // el piloto reprueba, antes de que la frustración decida por nosotros; MR-31 pone la épica
        // bajo la mirada periódica del Supervisor.

        [
            'codigo' => 'MR-29', 'nivel' => 'B', 'priority' => 'media',
            'modulo' => 'Mapa de Red', 'estado' => 'aprobado_irving',
            'title'  => 'MR-29 — Rúbrica de comparación congelada (la tabla que MR-27 va a llenar)',
            'description' => <<<'TXT'
Congela **hoy**, antes de que exista el resultado, los criterios con los que MR-27 va a decidir qué
módulo gana. Una rúbrica escrita después de ver los números no es una rúbrica: es una justificación.

Este item **no compara nada**. Sólo deja la tabla lista, con umbrales numéricos, la evidencia que
prueba cada fila y quién la firma.

## Rúbrica de comparación — Mapas vs MAPA DE RED

| # | Criterio | Umbral (se aprueba si…) | Evidencia que lo prueba | Quién firma |
|---|---|---|---|---|
| 1 | Paridad de conteo por tipo de elemento entre `Mapas` y `mapared_*` | **Tolerancia 0**: el conteo coincide exactamente para cada tipo (pack, cupboard, junction_box, source, splitter, equipo activo/pasivo) | Query de conteo por tipo lado a lado, pegada en el item, más la lista de omitidos de MR-05 con su motivo | Terminal MR-27 |
| 2 | Elementos huérfanos en el módulo nuevo | **0** elementos con padre inexistente, `lat`/`lng` nulos o en (0,0) que no vinieran ya así del origen | Query de huérfanos sobre `mapared_*`, contrastada con la misma query sobre el módulo viejo | Terminal MR-27 |
| 3 | Trazo OLT→ONT en la zona piloto `T-TULTITLAN-FO96-*` | **100%** de las NAPs de la zona resuelven cadena completa hasta el puerto PON | Salida del motor de MR-16 para cada NAP de la zona, con la lista de las que fallan (debe quedar vacía) | Terminal MR-27, valida Irving |
| 4 | Alta de una NAP nueva | **≤ 3 pasos** de principio a fin, sin escribir el nombre a mano | Screenshot de la secuencia completa (clic en el mapa → tipo + splitter → guardar) | **Irving** (visual) |
| 5 | Carga del mapa con todos los elementos de Tultitlán visibles | **≤ 3 s** hasta que el mapa es usable | Medición repetible (3 corridas, se reporta la peor) + screenshot con el contador visible | **Irving** (visual) |
| 6 | Presupuesto óptico calculado vs. RX real de MultiOLT | Desviación **≤ 3 dB** en **al menos 20 ONUs** | Tabla de las 20+ ONUs con calculado, real y diferencia; las que se pasen quedan explicadas una por una | Terminal MR-27 |

## Regla de desempate

**Si el módulo nuevo no gana en TODOS los criterios, no se retira el viejo y MR-28 no se ejecuta.**
No hay promedio, ni "ganó en 5 de 6", ni empate a favor del nuevo por ser el nuevo. Un solo criterio
reprobado activa **MR-30 (contingencia)** y los dos módulos siguen conviviendo.
TXT,
            'prompt' => <<<'TXT'
⚠️ Este item **NO compara** los módulos y **NO mide** nada. Comparar es MR-27.

Su trabajo es dejar la rúbrica de la `description` **operativa**: que cada uno de los 6 criterios
tenga identificada, hoy, la fuente concreta con la que se va a medir en el sistema real.

Para cada fila de la tabla, anotá en `comentarios_claude` **con qué se mide**:

- Criterios 1 y 2: la query exacta (tabla y columnas reales, verificadas contra el esquema que dejó
  MR-04), no una descripción de la query.
- Criterio 3: qué endpoint o servicio de MR-16 se invoca y cómo se enumeran las NAPs de la zona
  piloto.
- Criterios 4 y 5: qué pantalla se abre y qué se captura, para que Irving pueda firmarlos sin
  interpretar.
- Criterio 6: de dónde sale el RX real (MultiOLT, D18 — **no** un lector propio) y cómo se emparejan
  las ONUs con su trazo.

Si algún criterio **no se puede medir todavía** porque el item que lo habilita aún no cerró, se
anota así explícitamente, con el item del que depende. Eso no es un fallo: es el estado real.

**Prohibido** cambiar umbrales, agregar criterios o quitar filas. La rúbrica está congelada — si
creés que un umbral está mal, abrí un item de respuesta y dejá la tabla intacta.

**DoD:** las 6 filas con su fuente de medición anotada y verificada contra el sistema real, o
marcadas como bloqueadas con el item del que dependen. Cero mediciones ejecutadas.
TXT,
        ],

        [
            'codigo' => 'MR-30', 'nivel' => 'B', 'priority' => 'media',
            'modulo' => 'Mapa de Red', 'estado' => 'aprobado_irving',
            'title'  => 'MR-30 — Contingencia si el piloto falla (qué se hace cuando MR-29 reprueba)',
            'description' => <<<'TXT'
Escribe **hoy** qué pasa si el módulo nuevo no aprueba la rúbrica de MR-29, para que esa decisión no
se tome en caliente, con el trabajo ya hecho encima y las ganas de cerrarlo.

## Qué se hace si MR-29 reprueba

1. **Los dos módulos se quedan conviviendo.** `MR-28 no corre.` No se oculta, no se deshabilita y
   no se borra nada del módulo viejo. La convivencia era el plan desde D1; volver a ella no es un
   retroceso, es el estado seguro.
2. **Se abre un item `[RESPUESTA]` contra #936** con: qué criterios reprobaron, el número medido
   contra el umbral, y la **causa raíz** de cada uno (no "faltó tiempo": qué falta técnicamente).
3. **El módulo nuevo queda marcado como `beta` en el sidebar**, visible y usable, no retirado. Quien
   lo abra debe saber que todavía no es la fuente de verdad.
4. Se decide con Irving si el trabajo restante justifica otra ronda, y esa ronda nace como items
   nuevos hijos de #936 — nunca reabriendo los ya cerrados.

## Lo que NO se hace

**Nadie borra nada por frustración ni por antigüedad del item.** Que la épica lleve meses abierta no
es un argumento técnico. Que el módulo nuevo "ya casi" pase la rúbrica tampoco: el umbral es el
umbral. Un item viejo no se cierra retirándole el alcance.
TXT,
            'prompt' => <<<'TXT'
Este item se ejecuta **sólo si MR-29 reprueba** (uno o más criterios por debajo de su umbral). Si
MR-29 aprueba en todo, se cierra como no aplicable, citando el resultado de MR-27.

Cuando aplique:

1. Verificá que **MR-28 no se haya ejecutado**. Si alguien ya corrió el Tiempo 1 (módulo oculto o
   `module_registry.enabled=false`), **revertilo**: `keep_data:true` garantiza que los datos siguen
   ahí, y los permisos Spatie nunca se tocaron.
2. Abrí **un** item `[RESPUESTA]` contra #936 con la lista de criterios reprobados: valor medido,
   umbral, y causa raíz técnica de cada uno.
3. Marcá el módulo nuevo como **beta** en el sidebar (etiqueta visible junto a "MAPA DE RED"), sin
   quitarlo ni degradar sus permisos.
4. Dejá en el reporte qué haría falta para aprobar cada criterio reprobado, como insumo de la ronda
   siguiente. No crees vos los items de esa ronda: eso lo decide Irving con la lista delante.

**Prohibido:** retirar, ocultar o borrar cualquiera de los dos módulos. Este item es la contingencia,
no una vía alterna para ejecutar el retiro.

**DoD:** MR-28 verificado sin ejecutar (o revertido), item `[RESPUESTA]` abierto contra #936 con
causa raíz por criterio, módulo nuevo etiquetado beta en el sidebar y visible.
**Screenshot a validar:** el sidebar mostrando los dos módulos, con el nuevo marcado beta.
TXT,
        ],

        [
            'codigo' => 'MR-31', 'nivel' => 'A', 'priority' => 'media',
            'modulo' => 'Mapa de Red', 'estado' => 'aprobado_irving',
            'title'  => 'MR-31 — Seguimiento de la épica por el Supervisor (resumen semanal de #936)',
            'description' => <<<'TXT'
Pone la épica bajo mirada periódica, para que no se descubra dentro de dos meses que lleva cinco
semanas detenida en un item que nadie miró.

El Supervisor **ya existe**: `SupervisorService` (Jarvis T), con su vuelta `circuito:jarvis` y su
digest `circuito:digest`. Este item **no construye infraestructura nueva** — cuelga un resumen del
canal que Jarvis ya usa.

## Qué reporta, cada semana

- **Cuántos hijos de #936 cerraron** y cuántos quedan (sobre los 31 sembrados).
- **Cuál está en curso** ahora mismo, con su `worker_sid` y desde cuándo.
- **Cuáles llevan más de 7 días parados en `requiere_irving`** — la señal que importa: un item que
  espera decisión humana y nadie la toma no se distingue solo de uno que avanza.
- Si la épica **no tuvo movimiento** en la semana, eso se dice explícitamente. Un resumen que se
  calla cuando no pasa nada es indistinguible de un resumen que no corrió.
TXT,
            'prompt' => <<<'TXT'
Agregá al digest que Jarvis ya emite (`circuito:digest`) una sección de seguimiento de la épica
#936. **No** construyas un canal, un comando ni una pantalla nuevos: si hace falta un comando
propio, es señal de que estás saliéndote del alcance — abrí item de respuesta y pará.

La sección debe responder, sobre los hijos de #936 (`origen_item_id = 936`):

- cerrados vs. total;
- cuál está `en_progreso`, con `worker_sid` y `trabajo_iniciado_at`;
- cuáles llevan **> 7 días** en `requiere_irving` (usar `revisado_at`/`updated_at`, el que refleje
  la entrada al estado);
- si no hubo cambios en la semana, decirlo con esas palabras.

Todo es **lectura**: contar y presentar. Este item no cambia el estado de ningún item de la épica,
no despacha, no libera frenos y no toca `excluir_pool_automatico` de nadie.

Cadencia semanal. Si el digest es diario, la sección de #936 sale una vez por semana (o marcada como
"sin cambios" el resto de los días) — el criterio lo define quien lo implemente, con tal de que no
genere ruido diario sobre una épica que avanza de a poco.

**DoD:** el digest incluye la sección de #936 con los 4 datos, verificada en una corrida real, y
cero escrituras sobre los items de la épica.
TXT,
        ],

        ];
    }

    /**
     * Parches declarativos a items YA sembrados. `marca` es la prueba de idempotencia: si el campo
     * ya la contiene, no se escribe.
     */
    private function parches(): array
    {
        return [
            [
                'id' => 936, 'codigo' => 'MR-00', 'campo' => 'description',
                'que' => 'D31 congelamiento + D32 entrega final',
                'marca' => 'REGLA DE CONGELAMIENTO (D31)',
                'texto' => <<<'TXT'
## REGLA DE CONGELAMIENTO (D31)

El alcance de esta épica quedó cerrado el 2026-09-03. A partir de aquí:

- Ningún item sembrado (MR-01 a MR-28) se edita para cambiar su alcance.
  Se corrigen erratas y se sincroniza el bloque de canal de respuesta;
  nada más.
- Todo cambio de idea, mejora o alcance nuevo nace como item NUEVO hijo
  de #936, numerado MR-29 en adelante, y arranca en `requiere_irving`.
- Una instrucción verbal posterior que contradiga un item sembrado NO lo
  reemplaza. La terminal que la reciba abre un item [RESPUESTA] contra el
  item afectado y sigue con el prompt sembrado hasta que Irving apruebe el
  cambio por escrito en la Hoja de Ruta.
- Esta regla la puede levantar Irving, pero solo citándola explícitamente.
  El objetivo no es impedirle cambiar de opinión; es que el cambio quede
  visible en vez de disolverse dentro de un prompt editado a mano.

## ENTREGA FINAL (D32)

La épica se considera entregada cuando: MR-27 cerró con veredicto escrito,
MR-28 ejecutó el retiro del módulo perdedor, y existe un item [RESPUESTA]
o reporte final que liste qué quedó fuera de alcance (inalámbrico D29 y
multi-tenant D30 incluidos) para la ronda siguiente.

> Nota de siembra (2026-09-03): MR-29, MR-30 y MR-31 se crearon en la misma tanda que D31, como
> cierre de la siembra y no como cambio de alcance posterior. Son, respectivamente, la rúbrica
> congelada con la que MR-27 decide, la contingencia si el piloto reprueba, y el seguimiento
> semanal de esta épica por el Supervisor.
TXT,
            ],
            [
                'id' => 963, 'codigo' => 'MR-27', 'campo' => 'prompt',
                'que' => 'referencia a MR-29 como su rúbrica',
                'marca' => 'La rúbrica con la que se decide',
                'texto' => <<<'TXT'
## La rúbrica con la que se decide ya está escrita: MR-29

**No inventes criterios ni umbrales acá.** La tabla de comparación quedó congelada en
**MR-29 — Rúbrica de comparación congelada**, antes de que existiera cualquier resultado, y es la
única que vale: 6 criterios con umbral numérico, la evidencia que prueba cada uno y quién lo firma.

Tu trabajo en este item es **llenarla** con lo medido y firmar cada fila. Si al medir te parece que
un umbral está mal calibrado, **no lo cambies**: abrí un item de respuesta y reportá el número real
contra el umbral vigente.

**Regla de desempate (de MR-29):** si el módulo nuevo no gana en TODOS los criterios, no se retira
el viejo y **MR-28 no se ejecuta** — se activa **MR-30 (contingencia)** y los dos módulos siguen
conviviendo. No hay promedio ni "ganó en 5 de 6".
TXT,
            ],
            [
                'id' => 964, 'codigo' => 'MR-28', 'campo' => 'prompt',
                'que' => 'ventana de reversión de 30 días',
                'marca' => 'Ventana de reversión (30 días)',
                'texto' => <<<'TXT'
## Ventana de reversión (30 días) — complementa los 14 días de convivencia

Son **dos plazos distintos y los dos aplican**. No se sustituyen:

- **14 días de convivencia** — entre el Tiempo 1 (ocultar) y el Tiempo 2 (borrar). Es el plazo ya
  decidido en la pregunta q1 de este item y no cambia.
- **30 días de reversión** — cuentan **desde el Tiempo 2**, ya con el módulo eliminado.

Durante esos 30 días, el respaldo verificado de MR-02 sobre el módulo perdedor **se conserva en
línea y localizable**, con su comando de restauración ya probado, de modo que el retiro pueda
deshacerse sin depender de que un backup rotado siga existiendo.

**No se borra ni se rota ese respaldo antes de que expiren los 30 días.** Recién cumplidos pasa a la
política de retención normal. Anotá en el item la fecha exacta de expiración al ejecutar el Tiempo 2.
TXT,
            ],
        ];
    }

    /**
     * Tabla D1–D30 — decisiones ya tomadas por Irving. Va COMPLETA dentro del `description` de
     * MR-00 para que cualquier terminal que tome un hijo la tenga a la mano y no vuelva a preguntar.
     */
    private function tablaDecisiones(): string
    {
        return <<<'TXT'
## DECISIONES YA RESUELTAS (no preguntar ninguna de estas)

| # | Decisión | Valor definitivo |
|---|---|---|
| D1 | Estrategia general | **Convivencia**. El módulo `Mapas` actual queda intacto y visible en el sidebar. Se crea uno nuevo en paralelo. Nada se borra hasta MR-27/MR-28. |
| D2 | Nombre visible | **MAPA DE RED** (así, en el sidebar, debajo o junto a "Mapas") |
| D3 | Carpeta del módulo | `app/Modules/Addons/MapaRed` |
| D4 | Slug / ruta base | `mapa-red` → `https://dev.meganett.com.mx/mapa-red` |
| D5 | Prefijo de tablas | `mapared_` (ej. `mapared_elementos`, `mapared_cables`) |
| D6 | Prefijo de permisos | `mapa_red_` (ej. `mapa_red_elemento_view`) |
| D7 | Motor de mapa | **Leaflet + OpenStreetMap**, igual que Flotas. **NO Google Maps** (evitamos API key de pago y su facturación por carga). Capa satelital opcional vía Esri World Imagery. |
| D8 | Geometría en BD | MySQL sin PostGIS. Puntos: `lat DECIMAL(10,7)`, `lng DECIMAL(10,7)`. Líneas/polígonos: `geom_json LONGTEXT` (GeoJSON). Índices compuestos `(lat,lng)` + columnas `bbox_min_lat/bbox_max_lat/bbox_min_lng/bbox_max_lng` para consulta por viewport. **No** usar tipos SPATIAL de MySQL. |
| D9 | Copia de datos | Copia **por comando artisan idempotente**, no por `INSERT ... SELECT` a mano, y nunca por `mysqldump` restaurado encima. El original nunca se modifica. |
| D10 | Migraciones | **Solo aditivas.** `migrate:fresh` está PROHIBIDO. Nunca tocar tablas del módulo `Mapas` viejo con `ALTER`/`DROP`/`UPDATE`. |
| D11 | Base del modelo | Lo que ya existe (`source`, `cupboard`, `junction_box`, `pack`, `splitter`, equipo activo/pasivo) se conserva como **tipos de elemento**, no se tira. Encima se agregan puerto, hilo, empalme y enlace de servicio. |
| D12 | Código de colores de fibra | Por defecto **EIA/TIA-598**. Selector alterno **ABNT NBR 14771**. Configurable por instalación en catálogo, no hardcodeado. |
| D13 | Pérdidas de splitter balanceado (dB) | 1:2=4 · 1:4=7 · 1:8=11 · 1:16=15 · 1:32=19 · 1:64=22 · 1:128=26. Editables en catálogo; estos son los valores semilla. |
| D14 | Pérdidas de splitter desbalanceado (dB, paso/derivación) | 50/50=3.6/3.6 · 40/60=2.7/4.4 · 30/70=1.9/5.6 · 20/80=1.3/7.4 · 10/90=0.8/11 · 5/95=0.4/14 · 1/99=0.3/21. Editables en catálogo. |
| D15 | Pérdidas adicionales semilla | Fibra 1310nm = 0.35 dB/km · 1490/1550nm = 0.25 dB/km · fusión = 0.1 dB · conector mecánico = 0.5 dB · empalme mecánico = 0.3 dB. Editables. |
| D16 | Semáforo de ocupación de NAP | Gris 0–50% · Amarillo 51–70% · Naranja 71–99% · Rojo 100%. (Patrón tomado de AdminOLT/SmartOLT.) |
| D17 | Semáforo de salud de NAP | Verde señal estable · Amarillo variación · Rojo señal mala · Gris sin ONUs asociadas. Se calcula con el promedio de RX de las ONUs de esa NAP. |
| D18 | Fuente de señal/ONU | El módulo **MultiOLT (addon-gestion-red)** ya existente. **No** crear un lector de OLT nuevo ni duplicar el motor Huawei. Se consume lo que MultiOLT ya expone. |
| D19 | Relación con el cliente | El enlace es `cliente/ONT ↔ puerto de NAP ↔ hilo`. Se resuelve **por nombre/serie**, nunca por ID de BD (dev y prod divergen). |
| D20 | Importación prioritaria | 1º KML/KMZ · 2º GeoJSON · 3º CSV · 4º DXF/SHP (este último puede quedar en fase posterior si no da el tiempo). |
| D21 | Exportación | KML + GeoJSON + carta de empalme en PDF + BOM en XLSX. |
| D22 | Árbol de navegación | Máximo **3 niveles derivados** (Zona → Tipo → Elemento). Las carpetas manuales del módulo viejo se conservan como atributo `proyecto` del elemento, **no** como estructura de navegación. |
| D23 | Alta de NAP | Máximo **3 pasos**: clic en el mapa → tipo + splitter → guardar. Zona, proyecto y nomenclatura se heredan del tramo/zona donde cayó el clic. |
| D24 | Nomenclatura automática | Patrón existente `T-{ZONA}-FO{HILOS}-{N}` para troncales y `NAP-{ZONA}-{N}` para NAPs. Correlativo por zona, sin huecos, generado en servidor. |
| D25 | Permisos | Todo permiso nuevo se asigna a `super-administrator` y `DESARROLLADOR`, y se corre `permissions:sync-roles`. Nada de reusar permisos prestados del módulo viejo. |
| D26 | Criterio de corte (qué módulo gana) | Se decide en MR-27 con datos, no por gusto: paridad funcional al 100%, cero pérdidas de datos en la comparación, y que el trazo OLT→ONT funcione en una zona piloto real. |
| D27 | Zona piloto | **Tultitlán** (troncales `T-TULTITLAN-FO96-*`), por ser la mejor documentada hoy. |
| D28 | Frontend | Vue 3 + Quasar UMD sobre Blade, como el resto de MegaISP. Leaflet vía npm. `npm run prod` después de cada cambio `.vue`. |
| D29 | Alcance de inalámbrico/WiFi | **Fuera de alcance en esta ronda.** El módulo guarda el polígono de cobertura y el sector (azimut, apertura, alcance, altura) como dato importable, pero **no** se construye simulador de propagación. Los diseños vienen de UISP Design Center vía KMZ. |
| D30 | Multi-tenant / Medussa | Fuera de alcance en esta ronda. Se diseñan las tablas con `empresa_id` nullable previsto, pero no se implementa aislamiento. |

> Nota de siembra (2026-09-03): D1 y D26 decían "MR-26/MR-27" en el documento de origen; la
> comparativa es **MR-27** y el retiro es **MR-28**. Corregido aquí.
TXT;
    }
}
