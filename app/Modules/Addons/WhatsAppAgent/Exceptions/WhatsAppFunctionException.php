<?php

namespace App\Modules\Addons\WhatsAppAgent\Exceptions;

use App\Modules\Addons\WhatsAppAgent\Models\WhatsAppFunction;
use App\Modules\Addons\WhatsAppAgent\Models\WhatsAppInstance;
use RuntimeException;

/**
 * Excepción de dominio de la capa de funciones. Tiene render() → 422 con mensaje en
 * español, así CUALQUIER path que la dispare (service directo, observer al borrar una
 * línea desde WhatsAppInstanceController::destroy, etc.) responde limpio sin try/catch.
 */
class WhatsAppFunctionException extends RuntimeException
{
    /** @var string[] nombres de funciones afectadas (para la UI) */
    public array $functions = [];

    public static function wouldOrphan(WhatsAppFunction $function): self
    {
        $e = new self("No puedes quitar la última línea de «{$function->name}». Reasígnala a otra línea primero.");
        $e->functions = [$function->name];
        return $e;
    }

    /** @param WhatsAppFunction[] $functions */
    public static function wouldOrphanOnRemoval(WhatsAppInstance $instance, array $functions): self
    {
        $names = array_map(fn (WhatsAppFunction $f) => $f->name, $functions);
        $list  = implode(', ', $names);
        $e = new self("No puedes quitar/desconectar «{$instance->name}»: es la única línea de: {$list}. Reasigna esas funciones a otra línea primero.");
        $e->functions = $names;
        return $e;
    }

    public function render($request)
    {
        return response()->json([
            'message'   => $this->getMessage(),
            'functions' => $this->functions,
        ], 422);
    }
}
