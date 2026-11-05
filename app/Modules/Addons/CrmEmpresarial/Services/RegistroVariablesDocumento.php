<?php

namespace App\Modules\Addons\CrmEmpresarial\Services;

use Carbon\Carbon;
use InvalidArgumentException;

/**
 * Item #602 (CRM-E 06 · Fase 3): registro central de variables de plantillas de documentos.
 *
 * Cada variable tiene nombre ({{grupo.campo}}), tipo y fuente (ruta dentro del contexto).
 * Seguridad: solo SUSTITUYE valores ya escapados; jamás evalúa ni ejecuta nada de la plantilla.
 * Una variable desconocida se deja tal cual (no se inventa un valor) y se reporta en `faltantes`.
 */
class RegistroVariablesDocumento
{
    public const TIPOS = ['texto', 'fecha', 'moneda', 'numero', 'imagen'];

    /** nombre => [tipo, fuente (grupo.ruta dentro del contexto), descripcion] */
    private const VARIABLES = [
        'cliente.razon_social' => ['texto', 'cliente.razon_social', 'Razón social del cliente'],
        'cliente.nombre' => ['texto', 'cliente.nombre', 'Nombre del cliente'],
        'cliente.rfc' => ['texto', 'cliente.rfc', 'RFC del cliente'],
        'cliente.regimen_fiscal' => ['texto', 'cliente.regimen_fiscal', 'Régimen fiscal'],
        'cliente.domicilio' => ['texto', 'cliente.domicilio', 'Domicilio fiscal'],
        'cliente.representante' => ['texto', 'cliente.representante', 'Representante legal'],
        'servicio.nombre' => ['texto', 'servicio.nombre', 'Nombre del servicio contratado'],
        'servicio.velocidad' => ['texto', 'servicio.velocidad', 'Velocidad contratada'],
        'servicio.precio_mensual' => ['moneda', 'servicio.precio_mensual', 'Mensualidad'],
        'servicio.direccion_instalacion' => ['texto', 'servicio.direccion_instalacion', 'Sitio de instalación'],
        'documento.folio' => ['texto', 'documento.folio', 'Folio del documento'],
        'documento.fecha' => ['fecha', 'documento.fecha', 'Fecha del documento'],
        'documento.vigencia_meses' => ['numero', 'documento.vigencia_meses', 'Vigencia en meses'],
        'meganet.razon_social' => ['texto', 'meganet.razon_social', 'Razón social de Meganet'],
        'meganet.rfc' => ['texto', 'meganet.rfc', 'RFC de Meganet'],
        'meganet.representante' => ['texto', 'meganet.representante', 'Representante de Meganet'],
        'firma.cliente' => ['imagen', 'firma.cliente', 'Firma del cliente'],
        'firma.meganet' => ['imagen', 'firma.meganet', 'Firma de Meganet'],
    ];

    public function definiciones(): array
    {
        $out = [];
        foreach (self::VARIABLES as $nombre => [$tipo, $fuente, $desc]) {
            $out[$nombre] = compact('tipo', 'fuente') + ['descripcion' => $desc];
        }

        return $out;
    }

    public function existe(string $nombre): bool
    {
        return isset(self::VARIABLES[$nombre]);
    }

    /** Variables {{x.y}} que aparecen en el texto (únicas, en orden). */
    public function encontrar(string $plantilla): array
    {
        preg_match_all('/\{\{\s*([a-z_]+\.[a-z_]+)\s*\}\}/', $plantilla, $m);

        return array_values(array_unique($m[1]));
    }

    /** Valor formateado y escapado para HTML de una variable; null si no hay dato. */
    public function resolver(string $nombre, array $contexto): ?string
    {
        if (! $this->existe($nombre)) {
            throw new InvalidArgumentException("Variable desconocida: {$nombre}");
        }
        [$tipo, $fuente] = self::VARIABLES[$nombre];
        $valor = data_get($contexto, $fuente);
        if ($valor === null || $valor === '' || is_array($valor) || is_object($valor)) {
            return null;
        }

        switch ($tipo) {
            case 'fecha':
                try {
                    $valor = Carbon::parse($valor)->format('d/m/Y');
                } catch (\Throwable $e) {
                    return null;
                }
                break;
            case 'moneda':
                if (! is_numeric($valor)) {
                    return null;
                }
                $valor = '$'.number_format((float) $valor, 2);
                break;
            case 'numero':
                if (! is_numeric($valor)) {
                    return null;
                }
                $valor = (string) $valor + 0;
                break;
            case 'imagen':
                // Solo data-URI de imagen raster; nunca URLs ni SVG.
                if (! preg_match('#^data:image/(png|jpeg|jpg|gif);base64,[A-Za-z0-9+/=]+$#', (string) $valor)) {
                    return null;
                }

                return '<img src="'.$valor.'" alt="firma" style="max-height:80px">';
        }

        return e((string) $valor);
    }

    /**
     * Sustituye {{variable}} por su valor escapado. No ejecuta código.
     *
     * @return array{texto:string, faltantes:array<int,string>}
     */
    public function renderizar(string $plantilla, array $contexto): array
    {
        $faltantes = [];
        $texto = preg_replace_callback('/\{\{\s*([a-z_]+\.[a-z_]+)\s*\}\}/', function ($m) use ($contexto, &$faltantes) {
            $nombre = $m[1];
            $valor = $this->existe($nombre) ? $this->resolver($nombre, $contexto) : null;
            if ($valor === null) {
                $faltantes[] = $nombre;

                return $m[0];
            }

            return $valor;
        }, $plantilla);

        return ['texto' => $texto, 'faltantes' => array_values(array_unique($faltantes))];
    }
}
