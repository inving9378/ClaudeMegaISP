<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Item #1139 (#576 Fase 2) — aditiva. Zona = NAS (IP del router que atiende el PPPoE).
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('pppoe_csid_zonas')) {
            Schema::create('pppoe_csid_zonas', function (Blueprint $t) {
                $t->id();
                $t->string('nas_ip', 45)->unique();
                $t->string('modo', 10)->default('apagado'); // apagado | aprender | aplicar
                $t->timestamp('aprendiendo_desde')->nullable();
                $t->timestamp('aplicando_desde')->nullable();
                $t->string('cambiado_por', 100)->nullable();
                $t->timestamps();
            });
        }
        if (Schema::hasTable('pppoe_mac_aprendidas')) {
            Schema::table('pppoe_mac_aprendidas', function (Blueprint $t) {
                if (!Schema::hasColumn('pppoe_mac_aprendidas', 'nas_ip')) {
                    $t->string('nas_ip', 45)->nullable()->index();
                }
                if (!Schema::hasColumn('pppoe_mac_aprendidas', 'mac_crudo')) {
                    $t->string('mac_crudo', 32)->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pppoe_csid_zonas');
        if (Schema::hasTable('pppoe_mac_aprendidas')) {
            Schema::table('pppoe_mac_aprendidas', function (Blueprint $t) {
                foreach (['nas_ip', 'mac_crudo'] as $c) {
                    if (Schema::hasColumn('pppoe_mac_aprendidas', $c)) {
                        $t->dropColumn($c);
                    }
                }
            });
        }
    }
};
