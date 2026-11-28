<?php

namespace App\Services\Asistente;

use App\Models\Asistente\Adjunto;
use App\Models\Asistente\Conversacion;
use App\Models\Asistente\Mensaje;
use App\Models\User;
use App\Services\Asistente\Adjuntos\AdjuntoRechazado;
use App\Services\Asistente\Adjuntos\ProcesadorAdjuntos;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * Archivos del chat de JARVIS.
 *
 * - Llegan en partes de ~1 MB (así funcionan aunque el servidor limite el tamaño de cada subida) y
 *   al completarse se preparan una vez (ProcesadorAdjuntos).
 * - Se borran solos: al vencer (limites.json → adjuntos.horas_de_vida), al empezar un tema nuevo y
 *   con `asistente:purgar-adjuntos`. La fila queda como constancia, ya sin bytes.
 * - paraIa() arma lo que la IA recibe de cada archivo: las fotos, PDF y cuadros de video como
 *   imagen/documento cuando el proveedor los acepta, y el resto como texto con una vista previa.
 */
class AdjuntosAsistente
{
    public const TIPOS = [
        'imagen'    => ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp', 'heic', 'heif'],
        'pdf'       => ['pdf'],
        'hoja'      => ['xlsx', 'xlsm', 'xls', 'ods', 'csv', 'tsv'],
        'documento' => ['docx', 'odt', 'rtf', 'txt', 'md', 'json', 'xml', 'html', 'htm', 'log', 'sql', 'yaml', 'yml', 'ini', 'vcf', 'eml'],
        'video'     => ['mp4', 'mov', 'm4v', 'webm', 'mkv', 'avi', '3gp', 'mpeg', 'mpg', 'wmv'],
        'audio'     => ['mp3', 'wav', 'ogg', 'oga', 'opus', 'm4a', 'aac', 'flac', 'amr', 'weba'],
    ];

    /** MIME reales que nunca se aceptan, aunque la extensión diga otra cosa. */
    private const MIME_PROHIBIDO = '#(x-dosexec|x-executable|x-sharedlib|x-mach-binary|x-msdownload|x-php|x-httpd-php|x-sh|x-shellscript|java-archive)#i';

    /** Lo que el contenido real (finfo) debe parecer según el tipo que dice la extensión. */
    private const MIME_ESPERADO = [
        'imagen'    => '#^(image/|application/octet-stream$)#',
        'pdf'       => '#^application/(pdf|x-pdf)$#',
        'hoja'      => '#^(text/|application/(zip|vnd\.|x-ole-storage|CDFV2|csv|octet-stream|encrypted))#i',
        'documento' => '#^(text/|application/(zip|vnd\.|rtf|json|xml|x-empty|octet-stream)|message/|inode/x-empty)#',
        'video'     => '#^(video/|application/(octet-stream|x-matroska|mp4))#',
        'audio'     => '#^(audio/|video/|application/(octet-stream|ogg))#',
    ];

    private const PARTE_MAX_BYTES = 3 * 1024 * 1024;

    private const CARACTERES_EN_LINEA = 15000;

    public function __construct(
        private ConocimientoAsistente $conocimiento,
        private ProcesadorAdjuntos $procesador,
    ) {
    }

    public static function tipoDeExtension(string $extension): ?string
    {
        foreach (self::TIPOS as $tipo => $extensiones) {
            if (in_array(strtolower($extension), $extensiones, true)) {
                return $tipo;
            }
        }

        return null;
    }

    public function ajustes(): array
    {
        return [
            'max_mb'          => (int) $this->conocimiento->ajuste('adjuntos.max_mb', 100),
            'max_mb_hoja'     => (int) $this->conocimiento->ajuste('adjuntos.max_mb_hoja', 40),
            'horas_de_vida'   => (int) $this->conocimiento->ajuste('adjuntos.horas_de_vida', 24),
            'max_por_mensaje' => (int) $this->conocimiento->ajuste('adjuntos.max_por_mensaje', 10),
            'extensiones'     => array_merge(...array_values(self::TIPOS)),
        ];
    }

