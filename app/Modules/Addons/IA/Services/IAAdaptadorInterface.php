<?php

namespace App\Modules\Addons\IA\Services;

interface IAAdaptadorInterface
{
    /**
     * Envía un mensaje a la IA y retorna el contenido de texto de la respuesta.
     *
     * @param array<int, array{rol:string, contenido:string, imagenes?:array}> $historial
     *        Historial previo de la conversación. NO incluye el mensaje actual.
     * @param string $mensaje  Mensaje del usuario actual.
     * @param array<int, array{mime:string, data:string}> $imagenes
     *        Imágenes adjuntas al mensaje actual (base64 sin prefijo data:).
     * @param string|null $systemPrompt Contexto/instrucciones del sistema (inyectadas por ContextoProyectoService).
     *
     * @return array{texto:string, tokens_input:?int, tokens_output:?int, raw:array}
     */
    public function enviarMensaje(array $historial, string $mensaje, array $imagenes = [], ?string $systemPrompt = null): array;

    /**
     * Hace una llamada mínima al proveedor para validar conectividad y credenciales.
     */
    public function probarConexion(): bool;

    /**
     * Construye el payload que se enviará al endpoint del proveedor.
     */
    public function construirPayload(array $historial, string $mensaje, array $imagenes, ?string $systemPrompt = null): array;

    /**
     * Extrae el texto de la respuesta del proveedor.
     */
    public function parsearRespuesta(array $respuesta): string;
}
