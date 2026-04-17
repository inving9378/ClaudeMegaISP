<?php

use App\Models\CommandConfig;
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
       CommandConfig::where('process_name','removeservicefromaddresslist-deployed-charged:process')->first()->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('command_configs', function (Blueprint $table) {
            //
        });
    }
};