    /**
     * Recibe una parte. En la última, junta el archivo y lo prepara.
     *
     * @param array{subida:string, indice:int, total:int, nombre:string, tamano:int} $d
     */
    public function recibirParte(User $user, array $d, UploadedFile $parte): Adjunto
    {
        $ajustes = $this->ajustes();
        $indice = (int) $d['indice'];
        $total = (int) $d['total'];

        if ($parte->getSize() > self::PARTE_MAX_BYTES) {
            throw new AdjuntoRechazado('Esa parte del archivo llegó demasiado grande.');
        }

        if ($indice === 0) {
            $adjunto = $this->iniciar($user, $d, $ajustes);
        } else {
            $adjunto = Adjunto::where('uuid', $d['subida'])->where('user_id', $user->id)->where('estado', Adjunto::SUBIENDO)->first();
            if (!$adjunto) {
                throw new AdjuntoRechazado('Se interrumpió la subida de ese archivo. Vuelve a adjuntarlo.');
            }
            if ((int) $adjunto->meta('partes', 0) !== $indice) {
                throw new AdjuntoRechazado('Las partes del archivo llegaron desordenadas. Vuelve a adjuntarlo.');
            }
        }

        $temporal = $adjunto->ruta('original.parte');
        file_put_contents($temporal, (string) file_get_contents($parte->getRealPath()), FILE_APPEND | LOCK_EX);
        clearstatcache(true, $temporal);
        if (filesize($temporal) > (int) $adjunto->bytes || filesize($temporal) > $ajustes['max_mb'] * 1048576) {
            $this->borrar($adjunto);

            throw new AdjuntoRechazado('El archivo pesa más de lo que dijo al empezar a subirse.');
        }
        $adjunto->update(['meta' => array_merge($adjunto->meta ?? [], ['partes' => $indice + 1])]);

        return $indice + 1 >= $total ? $this->terminar($adjunto) : $adjunto;
    }

