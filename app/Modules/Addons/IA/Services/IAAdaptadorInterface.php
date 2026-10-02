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
     *        Adjuntos del mensaje actual (base64 sin prefijo data:). Imágenes y,
     *        si el proveedor lo soporta (IAProveedor::soportaPdf), application/pdf.
     * @param string|null $systemPrompt Contexto/instrucciones del sistema (inyectadas por ContextoProyectoService).
     * @param array $opciones Ajustes por llamada, todos opcionales (pisan config_extra del proveedor):
     *        max_tokens (int), temperatura (float), timeout (seg, default 120),
     *        reintentos (int, default 2; solo ante 429/5xx), json (bool: pedir JSON
     *        nativo cuando el proveedor lo soporta — el prompt debe pedir un OBJETO JSON).
     *
     * @return array{texto:string, tokens_input:?int, tokens_output:?int, fin:string, raw:array}
     *         fin: 'completo' | 'max_tokens' | 'otro' (normalizado entre proveedores).
     */
    public function enviarMensaje(array $historial, string $mensaje, array $imagenes = [], ?string $systemPrompt = null, array $opciones = []): array;

    /**
     * Hace una llamada mínima al proveedor para validar conectividad y credenciales.
     */
    public function probarConexion(): bool;

    /**
     * Construye el payload que se enviará al endpoint del proveedor.
     */
    public function construirPayload(array $historial, string $mensaje, array $imagenes, ?string $systemPrompt = null, array $opciones = []): array;

    /**
     * Extrae el texto de la respuesta del proveedor.
     */
    public function parsearRespuesta(array $respuesta): string;
}
