<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Clasificación de los tipos de artículo sin categoría (item #572, decisión de Irving 2026-08-08).
 *
 * Se agrega la TERCERA categoría `equipo_cliente` (ONT, módem, teléfonos de casa: lo que se
 * presta/instala en el cliente) y se reparte el resto entre las tres según su naturaleza.
 *
 * ⚠️ ALCANCE REAL vs. el enunciado del item: el item hablaba de "~18 tipos en null" — esa cifra era
 * la lista de DUDOSOS de la Fase A, no el total. En la BD había **377 de 401** tipos sin clasificar.
 * Esta migración clasifica los que se pueden determinar por su naturaleza y deja deliberadamente en
 * `null` los AMBIGUOS (listados abajo) para que los decida Irving: adivinar una categoría equivocada
 * es peor que dejarla vacía, porque el portal la muestra como un hecho.
 *
 * REGLAS DE SEGURIDAD:
 * - Solo toca filas con `categoria IS NULL` → nunca pisa una clasificación ya hecha a mano.
 * - Empareja por NOMBRE NORMALIZADO (trim + espacios colapsados + mayúsculas), NO por id: los ids
 *   de dev y prod NO coinciden y en el catálogo hay nombres con espacios dobles
 *   ("BRAZO SIN  HERRAJE", "TUERCA DE ACERO  1/4").
 * - Idempotente: re-correrla no cambia nada.
 */
return new class extends Migration
{
    /** Durable: se USA y se DEVUELVE (incluye EPP, instrumentos de medición y equipo de oficina). */
    private array $herramienta = [
        'ADAPTADOR DE DISCO DURO', 'ARO DE LUZ', 'BANCO', 'BARBIQUEJO', 'BASE CON VENTILADOR',
        'BOCINA', 'BOMBA', 'BROCA', 'BROCAS', 'BROCHA', 'CAJA DE HERRAMIENTA', 'CAJA FUERTE',
        'CALEFACCION', 'CAMARAS', 'CARDA', 'CARPA', 'CASCOS', 'CAUTIN', 'CEGUETA', 'CHALECO',
        'CHALECOS', 'CONO', 'CORTADORA FIBER', 'CORTADORAS SENCILLAS', 'DADO',
        'DESPACHADOR DE AGUA', 'DISCO DURO', 'DISCO SOLIDO', 'DISCOS DUROS DE PC', 'EMPUÑADURA',
        'ENGRAPADORA', 'ESCOBA', 'ESPATULA', 'EXTENSION', 'FUSIONADORA', 'GENERADOR', 'GORRO',
        'GUIA', 'HERRAMIENTAS', 'IMPRESORA', 'INFLADOR', 'JALADOR', 'LAPTOP', 'LIJADORA', 'LIMA',
        'LLAVE DE GAS', 'LLAVE INGLESA', 'LLAVES', 'MALACATE', 'MASETA', 'MASETAS', 'MEDIDOR',
        'MEGAFONO', 'MESA', 'METRO', 'MOUSE', 'MUEBLE', 'MULTICONTACTO', 'NAVAJA', 'OTDR', 'PC',
        'PINZAS', 'PISTOLA', 'PISTOLA DE PINTURA', 'PLAYERA SKY', 'PLAYO', 'PRENSAS', 'PROBADOR',
        'PUNTAS', 'RAM', 'REFLECTOR', 'REGLA', 'REGULADOR', 'REMACHADORA', 'SIRENA', 'TECLADO',
        'TIJERA', 'TIJERAS', 'UPS', 'VENTILADOR',
    ];

    /** Consumible: se GASTA o queda instalado en la red (no vuelve al almacén). */
    private array $material = [
        'ABRASADERA', 'ABRAZADERA', 'ABRAZADERAS', 'ABRAZADORES', 'ABRAZADORES DE ALUMINIO',
        'ACEITE', 'ACERO', 'ACIDO MURIATICO', 'ACOPLADOR', 'ACOPLADORES', 'AEROSOL BLANCO',
        'AFLOJATODO', 'ALAMBRE', 'ALCOHOL', 'ALMOHADILLA', 'ANCLAJE', 'ANILLO', 'APAGADOR',
        'ARANDELAS', 'ARMELLA', 'ARMELLA CARRADA', 'ARMELLAS', 'BANDAS AMARILLAS',
        'BANDEJA DE EMPALME', 'BARNIZ', 'BATERIA', 'BLANCO DE ESPAÑA', 'BLOCKE', 'BOLSA',
        'BOMBILLO', 'BOTE', 'BRAZO', 'BRAZO CON HERRAJE', 'BRAZO SIN HERRAJE', 'BRAZOS', 'BUJE',
        'CABLE', 'CABLE AUDIO', 'CABLE AUDIO/VIDEO', 'CABLE AV', 'CABLE DE CORRIENTE',
        'CABLE DE CORRIENTE PARA POE', 'CABLE F.O.', 'CABLE LUZ', 'CABLE OFC', 'CABLE PIN',
        'CABLE RCA', 'CABLE SATA', 'CABLE UTP', 'CABLE VGA', 'CABLE VIDEO', 'CABLES',
        'CABLES COAXIALES', 'CABLES DE CORRIENTE PC', 'CAJA', 'CAJA DE DISTRIBUCION',
        'CAJA DE EMPALME', 'CAJA DE HOJAS', 'CAJA DE TERMINACION', 'CAJA EMPALME', 'CAJA NAP',
        'CAJA/CIERRE', 'CAJA/FIBRA', 'CAJAS', 'CAJAS DE DISTRIBUCION', 'CARCAS', 'CARPETAS',
        'CARRETE', 'CATALIZADOR', 'CENTRO DE CARGA', 'CERRADURA', 'CHAROLA', 'CHAROLAS DE FUSION',
        'CIERRE', 'CILICON', 'CINCHOS', 'CINCHOS DE VELCRO', 'CINTA DE AISLAR', 'CINTA DE SELLADO',
        'CINTA DOBLE CARA', 'CINTA DOLECARA', 'CLAVIJA', 'CLORALEX AEROSOL', 'CODO', 'CONECTOR',
        'CONECTOR RJ45', 'CONECTORES', 'CONTACTO', 'CORREA', 'DISCO', 'DIVISOR', 'DIVISOR DE SEÑAL',
        'DOCUMENTOS', 'DUAL VGA', 'DVD', 'EMBASES', 'ENCHUFE', 'ENVASE', 'ENVASES', 'ESMALTE',
        'ESPONJA', 'ESPUMA', 'ESQUINERO', 'ESTOPA', 'ETIQUETA', 'ETIQUETAS', 'ETIQUETAS PAR CAJAS',
        'FIBER', 'FIBRA CONEXTERIZADA', 'FILTRO DE AGUA', 'FLEJE', 'FLEJES', 'FLYERS', 'FOCOS LED',
        'FUEL', 'GANCHO', 'GANCHO MULTIUSO', 'GANCHOS', 'GARRAFA', 'GARRAFON', 'GARRAFONES',
        'GLOBOS', 'GONHER', 'GRASA', 'GRASA HIDROFUGA', 'HDMI', 'HEBILLA', 'HEBILLAS',
        'HEBILLAS DE ACERO', 'HERRAJES', 'HILO CAÑAMO', 'HOJAS', 'INSECTICIDA', 'JUMPPERS',
        'KIT DE CAJA', 'KOLA LOCA', 'LAJAS', 'LONA', 'LUBRICANTE', 'LUMINARIOS', 'MANGAS',
        'MANGUERA', 'MANIJA', 'MASTIL', 'MICROALAMBRE', 'MICROFILTROS', 'MUELAS', 'NYLON', 'OREJA',
        'ORGANIZADOR', 'ORGANIZADOR VERTICAL', 'PANEL', 'PAPEL', 'PAPEL TERMICO', 'PAPELERIA',
        'PASTA', 'PEGAMENTO', 'PIEDRA', 'PIJA', 'PIJA FIJADORA DE 10X1', 'PIJA TABLAROCA', 'PILAS',
        'PINTURA', 'PLACA', 'PLUMAS', 'PUNTILLAS', 'RAFIA', 'RAQUETAS', 'REJA', 'REMACHES',
        'REMATE', 'REMOVEDOR DE PINTURA', 'RESANADOR', 'ROLLOS PARA TIKETS', 'ROZETAS', 'SELLADOR',
        'SEPARADOR', 'SILICON', 'SOCKET', 'SOLDADURA', 'SOPORTE', 'SPLITTER', 'SPLITTESR',
        'SUJETADOR', 'TAPA', 'TAPA CONTACTO', 'TAPADERA', 'TAPAS', 'TAPON', 'TAPONES', 'TAQUETES',
        'TAQUETES DE PLASTICO', 'TENSOR', 'TENSORES', 'TERMINAL', 'TOALLITAS', 'TONER', 'TORNILLO',
        'TORNILLOS', 'TORNILLOS DE ACERO', 'TUBO', 'TUBO DE HIERRO', 'TUERCA DE ACERO 1/4', 'UNION',
        'UTP', 'VALVULA', 'VELCRO', 'VINILO ADHESIVO', 'VISAGRA', 'WIRE',
    ];

    /** Se PRESTA/INSTALA en el domicilio del cliente (la categoría nueva de este item). */
    private array $equipoCliente = [
        'ONT', 'MODEM', 'MODEM HUAWEI', 'MODEM TELMEX', 'MODEM ZT', 'MODEM"S', 'ROUTER',
        'TELEFONOS DE CASA', 'DECODIFICADOR', 'DESCODIFICADOR', 'TV BOX',
    ];

    /**
     * DELIBERADAMENTE SIN CLASIFICAR — para decisión de Irving. Se documentan aquí para que la
     * próxima sesión no los "resuelva" adivinando. Tres motivos distintos:
     *
     * (1) EQUIPO DE RED / INFRAESTRUCTURA — no es del cliente, no se gasta y no es una herramienta
     *     que el técnico devuelva: es activo fijo de la red. Sugiere una CUARTA categoría
     *     ("equipo de red"). Tipos: ANTENA, ANTENAS, ANTENA HP, ANTENAS DE IMAN, PUNTA ANTENA,
     *     CISCO SYSTEMS, HUAWEI, MIKROTIKS, NANO, NOT ENGINE, OLT, RADIO, RAISECOM, ROCKET, ROKET,
     *     RPCKET, SECTORES, SWITCH, TP-LINK, TSMS.
     *
     * (2) DUDOSOS DE NEGOCIO que Irving ya había marcado — dependen de cómo se entreguen:
     *     ELIMINADOR y POE (¿van dentro del kit del cliente junto al ONT, o son material de
     *     almacén?) y POWER (¿medidor de potencia óptica = herramienta, o fuente de poder?).
     *
     * (3) NOMBRE GENÉRICO, ILEGIBLE O DATO DE PRUEBA — no se puede determinar la naturaleza desde
     *     el nombre; varios son duplicados/erratas del catálogo y deberían depurarse:
     *     3M-288, ABS, ADAPTADOR, BANDA, BANDEJA, BANDOLA, BARITAS, CANASTA, CANASTAS, CEDA,
     *     CHICHOS, COLADORA, COMAL, CONSOLA, CONTROL, COPITAS, INSTALACION, KIT, KIT DE RESPUESTOS,
     *     MAQUINA, MATERIAL USADO, MEDIA, MICRO-V, MOLINETE, NUDOS, OPEN BOCK, OPEN BOX, PARRILLA,
     *     PATAS NEGRAS, PONTERIA, PORTA CARBONES, PROTECCION, PROTECTOR, PUJES, QUICK, REVELADOR,
     *     SALCHICHA, SALLA, SEGURO, SELLOS, SOMBRA, SOMBRAS, STAN MEGANET, TARJETAS, TELEFONO,
     *     TELEFONOS, Tipo 1 Prueba, TRAMO, TRANSFORMADOR, UNIDAD, VENTANA, VOLTEADOR.
     *     (TELEFONO/TELEFONOS quedan fuera a propósito: "TELEFONOS DE CASA" ya existe aparte, así
     *     que estos dos podrían ser teléfonos del personal, no del cliente.)
     */
    private const AMBIGUOS_DOCUMENTADOS = true;

    public function up(): void
    {
        $lookup = [];
        foreach (['herramienta' => $this->herramienta, 'material' => $this->material, 'equipo_cliente' => $this->equipoCliente] as $cat => $nombres) {
            foreach ($nombres as $n) {
                $lookup[$this->normaliza($n)] = $cat;
            }
        }

        // Solo los que hoy están sin clasificar: nunca se pisa una decisión previa.
        $pendientes = DB::table('inventory_item_types')
            ->whereNull('categoria')->whereNull('deleted_at')
            ->get(['id', 'name']);

        $porCategoria = [];
        foreach ($pendientes as $t) {
            $cat = $lookup[$this->normaliza($t->name)] ?? null;
            if ($cat !== null) {
                $porCategoria[$cat][] = $t->id;
            }
        }

        foreach ($porCategoria as $cat => $ids) {
            foreach (array_chunk($ids, 500) as $lote) {
                DB::table('inventory_item_types')
                    ->whereIn('id', $lote)->whereNull('categoria')
                    ->update(['categoria' => $cat]);
            }
        }
    }

    /**
     * `down()` vacío A PROPÓSITO: revertir dejaría los tipos sin categoría otra vez, perdiendo la
     * clasificación de negocio (y borraría también la que un admin haya hecho a mano encima).
     * La columna la crea/borra su propia migración (2026_07_03_120000).
     */
    public function down(): void
    {
    }

    private function normaliza(string $nombre): string
    {
        return preg_replace('/\s+/', ' ', trim(mb_strtoupper($nombre, 'UTF-8')));
    }
};
