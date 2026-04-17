<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        Schema::table('client_main_information', function (Blueprint $table) {
            $table->string('estado')->nullable()->change();
        });
        Schema::table('client_internet_services', function (Blueprint $table) {
            $table->string('estado')->nullable()->change();
        });
        Schema::table('client_custom_services', function (Blueprint $table) {
            $table->string('estado')->nullable()->change();
        });
        Schema::table('client_voz_services', function (Blueprint $table) {
            $table->string('estado')->nullable()->change();
        });
        Schema::table('client_bundle_services', function (Blueprint $table) {
            $table->string('estado')->nullable()->change();
        });

        \App\Models\ClientMainInformation::where('estado', \App\Http\Controllers\Utils\ComunConstantsController::STATE_ACTIVE)
            ->update(['estado' => \App\Http\Controllers\Utils\ComunConstantsController::STATE_ACTIVE]);

        \App\Models\ClientInternetService::where('estado', \App\Http\Controllers\Utils\ComunConstantsController::STATE_ACTIVE)
            ->update(['estado' => \App\Http\Controllers\Utils\ComunConstantsController::STATE_ACTIVE]);

        \App\Models\ClientCustomService::where('estado', \App\Http\Controllers\Utils\ComunConstantsController::STATE_ACTIVE)
            ->update(['estado' => \App\Http\Controllers\Utils\ComunConstantsController::STATE_ACTIVE]);

        \App\Models\ClientVozService::where('estado', \App\Http\Controllers\Utils\ComunConstantsController::STATE_ACTIVE)
            ->update(['estado' => \App\Http\Controllers\Utils\ComunConstantsController::STATE_ACTIVE]);

        \App\Models\ClientBundleService::where('estado', \App\Http\Controllers\Utils\ComunConstantsController::STATE_ACTIVE)
            ->update(['estado' => \App\Http\Controllers\Utils\ComunConstantsController::STATE_ACTIVE]);

        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
