<?php

namespace App\Services;

use App\Http\Repository\NetworkIpRepository;

class ServerInfoService
{
    protected $clientService;
    /**
     * Recupera informe de estado del servidor desde el archivo de informes de estados. Devuelve un array asociativo.
     *
     * @return array Un array asociativo que contiene la siguiente información:
     *               ['key']=valor
     *               - instant: la hora actual
     *               - hostname: el nombre del servidor
     *               - osInfo: la información del sistema operativo
     *               - uptime: el tiempo de actividad del servidor
     *               - timezone: la zona horaria del servidor
     *               - localDateTime: la fecha y hora local
     *               - phpVersion: la versión de PHP
     *               - cpuInfo: la información del procesador
     *               - cpuCores: el número de núcleos del procesador
     *               - cpuUsedPct: el porcentaje de uso del procesador
     *               - memUsed: la memoria utilizada
     *               - memUsedPct: el porcentaje de uso de la memoria
     *               - memTotal: la memoria total
     *               - swapUsed: el espacio de swap utilizado
     *               - swapUsedPct: el porcentaje de uso del espacio de swap
     *               - swapTotal: el espacio de swap total
     *               - processCount: el número de procesos
     *               - systemLoad1: la carga del sistema en los últimos 1 minutos
     *               - systemLoad5: la carga del sistema en los últimos 5 minutos
     *               - systemLoad15: la carga del sistema en los últimos 15 minutos
     *               - hdRootUsed: el espacio utilizado en la partición raíz
     *               - hdRootUsedPct: el porcentaje de uso del espacio en la partición raíz
     *               - hdRootTotal: el espacio total en la partición raíz
     *               - hdRootFree: el espacio libre en la partición raíz
     *               - hdRootFreePct: el porcentaje de espacio libre en la partición raíz
     *               - hdTmpUsed: el espacio utilizado en la partición tmp
     *               - hdTmpUsedPct: el porcentaje de uso del espacio en la partición tmp
     *               - hdTmpTotal: el espacio total en la partición tmp
     *               - hdTmpFree: el espacio libre en la partición tmp
     *               - hdTmpFreePct: el porcentaje de espacio libre en la partición tmp
     *               - hdVarUsed: el espacio utilizado en la partición var
     *               - hdVarUsedPct: el porcentaje de uso del espacio en la partición var
     *               - hdVarTotal: el espacio total en la partición var
     *               - hdVarFree: el espacio libre en la partición var
     *               - hdVarFreePct: el porcentaje de espacio libre en la partición var
     *               - hdAppUsed: el espacio utilizado en la partición app
     *               - hdAppUsedPct: el porcentaje de uso del espacio en la partición app
     *               - hdAppTotal: el espacio total en la partición app
     *               - hdAppFree: el espacio libre en la partición app
     *               - hdAppFreePct: el porcentaje de espacio libre en la partición app
     */
    public function serverInform()
    {
        $serverInformFile = storage_path('server_status/status_server.txt');

        if (!file_exists($serverInformFile))
            return [];

        $data = file_get_contents($serverInformFile);
        $lines = explode("\n", $data);
        $inform = [];

        $inform['instant'] = $lines[0];
        $inform['hostname'] = $lines[1];
        $inform['osInfo'] = $lines[2];
        $inform['uptime'] = $lines[3];
        $inform['timezone'] = $lines[4];
        $inform['localDateTime'] = $lines[5];
        $inform['phpVersion'] = $lines[6];

        $inform['cpuInfo'] = $lines[7];
        $inform['cpuCores'] = $lines[8];
        $inform['cpuUsedPct'] = $lines[9];
        $inform['memUsed'] = $lines[10];
        $inform['memUsedPct'] = $lines[11];
        $inform['memTotal'] = $lines[12];
        $inform['swapUsed'] = $lines[13];
        $inform['swapUsedPct'] = $lines[14];
        $inform['swapTotal'] = $lines[15];

        $inform['processCount'] = $lines[16];
        $inform['systemLoad1'] = $lines[17];
        $inform['systemLoad5'] = $lines[18];
        $inform['systemLoad15'] = $lines[19];

        $inform['hdRootUsed'] = $lines[20];
        $inform['hdRootUsedPct'] = $lines[21];
        $inform['hdRootTotal'] = $lines[22];
        $inform['hdRootFree'] = $lines[23];
        $inform['hdRootFreePct'] = $lines[24];

        $inform['hdTmpUsed'] = $lines[25];
        $inform['hdTmpUsedPct'] = $lines[26];
        $inform['hdTmpTotal'] = $lines[27];
        $inform['hdTmpFree'] = $lines[28];
        $inform['hdTmpFreePct'] = $lines[29];

        $inform['hdVarUsed'] = $lines[30];
        $inform['hdVarUsedPct'] = $lines[31];
        $inform['hdVarTotal'] = $lines[32];
        $inform['hdVarFree'] = $lines[33];
        $inform['hdVarFreePct'] = $lines[34];

        $inform['hdAppUsed'] = $lines[35];
        $inform['hdAppUsedPct'] = $lines[36];
        $inform['hdAppTotal'] = $lines[37];
        $inform['hdAppFree'] = $lines[38];
        $inform['hdAppFreePct'] = $lines[39];
        $inform['lastBackupDb'] = $lines[40];
        return $inform;
    }
}
