<?php

namespace App\Console\Commands\Active;

use App\Models\ActivityLog;
use Illuminate\Console\Command;
use Spatie\Activitylog\Models\Activity;

class ServerStatusCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:server-status-command';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if (config('app.env') !== "production") {
            return false;
        }
        $instant = time();
        // Obtener la información del sistema
        $hostname = gethostname();
        $osInfo = trim(php_uname());
        $uptime = trim(shell_exec('uptime -p'));
        $timezone = date_default_timezone_get();
        $localDateTime = date('Y-m-d H:i:s');
        $phpVersion = phpversion();

        // Información del CPU
        $cpuInfo = trim(shell_exec('cat /proc/cpuinfo | grep "model name" | head -1 | cut -d: -f2'));
        $cpuCores = trim(shell_exec('nproc'));
        $cpuUsedPct = trim(shell_exec('top -bn1 | grep "Cpu(s)" | awk \'{print 100 - $8}\''));

        // Información de memoria
        $memInfo = shell_exec('free -m');
        preg_match_all('/\d+/', $memInfo, $memMatches);
        $memTotal = (int)($memMatches[0][0]);
        $memUsed = (int)$memMatches[0][1];
        $memUsedPct = (int)(($memTotal > 0) ? ($memUsed / $memTotal) * 100 : 0);

        $swapTotal = (int)($memMatches[0][2]);
        $swapUsed = (int)($memMatches[0][3]);
        $swapUsedPct = (int)(($swapTotal > 0) ? ($swapUsed / $swapTotal) * 100 : 0);

        // Información del proceso
        $processCount = trim(shell_exec('ps -e | wc -l'));

        // Información de carga del sistema
        $systemLoad = sys_getloadavg();
        $systemLoad1 = number_format($systemLoad[0], 3);
        $systemLoad5 = number_format($systemLoad[1], 3);
        $systemLoad15 = number_format($systemLoad[2], 3);

        // Información de almacenamiento
        $partitions = ['/', '/tmp', '/var', realpath(__DIR__)];
        $storageInfo = [];
        foreach ($partitions as $partition) {
            $storageData = shell_exec("df -BG $partition | tail -1");
            preg_match_all('/\S+/', $storageData, $matches);

            $total = (int)$matches[0][1];
            $used = (int)$matches[0][2];
            $free = $matches[0][3];
            $usedPct = number_format(($used / $total) * 100, 2);
            $freePct = number_format(100 - $usedPct, 2);

            $storageInfo[$partition] = [
                'total' => $total,
                'used' => $used,
                'used_pct' => $usedPct,
                'free' => $free,
                'free_pct' => $freePct
            ];
        }

        // Desacoplar los datos de almacenamiento en variables individuales
        $hdRootTotal = $storageInfo['/']['total'];
        $hdRootUsed = $storageInfo['/']['used'];
        $hdRootUsedPct = $storageInfo['/']['used_pct'];
        $hdRootFree = $storageInfo['/']['free'];
        $hdRootFreePct = $storageInfo['/']['free_pct'];

        $hdTmpTotal = $storageInfo['/tmp']['total'];
        $hdTmpUsed = $storageInfo['/tmp']['used'];
        $hdTmpUsedPct = $storageInfo['/tmp']['used_pct'];
        $hdTmpFree = $storageInfo['/tmp']['free'];
        $hdTmpFreePct = $storageInfo['/tmp']['free_pct'];

        $hdVarTotal = $storageInfo['/var']['total'];
        $hdVarUsed = $storageInfo['/var']['used'];
        $hdVarUsedPct = $storageInfo['/var']['used_pct'];
        $hdVarFree = $storageInfo['/var']['free'];
        $hdVarFreePct = $storageInfo['/var']['free_pct'];

        $hdAppTotal = $storageInfo[realpath(__DIR__)]['total'];
        $hdAppUsed = $storageInfo[realpath(__DIR__)]['used'];
        $hdAppUsedPct = $storageInfo[realpath(__DIR__)]['used_pct'];
        $hdAppFree = $storageInfo[realpath(__DIR__)]['free'];
        $hdAppFreePct = $storageInfo[realpath(__DIR__)]['free_pct'];


        $lastActivityLogBackupDB = ActivityLog::where('description', 'Salva de BD')->orderBy('id', 'desc')->first();
        $lastBackupDb = "";
        if ($lastActivityLogBackupDB) {
            $lastBackupDb = $lastActivityLogBackupDB->created_at;
        }

        // Construir la salida
        $data = [
            $instant,
            $hostname,
            $osInfo,
            $uptime,
            $timezone,
            $localDateTime,
            $phpVersion,
            $cpuInfo,
            $cpuCores,
            $cpuUsedPct,
            $memUsed,
            $memUsedPct,
            $memTotal,
            $swapUsed,
            $swapUsedPct,
            $swapTotal,
            $processCount,
            $systemLoad1,
            $systemLoad5,
            $systemLoad15,
            $hdRootUsed,
            $hdRootUsedPct,
            $hdRootTotal,
            $hdRootFree,
            $hdRootFreePct,
            $hdTmpUsed,
            $hdTmpUsedPct,
            $hdTmpTotal,
            $hdTmpFree,
            $hdTmpFreePct,
            $hdVarUsed,
            $hdVarUsedPct,
            $hdVarTotal,
            $hdVarFree,
            $hdVarFreePct,
            $hdAppUsed,
            $hdAppUsedPct,
            $hdAppTotal,
            $hdAppFree,
            $hdAppFreePct,
            $lastBackupDb
        ];

        $this->createFile(implode("\n", $data));
    }

    public function createFile($data)
    {
        // Define la ruta dentro de storage
        $directory = storage_path('server_status');

        // Crea la carpeta si no existe
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true); // Permisos y creación de carpetas recursiva
        }

        // Define la ruta completa del archivo
        $filePath = $directory . '/status_server.txt';

        // Escribe el contenido en el archivo
        file_put_contents($filePath, $data);
        return $filePath;
    }
}
