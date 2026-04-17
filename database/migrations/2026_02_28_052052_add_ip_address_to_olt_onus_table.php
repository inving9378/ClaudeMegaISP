<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('olt_onus', function (Blueprint $table) {
            $table->string('subnet_mask')->nullable()->after('ip_address');
            $table->string('default_gateway')->nullable()->after('ip_address');
            $table->string('dns1')->nullable()->after('ip_address');
            $table->string('dns2')->nullable()->after('ip_address');
            $table->string('username')->nullable()->after('ip_address');
            $table->string('password')->nullable()->after('ip_address');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('olt_onus', function (Blueprint $table) {
            $table->dropColumn([
                'subnet_mask',
                'default_gateway',
                'dns1',
                'dns2',
                'username',
                'password',
            ]);
        });
    }
};
