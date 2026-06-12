<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Completa el schema de ps_endpoint_id_ips al estándar Asterisk 20.19.0.
 *
 * Tabla existente tiene solo: id(40), endpoint(40), match(80).
 * El árbol alembic de Asterisk 20 (contrib/ast-db-manage/config/versions/) añade:
 *   - srv_lookups   ENUM multi-valor  (28ab27a7826d)
 *   - match_header  VARCHAR(255)       (465e70e8c337)
 *   - match_request_uri VARCHAR(255)   (cf150a175fd3)
 *   - id/endpoint expandidos a VARCHAR(255)
 *
 * Mapeo en /etc/asterisk/res_config_mysql.conf:
 *   [res_pjsip_endpoint_identifier_ip]
 *   identify=realtime,ps_endpoint_id_ips
 *
 * NOTA: transport fue añadido (d5122576cca8) y luego revertido (bd9c5159c7ea).
 * No se incluye.
 */
return new class extends Migration
{
    protected $connection = 'asterisk_rt';

    public function up(): void
    {
        $db = DB::connection('asterisk_rt');

        // Expandir id VARCHAR(40) → VARCHAR(255) NOT NULL
        $idType = $this->getColumnType($db, 'id');
        if ($idType && str_contains(strtolower($idType), 'varchar(40)')) {
            $db->statement("ALTER TABLE ps_endpoint_id_ips MODIFY COLUMN `id` VARCHAR(255) NOT NULL");
        }

        // Expandir endpoint VARCHAR(40) → VARCHAR(255) NULL
        $endpointType = $this->getColumnType($db, 'endpoint');
        if ($endpointType && str_contains(strtolower($endpointType), 'varchar(40)')) {
            $db->statement("ALTER TABLE ps_endpoint_id_ips MODIFY COLUMN `endpoint` VARCHAR(255) NULL DEFAULT NULL");
        }

        // srv_lookups — enum multi-valor canónico de Asterisk 20
        if (!Schema::connection('asterisk_rt')->hasColumn('ps_endpoint_id_ips', 'srv_lookups')) {
            $db->statement(
                "ALTER TABLE ps_endpoint_id_ips ADD COLUMN `srv_lookups` " .
                "ENUM('0','1','off','on','false','true','no','yes') NULL DEFAULT NULL"
            );
        }

        // match_header — para identificar por cabecera SIP
        if (!Schema::connection('asterisk_rt')->hasColumn('ps_endpoint_id_ips', 'match_header')) {
            $db->statement(
                "ALTER TABLE ps_endpoint_id_ips ADD COLUMN `match_header` VARCHAR(255) NULL DEFAULT NULL"
            );
        }

        // match_request_uri — para identificar por Request-URI
        if (!Schema::connection('asterisk_rt')->hasColumn('ps_endpoint_id_ips', 'match_request_uri')) {
            $db->statement(
                "ALTER TABLE ps_endpoint_id_ips ADD COLUMN `match_request_uri` VARCHAR(255) NULL DEFAULT NULL"
            );
        }
    }

    public function down(): void
    {
        $db = DB::connection('asterisk_rt');

        foreach (['match_request_uri', 'match_header', 'srv_lookups'] as $col) {
            if (Schema::connection('asterisk_rt')->hasColumn('ps_endpoint_id_ips', $col)) {
                $db->statement("ALTER TABLE ps_endpoint_id_ips DROP COLUMN `{$col}`");
            }
        }

        // Revertir a VARCHAR(40) solo si actualmente son VARCHAR(255)
        $db->statement("ALTER TABLE ps_endpoint_id_ips MODIFY COLUMN `id` VARCHAR(40) NOT NULL");
        $db->statement("ALTER TABLE ps_endpoint_id_ips MODIFY COLUMN `endpoint` VARCHAR(40) NULL DEFAULT NULL");
    }

    private function getColumnType($db, string $col): ?string
    {
        $rows = $db->select("SHOW COLUMNS FROM `ps_endpoint_id_ips`");
        foreach ($rows as $row) {
            if ($row->Field === $col) {
                return $row->Type;
            }
        }
        return null;
    }
};
