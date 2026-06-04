<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing_config', function (Blueprint $table) {
            $table->boolean('envio_pausado')->default(false)->after('send_ticket_email');
        });

        // Activa la pausa inmediatamente
        DB::table('billing_config')->update(['envio_pausado' => true]);
    }

    public function down(): void
    {
        Schema::table('billing_config', function (Blueprint $table) {
            $table->dropColumn('envio_pausado');
        });
    }
};
