<?php

namespace App\Services\Asistente\Herramientas;

use App\Models\User;
use App\Http\Requests\module\crm\CrmCreateRequest;
use App\Modules\Core\CRM\Controllers\CrmController;

/**
 * Registra un prospecto en el CRM por el MISMO camino que la pantalla Crear prospecto
 * (CrmCreateRequest + CrmController::store): mismas reglas (nombre repetido, correo repetido...),
 * mismo evento y misma bitácora del CRM.
 */
class CrearProspecto extends Herramienta
{
    use DatosAlta;

    public function nombre(): string
    {
        return 'crear_prospecto';
    }

    public function descripcion(): string
    {
        return 'Registra un prospecto (posible cliente) en el CRM. Pide nombre con apellido paterno y materno, '
            . 'teléfono y colonia (usa buscar_colonia); el correo y el domicilio son opcionales. '
            . 'Es la opción cuando solo se tienen datos de contacto. Llámala con lo que ya tengas aunque falten datos: te '
            . 'responde la lista exacta de lo que falta. Prepara el registro y el usuario lo confirma.';
    }

    public function permiso(): string|array|null
    {
        return 'crm_add_crm';
    }

    public function esCambio(): bool
    {
        return true;
    }

    public function parametros(): array
    {
        return ['type' => 'object', 'properties' => $this->propiedadesAlta(), 'required' => ['nombre']];
    }

    public function preparar(User $user, array $args): array
    {
        ['datos' => $datos, 'faltan' => $faltan, 'colonia' => $colonia] = $this->datosComunes($args);
        if ($datos['phone'] === '') {
            $faltan[] = 'teléfono';
        }
        if ($faltan) {
            return ['error' => 'Para registrar el prospecto me falta: ' . implode(', ', $faltan) . '.'];
        }

        $datos += $this->fijos($user, $datos);
        if ($error = Formulario::errores(Formulario::peticion(CrmCreateRequest::class, '/crm/add', $datos, $user))) {
            return self::rechazo('prospectos', $error, $datos);
        }

        return [
            'resumen'    => "Registrar prospecto nuevo en el CRM:\n" . implode("\n", $this->lineasResumen($datos, $colonia))
                . "\n- Estado: Nuevo\n- Responsable: " . trim("{$user->name} {$user->father_last_name}"),
            'argumentos' => ['datos' => $datos],
        ];
    }

    public function ejecutar(User $user, array $args): array
    {
        $datos = ['owner_id' => $user->id] + (array) ($args['datos'] ?? []);
        $req = Formulario::peticion(CrmCreateRequest::class, '/crm/add', $datos, $user);
        if ($error = Formulario::errores($req)) {
            return ['error' => "La pantalla de prospectos ya no lo acepta: {$error}"];
        }

        $resultado = Formulario::comoPeticionActual($req, fn () => app(CrmController::class)->store($req));
        if (!is_object($resultado) || !isset($resultado->id) || $resultado instanceof \Symfony\Component\HttpFoundation\Response) {
            // CrmController::store atrapa sus errores y los deja en la sesión (back()->with('error')).
            $motivo = session()->pull('error');

            return ['error' => 'No se pudo registrar el prospecto' . ($motivo ? ': ' . Formulario::textoPlano((string) $motivo) : '.')];
        }

        return [
            'ok'      => true,
            'mensaje' => "Listo, registré el prospecto número {$resultado->id}.",
            'enlace'  => $this->enlace('prospecto', $resultado->id),
            'sujeto'  => $resultado,
        ];
    }

    private function fijos(User $user, array $datos): array
    {
        return [
            'crm_status'        => 'Nuevo',
            'owner_id'          => $user->id,
            'email_is_required' => $datos['email'] !== '',
            'last_contacted'    => now()->toDateString(),
        ];
    }
}
