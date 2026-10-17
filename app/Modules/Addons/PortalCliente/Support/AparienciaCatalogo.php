<?php

namespace App\Modules\Addons\PortalCliente\Support;

/**
 * Catálogo fijo de Configuración → Portal Cliente: valores por defecto (= el rediseño aprobado,
 * commit 6510402a), biblioteca local de iconos, paleta de colores de tarjetas y etiquetas
 * legibles para el resumen de cambios.
 *
 * Los iconos son markup SVG de este archivo (confiable): la configuración solo guarda la CLAVE y
 * se valida contra esta lista, así que nunca se pinta SVG que haya llegado de un usuario.
 */
final class AparienciaCatalogo
{
    public const TIPOS_IMAGEN = ['escritorio', 'movil', 'respaldo', 'logo'];

    /** Mínimos de cada tipo de imagen (ancho × alto, px). */
    public const MINIMOS = [
        'escritorio' => [1280, 600],
        'movil'      => [600, 800],
        'respaldo'   => [1280, 600],
        'logo'       => [120, 40],
    ];

    /** Máximos tras re-codificar (se reduce manteniendo proporción, nunca se agranda). */
    public const MAXIMOS_SALIDA = [
        'escritorio' => [2560, 2560],
        'movil'      => [1440, 2560],
        'respaldo'   => [2560, 2560],
        'logo'       => [1600, 400],
    ];

    public const TARJETAS = ['internet', 'facturas', 'servicios', 'soporte'];

    public const COLORES_CONFIG = ['primario', 'profundo', 'noche', 'electrico', 'acento'];

    private const ICONOS = [
        'wifi'       => ['Wi-Fi', '<path d="M5 12.55a11 11 0 0 1 14.08 0"/><path d="M1.42 9a16 16 0 0 1 21.16 0"/><path d="M8.53 16.11a6 6 0 0 1 6.95 0"/><line x1="12" y1="20" x2="12.01" y2="20"/>'],
        'documento'  => ['Documento', '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/>'],
        'engranaje'  => ['Engranaje', '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/>'],
        'audifonos'  => ['Audífonos', '<path d="M3 18v-6a9 9 0 0 1 18 0v6"/><path d="M21 19a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3zM3 19a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-3a2 2 0 0 0-2-2H3z"/>'],
        'tarjeta'    => ['Tarjeta de pago', '<rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/>'],
        'escudo'     => ['Escudo', '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>'],
        'rayo'       => ['Rayo', '<polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>'],
        'telefono'   => ['Teléfono', '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>'],
        'chat'       => ['Mensaje', '<path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>'],
        'casa'       => ['Casa', '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>'],
        'reloj'      => ['Reloj', '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>'],
        'ubicacion'  => ['Ubicación', '<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>'],
        'usuario'    => ['Usuario', '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>'],
        'grafica'    => ['Gráfica', '<line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>'],
        'estrella'   => ['Estrella', '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>'],
        'regalo'     => ['Regalo', '<polyline points="20 12 20 22 4 22 4 12"/><rect x="2" y="7" width="20" height="5"/><line x1="12" y1="22" x2="12" y2="7"/><path d="M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7z"/><path d="M12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"/>'],
        'pantalla'   => ['Pantalla', '<rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/>'],
    ];

    /** clave => [etiqueta, color inicial, color final, sombra rgb] */
    private const COLORES_TARJETA = [
        'azul'     => ['Azul', '#00D5FF', '#008CFF', '0, 170, 255'],
        'verde'    => ['Verde', '#34D399', '#059669', '16, 185, 129'],
        'naranja'  => ['Naranja', '#FBBF24', '#F97316', '249, 115, 22'],
        'violeta'  => ['Violeta', '#C084FC', '#7C3AED', '139, 92, 246'],
        'cyan'     => ['Cian', '#67E8F9', '#0891B2', '6, 182, 212'],
        'rojo'     => ['Rojo', '#FB7185', '#E11D48', '225, 29, 72'],
        'rosa'     => ['Rosa', '#F9A8D4', '#DB2777', '219, 39, 119'],
        'amarillo' => ['Amarillo', '#FDE047', '#CA8A04', '202, 138, 4'],
    ];

