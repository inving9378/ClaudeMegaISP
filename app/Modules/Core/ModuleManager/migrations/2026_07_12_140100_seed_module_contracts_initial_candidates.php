<?php

use App\Modules\Core\ModuleManager\Models\ModuleContract;
use App\Modules\Core\ModuleManager\Models\ModuleContractConsumer;
use Illuminate\Database\Migrations\Migration;

/**
 * Siembra idempotente de los "primeros candidatos" identificados en la auditoría
 * Fase 1 del item roadmap #308 (mapa proveedor->consumidores + riesgos R1/R2/R4/R5),
 * con los dueños ya decididos por Irving en Fase 2. Alcance recortado de Fase 3:
 * solo el catálogo inicial, SIN workflow de deprecación ni auto-registro por módulo
 * (queda para un item de seguimiento).
 */
return new class extends Migration
{
    public function up(): void
    {
        $sinModulo = '(sin módulo — app/Services)';

        $contratos = [
            [
                'modulo_proveedor' => 'Marketing',
                'tipo' => 'servicio',
                'nombre' => 'EvolutionApiService',
                'dueno_designado' => 'WhatsAppAgent',
                'contrato' => ['riesgo' => 'R1', 'nota' => 'Duplicado con WhatsAppAgent\\Services\\EvolutionApiService; el gateway designado es WhatsAppGateway. Ver items #203/#212.'],
                'consumidores' => ['Domiciliacion', 'Embajadores', 'Flotas', 'Payments', 'WarRoom', 'WhatsAppAgent'],
            ],
            [
                'modulo_proveedor' => 'WhatsAppAgent',
                'tipo' => 'servicio',
                'nombre' => 'WhatsAppGateway',
                'dueno_designado' => 'WhatsAppAgent',
                'contrato' => ['riesgo' => 'R1', 'nota' => 'Gateway único designado por CLAUDE.md para WhatsApp.'],
                'consumidores' => ['Payments'],
            ],
            [
                'modulo_proveedor' => 'Marketing',
                'tipo' => 'servicio',
                'nombre' => 'ClaudeApiClient',
                'dueno_designado' => 'IA',
                'contrato' => ['riesgo' => 'R2', 'nota' => 'Consumido directo por Payments/Talento saltando el módulo IA (dueño designado de proveedores IA).'],
                'consumidores' => ['Payments', 'Talento'],
            ],
            [
                'modulo_proveedor' => 'Core',
                'tipo' => 'servicio',
                'nombre' => 'Core (base común)',
                'dueno_designado' => 'Core',
                'contrato' => ['riesgo' => 'R4', 'nota' => 'Base común sin versión ni contrato declarado; blast radius amplio en cualquier cambio.'],
                'consumidores' => ['CobranzaBlaster', 'DevTools', 'Embajadores', 'EvaluadorEmpresarial', 'Finanzas', 'Manual', 'MegaFamilia', 'Mensajes', 'Payments', 'PortalCliente', 'PortalPago', 'Scheduling', 'SmartImportExport', 'Talento', 'Vendedores', 'VoIP', 'WhatsAppAgent'],
            ],
            [
                'modulo_proveedor' => 'Contracts',
                'tipo' => 'servicio',
                'nombre' => 'Contracts (interfaces base)',
                'dueno_designado' => 'Core',
                'contrato' => ['riesgo' => 'R4', 'nota' => 'Interfaces base compartidas (app/Modules/Contracts).'],
                'consumidores' => ['Core', 'Demo', 'Flotas', 'Roadmap', 'Talento', 'Vendedores', 'VoIP'],
            ],
            [
                'modulo_proveedor' => $sinModulo,
                'tipo' => 'servicio',
                'nombre' => 'OLTsService',
                'dueno_designado' => 'GestionRed',
                'contrato' => ['riesgo' => 'R5', 'nota' => 'Vive en app/Services sin módulo dueño; dueño natural = GestionRed (no existe módulo OLTs).'],
                'consumidores' => ['Core', 'GestionRed', 'Payments', 'Talento'],
            ],
            [
                'modulo_proveedor' => 'Payments',
                'tipo' => 'servicio',
                'nombre' => 'PaymentApplicationService',
                'dueno_designado' => 'Payments',
                'contrato' => ['riesgo' => 'R5', 'nota' => 'Aplica pagos (Paso 2/F4 conciliación WhatsApp); consumido cross-módulo.'],
                'consumidores' => ['WhatsAppAgent'],
            ],
        ];

        foreach ($contratos as $c) {
            $contract = ModuleContract::firstOrCreate(
                [
                    'modulo_proveedor' => $c['modulo_proveedor'],
                    'tipo' => $c['tipo'],
                    'nombre' => $c['nombre'],
                ],
                [
                    'version' => '1.0.0',
                    'estado' => 'activa',
                    'dueno_designado' => $c['dueno_designado'],
                    'contrato' => $c['contrato'],
                    'fecha_estado' => now(),
                ]
            );

            foreach ($c['consumidores'] as $modulo) {
                ModuleContractConsumer::firstOrCreate([
                    'contract_id' => $contract->id,
                    'modulo_consumidor' => $modulo,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Catálogo inicial — no se revierte automáticamente (evita borrar registros
        // que ya hayan sido editados/ampliados a mano tras la siembra).
    }
};