    private function iniciar(User $user, array $d, array $ajustes): Adjunto
    {
        $nombre = trim(preg_replace('/[\x00-\x1F\x7F\/\\\\]+/u', ' ', basename((string) $d['nombre'])));
        $nombre = mb_substr($nombre !== '' ? $nombre : 'archivo', 0, 200);
        $extension = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));
        $tipo = self::tipoDeExtension($extension);
        if ($tipo === null) {
            throw new AdjuntoRechazado('Ese tipo de archivo no lo puedo leer todavía. Puedo con fotos, PDF, Excel o CSV, Word o texto, videos y audios.');
        }

        $tamano = (int) $d['tamano'];
        $maximo = $tipo === 'hoja' ? $ajustes['max_mb_hoja'] : $ajustes['max_mb'];
        if ($tamano <= 0) {
            throw new AdjuntoRechazado('Ese archivo está vacío.');
        }
        if ($tamano > $maximo * 1048576) {
            throw new AdjuntoRechazado("Ese archivo pesa más de {$maximo} MB, que es lo máximo que puedo recibir.");
        }
        if (Adjunto::where('user_id', $user->id)->where('estado', Adjunto::SUBIENDO)->where('created_at', '>', now()->subHour())->count() >= 20) {
            throw new AdjuntoRechazado('Tienes muchos archivos subiéndose al mismo tiempo. Espera a que terminen.');
        }
        if (Adjunto::where('uuid', $d['subida'])->exists()) {
            throw new AdjuntoRechazado('Ese archivo ya se estaba subiendo.');
        }

        $carpeta = 'asistente/adjuntos/' . $user->id . '/' . $d['subida'];
        File::ensureDirectoryExists(storage_path('app/' . $carpeta), 0775);

        return Adjunto::create([
            'uuid'      => $d['subida'],
            'user_id'   => $user->id,
            'origen'    => 'usuario',
            'nombre'    => $nombre,
            'extension' => $extension,
            'tipo'      => $tipo,
            'bytes'     => $tamano,
            'carpeta'   => $carpeta,
            'estado'    => Adjunto::SUBIENDO,
            'meta'      => ['partes' => 0],
            'expira_at' => now()->addHours(max(1, $ajustes['horas_de_vida'])),
        ]);
    }

    private function terminar(Adjunto $adjunto): Adjunto
    {
        $final = $adjunto->original();
        rename($adjunto->ruta('original.parte'), $final);
        clearstatcache(true, $final);

        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($final);
        if (preg_match(self::MIME_PROHIBIDO, $mime) || str_contains($mime, 'executable')) {
            $this->borrar($adjunto, Adjunto::ERROR, 'programa');

            throw new AdjuntoRechazado('Ese archivo parece un programa y no lo puedo recibir.');
        }
        if (!preg_match(self::MIME_ESPERADO[$adjunto->tipo] ?? '#.#', $mime)) {
            $this->borrar($adjunto, Adjunto::ERROR, "contenido {$mime}");

            throw new AdjuntoRechazado('Ese archivo no es lo que dice su nombre (.' . $adjunto->extension . '). Ábrelo en tu computadora y guárdalo de nuevo.');
        }
        $adjunto->update(['mime' => $mime, 'bytes' => filesize($final)]);

        try {
            @set_time_limit(300);
            $meta = $this->procesador->procesar($adjunto);
            $adjunto->update(['estado' => Adjunto::LISTO, 'meta' => $meta]);
        } catch (AdjuntoRechazado $e) {
            $this->borrar($adjunto, Adjunto::ERROR, $e->getMessage());

            throw $e;
        } catch (\Throwable $e) {
            Log::error('[JARVIS] no se pudo preparar un archivo', ['adjunto' => $adjunto->id, 'tipo' => $adjunto->tipo, 'error' => $e->getMessage()]);
            $this->borrar($adjunto, Adjunto::ERROR, 'No pude leer ese archivo.');

            throw new AdjuntoRechazado('No pude leer ese archivo. Revisa que abra bien en tu computadora y vuelve a intentarlo.');
        }

        $this->purgarVencidos(50);

        return $adjunto->fresh();
    }

    /** Archivos listos del usuario que todavía no van en ningún mensaje, en el orden pedido. */
    public function paraMensaje(User $user, array $ids): Collection
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if (!$ids) {
            return collect();
        }
        if (count($ids) > $this->ajustes()['max_por_mensaje']) {
            throw new AdjuntoRechazado('Son demasiados archivos para un solo mensaje. Mándalos en varios.');
        }

        $adjuntos = Adjunto::whereIn('id', $ids)->where('user_id', $user->id)->whereNull('mensaje_id')
            ->where('estado', Adjunto::LISTO)->get()->keyBy('id');
        if ($adjuntos->count() !== count($ids)) {
            throw new AdjuntoRechazado('Uno de los archivos ya no está disponible. Vuelve a adjuntarlo.');
        }

        return collect($ids)->map(fn ($id) => $adjuntos[$id]);
    }

    public function vincular(Collection $adjuntos, Mensaje $mensaje): void
    {
        if ($adjuntos->isNotEmpty()) {
            Adjunto::whereIn('id', $adjuntos->pluck('id'))->update(['mensaje_id' => $mensaje->id, 'conversacion_id' => $mensaje->conversacion_id]);
        }
    }

    /** Un archivo que JARVIS genera (p. ej. el Excel de un resultado). $escribir recibe la ruta destino. */
    public function crearGenerado(User $user, ?Conversacion $conversacion, string $nombre, callable $escribir): Adjunto
    {
        $uuid = (string) \Illuminate\Support\Str::uuid();
        $extension = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));
        $carpeta = 'asistente/adjuntos/' . $user->id . '/' . $uuid;
        File::ensureDirectoryExists(storage_path('app/' . $carpeta), 0775);

        $adjunto = Adjunto::create([
            'uuid'            => $uuid,
            'user_id'         => $user->id,
            'conversacion_id' => $conversacion?->id,
            'origen'          => 'jarvis',
            'nombre'          => $nombre,
            'extension'       => $extension,
            'tipo'            => self::tipoDeExtension($extension) ?? 'documento',
            'carpeta'         => $carpeta,
            'estado'          => Adjunto::SUBIENDO,
            'expira_at'       => now()->addHours(max(1, $this->ajustes()['horas_de_vida'])),
        ]);

        try {
            $escribir($adjunto->original());
            clearstatcache(true, $adjunto->original());
            $adjunto->update([
                'estado' => Adjunto::LISTO,
                'bytes'  => (int) @filesize($adjunto->original()),
                'mime'   => (string) (new \finfo(FILEINFO_MIME_TYPE))->file($adjunto->original()),
            ]);
        } catch (\Throwable $e) {
            $this->borrar($adjunto, Adjunto::ERROR, 'No se pudo generar.');

            throw $e;
        }

        return $adjunto;
    }

    public function borrar(Adjunto $adjunto, string $estado = Adjunto::BORRADO, ?string $error = null): void
    {
        $carpeta = storage_path('app/' . $adjunto->carpeta);
        // Solo dentro de la carpeta de adjuntos: una fila alterada nunca puede borrar otra cosa.
        if (str_starts_with($adjunto->carpeta, 'asistente/adjuntos/') && !str_contains($adjunto->carpeta, '..') && is_dir($carpeta)) {
            File::deleteDirectory($carpeta);
        }
        $adjunto->update(array_filter([
            'estado'     => $estado,
            'error'      => $error,
            'borrado_at' => now(),
        ], fn ($v) => $v !== null));
    }

    public function borrarDeConversacion(Conversacion $conversacion): int
    {
        $n = 0;
        Adjunto::where('conversacion_id', $conversacion->id)->whereNull('borrado_at')->each(function (Adjunto $a) use (&$n) {
            $this->borrar($a);
            $n++;
        });

        return $n;
    }

    /** Borra los archivos vencidos y las subidas que se quedaron a medias. */
    public function purgarVencidos(int $maximo = 1000): int
    {
        $n = 0;
        Adjunto::whereNull('borrado_at')
            ->where(fn ($q) => $q->where('expira_at', '<', now())
                ->orWhere(fn ($q) => $q->where('estado', Adjunto::SUBIENDO)->where('created_at', '<', now()->subHours(3))))
            ->orderBy('id')->limit($maximo)->get()
            ->each(function (Adjunto $a) use (&$n) {
                $this->borrar($a);
                $n++;
            });

        return $n;
    }

    // ── Lo que recibe la IA ───────────────────────────────────────────────────────────────────

    /**
     * Texto y binarios de un archivo para el turno del usuario donde llegó.
     * $completo: el archivo está en uno de los últimos mensajes → va con su contenido; si no, solo
     * se menciona (la IA lo puede volver a leer con leer_archivo mientras exista).
     *
     * @param array{imagenes:bool, pdf:bool} $capacidades
     * @return array{texto:string, binarios:array<int, array{mime:string, data:string, nombre:string}>}
     */
    public function paraIa(Adjunto $a, array $capacidades, bool $completo): array
    {
        $cabeza = "Archivo #{$a->id} «{$a->nombre}»";
        if (!$a->disponible()) {
            return ['texto' => "[{$cabeza}: ya se borró (los archivos se borran solos). Si lo necesitas, pide que lo vuelvan a mandar.]", 'binarios' => []];
        }
        $resumen = (string) $a->meta('resumen', $a->tipo);
        if (!$completo) {
            $extra = $a->tipo === 'hoja' ? ' En consultas: ' . implode(', ', $this->tablasDeHoja($a)) . '.' : '';

            return ['texto' => "[{$cabeza} ({$resumen}), mandado antes en la plática. Para revisarlo otra vez usa leer_archivo con archivo={$a->id}.{$extra}]", 'binarios' => []];
        }

        return match ($a->tipo) {
            'imagen'    => $this->imagenParaIa($a, $cabeza, $resumen, $capacidades),
            'pdf'       => $this->pdfParaIa($a, $cabeza, $resumen, $capacidades),
            'hoja'      => ['texto' => $this->vistaPreviaHoja($a, $cabeza, $resumen), 'binarios' => []],
            'video'     => $this->videoParaIa($a, $cabeza, $resumen, $capacidades),
            'audio'     => ['texto' => "[{$cabeza} ({$resumen}). " . $this->textoTranscripcion($a) . ']', 'binarios' => []],
            default     => ['texto' => $this->textoEnLinea($a, $cabeza, $resumen), 'binarios' => []],
        };
    }

    private function imagenParaIa(Adjunto $a, string $cabeza, string $resumen, array $cap): array
    {
        $vista = $a->meta('vista');
        if (!$cap['imagenes'] || !$vista || !is_file($a->ruta($vista['archivo']))) {
            return ['texto' => "[{$cabeza} ({$resumen}): la IA configurada para JARVIS no puede ver imágenes. Díselo al usuario.]", 'binarios' => []];
        }

        return [
            'texto'    => "[{$cabeza} ({$resumen}): la tienes a la vista.]",
            'binarios' => [['mime' => $vista['mime'], 'data' => base64_encode((string) file_get_contents($a->ruta($vista['archivo']))), 'nombre' => $a->nombre]],
        ];
    }

    private function pdfParaIa(Adjunto $a, string $cabeza, string $resumen, array $cap): array
    {
        $paginas = (int) $a->meta('paginas', 0);
        if ($cap['pdf'] && $a->bytes <= 25 * 1048576 && $paginas <= 100) {
            return [
                'texto'    => "[{$cabeza} ({$resumen}): lo tienes a la vista. Su texto también se puede leer con leer_archivo archivo={$a->id}.]",
                'binarios' => [['mime' => 'application/pdf', 'data' => base64_encode((string) file_get_contents($a->original())), 'nombre' => $a->nombre]],
            ];
        }
        if ($a->meta('escaneado') && $cap['imagenes']) {
            $binarios = [];
            foreach ((array) $a->meta('imagenes', []) as $img) {
                if (is_file($a->ruta($img))) {
                    $binarios[] = ['mime' => 'image/jpeg', 'data' => base64_encode((string) file_get_contents($a->ruta($img))), 'nombre' => $img];
                }
            }
            $n = count($binarios);

            return ['texto' => "[{$cabeza} ({$resumen}): es un PDF escaneado; tienes a la vista sus primeras {$n} páginas como imágenes.]", 'binarios' => $binarios];
        }

        return ['texto' => $this->textoEnLinea($a, $cabeza, $resumen), 'binarios' => []];
    }

    private function videoParaIa(Adjunto $a, string $cabeza, string $resumen, array $cap): array
    {
        $binarios = [];
        $segundos = [];
        if ($cap['imagenes']) {
            foreach ((array) $a->meta('cuadros', []) as $c) {
                if (is_file($a->ruta($c['archivo']))) {
                    $binarios[] = ['mime' => 'image/jpeg', 'data' => base64_encode((string) file_get_contents($a->ruta($c['archivo']))), 'nombre' => $c['archivo']];
                    $segundos[] = rtrim(rtrim(number_format((float) $c['segundo'], 1, '.', ''), '0'), '.') . ' s';
                }
            }
        }
        $vista = $binarios
            ? 'Tienes a la vista ' . count($binarios) . ' cuadros del video, en orden (segundos ' . implode(', ', $segundos) . ').'
            : 'La IA configurada para JARVIS no puede ver imágenes, así que no ves el video.';

        return ['texto' => "[{$cabeza} ({$resumen}). {$vista} " . $this->textoTranscripcion($a) . ']', 'binarios' => $binarios];
    }

    private function textoTranscripcion(Adjunto $a): string
    {
        if ($a->tipo === 'video' && !$a->meta('con_audio')) {
            return 'No tiene sonido.';
        }
        if (!$a->meta('transcripcion')) {
            return $a->meta('transcripcion_error') === 'sin IA de transcripción'
                ? 'No pude escuchar el audio: falta asignar la IA de transcripción (Integraciones → Módulos IA, «voip.transcripcion»).'
                : 'No pude escuchar el audio.';
        }
        $texto = trim((string) @file_get_contents($a->ruta('transcripcion.txt')));
        if ($texto === '') {
            return 'No se entiende nada de lo que se oye (silencio o ruido).';
        }

        return 'Lo que se oye (primeros 5 minutos, transcrito automáticamente): «' . mb_substr($texto, 0, 8000) . '»';
    }

    private function textoEnLinea(Adjunto $a, string $cabeza, string $resumen): string
    {
        $texto = $this->texto($a);
        $largo = mb_strlen($texto);
        $corte = mb_substr($texto, 0, self::CARACTERES_EN_LINEA);
        $sigue = $largo > self::CARACTERES_EN_LINEA
            ? "\n…(sigue; lleva " . number_format(self::CARACTERES_EN_LINEA) . ' de ' . number_format($largo) . " caracteres: usa leer_archivo archivo={$a->id} desde=" . self::CARACTERES_EN_LINEA . ')'
            : '';

        return "[{$cabeza} ({$resumen}). Su contenido:]\n<<<\n{$corte}{$sigue}\n>>>";
    }

    private function vistaPreviaHoja(Adjunto $a, string $cabeza, string $resumen): string
    {
        $lineas = ["[{$cabeza} ({$resumen}). Para leer más filas o filtrarlas usa leer_archivo archivo={$a->id}; para sumar, agrupar o cruzarlo con el sistema usa consultar_datos con archivos=[{$a->id}] y la tabla indicada (cada hoja trae además la columna _fila = número de renglón de datos).]"];
        $tablas = $this->tablasDeHoja($a);
        foreach (array_slice((array) $a->meta('hojas', []), 0, 6) as $i => $h) {
            $cols = array_map(fn ($c) => "{$c['id']} («{$c['titulo']}», {$c['tipo']})", array_slice($h['columnas'], 0, 40));
            $lineas[] = "Hoja {$h['n']} «{$h['nombre']}» → tabla {$tablas[$i]}: " . number_format($h['filas']) . ' filas' . (!empty($h['recortada']) ? ' (recortada a las primeras ' . number_format($h['filas']) . ')' : '') . '. Columnas: ' . implode(', ', $cols) . (count($h['columnas']) > 40 ? ', …' : '');

            $datos = $this->hoja($a, (int) $h['n']);
            foreach (array_slice($datos['filas'] ?? [], 0, 5) as $fila) {
                $lineas[] = '  | ' . implode(' | ', array_map(fn ($v) => $v === null ? '' : mb_substr((string) $v, 0, 40), array_slice($fila, 0, 14))) . ' |';
            }
        }

        return implode("\n", $lineas);
    }

    /** Nombre de cada hoja en consultas: archivo_<id> la primera, archivo_<id>_<n> las demás. */
    public function tablasDeHoja(Adjunto $a): array
    {
        return array_map(fn ($h) => self::nombreTabla($a->id, (int) $h['n']), (array) $a->meta('hojas', []));
    }

    public static function nombreTabla(int $id, int $hoja): string
    {
        return $hoja <= 1 ? "archivo_{$id}" : "archivo_{$id}_{$hoja}";
    }

    public function hoja(Adjunto $a, int $n): ?array
    {
        $ruta = $a->ruta("hoja-{$n}.json");

        return $a->disponible() && is_file($ruta) ? json_decode((string) file_get_contents($ruta), true) : null;
    }

    public function texto(Adjunto $a): string
    {
        foreach (['texto.txt', 'transcripcion.txt'] as $archivo) {
            if (is_file($a->ruta($archivo))) {
                return (string) file_get_contents($a->ruta($archivo));
            }
        }

        return '';
    }
}
