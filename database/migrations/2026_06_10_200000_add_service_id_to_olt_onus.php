<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('olt_onus', function (Blueprint $table) {
            $table->unsignedBigInteger('service_id')
                ->nullable()
                ->after('client_id')
                ->comment('FK → client_internet_services.id (1 ONU = 1 servicio)');

            $table->foreign('service_id')
                ->references('id')
                ->on('client_internet_services')
                ->nullOnDelete();

            $table->index('service_id');
        });
    }

    public function down(): void
    {
        Schema::table('olt_onus', function (Blueprint $table) {
            $table->dropForeign(['service_id']);
            $table->dropIndex(['service_id']);
            $table->dropColumn('service_id');
        });
    }
};
