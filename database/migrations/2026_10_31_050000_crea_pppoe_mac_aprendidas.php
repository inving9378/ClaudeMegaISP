<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('pppoe_mac_aprendidas')) {
            return;
        }
        Schema::create('pppoe_mac_aprendidas', function (Blueprint $t) {
            $t->id();
            $t->string('username', 191)->index();
            $t->unsignedBigInteger('client_internet_service_id')->nullable()->index();
            $t->string('mac', 17);
            $t->string('origen', 20)->default('radius');
            $t->timestamp('primera_vez')->nullable();
            $t->timestamp('ultima_vez')->nullable();
            $t->unsignedInteger('veces')->default(1);
            $t->timestamps();
            $t->unique(['username', 'mac']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pppoe_mac_aprendidas');
    }
};
