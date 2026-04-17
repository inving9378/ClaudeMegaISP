<?php

namespace App\Console\Commands\Scripts;

use App\Http\Repository\NetworkIpRepository;
use App\Models\NetworkIp;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CrearSqlConTodasLasTablasVaciasExceptoLasDeConfig extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:crear-sql-con-todas-las-tablas-vacias-excepto-las-de-config';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Crear un SQL con todas las tablas vacías excepto las de config.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        set_time_limit(0);
        ini_set('memory_limit', '8912M');

        $db = env('DB_DATABASE', 'forge');
        $user = env('DB_USERNAME', 'forge');
        $pass = env('DB_PASSWORD', '');
        $host = env('DB_HOST', 'localhost');

        //la tabla network_ips debe hacerse el vaciar ip antes

        $networkIps = NetworkIp::all();
        foreach ($networkIps as $networkIp) {
            $networkIpRepository = new NetworkIpRepository();
            $networkIpRepository->removeUsedIp($networkIp);
        }

        $tablasQueVanVacias = [
            'billing_configurations',
            'billing_addresses',
            'balances',
            'app_layout_configurations',
            'activity_log',
            'boxes',
            'box_zones',
            'change_plan_internet_clients',
            'change_plan_voz_clients',
            'client_additional_information',
            'client_bundle_services',
            'client_custom_services',
            'client_grace_periods',
            'client_internet_services',
            'client_invoices',
            'client_logs',
            'client_main_information',
            'client_payment_promises',
            'client_payment_services',
            'client_serviceables',
            'client_users',
            'client_voz_services',
            'clients',
            'commissions',
            'commissions_details',
            'commissions_rules',
            'commissions_rules_sellers',
            'credential_images',
            'crm_lead_information',
            'crm_main_information',
            'crms',
            'default_values',
            'document_clients',
            'document_crms',
            'document_templates',
            'document_type_templates',
            'files',
            'log_activities',
            'mikrotik_client_ppoes',
            'mikrotik_client_hostpot_users',
            'mikrotik_client_hostpot_radius',
            'migrations',
            'model_has_permissions',
            'model_has_roles',
            'notifications',
            'observation_tasks',
            'payments',
            'payments_sellers',
            'payments_details',
            'receipts',
            'reminders_configurations',
            'seller_status',
            'seller_types',
            'sales',
            'sellers',
            'service_in_address_lists',
            'setting_debt_payment_client_recurrents',
            'setting_debt_payment_client_customs',
            'task_notifications',
            'task_user',
            'tasks',
            'team_user',
            'teams',
            'template_tasks',
            'template_verifications',
            'transaction_logs',
            'transactions',
            'user_column_datatable_modules',
            'users',
            'work_flows',
            'municipalities',
            'colonies',
            'states',
            'networks',
            'network_ips',
            'tickets',
            'nomenclatures',
            'personal_access_tokens'
        ];

        try {
            // Crear archivos temporales para los dumps
            $fullDumpPath = storage_path('sql_test/full_dump_temp.sql');
            $structureDumpPath = storage_path('sql_test/structure_dump_temp.sql');

            // Crear directorio si no existe
            $this->createDirectory(storage_path('sql_test'));

            // Dump con datos completos excepto las tablas vacías
            $dumpFull = new \Ifsnop\Mysqldump\Mysqldump(
                "mysql:host=$host;port=" . env('DB_PORT', 3306) . ";dbname=$db",
                $user,
                $pass,
                [
                    'exclude-tables' => $tablasQueVanVacias,
                    'reset-auto-increment' => true,
                    'skip-triggers' => true,
                ],
            );
            $dumpFull->start($fullDumpPath);

            // Dump solo con estructuras de tablas vacías
            $dumpStructure = new \Ifsnop\Mysqldump\Mysqldump(
                "mysql:host=$host;port=" . env('DB_PORT', 3306) . ";dbname=$db",
                $user,
                $pass,
                [
                    'include-tables' => $tablasQueVanVacias,
                    'no-data' => true,
                    'exclude-tables' => [
                        'personal_access_tokens',
                        'migrations'
                    ],
                    'skip-triggers' => true,
                ]
            );
            $dumpStructure->start($structureDumpPath);

            // Combinar ambos dumps en un solo archivo
            $combinedSql = file_get_contents($fullDumpPath) . "\n\n" . file_get_contents($structureDumpPath);

            // Guardar el archivo combinado
            $filePath = $this->createFile($combinedSql);

            // Limpiar archivos temporales
            unlink($fullDumpPath);
            unlink($structureDumpPath);

            Log::info('Backup filtrado de la base de datos generado en: ' . $filePath);
            $this->info('Backup generado con éxito en: ' . $filePath);
        } catch (\Exception $e) {
            Log::error('Error al generar el backup filtrado: ' . $e->getMessage());
            $this->error('Error: ' . $e->getMessage());
        }

        activity()->log('Backup filtrado de BD generado');
    }

    /**
     * Crea un archivo SQL en el directorio especificado.
     */
    public function createFile($sql)
    {
        // Define la ruta dentro de storage
        $directory = storage_path('sql_test');

        // Crea la carpeta si no existe
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true); // Permisos y creación de carpetas recursiva
        }

        // Define la ruta completa del archivo
        $filePath = $directory . '/sql_test_unit.sql';

        // Escribe el contenido en el archivo
        file_put_contents($filePath, $sql);
        $this->info("Archivo SQL generado con éxito en storage/sql_test/sql_test_unit.sql");

        return $filePath;
    }

    /**
     * Crea un directorio si no existe.
     */
    private function createDirectory($path)
    {
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }
    }
}
