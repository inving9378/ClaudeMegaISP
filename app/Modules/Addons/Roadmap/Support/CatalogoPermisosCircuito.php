<?php

namespace App\Modules\Addons\Roadmap\Support;

/**
 * Qué habilita cada permiso del circuito y qué se recomienda para cada rol.
 *
 * La recomendación es exactamente eso: una sugerencia. La decisión es de Irving y se
 * guarda aunque la contradiga (queda marcada como tal, con fecha y autor). El criterio
 * que la genera es el mismo del circuito: lo que puede DISPARAR trabajo automático o
 * AMPLIAR el alcance de la automatización se recomienda con cautela; lo que solo mira,
 * o lo que FRENA, se recomienda conceder.
 */
class CatalogoPermisosCircuito
{
    /** 'conceder' = recomendado sí · 'revisar' = concédelo solo a quien deba · 'negar' = no. */
    public const PERMISOS = [
        'circuito.pause' => [
            'habilita'      => 'Poner y quitar el freno de mano del circuito desde la Torre.',
            'consecuencia'  => 'Quitarlo hace que el circuito vuelva a lanzar vueltas automáticas.',
            'recomendacion' => ['super-administrator' => 'conceder', 'DESARROLLADOR' => 'conceder'],
            'porque'        => 'Frenar debe estar al alcance de cualquiera que opere el circuito: un freno que pocos pueden accionar no es un freno.',
        ],
        'circuito.decidir' => [
            'habilita'      => 'Aprobar o rechazar items de la Hoja de Ruta.',
            'consecuencia'  => 'Un item aprobado entra a la cola y puede ser tomado por una terminal.',
            'recomendacion' => ['super-administrator' => 'conceder', 'DESARROLLADOR' => 'revisar'],
            'porque'        => 'Aprobar es lo que convierte una propuesta en trabajo real. Concédelo solo a quien responda por el resultado.',
        ],
        'circuito.disparar' => [
            'habilita'      => 'Adelantar el scheduler y lanzar una vuelta sin esperar al cron.',
            'consecuencia'  => 'Arranca de inmediato un agente con permisos de escritura sobre el repositorio.',
            'recomendacion' => ['super-administrator' => 'conceder', 'DESARROLLADOR' => 'revisar'],
            'porque'        => 'Es el permiso que ejecuta trabajo en el acto. Es el más cercano a lo que hizo daño con el botón de Ignition.',
        ],
        'torre.config.edit' => [
            'habilita'      => 'Cambiar la configuración de la Torre, incluido el nivel del autopilot.',
            'consecuencia'  => 'Subir el nivel amplía qué items toma el circuito sin pedir permiso.',
            'recomendacion' => ['super-administrator' => 'conceder', 'DESARROLLADOR' => 'revisar'],
            'porque'        => 'Cambia las reglas del juego para todos los demás controles, no solo el suyo.',
        ],
        'torre.config.view' => [
            'habilita'      => 'Ver la configuración de la Torre.',
            'consecuencia'  => 'Solo lectura.',
            'recomendacion' => ['super-administrator' => 'conceder', 'DESARROLLADOR' => 'conceder'],
            'porque'        => 'Saber cómo está configurado el circuito no le hace daño a nadie y evita operar a ciegas.',
        ],
        'torre.cola.ver' => [
            'habilita'      => 'Ver la cola ejecutable: orden exacto de despacho y excluidos con su causa.',
            'consecuencia'  => 'Solo lectura.',
            'recomendacion' => ['super-administrator' => 'conceder', 'DESARROLLADOR' => 'conceder'],
            'porque'        => 'Es la vista que explica por qué un item no avanza. Ocultarla genera trabajo de soporte.',
        ],
        'torre.salud.manage' => [
            'habilita'      => 'Gestionar los indicadores de salud de la Torre.',
            'consecuencia'  => 'Puede silenciar avisos que de otro modo se verían.',
            'recomendacion' => ['super-administrator' => 'conceder', 'DESARROLLADOR' => 'revisar'],
            'porque'        => 'Silenciar un aviso es justo lo que hizo que 268.896 excepciones pasaran dos días inadvertidas.',
        ],
        'torre.terminales.editar_avatar' => [
            'habilita'      => 'Cambiar el avatar de las terminales del circuito.',
            'consecuencia'  => 'Cosmético.',
            'recomendacion' => ['super-administrator' => 'conceder', 'DESARROLLADOR' => 'conceder'],
            'porque'        => 'No toca ninguna compuerta.',
        ],
    ];

    public const ROLES = ['super-administrator', 'DESARROLLADOR'];

    public static function recomendacion(string $permiso, string $rol): string
    {
        return self::PERMISOS[$permiso]['recomendacion'][$rol] ?? 'revisar';
    }
}
