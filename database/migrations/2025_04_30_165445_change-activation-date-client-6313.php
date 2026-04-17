<?php

use App\Services\LogService;
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
        //fecha actual 2024-06-25
        DB::statement("update client_main_information set activation_date='2024-06-08T15:22' where client_id=6313");
        DB::statement("update payments set date='2024-06-08' where id=92874");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