    public static function defaults(): array
    {
        return [
            'fondo' => [
                'escritorio'      => null,
                'movil'           => null,
                'respaldo'        => null,
                'posicion_x'      => 50,
                'posicion_y'      => 50,
                'degradado'       => 80,
                'oscurecer'       => 0,
                'opacidad_imagen' => 100,
            ],
            'identidad' => [
                'logo'            => null,
                'titulo'          => 'Portal Cliente',
                'subtitulo'       => 'Meganet Telecomunicaciones',
                'eslogan'         => 'Te conecta',
                'mostrar_eslogan' => false,
                'colores' => [
                    'primario'  => '#0759B8',
                    'profundo'  => '#061C48',
                    'noche'     => '#04112C',
                    'electrico' => '#008CFF',
                    'acento'    => '#00D5FF',
                ],
                'tarjetas'   => ['opacidad' => 62, 'radio' => 18],
                'formulario' => ['opacidad' => 86, 'desenfoque' => 18, 'radio' => 24],
            ],
            'mensajes' => [
                'titulo'              => 'Gracias por ser parte de Meganet',
                'destacado'           => 'Meganet',
                'descripcion'         => 'Tu confianza nos impulsa a seguir conectando personas, hogares y negocios con un mejor futuro.',
                'mostrar_titulo'      => true,
                'mostrar_descripcion' => true,
                'tamano'              => 'normal',
                'alineacion'          => 'izquierda',
            ],
            'tarjetas' => [
                ['clave' => 'internet', 'titulo' => 'Consulta tu internet', 'descripcion' => 'Consulta el estado de tu servicio y mantente al tanto de tu conexión.', 'icono' => 'wifi', 'color' => 'azul', 'activa' => true],
                ['clave' => 'facturas', 'titulo' => 'Descarga tus facturas', 'descripcion' => 'Consulta y descarga tus comprobantes cuando los necesites.', 'icono' => 'documento', 'color' => 'verde', 'activa' => true],
                ['clave' => 'servicios', 'titulo' => 'Administra tus servicios', 'descripcion' => 'Gestiona tus servicios y mantén tu información actualizada.', 'icono' => 'engranaje', 'color' => 'naranja', 'activa' => true],
                ['clave' => 'soporte', 'titulo' => 'Soporte en línea', 'descripcion' => 'Estamos contigo cuando nos necesitas.', 'icono' => 'audifonos', 'color' => 'violeta', 'activa' => true],
            ],
            'pantallas' => [
                'login'     => ['mostrar_identidad' => true, 'mostrar_tarjetas' => true],
                'registro'  => ['titulo' => 'Crear Cuenta', 'subtitulo' => 'Portal Cliente Meganet', 'mostrar_identidad' => true, 'mostrar_tarjetas' => false],
                'recuperar' => ['titulo' => 'Recuperar Contraseña', 'subtitulo' => 'Portal Cliente Meganet', 'mostrar_identidad' => true, 'mostrar_tarjetas' => false],
            ],
        ];
    }

    public static function claveIconos(): array
    {
        return array_keys(self::ICONOS);
    }

    public static function claveColores(): array
    {
        return array_keys(self::COLORES_TARJETA);
    }

    public static function svgIcono(string $clave): string
    {
        $paths = self::ICONOS[$clave][1] ?? self::ICONOS['estrella'][1];

        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $paths . '</svg>';
    }

    /** @return array{desde: string, hasta: string, sombra: string} */
    public static function colorTarjeta(string $clave): array
    {
        $c = self::COLORES_TARJETA[$clave] ?? self::COLORES_TARJETA['azul'];

        return ['desde' => $c[1], 'hasta' => $c[2], 'sombra' => $c[3]];
    }

    /** Catálogo para el panel administrativo (los SVG salen de este archivo, no del usuario). */
    public static function paraPanel(): array
    {
        $iconos = [];
        foreach (self::ICONOS as $clave => [$etiqueta]) {
            $iconos[] = ['clave' => $clave, 'etiqueta' => $etiqueta, 'svg' => self::svgIcono($clave)];
        }
        $colores = [];
        foreach (self::COLORES_TARJETA as $clave => [$etiqueta, $desde, $hasta]) {
            $colores[] = ['clave' => $clave, 'etiqueta' => $etiqueta, 'desde' => $desde, 'hasta' => $hasta];
        }

        return [
            'iconos'  => $iconos,
            'colores' => $colores,
            'minimos' => self::MINIMOS,
            'peso_maximo_kb' => (int) config('portalcliente.apariencia.peso_maximo_kb', 10240),
            'formatos' => ['jpg', 'jpeg', 'png', 'webp'],
        ];
    }

    /** Etiqueta legible de una ruta de configuración (para el resumen de cambios). */
    public static function etiqueta(string $ruta): string
    {
        static $mapa = [
            'fondo.escritorio'              => 'Apariencia › Fotografía de escritorio',
            'fondo.movil'                   => 'Apariencia › Imagen para móviles',
            'fondo.respaldo'                => 'Apariencia › Imagen de respaldo',
            'fondo.posicion_x'              => 'Apariencia › Posición horizontal',
            'fondo.posicion_y'              => 'Apariencia › Posición vertical',
            'fondo.degradado'               => 'Apariencia › Intensidad del degradado',
            'fondo.oscurecer'               => 'Apariencia › Oscurecimiento',
            'fondo.opacidad_imagen'         => 'Apariencia › Transparencia de la imagen',
            'identidad.logo'                => 'Identidad › Logotipo',
            'identidad.titulo'              => 'Identidad › Título del portal',
            'identidad.subtitulo'           => 'Identidad › Subtítulo',
            'identidad.eslogan'             => 'Identidad › Eslogan',
            'identidad.mostrar_eslogan'     => 'Identidad › Mostrar eslogan',
            'identidad.colores.primario'    => 'Identidad › Color corporativo',
            'identidad.colores.profundo'    => 'Identidad › Azul profundo',
            'identidad.colores.noche'       => 'Identidad › Azul nocturno',
            'identidad.colores.electrico'   => 'Identidad › Azul eléctrico',
            'identidad.colores.acento'      => 'Identidad › Color de acento',
            'identidad.tarjetas.opacidad'   => 'Identidad › Opacidad de las tarjetas',
            'identidad.tarjetas.radio'      => 'Identidad › Esquinas de las tarjetas',
            'identidad.formulario.opacidad' => 'Identidad › Opacidad del formulario',
            'identidad.formulario.desenfoque' => 'Identidad › Desenfoque del formulario',
            'identidad.formulario.radio'    => 'Identidad › Esquinas del formulario',
            'mensajes.titulo'               => 'Mensajes › Título principal',
            'mensajes.destacado'            => 'Mensajes › Palabra destacada',
            'mensajes.descripcion'          => 'Mensajes › Descripción',
            'mensajes.mostrar_titulo'       => 'Mensajes › Mostrar título',
            'mensajes.mostrar_descripcion'  => 'Mensajes › Mostrar descripción',
            'mensajes.tamano'               => 'Mensajes › Tamaño',
            'mensajes.alineacion'           => 'Mensajes › Alineación',
        ];

        if (isset($mapa[$ruta])) {
            return $mapa[$ruta];
        }
        if (preg_match('/^pantallas\.(login|registro|recuperar)\.(\w+)$/', $ruta, $m)) {
            $pantalla = ['login' => 'Iniciar sesión', 'registro' => 'Crear cuenta', 'recuperar' => 'Recuperar contraseña'][$m[1]];
            $campo = [
                'titulo' => 'título', 'subtitulo' => 'subtítulo',
                'mostrar_identidad' => 'mostrar mensaje de agradecimiento', 'mostrar_tarjetas' => 'mostrar tarjetas',
            ][$m[2]] ?? $m[2];

            return "Pantallas › {$pantalla} › {$campo}";
        }

        return $ruta;
    }
}
